<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$db = getDb();

// Получаем категорию по slug
$stmt = $db->prepare("SELECT id, name, slug FROM categories WHERE slug = ?");
$stmt->execute([$slug]);
$category = $stmt->fetch();

if (!$category) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}


// Пагинация
$postsPerLoad = (int)getSetting('posts_per_load') ?: 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $postsPerLoad;

// Получаем общее количество постов в категории (для остановки подгрузки)
$stmtCount = $db->prepare("SELECT COUNT(*) FROM posts p INNER JOIN post_categories pc ON p.id = pc.post_id WHERE pc.category_id = ? AND p.status = 'published'");
$stmtCount->execute([$category['id']]);
$totalPosts = $stmtCount->fetchColumn();

// Получаем первую порцию постов (с автором)
$stmt = $db->prepare("
    SELECT p.*,
        u.username as author_name,
        (SELECT GROUP_CONCAT(c2.name SEPARATOR ',') 
         FROM post_categories pc2 
         JOIN categories c2 ON pc2.category_id = c2.id 
         WHERE pc2.post_id = p.id) as categories,
        (SELECT GROUP_CONCAT(h.name SEPARATOR ',') 
         FROM post_hashtags ph 
         JOIN hashtags h ON ph.hashtag_id = h.id 
         WHERE ph.post_id = p.id) as hashtags
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    INNER JOIN post_categories pc ON p.id = pc.post_id
    WHERE pc.category_id = ? AND p.status = 'published'
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$category['id'], $postsPerLoad, $offset]);
$posts = $stmt->fetchAll();

// SEO
$pageTitle = __('category') . ': ' . h($category['name']);
$pageDescription = __('posts_in_category') . ' ' . h($category['name']);
$canonicalUrl = SITE_URL . '/category/' . $category['slug'];

$includeInfiniteScroll = true;
include __DIR__ . '/templates/header.php';
?>

<h1><?php echo h($category['name']); ?></h1>
<div id="posts-container">
    <?php if (empty($posts)): ?>
        <p><?php echo __('no_posts_in_category'); ?></p>
    <?php else: ?>
        <?php include __DIR__ . '/templates/post_list.php'; ?>
    <?php endif; ?>
</div>
<div id="loading-spinner" style="display:none; text-align:center; padding:20px;"><?php echo __('loading'); ?></div>
<div id="load-error" style="display:none; text-align:center; padding:20px; color:#e74c3c;"><?php echo __('load_error'); ?></div>

<script>
    var currentOffset = <?php echo $postsPerLoad; ?>;
    var postsPerLoad = <?php echo $postsPerLoad; ?>;
    var apiUrl = '/api/load_more_posts.php';
    var apiParams = { type: 'category', slug: '<?php echo $category['slug']; ?>' };
    var totalPosts = <?php echo $totalPosts; ?>;
</script>

<?php
include __DIR__ . '/templates/footer.php';
?>