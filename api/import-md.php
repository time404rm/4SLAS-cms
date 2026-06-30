<?php
/**
 * Импорт Markdown → пост
 * 
 * POST: multipart/form-data с файлом .md
 * Ответ: { success, post_id, url, title, message }
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/md-parser.php';
header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['md_file'])) {
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['md_file'];

// Проверка расширения
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'md') {
    echo json_encode(['error' => 'Only .md files allowed']);
    exit;
}

// Читаем содержимое
$content = file_get_contents($file['tmp_name']);
if (!$content) {
    echo json_encode(['error' => 'Failed to read file']);
    exit;
}

// Конвертируем MD → HTML
$result = mdParse($content);
$html = $result['html'];
$meta = $result['meta'];

// Определяем заголовок
$title = $meta['title'] ?? $meta['заголовок'] ?? pathinfo($file['name'], PATHINFO_FILENAME);
$description = $meta['description'] ?? $meta['desc'] ?? $meta['описание'] ?? '';
$keywords = $meta['keywords'] ?? $meta['tags'] ?? $meta['теги'] ?? '';
$slug = $meta['slug'] ?? mdSlugify($title);

// Проверка дубля по slug
$db = getDb();
$check = $db->prepare("SELECT id FROM posts WHERE slug = ?");
$check->execute([$slug]);
if ($check->fetch()) {
    $slug = $slug . '-' . time();
}

// Создаём пост
$authorId = $_SESSION['user_id'] ?? 1;
$stmt = $db->prepare("INSERT INTO posts (user_id, title, slug, content, meta_title, meta_description, meta_keywords, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'published', NOW())");
$stmt->execute([
    $authorId,
    $title,
    $slug,
    $html,
    $title,
    $description,
    $keywords
]);

$postId = $db->lastInsertId();
$postUrl = SITE_URL . '/post/' . $slug;

echo json_encode([
    'success' => true,
    'post_id' => $postId,
    'url' => $postUrl,
    'title' => $title,
    'message' => "Пост «{$title}» создан! <a href=\"{$postUrl}\" target=\"_blank\">Открыть</a>"
]);
