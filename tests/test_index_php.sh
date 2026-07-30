#!/bin/bash
# Renders conf/index.php in a PHP 8.2 container and checks that a user only
# ever sees its own secret. Requires podman (or docker).

set -euo pipefail

repo="$(cd "$(dirname "$0")/.." && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

runtime="$(command -v podman || command -v docker)"
image="docker.io/library/php:8.2-cli"

install_dir="/srv/app"
mkdir -p "$work/app/conf" "$work/app/www"

alice_secret="ee1111111111111111111111111111111173746f726167652e676f6f676c65"
bob_secret="ee2222222222222222222222222222222273746f726167652e676f6f676c65"
cat >"$work/app/conf/secrets.toml" <<EOF
"alice" = "$alice_secret"
"bob" = "$bob_secret"
EOF

sed -e "s|__VERSION__|1.11.0~ynh4|g" \
    -e "s|__INSTALL_DIR__|$install_dir|g" \
    -e "s|__DOMAIN__|proxy.example.tld|g" \
    -e "s|__PORT__|3128|g" \
    -e "s|__PORT_API__|9090|g" \
    -e "s|__FRONTEND_DOMAIN__|storage.googleapis.com|g" \
    "$repo/conf/index.php" >"$work/app/www/index.php"

cat >"$work/render.php" <<'EOF'
<?php
if (($argv[1] ?? '') !== '') {
    $_SERVER['REMOTE_USER'] = $argv[1];
}
require '/srv/app/www/index.php';
EOF

render() {
    "$runtime" run --rm -v "$work:/srv:ro,z" "$image" php /srv/render.php "$1" 2>&1
}

out_alice="$(render alice)"
grep -q "$alice_secret" <<<"$out_alice" || { echo "FAIL: alice cannot see her own secret"; exit 1; }
echo "PASS: the logged-in user sees their own secret"

grep -q "$bob_secret" <<<"$out_alice" && { echo "FAIL: alice can see bob's secret"; exit 1; }
echo "PASS: other users' secrets are never rendered"

grep -q "t.me/proxy" <<<"$out_alice" || { echo "FAIL: missing Telegram link"; exit 1; }
echo "PASS: Telegram connection link is generated"

out_anon="$(render '')"
grep -q "403" <<<"$out_anon" || { echo "FAIL: anonymous access not rejected"; exit 1; }
grep -qE "$alice_secret|$bob_secret" <<<"$out_anon" && { echo "FAIL: secret leaked to anonymous"; exit 1; }
echo "PASS: unauthenticated request is refused with 403 and leaks nothing"

out_unknown="$(render dave)"
grep -qE "$alice_secret|$bob_secret" <<<"$out_unknown" && { echo "FAIL: secret leaked to unknown user"; exit 1; }
grep -q "не создан секрет" <<<"$out_unknown" || { echo "FAIL: missing 'no secret' notice"; exit 1; }
echo "PASS: a user without a secret gets a notice, not someone else's key"

echo
echo "All web-interface tests passed."
