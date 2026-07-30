#!/bin/bash
# Live test of the post_user_create / post_user_delete hooks.
# The test user's password is generated on the server and never printed.
set -uo pipefail

secrets=/var/www/mtg-multi/conf/secrets.toml
config=/var/www/mtg-multi/conf/mtg.toml
testuser=mtgtest

fails=0
ok() { echo "PASS: $*"; }
ko() { echo "FAIL: $*"; fails=$((fails + 1)); }

admin_before="$(grep -oP '^"administrator" = "\K[0-9a-f]+' "$secrets")"

echo "=== creating $testuser ==="
yunohost user create "$testuser" --fullname "MTG Test" --domain test.tildaslash.sh \
    --password "$(openssl rand -base64 24)" >/dev/null 2>&1 || {
    echo "user create failed, retrying with -F/-d/-p"
    yunohost user create "$testuser" -F "MTG Test" -d test.tildaslash.sh \
        -p "$(openssl rand -base64 24)" >/dev/null 2>&1
}

sleep 2
echo "--- secrets.toml ---"; cat "$secrets"

new_secret="$(grep -oP "^\"$testuser\" = \"\K[0-9a-f]+" "$secrets")"
[ -n "$new_secret" ] && ok "hook created a secret for $testuser" || ko "no secret created for $testuser"
[ "$new_secret" != "$admin_before" ] && ok "secret is unique" || ko "secret collides with administrator"
[ "$(grep -oP '^"administrator" = "\K[0-9a-f]+' "$secrets")" = "$admin_before" ] \
    && ok "administrator's secret untouched" || ko "administrator's secret changed"
grep -qF "\"$testuser\" = \"$new_secret\"" "$config" && ok "mtg.toml updated" || ko "mtg.toml NOT updated"
python3 -c "import tomllib,sys; d=tomllib.load(open('$config','rb')); assert sorted(d['secrets'])==['administrator','$testuser'], d['secrets']" \
    && ok "mtg.toml is valid TOML with both users" || ko "mtg.toml invalid"
[ "$(systemctl is-active mtg-multi)" = active ] && ok "service still active after hook" || ko "service died"

echo
echo "=== deleting $testuser ==="
yunohost user delete "$testuser" --force >/dev/null 2>&1 || yunohost user delete "$testuser" >/dev/null 2>&1
sleep 2
echo "--- secrets.toml ---"; cat "$secrets"

grep -qF "\"$testuser\"" "$secrets" && ko "secret NOT revoked" || ok "secret revoked from secrets.toml"
grep -qF "\"$testuser\"" "$config" && ko "still present in mtg.toml" || ok "removed from mtg.toml"
[ "$(grep -oP '^"administrator" = "\K[0-9a-f]+' "$secrets")" = "$admin_before" ] \
    && ok "administrator's secret still untouched" || ko "administrator's secret changed"
[ "$(systemctl is-active mtg-multi)" = active ] && ok "service still active" || ko "service died"

echo
echo "=== summary: $fails failure(s) ==="
exit $fails
