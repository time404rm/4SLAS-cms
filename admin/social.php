<?php
require_once '../includes/functions.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

$db = getDb();
$csrf_token = generateCsrfToken();
$message = '';
$error = '';

$vk = getSetting('social_vk');
$telegram = getSetting('social_telegram');
$email = getSetting('social_email');
$icon_size = (int)getSetting('social_icon_size') ?: 32;
$icon_gap = (int)getSetting('social_icon_gap') ?: 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_social'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'CSRF токен неверен';
    } else {
        $vk = trim($_POST['vk'] ?? '');
        $telegram = trim($_POST['telegram'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $icon_size = max(16, (int)($_POST['icon_size'] ?? 32));
        $icon_gap = max(0, (int)($_POST['icon_gap'] ?? 15));

        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute(['social_vk', $vk]);
        $stmt->execute(['social_telegram', $telegram]);
        $stmt->execute(['social_email', $email]);
        $stmt->execute(['social_icon_size', $icon_size]);
        $stmt->execute(['social_icon_gap', $icon_gap]);

        $message = 'Настройки сохранены';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Настройки соцсетей (подвал)</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="url"], input[type="email"] { width: 100%; max-width: 400px; padding: 8px; }
        input[type="number"] { width: 100px; padding: 8px; }
        button { background: #2563eb; color: white; border: none; padding: 8px 20px; cursor: pointer; border-radius: 4px; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Настройки соцсетей (подвал)</h1>
    <?php if ($message): ?><div class="success"><?php echo h($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="save_social" value="1">

        <div class="form-group">
            <label>Ссылка VK</label>
            <input type="url" name="vk" value="<?php echo h($vk); ?>" placeholder="https://vk.com/...">
        </div>
        <div class="form-group">
            <label>Ссылка Telegram</label>
            <input type="url" name="telegram" value="<?php echo h($telegram); ?>" placeholder="https://t.me/...">
        </div>
        <div class="form-group">
            <label>Email адрес</label>
            <input type="email" name="email" value="<?php echo h($email); ?>" placeholder="example@domain.com">
        </div>
        <div class="form-group">
            <label>Размер иконок (px)</label>
            <input type="number" name="icon_size" value="<?php echo $icon_size; ?>" min="16" max="64" step="2">
        </div>
        <div class="form-group">
            <label>Расстояние между иконками (px)</label>
            <input type="number" name="icon_gap" value="<?php echo $icon_gap; ?>" min="0" max="50" step="1">
        </div>

        <button type="submit">Сохранить</button>
    </form>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>