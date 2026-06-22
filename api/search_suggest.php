<?php
/**
 * API для автодополнения поиска
 * Возвращает JSON с подсказками по заголовкам постов (и, опционально, категориям/тегам)
 */

require_once '../includes/functions.php';

// Разрешаем только GET-запросы
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$db = getDb();
// Ищем похожие заголовки постов (LIMIT 10)
$stmt = $db->prepare("SELECT title, slug FROM posts WHERE title LIKE ? AND status = 'published' ORDER BY title LIMIT 10");
$stmt->execute(['%' . $query . '%']);
$posts = $stmt->fetchAll();

// Можно также добавить подсказки из категорий и хештегов (опционально)
$categories = [];
$hashtags = [];
if (strlen($query) > 2) {
    $stmt = $db->prepare("SELECT name, slug FROM categories WHERE name LIKE ? LIMIT 5");
    $stmt->execute(['%' . $query . '%']);
    $categories = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT name FROM hashtags WHERE name LIKE ? LIMIT 5");
    $stmt->execute(['%' . $query . '%']);
    $hashtags = $stmt->fetchAll();
}

$suggestions = [];
foreach ($posts as $post) {
    $suggestions[] = [
        'type' => 'post',
        'text' => $post['title'],
        'url' => SITE_URL . '/post/' . $post['slug']
    ];
}
foreach ($categories as $cat) {
    $suggestions[] = [
        'type' => 'category',
        'text' => $cat['name'],
        'url' => SITE_URL . '/category/' . $cat['slug']
    ];
}
foreach ($hashtags as $tag) {
    $suggestions[] = [
        'type' => 'tag',
        'text' => '#' . $tag['name'],
        'url' => SITE_URL . '/search.php?q=' . urlencode($tag['name'])
    ];
}

header('Content-Type: application/json');
echo json_encode($suggestions);