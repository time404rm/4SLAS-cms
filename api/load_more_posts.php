<?php
require_once '../includes/functions.php';

$offset = (int)($_GET['offset'] ?? 0);
$limit = (int)($_GET['limit'] ?? 5);
$type = $_GET['type'] ?? '';
$slug = $_GET['slug'] ?? '';

$validTypes = ['home', 'category'];
if (!in_array($type, $validTypes)) {
    http_response_code(400);
    exit;
}

if ($type === 'category' && $slug) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    $categoryId = $stmt->fetchColumn();
    if (!$categoryId) {
        http_response_code(404);
        exit;
    }
    $sql = "SELECT p.*,
            u.username as author_name,
            (SELECT GROUP_CONCAT(c2.name SEPARATOR ',') 
             FROM post_categories pc2 
             JOIN categories c2 ON pc2.category_id = c2.id 
             WHERE pc2.post_id = p.id) as categories,
            (SELECT GROUP_CONCAT(h.name SEPARATOR ',') 
             FROM post_hashtags ph 
             JOIN hashtags h ON ph.hashtag_id = h.id 
             WHERE ph.post_id = p.id) as hashtags,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            INNER JOIN post_categories pc ON p.id = pc.post_id
            WHERE pc.category_id = ? AND p.status = 'published'
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$categoryId, $limit, $offset]);
    $posts = $stmt->fetchAll();
} else {
    // обычные посты (главная страница)
    $posts = getPosts($limit, $offset);
}

if (empty($posts)) {
    http_response_code(204);
    exit;
}
$totalPages = 0;
include '../templates/post_list.php';
?>