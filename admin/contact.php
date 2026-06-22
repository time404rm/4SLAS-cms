<?php
require_once '../includes/functions.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

$db = getDb();
$csrf_token = generateCsrfToken();
$message = '';
$error = '';

// ---- ВИЗИТНАЯ КАРТОЧКА ----
$stmt = $db->prepare("SELECT * FROM contact_info WHERE id = 1");
$stmt->execute();
$contact = $stmt->fetch();
if (!$contact) {
    $contact = ['full_name' => '', 'position' => '', 'bio' => '', 'photo' => ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_contact'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) { $error = 'CSRF токен неверен'; }
    else {
        $full_name = trim($_POST['full_name']);
        $position = trim($_POST['position']);
        $bio = trim($_POST['bio']);
        $photo = $contact['photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowed)) { $error = 'Недопустимый формат изображения.'; }
            else {
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $newName = 'contact_' . time() . '.' . $ext;
                $uploadDir = UPLOAD_DIR . 'contact/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $newName)) {
                    $photo = '/uploads/contact/' . $newName;
                    if ($contact['photo'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $contact['photo'])) { unlink($_SERVER['DOCUMENT_ROOT'] . $contact['photo']); }
                } else { $error = 'Ошибка загрузки файла'; }
            }
        }
        if (empty($error)) {
            $stmt = $db->prepare("UPDATE contact_info SET full_name=?, position=?, bio=?, photo=? WHERE id=1");
            $stmt->execute([$full_name, $position, $bio, $photo]);
            $message = 'Контактная информация сохранена';
            $contact = ['full_name' => $full_name, 'position' => $position, 'bio' => $bio, 'photo' => $photo];
            clearCache();
        }
    }
}

// ---- УПРАВЛЕНИЕ СОЦСЕТЯМИ ДЛЯ СТРАНИЦЫ КОНТАКТОВ ----
// Настройки размера и отступа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_contact_social_settings'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) { $error = 'CSRF токен неверен'; }
    else {
        $icon_size = max(16, (int)($_POST['icon_size'] ?? 48));
        $icon_gap = max(0, (int)($_POST['icon_gap'] ?? 25));
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute(['contact_social_icon_size', $icon_size]);
        $stmt->execute(['contact_social_icon_gap', $icon_gap]);
        $message = 'Настройки соцсетей сохранены';
        clearCache();
    }
}

// Добавление новой соцсети
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contact_social'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) { $error = 'CSRF токен неверен'; }
    else {
        $name = trim($_POST['social_name']);
        $icon = trim($_POST['social_icon']);
        $url = trim($_POST['social_url']);
        if ($name && $icon && $url) {
            $stmt = $db->prepare("INSERT INTO contact_social_links (name, icon, url, sort_order) VALUES (?, ?, ?, (SELECT IFNULL(MAX(sort_order),0)+1 FROM (SELECT * FROM contact_social_links) AS tmp))");
            $stmt->execute([$name, $icon, $url]);
            $message = 'Соцсеть добавлена';
            clearCache();
        } else { $error = 'Заполните все поля'; }
    }
}

// Удаление соцсети
if (isset($_GET['delete_contact_social']) && isset($_GET['csrf_token'])) {
    if (!verifyCsrfToken($_GET['csrf_token'])) die('CSRF failed');
    $id = (int)$_GET['delete_contact_social'];
    $db->prepare("DELETE FROM contact_social_links WHERE id = ?")->execute([$id]);
    clearCache();
    header('Location: contact.php?msg=deleted');
    exit;
}

