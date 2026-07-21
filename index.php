<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/redirect-handler.php';
checkRedirect();
require_once __DIR__ . '/includes/seo.php';
if (maintenanceModeActive()) {
    renderMaintenancePage();
}

// Если запрошен несуществующий URL — показываем 404
$q = $_GET['q'] ?? '';
if ($q !== '' && $q !== 'index.php') {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ---------- PAGE CACHE ----------
$doCache = isCacheEnabled() && !isAdmin() && $_SERVER['REQUEST_METHOD'] === 'GET';
if ($doCache) {
    $cacheKey = getCacheKey($_SERVER['REQUEST_URI']);
    $cached = getCache($cacheKey);
    if ($cached !== false) { echo $cached; exit; }
    ob_start();
}
// --------------------------------

$seo = getSeoData('home', null);
$pageTitle = $seo['meta_title'] ?: __('home_title');
$pageDescription = $seo['meta_description'] ?: getSetting('site_description');
$pageKeywords = $seo['meta_keywords'] ?: '';
$ogImage = $seo['og_image'] ?: '';

$postsPerLoad = (int)getSetting('posts_per_load') ?: 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$posts = getPosts($postsPerLoad, 0);
$totalPosts = getPostsCount();
$totalPages = ceil($totalPosts / $postsPerLoad);

$includeInfiniteScroll = true;
include __DIR__ . '/templates/header.php';
?>

<div id="posts-container">
    <?php include __DIR__ . '/templates/post_list.php'; ?>
</div>
<div id="loading-spinner" style="display:none; text-align:center; padding:20px;">
    <?php echo __('loading'); ?>
</div>
<div id="load-error" style="display:none; text-align:center; padding:20px; color:#e74c3c;">
    <?php echo __('load_error'); ?>
</div>

<!-- Скрытые ссылки для поисковых роботов (пагинация) -->
<?php if ($totalPages > 1): ?>
<div style="display: none;" class="pagination-seo">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="<?php echo SITE_URL . '/?page=' . $i; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<script>
    var currentOffset = <?php echo $postsPerLoad; ?>;
    var postsPerLoad = <?php echo $postsPerLoad; ?>;
    var apiUrl = '/api/load_more_posts.php';
    var apiParams = { type: 'home' };
</script>

<?php
// ---------- SAVE CACHE ----------
if ($doCache && ob_get_level() > 0) {
    setCache($cacheKey, ob_get_contents());
    ob_end_flush();
}
// --------------------------------
include __DIR__ . '/templates/footer.php';
?>