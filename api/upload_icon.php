<?php
require_once '../includes/functions.php';
if (!canManagePosts()) { http_response_code(403); die('Unauthorized'); }

header('Content-Type: application/json');

$uploadDir = UPLOAD_DIR . 'icons/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// GET – получить список иконок
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $db = getDb();
    $stmt = $db->query("SELECT id, filepath, original_name FROM icon_library ORDER BY created_at DESC");
    $icons = $stmt->fetchAll();
    echo json_encode($icons);
    exit;
}

// POST – загрузить новую иконку
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['icon'])) {
    $file = $_FILES['icon'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Ошибка загрузки файла']);
        exit;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['error' => 'Недопустимый формат файла']);
        exit;
    }
    $newName = 'icon_' . uniqid() . '.' . $ext;
    $target = $uploadDir . $newName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        echo json_encode(['error' => 'Ошибка перемещения файла']);
        exit;
    }
    $iconUrl = SITE_URL . '/uploads/icons/' . $newName;
    $db = getDb();
    $stmt = $db->prepare("INSERT INTO icon_library (filename, filepath, original_name, file_size) VALUES (?, ?, ?, ?)");
    $stmt->execute([$newName, $iconUrl, $file['name'], $file['size']]);
    echo json_encode(['success' => true, 'url' => $iconUrl]);
    exit;
}

// Если не GET и не POST с файлом – ошибка
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);