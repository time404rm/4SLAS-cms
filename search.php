<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';

// Переключение языка
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$query = trim($_GET['q'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$posts = [];
$total = 0;
$totalPages = 0;
$isHashtagSearch = false;

if ($query !== '') {
    $db = getDb();

    // Проверяем, является ли запрос точным хештегом (без символа #)
    $stmt = $db->prepare("SELECT 1 FROM hashtags WHERE name = ?");
    $stmt->execute([$query]);
    $isHashtagSearch = (bool)$stmt->fetchColumn();

    if ($isHashtagSearch) {
        // Точный поиск по хештегу
        $sql = "SELECT p.*, u.username as author_name,
                (SELECT GROUP_CONCAT(c.name SEPARATOR ',') 
                 FROM post_categories pc 
                 JOIN categories c ON pc.category_id = c.id 
                 WHERE pc.post_id = p.id) as categories,
                (SELECT GROUP_CONCAT(h2.name SEPARATOR ',') 
                 FROM post_hashtags ph2 
                 JOIN hashtags h2 ON ph2.hashtag_id = h2.id 
                 WHERE ph2.post_id = p.id) as hashtags
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE EXISTS (
                    SELECT 1 FROM post_hashtags ph
                    JOIN hashtags h ON ph.hashtag_id = h.id
                    WHERE ph.post_id = p.id AND h.name = ?
                ) AND p.status = 'published'
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$query, $perPage, $offset]);
        $posts = $stmt->fetchAll();

        // Подсчёт общего количества
        $countSql = "SELECT COUNT(*) FROM posts p
                     WHERE EXISTS (
                         SELECT 1 FROM post_hashtags ph
                         JOIN hashtags h ON ph.hashtag_id = h.id
                         WHERE ph.post_id = p.id AND h.name = ?
                     ) AND p.status = 'published'";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute([$query]);
        $total = (int)$stmtCount->fetchColumn();
        $totalPages = ceil($total / $perPage);
    } else {
        // Обычный полнотекстовый поиск
        $total = getSearchPostsCount($query);
        $totalPages = ceil($total / $perPage);
        if ($page < 1) $page = 1;
        if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
        $offset = ($page - 1) * $perPage;
        $posts = searchPosts($query, $perPage, $offset);
    }
}

// SEO
if ($isHashtagSearch) {
    $pageTitle = '#' . h($query) . ' — ' . __('all_tags');
    $pageDescription = sprintf(__('hashtag_page_description'), h($query), $total);
} elseif ($query !== '') {
    $pageTitle = __('search') . ': ' . h($query);
    $pageDescription = sprintf(__('search_results_description'), h($query), $total);
} else {
    $pageTitle = __('search');
    $pageDescription = __('search_page_description');
}
$canonicalUrl = SITE_URL . '/search.php?q=' . urlencode($query) . '&page=' . $page;

include __DIR__ . '/templates/header.php';
?>

<div class="search-page">
    <?php if ($query === ''): ?>
        <p class="no-results"><?php echo __('enter_search_query'); ?></p>
        <div class="search-pg">
            <form action="<?php echo SITE_URL; ?>/search.php" method="get" class="search-form">
                <input type="text" name="q" placeholder="<?php echo __('search'); ?>">
                <button type="submit">🔍</button>
            </form>
        </div>
    <?php elseif ($total === 0): ?>
        <p class="no-results"><?php echo __('no_search_results'); ?></p>
    <?php else: ?>
        <div class="search-results-count">
            <?php if ($isHashtagSearch): ?>
                #<?php echo h($query); ?>
            <?php else: ?>
                <?php echo h($query); ?>
            <?php endif; ?>
            <?php echo sprintf(__('found_posts'), $total); ?>
        </div>

        <?php include __DIR__ . '/templates/post_list.php'; ?>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?q=<?php echo urlencode($query); ?>&page=<?php echo $page-1; ?>" class="pagination-prev">&laquo; <?php echo __('prev'); ?></a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="pagination-current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?q=<?php echo urlencode($query); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?q=<?php echo urlencode($query); ?>&page=<?php echo $page+1; ?>" class="pagination-next"><?php echo __('next'); ?> &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/templates/footer.php';
?>