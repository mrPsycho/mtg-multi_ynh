<?php
/**
 * MTG-Multi — информационная страница для YunoHost
 *
 * Показывает статус прокси, секреты и параметры подключения.
 */

// Читаем конфигурационный файл mtg.toml
$config_file = __DIR__ . '/mtg.toml';
$config = file_exists($config_file) ? parse_mtg_toml($config_file) : null;

// Парсинг TOML (упрощённый, только для нашей структуры)
function parse_mtg_toml($file) {
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    $result = [];
    $section = 'root';

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) continue;

        if (str_starts_with($line, '[') && str_ends_with($line, ']')) {
            $section = trim(substr($line, 1, -1));
            continue;
        }

        if (preg_match('/^"([^"]+)"\s*=\s*"([^"]*)"$/', $line, $m)) {
            $result[$section][$m[1]] = $m[2];
        } elseif (preg_match('/^([a-zA-Z0-9_-]+)\s*=\s*"([^"]*)"$/', $line, $m)) {
            $result[$section][$m[1]] = $m[2];
        } elseif (preg_match('/^([a-zA-Z0-9_-]+)\s*=\s*(true|false)$/', $line, $m)) {
            $result[$section][$m[1]] = $m[2] === 'true';
        } elseif (preg_match('/^([a-zA-Z0-9_-]+)\s*=\s*"([^"]*)"$/', $line, $m)) {
            $result['root'][$m[1]] = $m[2];
        }
    }

    return $result;
}

function str_starts_with($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

function str_ends_with($haystack, $needle) {
    $len = strlen($needle);
    return $len === 0 || substr($haystack, -$len) === $needle;
}

// Получаем информацию о порте из конфига
$bind_to = $config['root']['bind-to'] ?? '0.0.0.0:3128';
$bind_parts = explode(':', $bind_to);
$proxy_port = end($bind_parts);

$api_bind = $config['root']['api-bind-to'] ?? '127.0.0.1:9090';
$api_parts = explode(':', $api_bind);
$api_port = end($api_parts);

$dns = $config['network']['dns'] ?? '1.1.1.1';
$prometheus_enabled = $config['stats.prometheus']['enabled'] ?? false;
$secrets = $config['secrets'] ?? [];

// Пытаемся получить статус через API
$api_url = "http://127.0.0.1:$api_port";
$api_status = null;
$api_error = null;

if ($api_port) {
    $ctx = stream_context_create(['http' => ['timeout' => 2, 'method' => 'GET']]);
    $api_response = @file_get_contents("$api_url/status", false, $ctx);
    if ($api_response !== false) {
        $api_status = json_decode($api_response, true);
    } else {
        $api_error = 'API недоступен';
    }
}

// Определяем домен YunoHost
$domain = getenv('YNH_DOMAIN') ?: ($_SERVER['HTTP_HOST'] ?? 'mtg.local');

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MTG-Multi Proxy — статус</title>
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
        .status-badge.warning { background: #fef5e7; color: var(--warning); }
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
        .secret-item {
            padding: 12px;
            background: var(--bg);
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .secret-item:last-child { margin-bottom: 0; }
        .secret-item .secret-name {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }
        .secret-item .secret-value {
            font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
            font-size: 0.85rem;
            color: var(--text-muted);
            word-break: break-all;
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
            Статус прокси-сервера и параметры подключения
        </p>

        <!-- Статус сервера -->
        <div class="card">
            <h2>Статус сервера</h2>
            <?php if ($api_status !== null): ?>
                <span class="status-badge online">🟢 Онлайн</span>
                <p style="margin-top: 12px;">
                    Прокси-сервер работает и принимает подключения.
                </p>
            <?php elseif ($api_error !== null): ?>
                <span class="status-badge warning">🟡 API недоступен</span>
                <p style="margin-top: 12px;">
                    Прокси-сервер запущен, но API статистики недоступен.
                    Проверьте настройки <code>api-bind-to</code> в конфигурации.
                </p>
            <?php else: ?>
                <span class="status-badge offline">🔴 Статус неизвестен</span>
                <p style="margin-top: 12px;">
                    Не удалось подключиться к прокси-серверу. Возможно, он ещё запускается.
                </p>
            <?php endif; ?>
        </div>

        <!-- Параметры подключения -->
        <div class="card">
            <h2>Параметры подключения</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Порт прокси</div>
                    <div class="value mono"><?= htmlspecialchars($proxy_port) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">DNS резольвер</div>
                    <div class="value mono"><?= htmlspecialchars($dns) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Prometheus метрики</div>
                    <div class="value"><?= $prometheus_enabled ? '✅ Включены' : '❌ Отключены' ?></div>
                </div>
                <div class="info-item">
                    <div class="label">API порт</div>
                    <div class="value mono"><?= htmlspecialchars($api_port) ?></div>
                </div>
            </div>

            <div class="connection-info">
                <strong>🔗 Подключение к прокси</strong>
                <p style="margin-top: 8px; font-size: 0.9rem;">
                    Используйте этот адрес для подключения в Telegram:
                </p>
                <code>server = <?= htmlspecialchars($domain) ?>:<?= htmlspecialchars($proxy_port) ?></code>
                <p style="margin-top: 8px; font-size: 0.85rem; color: var(--text-muted);">
                    Замените домен на IP-адрес сервера, если DNS не настроен.
                </p>
            </div>
        </div>

        <!-- Секреты -->
        <div class="card">
            <h2>MTProto секреты</h2>
            <?php if (empty($secrets)): ?>
                <p style="color: var(--warning);">
                    ⚠️ Секреты не настроены. Прокси не будет принимать подключения.
                </p>
                <p style="margin-top: 8px; font-size: 0.9rem;">
                    Сгенерируйте секрет с помощью команды:
                </p>
                <code style="display: block; padding: 8px 12px; background: var(--bg); border-radius: 6px; margin-top: 8px; font-size: 0.85rem;">
                    sudo mtg-multi generate-secret google.com
                </code>
            <?php else: ?>
                <?php foreach ($secrets as $name => $secret): ?>
                    <div class="secret-item">
                        <div class="secret-name">🔑 <?= htmlspecialchars($name) ?></div>
                        <div class="secret-value"><?= htmlspecialchars($secret) ?></div>
                    </div>
                <?php endforeach; ?>
                <p style="margin-top: 12px; font-size: 0.85rem; color: var(--text-muted);">
                    Для подключения используйте секрет вместе с адресом сервера.
                </p>
            <?php endif; ?>
        </div>

        <!-- Информация о системе -->
        <div class="card">
            <h2>Информация о системе</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Версия приложения</div>
                    <div class="value">1.11.0~ynh2</div>
                </div>
                <div class="info-item">
                    <div class="label">Директория установки</div>
                    <div class="value mono" style="font-size: 0.8rem;"><?= __DIR__ ?></div>
                </div>
            </div>
        </div>

        <div class="footer">
            MTG-Multi Proxy для YunoHost — 
            <a href="https://github.com/dolonet/mtg-multi" target="_blank" rel="noopener">GitHub</a>
        </div>
    </div>
</body>
</html>