$contactSocialLinks = $db->query("SELECT * FROM contact_social_links ORDER BY sort_order")->fetchAll();
$contactIconSize = (int)getSetting('contact_social_icon_size') ?: 48;
$contactIconGap = (int)getSetting('contact_social_icon_gap') ?: 25;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Редактирование контактов</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .form-group { margin-bottom: 20px; }
        textarea { height: 150px; }
        .preview-photo { max-width: 150px; max-height: 150px; border-radius: 50%; margin-top: 10px; object-fit: cover; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Управление контактами</h1>

    <?php if ($message): ?><div class="success"><?php echo h($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>

    <!-- ========== ВИЗИТНАЯ КАРТОЧКА ========== -->
    <h2>Визитная карточка</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="save_contact" value="1">
        <div class="form-group">
            <label>Ваше имя (ФИО)</label>
            <input type="text" name="full_name" value="<?php echo h($contact['full_name']); ?>" required>
        </div>
        <div class="form-group">
            <label>Должность / Кто вы</label>
            <input type="text" name="position" value="<?php echo h($contact['position']); ?>" placeholder="Например: Автор блога, PHP-разработчик">
        </div>
        <div class="form-group">
            <label>Фотография (круглый аватар)</label>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
            <?php if ($contact['photo']): ?>
                <div><img src="<?php echo SITE_URL . $contact['photo']; ?>" class="preview-photo" alt="Фото"></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>О себе (биография) — визуальный редактор</label>
        <!-- Редактор -->
        <div class="editor-wrapper">
            <div id="bio-editor" class="custom-editor" contenteditable="true"><?php echo htmlspecialchars_decode($contact['bio']); ?></div>
            <textarea name="bio" id="bio-content-hidden" style="display:none;"><?php echo htmlspecialchars($contact['bio']); ?></textarea>
        </div>
    </div>
        </div>
        
        <button type="submit">Сохранить визитку</button>
    </form>

    <hr>

    <!-- ========== СОЦИАЛЬНЫЕ СЕТИ ДЛЯ СТРАНИЦЫ КОНТАКТОВ ========== -->
    <h2>Социальные сети на странице "Контакты"</h2>

    <form method="post" style="margin-bottom: 20px;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="save_contact_social_settings" value="1">
        <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group" style="margin-bottom:0;">
                <label>Размер иконок (px)</label>
                <input type="number" name="icon_size" value="<?php echo $contactIconSize; ?>" min="16" max="64" step="2">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>Расстояние между иконками (px)</label>
                <input type="number" name="icon_gap" value="<?php echo $contactIconGap; ?>" min="0" max="50" step="1">
            </div>
            <button type="submit">Сохранить настройки</button>
        </div>
    </form>

    <h3>Добавить социальную сеть</h3>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="add_contact_social" value="1">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" name="social_name" placeholder="Название (YouTube)" required style="width: 150px;">
            <input type="text" name="social_icon" placeholder="Путь к иконке (/templates/icons/youtube.svg)" required style="width: 250px;">
            <input type="url" name="social_url" placeholder="Ссылка (https://...)" required style="width: 250px;">
            <button type="submit">➕ Добавить</button>
        </div>
    </form>

    <h3>Текущие соцсети на странице контактов</h3>
    <?php if (empty($contactSocialLinks)): ?>
        <p>Соцсети не добавлены.</p>
    <?php else: ?>
        <table class="social-table">
            <thead><tr><th>Название</th><th>Иконка</th><th>Ссылка</th><th>Действия</th></tr></thead>
            <tbody>
                <?php foreach ($contactSocialLinks as $link): ?>
                <tr>
                    <td><?php echo h($link['name']); ?></td>
                    <td class="icon-preview"><img src="<?php echo SITE_URL . $link['icon']; ?>" width="24" height="24"> <?php echo h($link['icon']); ?></td>
                    <td><a href="<?php echo h($link['url']); ?>" target="_blank"><?php echo h($link['url']); ?></a></td>
                    <td><a href="contact.php?delete_contact_social=<?php echo $link['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" class="button btn-small btn-danger" onclick="return confirm('Удалить?')">Удалить</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <script src="<?php echo SITE_URL; ?>/src/4SLASeditor.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        new SimpleEditor('bio-editor', 'bio-content-hidden');
    });
</script>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>