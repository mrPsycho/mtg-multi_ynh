<?php
// Personal MTProto page: shows ONLY the secret of the SSO-authenticated user.
// $_SERVER['REMOTE_USER'] is set by nginx from the Ynh-User header injected by
// the YunoHost SSO (see fastcgi_params_with_auth).

const APP_VERSION     = '__VERSION__';
const APP_INSTALL_DIR = '__INSTALL_DIR__';
const APP_PATH        = '__PATH__';
const PROXY_HOST      = '__DOMAIN__';
const PROXY_PORT      = '__PORT__';
const API_PORT        = '__PORT_API__';
const FRONTEND_DOMAIN = '__FRONTEND_DOMAIN__';

const SECRETS_FILE = APP_INSTALL_DIR . '/conf/secrets.toml';
const CONFIG_FILE  = APP_INSTALL_DIR . '/conf/mtg.toml';
const RELOAD_FILE  = APP_INSTALL_DIR . '/conf/.reload';

const USER_RE = '/^[a-z0-9_.-]+$/i';

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function read_secrets(): array
{
    $out = [];
    if (!is_readable(SECRETS_FILE)) {
        return $out;
    }
    foreach (file(SECRETS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^"([^"]+)"\s*=\s*"([0-9a-fA-F]+)"$/', $line, $m) && preg_match(USER_RE, $m[1])) {
            $out[$m[1]] = $m[2];
        }
    }
    return $out;
}

// Same layout as `mtg-multi generate-secret --hex <domain>`: the "ee" marker,
// 16 random bytes, then the domain-fronting host in hex.
function generate_secret(): string
{
    return 'ee' . bin2hex(random_bytes(16)) . bin2hex(FRONTEND_DOMAIN);
}

// Rewrites both files in place so they keep their 0600 owner-only mode, then
// pokes the trigger watched by the systemd path unit that restarts the proxy.
function store_secret(string $user, string $secret): bool
{
    $secrets = read_secrets();
    $secrets[$user] = $secret;

    $body = '';
    foreach ($secrets as $name => $value) {
        $body .= '"' . $name . '" = "' . $value . '"' . "\n";
    }
    if (file_put_contents(SECRETS_FILE, $body, LOCK_EX) === false) {
        return false;
    }

    $config = @file(CONFIG_FILE, FILE_IGNORE_NEW_LINES);
    if ($config === false) {
        return false;
    }
    $head = [];
    foreach ($config as $line) {
        $head[] = $line;
        if (trim($line) === '[secrets]') {
            break;
        }
    }
    if (file_put_contents(CONFIG_FILE, implode("\n", $head) . "\n" . $body, LOCK_EX) === false) {
        return false;
    }

    return file_put_contents(RELOAD_FILE, (string) time()) !== false;
}

// Stateless CSRF token: bound to the secret currently held by this user, which
// a third-party site cannot read.
function csrf_token(string $user, ?string $secret): string
{
    return hash_hmac('sha256', $user, (string) $secret . APP_INSTALL_DIR);
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
if (!preg_match(USER_RE, $user)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("403 — Требуется авторизация через портал YunoHost.\n");
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'reset') {
    $current = read_secrets()[$user] ?? null;
    if (!hash_equals(csrf_token($user, $current), (string) ($_POST['token'] ?? ''))) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        exit("400 — Недействительный токен формы. Обновите страницу и попробуйте снова.\n");
    }
    $done = store_secret($user, generate_secret());
    header('Location: ' . rtrim(APP_PATH, '/') . '/?reset=' . ($done ? 'ok' : 'error'));
    exit;
}

$reset  = $_GET['reset'] ?? null;
$secret = read_secrets()[$user] ?? null;
$all    = stats();
$mine   = ($all !== null && isset($all['users'][$user])) ? $all['users'][$user] : null;

