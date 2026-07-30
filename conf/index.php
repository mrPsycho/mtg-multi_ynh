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

const LANGS = ['en' => 'English', 'fr' => 'Français', 'ru' => 'Русский'];

// Strings tagged "html" below intentionally carry markup and are echoed raw;
// everything interpolated into them is escaped at the call site.
const STRINGS = [
    'en' => [
        'title'             => 'MTG-Multi Proxy — my access',
        'tagline'           => 'MTProto proxy for Telegram',
        'subtitle'          => 'Personal access for user %s', // html
        'reset_ok'          => 'Your key has been regenerated. The old one no longer works — update it on every device. The proxy is restarting, which takes a couple of seconds.',
        'reset_error'       => 'The key could not be regenerated. Please contact the server administrator.',
        'card_key'          => 'My access key',
        'no_secret'         => 'No secret has been generated for your account yet.',
        'btn_create'        => 'Create a key',
        'lbl_server'        => 'Server',
        'lbl_port'          => 'Port',
        'secret_title'      => 'Secret (yours only — never share it)',
        'btn_connect'       => 'Connect in Telegram',
        'btn_copy_secret'   => 'Copy secret',
        'hint_deeplink'     => 'If the link does not open, try %s or set the proxy up manually (see below).', // html
        'confirm_reset'     => 'Regenerate the key? The old one will stop working on every device.',
        'btn_reset'         => 'Reset key',
        'hint_reset'        => 'Reset the key if it leaked to someone else. After a reset you have to reconnect the proxy on each device.',
        'card_howto'        => 'How to set up Telegram',
        'howto_intro'       => 'The easiest way is the “Connect in Telegram” button above: Telegram fills in the server, port and secret for you, and you only have to tap “Connect”.',
        'howto_manual'      => 'Manually',
        'step_android'      => '<strong>Android:</strong> Settings → Data and Storage → Proxy → Add Proxy → MTProto.', // html
        'step_ios'          => '<strong>iOS:</strong> Settings → Data and Storage → Proxy → Add Proxy → MTProto.', // html
        'step_desktop'      => '<strong>Desktop:</strong> Settings → Advanced → Connection type → Use custom proxy → MTProto.', // html
        'step_fill'         => 'Fill in the fields with the values below, and turn on “Use proxy for calls” if you want voice traffic proxied too.',
        'btn_copy_host'     => 'Copy server',
        'btn_copy_port'     => 'Copy port',
        'hint_secret'       => 'Enter the secret in full, including the %1$s prefix — it enables domain fronting through %2$s. If %3$s does not resolve on your network, use the server IP address instead. The same key works on several devices at once.', // html
        'card_stats'        => 'My statistics',
        'offline'           => 'Proxy unreachable',
        'stats_unavailable' => 'Statistics could not be fetched. The service may be stopped or still starting up.',
        'online'            => 'Online',
        'stat_connections'  => 'Active connections',
        'stat_last_seen'    => 'Last activity',
        'stat_in'           => 'Received',
        'stat_out'          => 'Sent',
        'footer_for'        => 'MTG-Multi Proxy for YunoHost',
        'copied'            => 'Copied',
        'err_403'           => '403 — Please sign in through the YunoHost portal.',
        'err_400'           => '400 — Invalid form token. Reload the page and try again.',
    ],
    'fr' => [
        'title'             => 'MTG-Multi Proxy — mon accès',
        'tagline'           => 'Proxy MTProto pour Telegram',
        'subtitle'          => 'Accès personnel pour l’utilisateur %s',
        'reset_ok'          => 'Votre clé a été régénérée. L’ancienne ne fonctionne plus — mettez-la à jour sur tous vos appareils. Le proxy redémarre, cela prend quelques secondes.',
        'reset_error'       => 'La clé n’a pas pu être régénérée. Contactez l’administrateur du serveur.',
        'card_key'          => 'Ma clé d’accès',
        'no_secret'         => 'Aucun secret n’a encore été généré pour votre compte.',
        'btn_create'        => 'Créer une clé',
        'lbl_server'        => 'Serveur',
        'lbl_port'          => 'Port',
        'secret_title'      => 'Secret (le vôtre uniquement — ne le partagez jamais)',
        'btn_connect'       => 'Se connecter dans Telegram',
        'btn_copy_secret'   => 'Copier le secret',
        'hint_deeplink'     => 'Si le lien ne s’ouvre pas, essayez %s ou configurez le proxy manuellement (voir ci-dessous).',
        'confirm_reset'     => 'Régénérer la clé ? L’ancienne cessera de fonctionner sur tous les appareils.',
        'btn_reset'         => 'Réinitialiser la clé',
        'hint_reset'        => 'Réinitialisez la clé si elle a été divulguée. Après la réinitialisation, vous devez reconfigurer le proxy sur chaque appareil.',
        'card_howto'        => 'Comment configurer Telegram',
        'howto_intro'       => 'Le plus simple est le bouton « Se connecter dans Telegram » ci-dessus : Telegram remplit le serveur, le port et le secret, il ne reste qu’à appuyer sur « Se connecter ».',
        'howto_manual'      => 'Manuellement',
        'step_android'      => '<strong>Android :</strong> Paramètres → Données et stockage → Proxy → Ajouter un proxy → MTProto.',
        'step_ios'          => '<strong>iOS :</strong> Réglages → Données et stockage → Proxy → Ajouter un proxy → MTProto.',
        'step_desktop'      => '<strong>Desktop :</strong> Paramètres → Paramètres avancés → Type de connexion → Utiliser un proxy personnalisé → MTProto.',
        'step_fill'         => 'Remplissez les champs avec les valeurs ci-dessous et activez « Utiliser le proxy pour les appels » si vous voulez aussi faire transiter la voix.',
        'btn_copy_host'     => 'Copier le serveur',
        'btn_copy_port'     => 'Copier le port',
        'hint_secret'       => 'Saisissez le secret en entier, préfixe %1$s compris — il active le domain fronting via %2$s. Si %3$s ne se résout pas sur votre réseau, utilisez l’adresse IP du serveur à la place. La même clé fonctionne sur plusieurs appareils à la fois.',
        'card_stats'        => 'Mes statistiques',
        'offline'           => 'Proxy injoignable',
        'stats_unavailable' => 'Impossible de récupérer les statistiques. Le service est peut-être arrêté ou en cours de démarrage.',
        'online'            => 'En ligne',
        'stat_connections'  => 'Connexions actives',
        'stat_last_seen'    => 'Dernière activité',
        'stat_in'           => 'Reçu',
        'stat_out'          => 'Envoyé',
        'footer_for'        => 'MTG-Multi Proxy pour YunoHost',
        'copied'            => 'Copié',
        'err_403'           => '403 — Connectez-vous via le portail YunoHost.',
        'err_400'           => '400 — Jeton de formulaire invalide. Rechargez la page et réessayez.',
    ],
    'ru' => [
        'title'             => 'MTG-Multi Proxy — мой доступ',
        'tagline'           => 'MTProto proxy для Telegram',
        'subtitle'          => 'Персональный доступ для пользователя %s',
        'reset_ok'          => 'Ключ пересоздан. Старый ключ больше не работает — обновите настройки на всех устройствах. Прокси перезапускается, это занимает пару секунд.',
        'reset_error'       => 'Не удалось пересоздать ключ. Обратитесь к администратору сервера.',
        'card_key'          => 'Мой ключ доступа',
        'no_secret'         => 'Для вашей учётной записи ещё не создан секрет.',
        'btn_create'        => 'Создать ключ',
        'lbl_server'        => 'Сервер',
        'lbl_port'          => 'Порт',
        'secret_title'      => 'Секрет (только ваш, не передавайте его другим)',
        'btn_connect'       => 'Подключить в Telegram',
        'btn_copy_secret'   => 'Скопировать секрет',
        'hint_deeplink'     => 'Если ссылка не открывается, попробуйте %s или настройте прокси вручную (см. ниже).',
        'confirm_reset'     => 'Пересоздать ключ? Старый перестанет работать на всех устройствах.',
        'btn_reset'         => 'Сбросить ключ',
        'hint_reset'        => 'Сбросьте ключ, если он попал к посторонним. После сброса нужно заново подключить прокси на каждом устройстве.',
        'card_howto'        => 'Как настроить Telegram',
        'howto_intro'       => 'Самый простой способ — кнопка «Подключить в Telegram» выше: Telegram сам подставит сервер, порт и секрет, останется нажать «Подключиться».',
        'howto_manual'      => 'Вручную',
        'step_android'      => '<strong>Android:</strong> Настройки → Данные и память → Прокси → Добавить прокси → MTProto.',
        'step_ios'          => '<strong>iOS:</strong> Настройки → Данные и память → Прокси → Добавить прокси → MTProto.',
        'step_desktop'      => '<strong>Desktop:</strong> Настройки → Продвинутые настройки → Тип соединения → Использовать прокси → MTProto.',
        'step_fill'         => 'Заполните поля значениями ниже и включите «Использовать прокси для звонков», если нужен проксированный голос.',
        'btn_copy_host'     => 'Скопировать сервер',
        'btn_copy_port'     => 'Скопировать порт',
        'hint_secret'       => 'Секрет вводится целиком, вместе с префиксом %1$s — он включает маскировку под %2$s. Если %3$s не резолвится в вашей сети, укажите вместо него IP-адрес сервера. Один ключ можно использовать на нескольких устройствах.',
        'card_stats'        => 'Моя статистика',
        'offline'           => 'Прокси недоступен',
        'stats_unavailable' => 'Не удалось получить статистику. Возможно, сервис остановлен или ещё запускается.',
        'online'            => 'Онлайн',
        'stat_connections'  => 'Активных соединений',
        'stat_last_seen'    => 'Последняя активность',
        'stat_in'           => 'Принято',
        'stat_out'          => 'Отправлено',
        'footer_for'        => 'MTG-Multi Proxy для YunoHost',
        'copied'            => 'Скопировано',
        'err_403'           => '403 — Требуется авторизация через портал YunoHost.',
        'err_400'           => '400 — Недействительный токен формы. Обновите страницу и попробуйте снова.',
    ],
];

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ?lang= wins and is remembered, then the browser's Accept-Language, then English.
function pick_lang(): string
{
    $chosen = $_GET['lang'] ?? null;
    if (is_string($chosen) && array_key_exists($chosen, LANGS)) {
        setcookie('mtg_lang', $chosen, [
            'expires'  => time() + 31536000,
            'path'     => base_path() . '/',
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        return $chosen;
    }

    $saved = $_COOKIE['mtg_lang'] ?? null;
    if (is_string($saved) && array_key_exists($saved, LANGS)) {
        return $saved;
    }

    foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') as $part) {
        $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
        if (array_key_exists($code, LANGS)) {
            return $code;
        }
    }

    return 'en';
}

function t(string $key, string ...$args): string
{
    global $lang;
    $table = STRINGS;
    $s = $table[$lang][$key] ?? $table['en'][$key] ?? $key;
    return $args ? vsprintf($s, $args) : $s;
}

function base_path(): string
{
    return rtrim(APP_PATH, '/');
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

$lang = pick_lang();

$user = $_SERVER['REMOTE_USER'] ?? '';
if (!preg_match(USER_RE, $user)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit(t('err_403') . "\n");
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'reset') {
    $current = read_secrets()[$user] ?? null;
    if (!hash_equals(csrf_token($user, $current), (string) ($_POST['token'] ?? ''))) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        exit(t('err_400') . "\n");
    }
    $done = store_secret($user, generate_secret());
    header('Location: ' . base_path() . '/?reset=' . ($done ? 'ok' : 'error'));
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
<html lang="<?= h($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= h(t('title')) ?></title>
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
        .topline {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
            margin-bottom: 2rem;
        }
        .subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        .lang-switch { font-size: 0.85rem; }
        .lang-switch a {
            color: var(--text-muted);
            text-decoration: none;
            margin-left: 8px;
        }
        .lang-switch a:hover { text-decoration: underline; }
        .lang-switch a.active { color: var(--accent); font-weight: 600; }
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
            <small><?= h(t('tagline')) ?></small>
        </h1>
        <div class="topline">
            <p class="subtitle"><?= t('subtitle', '<strong>' . h($user) . '</strong>') ?></p>
            <p class="lang-switch">
                <?php foreach (LANGS as $code => $name): ?>
                    <a href="<?= h(base_path() . '/?lang=' . $code) ?>"
                       class="<?= $code === $lang ? 'active' : '' ?>"
                       lang="<?= h($code) ?>"><?= h($name) ?></a>
                <?php endforeach; ?>
            </p>
        </div>

        <?php if ($reset === 'ok'): ?>
            <div class="notice ok"><?= h(t('reset_ok')) ?></div>
        <?php elseif ($reset === 'error'): ?>
            <div class="notice bad"><?= h(t('reset_error')) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2><?= h(t('card_key')) ?></h2>
            <?php if ($secret === null): ?>
                <div class="error-box"><?= h(t('no_secret')) ?></div>
                <form method="post">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="token" value="<?= h(csrf_token($user, $secret)) ?>">
                    <button class="btn" type="submit"><?= h(t('btn_create')) ?></button>
                </form>
            <?php else: ?>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label"><?= h(t('lbl_server')) ?></div>
                        <div class="value mono"><?= h(PROXY_HOST) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label"><?= h(t('lbl_port')) ?></div>
                        <div class="value mono"><?= h(PROXY_PORT) ?></div>
                    </div>
                </div>
                <div class="connection-info">
                    <strong>🔑 <?= h(t('secret_title')) ?></strong>
                    <code id="secret"><?= h($secret) ?></code>
                    <div class="btn-row">
                        <a class="btn" href="<?= h($tg_link) ?>"><?= h(t('btn_connect')) ?></a>
                        <button class="btn secondary" type="button" data-copy="secret"><?= h(t('btn_copy_secret')) ?></button>
                    </div>
                    <p class="hint">
                        <?= t('hint_deeplink', '<a href="' . h($tg_deep) . '">tg://proxy</a>') ?>
                    </p>
                </div>

                <form method="post" onsubmit="return confirm(<?= h(json_encode(t('confirm_reset'), JSON_UNESCAPED_UNICODE)) ?>);">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="token" value="<?= h(csrf_token($user, $secret)) ?>">
                    <button class="btn danger" type="submit"><?= h(t('btn_reset')) ?></button>
                </form>
                <p class="hint"><?= h(t('hint_reset')) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($secret !== null): ?>
        <div class="card">
            <h2><?= h(t('card_howto')) ?></h2>
            <p><?= h(t('howto_intro')) ?></p>

            <h3 style="margin: 18px 0 8px; font-size: 1rem;"><?= h(t('howto_manual')) ?></h3>
            <ol class="steps">
                <li><?= t('step_android') ?></li>
                <li><?= t('step_ios') ?></li>
                <li><?= t('step_desktop') ?></li>
                <li><?= h(t('step_fill')) ?></li>
            </ol>

            <div class="info-grid" style="margin-top: 12px;">
                <div class="info-item">
                    <div class="label"><?= h(t('lbl_server')) ?></div>
                    <div class="value mono" id="host"><?= h(PROXY_HOST) ?></div>
                </div>
                <div class="info-item">
                    <div class="label"><?= h(t('lbl_port')) ?></div>
                    <div class="value mono" id="port"><?= h(PROXY_PORT) ?></div>
                </div>
            </div>
            <div class="btn-row">
                <button class="btn secondary" type="button" data-copy="host"><?= h(t('btn_copy_host')) ?></button>
                <button class="btn secondary" type="button" data-copy="port"><?= h(t('btn_copy_port')) ?></button>
                <button class="btn secondary" type="button" data-copy="secret"><?= h(t('btn_copy_secret')) ?></button>
            </div>

            <p class="hint">
                <?= t(
                    'hint_secret',
                    '<span class="inline-code">ee</span>',
                    h(FRONTEND_DOMAIN),
                    '<span class="inline-code">' . h(PROXY_HOST) . '</span>'
                ) ?>
            </p>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2><?= h(t('card_stats')) ?></h2>
            <?php if ($all === null): ?>
                <span class="status-badge offline">🔴 <?= h(t('offline')) ?></span>
                <p style="margin-top: 12px;"><?= h(t('stats_unavailable')) ?></p>
            <?php else: ?>
                <span class="status-badge online">🟢 <?= h(t('online')) ?></span>
                <div class="info-grid" style="margin-top: 16px;">
                    <div class="info-item">
                        <div class="label"><?= h(t('stat_connections')) ?></div>
                        <div class="value"><?= h((string) ($mine['connections'] ?? 0)) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label"><?= h(t('stat_last_seen')) ?></div>
                        <div class="value" style="font-size: 0.95rem;">
                            <?= h($mine['last_seen'] ?? '—') ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="label"><?= h(t('stat_in')) ?></div>
                        <div class="value"><?= h(human_bytes($mine['bytes_in'] ?? 0)) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label"><?= h(t('stat_out')) ?></div>
                        <div class="value"><?= h(human_bytes($mine['bytes_out'] ?? 0)) ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <?= h(t('footer_for')) ?> <?= h(APP_VERSION) ?> —
            <a href="https://github.com/dolonet/mtg-multi" target="_blank" rel="noopener">GitHub</a>
        </div>
    </div>
    <script>
        var COPIED = <?= json_encode(t('copied'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var el = document.getElementById(btn.dataset.copy);
                if (!el) { return; }
                navigator.clipboard.writeText(el.textContent.trim()).then(function () {
                    var label = btn.textContent;
                    btn.textContent = COPIED;
                    setTimeout(function () { btn.textContent = label; }, 1500);
                });
            });
        });
    </script>
</body>
</html>
