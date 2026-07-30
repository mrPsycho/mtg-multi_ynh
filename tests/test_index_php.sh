#!/bin/bash
# Renders conf/index.php in a PHP 8.2 container: checks that a user only ever
# sees its own secret, that the Telegram instructions are shown, and that the
# self-service key reset is CSRF-protected and rewrites both config files.
# Requires podman (or docker).

set -euo pipefail

repo="$(cd "$(dirname "$0")/.." && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

runtime="$(command -v podman || command -v docker)"
image="docker.io/library/php:8.2-cli"

install_dir="/srv/app"
frontend_domain="storage.googleapis.com"
frontend_hex="73746f726167652e676f6f676c65617069732e636f6d"
mkdir -p "$work/app/conf" "$work/app/www"

alice_secret="ee11111111111111111111111111111111$frontend_hex"
bob_secret="ee22222222222222222222222222222222$frontend_hex"

write_fixtures() {
    cat >"$work/app/conf/secrets.toml" <<EOF
"alice" = "$alice_secret"
"bob" = "$bob_secret"
EOF
    cat >"$work/app/conf/mtg.toml" <<EOF
bind-to = "0.0.0.0:3128"

[network]
dns = "https://1.1.1.1"

[secrets]
"alice" = "$alice_secret"
"bob" = "$bob_secret"
EOF
    rm -f "$work/app/conf/.reload"
}
write_fixtures

sed -e "s|__VERSION__|1.11.0~ynh4|g" \
    -e "s|__INSTALL_DIR__|$install_dir|g" \
    -e "s|__PATH__|/mtg-multi|g" \
    -e "s|__DOMAIN__|proxy.example.tld|g" \
    -e "s|__PORT__|3128|g" \
    -e "s|__PORT_API__|9090|g" \
    -e "s|__FRONTEND_DOMAIN__|$frontend_domain|g" \
    "$repo/conf/index.php" >"$work/app/www/index.php"

cat >"$work/render.php" <<'EOF'
<?php
// argv: user, request method, urlencoded body, Accept-Language
if (($argv[1] ?? '') !== '') {
    $_SERVER['REMOTE_USER'] = $argv[1];
}
$_SERVER['REQUEST_METHOD'] = ($argv[2] ?? '') !== '' ? $argv[2] : 'GET';
if (($argv[3] ?? '') !== '') {
    parse_str($argv[3], $_POST);
}
if (($argv[4] ?? '') !== '') {
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $argv[4];
}
require '/srv/app/www/index.php';
EOF

render() {
    "$runtime" run --rm -v "$work:/srv:z" "$image" php /srv/render.php "$1" "${2:-GET}" "${3:-}" "${4:-}" 2>&1
}

token_for() { # user, current secret
    printf '%s' "$1" | openssl dgst -sha256 -hmac "$2$install_dir" -r | cut -d' ' -f1
}

secret_of() { # user
    grep -oP "^\"$1\" = \"\K[0-9a-f]+" "$work/app/conf/secrets.toml" || true
}

# --- 1. per-user isolation ---------------------------------------------
out_alice="$(render alice)"
grep -q "$alice_secret" <<<"$out_alice" || { echo "FAIL: alice cannot see her own secret"; exit 1; }
echo "PASS: the logged-in user sees their own secret"

grep -q "$bob_secret" <<<"$out_alice" && { echo "FAIL: alice can see bob's secret"; exit 1; }
echo "PASS: other users' secrets are never rendered"

grep -q "t.me/proxy" <<<"$out_alice" || { echo "FAIL: missing Telegram link"; exit 1; }
grep -q "tg://proxy" <<<"$out_alice" || { echo "FAIL: missing tg:// deep link"; exit 1; }
echo "PASS: Telegram connection links are generated"

# --- 2. setup instructions ---------------------------------------------
grep -q "How to set up Telegram" <<<"$out_alice" || { echo "FAIL: no setup instructions"; exit 1; }
for needle in Android iOS Desktop MTProto; do
    grep -q "$needle" <<<"$out_alice" || { echo "FAIL: instructions miss $needle"; exit 1; }
done
echo "PASS: manual Telegram setup instructions are shown"

# --- 2b. localisation --------------------------------------------------
grep -q '<html lang="en"' <<<"$out_alice" || { echo "FAIL: default language is not English"; exit 1; }
# The switcher deliberately labels each locale in its own language, so it is
# the one place where non-English text is expected.
sed '/lang-switch/,/<\/p>/d' <<<"$out_alice" | grep -qP '\p{Cyrillic}' \
    && { echo "FAIL: Russian text in the default rendering"; exit 1; }
echo "PASS: English is the default language"

