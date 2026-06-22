<?php
require_once '../includes/functions.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

$db = getDb();
$csrf_token = generateCsrfToken();
$message = '';
$error = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'CSRF токен неверен';
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        
        if ($action === 'delete' && $id) {
            if (deleteUser($id)) {
                $message = 'Пользователь удалён';
            } else {
                $error = 'Не удалось удалить пользователя (возможно, вы пытаетесь удалить себя)';
            }
        } elseif ($action === 'change_role' && $id && isset($_POST['role'])) {
            $role = $_POST['role'];
            if (updateUserRole($id, $role)) {
                $message = 'Роль пользователя изменена';
            } else {
                $error = 'Ошибка изменения роли';
            }
        } elseif ($action === 'reset_password' && $id) {
            $newPassword = bin2hex(random_bytes(6)); // 12 символов
            $user = getUserById($id);
            if ($user && updateUserPassword($id, $newPassword)) {
                sendPasswordResetEmail($user['email'], $newPassword);
                $message = "Новый пароль отправлен на email пользователя";
            } else {
                $error = 'Ошибка сброса пароля';
            }
        } elseif ($action === 'change_username' && $id && isset($_POST['username'])) {
            $newUsername = trim($_POST['username']);
            if (strlen($newUsername) < 3) {
                $error = 'Имя пользователя слишком короткое (минимум 3 символа)';
            } elseif (updateUserName($id, $newUsername)) {
                $message = 'Имя пользователя изменено';
                // Если редактируем самого себя – обновляем сессию
                if ($id == $_SESSION['user_id']) {
                    $_SESSION['username'] = $newUsername;
                }
            } else {
                $error = 'Имя пользователя уже занято или не удалось обновить';
            }
        } elseif ($action === 'change_email' && $id && isset($_POST['email'])) {
            $newEmail = trim($_POST['email']);
            if (updateUserEmail($id, $newEmail)) {
                $message = 'Email пользователя изменён';
            } else {
                $error = 'Email уже занят или неверный формат';
            }
        }
    }
}

$users = getAllUsers();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Управление пользователями</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .user-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .user-table th, .user-table td { border: 1px solid #2a3650; padding: 8px; text-align: left; vertical-align: top; }
        .user-table th { background: #1e2a3e; }
        .actions { display: flex; gap: 5px; flex-wrap: wrap; }
        .btn { background: #2563eb; border: none; padding: 4px 8px; color: white; border-radius: 4px; cursor: pointer; }
        .btn-danger { background: #7f1a1a; }
        .btn-small { font-size: 0.8rem; }
        form { display: inline; }
        .inline-form { display: inline-flex; gap: 5px; align-items: center; }
        .inline-form input { padding: 4px; margin-right: 5px; width: auto; }
        .inline-form button { padding: 2px 6px; font-size: 0.7rem; }
        .edit-field { margin-bottom: 5px; }
        td:first-child { white-space: nowrap; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Управление пользователями</h1>
    
    <?php if ($message): ?><div class="success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    
    <table class="user-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Имя пользователя</th>
                <th>Email</th>
                <th>Роль</th>
                <th>Дата регистрации</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <!-- Имя пользователя с inline-редактированием -->
                <td>
                    <div class="edit-field">
                        <span class="current-username" id="username-<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></span>
                        <form method="post" class="inline-form" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="change_username">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" size="12" placeholder="новое имя">
                            <button type="submit" class="btn btn-small">✏️</button>
                        </form>
                    </div>
                 </td>
                <!-- Email с  -->
                <td>
                    <div class="edit-field">
                        <span class="current-email" id="email-<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['email']); ?></span>
                      <?php if(false): ?> <form method="post" class="inline-form" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="change_email">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" size="15" placeholder="новый email">
                            <button type="submit" class="btn btn-small">✏️</button>
                        </form> <?php endif; ?>
                    </div>
                </td>
                <!-- Роль (уже была) -->
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="change_role">
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <select name="role">
                            <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>Пользователь</option>
                            <option value="editor" <?php echo $user['role'] == 'editor' ? 'selected' : ''; ?>>Редактор</option>
                            <option value="moderator" <?php echo $user['role'] == 'moderator' ? 'selected' : ''; ?>>Модератор</option>
                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Администратор</option>
                        </select>
                        <button type="submit" class="btn btn-small">Изменить</button>
                    </form>
                </td>
                <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                <td class="actions">
                    <form method="post" onsubmit="return confirm('Сбросить пароль? Новый пароль будет отправлен на email.');">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="btn btn-small">🔑 Сбросить пароль</button>
                    </form>
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                    <form method="post" onsubmit="return confirm('Удалить пользователя?');">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-small">🗑️ Удалить</button>
                    </form>
                    <?php else: ?>
                    <span class="btn-small" style="color: #888;">Вы (нельзя удалить)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>