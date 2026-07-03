<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!defined('CAPTCHA_SESSION_KEY')) define('CAPTCHA_SESSION_KEY', 'captcha_result');

// ====== ЗАЩИТА ОТ ПОВТОРНОЙ УСТАНОВКИ ======
if (($_GET['step'] ?? '') !== '5' && (file_exists(__DIR__ . '/INSTALL_LOCK') || file_exists(__DIR__ . '/includes/config.php'))) {
    http_response_code(403);
    die('
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Уже установлено</title>
    <style>body{background:#0f1422;color:#e2e8f0;font-family:system-ui;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}
    .card{background:#1e2a3e;padding:40px;border-radius:24px;max-width:480px;text-align:center}
    h2{color:#ef4444}code{background:#0f1422;padding:2px 6px;border-radius:4px}
    a{color:#60a5fa}</style></head><body>
    <div class="card">
        <h2>⚠️ Система уже установлена</h2>
        <p>Файл конфигурации <code>includes/config.php</code> существует.</p>
        <p>Для переустановки удалите этот файл и очистите базу данных.</p>
        <p><a href="/">Вернуться на сайт →</a></p>
    </div></body></html>');
}

$step = (int)($_GET['step'] ?? 1);
$error = $_SESSION['install_error'] ?? null;
unset($_SESSION['install_error']);
$success = $_SESSION['install_success'] ?? null;
unset($_SESSION['install_success']);

function setFlash($msg, $type = 'success') {
    $_SESSION['install_' . $type] = $msg;
    header('Location: install.php?step=' . (($_GET['step'] ?? 1) + ($type === 'success' ? 1 : 0)));
    exit;
}

// ====== ШАГ 1: ПРОВЕРКА ТРЕБОВАНИЙ ======
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['install_error'] = 'Недействительный токен безопасности';
        header('Location: install.php?step=1');
        exit;
    }
    setFlash('Требования выполнены');
}

// ====== ШАГ 2: ПОДКЛЮЧЕНИЕ К БД ======
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['install_error'] = 'Недействительный токен безопасности';
        header('Location: install.php?step=2');
        exit;
    }
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';

    if (!$db_name || !$db_user) {
        $_SESSION['install_error'] = 'Заполните имя базы данных и пользователя';
        header('Location: install.php?step=2');
        exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
        $_SESSION['install_error'] = 'Недопустимое имя базы данных (только латиница, цифры, _)';
        header('Location: install.php?step=2');
        exit;
    }

    try {
        $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$db_name]);
        if (!$stmt->fetch()) {
            $pdo->exec("CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        $_SESSION['db_config'] = compact('db_host', 'db_name', 'db_user', 'db_pass');
        setFlash('Подключение к БД успешно');
    } catch (PDOException $e) {
        $_SESSION['install_error'] = 'Ошибка подключения к БД. Проверьте данные.';
        header('Location: install.php?step=2');
        exit;
    }
}

// ====== ШАГ 3: АДМИНИСТРАТОР + TIMEZONE ======
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['install_error'] = 'Недействительный токен безопасности';
        header('Location: install.php?step=3');
        exit;
    }
    $admin_username = trim($_POST['admin_username'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_pass = $_POST['admin_pass'] ?? '';
    $admin_pass2 = $_POST['admin_pass2'] ?? '';
    $timezone = $_POST['timezone'] ?? 'Europe/Moscow';

    $err = [];
    if (!$admin_username) $err[] = 'Укажите логин';
    if (!$admin_email || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) $err[] = 'Укажите корректный email';
    if (strlen($admin_pass) < 6) $err[] = 'Пароль должен быть не менее 6 символов';
    if ($admin_pass !== $admin_pass2) $err[] = 'Пароли не совпадают';
    if ($err) {
        $_SESSION['install_error'] = implode('<br>', $err);
        header('Location: install.php?step=3');
        exit;
    }

    $_SESSION['admin'] = [
        'username' => $admin_username,
        'email' => $admin_email,
        'password' => password_hash($admin_pass, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)
    ];
    $_SESSION['timezone'] = $timezone;
    setFlash('Данные администратора сохранены');
}

// ====== ШАГ 4: ВЫПОЛНЕНИЕ УСТАНОВКИ ======
if ($step === 4) {
    if (empty($_SESSION['db_config']) || empty($_SESSION['admin'])) {
        header('Location: install.php?step=1');
        exit;
    }

    $db = $_SESSION['db_config'];
    $admin = $_SESSION['admin'];
    $timezone = $_SESSION['timezone'] ?? 'Europe/Moscow';

    try {
        $pdo = new PDO("mysql:host={$db['db_host']};dbname={$db['db_name']};charset=utf8mb4", $db['db_user'], $db['db_pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Выполнение SQL
        $sqlFile = __DIR__ . '/install.sql';
        if (!file_exists($sqlFile)) throw new Exception("Файл install.sql не найден");
        $sql = file_get_contents($sqlFile);
        $queries = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($queries as $query) {
            if (!empty($query)) $pdo->exec($query);
        }

        // Создание администратора
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, is_verified) VALUES (?, ?, ?, 'admin', 1)");
        $stmt->execute([$admin['username'], $admin['email'], $admin['password']]);

        // Создание config.php
        $escHost = addslashes($db['db_host']);
        $escName = addslashes($db['db_name']);
        $escUser = addslashes($db['db_user']);
        $escPass = addslashes($db['db_pass']);
        $configContent = "<?php
define('DB_HOST', getenv('DB_HOST') ?: '{$escHost}');
define('DB_NAME', getenv('DB_NAME') ?: '{$escName}');
define('DB_USER', getenv('DB_USER') ?: '{$escUser}');
define('DB_PASS', getenv('DB_PASS') ?: '{$escPass}');

\$protocol = isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
\$host = \$_SERVER['HTTP_HOST'];
\$allowedHostsEnv = getenv('ALLOWED_HOSTS');
if (\$allowedHostsEnv !== false && \$allowedHostsEnv !== '') {
    \$allowedHosts = explode(',', \$allowedHostsEnv);
    if (!in_array(explode(':', \$host)[0], \$allowedHosts)) {
        \$host = \$allowedHosts[0];
    }
}
define('SITE_URL', \$protocol . \$host);

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('POSTS_IMG_DIR', UPLOAD_DIR . 'posts/');
define('CACHE_DIR', __DIR__ . '/../cache/');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}
define('CAPTCHA_SESSION_KEY', 'captcha_result');
date_default_timezone_set('$timezone');
?>";
        if (file_put_contents(__DIR__ . '/includes/config.php', $configContent) === false) {
            throw new Exception("Не удалось записать includes/config.php. Проверьте права на запись.");
        }

        // Создание директорий
        $dirs = [
            __DIR__ . '/uploads',
            __DIR__ . '/uploads/posts',
            __DIR__ . '/uploads/gallery',
            __DIR__ . '/uploads/temp',
            __DIR__ . '/uploads/pages',
            __DIR__ . '/cache',
            __DIR__ . '/backup'
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) mkdir($dir, 0755, true);
        }
        @chmod(__DIR__ . '/uploads', 0755);
        @chmod(__DIR__ . '/uploads/posts', 0755);
        @chmod(__DIR__ . '/cache', 0755);

        // .htaccess для uploads
        $htaccess = "Order Deny,Allow\nDeny from all\n<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">\n    Allow from all\n</FilesMatch>";
        file_put_contents(__DIR__ . '/uploads/.htaccess', $htaccess);

        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['install_complete'] = true;
        unset($_SESSION['db_config'], $_SESSION['admin'], $_SESSION['timezone']);

        // Блокировка повторной установки
        file_put_contents(__DIR__ . '/INSTALL_LOCK', 'Installed: ' . date('Y-m-d H:i:s'));

        header('Location: install.php?step=5');
        exit;

    } catch (Exception $e) {
        error_log('Install error: ' . $e->getMessage());
        $_SESSION['install_error'] = 'Ошибка установки. Проверьте права на запись и доступ к БД.';
        header('Location: install.php?step=4');
        exit;
    }
}

