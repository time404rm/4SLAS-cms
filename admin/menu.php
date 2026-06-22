<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../includes/functions.php';
require_once '../includes/menu.php';

if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

$csrf_token = generateCsrfToken();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'CSRF токен неверен';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $result = addMenuItem(
                $_POST['title'],
                $_POST['url'],
                (int)$_POST['parent_id'],
                $_POST['target'],
                $_POST['icon'],
                (int)$_POST['sort_order']
            );
            $message = $result ? 'Пункт добавлен' : 'Ошибка добавления';
        } elseif ($action === 'edit') {
            $result = updateMenuItem(
                (int)$_POST['id'],
                $_POST['title'],
                $_POST['url'],
                (int)$_POST['parent_id'],
                $_POST['target'],
                $_POST['icon'],
                (int)$_POST['sort_order'],
                (int)$_POST['status']
            );
            $message = $result ? 'Пункт обновлён' : 'Ошибка обновления';
        } elseif ($action === 'delete') {
            $result = deleteMenuItem((int)$_POST['id']);
            $message = $result ? 'Пункт удалён' : 'Ошибка удаления';
        }
        
        if ($message && !$error) {
            header('Location: menu.php?msg=' . urlencode($message));
            exit;
        }
    }
}

$menuItems = getAllMenuItems();
$parents = [0 => '— Корневой —'] + array_column($menuItems, 'title', 'id');

$db = getDb();
$pages = $categories = $hashtags = [];
try {
    $pages = $db->query("SELECT id, title, slug FROM pages WHERE status = 'published' ORDER BY title")->fetchAll();
} catch (PDOException $e) {}
try {
    $categories = $db->query("SELECT id, name, slug FROM categories ORDER BY name")->fetchAll();
} catch (PDOException $e) {}
try {
    $hashtags = $db->query("SELECT name FROM hashtags ORDER BY name")->fetchAll();
} catch (PDOException $e) {}

