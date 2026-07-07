<?php
require_once '../includes/functions.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

$db = getDb();
$csrf_token = generateCsrfToken();
$message = '';
$error = '';

// Позиции блоков (жёстко заданы)
$positions = ['leftmenu', 'footer', 'after_first_post', 'mid_content', 'after_page_content'];

// Добавление/редактирование блока
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_block'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) $error = 'CSRF failed';
    else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name']);
        $content = $_POST['content'];
        $position = $_POST['position'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if (empty($name)) $error = 'Название блока обязательно';
        else {
            if ($id) {
                $db->prepare("UPDATE custom_blocks SET name=?, content=?, position=?, is_active=? WHERE id=?")->execute([$name, $content, $position, $is_active, $id]);
                $message = 'Блок обновлён';
            } else {
                $db->prepare("INSERT INTO custom_blocks (name, content, position, is_active) VALUES (?,?,?,?)")->execute([$name, $content, $position, $is_active]);
                $message = 'Блок добавлен';
            }
            clearCache();
        }
    }
}

// Удаление блока
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    if (!verifyCsrfToken($_GET['csrf_token'])) $error = 'CSRF failed';
    else {
        $id = (int)$_GET['delete'];
        $db->prepare("DELETE FROM custom_blocks WHERE id = ?")->execute([$id]);
        clearCache();
        header('Location: blocks.php?msg=deleted');
        exit;
    }
}

// Получаем все блоки, сгруппированные по позициям
$blocks = $db->query("SELECT * FROM custom_blocks ORDER BY position, id ASC")->fetchAll();
$grouped = [];
foreach ($blocks as $b) {
    $grouped[$b['position']][] = $b;
}
// Добавляем пустые позиции
foreach ($positions as $p) {
    if (!isset($grouped[$p])) $grouped[$p] = [];
}

if (isset($_GET['msg'])) $message = $_GET['msg'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Управление блоками</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .mb-10 { margin-bottom: 10px; }
        .block-group { margin-bottom: 30px; border: 1px solid #2a3650; border-radius: 8px; overflow: hidden; }
        .block-group-header { background: #1e2a3e; padding: 10px 15px; display: flex; justify-content: space-between; align-items: center; }
        .block-group-header h3 { margin: 0; color: #60a5fa; font-size: 1rem; }
        .block-group-body { padding: 10px; min-height: 40px; background: #0f1422; }
        .block-item { background: #1e2a3e; border: 1px solid #2a3650; border-radius: 4px; padding: 10px 12px; margin-bottom: 6px; display: flex; align-items: center; gap: 10px; }
        .block-item:hover { background: #263450; }
        .block-item .block-name { flex: 1; color: #e2e8f0; font-weight: 500; }
        .block-item .block-status { font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; flex-shrink: 0; }
        .block-item .block-status.active { background: #166534; color: #86efac; }
        .block-item .block-status.inactive { background: #6b3b00; color: #fcd34d; }
        .block-item .block-actions { display: flex; gap: 4px; flex-shrink: 0; }
        .block-item .block-actions a { padding: 4px 8px; font-size: 0.75rem; border-radius: 4px; text-decoration: none; }
        .block-empty { color: #6b7fa0; text-align: center; padding: 20px; font-style: italic; }
        .toolbar { margin-bottom: 20px; }
        .btn { background: #2563eb; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.9rem; }
        .btn:hover { background: #1e40af; }
        .btn-sm { padding: 4px 8px; font-size: 0.75rem; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-secondary { background: #2a3650; color: #e2e8f0; }
        .btn-secondary:hover { background: #3b4e6e; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Свободные блоки</h1>
    <?php if ($message): ?><div class="success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="toolbar">
        <a href="block_edit.php" class="btn">+ Добавить блок</a>
    </div>

    <?php foreach ($positions as $pos): ?>
    <div class="block-group">
        <div class="block-group-header">
            <h3><?php echo htmlspecialchars($pos); ?></h3>
        </div>
        <div class="block-group-body">
            <?php if (!empty($grouped[$pos])): ?>
                <?php foreach ($grouped[$pos] as $b): ?>
                <div class="block-item">
                    <span class="block-name"><?php echo htmlspecialchars($b['name']); ?></span>
                    <span class="block-status <?php echo $b['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $b['is_active'] ? 'Активен' : 'Отключён'; ?></span>
                    <div class="block-actions">
                        <a href="block_edit.php?id=<?php echo $b['id']; ?>" class="btn-sm btn-secondary">✏️</a>
                        <a href="?delete=<?php echo $b['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" class="btn-sm btn-danger" onclick="return confirm('Удалить блок &quot;<?php echo htmlspecialchars($b['name'], ENT_QUOTES); ?>&quot;?')">🗑️</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="block-empty">Нет блоков в этой позиции</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>
