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
        .status-badge.loading { background: #fef5e7; color: var(--warning); }
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
        <p class="subtitle" id="subtitle">
            Загрузка статуса прокси-сервера...
        </p>

        <!-- Статус сервера -->
        <div class="card">
            <h2>Статус сервера</h2>
            <div id="server-status">
                <span class="status-badge loading">🟡 Загрузка...</span>
                <p style="margin-top: 12px;">Подключение к API статистики...</p>
            </div>
        </div>

        <!-- Параметры подключения -->
        <div class="card">
            <h2>Параметры подключения</h2>
            <div class="info-grid" id="params-grid">
                <div class="info-item">
                    <div class="label">Порт прокси</div>
                    <div class="value mono" id="proxy-port">—</div>
                </div>
                <div class="info-item">
                    <div class="label">DNS резольвер</div>
                    <div class="value mono" id="dns-resolver">—</div>
                </div>
                <div class="info-item">
                    <div class="label">Prometheus метрики</div>
                    <div class="value" id="prometheus-status">—</div>
                </div>
                <div class="info-item">
                    <div class="label">API порт</div>
                    <div class="value mono" id="api-port">—</div>
                </div>
            </div>

            <div class="connection-info">
                <strong>🔗 Подключение к прокси</strong>
                <p style="margin-top: 8px; font-size: 0.9rem;">
                    Используйте этот адрес для подключения в Telegram:
                </p>
                <code id="connection-string">server = ...</code>
                <p style="margin-top: 8px; font-size: 0.85rem; color: var(--text-muted);">
                    Замените домен на IP-адрес сервера, если DNS не настроен.
                </p>
            </div>
        </div>

        <!-- Секреты -->
        <div class="card">
            <h2>MTProto секреты</h2>
            <div id="secrets-list">
                <p style="color: var(--warning);">⚠️ Загрузка секретов...</p>
            </div>
        </div>

        <!-- Информация о системе -->
        <div class="card">
            <h2>Информация о системе</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Версия приложения</div>
                    <div class="value">1.11.0~ynh3</div>
                </div>
                <div class="info-item">
                    <div class="label">Директория установки</div>
                    <div class="value mono" style="font-size: 0.8rem;" id="install-dir">—</div>
                </div>
            </div>
        </div>

        <div class="footer">
            MTG-Multi Proxy для YunoHost — 
            <a href="https://github.com/dolonet/mtg-multi" target="_blank" rel="noopener">GitHub</a>
        </div>
    </div>

    <script>
    // Parse TOML-like config from the API
    async function fetchConfig() {
        try {
            // Try to fetch stats from the API
            const statsResp = await fetch('http://127.0.0.1:9090/stats');
            if (statsResp.ok) {
                const stats = await statsResp.json();
                document.getElementById('server-status').innerHTML = 
                    '<span class="status-badge online">🟢 Онлайн</span>' +
                    '<p style="margin-top: 12px;">' +
                    'Прокси-сервер работает и принимает подключения.<br>' +
                    '<small>Запущен: ' + new Date(stats.started_at).toLocaleString() + 
                    ' | Соединений: ' + stats.total_connections + '</small>' +
                    '</p>';
                document.getElementById('subtitle').textContent = 'Прокси-сервер работает';
            }
        } catch (e) {
            document.getElementById('server-status').innerHTML = 
                '<span class="status-badge offline">🔴 Статус неизвестен</span>' +
                '<p style="margin-top: 12px;">' +
                'Не удалось подключиться к API статистики. Возможно, сервер ещё запускается.' +
                '</p>';
        }
    }

    // Fetch config file and parse it client-side
    async function fetchConfigFile() {
        try {
            const resp = await fetch('mtg.toml');
            if (!resp.ok) throw new Error('Config not accessible');
            const text = await resp.text();
            const config = parseTOML(text);
            
            // Extract proxy port
            const bindTo = config['bind-to'] || '0.0.0.0:3128';
            const proxyPort = bindTo.split(':').pop();
            document.getElementById('proxy-port').textContent = proxyPort;
            
            // Extract API port
            const apiBind = config['api-bind-to'] || '127.0.0.1:9090';
            const apiPort = apiBind.split(':').pop();
            document.getElementById('api-port').textContent = apiPort;
            
            // Extract DNS
            const dns = config['network']?.dns || '1.1.1.1';
            document.getElementById('dns-resolver').textContent = dns;
            
            // Extract Prometheus
            const promEnabled = config['stats.prometheus']?.enabled;
            document.getElementById('prometheus-status').textContent = 
                promEnabled ? '✅ Включены' : '❌ Отключены';
            
            // Extract secrets
            const secrets = config['secrets'] || {};
            const secretsDiv = document.getElementById('secrets-list');
            if (Object.keys(secrets).length === 0) {
                secretsDiv.innerHTML = 
                    '<p style="color: var(--warning);">⚠️ Секреты не настроены.</p>';
            } else {
                let html = '';
                for (const [name, secret] of Object.entries(secrets)) {
                    html += '<div class="secret-item">' +
                        '<div class="secret-name">🔑 ' + escapeHtml(name) + '</div>' +
                        '<div class="secret-value">' + escapeHtml(secret) + '</div>' +
                        '</div>';
                }
                secretsDiv.innerHTML = html;
            }
            
            // Build connection string
            const domain = window.location.hostname || 'mtg.local';
            document.getElementById('connection-string').textContent = 
                'server = ' + domain + ':' + proxyPort;
            
            // Install dir
            document.getElementById('install-dir').textContent = '/var/www/mtg-multi';
            
        } catch (e) {
            console.error('Failed to load config:', e);
            document.getElementById('params-grid').innerHTML = 
                '<div class="error-box">' +
                'Не удалось загрузить конфигурацию. ' +
                'Убедитесь, что файл mtg.toml доступен для чтения.' +
                '</div>';
        }
    }

    // Simple TOML parser (handles our config structure)
    function parseTOML(text) {
        const result = {};
        let currentSection = result;
        
        text.split('\n').forEach(line => {
            line = line.trim();
            if (!line || line.startsWith('#')) return;
            
            // Section header
            const sectionMatch = line.match(/^\[([^\]]+)\]$/);
            if (sectionMatch) {
                const parts = sectionMatch[1].split('.');
                currentSection = result;
                parts.forEach(part => {
                    if (!currentSection[part]) currentSection[part] = {};
                    currentSection = currentSection[part];
                });
                return;
            }
            
            // Key = "value"
            const kvMatch = line.match(/^(?:'([^']+)'|"([^"]+)"|([a-zA-Z0-9_-]+))\s*=\s*(?:"([^"]*)"|'([^']*)'|(true|false|\d+(?:\.\d+)?))$/);
            if (kvMatch) {
                const key = kvMatch[1] || kvMatch[2] || kvMatch[3];
                let value = kvMatch[4] || kvMatch[5] || kvMatch[6];
                if (value === 'true') value = true;
                else if (value === 'false') value = false;
                else if (!isNaN(value) && value !== '') value = Number(value);
                currentSection[key] = value;
            }
        });
        
        return result;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Load everything on page load
    fetchConfig();
    fetchConfigFile();
    </script>
</body>
</html>