out_fr="$(render alice GET '' 'fr-FR,fr;q=0.9,en;q=0.8')"
grep -q '<html lang="fr"' <<<"$out_fr" || { echo "FAIL: Accept-Language fr not honoured"; exit 1; }
grep -q "Comment configurer Telegram" <<<"$out_fr" || { echo "FAIL: French strings missing"; exit 1; }
echo "PASS: Accept-Language selects French"

out_ru="$(render alice GET '' 'ru-RU,ru;q=0.9')"
grep -q '<html lang="ru"' <<<"$out_ru" || { echo "FAIL: Accept-Language ru not honoured"; exit 1; }
grep -q "Как настроить Telegram" <<<"$out_ru" || { echo "FAIL: Russian strings missing"; exit 1; }
echo "PASS: Accept-Language selects Russian"

out_xx="$(render alice GET '' 'de-DE,de;q=0.9,zh;q=0.8')"
grep -q '<html lang="en"' <<<"$out_xx" || { echo "FAIL: unsupported locale did not fall back to English"; exit 1; }
echo "PASS: an unsupported locale falls back to English"

for code in en fr ru; do
    grep -q "?lang=$code" <<<"$out_alice" || { echo "FAIL: no switcher link for $code"; exit 1; }
done
echo "PASS: the language switcher offers every locale"

python3 "$repo/tests/check_translations.py" "$repo/conf/index.php" \
    || { echo "FAIL: the translation tables do not define the same keys"; exit 1; }
echo "PASS: every locale defines the same set of strings"

# --- 3. anonymous / unknown users --------------------------------------
out_anon="$(render '')"
grep -q "403" <<<"$out_anon" || { echo "FAIL: anonymous access not rejected"; exit 1; }
grep -qE "$alice_secret|$bob_secret" <<<"$out_anon" && { echo "FAIL: secret leaked to anonymous"; exit 1; }
echo "PASS: unauthenticated request is refused with 403 and leaks nothing"

out_unknown="$(render dave)"
grep -qE "$alice_secret|$bob_secret" <<<"$out_unknown" && { echo "FAIL: secret leaked to unknown user"; exit 1; }
grep -q "No secret has been generated" <<<"$out_unknown" || { echo "FAIL: missing 'no secret' notice"; exit 1; }
echo "PASS: a user without a secret gets a notice, not someone else's key"

# --- 4. reset is CSRF-protected ----------------------------------------
out_bad="$(render alice POST 'action=reset&token=deadbeef')"
grep -q "400" <<<"$out_bad" || { echo "FAIL: reset accepted without a valid token"; exit 1; }
[ "$(secret_of alice)" = "$alice_secret" ] || { echo "FAIL: secret changed despite a bad token"; exit 1; }
echo "PASS: reset without a valid CSRF token is refused"

out_other="$(render alice POST "action=reset&token=$(token_for bob "$bob_secret")")"
grep -q "400" <<<"$out_other" || { echo "FAIL: another user's token was accepted"; exit 1; }
echo "PASS: a token minted for another user is refused"

# --- 5. reset regenerates only the caller's secret ----------------------
render alice POST "action=reset&token=$(token_for alice "$alice_secret")" >/dev/null
new_alice="$(secret_of alice)"
[ -n "$new_alice" ] && [ "$new_alice" != "$alice_secret" ] || { echo "FAIL: secret was not regenerated"; exit 1; }
[ "$(secret_of bob)" = "$bob_secret" ] || { echo "FAIL: reset clobbered bob's secret"; exit 1; }
echo "PASS: reset regenerates only the caller's secret"

grep -qE "^ee[0-9a-f]{32}$frontend_hex\$" <<<"$new_alice" \
    || { echo "FAIL: new secret is not in mtg-multi --hex format: $new_alice"; exit 1; }
echo "PASS: the new secret has the same format as 'mtg-multi generate-secret --hex'"

python3 -c "import tomllib,sys; d=tomllib.load(open(sys.argv[1],'rb')); assert sorted(d['secrets'])==['alice','bob'], d['secrets']; assert d['secrets']['alice']==sys.argv[2], 'mtg.toml not updated'; assert d['bind-to']=='0.0.0.0:3128', 'header lost'" \
    "$work/app/conf/mtg.toml" "$new_alice"
echo "PASS: mtg.toml is rebuilt, keeps its header and stays valid TOML"

[ -s "$work/app/conf/.reload" ] || { echo "FAIL: reload trigger not written"; exit 1; }
echo "PASS: the systemd reload trigger is written"

# --- 6. a user without a secret can create one -------------------------
write_fixtures
render dave POST "action=reset&token=$(token_for dave '')" >/dev/null
[ -n "$(secret_of dave)" ] || { echo "FAIL: user without a secret could not create one"; exit 1; }
[ "$(secret_of alice)" = "$alice_secret" ] || { echo "FAIL: creating a secret clobbered alice"; exit 1; }
echo "PASS: a user without a secret can create one"

echo
echo "All web-interface tests passed."
