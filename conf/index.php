<?php
/**
 * mtg-multi Web Interface
 *
 * Self-service for MTProto proxy secrets.
 * Users can create, update, and delete their own secrets.
 * Integrates with YunoHost SSO — the user is identified via $_SERVER['REMOTE_USER'].
 */

// ─── Configuration ───────────────────────────────────────────────────────────

// Path to mtg-multi config file (injected by YunoHost)
$config_path = '__INSTALL_DIR__/conf/mtg.toml';

// Path to the mtg-multi binary (for secret generation)
$binary_path = '__INSTALL_DIR__/mtg-multi';

// The system user that owns the mtg-multi process
$app_user = '__APP__';

// Fronting domain for secret generation
$fronting_domain = 'storage.googleapis.com';

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Parse mtg-multi TOML config and return secrets section.
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

        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//')) {
            continue;
        }

        if (preg_match('/^\[(.+)\]$/', $line, $m)) {
            $current_section = $m[1];
            continue;
        }

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
 * Update the [secrets] section in the TOML config file.
 * Replaces all secrets with the provided array.
 */
function write_secrets(array $secrets): bool {
    global $config_path;

    if (!file_exists($config_path)) {
        return false;
    }

    $lines = file($config_path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return false;
    }

    $in_secrets = false;
    $secrets_start = null;
    $secrets_end = null;

    // Find the [secrets] section boundaries
    foreach ($lines as $i => $line) {
        $trimmed = trim($line);

        if (preg_match('/^\[secrets\]$/', $trimmed)) {
            $in_secrets = true;
            $secrets_start = $i;
            continue;
        }

        if ($in_secrets) {
            // Check if we've hit a new section or end of file
            if (preg_match('/^\[/', $trimmed)) {
                $secrets_end = $i;
                break;
            }
            // If line is empty or comment, skip
            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '//')) {
                continue;
            }
            // If it's a key = value line, it's part of secrets
            if (preg_match('/^[a-zA-Z0-9_.-]+\s*=/', $trimmed)) {
                continue;
            }
        }
    }

    if ($secrets_start === null) {
        return false;
    }

    if ($secrets_end === null) {
        $secrets_end = count($lines);
    }

    // Build new secrets lines
    $new_lines = [];
    if (empty($secrets)) {
        // Keep the [secrets] header but with no entries
        $new_lines[] = "[secrets]\n";
    } else {
        $new_lines[] = "[secrets]\n";
        foreach ($secrets as $user => $secret) {
            $new_lines[] = "$user = \"$secret\"\n";
        }
    }

    // Replace the old secrets section
    array_splice($lines, $secrets_start, $secrets_end - $secrets_start, $new_lines);

    $result = file_put_contents($config_path, implode('', $lines));
    return $result !== false;
}

/**
 * Generate a new MTProto secret using the mtg-multi binary.
 * Runs via sudo as the app user (www-data has sudoers access).
 */
function generate_secret(): ?string {
    global $binary_path, $fronting_domain, $app_user;

    if (!file_exists($binary_path)) {
        return null;
    }

    $command = sprintf(
        'sudo -u %s %s generate-secret --hex %s 2>/dev/null',
        escapeshellarg($app_user),
        escapeshellcmd($binary_path),
        escapeshellarg($fronting_domain)
    );

    $output = shell_exec($command);
    if ($output === null || $output === false) {
        return null;
    }

    $secret = trim($output);
    if (empty($secret)) {
        return null;
    }

    return $secret;
}

/**
 * Restart the mtg-multi systemd service.
 * www-data has passwordless sudo access to systemctl for this service.
 */
function restart_service(): bool {
    global $app_user;

    $command = sprintf(
        'sudo -u %s systemctl restart %s 2>/dev/null',
        escapeshellarg($app_user),
        escapeshellarg($app_user)
    );
    shell_exec($command);

    return true;
}

/**
 * Get the public IP of the server.
 */
function get_public_ip(): string {
    $config = parse_config($GLOBALS['config_path']);
    if (!empty($config['public_ip'])) {
        return $config['public_ip'];
    }

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
    if (!empty($_SERVER['REMOTE_USER'])) {
        return $_SERVER['REMOTE_USER'];
    }

    if (!empty($_SERVER['PHP_AUTH_USER'])) {
        return $_SERVER['PHP_AUTH_USER'];
    }

    return null;
}

/**
 * Format a secret for display in Telegram client config.
 */
function format_telegram_config(string $secret, string $server, string $port): array {
    $mtproxy_link = sprintf(
        'https://t.me/proxy?server=%s&port=%s&secret=%s',
        $server, $port, $secret
    );

    return [
        'server' => $server,
        'port'   => $port,
        'secret' => $secret,
        'link'   => $mtproxy_link,
    ];
}

/**
 * Send a JSON response and exit.
 */
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send an error response and exit.
 */
function error_response(string $message, int $status = 400): void {
    json_response(['error' => $message], $status);
}

// ─── Authentication ──────────────────────────────────────────────────────────

$user = get_current_user();

if ($user === null) {
    error_response('Unauthenticated', 401);
}

