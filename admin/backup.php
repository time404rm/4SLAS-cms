<?php
require_once '../includes/functions.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

$csrf_token = generateCsrfToken();
$message = '';
$error = '';

// Создание бэкапа файлов
if (isset($_POST['backup_files']) && isset($_POST['csrf_token']) && verifyCsrfToken($_POST['csrf_token'])) {
    $result = backupFiles(); // функция из functions.php
    if ($result['success']) {
        $message = 'Бэкап файлов создан: ' . $result['file'];
    } else {
        $error = 'Ошибка: ' . $result['error'];
    }
}

// Создание бэкапа БД
if (isset($_POST['backup_db']) && isset($_POST['csrf_token']) && verifyCsrfToken($_POST['csrf_token'])) {
    $result = backupDatabase(); // функция из functions.php
    if ($result['success']) {
        $message = 'Бэкап базы данных создан: ' . $result['file'];
    } else {
        $error = 'Ошибка: ' . $result['error'];
    }
}

// Удаление бэкапа
if (isset($_GET['delete']) && isset($_GET['type']) && isset($_GET['csrf_token'])) {
    if (verifyCsrfToken($_GET['csrf_token'])) {
        deleteBackup($_GET['type'], $_GET['delete']);
        header('Location: backup.php?deleted=1');
        exit;
    }
}

// Скачивание бэкапа
if (isset($_GET['download']) && isset($_GET['type']) && isset($_GET['csrf_token'])) {
    if (verifyCsrfToken($_GET['csrf_token'])) {
        $file = getBackupPath($_GET['type']) . basename($_GET['download']);
        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        } else {
            $error = 'Файл не найден';
        }
    }
}

$cmsBackups = getBackupFiles('cms');
$sqlBackups = getBackupFiles('sql');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Резервное копирование</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .backup-actions { display: flex; gap: 20px; margin-bottom: 30px; }
        .backup-btn { background: #2563eb; padding: 10px 20px; border: none; border-radius: 6px; color: white; cursor: pointer; font-size: 1rem; }
        .backup-btn:hover { background: #1e40af; }
        .backup-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .backup-table th, .backup-table td { border: 1px solid #2a3650; padding: 8px; text-align: left; }
        .delete-link { color: #ef4444; }
        .download-link { color: #60a5fa; margin-right: 10px; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Резервное копирование</h1>
    
    <?php if ($message): ?><div class="success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="success">Бэкап удалён.</div><?php endif; ?>

    <div class="backup-actions">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <button type="submit" name="backup_files" class="backup-btn">📦 Создать бэкап файлов (ZIP)</button>
        </form>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <button type="submit" name="backup_db" class="backup-btn">🗄️ Создать бэкап базы данных (SQL.GZ)</button>
        </form>
    </div>

    <h2>Бэкапы файлов (CMS)</h2>
    <?php if (empty($cmsBackups)): ?>
        <p>Нет бэкапов файлов.</p>
    <?php else: ?>
        <table class="backup-table">
            <thead>
                <tr><th>Имя файла</th><th>Размер</th><th>Дата создания</th><th>Действия</th></tr>
            </thead>
            <tbody>
                <?php foreach ($cmsBackups as $backup): ?>
                <tr>
                    <td><?php echo htmlspecialchars($backup['name']); ?></td>
                    <td><?php echo round($backup['size'] / 1024, 2); ?> KB</td>
                    <td><?php echo date('d.m.Y H:i:s', $backup['mtime']); ?></td>
                    <td>
                        <a href="?download=<?php echo urlencode($backup['name']); ?>&type=cms&csrf_token=<?php echo $csrf_token; ?>" class="download-link">📥 Скачать</a>
                        <a href="?delete=<?php echo urlencode($backup['name']); ?>&type=cms&csrf_token=<?php echo $csrf_token; ?>" class="delete-link" onclick="return confirm('Удалить бэкап?')">🗑️ Удалить</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Бэкапы базы данных</h2>
    <?php if (empty($sqlBackups)): ?>
        <p>Нет бэкапов базы данных.</p>
    <?php else: ?>
        <table class="backup-table">
            <thead>
                <tr><th>Имя файла</th><th>Размер</th><th>Дата создания</th><th>Действия</th></tr>
            </thead>
            <tbody>
                <?php foreach ($sqlBackups as $backup): ?>
                <tr>
                    <td><?php echo htmlspecialchars($backup['name']); ?></td>
                    <td><?php echo round($backup['size'] / 1024, 2); ?> KB</td>
                    <td><?php echo date('d.m.Y H:i:s', $backup['mtime']); ?></td>
                    <td>
                        <a href="?download=<?php echo urlencode($backup['name']); ?>&type=sql&csrf_token=<?php echo $csrf_token; ?>" class="download-link">📥 Скачать</a>
                        <a href="?delete=<?php echo urlencode($backup['name']); ?>&type=sql&csrf_token=<?php echo $csrf_token; ?>" class="delete-link" onclick="return confirm('Удалить бэкап?')">🗑️ Удалить</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>