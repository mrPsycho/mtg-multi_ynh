## Configuration

The configuration file is at `/etc/mtg/mtg.toml`. Key settings:

- `secret` — The MTProto secret. Changing this requires restarting the service.
- `bind-to` — The port the proxy listens on.
- `api-bind-to` — The stats API endpoint (per-user connection counts and traffic).
- `[network] dns` — The DNS resolver used for IP resolution.
- `[throttle]` — Automatic per-user connection limits to protect the server from overload.
- `[stats.prometheus]` — Enable/disable the Prometheus metrics endpoint.

After editing the config file, restart the service:
```bash
sudo systemctl restart mtg-multi
```

## Getting the connection link for Telegram

After installation, find your proxy settings:
```bash
sudo cat /etc/mtg/mtg.toml
```

Use the `secret` and your server's public IP + port to connect from Telegram:
**Settings → Data and Storage → Proxy Settings → Add Proxy → MTProto**

## Multi-user support

mtg-multi supports multiple secrets per instance. To add additional users, edit the config file and add a `[secrets]` section:

```toml
[secrets]
alice = "ee367a189aee18fa31c190054efd4a8e..."
bob   = "ee0123456789abcdef0123456789abcd..."
```

Each key is a user name, used for per-user stats tracking. Secrets may use different hostnames for per-user domain fronting.

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
