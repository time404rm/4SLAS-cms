<?php
require_once '../includes/functions.php';
$csrf_token = generateCsrfToken();
if (!canManageComments()) { header('Location: login.php'); exit; }

$db = getDb();
$csrf_token = generateCsrfToken();
$message = '';
$error = '';

// Обработка массовых действий: approve, spam, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Неверный CSRF-токен.';
    } else {
        $ids = $_POST['ids'] ?? [];
        if (empty($ids)) {
            $error = 'Не выбрано ни одного комментария.';
        } else {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $action = $_POST['bulk_action'];
            if ($action === 'approve') {
                $stmt = $db->prepare("UPDATE comments SET status = 'approved' WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $message = 'Одобрено комментариев: ' . $stmt->rowCount();
            } elseif ($action === 'spam') {
                $stmt = $db->prepare("UPDATE comments SET status = 'spam' WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $message = 'Помечено спамом: ' . $stmt->rowCount();
            } elseif ($action === 'delete') {
                $stmt = $db->prepare("DELETE FROM comments WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $message = 'Удалено комментариев: ' . $stmt->rowCount();
            }
        }
    }
}

// Фильтры по статусу
$statusFilter = $_GET['status'] ?? 'pending';
$allowedStatus = ['pending', 'approved', 'spam'];
if (!in_array($statusFilter, $allowedStatus)) $statusFilter = 'pending';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Подсчёт общего количества
$stmt = $db->prepare("SELECT COUNT(*) FROM comments WHERE status = ?");
$stmt->execute([$statusFilter]);
$totalComments = $stmt->fetchColumn();
$totalPages = ceil($totalComments / $perPage);

// Получение комментариев с информацией о посте
$stmt = $db->prepare("
    SELECT c.*, p.title as post_title, p.slug as post_slug 
    FROM comments c 
    LEFT JOIN posts p ON c.post_id = p.id 
    WHERE c.status = ? 
    ORDER BY c.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$statusFilter, $perPage, $offset]);
$comments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Управление комментариями</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .filter-bar { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; }
        .filter-bar a { padding: 5px 10px; background: #1e2a3e; text-decoration: none; border-radius: 4px; }
        .filter-bar a.active { background: #2563eb; color: white; }
        .bulk-bar { margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .comments-table { width: 100%; border-collapse: collapse; }
        .comments-table th, .comments-table td { border: 1px solid #2a3650; padding: 8px; vertical-align: top; }
        .comment-content { max-width: 400px; word-break: break-word; }
        .pagination { margin-top: 20px; display: flex; gap: 5px; justify-content: center; }
        .pagination a, .pagination span { padding: 5px 10px; border: 1px solid #2a3650; text-decoration: none; }
        .pagination .current { background: #2563eb; color: white; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Управление комментариями</h1>
    <?php if ($message): ?><p class="success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

    <div class="filter-bar">
        <a href="?status=pending" class="<?php echo $statusFilter == 'pending' ? 'active' : ''; ?>">На модерации</a>
        <a href="?status=approved" class="<?php echo $statusFilter == 'approved' ? 'active' : ''; ?>">Одобренные</a>
        <a href="?status=spam" class="<?php echo $statusFilter == 'spam' ? 'active' : ''; ?>">Спам</a>
    </div>

    <form method="post" id="bulkForm">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <div class="bulk-bar">
            <input type="checkbox" id="selectAll"> Выбрать все
            <select name="bulk_action">
                <option value="">-- Действие --</option>
                <?php if ($statusFilter == 'pending'): ?>
                    <option value="approve">Одобрить</option>
                    <option value="spam">Пометить спамом</option>
                <?php elseif ($statusFilter == 'approved'): ?>
                    <option value="spam">Пометить спамом</option>
                <?php elseif ($statusFilter == 'spam'): ?>
                    <option value="delete">Удалить навсегда</option>
                <?php endif; ?>
                <option value="delete">Удалить</option>
            </select>
            <button type="submit">Применить</button>
        </div>

        <?php if (empty($comments)): ?>
            <p>Комментариев не найдено.</p>
        <?php else: ?>
            <table class="comments-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllCheckbox"></th>
                        <th>ID</th>
                        <th>Автор</th>
                        <th>Email</th>
                        <th>Комментарий</th>
                        <th>Пост</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comments as $comment): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?php echo $comment['id']; ?>"></td>
                        <td><?php echo $comment['id']; ?></td>
                        <td><?php echo htmlspecialchars($comment['author_name']); ?></td>
                        <td><?php echo htmlspecialchars($comment['author_email']); ?></td>
                        <td class="comment-content"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></td>
                        <td>
                            <?php if ($comment['post_title']): ?>
                                <a href="../post.php?slug=<?php echo urlencode($comment['post_slug']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($comment['post_title']); ?>
                                </a>
                            <?php else: ?>
                                <em>Пост удалён</em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?></td>
                        <td>
                            <?php if ($comment['status'] == 'pending'): ?>
                                <a href="comment_action.php?action=approve&id=<?php echo $comment['id']; ?>&csrf=<?php echo urlencode($csrf_token); ?>">Одобрить</a> |
                                <a href="comment_action.php?action=spam&id=<?php echo $comment['id']; ?>&csrf=<?php echo urlencode($csrf_token); ?>">Спам</a> |
                            <?php elseif ($comment['status'] == 'approved'): ?>
                                <a href="comment_action.php?action=spam&id=<?php echo $comment['id']; ?>&csrf=<?php echo urlencode($csrf_token); ?>">Спам</a> |
                            <?php endif; ?>
                            <a href="comment_action.php?action=delete&id=<?php echo $comment['id']; ?>&csrf=<?php echo urlencode($csrf_token); ?>" onclick="return confirm('Удалить комментарий?')">Удалить</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </form>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?status=<?php echo $statusFilter; ?>&page=<?php echo $page-1; ?>">&laquo; Пред.</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?status=<?php echo $statusFilter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?status=<?php echo $statusFilter; ?>&page=<?php echo $page+1; ?>">След. &raquo;</a>
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