<?php
require_once '../includes/functions.php';
$csrf_token = generateCsrfToken();
require_once '../includes/pages.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

if (isset($_GET['delete'])) {
    deletePage((int)$_GET['delete']);
    header('Location: pages.php');
    exit;
}

$pages = getAllPages();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Управление страницами</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Статические страницы</h1>
    <a href="page_edit.php">+ Добавить страницу</a>
    <table border="1">
        <tr><th>ID</th><th>Заголовок</th><th>Slug</th><th>Статус</th><th>Действия</th></tr>
        <?php foreach ($pages as $p): ?>
        <tr>
            <td><?php echo $p['id']; ?></td>
            <td><?php echo h($p['title']); ?></td>
            <td><?php echo h($p['slug']); ?></td>
            <td><?php echo $p['status']; ?></td>
            <td>
                <a href="page_edit.php?id=<?php echo $p['id']; ?>">Редактировать</a> |
                <a href="?delete=<?php echo $p['id']; ?>" onclick="return confirm('Удалить страницу?')">Удалить</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>