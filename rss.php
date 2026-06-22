<?php
/**
 * RSS-лента блога (последние 20 опубликованных постов)
 */

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/rss+xml; charset=utf-8');

$siteName = getSetting('site_name');
$siteDescription = getSetting('site_description');
$siteUrl = SITE_URL;

$db = getDb();
$stmt = $db->prepare("SELECT id, title, slug, content, created_at, updated_at FROM posts WHERE status = 'published' ORDER BY created_at DESC LIMIT 20");
$stmt->execute();
$posts = $stmt->fetchAll();

// Очищаем буфер вывода, чтобы не было лишних пробелов
ob_clean();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title><?php echo htmlspecialchars($siteName, ENT_XML1, 'UTF-8'); ?></title>
    <link><?php echo htmlspecialchars($siteUrl, ENT_XML1, 'UTF-8'); ?></link>
    <description><?php echo htmlspecialchars($siteDescription, ENT_XML1, 'UTF-8'); ?></description>
    <language>ru</language>
    <lastBuildDate><?php echo date(DATE_RFC2822); ?></lastBuildDate>
    <atom:link href="<?php echo htmlspecialchars($siteUrl . '/rss.php', ENT_XML1, 'UTF-8'); ?>" rel="self" type="application/rss+xml" />
    
    <?php foreach ($posts as $post): ?>
    <item>
        <title><?php echo htmlspecialchars($post['title'], ENT_XML1, 'UTF-8'); ?></title>
        <link><?php echo htmlspecialchars($siteUrl . '/post/' . $post['slug'], ENT_XML1, 'UTF-8'); ?></link>
        <guid isPermaLink="true"><?php echo htmlspecialchars($siteUrl . '/post/' . $post['slug'], ENT_XML1, 'UTF-8'); ?></guid>
        <description><![CDATA[<?php echo mb_substr(strip_tags($post['content']), 0, 500); ?>]]></description>
        <pubDate><?php echo date(DATE_RFC2822, strtotime($post['created_at'])); ?></pubDate>
    </item>
    <?php endforeach; ?>
</channel>
</rss>
<?php
exit;