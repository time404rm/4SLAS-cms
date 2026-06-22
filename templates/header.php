<?php
// 4SLAS-cms
// Автор: ruslanabuzyaroff
// Telegram: https://t.me/time4_04
// Сайт: time404.ru
// E-mail: ruslan@time404.ru
// Лицензия: MIT
// Если этот CMS понравился, можете оставить монетку автору на чашечку кофе или дать ссылку на проект.
// Если планируете модифицировать и улучшать, буду рад если поделитесь доработками
?>
<?php
// Проверка режима реконструкции
if (maintenanceModeActive()) {
    renderMaintenancePage();
}
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/seo.php';
require_once __DIR__ . '/../includes/yandex_auth.php';
require_once __DIR__ . '/../includes/vk_auth.php';

$siteName = h(getSetting('site_name'));
$currentLang = $_SESSION['lang'] ?? 'ru';
// Формат заголовка страницы (из настроек)
$titleFormat = getSetting('title_format') ?: 'site_page';
$pageTitleRaw = isset($pageTitle) ? $pageTitle : $siteName;

if ($titleFormat === 'site_page') {
    $metaTitle = $siteName . ' - ' . $pageTitleRaw;
} elseif ($titleFormat === 'page_site') {
    $metaTitle = $pageTitleRaw . ' - ' . $siteName;
} else { // 'page_only'
    $metaTitle = $pageTitleRaw;
}
$metaTitle = h($metaTitle); // экранируем финальный результат
$metaDesc = isset($pageDescription) ? h($pageDescription) : h(getSetting('site_description'));
$metaKeywords = isset($pageKeywords) ? h($pageKeywords) : '';

// OG image — если нет картинки, генерируем fallback
if (isset($ogImage) && $ogImage) {
    $ogImg = SITE_URL . '/uploads/posts/' . h($ogImage);
} elseif (isset($post) && !empty($post['intro_image'])) {
    $ogImg = SITE_URL . '/uploads/posts/' . h($post['intro_image']);
} else {
    $ogImg = SITE_URL . '/default-og.php';
}
$canonical = isset($canonicalUrl) ? $canonicalUrl : (SITE_URL . $_SERVER['REQUEST_URI']);

// Верификация поисковиков
$yandexVerification = getSetting('yandex_verification');
$googleVerification = getSetting('google_verification');

// Цвет темы для мобильных браузеров
$themeColor = getSetting('theme_color') ?: '#1a1a2e';
$ymCounterId = getSetting('yandex_metrica_id');

// Данные для выдвижной панели
$menuItems = getMenuItems(0);
$allCategories = getAllCategories();
$allTags = getAllTags();
$topPosts = getTopPostsByLikes(5);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $metaTitle; ?></title>
    <meta name="description" content="<?php echo $metaDesc; ?>">
    <?php if ($metaKeywords): ?>
    <meta name="keywords" content="<?php echo $metaKeywords; ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?php echo h($canonical); ?>">
    <!-- hreflang для мультиязычности -->
    <link rel="alternate" hreflang="ru" href="<?php echo h($canonical); ?><?php echo strpos($canonical, '?') !== false ? '&' : '?'; ?>lang=ru">
    <link rel="alternate" hreflang="en" href="<?php echo h($canonical); ?><?php echo strpos($canonical, '?') !== false ? '&' : '?'; ?>lang=en">
    <link rel="alternate" hreflang="x-default" href="<?php echo h($canonical); ?>">
    <?php if (isset($page) && is_numeric($page) && $page > 1): ?>
    <meta name="robots" content="noindex, follow">
    <link rel="prev" href="<?php echo SITE_URL . ($page > 2 ? '/?page=' . ($page - 1) : '/'); ?>">
    <?php endif; ?>
    <?php if (isset($totalPages) && isset($page) && is_numeric($page) && $page < $totalPages): ?>
    <link rel="next" href="<?php echo SITE_URL . '/?page=' . ($page + 1); ?>">
    <?php endif; ?>
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo $metaTitle; ?>">
    <meta property="og:description" content="<?php echo $metaDesc; ?>">
    <meta property="og:image" content="<?php echo $ogImg; ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="<?php echo $currentLang === 'ru' ? 'ru_RU' : 'en_US'; ?>">
    <meta property="og:site_name" content="<?php echo $siteName; ?>">
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $metaTitle; ?>">
    <meta name="twitter:description" content="<?php echo $metaDesc; ?>">
    <meta name="twitter:image" content="<?php echo $ogImg; ?>">
    <!-- Theme color -->
    <meta name="theme-color" content="<?php echo h($themeColor); ?>">
    <!-- Верификация поисковиков -->
    <?php if ($yandexVerification): ?>
    <meta name="yandex-verification" content="<?php echo h($yandexVerification); ?>">
    <?php endif; ?>
    <?php if ($googleVerification): ?>
    <meta name="google-site-verification" content="<?php echo h($googleVerification); ?>">
    <?php endif; ?>

    <!-- CSS (минифицированный или обычный) -->
    <?php if (function_exists('isCssMinifyEnabled') && isCssMinifyEnabled()): ?>
    <style><?php echo getMinifiedCss(__DIR__ . '/../css/style.css'); ?></style>
    <?php else: ?>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
    <?php endif; ?>

    <!-- Highlight.js (локально) -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/highlight/styles/vs.min.css">
    <!-- Lightbox -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/lightbox/css/lightbox.min.css">
    <?php
