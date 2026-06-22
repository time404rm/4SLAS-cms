<?php
require_once '../includes/functions.php';
header('Content-Type: application/json');

$db = getDb();

$posts = $db->query("SELECT id, title, slug FROM posts WHERE status = 'published' ORDER BY created_at DESC")->fetchAll();
$pages = $db->query("SELECT id, title, slug FROM pages WHERE status = 'published' ORDER BY created_at DESC")->fetchAll();

$result = [];
foreach ($posts as $p) {
    $result[] = [
        'type' => 'post',
        'title' => $p['title'],
        'url' => SITE_URL . '/post/' . $p['slug']
    ];
}
foreach ($pages as $pg) {
    $result[] = [
        'type' => 'page',
        'title' => $pg['title'],
        'url' => SITE_URL . '/page/' . $pg['slug']
    ];
}

echo json_encode($result);