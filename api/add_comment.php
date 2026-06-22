<?php
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => __('login_required')]);
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
$parentId = (int)($_POST['parent_id'] ?? 0);
$content = trim($_POST['content'] ?? '');
$csrf = $_POST['csrf_token'] ?? '';

if (!verifyCsrfToken($csrf)) {
    echo json_encode(['error' => __('csrf_invalid')]);
    exit;
}
if (empty($content)) {
    echo json_encode(['error' => __('empty_comment')]);
    exit;
}

$db = getDb();
$stmt = $db->prepare("SELECT comments_enabled FROM posts WHERE id = ?");
$stmt->execute([$postId]);
if (!$stmt->fetchColumn()) {
    echo json_encode(['error' => __('comments_disabled')]);
    exit;
}

$moderation = getSetting('comment_moderation') ? 'pending' : 'approved';
$stmt = $db->prepare("INSERT INTO comments (post_id, parent_id, author_name, author_email, content, status) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$postId, $parentId, $_SESSION['username'], '', $content, $moderation]);
// ----- УВЕДОМЛЕНИЯ О НОВОМ КОММЕНТАРИИ -----
if (getSetting('notify_on_comment')) {
    $postTitle = getPostTitle($postId);
    $postSlug = getPostSlug($postId);
    $postUrl = SITE_URL . '/post/' . $postSlug;
    $author = $_SESSION['username'];
    $email = ''; // у вас нет email в этой форме

    $subject = 'Новый комментарий на сайте ' . getSetting('site_name');
    $message = "На пост \"$postTitle\" добавлен новый комментарий.\n\n";
    $message .= "Автор: $author\n";
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

$slug = getPostSlugForCache($postId);
if ($slug) clearCacheForUrl('/post/' . $slug);

echo json_encode(['success' => true, 'message' => $moderation === 'pending' ? __('comment_sent_moderation') : __('comment_added')]);