<?php
require_once 'includes/functions.php';
require_once 'includes/menu.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}
$db = getDb();
$stmt = $db->prepare("SELECT * FROM menu_items WHERE slug = ? AND status = 1");
$stmt->execute([$slug]);
$menuItem = $stmt->fetch();

if (!$menuItem) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// Глобальные настройки SEO для страницы
$pageTitle = $menuItem['meta_title'] ?: $menuItem['title'];
$pageDescription = $menuItem['meta_description'] ?: '';
$hideTitle = $menuItem['hide_page_title'];
$canonicalUrl = SITE_URL . '/menu/' . $slug;
include __DIR__ . '/templates/header.php';

include 'templates/header.php';
?>

<?php if (!$hideTitle): ?>
    <h1><?php echo h($menuItem['title']); ?></h1>
<?php endif; ?>
<div class="menu-page-content">
    <?php
    // Здесь можно вывести произвольный контент, если нужно.
    // Либо использовать $menuItem['url'] как редирект.
    if (filter_var($menuItem['url'], FILTER_VALIDATE_URL)) {
        header('Location: ' . $menuItem['url']);
        exit;
    } else {
        echo '<p>Содержимое этой страницы не задано. Вы можете изменить его в админ-панели.</p>';
    }
    ?>
</div>

<?php
include 'templates/footer.php';
?>