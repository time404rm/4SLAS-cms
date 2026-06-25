<?php
/**
 * Yandex Turbo-страницы
 * XML-фид для мгновенной загрузки в выдаче Яндекса
 */

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$db = getDb();

// Кеш на 1 час
$cacheFile = __DIR__ . '/cache/turbo.xml';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
    readfile($cacheFile);
    exit;
}

ob_start();

$siteName = h(getSetting('site_name'));
$siteDesc = h(getSetting('site_description'));

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss xmlns:yandex="http://news.yandex.ru"' . "\n";
echo '     xmlns:media="http://search.yahoo.com/mrss/"' . "\n";
echo '     version="2.0">' . "\n";
echo '<channel>' . "\n";
echo '<title>' . $siteName . '</title>' . "\n";
echo '<link>' . SITE_URL . '</link>' . "\n";
echo '<description>' . $siteDesc . '</description>' . "\n";
echo '<language>ru</language>' . "\n";

// Посты
$stmt = $db->prepare("SELECT p.id, p.title, p.slug, p.content, p.intro_image, p.created_at, p.updated_at,
                             u.username as author_name
                      FROM posts p
                      LEFT JOIN users u ON p.user_id = u.id
                      WHERE p.status = 'published'
                      ORDER BY p.created_at DESC
                      LIMIT 50");
$stmt->execute();

foreach ($stmt->fetchAll() as $post) {
    $postUrl = SITE_URL . '/post/' . htmlspecialchars($post['slug']);
    $postTitle = htmlspecialchars($post['title']);
    $authorName = $post['author_name'] ? htmlspecialchars($post['author_name']) : $siteName;
    $postDate = date('r', strtotime($post['created_at']));

    echo '<item turbo="true">' . "\n";
    echo '<link>' . $postUrl . '</link>' . "\n";
    echo '<author>' . $authorName . '</author>' . "\n";
    echo '<category>Блог</category>' . "\n";
    echo '<pubDate>' . $postDate . '</pubDate>' . "\n";

    // Формируем Turbo-контент
    $turboContent = '<header><h1>' . $postTitle . '</h1></header>' . "\n";

    // Intro image как figure
    if (!empty($post['intro_image'])) {
        $imgUrl = SITE_URL . '/uploads/posts/' . htmlspecialchars($post['intro_image']);
        $turboContent .= '<figure><img src="' . $imgUrl . '" alt="' . $postTitle . '" /></figure>' . "\n";
    }

    // Обработка контента — очистка, адаптация под Turbo
    $content = $post['content'];

    // Удалить скрипты, стили, iframe, form
    $content = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $content);
    $content = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $content);
    $content = preg_replace('/<iframe[^>]*>.*?<\/iframe>/si', '', $content);
    $content = preg_replace('/<form[^>]*>.*?<\/form>/si', '', $content);
    $content = preg_replace('/<input[^>]*>/si', '', $content);
    $content = preg_replace('/<button[^>]*>.*?<\/button>/si', '', $content);

    // Обернуть все img в figure (если ещё не обёрнуты)
    $content = preg_replace('/<img\s+([^>]*)>/si', '<figure><img $1></figure>', $content);

    // Удалить пустые figure
    $content = preg_replace('/<figure>\s*<\/figure>/si', '', $content);

    // Удалить атрибуты style (Turbo не любит inline-стили)
    $content = preg_replace('/\s+style=("[^"]*"|\'[^\']*\')/si', '', $content);

    // Удалить классы
    $content = preg_replace('/\s+class=("[^"]*"|\'[^\']*\')/si', '', $content);

    // Удалить data-атрибуты
    $content = preg_replace('/\s+data-[^=]+=("[^"]*"|\'[^\']*\')/si', '', $content);

    // Заменить относительные ссылки на абсолютные
    $content = preg_replace('/src="\/([^"]+)"/si', 'src="' . SITE_URL . '/$1"', $content);
    $content = preg_replace('/href="\/([^"]+)"/si', 'href="' . SITE_URL . '/$1"', $content);

    $turboContent .= $content;

    echo '<turbo:content><![CDATA[' . "\n";
    echo $turboContent;
    echo ']]></turbo:content>' . "\n";
    echo '</item>' . "\n";
}

echo '</channel>' . "\n";
echo '</rss>' . "\n";

$xml = ob_get_clean();
@file_put_contents($cacheFile, $xml);
echo $xml;
