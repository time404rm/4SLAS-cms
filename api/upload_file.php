<?php
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Разрешённые расширения
$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', '7z'];
// Максимальный размер 20MB
$maxSize = 20 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Проверяем авторизацию (только редакторы и админы)
if (!canManagePosts()) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Файл не загружен']);
    exit;
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExtensions)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый тип файла']);
    exit;
}
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'Файл слишком большой (макс. 20MB)']);
    exit;
}

$uploadDir = UPLOAD_DIR . 'files/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$newName = uniqid() . '.' . $ext;
$targetPath = $uploadDir . $newName;
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $fileUrl = SITE_URL . '/uploads/files/' . $newName;
    echo json_encode(['success' => true, 'url' => $fileUrl, 'filename' => $file['name']]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при сохранении файла']);
}