<?php
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => __('login_required')]);
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
if ($postId <= 0) {
    echo json_encode(['error' => __('invalid_post_id')]);
    exit;
}

if (addLike($postId, $_SESSION['user_id'])) {
    $db = getDb();
    $stmt = $db->prepare("SELECT likes_count FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $likes = $stmt->fetchColumn();
    echo json_encode(['success' => true, 'likes' => $likes]);
} else {
    echo json_encode(['error' => __('already_liked')]);
}