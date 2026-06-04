<?php
/**
 * mtg-multi Web Interface
 *
 * Provides authenticated users with their personal MTProto proxy configuration.
 * Integrates with YunoHost SSO — the user is identified via $_SERVER['REMOTE_USER'].
 */

// ─── Configuration ───────────────────────────────────────────────────────────

// Path to mtg-multi config file (injected by YunoHost)
$config_path = '__INSTALL_DIR__/conf/mtg.toml';

// Path to the mtg-multi binary (for secret generation)
$binary_path = '__INSTALL_DIR__/mtg-multi';

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Parse mtg-multi TOML config and return secrets section.
 * This is a simple line-based parser — sufficient for our config format.
 */
function parse_config(string $path): array {
    $config = [
        'secrets'   => [],
        'bind_to'   => '',
        'api_bind'  => '',
        'dns'       => '',
        'public_ip' => '',
    ];

    if (!file_exists($path)) {
        return $config;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $config;
    }

    $current_section = '';

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//')) {
            continue;
        }

        // Section header
        if (preg_match('/^\[(.+)\]$/', $line, $m)) {
            $current_section = $m[1];
            continue;
        }

        // Key = value
        if (preg_match('/^([a-zA-Z0-9_.-]+)\s*=\s*"(.+)"$/', $line, $m)) {
            $key = $m[1];
            $val = $m[2];

            if ($current_section === 'secrets') {
                $config['secrets'][$key] = $val;
            } elseif ($current_section === '') {
                $config[$key] = $val;
            }
        }
    }

    return $config;
}

/**
 * Get the public IP of the server by checking common sources.
 */