$favicon = getSetting('favicon');
if ($favicon && file_exists($_SERVER['DOCUMENT_ROOT'] . $favicon)): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL . $favicon; ?>">
    <link rel="shortcut icon" href="<?php echo SITE_URL . $favicon; ?>">
<?php else: ?>
    <link rel="icon" href="<?php echo SITE_URL; ?>/favicon.ico" type="image/x-icon">
<?php endif; ?>
<?php if (isset($show_breadcrumbs) && $show_breadcrumbs && count($breadcrumb_items) > 1): ?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [<?php foreach ($breadcrumb_items as $i => $item): ?>{"@type":"ListItem","position":<?php echo $i+1; ?>,"name":"<?php echo h($item['name']); ?>","item":"<?php echo !empty($item['url']) ? h($item['url']) : h($canonical); ?>"}<?php if ($i < count($breadcrumb_items)-1): ?>,<?php endif; ?><?php endforeach; ?>]
}
</script>
<?php endif; ?>
<!-- JSON-LD WebSite + SearchAction -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": <?php echo json_encode(getSetting('site_name')); ?>,
  "url": <?php echo json_encode(SITE_URL); ?>,
  "potentialAction": {
    "@type": "SearchAction",
    "target": <?php echo json_encode(SITE_URL . '/search.php?q={search_term_string}'); ?>,
    "query-input": "required name=search_term_string"
  }
}
</script>
<!-- JSON-LD Organization -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": <?php echo json_encode(getSetting('site_name')); ?>,
  "url": <?php echo json_encode(SITE_URL); ?>,
  "logo": <?php echo json_encode(SITE_URL . '/default-og.php'); ?><?php $contactEmail = getSetting('contact_email'); if ($contactEmail): ?>,
  "contactPoint": {
    "@type": "ContactPoint",
    "email": <?php echo json_encode(h($contactEmail)); ?>,
    "contactType": "customer support"
  }<?php endif; ?>
}
</script>
</head>
<body>
    <?php if ($ymCounterId): ?>
    <!-- Yandex.Metrika counter -->
                <script type="text/javascript" >
                    (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
                    m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
                    (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");
                    ym(<?php echo (int)$ymCounterId; ?>, "init", {
                    clickmap:true,
                    trackLinks:true,
                    accurateTrackBounce:true
                    });
                </script>
                <noscript><div><img src="https://mc.yandex.ru/watch/<?php echo (int)$ymCounterId; ?>" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
            <!-- /Yandex.Metrika counter -->
    <?php endif; ?>
    <div class="top-nav">
            <header class="site-header">
        
    </header>
        <div class="menu-button"> 
            <div class="menu-icon" id="menu-icon">
                <span></span><span></span><span></span>
            </div>
        </div>
        <div class="search">
             <form action="<?php echo SITE_URL; ?>/search.php" method="get" class="search-form">
                <input type="text" name="q" placeholder="<?php echo __('search'); ?>">
                <button type="submit">🔍</button>
            </form>
        </div>
        <div class="logo">
            <span>
             <h1 class="header-logo"><a href="<?php echo SITE_URL; ?>"><?php echo $siteName; ?></a></h1>
             <div class="site-desc"><?php echo h(getSetting('site_description')); ?></div>
             <?php include __DIR__ . '/../templates/icons_row.php'; ?>
            </span>
            <?php
            $logo = getSetting('site_logo');
            if ($logo && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo)): ?>
                <a href="<?php echo SITE_URL; ?>"><img src="<?php echo SITE_URL . $logo; ?>" alt="<?php echo $siteName; ?>" class="site-logo"></a>
            <?php endif; ?>
        </div>
    </div>

<div class="site-wrapper">
    <!-- Плавающая панель иконок (десктоп) -->
    <div class="float-bar">
        <div class="float-bar-body">
            <div class="float-bar-item" title="Меню">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                <div class="float-panel">
                    <div class="float-panel-title"><?php echo __('menu'); ?></div>
                    <nav class="float-nav"><?php echo renderMenu($menuItems); ?></nav>
                </div>
            </div>
            <div class="float-bar-item" title="Категории">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg>
                <div class="float-panel">
                    <div class="float-panel-title"><?php echo __('categories'); ?></div>
                    <ul class="float-categories">
                        <?php foreach ($allCategories as $cat): ?>
                            <li><a href="<?php echo SITE_URL; ?>/category/<?php echo h($cat['slug']); ?>"><?php echo h($cat['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="float-bar-item" title="Хештеги">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="9" y1="4" x2="7" y2="20"/><line x1="17" y1="4" x2="15" y2="20"/><line x1="4" y1="9" x2="20" y2="7"/><line x1="4" y1="17" x2="20" y2="15"/></svg>
                <div class="float-panel">
                    <div class="float-panel-title"><?php echo __('tags'); ?></div>
                    <div class="float-tags">
                        <?php foreach ($allTags as $tag): ?>
                            <a href="<?php echo SITE_URL; ?>/search.php?q=<?php echo urlencode($tag['name']); ?>" class="tag-link">#<?php echo h($tag['name']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="float-bar-item" title="Лучшие статьи">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 19h20"/><path d="M4 19l2-14 4 6 2-8 2 8 4-6 2 14"/></svg>
                <div class="float-panel">
                    <div class="float-panel-title"><?php echo __('top_posts'); ?></div>
                    <ul class="float-top-posts">
                        <?php foreach ($topPosts as $tp): ?>
                            <li><span class="likes-badge">&#9825; <?php echo (int)$tp['likes_count']; ?></span><a href="<?php echo SITE_URL; ?>/post/<?php echo h($tp['slug']); ?>"><?php echo h(mb_strlen($tp['title'], 'UTF-8') > 30 ? mb_substr($tp['title'], 0, 30, 'UTF-8') . '…' : $tp['title']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="float-bar-bottom">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="float-bar-item" title="Настройки">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    <div class="float-panel">
                        <div class="float-panel-title"><?php echo __('settings'); ?></div>
                        <p class="float-user-greeting"><?php echo __('hello'); ?>, <?php echo h($_SESSION['username']); ?>!</p>
                        <div class="float-auth-links" style="flex-direction:column; gap:6px;">
                            <?php if (function_exists('canAccessAdmin') && canAccessAdmin()): ?>
                                <a href="<?php echo SITE_URL; ?>/admin/" class="float-btn"><?php echo __('admin_panel'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <a href="<?php echo SITE_URL; ?>/logout.php" class="float-bar-item float-bar-link" title="Выход">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            <?php else: ?>
                <div class="float-bar-item" title="Вход">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    <div class="float-panel">
                        <div class="float-panel-title"><?php echo __('login'); ?></div>
                        <div class="float-auth-links" style="flex-direction:column;">
                            <a href="<?php echo SITE_URL; ?>/login.php" class="float-btn"><?php echo __('login'); ?></a>
                            <a href="<?php echo SITE_URL; ?>/register.php" class="float-btn float-btn-outline"><?php echo __('register'); ?></a>
                            <?php if (function_exists('yandexOAuthEnabled') && yandexOAuthEnabled()): ?>
                                <a href="<?php echo SITE_URL; ?>/oauth/yandex.php" class="float-btn-yandex" style="margin-top:4px;">
                                    <svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:4px;"><rect width="24" height="24" rx="4" fill="#fff" opacity=".2"/><text x="12" y="17" text-anchor="middle" font-family="Arial,sans-serif" font-weight="bold" font-size="14" fill="#fff">Я</text></svg>
                                    <?php echo __('login_via_yandex'); ?>
                                </a>
                            <?php endif; ?>
                            <?php if (function_exists('vkOAuthEnabled') && vkOAuthEnabled()): ?>
                                <a href="<?php echo SITE_URL; ?>/oauth/vk.php" class="float-btn-vk">
                                    <svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:4px;"><rect width="24" height="24" rx="4" fill="#fff" opacity=".2"/><text x="12" y="17" text-anchor="middle" font-family="Arial,sans-serif" font-weight="bold" font-size="12" fill="#fff">VK</text></svg>
                                    <?php echo __('login_via_vk'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Левая выдвижная панель -->
   
    <div class="drawer" id="drawer">
        <div class="drawer-header">
            <div class="search-drawer">
             <form action="<?php echo SITE_URL; ?>/search.php" method="get" class="search-form">
                <input type="text" name="q" placeholder="<?php echo __('search'); ?>">
                <button type="submit">🔍</button>
            </form>
        </div>
            <button class="drawer-close" id="drawer-close">	&laquo;</button>
        </div>
        <div class="drawer-content">
            <div class="drawer-section">
                <div class="drw-title">
                    <span class="drawer-sp">&#9776;</span>
                    <span><h3 class="drawer-title"><?php echo __('menu'); ?></h3></span>
                </div>
                <nav class="drawer-nav"><?php echo renderMenu($menuItems); ?></nav>
                <ul class="drawer-categories">
                    <?php foreach ($allCategories as $cat): ?>
                        <li><a href="<?php echo SITE_URL; ?>/category/<?php echo h($cat['slug']); ?>"><?php echo h($cat['name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
    
            <div class="drawer-tags">
    <?php
    // Получаем все теги (уже отсортированы по популярности)
    $allTags = getAllTags();
    $topTags = array_slice($allTags, 0, 14); // берём первые 14 самых популярных
    foreach ($topTags as $tag):
    ?>
        <a href="<?php echo SITE_URL; ?>/search.php?q=<?php echo urlencode($tag['name']); ?>" class="tag-link">#<?php
$name = $tag['name'];
echo h(mb_strlen($name, 'UTF-8') > 9 ? mb_substr($name, 0, 9, 'UTF-8') . '…' : $name);
?></a>
    <?php endforeach; ?>
    <?php if (count($allTags) > 14): ?>
        <div class="more-tags-link">
            <a href="<?php echo SITE_URL; ?>/tag.php" class="tag-link more">… все хештеги</a>
        </div>
    <?php endif; ?>
</div>

            <div class="drawer-section">
                <div class="drw-title">
                    <span class="drawer-sp">&#8679;</span>
                    <span><h3 class="drawer-title"><?php echo __('top_posts'); ?></h3></span>
                </div>
                <ul class="drawer-top-posts">
                    <?php foreach ($topPosts as $topPost): ?>
                        <li>
                            <span class="likes-badge">&#9825; <?php echo $topPost['likes_count']; ?></span>
                            <a href="<?php echo SITE_URL; ?>/post/<?php echo h($topPost['slug']); ?>">
                                <?php
                                // ограничение символов
                                    $maxChars = 20;
                                    $title = $topPost['title'];
                                        if (mb_strlen($title, 'UTF-8') > $maxChars) {
                                            $short = mb_substr($title, 0, $maxChars, 'UTF-8');
                                            $lastSpace = mb_strrpos($short, ' ', 0, 'UTF-8');
                                        if ($lastSpace !== false) {
                                        $short = mb_substr($short, 0, $lastSpace, 'UTF-8');
                                    }
                                    $shortTitle = $short . '…';
                                    } else {
                                    $shortTitle = $title;
                                    }
                                        echo h($shortTitle);
                    ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php
                $blockStmt = getDb()->prepare("SELECT content FROM custom_blocks WHERE position = ? AND is_active = 1 ORDER BY id ASC LIMIT 1");
                $blockStmt->execute(['leftmenu']);
                $blockContent = $blockStmt->fetchColumn();
                if ($blockContent) echo $blockContent;
                ?>
            </div>
        </div>
    </div>

    <main class="main-content">
    <?php
    // Хлебные крошки (безопасные)
    $show_breadcrumbs = false;
    $breadcrumb_items = [];

    if (isset($post) && is_array($post) && !empty($post)) {
        $show_breadcrumbs = true;
        $breadcrumb_items[] = ['name' => __('home'), 'url' => SITE_URL];
        $breadcrumb_items[] = ['name' => strip_tags($post['title']), 'url' => ''];
    } elseif (isset($category) && is_array($category) && !empty($category)) {
        $show_breadcrumbs = true;
        $breadcrumb_items[] = ['name' => __('home'), 'url' => SITE_URL];
        $breadcrumb_items[] = ['name' => strip_tags($category['name']), 'url' => ''];
    } elseif (isset($page) && is_array($page) && !empty($page)) {
        $show_breadcrumbs = true;
        $breadcrumb_items[] = ['name' => __('home'), 'url' => SITE_URL];
        $breadcrumb_items[] = ['name' => strip_tags($page['title']), 'url' => ''];
    } elseif (strpos($_SERVER['REQUEST_URI'] ?? '', '/search.php') !== false) {
        $show_breadcrumbs = true;
        $q = isset($_GET['q']) ? strip_tags($_GET['q']) : '';
        $breadcrumb_items[] = ['name' => __('home'), 'url' => SITE_URL];
        $breadcrumb_items[] = ['name' => __('search'), 'url' => SITE_URL . '/search.php'];
        if ($q) $breadcrumb_items[] = ['name' => $q, 'url' => ''];
    } elseif (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'contact.php') {
        $show_breadcrumbs = true;
        $breadcrumb_items[] = ['name' => __('home'), 'url' => SITE_URL];
        $breadcrumb_items[] = ['name' => 'Контакты', 'url' => ''];
    } elseif (strpos($_SERVER['REQUEST_URI'] ?? '', '/tag') !== false) {
        $show_breadcrumbs = true;
        $breadcrumb_items[] = ['name' => __('home'), 'url' => SITE_URL];
        $breadcrumb_items[] = ['name' => __('all_tags'), 'url' => ''];
    } elseif (isset($is_404) && $is_404) {
        $show_breadcrumbs = true;
        $breadcrumb_items[] = ['name' => __('home'), 'url' => SITE_URL];
        $breadcrumb_items[] = ['name' => '404', 'url' => ''];
    } elseif ($_SERVER['REQUEST_URI'] !== '/' && $_SERVER['REQUEST_URI'] !== '/index.php') {
        $show_breadcrumbs = true;
        $breadcrumb_items[] = ['name' => __('home'), 'url' => SITE_URL];
        $breadcrumb_items[] = ['name' => __('page_not_found'), 'url' => ''];
    }

    if ($show_breadcrumbs && count($breadcrumb_items) > 1) {
        echo '<div class="breadcrumbs">';
        foreach ($breadcrumb_items as $i => $item) {
            if (!empty($item['url'])) {
                echo '<a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['name']) . '</a>';
            } else {
                echo '<span>' . htmlspecialchars($item['name']) . '</span>';
            }
            if ($i < count($breadcrumb_items) - 1) echo '<span class="separator"> / </span>';
        }
        echo '</div>';
    }
    <?php /* Основной контент подключается из шаблонов (post_list.php и т.д.).
       Закрывающие теги </main> и </div> находятся в footer.php */ ?>