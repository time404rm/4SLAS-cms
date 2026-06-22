<?php
require_once '../includes/functions.php';
if (!canAccessAdmin()) { header('Location: login.php'); exit; }

$db = getDb();
$siteName = getSetting('site_name');
$csrf_token = generateCsrfToken();

// Обработка обнуления счетчиков посещений
if (isset($_GET['reset_visits']) && isset($_GET['csrf_token'])) {
    if (!verifyCsrfToken($_GET['csrf_token'])) {
        die('CSRF token validation failed');
    }
    $db->exec("DELETE FROM page_views");
    header('Location: index.php?visits_reset=1');
    exit;
}

// 1. Новые комментарии (ожидают модерации)
$stmt = $db->query("SELECT COUNT(*) FROM comments WHERE status = 'pending'");
$pendingComments = $stmt->fetchColumn();

// 2. Наиболее интересные посты (по лайкам, топ-5)
$stmt = $db->query("SELECT id, title, slug, likes_count FROM posts WHERE status = 'published' ORDER BY likes_count DESC LIMIT 5");
$topPosts = $stmt->fetchAll();

// 3. Список лайков (последние 10 лайков с именами пользователей)
$stmt = $db->query("
    SELECT l.post_id, p.title, l.created_at, u.username 
    FROM likes l
    JOIN posts p ON l.post_id = p.id
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 10
");
$recentLikes = $stmt->fetchAll();

// 4. Популярные хештеги (топ-10)
$stmt = $db->query("
    SELECT h.name, COUNT(ph.post_id) as count
    FROM hashtags h
    JOIN post_hashtags ph ON h.id = ph.hashtag_id
    JOIN posts p ON ph.post_id = p.id
    WHERE p.status = 'published'
    GROUP BY h.name
    ORDER BY count DESC
    LIMIT 10
");
$topHashtags = $stmt->fetchAll();

// 5. Счётчик посещений (за сегодня, вчера, за всё время)
$todayVisits = 0;
$yesterdayVisits = 0;
$totalVisits = 0;
$tableExists = $db->query("SHOW TABLES LIKE 'page_views'")->rowCount() > 0;
if ($tableExists) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $stmt = $db->prepare("SELECT SUM(visits) FROM page_views WHERE visit_date = ?");
    $stmt->execute([$today]);
    $todayVisits = (int)$stmt->fetchColumn();
    $stmt->execute([$yesterday]);
    $yesterdayVisits = (int)$stmt->fetchColumn();
    $totalVisits = (int)$db->query("SELECT SUM(visits) FROM page_views")->fetchColumn();
}

$pageTitle = __('admin_dashboard');
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo h($siteName); ?> - <?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .reset-btn {
            display: inline-block;
            margin-top: 10px;
            background: #7f1a1a;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
        }
        .reset-btn:hover {
            background: #991b1b;
            text-decoration: none;
        }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1><?php echo __('dashboard'); ?></h1>
    
    <?php if (isset($_GET['visits_reset'])): ?>
        <div class="success">Счётчики посещений обнулены.</div>
    <?php endif; ?>

    <!-- Блок с серверным временем -->
    <div class="server-time">
        <h3>🕒 Серверное время</h3>
        <p><?php echo date('d.m.Y H:i:s'); ?></p>
        <small>Часовой пояс: <?php echo date_default_timezone_get(); ?></small>
    </div>

    <div class="dashboard-stats">
        <div class="stat-card">
            <h3>✉️ <?php echo __('pending_comments'); ?></h3>
            <div class="number"><?php echo $pendingComments; ?></div>
            <a href="comments.php?status=pending"><?php echo __('moderate'); ?></a>
        </div>
        <div class="stat-card">
            <h3>👁️ <?php echo __('visits_today'); ?></h3>
            <div class="number"><?php echo $todayVisits; ?></div>
        </div>
        <div class="stat-card">
            <h3>📊 <?php echo __('visits_yesterday'); ?></h3>
            <div class="number"><?php echo $yesterdayVisits; ?></div>
        </div>
        <div class="stat-card">
            <h3>🌐 <?php echo __('total_visits'); ?></h3>
            <div class="number"><?php echo $totalVisits; ?></div>
            <?php if ($tableExists && $totalVisits > 0 && isAdmin()): ?>
                <a href="?reset_visits=1&csrf_token=<?php echo urlencode($csrf_token); ?>" class="reset-btn" onclick="return confirm('Обнулить все счётчики посещений? Это действие необратимо.')">🗑️ Обнулить</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-section">
        <h2>⭐ <?php echo __('top_posts'); ?></h2>
        <?php if (empty($topPosts)): ?>
            <p><?php echo __('no_posts'); ?></p>
        <?php else: ?>
            <table>
                <tr><th><?php echo __('title'); ?></th><th><?php echo __('likes'); ?></th><th></th></tr>
                <?php foreach ($topPosts as $p): ?>
                <tr>
                    <td><?php echo h($p['title']); ?></td>
                    <td>👍 <?php echo $p['likes_count']; ?></td>
                    <td><a href="<?php echo SITE_URL; ?>/post/<?php echo h($p['slug']); ?>" target="_blank"><?php echo __('view'); ?></a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="dashboard-section">
        <h2>❤️ <?php echo __('recent_likes'); ?></h2>
        <?php if (empty($recentLikes)): ?>
            <p><?php echo __('no_likes'); ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th><?php echo __('post'); ?></th><th><?php echo __('user'); ?></th><th><?php echo __('date'); ?></th></tr>
                </thead>
                <tbody>
                <?php foreach ($recentLikes as $like): ?>
                    <tr>
                        <td><a href="<?php echo SITE_URL; ?>/post/<?php echo h($like['slug'] ?? ''); ?>"><?php echo h($like['title']); ?></a></td>
                        <td><?php echo $like['username'] ? h($like['username']) : __('guest'); ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($like['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="dashboard-section">
        <h2>#️⃣ <?php echo __('popular_hashtags'); ?></h2>
        <?php if (empty($topHashtags)): ?>
            <p><?php echo __('no_hashtags'); ?></p>
        <?php else: ?>
            <div class="hashtag-list">
                <?php foreach ($topHashtags as $tag): ?>
                    <a href="<?php echo SITE_URL; ?>/search.php?q=<?php echo urlencode($tag['name']); ?>" class="hashtag-item" target="_blank">
                        #<?php echo h($tag['name']); ?> <span class="badge"><?php echo $tag['count']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>