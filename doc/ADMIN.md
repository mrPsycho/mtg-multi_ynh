## Per-user access

Every YunoHost user automatically gets its own MTProto secret. Users retrieve it
themselves on the app's page, which is behind the YunoHost SSO: a user only ever
sees its own key, plus its own traffic statistics.

Secrets are kept in sync automatically: creating a YunoHost user generates a new
secret, deleting a user revokes it. Restrict who gets access with the app's
permission (`Users` group) in the webadmin.

## Self-service key reset

The app's page also carries step-by-step Telegram setup instructions and a
**«Сбросить ключ»** button, so a user whose key leaked can rotate it without
asking an admin. A user can only ever reset its own key: the request is
authenticated by the SSO header and additionally signed with a per-user CSRF
token derived from the current secret.

`index.php` runs as the `mtg-multi` system user and therefore may rewrite the
secret files but not restart the service. Instead it touches
`/var/www/mtg-multi/conf/.reload`, which the `mtg-multi-reload.path` systemd unit
watches; that unit triggers the oneshot `mtg-multi-reload.service`, which
restarts the proxy. No sudo rule and no `exec()` from PHP is involved.

```bash
systemctl status mtg-multi-reload.path
journalctl -u mtg-multi-reload.service
```

## Configuration

The configuration file is at `/var/www/mtg-multi/conf/mtg.toml` and the
`username -> secret` mapping at `/var/www/mtg-multi/conf/secrets.toml`. Both are
only readable by the app's system user and are never served over HTTP.

Key settings:

- `bind-to` — The port the proxy listens on.
- `api-bind-to` — The stats API endpoint (per-user connection counts and traffic).
- `[network] dns` — The DNS resolver used for IP resolution.
- `[throttle]` — Automatic per-user connection limits to protect the server from overload.
- `[stats.prometheus]` — Enable/disable the Prometheus metrics endpoint.
- `[secrets]` — Generated from `secrets.toml`, do not edit by hand.

After editing the config file, restart the service:
```bash
sudo systemctl restart mtg-multi
```

## Multi-user support

mtg-multi supports multiple secrets per instance. The package writes one entry
per YunoHost user:

```toml
[secrets]
"alice" = "ee367a189aee18fa31c190054efd4a8e..."
"bob" = "ee0123456789abcdef0123456789abcd..."
```

Each key is a user name, used for per-user stats tracking.

## Stats API

mtg-multi provides a lightweight HTTP endpoint that shows live per-user traffic:

```bash
curl http://127.0.0.1:9090/stats
```

Response example:
```json
{
  "started_at": "2026-03-29T10:30:00Z",
  "uptime_seconds": 3600,
  "total_connections": 15,
  "users": {
    "alice": {
      "connections": 8,
      "bytes_in": 1048576,
      "bytes_out": 2097152,
      "last_seen": "2026-03-29T11:25:30Z"
    }
  }
}
