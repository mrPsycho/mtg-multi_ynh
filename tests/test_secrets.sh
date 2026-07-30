#!/bin/bash
# Offline harness for the packaging logic (no YunoHost needed).
# Fakes the mtg-multi binary + the ynh_* helpers used by _common.sh, then
# exercises secret sync, config rendering and the user hooks.

set -euo pipefail

repo="$(cd "$(dirname "$0")/.." && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

app="mtg-multi"
install_dir="$work/install"
frontend_domain="storage.googleapis.com"
port=3128
port_api=9090
port_prometheus=3129
dns="1.1.1.1"
prometheus=0
users=(alice bob)

mkdir -p "$install_dir/conf"

cat >"$install_dir/mtg-multi" <<'EOF'
#!/bin/bash
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n'
echo
EOF
chmod +x "$install_dir/mtg-multi"

# --- helper stubs -------------------------------------------------------
ynh_user_list() { printf '%s\n' "${users[@]}"; }
ynh_store_file_checksum() { :; }
ynh_config_add() {
    local template destination
    while [ $# -gt 0 ]; do
        case "$1" in
            --template=*) template="${1#*=}" ;;
            --destination=*) destination="${1#*=}" ;;
        esac
        shift
    done
    local content
    content="$(cat "$repo/conf/$template")"
    for var in APP INSTALL_DIR PORT PORT_API PORT_PROMETHEUS DNS PROM_ENABLED FRONTEND_DOMAIN; do
        local lower="${var,,}"
        content="${content//__${var}__/${!lower}}"
    done
    printf '%s\n' "$content" >"$destination"
}
chown() { :; }

# `install -o app -g app` would fail with a non-existent user
install() {
    local args=()
    while [ $# -gt 0 ]; do
        case "$1" in
            -o | -g)
                shift 2
                continue
                ;;
        esac
        args+=("$1")
        shift
    done
    command install "${args[@]}"
}

source "$repo/scripts/_common.sh"

# --- 1. initial sync ----------------------------------------------------
mtg_sync_secrets
mtg_add_config

echo "--- mtg.toml ---"
cat "$config_file"

alice_secret="$(mtg_secret_of alice)"
bob_secret="$(mtg_secret_of bob)"
[ -n "$alice_secret" ] && [ -n "$bob_secret" ] || { echo "FAIL: missing secrets"; exit 1; }
[ "$alice_secret" != "$bob_secret" ] || { echo "FAIL: secrets are not unique"; exit 1; }
python3 -c "import tomllib,sys; d=tomllib.load(open(sys.argv[1],'rb')); assert sorted(d['secrets'])==['alice','bob'], d['secrets']" "$config_file"
echo "PASS: initial sync produces valid TOML with one unique secret per user"

# --- 2. idempotency -----------------------------------------------------
mtg_sync_secrets
mtg_add_config
[ "$(mtg_secret_of alice)" = "$alice_secret" ] || { echo "FAIL: secret not preserved"; exit 1; }
echo "PASS: re-sync preserves existing secrets"

# --- 3. post_user_create hook ------------------------------------------
# The real hooks read the app settings back from settings.yml, so fake one and
# point the rendered copies at it.
cat >"$work/settings.yml" <<EOF
app: $app
frontend_domain: $frontend_domain
install_dir: $install_dir
EOF

render_hook() {
    sed -e "s|^settings=.*|settings=\"$work/settings.yml\"|" \
        -e "s|^systemctl restart .*|true|" -e "s|^chown |true |" \
        "$repo/hooks/$1" >"$work/$1"
    chmod +x "$work/$1"
}
render_hook post_user_create
render_hook post_user_delete

"$work/post_user_create" carol
python3 -c "import tomllib,sys; d=tomllib.load(open(sys.argv[1],'rb')); assert sorted(d['secrets'])==['alice','bob','carol'], d['secrets']" "$config_file"
[ "$(mtg_secret_of alice)" = "$alice_secret" ] || { echo "FAIL: hook clobbered alice"; exit 1; }
echo "PASS: post_user_create adds a secret without touching the others"

"$work/post_user_create" carol
python3 -c "import tomllib,sys; d=tomllib.load(open(sys.argv[1],'rb')); assert sorted(d['secrets'])==['alice','bob','carol'], d['secrets']" "$config_file"
echo "PASS: post_user_create is idempotent"

# --- 4. post_user_delete hook ------------------------------------------
"$work/post_user_delete" bob
python3 -c "import tomllib,sys; d=tomllib.load(open(sys.argv[1],'rb')); assert sorted(d['secrets'])==['alice','carol'], d['secrets']" "$config_file"
echo "PASS: post_user_delete revokes only that user's secret"

# --- 5. rejects junk usernames -----------------------------------------
"$work/post_user_create" 'evil"] = "x' || true
python3 -c "import tomllib,sys; d=tomllib.load(open(sys.argv[1],'rb')); assert sorted(d['secrets'])==['alice','carol'], d['secrets']" "$config_file"
echo "PASS: malformed usernames are ignored"

echo
echo "All packaging-logic tests passed."
