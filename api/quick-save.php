<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$type = $input['type'] ?? '';
$id = (int)($input['id'] ?? 0);
$title = trim($input['title'] ?? '');
$content = $input['content'] ?? '';
$metaTitle = trim($input['meta_title'] ?? '');
$metaDesc = trim($input['meta_description'] ?? '');
$metaKw = trim($input['meta_keywords'] ?? '');
$displayAuthor = trim($input['display_author'] ?? '');
$canonicalUrl = trim($input['canonical_url'] ?? '');

if (!$id || !$title) {
    echo json_encode(['success' => false, 'error' => 'Missing id or title']);
    exit;
}

$db = getDb();

// Auto-migrate pages table if needed
try {
    $db->exec("ALTER TABLE pages ADD COLUMN display_author VARCHAR(255) DEFAULT NULL AFTER meta_keywords");
} catch (PDOException $e) {}
try {
    $db->exec("ALTER TABLE pages ADD COLUMN canonical_url VARCHAR(500) DEFAULT NULL AFTER display_author");
} catch (PDOException $e) {}

if ($type === 'post') {
    $stmt = $db->prepare("SELECT slug FROM posts WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch();

    if (!$post) {
        echo json_encode(['success' => false, 'error' => 'Post not found']);
        exit;
    }

    $slug = $post['slug'];

    $db->prepare("UPDATE posts SET title=?, content=?, meta_title=?, meta_description=?, meta_keywords=?, display_author=?, canonical_url=? WHERE id=?")->execute([
        $title, $content, $metaTitle, $metaDesc, $metaKw, $displayAuthor, $canonicalUrl, $id
    ]);

    clearCache();
    echo json_encode(['success' => true, 'message' => 'Сохранено', 'url' => SITE_URL . '/post/' . $slug]);

} elseif ($type === 'page') {
    $stmt = $db->prepare("SELECT slug FROM pages WHERE id = ?");
    $stmt->execute([$id]);
    $page = $stmt->fetch();

    if (!$page) {
        echo json_encode(['success' => false, 'error' => 'Page not found']);
        exit;
    }

    $slug = $page['slug'];

    $db->prepare("UPDATE pages SET title=?, content=?, meta_title=?, meta_description=?, meta_keywords=?, display_author=?, canonical_url=? WHERE id=?")->execute([
        $title, $content, $metaTitle, $metaDesc, $metaKw, $displayAuthor, $canonicalUrl, $id
    ]);

    clearCache();
    echo json_encode(['success' => true, 'message' => 'Сохранено', 'url' => SITE_URL . '/page/' . $slug]);

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid type']);
}
