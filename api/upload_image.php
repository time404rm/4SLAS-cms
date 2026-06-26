<?php
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
$pageId = (int)($_POST['page_id'] ?? 0);
$db = getDb();

if ($postId > 0) {
    $stmt = $db->prepare("SELECT slug FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $slug = $stmt->fetchColumn();
    if (!$slug) {
        echo json_encode(['error' => 'Post not found']);
        exit;
    }
    $type = 'post';
} elseif ($pageId > 0) {
    $stmt = $db->prepare("SELECT slug FROM pages WHERE id = ?");
    $stmt->execute([$pageId]);
    $slug = $stmt->fetchColumn();
    if (!$slug) {
        echo json_encode(['error' => 'Page not found']);
        exit;
    }
    $type = 'page';
} else {
    // Нет ID поста/страницы — сохраняем во временную директорию редактора
    $type = 'editor';
    $slug = 'unsaved';
}

if (empty($_FILES['image'])) {
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['image'];
$result = uploadEditorImage($file, $type, $slug);
if ($result) {
    echo json_encode(['success' => true, 'url' => SITE_URL . $result]);
} else {
    echo json_encode(['error' => 'Upload failed']);
}
