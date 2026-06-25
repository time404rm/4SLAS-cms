<?php
require_once __DIR__ . '/../includes/functions.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

$db = getDb();

// Очистка лога
if (isset($_POST['clear']) && verifyCsrfToken($_POST['csrf_token'])) {
    $db->exec("DELETE FROM log_404");
    header('Location: 404-report.php?cleared=1');
    exit;
}

// Удаление конкретной записи
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $db->prepare("DELETE FROM log_404 WHERE id = ?")->execute([(int)$_GET['delete']]);
    header('Location: 404-report.php');
    exit;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 30;
$total = $db->query("SELECT COUNT(*) FROM log_404")->fetchColumn();
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT * FROM log_404 ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$perPage, $offset]);
$logs = $stmt->fetchAll();

$grouped = $db->query("SELECT url, COUNT(*) as hits, MAX(created_at) as last_hit FROM log_404 GROUP BY url ORDER BY hits DESC LIMIT 20")->fetchAll();

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 мониторинг</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .section { background: #1e2a3e; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .section h2 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #2a3650; padding: 8px 10px; text-align: left; font-size: 11px; text-transform: uppercase; color: #8a9bd5; }
        td { padding: 8px 10px; border-bottom: 1px solid #2a3650; }
        tr:hover td { background: #1a2640; }
        .hit-count { font-weight: bold; font-size: 16px; color: #e74c3c; }
        .pagination { margin-top: 12px; }
        .pagination a { padding: 4px 10px; background: #2a3650; color: #e2e8f0; text-decoration: none; border-radius: 4px; margin: 0 2px; }
        .pagination a:hover { background: #3a4a6a; }
        .pagination .current { background: #3a4a6a; }
        .btn-danger { padding: 6px 14px; background: #7a2020; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .btn-danger:hover { background: #9a3030; }
        .btn-small { padding: 2px 8px; font-size: 11px; }
        .success { background: #1b5e3f; color: #fff; padding: 10px; border-radius: 4px; margin-bottom: 12px; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>🔍 404 мониторинг</h1>

    <?php if (isset($_GET['cleared'])): ?>
        <div class="success">Лог 404 очищен</div>
    <?php endif; ?>

    <!-- ТОП-20 самых частых 404 -->
    <div class="section">
        <h2>🏆 Самые частые 404</h2>
        <?php if (empty($grouped)): ?>
            <p style="color:#8a9bd5;">Нет записей</p>
        <?php else: ?>
            <table>
                <tr><th>URL</th><th>Кол-во</th><th>Последний раз</th><th></th></tr>
                <?php foreach ($grouped as $g): ?>
                <tr>
                    <td><code><?php echo h($g['url']); ?></code></td>
                    <td><span class="hit-count"><?php echo $g['hits']; ?></span></td>
                    <td><?php echo $g['last_hit']; ?></td>
                    <td><a href="redirect.php?old_url=<?php echo urlencode($g['url']); ?>" class="btn-danger btn-small" style="text-decoration:none;">➕ Редирект</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <!-- Свежие 404 -->
    <div class="section">
        <h2>📋 Последние 404</h2>
        <?php if (empty($logs)): ?>
            <p style="color:#8a9bd5;">Нет записей</p>
        <?php else: ?>
            <table>
                <tr><th>ID</th><th>URL</th><th>Referer</th><th>IP</th><th>Когда</th><th></th></tr>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo $log['id']; ?></td>
                    <td><code><?php echo h($log['url']); ?></code></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;"><?php echo h($log['referer']); ?></td>
                    <td><?php echo h($log['ip']); ?></td>
                    <td><?php echo $log['created_at']; ?></td>
                    <td>
                        <a href="?delete=<?php echo $log['id']; ?>" class="btn-danger btn-small" style="text-decoration:none;" onclick="return confirm('Удалить?')">✕</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'current' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <form method="post" style="margin-top:12px;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <button type="submit" name="clear" class="btn-danger" onclick="return confirm('Очистить весь лог?')">🗑 Очистить лог</button>
            </form>
        <?php endif; ?>
        <p style="color:#8a9bd5;font-size:12px;margin-top:8px;">Всего записей: <?php echo $total; ?></p>
    </div>
</body>
</html>
