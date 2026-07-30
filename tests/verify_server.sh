#!/bin/bash
# Post-install verification, run on the YunoHost test server.
set -uo pipefail

app=mtg-multi
url="https://test.tildaslash.sh/mtg-multi"

ok() { echo "PASS: $*"; }
ko() { echo "FAIL: $*"; fails=$((fails + 1)); }
fails=0

echo "=== 1. services ==="
for s in "$app" nginx php8.2-fpm; do
    if [ "$(systemctl is-active "$s")" = active ]; then ok "$s is active"; else ko "$s is NOT active"; fi
done

echo
echo "=== 2. hooks installed by core ==="
for h in post_user_create post_user_delete; do
    f="/etc/yunohost/hooks.d/$h/50-$app"
    if [ -f "$f" ]; then ok "$h hook present ($(stat -c %a "$f"))"; else ko "$h hook MISSING"; fi
done

echo
echo "=== 3. secrets ==="
cat /var/www/mtg-multi/conf/secrets.toml
stat -c '%n %a %U:%G' /var/www/mtg-multi/conf/secrets.toml /var/www/mtg-multi/conf/mtg.toml
admin_secret="$(grep -oP '^"administrator" = "\K[0-9a-f]+' /var/www/mtg-multi/conf/secrets.toml)"
[ -n "$admin_secret" ] && ok "administrator has a secret" || ko "administrator has NO secret"

echo
echo "=== 4. secrets are not reachable over HTTP ==="
for p in /conf/secrets.toml /conf/mtg.toml /../conf/secrets.toml; do
    code="$(curl -s -o /dev/null -w '%{http_code}' "$url$p")"
    [ "$code" = 200 ] && ko "$p returned 200" || ok "$p -> HTTP $code"
done

echo
echo "=== 5. unauthenticated access leaks nothing ==="
body="$(curl -sL "$url/")"
if echo "$body" | grep -qF "$admin_secret"; then ko "secret leaked to anonymous visitor"; else ok "no secret in anonymous response"; fi

echo
echo "=== 6. per-user rendering (simulating the SSO header) ==="
sock="/var/run/php/php8.2-fpm-$app.sock"
render() {
    SCRIPT_FILENAME=/var/www/mtg-multi/www/index.php \
        REQUEST_METHOD=GET \
        REMOTE_USER="$1" \
        cgi-fcgi -bind -connect "$sock" 2>/dev/null
}
if command -v cgi-fcgi >/dev/null; then
    out="$(render administrator)"
    echo "$out" | grep -qF "$admin_secret" && ok "administrator sees own secret" || ko "administrator does NOT see own secret"
    out_anon="$(render '')"
    echo "$out_anon" | grep -q "403" && ok "empty REMOTE_USER -> 403" || ko "empty REMOTE_USER not rejected"
else
    echo "SKIP: cgi-fcgi not installed"
fi

echo
echo "=== summary: $fails failure(s) ==="
exit $fails
