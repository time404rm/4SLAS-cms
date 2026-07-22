<?php
require_once '../includes/functions.php';
if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

$db = getDb();
$csrf_token = generateCsrfToken();
$message = '';
$error = '';

// Сохранение настроек виджетов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_widgets'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'CSRF токен неверен';
    } else {
        // Сохраняем флаг включения и ID приложения
        $vk_enabled = isset($_POST['vk_enabled']) ? 1 : 0;
        $vk_app_id = trim($_POST['vk_app_id'] ?? '');
        
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute(['vk_widget_enabled', $vk_enabled]);
        $stmt->execute(['vk_app_id', $vk_app_id]);
        
        $message = 'Настройки виджетов сохранены';
    }
}

// Загружаем текущие настройки
$vk_enabled = (int)getSetting('vk_widget_enabled');
$vk_app_id = getSetting('vk_app_id') ?: '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Настройки виджетов</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin-widgets.css">
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Управление виджетами</h1>
    
    <?php if ($message): ?>
        <div class="success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="widgets-list">
        <!-- Виджет VK Comments -->
        <div class="widget-card">
            <h3>ВКонтакте Комментарии (VK Comments)</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="save_widgets" value="1">
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="vk_enabled" value="1" <?php echo $vk_enabled ? 'checked' : ''; ?>>
                        Включить виджет VK Comments
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="vk_app_id">ID приложения ВКонтакте</label>
                    <input type="text" id="vk_app_id" name="vk_app_id" value="<?php echo htmlspecialchars($vk_app_id); ?>" placeholder="например, 12345678">
                    <small>Необязательно, но рекомендуется для полноценной работы виджета. <a href="https://vk.ru/editapp?act=create" target="_blank">Создать приложение</a> → получить числовой ID.</small>
                </div>
                
                <button type="submit" class="btn-save">Сохранить настройки</button>
            </form>
        </div>
        
        <!-- Здесь в будущем можно добавить другие виджеты (Facebook, Disqus, Telegram и т.д.) -->
    </div>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>