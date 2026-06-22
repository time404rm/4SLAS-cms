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

// Загружаем текущие настройки
$notify_on_comment = (int)getSetting('notify_on_comment');
$notify_moderators = (int)getSetting('notify_moderators');
$notify_author = (int)getSetting('notify_author');
$notify_only_approved = (int)getSetting('notify_only_approved');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notifications'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'CSRF токен неверен';
    } else {
        $notify_on_comment_new = isset($_POST['notify_on_comment']) ? 1 : 0;
        $notify_moderators_new = isset($_POST['notify_moderators']) ? 1 : 0;
        $notify_author_new = isset($_POST['notify_author']) ? 1 : 0;
        $notify_only_approved_new = isset($_POST['notify_only_approved']) ? 1 : 0;

        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute(['notify_on_comment', $notify_on_comment_new]);
        $stmt->execute(['notify_moderators', $notify_moderators_new]);
        $stmt->execute(['notify_author', $notify_author_new]);
        $stmt->execute(['notify_only_approved', $notify_only_approved_new]);

        $message = 'Настройки уведомлений сохранены';
        // обновляем переменные для отображения
        $notify_on_comment = $notify_on_comment_new;
        $notify_moderators = $notify_moderators_new;
        $notify_author = $notify_author_new;
        $notify_only_approved = $notify_only_approved_new;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Настройки уведомлений</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .notifications-form {
            max-width: 600px;
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .checkbox-group label {
            font-weight: normal;
            cursor: pointer;
        }
        .sub-options {
            margin-left: 25px;
            padding-left: 15px;
            border-left: 2px solid #2a3650;
        }
        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 20px;
            cursor: pointer;
            border-radius: 4px;
        }
        .success { background: #1b5e3f; color: #fff; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .error { background: #7f1a1a; color: #fff; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Настройки уведомлений</h1>

    <?php if ($message): ?><div class="success"><?php echo h($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>

    <form method="post" class="notifications-form">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="save_notifications" value="1">

        <div class="form-group checkbox-group">
            <input type="checkbox" id="notify_on_comment" name="notify_on_comment" value="1" <?php echo $notify_on_comment ? 'checked' : ''; ?>>
            <label for="notify_on_comment">Включить уведомления о новых комментариях</label>
        </div>

        <div class="sub-options" id="notify_options" style="<?php echo !$notify_on_comment ? 'display:none;' : ''; ?>">
            <div class="checkbox-group">
                <input type="checkbox" id="notify_moderators" name="notify_moderators" value="1" <?php echo $notify_moderators ? 'checked' : ''; ?>>
                <label for="notify_moderators">Уведомлять модераторов</label>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="notify_author" name="notify_author" value="1" <?php echo $notify_author ? 'checked' : ''; ?>>
                <label for="notify_author">Уведомлять автора поста</label>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="notify_only_approved" name="notify_only_approved" value="1" <?php echo $notify_only_approved ? 'checked' : ''; ?>>
                <label for="notify_only_approved">Отправлять уведомления только после одобрения комментария</label>
                <small>(если включена модерация)</small>
            </div>
        </div>

        <button type="submit">Сохранить настройки</button>
    </form>

    <script>
        document.getElementById('notify_on_comment').addEventListener('change', function() {
            var options = document.getElementById('notify_options');
            options.style.display = this.checked ? 'block' : 'none';
        });
    </script>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>