// ====== ШАГ 5: ЗАВЕРШЕНИЕ ======
if ($step === 5 && empty($_SESSION['install_complete'])) {
    header('Location: install.php?step=1');
    exit;
}

// ====== ПРОВЕРКИ ДЛЯ ШАГА 1 ======
$checks = [
    'php'    => version_compare(PHP_VERSION, '8.0.0', '>='),
    'pdo'    => extension_loaded('pdo_mysql'),
    'gd'     => extension_loaded('gd'),
    'curl'   => extension_loaded('curl'),
    'json'   => extension_loaded('json'),
    'sess'   => extension_loaded('session'),
    'zip'    => extension_loaded('zip'),
    'write'  => is_writable(__DIR__) && is_writable(__DIR__ . '/includes/'),
];
$allOk = $checks['php'] && $checks['pdo'] && $checks['gd'] && $checks['write'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Установка 4SLAS CMS</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:linear-gradient(135deg,#070b15,#0c1222);font-family:'Segoe UI',system-ui,sans-serif;min-height:100vh;display:flex;justify-content:center;align-items:center;padding:20px}
        .installer{max-width:640px;width:100%;background:rgba(18,25,45,0.92);backdrop-filter:blur(12px);border-radius:32px;border:1px solid rgba(255,255,255,0.08);box-shadow:0 25px 50px rgba(0,0,0,0.6);overflow:hidden}
        .header{background:linear-gradient(90deg,#1a2a4f,#0f172a);padding:30px 28px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.06)}
        .header h1{font-size:1.9rem;font-weight:700;background:linear-gradient(135deg,#fff,#88aaff);-webkit-background-clip:text;background-clip:text;color:transparent}
        .header p{color:#6c86a3;margin-top:4px;font-size:0.9rem}
        .steps{display:flex;background:rgba(0,0,0,0.4);padding:12px 20px;gap:6px}
        .step{flex:1;text-align:center;font-size:0.75rem;padding:7px 4px;border-radius:40px;background:#1e2a3e;color:#6c86a3;white-space:nowrap}
        .step.active{background:#2563eb;color:#fff}
        .step.done{background:#166534;color:#86efac}
        .content{padding:28px 24px;color:#e2e8f0;min-height:340px}
        .content h2{font-size:1.2rem;margin-bottom:16px;color:#e2e8f0}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;margin-bottom:6px;font-size:0.85rem;font-weight:500;color:#b9c7e6}
        .form-group input,.form-group select{width:100%;padding:11px 14px;background:#0f1422;border:1px solid #2a3650;border-radius:14px;color:#fff;font-size:0.9rem;outline:none;transition:border-color 0.2s}
        .form-group input:focus,.form-group select:focus{border-color:#2563eb}
        .form-group small{display:block;color:#6c86a3;font-size:0.75rem;margin-top:4px}
        button{background:linear-gradient(90deg,#2563eb,#1d4ed8);border:none;padding:12px 28px;border-radius:40px;font-weight:600;font-size:0.95rem;color:#fff;cursor:pointer;width:100%;transition:opacity 0.2s}
        button:hover{opacity:0.9}
        button:disabled{opacity:0.4;cursor:not-allowed}
        .error{background:rgba(220,38,38,0.18);border-left:4px solid #ef4444;padding:12px;border-radius:12px;margin-bottom:16px;color:#fca5a5;font-size:0.85rem}
        .success{background:rgba(34,197,94,0.15);border-left:4px solid #22c55e;padding:12px;border-radius:12px;margin-bottom:16px;color:#86efac;font-size:0.85rem}
        .req-list{list-style:none}
        .req-list li{padding:7px 0;border-bottom:1px solid rgba(42,54,80,0.5);display:flex;align-items:center;gap:10px;font-size:0.9rem}
        .req-list li:last-child{border-bottom:none}
        .req-icon{font-size:1rem;width:20px;text-align:center}
        .footer{background:#0b0f17;padding:14px;text-align:center;font-size:0.7rem;color:#3b4e6e}
        .footer a{color:#5b6e8c;text-decoration:none}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        @media(max-width:500px){.grid-2{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="installer">
    <div class="header">
        <h1>⚡ 4SLAS CMS</h1>
        <p>Установка системы</p>
    </div>

    <div class="steps">
        <?php $s = [1=>'Проверка',2=>'База данных',3=>'Администратор',4=>'Установка',5=>'Завершение']; ?>
        <?php foreach ($s as $num => $label): ?>
            <div class="step <?php echo $step > $num ? 'done' : ($step === $num ? 'active' : ''); ?>"><?php echo $num ?>. <?php echo $label; ?></div>
        <?php endforeach; ?>
    </div>

    <div class="content">
        <?php if ($error): ?><div class="error">⚠️ <?php echo $error; ?></div><?php endif; ?>
        <?php if ($success && $step < 5 && $step !== 4): ?><div class="success">✅ <?php echo $success; ?></div><?php endif; ?>

        <?php if ($step === 1): ?>
            <h2>Проверка системных требований</h2>
            <ul class="req-list">
                <li><span class="req-icon"><?php echo $checks['php'] ? '✅' : '❌'; ?></span> PHP ≥ 8.0 (текущая: <?php echo PHP_VERSION; ?>)</li>
                <li><span class="req-icon"><?php echo $checks['pdo'] ? '✅' : '❌'; ?></span> PDO_MySQL</li>
                <li><span class="req-icon"><?php echo $checks['gd'] ? '✅' : '❌'; ?></span> GD (конвертация WebP)</li>
                <li><span class="req-icon"><?php echo $checks['curl'] ? '✅' : '❌'; ?></span> cURL</li>
                <li><span class="req-icon"><?php echo $checks['json'] ? '✅' : '❌'; ?></span> JSON</li>
                <li><span class="req-icon"><?php echo $checks['sess'] ? '✅' : '❌'; ?></span> Сессии</li>
                <li><span class="req-icon"><?php echo $checks['zip'] ? '✅' : '❌'; ?></span> ZipArchive (для обновлений)</li>
                <li><span class="req-icon"><?php echo $checks['write'] ? '✅' : '❌'; ?></span> Права на запись (корень + includes/)</li>
            </ul>
            <?php if ($allOk): ?>
                <form method="post" style="margin-top:20px"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><button>Продолжить →</button></form>
            <?php else: ?>
                <div class="error">❌ Некоторые требования не выполнены. Исправьте и обновите страницу.</div>
            <?php endif; ?>

        <?php elseif ($step === 2): ?>
            <h2>Подключение к базе данных</h2>
            <p style="color:#6c86a3;font-size:0.85rem;margin-bottom:16px;">База данных будет создана автоматически, если не существует.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group"><label>Хост MySQL</label><input type="text" name="db_host" value="localhost"></div>
                <div class="form-group"><label>Имя базы данных</label><input type="text" name="db_name" placeholder="4slas_cms" required></div>
                <div class="grid-2">
                    <div class="form-group"><label>Пользователь</label><input type="text" name="db_user" required></div>
                    <div class="form-group"><label>Пароль</label><input type="password" name="db_pass"></div>
                </div>
                <button>Подключиться →</button>
            </form>

        <?php elseif ($step === 3): ?>
            <h2>Создание администратора</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="grid-2">
                    <div class="form-group"><label>Логин</label><input type="text" name="admin_username" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="admin_email" required></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label>Пароль (мин. 6)</label><input type="password" name="admin_pass" required></div>
                    <div class="form-group"><label>Повтор пароля</label><input type="password" name="admin_pass2" required></div>
                </div>
                <div class="form-group">
                    <label>Часовой пояс</label>
                    <select name="timezone" required>
                        <optgroup label="Европа">
                            <option value="Europe/Kaliningrad">Europe/Kaliningrad (UTC+2)</option>
                            <option value="Europe/Moscow" selected>Europe/Moscow (UTC+3)</option>
                            <option value="Europe/Volgograd">Europe/Volgograd (UTC+3)</option>
                            <option value="Europe/Samara">Europe/Samara (UTC+4)</option>
                        </optgroup>
                        <optgroup label="Азия">
                            <option value="Asia/Yekaterinburg">Asia/Yekaterinburg (UTC+5)</option>
                            <option value="Asia/Omsk">Asia/Omsk (UTC+6)</option>
                            <option value="Asia/Novosibirsk">Asia/Novosibirsk (UTC+7)</option>
                            <option value="Asia/Krasnoyarsk">Asia/Krasnoyarsk (UTC+7)</option>
                            <option value="Asia/Irkutsk">Asia/Irkutsk (UTC+8)</option>
                            <option value="Asia/Yakutsk">Asia/Yakutsk (UTC+9)</option>
                            <option value="Asia/Vladivostok">Asia/Vladivostok (UTC+10)</option>
                            <option value="Asia/Magadan">Asia/Magadan (UTC+11)</option>
                            <option value="Asia/Kamchatka">Asia/Kamchatka (UTC+12)</option>
                        </optgroup>
                        <option value="UTC">UTC</option>
                    </select>
                </div>
                <button>Создать →</button>
            </form>

        <?php elseif ($step === 4): ?>
            <h2>Установка...</h2>
            <div class="success" style="text-align:center;padding:20px;">
                <div style="font-size:2rem;margin-bottom:10px;">⏳</div>
                Идёт импорт базы данных и настройка системы...<br>
                <small style="color:#6c86a3;">Пожалуйста, подождите.</small>
            </div>
            <meta http-equiv="refresh" content="2;url=install.php?step=4">

        <?php elseif ($step === 5): ?>
            <h2 style="text-align:center;font-size:1.4rem;">✅ Установка завершена!</h2>
            <div style="text-align:center;padding:10px 0 20px;">
                <div style="font-size:3rem;margin-bottom:10px;">🎉</div>
                <p>Ваш сайт на 4SLAS CMS успешно установлен.</p>
                <p style="margin-top:12px;background:#0f1422;padding:12px;border-radius:12px;text-align:left;">
                    <strong>Данные для входа:</strong><br>
                    Логин: <code><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'admin'); ?></code><br>
                    Пароль: (тот, который вы указали)
                </p>
                <div style="background:#2c1f1a;border-left:4px solid #f59e0b;padding:12px;border-radius:12px;margin-top:12px;text-align:left;font-size:0.85rem;">
                    ⚠️ <strong>Важно!</strong> Удалите файл <code>install.php</code> и <code>install.sql</code> с сервера после установки.
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                <form action="admin/login.php" method="get" style="flex:1"><button>🔐 Войти в админку</button></form>
                <form action="index.php" method="get" style="flex:1"><button style="background:linear-gradient(90deg,#2a3650,#1e2a3e);">🏠 На сайт</button></form>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        4SLAS CMS &mdash; <a href="https://time404.ru">time404.ru</a>
    </div>
</div>
</body>
</html>
