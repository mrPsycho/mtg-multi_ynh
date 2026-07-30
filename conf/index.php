<?php
// Personal MTProto page: shows ONLY the secret of the SSO-authenticated user.
// $_SERVER['REMOTE_USER'] is set by nginx from the Ynh-User header injected by
// the YunoHost SSO (see fastcgi_params_with_auth).

const APP_VERSION     = '__VERSION__';
const APP_INSTALL_DIR = '__INSTALL_DIR__';
const PROXY_HOST      = '__DOMAIN__';
const PROXY_PORT      = '__PORT__';
const API_PORT        = '__PORT_API__';
const FRONTEND_DOMAIN = '__FRONTEND_DOMAIN__';

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function secret_for(string $user): ?string
{
    $file = APP_INSTALL_DIR . '/conf/secrets.toml';
    if (!is_readable($file)) {
        return null;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^"([^"]+)"\s*=\s*"([0-9a-fA-F]+)"$/', $line, $m) && $m[1] === $user) {
            return $m[2];
        }
    }
    return null;
}

function stats(): ?array
{
    $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
    $raw = @file_get_contents('http://127.0.0.1:' . API_PORT . '/stats', false, $ctx);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function human_bytes($bytes): string
{
    $bytes = (float) $bytes;
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $i ? 1 : 0) . ' ' . $units[$i];
}

$user = $_SERVER['REMOTE_USER'] ?? '';
if (!preg_match('/^[a-z0-9_.-]+$/i', $user)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("403 — Требуется авторизация через портал YunoHost.\n");
}

$secret = secret_for($user);
$all    = stats();
$mine   = ($all !== null && isset($all['users'][$user])) ? $all['users'][$user] : null;

$tg_link = null;
if ($secret !== null) {
    $tg_link = 'https://t.me/proxy?' . http_build_query([
        'server' => PROXY_HOST,
        'port'   => PROXY_PORT,
        'secret' => $secret,
    ]);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>MTG-Multi Proxy — мой доступ</title>
    <style>
        :root {
            --bg: #f5f7fa;
            --card: #ffffff;
            --text: #2c3e50;
            --text-muted: #7f8c8d;
            --border: #e1e8ed;
            --accent: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            padding: 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        h1 small {
            font-size: 1rem;
            color: var(--text-muted);
            font-weight: normal;
        }
        .subtitle {
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        .card {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border: 1px solid var(--border);
        }
        .card h2 {
            font-size: 1.2rem;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border);
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-badge.online { background: #e8f8f0; color: var(--success); }
        .status-badge.offline { background: #fde8e8; color: var(--danger); }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .info-item {
            padding: 12px;
            background: var(--bg);
            border-radius: 8px;
        }
        .info-item .label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-item .value {
            font-size: 1.1rem;
            font-weight: 600;
            word-break: break-all;
        }
        .info-item .value.mono {
            font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
            font-size: 0.9rem;
        }
        .connection-info {
            background: #eaf2f8;
            border: 1px solid #d4e6f1;
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
        }
        .connection-info code {
            display: block;
            padding: 8px 12px;
            background: #fff;
            border-radius: 6px;
            font-size: 0.9rem;
            margin-top: 8px;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            margin-top: 12px;
            padding: 10px 18px;
            border-radius: 8px;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
        .error-box {
            background: #fde8e8;
            border: 1px solid #f8d7da;
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
            color: var(--danger);
        }
        .footer {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }
        @media (max-width: 600px) {
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            MTG-Multi Proxy
            <small>MTProto proxy для Telegram</small>
        </h1>
        <p class="subtitle">
            Персональный доступ для пользователя <strong><?= h($user) ?></strong>
        </p>

        <div class="card">
            <h2>Мой ключ доступа</h2>
            <?php if ($secret === null): ?>
                <div class="error-box">
                    Для вашей учётной записи ещё не создан секрет.
                    Обратитесь к администратору сервера.
                </div>
            <?php else: ?>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Сервер</div>
                        <div class="value mono"><?= h(PROXY_HOST) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Порт</div>
                        <div class="value mono"><?= h(PROXY_PORT) ?></div>
                    </div>
                </div>
                <div class="connection-info">
                    <strong>🔑 Секрет (только ваш, не передавайте его другим)</strong>
                    <code><?= h($secret) ?></code>
                    <a class="btn" href="<?= h($tg_link) ?>">Подключить в Telegram</a>
                    <p style="margin-top: 8px; font-size: 0.85rem; color: var(--text-muted);">
                        Если домен не резолвится, замените его на IP-адрес сервера.
                        Domain fronting: <?= h(FRONTEND_DOMAIN) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Моя статистика</h2>
            <?php if ($all === null): ?>
                <span class="status-badge offline">🔴 Прокси недоступен</span>
                <p style="margin-top: 12px;">
                    Не удалось получить статистику. Возможно, сервис остановлен или ещё запускается.
                </p>
            <?php else: ?>
                <span class="status-badge online">🟢 Онлайн</span>
                <div class="info-grid" style="margin-top: 16px;">
                    <div class="info-item">
                        <div class="label">Активных соединений</div>
                        <div class="value"><?= h((string) ($mine['connections'] ?? 0)) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Последняя активность</div>
                        <div class="value" style="font-size: 0.95rem;">
                            <?= h($mine['last_seen'] ?? '—') ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="label">Принято</div>
                        <div class="value"><?= h(human_bytes($mine['bytes_in'] ?? 0)) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Отправлено</div>
                        <div class="value"><?= h(human_bytes($mine['bytes_out'] ?? 0)) ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            MTG-Multi Proxy для YunoHost <?= h(APP_VERSION) ?> —
            <a href="https://github.com/dolonet/mtg-multi" target="_blank" rel="noopener">GitHub</a>
        </div>
    </div>
</body>
</html>
