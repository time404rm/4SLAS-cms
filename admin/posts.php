<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/functions.php';
if (!canManagePosts()) { header('Location: login.php'); exit; }

$db = getDb();
$message = '';
$error = '';

// ========== ОБРАБОТКА ОДИНОЧНОГО УДАЛЕНИЯ ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_single']) && isset($_POST['id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF токен неверен';
    } elseif (!canDeletePost()) {
        $error = 'Недостаточно прав для удаления';
    } else {
        $id = (int)$_POST['id'];
        if ($id) {
            $stmt = $db->prepare("SELECT intro_image FROM posts WHERE id = ?");
            $stmt->execute([$id]);
            $image = $stmt->fetchColumn();
            if ($image && file_exists(POSTS_IMG_DIR . $image)) {
                unlink(POSTS_IMG_DIR . $image);
            }
            $stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Пост удалён';
        } else {
            $error = 'Неверный ID поста';
        }
    }
}
// ========== ОБРАБОТКА МАССОВЫХ ДЕЙСТВИЙ ==========
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF токен неверен';
    } else {
        $ids = $_POST['ids'] ?? [];
        if (empty($ids)) {
            $error = 'Не выбрано ни одного поста';
        } else {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($_POST['bulk_action'] === 'delete') {
                $stmt = $db->prepare("SELECT intro_image FROM posts WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($images as $img) {
                    if ($img && file_exists(POSTS_IMG_DIR . $img)) {
                        unlink(POSTS_IMG_DIR . $img);
                    }
                }
                $stmt = $db->prepare("DELETE FROM posts WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $message = 'Удалено постов: ' . $stmt->rowCount();
            } elseif ($_POST['bulk_action'] === 'disable_comments') {
                $stmt = $db->prepare("UPDATE posts SET comments_enabled = 0 WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $message = 'Комментарии отключены для ' . $stmt->rowCount() . ' постов';
            } elseif ($_POST['bulk_action'] === 'enable_comments') {
                $stmt = $db->prepare("UPDATE posts SET comments_enabled = 1 WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $message = 'Комментарии включены для ' . $stmt->rowCount() . ' постов';
            }
        }
    }
}

// Переключение статуса (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status']) && isset($_POST['status'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF токен неверен';
    } else {
        $id = (int)$_POST['toggle_status'];
        $newStatus = $_POST['status'] === 'published' ? 'published' : 'draft';
        $stmt = $db->prepare("UPDATE posts SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        $message = 'Статус обновлён';
    }
}

// Список категорий для фильтра
$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
// Список хештегов для фильтра
$hashtags = $db->query("SELECT id, name FROM hashtags ORDER BY name")->fetchAll();

// Фильтры
$search = trim($_GET['search'] ?? '');
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$statusFilter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
$hashtagFilter = isset($_GET['hashtag']) ? (int)$_GET['hashtag'] : 0;
$allowedStatusFilter = ['all', 'published', 'draft'];
if (!in_array($statusFilter, $allowedStatusFilter)) $statusFilter = 'all';

// Пагинация
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Построение запроса с учётом фильтров
$sql = "SELECT DISTINCT p.*, 
        u.username as author_name,
        (SELECT GROUP_CONCAT(c2.name SEPARATOR ', ') 
         FROM post_categories pc2 
         JOIN categories c2 ON pc2.category_id = c2.id 
         WHERE pc2.post_id = p.id) as categories_list,
        (SELECT GROUP_CONCAT(h2.name SEPARATOR ', ') 
         FROM post_hashtags ph2 
         JOIN hashtags h2 ON ph2.hashtag_id = h2.id 
         WHERE ph2.post_id = p.id) as hashtags_list
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN post_categories pc ON p.id = pc.post_id
        LEFT JOIN post_hashtags ph ON p.id = ph.post_id
        WHERE 1=1";
$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND p.status = ?";
    $params[] = $statusFilter;
}
if ($categoryFilter > 0) {
    $sql .= " AND EXISTS (SELECT 1 FROM post_categories pc2 WHERE pc2.post_id = p.id AND pc2.category_id = ?)";
    $params[] = $categoryFilter;
}
if ($hashtagFilter > 0) {
    $sql .= " AND EXISTS (SELECT 1 FROM post_hashtags ph2 WHERE ph2.post_id = p.id AND ph2.hashtag_id = ?)";
    $params[] = $hashtagFilter;
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $sql .= " AND (p.title LIKE ? OR EXISTS (SELECT 1 FROM post_hashtags ph3 JOIN hashtags h3 ON ph3.hashtag_id = h3.id WHERE ph3.post_id = p.id AND h3.name LIKE ?))";
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Подсчёт общего количества с теми же фильтрами
$countSql = "SELECT COUNT(DISTINCT p.id) FROM posts p WHERE 1=1";
$countParams = [];
if ($statusFilter !== 'all') {
    $countSql .= " AND p.status = ?";
    $countParams[] = $statusFilter;
}
if ($categoryFilter > 0) {
    $countSql .= " AND EXISTS (SELECT 1 FROM post_categories pc2 WHERE pc2.post_id = p.id AND pc2.category_id = ?)";
    $countParams[] = $categoryFilter;
}
if ($hashtagFilter > 0) {
    $countSql .= " AND EXISTS (SELECT 1 FROM post_hashtags ph2 WHERE ph2.post_id = p.id AND ph2.hashtag_id = ?)";
    $countParams[] = $hashtagFilter;
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $countSql .= " AND (p.title LIKE ? OR EXISTS (SELECT 1 FROM post_hashtags ph3 JOIN hashtags h3 ON ph3.hashtag_id = h3.id WHERE ph3.post_id = p.id AND h3.name LIKE ?))";
    $countParams[] = $like;
    $countParams[] = $like;
}
$stmtCount = $db->prepare($countSql);
$stmtCount->execute($countParams);
$totalPosts = $stmtCount->fetchColumn();
$totalPages = ceil($totalPosts / $perPage);

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Управление постами</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .bulk-bar { margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .bulk-bar select, .bulk-bar button { padding: 5px 10px; }
        .filters { margin-bottom: 20px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; background: #1e2a3e; padding: 10px; border-radius: 8px; }
        .filters .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filters label { font-size: 0.8rem; color: #b9c7e6; }
        .filters select, .filters button { padding: 5px 10px; }
        .pagination { margin-top: 20px; display: flex; gap: 5px; justify-content: center; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 5px 10px; border: 1px solid #2a3650; text-decoration: none; border-radius: 4px; }
        .pagination .current { background: #2563eb; color: white; border-color: #2563eb; }
        .post-image { max-width: 50px; max-height: 50px; }
        .status-published { color: #22c55e; font-weight: bold; }
        .status-draft { color: #f97316; font-weight: bold; }
        .action-links a { margin-right: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #2a3650; text-align: left; }
        th { background: #1e2a3e; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Управление постами</h1>
    <a href="post_edit.php" class="button">➕ Добавить пост</a>
    <?php if ($message): ?><p class="success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

    <!-- Форма фильтров -->
    <form method="get" class="filters">
        <div class="filter-group">
            <label>Поиск:</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Название или хештег..." style="padding:5px 10px;background:#0f1422;border:1px solid #2a3650;color:#e2e8f0;border-radius:4px;">
        </div>
        <div class="filter-group">
            <label>Статус:</label>
            <select name="status_filter">
                <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>Все</option>
                <option value="published" <?php echo $statusFilter == 'published' ? 'selected' : ''; ?>>Опубликованные</option>
                <option value="draft" <?php echo $statusFilter == 'draft' ? 'selected' : ''; ?>>Черновики</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Категория:</label>
            <select name="category">
                <option value="0">Все категории</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Хештег:</label>
            <select name="hashtag">
                <option value="0">Все хештеги</option>
                <?php foreach ($hashtags as $tag): ?>
                    <option value="<?php echo $tag['id']; ?>" <?php echo $hashtagFilter == $tag['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($tag['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <button type="submit">Применить фильтр</button>
            <a href="posts.php" style="margin-left:10px;">Сбросить</a>
        </div>
    </form>

    <!-- Форма массовых действий -->
    <form method="post" id="bulkForm">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <div class="bulk-bar">
            <input type="checkbox" id="selectAll"> Выбрать все
            <select name="bulk_action">
                <option value="">-- Действие --</option>
                <?php if (canDeletePost()): ?>
                    <option value="delete">Удалить выбранные</option>
                <?php endif; ?>
                <option value="disable_comments">Отключить комментарии</option>
                <option value="enable_comments">Включить комментарии</option>
            </select>
            <button type="submit">Применить</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAllCheckbox"></th>
                    <th>ID</th>
                    <th>Заголовок</th>
                    <th>Автор</th>
                    <th>Категории</th>
                    <th>Хештеги</th>
                    <th>Slug</th>
                    <th>Комментарии</th>
                    <th>Лайки</th>
                    <th>Дата</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?php echo $post['id']; ?>"></td>
                    <td><?php echo $post['id']; ?></td>
                    <td><a href="post_edit.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a></td>
                    <td>@<?php echo !empty($post['author_name']) ? htmlspecialchars($post['author_name']) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($post['categories_list'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($post['hashtags_list'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($post['slug']); ?></td>
                    <td><?php echo $post['comments_enabled'] ? '✅ вкл' : '❌ выкл'; ?></td>
                    <td><?php echo $post['likes_count']; ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($post['created_at'])); ?></td>
                    <td>
                        <?php if ($post['status'] == 'published'): ?>
                            <span class="status-published">✅ Опубликован</span><br>
                            <form method="post" style="display:inline" onsubmit="return confirm('Снять с публикации?')">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="toggle_status" value="<?php echo $post['id']; ?>">
                                <input type="hidden" name="status" value="draft">
                                <button type="submit" class="link-btn">Снять</button>
                            </form>
                        <?php else: ?>
                            <span class="status-draft">⏳ Черновик</span><br>
                            <form method="post" style="display:inline" onsubmit="return confirm('Опубликовать?')">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="toggle_status" value="<?php echo $post['id']; ?>">
                                <input type="hidden" name="status" value="published">
                                <button type="submit" class="link-btn">Опубликовать</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td class="action-links">
                        <a href="post_edit.php?id=<?php echo $post['id']; ?>">✏️ Редактировать</a>
                        <?php if (canDeletePost()): ?>
                        | <form method="post" onsubmit="return confirm('Удалить пост?');" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="delete_single" value="1">
                            <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                            <button type="submit" style="background:none; border:none; color:#f44336; cursor:pointer; text-decoration:underline; padding:0; margin:0; font:inherit;">🗑️ Удалить</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($posts)): ?>
                <tr><td colspan="12">Постов не найдено</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">&laquo; Пред.</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">След. &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <script>
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('input[name="ids[]"]');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => cb.checked = selectAll.checked);
            });
        }
    </script>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>