<?php
/**
 * RSS-лента блога (последние 20 опубликованных постов)
 * Совместимость с Дзен: content:encoded, enclosure, author, pdalink
 */

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/rss+xml; charset=utf-8');

$siteName = getSetting('site_name');
$siteDescription = getSetting('site_description');
$siteUrl = SITE_URL;

$db = getDb();
$stmt = $db->prepare("SELECT p.id, p.title, p.slug, p.content, p.intro_image, p.created_at, p.updated_at, p.dzen_exclude, p.display_author, u.username as author_name
                       FROM posts p
                       LEFT JOIN users u ON p.user_id = u.id
                       WHERE p.status = 'published' AND (p.dzen_exclude IS NULL OR p.dzen_exclude = 0)
                       ORDER BY p.created_at DESC LIMIT 20");
$stmt->execute();
$posts = $stmt->fetchAll();

ob_clean();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:media="http://search.yahoo.com/mrss/"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:georss="http://www.georss.org/georss">
<channel>
    <title><?php echo htmlspecialchars($siteName, ENT_XML1, 'UTF-8'); ?></title>
    <link><?php echo htmlspecialchars($siteUrl, ENT_XML1, 'UTF-8'); ?></link>
    <description><?php echo htmlspecialchars($siteDescription, ENT_XML1, 'UTF-8'); ?></description>
    <language>ru</language>
    <lastBuildDate><?php echo date(DATE_RFC2822); ?></lastBuildDate>
    <atom:link href="<?php echo htmlspecialchars($siteUrl . '/rss.php', ENT_XML1, 'UTF-8'); ?>" rel="self" type="application/rss+xml" />
    
    <?php foreach ($posts as $post): ?>
    <?php $author = !empty($post['display_author']) ? $post['display_author'] : ($post['author_name'] ?? ''); ?>
    <item>
        <title><?php echo htmlspecialchars($post['title'], ENT_XML1, 'UTF-8'); ?></title>
        <link><?php echo htmlspecialchars($siteUrl . '/post/' . $post['slug'], ENT_XML1, 'UTF-8'); ?></link>
        <guid isPermaLink="true"><?php echo htmlspecialchars($siteUrl . '/post/' . $post['slug'], ENT_XML1, 'UTF-8'); ?></guid>
        <pdalink><?php echo htmlspecialchars($siteUrl . '/post/' . $post['slug'], ENT_XML1, 'UTF-8'); ?></pdalink>
        <description><![CDATA[<?php echo mb_substr(strip_tags($post['content']), 0, 500); ?>]]></description>
        <?php if ($post['intro_image']): ?>
        <?php $imgPath = __DIR__ . '/uploads/posts/' . $post['intro_image']; ?>
        <?php $imgExt = strtolower(pathinfo($post['intro_image'], PATHINFO_EXTENSION)); ?>
        <?php $imgType = $imgExt === 'png' ? 'image/png' : ($imgExt === 'webp' ? 'image/webp' : 'image/jpeg'); ?>
        <?php if (file_exists($imgPath)): ?>
        <enclosure url="<?php echo htmlspecialchars($siteUrl . '/uploads/posts/' . $post['intro_image'], ENT_XML1, 'UTF-8'); ?>" type="<?php echo $imgType; ?>" />
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($author): ?>
        <author><?php echo htmlspecialchars($author, ENT_XML1, 'UTF-8'); ?></author>
        <?php endif; ?>
        <content:encoded><![CDATA[
<?php echo $post['content']; ?>
<p style="margin-top:30px;padding-top:20px;border-top:1px solid #ddd;font-size:13px;color:#666;">
Первоисточник: <a href="<?php echo htmlspecialchars($siteUrl . '/post/' . $post['slug'], ENT_XML1, 'UTF-8'); ?>"><?php echo htmlspecialchars($post['title'], ENT_XML1, 'UTF-8'); ?></a> на <?php echo htmlspecialchars($siteName, ENT_XML1, 'UTF-8'); ?>
</p>
        ]]></content:encoded>
        <pubDate><?php echo date(DATE_RFC2822, strtotime($post['created_at'])); ?></pubDate>
    </item>
    <?php endforeach; ?>
</channel>
</rss>
<?php
exit;