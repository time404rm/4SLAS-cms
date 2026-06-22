<?php
require_once '../includes/functions.php';
require_once '../includes/pages.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

$db = getDb();
$id = (int)($_GET['id'] ?? 0);
$page = null;
if ($id) {
    $stmt = $db->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$id]);
    $page = $stmt->fetch();
}

$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $title = trim($_POST['title']);
    $slug = !empty($_POST['slug']) ? slugify($_POST['slug']) : slugify($title);
    $content = $_POST['content'];
    $status = $_POST['status'];
    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDesc = trim($_POST['meta_description'] ?? '');
    $metaKeywords = trim($_POST['meta_keywords'] ?? '');

    $stmt = $db->prepare("SELECT id FROM pages WHERE slug = ? AND id != ?");
    $stmt->execute([$slug, $id]);
    if ($stmt->fetch()) {
        $slug = $slug . '-' . time();
    }

    if ($id) {
        updatePage($id, $title, $slug, $content, $status, $metaTitle, $metaDesc, $metaKeywords);
    } else {
        createPage($title, $slug, $content, $status, $metaTitle, $metaDesc, $metaKeywords);
    }
    header('Location: pages.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $id ? 'Редактировать страницу' : 'Новая страница'; ?></title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .form-row { margin-bottom: 15px; }
        .form-row label { display: inline-block; width: 150px; vertical-align: top; font-weight: bold; }
        .form-row input[type="text"], .form-row textarea, .form-row select { width: 70%; padding: 8px; }
        .seo-group { margin-top: 20px; border-top: 1px solid #2a3650; padding-top: 15px; }
        button { background: #2563eb; color: white; border: none; padding: 8px 20px; cursor: pointer; border-radius: 4px; }
        button:hover { background: #1e40af; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1><?php echo $id ? 'Редактировать страницу' : 'Новая страница'; ?></h1>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="form-row">
            <label>Заголовок:</label>
            <input type="text" name="title" value="<?php echo $page ? htmlspecialchars($page['title']) : ''; ?>" required>
        </div>
        <div class="form-row">
            <label>Slug (оставьте пустым для автогенерации):</label>
            <input type="text" name="slug" value="<?php echo $page ? htmlspecialchars($page['slug']) : ''; ?>">
        </div>

        <div class="form-row">
            <label>Содержание:</label>
            <div id="post-editor" class="custom-editor" contenteditable="true"><?php echo $page ? $page['content'] : ''; ?></div>
            <textarea name="content" id="post-content-hidden" style="display:none;"><?php echo $page ? htmlspecialchars($page['content']) : ''; ?></textarea>
        </div>

        <div class="form-row">
            <label>Статус:</label>
            <select name="status">
                <option value="published" <?php echo ($page && $page['status'] === 'published') ? 'selected' : ''; ?>>Опубликовано</option>
                <option value="draft" <?php echo ($page && $page['status'] === 'draft') ? 'selected' : ''; ?>>Черновик</option>
            </select>
        </div>

        <div class="seo-group">
            <h3>SEO-настройки (опционально)</h3>
            <div class="form-row">
                <label>Meta Title:</label>
                <input type="text" name="meta_title" value="<?php echo $page ? htmlspecialchars($page['meta_title'] ?? '') : ''; ?>" size="80">
            </div>
            <div class="form-row">
                <label>Meta Description:</label>
                <textarea name="meta_description" rows="3" cols="80"><?php echo $page ? htmlspecialchars($page['meta_description'] ?? '') : ''; ?></textarea>
            </div>
            <div class="form-row">
                <label>Meta Keywords:</label>
                <input type="text" name="meta_keywords" value="<?php echo $page ? htmlspecialchars($page['meta_keywords'] ?? '') : ''; ?>" size="80">
            </div>
        </div>

        <div class="form-row">
            <button type="submit">Сохранить</button>
            <a href="pages.php">Отмена</a>
        </div>
    </form>

    <script src="<?php echo SITE_URL; ?>/src/4SLASeditor.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new SimpleEditor('post-editor', 'post-content-hidden');
    });
    </script>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>
