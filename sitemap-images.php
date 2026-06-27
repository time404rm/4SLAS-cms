<?php
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$db = getDb();

$cacheFile = __DIR__ . '/cache/sitemap-images.xml';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
    readfile($cacheFile);
    exit;
}

function absUrl($src) {
    if (strpos($src, '://') !== false) return $src;
    return SITE_URL . '/' . ltrim($src, '/');
}

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

// Собираем все картинки сгруппированными по страницам
$pageImages = [];

// 1. intro_image из постов
$stmt = $db->prepare("SELECT slug, title, intro_image FROM posts WHERE status = 'published' AND intro_image IS NOT NULL AND intro_image != ''");
$stmt->execute();
foreach ($stmt->fetchAll() as $post) {
    $key = 'post/' . $post['slug'];
    $pageImages[$key] = $pageImages[$key] ?? ['url' => SITE_URL . '/post/' . htmlspecialchars($post['slug']), 'images' => []];
    $pageImages[$key]['images'][] = [
        'loc' => absUrl('/uploads/posts/' . $post['intro_image']),
        'title' => $post['title']
    ];
}

// 2. Галерея
$stmt = $db->prepare("SELECT p.slug, p.title, pg.image 
                       FROM post_gallery pg 
                       JOIN posts p ON pg.post_id = p.id 
                       WHERE p.status = 'published' 
                       ORDER BY pg.post_id, pg.sort_order");
$stmt->execute();
foreach ($stmt->fetchAll() as $g) {
    $key = 'post/' . $g['slug'];
    $pageImages[$key] = $pageImages[$key] ?? ['url' => SITE_URL . '/post/' . htmlspecialchars($g['slug']), 'images' => []];
    $pageImages[$key]['images'][] = [
        'loc' => absUrl('/uploads/gallery/' . $g['image']),
        'title' => $g['title']
    ];
}

// 3. Изображения из контента постов
$stmt = $db->prepare("SELECT slug, title, content FROM posts WHERE status = 'published' AND content LIKE '%<img%'");
$stmt->execute();
foreach ($stmt->fetchAll() as $post) {
    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $post['content'], $matches);
    $seen = [];
    foreach ($matches[1] as $src) {
        $src = trim($src);
        if (empty($src) || isset($seen[$src])) continue;
        $seen[$src] = true;
        $key = 'post/' . $post['slug'];
        $pageImages[$key] = $pageImages[$key] ?? ['url' => SITE_URL . '/post/' . htmlspecialchars($post['slug']), 'images' => []];
        $pageImages[$key]['images'][] = [
            'loc' => absUrl($src),
            'title' => $post['title']
        ];
    }
}

// 4. Картинки из статических страниц
$stmt = $db->prepare("SELECT slug, title, content FROM pages WHERE status = 'published' AND content LIKE '%<img%'");
$stmt->execute();
foreach ($stmt->fetchAll() as $page) {
    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $page['content'], $matches);
    $seen = [];
    foreach ($matches[1] as $src) {
        $src = trim($src);
        if (empty($src) || isset($seen[$src])) continue;
        $seen[$src] = true;
        $key = 'page/' . $page['slug'];
        $pageImages[$key] = $pageImages[$key] ?? ['url' => SITE_URL . '/page/' . htmlspecialchars($page['slug']), 'images' => []];
        $pageImages[$key]['images'][] = [
            'loc' => absUrl($src),
            'title' => $page['title']
        ];
    }
}

// Вывод: группируем все картинки одной страницы под одним <url>
foreach ($pageImages as $entry) {
    echo '<url><loc>' . $entry['url'] . '</loc>' . "\n";
    foreach ($entry['images'] as $img) {
        echo '<image:image>' . "\n";
        echo '<image:loc>' . htmlspecialchars($img['loc']) . '</image:loc>' . "\n";
        if ($img['title']) echo '<image:title>' . htmlspecialchars(mb_substr($img['title'], 0, 200)) . '</image:title>' . "\n";
        echo '</image:image>' . "\n";
    }
    echo '</url>' . "\n";
}

echo '</urlset>';
$xml = ob_get_clean();
@file_put_contents($cacheFile, $xml);
echo $xml;
