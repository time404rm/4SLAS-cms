<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pages.php';

header('Content-Type: application/xml; charset=utf-8');

$db = getDb();

// Кеширование на 1 час (опционально)
$cacheFile = __DIR__ . '/cache/sitemap.xml';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
    readfile($cacheFile);
    exit;
}

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// 1. Главная
echo '<url><loc>' . SITE_URL . '/</loc><lastmod>' . date('Y-m-d') . '</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url>' . "\n";

// 2. Посты
$stmt = $db->prepare("SELECT slug, updated_at FROM posts WHERE status = 'published'");
$stmt->execute();
foreach ($stmt->fetchAll() as $post) {
    echo '<url><loc>' . SITE_URL . '/post/' . htmlspecialchars($post['slug']) . '</loc>';
    echo '<lastmod>' . date('Y-m-d', strtotime($post['updated_at'])) . '</lastmod>';
    echo '<changefreq>monthly</changefreq><priority>0.8</priority></url>' . "\n";
}

// 3. Категории
$stmt = $db->query("SELECT slug FROM categories");
foreach ($stmt->fetchAll() as $cat) {
    echo '<url><loc>' . SITE_URL . '/category/' . htmlspecialchars($cat['slug']) . '</loc>';
    echo '<changefreq>weekly</changefreq><priority>0.6</priority></url>' . "\n";
}

// 4. Теги (через поиск)
$stmt = $db->query("SELECT name FROM hashtags");
foreach ($stmt->fetchAll() as $tag) {
    echo '<url><loc>' . SITE_URL . '/search.php?q=' . urlencode($tag['name']) . '</loc>';
    echo '<changefreq>monthly</changefreq><priority>0.5</priority></url>' . "\n";
}

// 5. Статические страницы
if (function_exists('getAllPages')) {
    $pages = getAllPages();
    foreach ($pages as $page) {
        if ($page['status'] == 'published') {
            echo '<url><loc>' . SITE_URL . '/page/' . htmlspecialchars($page['slug']) . '</loc>';
            echo '<lastmod>' . date('Y-m-d', strtotime($page['updated_at'] ?? $page['created_at'])) . '</lastmod>';
            echo '<changefreq>monthly</changefreq><priority>0.7</priority></url>' . "\n";
        }
    }
}

// 6. Пользовательские URL из таблицы sitemap_urls
$stmt = $db->prepare("SELECT url, priority, changefreq FROM sitemap_urls WHERE status = 1");
$stmt->execute();
foreach ($stmt->fetchAll() as $custom) {
    echo '<url><loc>' . htmlspecialchars($custom['url']) . '</loc>';
    echo '<changefreq>' . $custom['changefreq'] . '</changefreq>';
    echo '<priority>' . $custom['priority'] . '</priority></url>' . "\n";
}

echo '</urlset>';
$xml = ob_get_clean();
@file_put_contents($cacheFile, $xml);
echo $xml;
?>