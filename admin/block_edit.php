<?php
require_once '../includes/functions.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

$db = getDb();
$id = (int)($_GET['id'] ?? 0);
$block = null;
if ($id) {
    $stmt = $db->prepare("SELECT * FROM custom_blocks WHERE id = ?");
    $stmt->execute([$id]);
    $block = $stmt->fetch();
    if (!$block) die('Блок не найден');
}

// Жёстко заданные позиции
$positions = ['leftmenu', 'footer', 'after_first_post', 'mid_content'];
$csrf_token = generateCsrfToken();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_block'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF failed');
    $name = trim($_POST['name']);
    $content = $_POST['content'];
    $position = $_POST['position'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name)) {
        $error = 'Название блока обязательно';
    } elseif ($id) {
        $stmt = $db->prepare("UPDATE custom_blocks SET name=?, content=?, position=?, is_active=? WHERE id=?");
        $stmt->execute([$name, $content, $position, $is_active, $id]);
        clearCache();
        header('Location: blocks.php?msg=updated');
        exit;
    } else {
        $stmt = $db->prepare("INSERT INTO custom_blocks (name, content, position, is_active) VALUES (?,?,?,?)");
        $stmt->execute([$name, $content, $position, $is_active]);
        clearCache();
        header('Location: blocks.php?msg=created');
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Редактировать блок</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #b9c7e6; }
        .form-group input, .form-group select { width: 100%; padding: 8px; background: #0f1422; border: 1px solid #2a3650; color: #e2e8f0; border-radius: 4px; }
        .form-group textarea { width: 100%; min-height: 400px; font-family: monospace; background: #0f1422; border: 1px solid #2a3650; color: #e2e8f0; border-radius: 4px; padding: 8px; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1><?php echo $block ? 'Редактировать блок: ' . htmlspecialchars($block['name']) : 'Создать новый блок'; ?></h1>
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="update_block" value="1">
        <div class="form-group">
            <label>Название</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($block['name'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label>Позиция</label>
            <select name="position">
                <?php foreach ($positions as $p): ?>
                <option value="<?php echo htmlspecialchars($p); ?>" <?php echo ($block['position'] ?? 'after_first_post') === $p ? 'selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Код блока (HTML, скрипты)</label>
            <textarea name="content"><?php echo htmlspecialchars($block['content'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_active" value="1" <?php echo ($block['is_active'] ?? 1) ? 'checked' : ''; ?>> Активен</label>
        </div>
        <button type="submit" style="background:#2563eb; color:white; border:none; padding:8px 20px; border-radius:4px; cursor:pointer;"><?php echo $block ? 'Сохранить' : 'Создать'; ?></button>
        <a href="blocks.php" style="color:#60a5fa; margin-left:10px;">Отмена</a>
    </form>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>