$pagesJson = json_encode($pages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$categoriesJson = json_encode($categories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$hashtagsJson = json_encode($hashtags, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Управление меню</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .form-row { margin-bottom: 15px; }
        .form-row label { display: inline-block; width: 150px; font-weight: bold; }
        .select-group { display: flex; gap: 10px; align-items: center; margin-top: 5px; }
        .select-group select, .select-group button { padding: 4px 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #2a3650; padding: 8px; text-align: left; }
        details summary { cursor: pointer; color: #60a5fa; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Редактор меню</h1>
    <?php if (isset($_GET['msg'])): ?>
        <p class="success"><?php echo htmlspecialchars($_GET['msg']); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    
    <h2>Добавить пункт</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="add">
        
        <div class="form-row">
            <label>Название:</label>
            <input type="text" name="title" required>
        </div>
        <div class="form-row">
            <label>URL:</label>
            <input type="text" name="url" id="add-url" value="/">
            <div class="select-group">
                <select id="add-select-type">
                    <option value="">-- Выбрать --</option>
                    <option value="page">Страница</option>
                    <option value="category">Категория</option>
                    <option value="hashtag">Хештег</option>
                </select>
                <select id="add-select-item" style="display:none;"></select>
                <button type="button" id="add-insert-btn" style="display:none;">Вставить</button>
            </div>
        </div>
        <div class="form-row">
            <label>Родитель:</label>
            <select name="parent_id"><?php foreach ($parents as $id=>$name) echo "<option value='$id'>$name</option>"; ?></select>
        </div>
        <div class="form-row">
            <label>Цель:</label>
            <select name="target"><option value="_self">_self</option><option value="_blank">_blank</option></select>
        </div>
        <div class="form-row">
            <label>Иконка:</label>
            <input type="text" name="icon" placeholder="fas fa-home">
        </div>
        <div class="form-row">
            <label>Порядок:</label>
            <input type="number" name="sort_order" value="0">
        </div>
        <button type="submit">Добавить</button>
    </form>

    <h2>Существующие пункты</h2>
    <table>
        <thead><tr><th>ID</th><th>Название</th><th>URL</th><th>Родитель</th><th>Порядок</th><th>Статус</th><th>Действия</th></tr></thead>
        <tbody>
        <?php foreach ($menuItems as $item): ?>
        <tr>
            <td><?php echo $item['id']; ?></td>
            <td><?php echo htmlspecialchars($item['title']); ?></td>
            <td><?php echo htmlspecialchars($item['url']); ?></td>
            <td><?php echo $item['parent_id'] ? ($parents[$item['parent_id']] ?? '—') : '—'; ?></td>
            <td><?php echo $item['sort_order']; ?></td>
            <td><?php echo $item['status'] ? 'Активен' : 'Скрыт'; ?></td>
            <td>
                <details>
                    <summary>✏️ Редактировать</summary>
                    <form method="post" style="margin-top:10px;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                        <div class="form-row"><label>Название:</label><input type="text" name="title" value="<?php echo htmlspecialchars($item['title']); ?>" size="30"></div>
                        <div class="form-row">
                            <label>URL:</label>
                            <input type="text" name="url" id="edit-url-<?php echo $item['id']; ?>" value="<?php echo htmlspecialchars($item['url']); ?>" size="50">
                            <div class="select-group">
                                <select class="edit-select-type" data-id="<?php echo $item['id']; ?>">
                                    <option value="">-- Выбрать --</option>
                                    <option value="page">Страница</option>
                                    <option value="category">Категория</option>
                                    <option value="hashtag">Хештег</option>
                                </select>
                                <select class="edit-select-item" data-id="<?php echo $item['id']; ?>" style="display:none;"></select>
                                <button type="button" class="edit-insert-btn" data-id="<?php echo $item['id']; ?>" style="display:none;">Вставить</button>
                            </div>
                        </div>
                        <div class="form-row"><label>Родитель:</label><select name="parent_id"><?php foreach ($parents as $id=>$name) echo "<option value='$id'".($id==$item['parent_id']?' selected':'').">$name</option>"; ?></select></div>
                        <div class="form-row"><label>Цель:</label><select name="target"><option <?php echo $item['target']=='_self'?'selected':''; ?>>_self</option><option <?php echo $item['target']=='_blank'?'selected':''; ?>>_blank</option></select></div>
                        <div class="form-row"><label>Иконка:</label><input type="text" name="icon" value="<?php echo htmlspecialchars($item['icon']); ?>" size="15"></div>
                        <div class="form-row"><label>Порядок:</label><input type="number" name="sort_order" value="<?php echo $item['sort_order']; ?>"></div>
                        <div class="form-row"><label>Статус:</label><select name="status"><option value="1" <?php echo $item['status']?'selected':''; ?>>Активен</option><option value="0" <?php echo !$item['status']?'selected':''; ?>>Скрыт</option></select></div>
                        <button type="submit">Сохранить</button>
                        <a href="menu.php">Отмена</a>
                    </form>
                </details>
                <form method="post" style="display:inline;" onsubmit="return confirm('Удалить?')">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                    <button type="submit">Удалить</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        const pages = <?php echo $pagesJson; ?>;
        const categories = <?php echo $categoriesJson; ?>;
        const hashtags = <?php echo $hashtagsJson; ?>;

        function populateSelect(selectElement, type) {
            selectElement.innerHTML = '<option value="">-- Выберите --</option>';
            let items = [];
            if (type === 'page') items = pages;
            else if (type === 'category') items = categories;
            else if (type === 'hashtag') items = hashtags;

            items.forEach(item => {
                const option = document.createElement('option');
                if (type === 'page') {
                    option.value = '/page/' + item.slug;
                    option.textContent = item.title;
                } else if (type === 'category') {
                    option.value = '/category/' + item.slug;
                    option.textContent = item.name;
                } else if (type === 'hashtag') {
                    option.value = '/search.php?q=' + encodeURIComponent(item.name);
                    option.textContent = '#' + item.name;
                }
                selectElement.appendChild(option);
            });
        }

        const addType = document.getElementById('add-select-type');
        const addItem = document.getElementById('add-select-item');
        const addBtn = document.getElementById('add-insert-btn');
        const addUrl = document.getElementById('add-url');
        if (addType) {
            addType.addEventListener('change', function() {
                const type = this.value;
                if (type) {
                    populateSelect(addItem, type);
                    addItem.style.display = 'inline-block';
                    addBtn.style.display = 'inline-block';
                } else {
                    addItem.style.display = 'none';
                    addBtn.style.display = 'none';
                    addItem.innerHTML = '';
                }
            });
            if (addBtn) {
                addBtn.addEventListener('click', function() {
                    const url = addItem.value;
                    if (url) addUrl.value = url;
                });
            }
        }

        document.querySelectorAll('.edit-select-type').forEach(select => {
            const id = select.dataset.id;
            const itemSelect = document.querySelector(`.edit-select-item[data-id="${id}"]`);
            const btn = document.querySelector(`.edit-insert-btn[data-id="${id}"]`);
            const urlInput = document.getElementById(`edit-url-${id}`);
            if (!itemSelect || !btn || !urlInput) return;

            select.addEventListener('change', function() {
                const type = this.value;
                if (type) {
                    populateSelect(itemSelect, type);
                    itemSelect.style.display = 'inline-block';
                    btn.style.display = 'inline-block';
                } else {
                    itemSelect.style.display = 'none';
                    btn.style.display = 'none';
                    itemSelect.innerHTML = '';
                }
            });
            btn.addEventListener('click', function() {
                const url = itemSelect.value;
                if (url) urlInput.value = url;
            });
        });
    </script>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>