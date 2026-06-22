<?php
require_once '../includes/functions.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

$db = getDb();
$message = '';
$error = '';
$csrf_token = generateCsrfToken();

// Добавление нового URL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_url'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF failed');
    $url = trim($_POST['url']);
    $priority = (float)($_POST['priority'] ?? 0.5);
    $changefreq = $_POST['changefreq'] ?? 'monthly';
    if (filter_var($url, FILTER_VALIDATE_URL) && $priority >= 0 && $priority <= 1) {
        $stmt = $db->prepare("INSERT INTO sitemap_urls (url, priority, changefreq) VALUES (?, ?, ?)");
        $stmt->execute([$url, $priority, $changefreq]);
        $message = 'URL добавлен';
        // Перегенерируем sitemap.xml принудительно
        @unlink(__DIR__ . '/../cache/sitemap.xml');
    } else {
        $error = 'Некорректный URL или приоритет (0.0-1.0)';
    }
}

// Удаление URL
if (isset($_GET['delete'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) die('CSRF failed');
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM sitemap_urls WHERE id = ?")->execute([$id]);
    @unlink(__DIR__ . '/../cache/sitemap.xml');
    header('Location: sitemap.php');
    exit;
}

// Перегенерация вручную
if (isset($_GET['regenerate'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) die('CSRF failed');
    @unlink(__DIR__ . '/../cache/sitemap.xml');
    $message = 'Sitemap.xml перегенерирован';
}

// Получение списка всех URL (из разных источников) – только для отображения
$allUrls = [];
// Посты
$stmt = $db->query("SELECT CONCAT('post/', slug) as url, 'post' as source, updated_at FROM posts WHERE status = 'published'");
$allUrls = array_merge($allUrls, $stmt->fetchAll());
// Категории
$stmt = $db->query("SELECT CONCAT('category/', slug) as url, 'category' as source, NULL as updated_at FROM categories");
$allUrls = array_merge($allUrls, $stmt->fetchAll());
// Теги
$stmt = $db->query("SELECT CONCAT('search.php?q=', name) as url, 'tag' as source, NULL as updated_at FROM hashtags");
$allUrls = array_merge($allUrls, $stmt->fetchAll());
// Страницы (если есть)
if (function_exists('getAllPages')) {
    $pages = getAllPages();
    foreach ($pages as $page) {
        if ($page['status'] == 'published') {
            $allUrls[] = ['url' => 'page/' . $page['slug'], 'source' => 'page', 'updated_at' => $page['updated_at']];
        }
    }
}
// Пользовательские
$customUrls = $db->query("SELECT id, url, priority, changefreq FROM sitemap_urls WHERE status = 1")->fetchAll();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Управление Sitemap</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Управление Sitemap</h1>
    <?php if ($message): ?><p class="success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

    <p><a href="?regenerate=1&csrf_token=<?php echo $csrf_token; ?>" class="button">Перегенерировать sitemap.xml</a></p>

    <h2>Добавить свой URL</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <label>URL (полный, включая http://): <input type="text" name="url" size="80" required></label><br>
        <label>Приоритет (0.0–1.0): <input type="text" name="priority" value="0.5" size="5"></label><br>
        <label>Частота: 
            <select name="changefreq">
                <option value="always">always</option><option value="hourly">hourly</option>
                <option value="daily">daily</option><option value="weekly">weekly</option>
                <option value="monthly" selected>monthly</option>
                <option value="yearly">yearly</option><option value="never">never</option>
            </select>
        </label><br>
        <button type="submit" name="add_url">Добавить</button>
    </form>

    <h2>Автоматически добавленные URL</h2>
    <table border="1">
        <tr><th>URL</th><th>Источник</th><th>Дата обновления</th><th>Действие</th></tr>
        <?php foreach ($allUrls as $item): ?>
        <tr>
            <td><?php echo htmlspecialchars(SITE_URL . '/' . $item['url']); ?></td>
            <td><?php echo $item['source']; ?></td>
            <td><?php echo isset($item['updated_at']) ? date('Y-m-d', strtotime($item['updated_at'])) : '-'; ?></td>
            <td><a href="?delete=<?php echo $item['url']; ?>&csrf_token=<?php echo $csrf_token; ?>" onclick="return confirm('Удалить? (только пользовательские записи)')">Удалить</a></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Пользовательские URL</h2>
    <table border="1">
        <tr><th>ID</th><th>URL</th><th>Приоритет</th><th>Частота</th><th>Действие</th></tr>
        <?php foreach ($customUrls as $cu): ?>
        <tr>
            <td><?php echo $cu['id']; ?></td>
            <td><?php echo htmlspecialchars($cu['url']); ?></td>
            <td><?php echo $cu['priority']; ?></td>
            <td><?php echo $cu['changefreq']; ?></td>
            <td><a href="?delete=<?php echo $cu['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" onclick="return confirm('Удалить?')">Удалить</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>