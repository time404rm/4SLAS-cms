<?php
/**
 * Добавление комментария для ГОСТЕВЫХ пользователей
 * Авторизованные используют api/add_comment.php
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/captcha.php';
header('Content-Type: application/json');

// Только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => __('method_not_allowed')]);
    exit;
}

// CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['error' => __('csrf_invalid')]);
    exit;
}

// Rate limiting
$ip = getClientIP();
cleanOldAttempts('comment_attempts', 15);
if (!checkRateLimit('comment_attempts', 3, 15, $ip)) {
    echo json_encode(['error' => __('comment_rate_limit')]);
    exit;
}

// Captcha
if (!verifyCaptcha($_POST['captcha'] ?? '')) {
    echo json_encode(['error' => __('captcha_failed')]);
    recordAttempt('comment_attempts', $ip);
    exit;
}

// Валидация данных
$postId = (int)($_POST['post_id'] ?? 0);
$parentId = (int)($_POST['parent_id'] ?? 0);
$authorName = trim($_POST['author_name'] ?? '');
$authorEmail = trim($_POST['author_email'] ?? '');
$content = trim($_POST['content'] ?? '');

if (!$postId || empty($content) || empty($authorName)) {
    echo json_encode(['error' => __('fill_required_fields')]);
    exit;
}

if (strlen($authorName) < 2 || strlen($authorName) > 50) {
    echo json_encode(['error' => __('invalid_name_length')]);
    exit;
}

if ($authorEmail && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => __('invalid_email')]);
    exit;
}

// Проверка поста
$db = getDb();
$stmt = $db->prepare("SELECT comments_enabled FROM posts WHERE id = ?");
$stmt->execute([$postId]);
if (!$stmt->fetchColumn()) {
    echo json_encode(['error' => __('comments_disabled')]);
    exit;
}

// Сохранение
$moderation = getSetting('comment_moderation') ? 'pending' : 'approved';
$stmt = $db->prepare("INSERT INTO comments (post_id, parent_id, author_name, author_email, content, status) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$postId, $parentId, $authorName, $authorEmail, $content, $moderation]);

// Уведомления
if (getSetting('notify_on_comment')) {
    $postTitle = getPostTitle($postId);
    $postSlug = getPostSlug($postId);
    $postUrl = SITE_URL . '/post/' . $postSlug;
    $subject = 'Новый комментарий на сайте ' . getSetting('site_name');
    $message = "На пост \"$postTitle\" добавлен новый комментарий.\n\n";
    $message .= "Автор: $authorName\n";
    $message .= "Текст:\n$content\n\n";
    if ($moderation === 'pending') {
        $message .= "Комментарий ожидает модерации.\n";
    }
    $message .= "Ссылка на пост: $postUrl\n";
    $emails = getCommentNotificationEmails($postId, true);
    foreach ($emails as $recipient) {
        sendEmail($recipient, $subject, $message);
    }
}

// Очистка кеша
$slug = getPostSlugForCache($postId);
if ($slug) clearCacheForUrl('/post/' . $slug);

echo json_encode([
    'success' => true,
    'message' => $moderation === 'pending' ? __('comment_sent_moderation') : __('comment_added')
]);
