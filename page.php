<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pages.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/faq-helper.php';
require_once __DIR__ . '/includes/toc-helper.php';
require_once __DIR__ . '/includes/howto-helper.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$slug = $_GET['slug'] ?? '';
$page = getPageBySlug($slug);
if (!$page) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$pageTitle = $page['meta_title'] ?: $page['title'];
$pageDescription = $page['meta_description'] ?: truncateText($page['content'], 160);
$pageKeywords = $page['meta_keywords'] ?? '';
$canonicalUrl = !empty($page['canonical_url']) ? $page['canonical_url'] : (SITE_URL . '/page/' . $page['slug']);

// Не индексировать служебные страницы
if ($page['slug'] === 'privacy') $noindex = true;

$isEditing = isAdmin() && isset($_GET['edit']);
$feData = isAdmin() ? [
    'pageId' => $page['id'],
    'pageType' => 'page',
    'metaTitle' => $page['meta_title'] ?? '',
    'metaDesc' => $page['meta_description'] ?? '',
    'metaKw' => $page['meta_keywords'] ?? '',
    'displayAuthor' => $page['display_author'] ?? '',
    'canonicalUrl' => $page['canonical_url'] ?? '',
] : null;

$showHighlight = (mb_strpos($page['content'], '<pre') !== false || mb_strpos($page['content'], '<code') !== false);

include __DIR__ . '/templates/header.php';
?>

<?php if ($feData): ?>
<script>
window.frontEditorData = <?php echo json_encode($feData); ?>;
window.currentPageId = <?php echo (int)$page['id']; ?>;
</script>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/src/front-editor.css">
<?php endif; ?>

<?php if ($isEditing): ?>
<div id="fe-content" style="display:none;"><?php echo $page['content']; ?></div>
<h1 id="fe-title" style="display:none;"><?php echo h($page['title']); ?></h1>
<?php else: ?>

<article class="artback">
    <h1><?php echo h($page['title']); ?></h1>
    <div class="page-content">
        <?php
        // Применяем Lightbox для изображений (без исключения intro, т.к. у страниц его нет)
        $content = wrapImagesWithLightbox($page['content']);
        // Если на страницах нужны активные хештеги – раскомментируйте следующую строку
        $content = activateHashtags($content);
        $content = maskEmails($content);
        $content = str_replace('[yoomoney]', renderYoomoneyButton(), $content);
        $content = faqParse($content);
        $content = howtoParse($content);
        $content = tocGenerate($content);
        echo $content;
        ?>
    </div>
</article>
<?php
$pageBlock = null;
try {
    $stmt = getDb()->prepare("SELECT content FROM custom_blocks WHERE position = 'after_page_content' AND is_active = 1 LIMIT 1");
    $stmt->execute();
    $pageBlock = $stmt->fetchColumn();
} catch (\PDOException $e) {}
if ($pageBlock): ?>
    <div class="custom-block-wrapper"><?php echo $pageBlock; ?></div>
<?php endif; ?>
<?php endif; // end if $isEditing ?>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": <?php echo json_encode($pageTitle); ?>,
  "description": <?php echo json_encode($pageDescription); ?>,
  "url": <?php echo json_encode($canonicalUrl); ?>
}
</script>

<?php if ($feData): ?>
<script src="<?php echo SITE_URL; ?>/src/front-editor.js"></script>
<?php endif; ?>

<?php
include __DIR__ . '/templates/footer.php';
?>