// ─── Handle POST actions (create/update/delete secret) ───────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Verify CSRF via content-type or origin check
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (!str_contains($content_type, 'application/x-www-form-urlencoded') && 
        !str_contains($content_type, 'multipart/form-data')) {
        error_response('Invalid content type', 415);
    }

    $config = parse_config($config_path);
    $secrets = $config['secrets'];

    switch ($action) {
        case 'create':
        case 'update':
            // Generate a new secret for this user
            $new_secret = generate_secret();
            if ($new_secret === null) {
                error_response('Failed to generate secret. Check that the mtg-multi binary is available.', 500);
            }

            $secrets[$user] = $new_secret;

            if (!write_secrets($secrets)) {
                error_response('Failed to write config file.', 500);
            }

            restart_service();

            $public_ip = get_public_ip();
            $port = $config['bind_to'] ? explode(':', $config['bind_to'])[1] ?? '3128' : '3128';
            $telegram = format_telegram_config($new_secret, $public_ip, $port);

            json_response([
                'success' => true,
                'message' => $action === 'create' ? 'Secret created successfully' : 'Secret updated successfully',
                'config'  => $telegram,
            ]);
            break;

        case 'delete':
            if (!isset($secrets[$user])) {
                error_response('No secret found for this user.', 404);
            }

            unset($secrets[$user]);

            if (!write_secrets($secrets)) {
                error_response('Failed to write config file.', 500);
            }

            restart_service();

            json_response([
                'success' => true,
                'message' => 'Secret deleted successfully',
            ]);
            break;

        default:
            error_response('Unknown action. Use: create, update, or delete.', 400);
    }

    exit;
}

// ─── GET request — show status page ──────────────────────────────────────────

$config = parse_config($config_path);
$public_ip = get_public_ip();
$port = $config['bind_to'] ? explode(':', $config['bind_to'])[1] ?? '3128' : '3128';

// Check if user has a personal secret
$has_secret = isset($config['secrets'][$user]);
$user_secret = $config['secrets'][$user] ?? null;

// ─── JSON response ───────────────────────────────────────────────────────────

$accept = $_SERVER['HTTP_ACCEPT'] ?? 'text/html';

if (str_contains($accept, 'application/json')) {
    json_response([
        'user'      => $user,
        'has_secret' => $has_secret,
        'config'    => $user_secret ? format_telegram_config($user_secret, $public_ip, $port) : null,
        'stats'     => [
            'api_endpoint' => '/api/stats',
        ],
    ]);
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
            --danger: #e74c3c;
            --warning: #f39c12;
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
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--accent);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
        .user-badge .logout {
            color: var(--muted);
            font-size: 0.75rem;
            text-decoration: none;
        }
        .user-badge .logout:hover {
            color: var(--text);
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
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
        .btn-group {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.25rem;
            flex-wrap: wrap;
        }
        .btn-group .btn {
            flex: 1;
            min-width: 140px;
            padding: 0.7rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }
        .btn-primary {
            background: var(--accent);
            color: var(--text);
        }
        .btn-primary:hover {
            background: #1a4a7a;
        }
        .btn-danger {
            background: transparent;
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        .btn-danger:hover {
            background: var(--danger);
            color: white;
        }
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: #3d8b40;
        }
        .no-secret {
            color: var(--muted);
            padding: 2rem 1rem;
            text-align: center;
            border: 1px dashed var(--border);
            border-radius: 8px;
        }
        .no-secret p {
            margin-bottom: 1rem;
            font-size: 0.95rem;
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
        .toast {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            pointer-events: none;
        }
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        .toast.success {
            background: var(--success);
            color: white;
        }
        .toast.error {
            background: var(--danger);
            color: white;
        }
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        @media (max-width: 480px) {
            body { padding: 1rem; }
            .card { padding: 1.25rem; }
            .btn-group { flex-direction: column; }
            .btn-group .btn { min-width: 100%; }
        }
    </style>
</head>
<body>
    <div id="toast" class="toast"></div>

    <div class="container">
        <div class="card">
            <div class="user-badge">
                👤 <?= htmlspecialchars($user) ?>
                <a href="/yunohost/sso/?action=logout" class="logout">(logout)</a>
            </div>
            <h1>MTProto Proxy</h1>
            <p class="subtitle">Self-service proxy configuration for Telegram</p>

            <?php if ($has_secret && $telegram): ?>
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

                <div class="btn-group">
                    <button class="btn btn-primary" onclick="submitAction('update')">
                        🔄 Update Secret
                    </button>
                    <button class="btn btn-danger" onclick="confirmDelete()">
                        🗑️ Delete Secret
                    </button>
                </div>
            <?php else: ?>
                <div class="no-secret">
                    <p>You don't have a proxy secret yet.</p>
                    <button class="btn btn-success" onclick="submitAction('create')" style="border:none;padding:0.75rem 2rem;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer">
                        ✨ Create My Secret
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <a href="/api/stats" target="_blank">📊 Live Statistics</a> &middot;
            mtg-multi proxy
        </div>
    </div>

    <form id="action-form" method="POST" style="display:none">
        <input type="hidden" name="action" id="action-input" value="">
    </form>

    <script>
    function showToast(message, type) {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = 'toast ' + type + ' show';
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    function submitAction(action) {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner"></span>';
        btn.disabled = true;

        const form = document.getElementById('action-form');
        document.getElementById('action-input').value = action;
        
        const formData = new FormData(form);

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                // Reload the page to show updated config
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast(data.error || 'An error occurred', 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            showToast('Network error: ' + error.message, 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }

    function confirmDelete() {
        if (confirm('Are you sure you want to delete your proxy secret? This will disconnect your Telegram client.')) {
            submitAction('delete');
        }
    }

    function copyValue(btn, value) {
        navigator.clipboard.writeText(value).then(() => {
            btn.textContent = 'Copied!';
            btn.classList.add('copied');
            setTimeout(() => {
                btn.textContent = 'Copy';
                btn.classList.remove('copied');
            }, 2000);
        }).catch(() => {
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