$tg_link  = null;
$tg_deep  = null;
if ($secret !== null) {
    $query   = http_build_query(['server' => PROXY_HOST, 'port' => PROXY_PORT, 'secret' => $secret]);
    $tg_link = 'https://t.me/proxy?' . $query;
    $tg_deep = 'tg://proxy?' . $query;
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
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            font-family: inherit;
        }
        .btn.secondary { background: #7f8c8d; }
        .btn.danger { background: var(--danger); }
        .btn-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .notice {
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 20px;
            border: 1px solid;
        }
        .notice.ok { background: #e8f8f0; border-color: #b7e4c7; color: #1e7d46; }
        .notice.bad { background: #fde8e8; border-color: #f8d7da; color: var(--danger); }
        .steps { padding-left: 20px; }
        .steps li { margin-bottom: 10px; }
        .steps code, .inline-code {
            background: var(--bg);
            border-radius: 4px;
            padding: 1px 6px;
            font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
            font-size: 0.9rem;
        }
        .hint {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 8px;
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

        <?php if ($reset === 'ok'): ?>
            <div class="notice ok">
                Ключ пересоздан. Старый ключ больше не работает — обновите настройки
                во всех устройствах. Прокси перезапускается, это занимает пару секунд.
            </div>
        <?php elseif ($reset === 'error'): ?>
            <div class="notice bad">
                Не удалось пересоздать ключ. Обратитесь к администратору сервера.
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Мой ключ доступа</h2>
            <?php if ($secret === null): ?>
                <div class="error-box">
                    Для вашей учётной записи ещё не создан секрет.
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="token" value="<?= h(csrf_token($user, $secret)) ?>">
                    <button class="btn" type="submit">Создать ключ</button>
                </form>
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
                    <code id="secret"><?= h($secret) ?></code>
                    <div class="btn-row">
                        <a class="btn" href="<?= h($tg_link) ?>">Подключить в Telegram</a>
                        <button class="btn secondary" type="button" data-copy="secret">Скопировать секрет</button>
                    </div>
                    <p class="hint">
                        Если ссылка не открывается, попробуйте
                        <a href="<?= h($tg_deep) ?>">tg://proxy</a>
                        или настройте вручную (см. ниже).
                    </p>
                </div>

                <form method="post" onsubmit="return confirm('Пересоздать ключ? Старый перестанет работать на всех устройствах.');">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="token" value="<?= h(csrf_token($user, $secret)) ?>">
                    <button class="btn danger" type="submit">Сбросить ключ</button>
                </form>
                <p class="hint">
                    Сбросьте ключ, если он попал к посторонним. После сброса нужно
                    заново подключить прокси на каждом устройстве.
                </p>
            <?php endif; ?>
        </div>

        <?php if ($secret !== null): ?>
        <div class="card">
            <h2>Как настроить Telegram</h2>
            <p>Самый простой способ — кнопка «Подключить в Telegram» выше: Telegram
               сам подставит сервер, порт и секрет, останется нажать «Подключиться».</p>

            <h3 style="margin: 18px 0 8px; font-size: 1rem;">Вручную</h3>
            <ol class="steps">
                <li><strong>Android:</strong> Настройки → Данные и память → Прокси →
                    Добавить прокси → MTProto.</li>
                <li><strong>iOS:</strong> Настройки → Данные и память → Прокси →
                    Добавить прокси → MTProto.</li>
                <li><strong>Desktop:</strong> Настройки → Продвинутые настройки →
                    Тип соединения → Использовать прокси → MTProto.</li>
                <li>Заполните поля значениями ниже и включите переключатель
                    «Использовать прокси для звонков», если нужен проксированный голос.</li>
            </ol>

            <div class="info-grid" style="margin-top: 12px;">
                <div class="info-item">
                    <div class="label">Server</div>
                    <div class="value mono" id="host"><?= h(PROXY_HOST) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Port</div>
                    <div class="value mono" id="port"><?= h(PROXY_PORT) ?></div>
                </div>
            </div>
            <div class="btn-row">
                <button class="btn secondary" type="button" data-copy="host">Скопировать сервер</button>
                <button class="btn secondary" type="button" data-copy="port">Скопировать порт</button>
                <button class="btn secondary" type="button" data-copy="secret">Скопировать секрет</button>
            </div>

            <p class="hint">
                Секрет вводится целиком, вместе с префиксом <span class="inline-code">ee</span> —
                он включает маскировку под <?= h(FRONTEND_DOMAIN) ?>.
                Если домен <span class="inline-code"><?= h(PROXY_HOST) ?></span> не резолвится
                в вашей сети, укажите вместо него IP-адрес сервера.
                Один ключ можно использовать на нескольких устройствах.
            </p>
        </div>
        <?php endif; ?>

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
    <script>
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var el = document.getElementById(btn.dataset.copy);
                if (!el) { return; }
                navigator.clipboard.writeText(el.textContent.trim()).then(function () {
                    var label = btn.textContent;
                    btn.textContent = 'Скопировано';
                    setTimeout(function () { btn.textContent = label; }, 1500);
                });
            });
        });
    </script>
</body>
</html>
