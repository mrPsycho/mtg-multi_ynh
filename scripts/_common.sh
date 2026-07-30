#!/bin/bash

# The secrets file is the source of truth for the "username -> secret" mapping.
# mtg.toml is derived from it, and index.php reads it to show a user its own key.
secrets_file="$install_dir/conf/secrets.toml"
config_file="$install_dir/conf/mtg.toml"

mtg_generate_secret() {
    "$install_dir/mtg-multi" generate-secret --hex "$frontend_domain" | tr -d '[:space:]'
}

mtg_secret_of() {
    local user="$1"
    [ -s "$secrets_file" ] || return 1
    grep -oP "^\"\Q$user\E\" = \"\K[0-9a-fA-F]+" "$secrets_file" | head -n1
}

# Create a secret for every YunoHost user that doesn't have one yet, and drop
# the entries of users that no longer exist.
mtg_sync_secrets() {
    local tmp user secret
    tmp="$(mktemp)"

    for user in $(ynh_user_list); do
        secret="$(mtg_secret_of "$user" || true)"
        [ -n "$secret" ] || secret="$(mtg_generate_secret)"
        echo "\"$user\" = \"$secret\"" >>"$tmp"
    done

    install -o "$app" -g "$app" -m 600 "$tmp" "$secrets_file"
    rm -f "$tmp"
}

# Rewrite the [secrets] table of mtg.toml from the secrets file.
# [secrets] is the last section of the template, so truncating right after its
# header and re-appending the entries is enough.
mtg_apply_secrets() {
    sed -i '/^\[secrets\]$/q' "$config_file"
    cat "$secrets_file" >>"$config_file"
    chown "$app:$app" "$config_file"
    chmod 600 "$config_file"
    ynh_store_file_checksum "$config_file"
}

mtg_add_config() {
    [ "${prometheus:-0}" = "1" ] && prom_enabled="true" || prom_enabled="false"
    ynh_config_add --template="mtg.toml" --destination="$config_file"
    mtg_apply_secrets
}

mtg_add_hooks() {
    local hook
    for hook in post_user_create post_user_delete; do
        mkdir -p "/etc/yunohost/hooks.d/$hook"
        ynh_config_add --template="$hook" --destination="/etc/yunohost/hooks.d/$hook/50-$app"
        chmod 755 "/etc/yunohost/hooks.d/$hook/50-$app"
    done
}

mtg_remove_hooks() {
    local hook
    for hook in post_user_create post_user_delete; do
        ynh_safe_rm "/etc/yunohost/hooks.d/$hook/50-$app"
    done
}
