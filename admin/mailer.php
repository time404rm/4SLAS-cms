<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../includes/functions.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

$db = getDb();
$csrf_token = generateCsrfToken();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_smtp'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF failed');
    
    $smtp_enabled = isset($_POST['smtp_enabled']) ? 1 : 0;
    $smtp_host = trim($_POST['smtp_host']);
    $smtp_port = (int)$_POST['smtp_port'];
    $smtp_user = trim($_POST['smtp_user']);
    $smtp_pass = $_POST['smtp_pass'] ?? '';
    $smtp_encryption = $_POST['smtp_encryption'] ?? '';
    
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['smtp_enabled', $smtp_enabled]);
    $stmt->execute(['smtp_host', $smtp_host]);
    $stmt->execute(['smtp_port', $smtp_port]);
    $stmt->execute(['smtp_user', $smtp_user]);
    if (!empty($smtp_pass)) {
        $stmt->execute(['smtp_pass', $smtp_pass]);
    }
    $stmt->execute(['smtp_encryption', $smtp_encryption]);
    
    $message = 'Настройки SMTP сохранены';
}

// Тестовая отправка
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email_submit'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF failed');
    $testEmail = trim($_POST['test_email'] ?? '');
    if (empty($testEmail)) {
        $error = 'Введите email для теста.';
    } else {
        if (sendEmail($testEmail, 'Тестовое письмо от БЛОГ Т404', 'Если вы видите это письмо, значит SMTP настроен правильно.')) {
            $message = 'Тестовое письмо успешно отправлено на ' . htmlspecialchars($testEmail);
        } else {
            $error = 'Ошибка отправки. Подробности в логах /logs/mailer/';
        }
    }
}

$smtp_enabled = (int)getSetting('smtp_enabled');
$smtp_host = getSetting('smtp_host');
$smtp_port = (int)getSetting('smtp_port') ?: 587;
$smtp_user = getSetting('smtp_user');
$smtp_encryption = getSetting('smtp_encryption') ?: 'tls';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Настройки SMTP</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 8px; background: #0f1422; color: #e2e8f0; border: 1px solid #2a3650; border-radius: 4px; }
        .success { background: #1b5e3f; padding: 10px; border-radius: 4px; }
        .error { background: #7f1a1a; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Настройки SMTP</h1>
    
    <?php if ($message): ?><div class="success"><?php echo h($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        
        <div class="form-group">
            <label><input type="checkbox" name="smtp_enabled" value="1" <?php echo $smtp_enabled ? 'checked' : ''; ?>> Использовать SMTP</label>
        </div>
        <div class="form-group">
            <label>SMTP сервер</label>
            <input type="text" name="smtp_host" value="<?php echo h($smtp_host); ?>" placeholder="smtp.yandex.ru">
        </div>
        <div class="form-group">
            <label>Порт</label>
            <input type="number" name="smtp_port" value="<?php echo $smtp_port; ?>" min="1" max="65535">
        </div>
        <div class="form-group">
            <label>Имя пользователя (email)</label>
            <input type="text" name="smtp_user" value="<?php echo h($smtp_user); ?>">
        </div>
        <div class="form-group">
            <label>Пароль</label>
            <input type="password" name="smtp_pass" value="">
            <small>Оставьте пустым, чтобы не менять текущий пароль.</small>
        </div>
        <div class="form-group">
            <label>Шифрование</label>
            <select name="smtp_encryption">
                <option value="tls" <?php echo $smtp_encryption == 'tls' ? 'selected' : ''; ?>>TLS</option>
                <option value="ssl" <?php echo $smtp_encryption == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                <option value="" <?php echo !$smtp_encryption ? 'selected' : ''; ?>>Нет</option>
            </select>
        </div>
        <button type="submit" name="save_smtp">Сохранить настройки</button>
    </form>
    
    <hr>
    <h2>Тест отправки</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <div class="form-group">
            <label>Email для теста</label>
            <input type="email" name="test_email" required>
        </div>
        <button type="submit" name="test_email_submit">Отправить тестовое письмо</button>
    </form>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>