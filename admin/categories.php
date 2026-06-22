<?php
require_once '../includes/functions.php';
$csrf_token = generateCsrfToken();
if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

$db = getDb();
$csrf_token = generateCsrfToken();
$message = '';
$error = '';

// Обработка добавления/редактирования/удаления
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Неверный CSRF-токен.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $name = trim($_POST['name']);
            $slug = !empty($_POST['slug']) ? slugify($_POST['slug']) : slugify($name);
            if (empty($name)) {
                $error = 'Название категории обязательно.';
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
                    $stmt->execute([$name, $slug]);
                    $message = 'Категория добавлена.';
                } catch (PDOException $e) {
                    $error = 'Ошибка: такая категория уже существует.';
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $name = trim($_POST['name']);
            $slug = !empty($_POST['slug']) ? slugify($_POST['slug']) : slugify($name);
            if (empty($name)) {
                $error = 'Название категории обязательно.';
            } else {
                $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $id]);
                $message = 'Категория обновлена.';
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            // Проверка, есть ли посты в этой категории (опционально)
            $stmt = $db->prepare("SELECT COUNT(*) FROM post_categories WHERE category_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                $error = "Невозможно удалить категорию: она используется в $count постах. Сначала переназначьте посты.";
            } else {
                $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Категория удалена.';
            }
        }
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Управление категориями</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .form-inline { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
        .form-inline .form-group { display: flex; flex-direction: column; }
        .categories-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .categories-table th, .categories-table td { border: 1px solid #2a3650; padding: 8px; text-align: left; }
        .edit-form { display: inline-flex; gap: 5px; flex-wrap: wrap; }
        .edit-form input, .edit-form button { margin: 0; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Управление категориями</h1>
    <?php if ($message): ?><p class="success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

    <h2>Добавить категорию</h2>
    <form method="post" class="form-inline">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
            <label>Название</label>
            <input type="text" name="name" required size="30">
        </div>
        <div class="form-group">
            <label>Slug (оставьте пустым для автогенерации)</label>
            <input type="text" name="slug" size="30">
        </div>
        <div class="form-group">
            <button type="submit">Добавить</button>
        </div>
    </form>

    <h2>Существующие категории</h2>
    <?php if (empty($categories)): ?>
        <p>Категории не найдены.</p>
    <?php else: ?>
        <table class="categories-table">
            <thead>
                <tr><th>ID</th><th>Название</th><th>Slug</th><th>Действия</th></tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?php echo $cat['id']; ?></td>
                    <td><?php echo htmlspecialchars($cat['name']); ?></td>
                    <td><?php echo htmlspecialchars($cat['slug']); ?></td>
                    <td>
                        <!-- Форма редактирования inline -->
                        <form method="post" class="edit-form" style="display:inline-flex;">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                            <input type="text" name="name" value="<?php echo htmlspecialchars($cat['name']); ?>" required size="15">
                            <input type="text" name="slug" value="<?php echo htmlspecialchars($cat['slug']); ?>" size="15" placeholder="slug">
                            <button type="submit">Обновить</button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Удалить категорию?')">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                            <button type="submit" style="background:#7f1a1a;">Удалить</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p><a href="index.php">← Назад в админ-панель</a></p>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>