function get_public_ip(): string {
    // First, try to get it from the mtg-multi config
    $config_path = $GLOBALS['config_path'];
    $config = parse_config($config_path);
    if (!empty($config['public_ip'])) {
        return $config['public_ip'];
    }

    // Fallback: try common discovery methods
    $sources = [
        'https://ifconfig.co/ip',
        'https://api.ipify.org',
        'https://checkip.amazonaws.com',
    ];

    foreach ($sources as $url) {
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $ip = @file_get_contents($url, false, $ctx);
        if ($ip !== false) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return gethostbyname(gethostname());
}

/**
 * Get the current YunoHost user from SSO.
 */
function get_current_user(): ?string {
    // YunoHost SSO sets REMOTE_USER after authentication
    if (!empty($_SERVER['REMOTE_USER'])) {
        return $_SERVER['REMOTE_USER'];
    }

    // Fallback for local testing
    if (!empty($_SERVER['PHP_AUTH_USER'])) {
        return $_SERVER['PHP_AUTH_USER'];
    }

    return null;
}

/**
 * Check if the current user is an admin.
 */
function is_admin(): bool {
    return (!empty($_SERVER['REMOTE_USER']) && $_SERVER['REMOTE_USER'] === 'admin')
        || (!empty($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER'] === 'admin');
}

/**
 * Format a secret for display in Telegram client config.
 */
function format_telegram_config(string $secret, string $server, string $port): array {
    // MTProxy format: https://t.me/proxy?server=SERVER&port=PORT&secret=SECRET
    $mtproxy_link = sprintf(
        'https://t.me/proxy?server=%s&port=%s&secret=%s',
        $server, $port, $secret
    );

    // Human-readable config
    $config = [
        'server' => $server,
        'port'   => $port,
        'secret' => $secret,
        'link'   => $mtproxy_link,
    ];

    return $config;
}

// ─── Main ────────────────────────────────────────────────────────────────────

$user = get_current_user();

if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

$config = parse_config($config_path);
$public_ip = get_public_ip();
$port = $config['bind_to'] ? explode(':', $config['bind_to'])[1] ?? '3128' : '3128';

// Determine the user's secret
$user_secret = $config['secrets'][$user] ?? null;

// If user has no personal secret, fall back to the main secret
if ($user_secret === null) {
    $user_secret = $config['secret'] ?? null;
}

// ─── Response ────────────────────────────────────────────────────────────────

$accept = $_SERVER['HTTP_ACCEPT'] ?? 'text/html';

if (str_contains($accept, 'application/json')) {
    // JSON API response
    header('Content-Type: application/json');
    echo json_encode([
        'user'   => $user,
        'config' => $user_secret ? format_telegram_config($user_secret, $public_ip, $port) : null,
        'stats'  => [
            'api_endpoint' => '/api/stats',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ─── HTML Response ───────────────────────────────────────────────────────────

$telegram = $user_secret ? format_telegram_config($user_secret, $public_ip, $port) : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MTProto Proxy — mtg-multi</title>
    <style>
        :root {
            --bg: #1a1a2e;
            --card: #16213e;
            --accent: #0f3460;
            --text: #e8e8e8;
            --muted: #a0a0b0;
            --success: #4caf50;
            --border: #2a2a4a;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .container {
            max-width: 640px;
            width: 100%;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .user-badge {
            display: inline-block;
            background: var(--accent);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
        .config-field {
            margin-bottom: 1rem;
        }
        .config-field label {
            display: block;
            font-size: 0.8rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.3rem;
        }
        .config-field .value {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.6rem 0.8rem;
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 0.9rem;
            word-break: break-all;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .config-field .value .copy-btn {
            background: var(--accent);
            border: none;
            color: var(--text);
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            white-space: nowrap;
            transition: background 0.2s;
        }
        .config-field .value .copy-btn:hover {
            background: #1a4a7a;
        }
        .config-field .value .copy-btn.copied {
            background: var(--success);
        }
        .telegram-link {
            display: inline-block;
            background: #0088cc;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.2s;
            text-align: center;
            width: 100%;
        }
        .telegram-link:hover {
            background: #0077b3;
        }
        .no-secret {
            color: #ff6b6b;
            padding: 1rem;
            text-align: center;
            border: 1px dashed #ff6b6b;
            border-radius: 8px;
        }
        .footer {
            text-align: center;
            color: var(--muted);
            font-size: 0.8rem;
            margin-top: 1rem;
        }
        .footer a {
            color: var(--muted);
        }
        .stats-link {
            display: inline-block;
            margin-top: 1rem;
            color: var(--muted);
            font-size: 0.85rem;
        }
        @media (max-width: 480px) {
            body { padding: 1rem; }
            .card { padding: 1.25rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="user-badge">👤 <?= htmlspecialchars($user) ?></div>
            <h1>MTProto Proxy Configuration</h1>
            <p class="subtitle">Use these settings to configure your Telegram client</p>

            <?php if ($telegram): ?>
                <div class="config-field">
                    <label>Server</label>
                    <div class="value">
                        <span><?= htmlspecialchars($telegram['server']) ?></span>
                        <button class="copy-btn" onclick="copyValue(this, '<?= htmlspecialchars($telegram['server']) ?>')">Copy</button>
                    </div>
                </div>

                <div class="config-field">
                    <label>Port</label>
                    <div class="value">
                        <span><?= htmlspecialchars($telegram['port']) ?></span>
                        <button class="copy-btn" onclick="copyValue(this, '<?= htmlspecialchars($telegram['port']) ?>')">Copy</button>
                    </div>
                </div>

                <div class="config-field">
                    <label>Secret</label>
                    <div class="value">
                        <span style="font-size:0.75rem"><?= htmlspecialchars($telegram['secret']) ?></span>
                        <button class="copy-btn" onclick="copyValue(this, '<?= htmlspecialchars($telegram['secret']) ?>')">Copy</button>
                    </div>
                </div>

                <a href="<?= htmlspecialchars($telegram['link']) ?>" class="telegram-link" target="_blank">
                    📱 Open in Telegram
                </a>

                <?php if (is_admin()): ?>
                    <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
                        <details>
                            <summary style="cursor:pointer;color:var(--muted);font-size:0.85rem">⚙️ Admin: Manage Users</summary>
                            <div style="margin-top:0.75rem;font-size:0.85rem">
                                <p style="color:var(--muted);margin-bottom:0.5rem">
                                    To add a user, edit the config file and add their secret to the <code>[secrets]</code> section:
                                </p>
                                <pre style="background:var(--bg);padding:0.75rem;border-radius:6px;overflow-x:auto;font-size:0.8rem"># ssh into your server
sudo -u mtg-multi mtg-multi generate-secret --hex storage.googleapis.com
# Add the output to:
sudo nano __INSTALL_DIR__/conf/mtg.toml

# Then restart:
sudo systemctl restart __APP__</pre>
                            </div>
                        </details>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-secret">
                    ⚠️ No secret configured for user "<?= htmlspecialchars($user) ?>".<br>
                    Contact your administrator to set up your proxy access.
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <a href="/api/stats" target="_blank">📊 Live Statistics</a> &middot;
            mtg-multi proxy
        </div>
    </div>

    <script>
    function copyValue(btn, value) {
        navigator.clipboard.writeText(value).then(() => {
            btn.textContent = 'Copied!';
            btn.classList.add('copied');
            setTimeout(() => {
                btn.textContent = 'Copy';
                btn.classList.remove('copied');
            }, 2000);
        }).catch(() => {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = value;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            btn.textContent = 'Copied!';
            btn.classList.add('copied');
            setTimeout(() => {
                btn.textContent = 'Copy';
                btn.classList.remove('copied');
            }, 2000);
        });
    }
    </script>
</body>
</html>
<?php