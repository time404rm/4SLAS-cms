<?php
require_once '../includes/functions.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

$db = getDb();
$csrf_token = generateCsrfToken();
$message = '';
$error = '';

// Обработка добавления/редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_redirect'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF failed');
    $id = (int)($_POST['id'] ?? 0);
    $old_url = trim($_POST['old_url']);
    $new_url = trim($_POST['new_url']);
    $type = $_POST['type'] === '302' ? '302' : '301';

    // Убираем домен и протокол из старого URL (сохраняем относительный путь, начинающийся с /)
    // Если ввели полную ссылку, оставляем только путь
    $old_url = preg_replace('#^https?://[^/]+#', '', $old_url);
    $new_url = preg_replace('#^https?://[^/]+#', '', $new_url);
    if (empty($old_url) || empty($new_url)) {
        $error = 'Заполните оба поля';
    } else {
        if ($id) {
            $stmt = $db->prepare("UPDATE redirects SET old_url=?, new_url=?, type=? WHERE id=?");
            $stmt->execute([$old_url, $new_url, $type, $id]);
            $message = 'Редирект обновлён';
        } else {
            // Проверяем, есть ли уже редирект для этого URL
            $check = $db->prepare("SELECT id, new_url, type FROM redirects WHERE old_url = ?");
            $check->execute([$old_url]);
            $existing = $check->fetch();

            if ($existing) {
                // Если есть — обновляем существующий
                $stmt = $db->prepare("UPDATE redirects SET new_url=?, type=? WHERE id=?");
                $stmt->execute([$new_url, $type, $existing['id']]);
                $message = 'Редирект обновлён (существующий)';
            } else {
                $stmt = $db->prepare("INSERT INTO redirects (old_url, new_url, type) VALUES (?, ?, ?)");
                $stmt->execute([$old_url, $new_url, $type]);
                $message = 'Редирект добавлен';
            }
        }
        clearCache();
        header('Location: redirect.php?msg=' . urlencode($message));
        exit;
    }
}

// Удаление редиректа
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    if (!verifyCsrfToken($_GET['csrf_token'])) die('CSRF failed');
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM redirects WHERE id = ?")->execute([$id]);
    clearCache();
    header('Location: redirect.php?msg=deleted');
    exit;
}

// Получение списка редиректов
$redirects = $db->query("SELECT * FROM redirects ORDER BY old_url")->fetchAll();

// Для удобного выбора нового поста из списка
$posts = $db->query("SELECT id, title, slug FROM posts WHERE status = 'published' ORDER BY created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Управление редиректами</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"] { width: 100%; max-width: 500px; }
        select { min-width: 150px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #2a3650; padding: 8px; text-align: left; }
        .actions { display: flex; gap: 5px; }
        .btn-small { padding: 2px 6px; font-size: 0.8rem; }
        .success { background: #1b5e3f; padding: 10px; border-radius: 4px; }
        .error { background: #7f1a1a; padding: 10px; border-radius: 4px; }
    </style>
    <script>
        // Функция для вставки URL выбранного поста в поле new_url
        function setPostUrl(postSlug) {
            document.getElementById('new_url').value = '/post/' + postSlug;
        }
        // Загружать список постов в select для быстрого выбора
        document.addEventListener('DOMContentLoaded', function() {
            const postSelect = document.getElementById('post_select');
            if (postSelect) {
                postSelect.addEventListener('change', function() {
                    const slug = this.value;
                    if (slug) setPostUrl(slug);
                });
            }
        });
    </script>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Управление редиректами (старые ссылки → новые)</h1>

    <?php if (isset($_GET['msg'])): ?>
        <div class="success"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <h2>Добавить / редактировать редирект</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="save_redirect" value="1">
        <input type="hidden" name="id" value="0" id="redirect_id">

        <div class="form-group">
            <label>Старый URL (относительный, начинается с /)</label>
            <input type="text" name="old_url" id="old_url" placeholder="/likbez/podklyuchenie-k-udalennomu-rabochemu-stolu-v-windows" required>
            <small>Вставляйте путь от корня сайта, например /likbez/... . Домен будет отброшен.</small>
        </div>

        <div class="form-group">
            <label>Новый URL (относительный)</label>
            <input type="text" name="new_url" id="new_url" placeholder="/post/..." required>
            <small>Или выберите пост:</small><br>
            <select id="post_select">
                <option value="">-- Выберите пост --</option>
                <?php foreach ($posts as $post): ?>
                    <option value="<?php echo htmlspecialchars($post['slug']); ?>"><?php echo htmlspecialchars($post['title']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Тип редиректа</label>
            <select name="type">
                <option value="301">301 (постоянный, SEO)</option>
                <option value="302">302 (временный)</option>
            </select>
        </div>

        <button type="submit">Сохранить редирект</button>
        <button type="button" onclick="document.getElementById('redirect_id').value=0; document.getElementById('old_url').value=''; document.getElementById('new_url').value=''; document.querySelector('select[name=type]').value='301';">Новый</button>
    </form>

    <h2>Существующие редиректы</h2>
    <?php if (empty($redirects)): ?>
        <p>Редиректов пока нет.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Старый URL</th><th>Новый URL</th><th>Тип</th><th>Действия</th></tr>
            </thead>
            <tbody>
                <?php foreach ($redirects as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['old_url']); ?></td>
                    <td><?php echo htmlspecialchars($r['new_url']); ?></td>
                    <td><?php echo $r['type']; ?></td>
                    <td class="actions">
                        <button onclick="editRedirect(<?php echo $r['id']; ?>, '<?php echo addslashes($r['old_url']); ?>', '<?php echo addslashes($r['new_url']); ?>', '<?php echo $r['type']; ?>')">✏️ Редактировать</button>
                        <a href="?delete=<?php echo $r['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" onclick="return confirm('Удалить редирект?')" class="btn-small">🗑️ Удалить</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <script>
        function editRedirect(id, oldUrl, newUrl, type) {
            document.getElementById('redirect_id').value = id;
            document.getElementById('old_url').value = oldUrl;
            document.getElementById('new_url').value = newUrl;
            document.querySelector('select[name=type]').value = type;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    </script>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>