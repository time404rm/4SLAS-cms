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
$pageDescription = $page['meta_description'] ?: mb_substr(strip_tags($page['content']), 0, 160);
$pageKeywords = $page['meta_keywords'] ?? '';
$canonicalUrl = SITE_URL . '/page/' . $page['slug'];

include __DIR__ . '/templates/header.php';
?>
<article class="page">
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
include __DIR__ . '/templates/footer.php';
?>