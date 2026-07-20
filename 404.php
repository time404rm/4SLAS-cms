<?php
// 404 Page — используется через header/footer шаблоны
http_response_code(404);
$pageTitle = '404 — Страница не найдена';
$pageDescription = 'Запрашиваемая страница не найдена';
$is_404 = true;

require_once __DIR__ . '/includes/captcha.php';

$db = getDb();
$db->exec("CREATE TABLE IF NOT EXISTS log_404 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(500) NOT NULL,
    referer VARCHAR(500) DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_url (url(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$stmt = $db->prepare("INSERT INTO log_404 (url, referer, ip, user_agent) VALUES (?, ?, ?, ?)");
$stmt->execute([
    $_SERVER['REQUEST_URI'] ?? '',
    $_SERVER['HTTP_REFERER'] ?? '',
    $_SERVER['REMOTE_ADDR'] ?? '',
    mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
]);

$commentError = '';
$commentOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_404'])) {
    $ip = getClientIP();
    cleanOldAttempts('comment_attempts', 15);

    if (isHoneypotFilled()) {
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    if (!checkRateLimit('comment_attempts', 3, 15, $ip)) {
        $commentError = 'Слишком много комментариев. Попробуйте через 15 минут.';
    } else {
        $captchaAnswer = $_POST['captcha'] ?? '';
        if (!verifyCaptcha($captchaAnswer)) {
            $commentError = __('captcha_failed');
            recordAttempt('comment_attempts', $ip);
        } else {
            $author_name = trim($_POST['author_name'] ?? '');
            $author_email = trim($_POST['author_email'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if (empty($author_name) || empty($content)) {
                $commentError = 'Имя и комментарий обязательны';
            } elseif ($author_email && !filter_var($author_email, FILTER_VALIDATE_EMAIL)) {
                $commentError = 'Некорректный email';
            } else {
                $stmt = $db->prepare("INSERT INTO comments (post_id, parent_id, author_name, author_email, content, status) VALUES (0, 0, ?, ?, ?, 'pending')");
                $stmt->execute([$author_name, $author_email ?: null, $content]);
                clearCaptcha();
                $commentOk = true;
            }
        }
    }
}

$recentPosts = getPosts(10, 0);
$allTags = getAllTags();
$comments404 = $db->query("SELECT * FROM comments WHERE post_id = 0 AND status = 'approved' ORDER BY created_at DESC LIMIT 30")->fetchAll();
$captchaQuestion = generateCaptcha();

include __DIR__ . '/templates/header.php';
?>

<style>
.error-404-page {
    background: #faf5f5;
    margin: -20px;
    padding: 20px;
    min-height: 80vh;
}
.error-breadcrumb {
    max-width: 1200px;
    margin: 0 auto 30px;
    font-size: 11px;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.error-breadcrumb a {
    color: #666;
    text-decoration: none;
}
.error-breadcrumb a:hover { color: #000; }
.error-layout {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 40px;
}
.error-main { text-align: center; }
.error-code-badge {
    display: inline-block;
    background: #e63946;
    color: #fff;
    padding: 4px 12px;
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 20px;
}
.error-title {
    font-size: 28px;
    font-weight: bold;
    margin: 0 0 10px;
    color: #1a1a1a;
}
.error-subtitle {
    font-size: 20px;
    color: #333;
    margin: 0 0 30px;
}
.error-image-wrap {
    width: 100%;
    max-width: 600px;
    height: 350px;
    margin: 0 auto 30px;
    border-radius: 8px;
    overflow: hidden;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
.error-robot {
    font-size: 120px;
    line-height: 1;
    filter: grayscale(0.3);
    animation: robotFloat 3s ease-in-out infinite;
}
@keyframes robotFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.error-text {
    font-size: 16px;
    color: #333;
    line-height: 1.6;
    margin: 0 0 30px;
}
.error-text strong { display: block; margin-bottom: 5px; }

.error-sidebar {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05);
    align-self: start;
}
.error-sidebar h3 {
    font-size: 18px;
    margin: 0 0 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #4a76a8;
    color: #1a1a1a;
}
.error-sidebar ul {
    list-style: none;
    padding: 0;
    margin: 0 0 30px;
}
.error-sidebar li { margin-bottom: 8px; }
.error-sidebar a {
    color: #4a76a8;
    text-decoration: none;
    font-size: 14px;
    line-height: 1.4;
}
.error-sidebar a:hover {
    text-decoration: underline;
    color: #1a1a1a;
}
.error-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.error-tags a {
    background: #f0f4f8;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 13px;
    color: #4a76a8;
}
.error-tags a:hover {
    background: #4a76a8;
    color: #fff;
    text-decoration: none;
}

.error-comments {
    max-width: 900px;
    margin: 40px auto 0;
    text-align: left;
}
.error-comments h3 {
    font-size: 20px;
    margin-bottom: 15px;
    color: #1a1a1a;
    border-bottom: 1px solid #e5e5e5;
    padding-bottom: 10px;
}
.error-comment-item {
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
}
.error-comment-item .comment-name {
    font-weight: bold;
    font-size: 14px;
    color: #4a76a8;
}
.error-comment-item .comment-date {
    font-size: 11px;
    color: #999;
    margin-left: 10px;
}
.error-comment-item .comment-text {
    margin-top: 8px;
    font-size: 14px;
    color: #444;
    line-height: 1.5;
}
.error-comment-form {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    max-width: 900px;
    margin: 20px auto 0;
    text-align: left;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}
.error-comment-form input,
.error-comment-form textarea {
    width: 100%;
    padding: 10px;
    margin-bottom: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-family: inherit;
    font-size: 14px;
}
.error-comment-form textarea {
    min-height: 100px;
    resize: vertical;
}
.error-comment-form .captcha-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}
.error-comment-form .captcha-row .captcha-display {
    font-size: 14px;
    font-weight: bold;
    color: #333;
    background: #f0f4f8;
    padding: 6px 12px;
    border-radius: 6px;
}
.error-comment-form button {
    background: #4a76a8;
    color: #fff;
    border: none;
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: background .2s;
}
.error-comment-form button:hover { background: #3a6390; }
.error-comment-ok {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    text-align: center;
}
.error-comment-error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 12px;
    font-size: 14px;
}

@media (max-width: 900px) {
    .error-layout { grid-template-columns: 1fr; }
    .error-image-wrap { height: 250px; }
    .error-robot { font-size: 80px; }
}

/* Тёмная тема */
[data-theme="dark"] .error-404-page { background: #0f1422; }
[data-theme="dark"] .error-breadcrumb,
[data-theme="dark"] .error-breadcrumb a { color: #64748b; }
[data-theme="dark"] .error-breadcrumb a:hover { color: #e2e8f0; }
[data-theme="dark"] .error-title { color: #e2e8f0; }
[data-theme="dark"] .error-subtitle { color: #94a3b8; }
[data-theme="dark"] .error-text { color: #94a3b8; }
[data-theme="dark"] .error-sidebar { background: #1a2332; box-shadow: 0 2px 10px rgba(0,0,0,.2); }
[data-theme="dark"] .error-sidebar h3 { color: #e2e8f0; border-color: #2a3650; }
[data-theme="dark"] .error-sidebar a { color: #60a5fa; }
[data-theme="dark"] .error-sidebar a:hover { color: #93c5fd; }
[data-theme="dark"] .error-tags a { background: #2a3650; color: #60a5fa; }
[data-theme="dark"] .error-tags a:hover { background: #60a5fa; color: #0f1422; }
[data-theme="dark"] .error-comments h3 { color: #e2e8f0; border-color: #2a3650; }
[data-theme="dark"] .error-comment-item { background: #1a2332; box-shadow: none; }
[data-theme="dark"] .error-comment-item .comment-name { color: #60a5fa; }
[data-theme="dark"] .error-comment-item .comment-date { color: #64748b; }
[data-theme="dark"] .error-comment-item .comment-text { color: #cbd5e1; }
[data-theme="dark"] .error-comment-form { background: #1a2332; box-shadow: none; }
[data-theme="dark"] .error-comment-form input,
[data-theme="dark"] .error-comment-form textarea { background: #0f1422; border-color: #2a3650; color: #e2e8f0; }
[data-theme="dark"] .error-comment-form .captcha-row .captcha-display { color: #e2e8f0; background: #2a3650; }
[data-theme="dark"] .error-comment-ok { background: #166534; color: #86efac; }
[data-theme="dark"] .error-comment-error { background: #7f1a1a; color: #fca5a5; }
</style>

<div class="error-404-page">
    <div class="error-breadcrumb">
        Вы здесь: <a href="<?php echo SITE_URL; ?>">Главная</a>
    </div>

    <div class="error-layout">
        <div class="error-main">
            <div class="error-code-badge">Код 404</div>
            <h1 class="error-title">УПС-С-С!</h1>
            <p class="error-subtitle">Такой информации не нашёл.</p>

            <div class="error-image-wrap">
                <div class="error-robot">🤖</div>
            </div>

            <div class="error-text">
                <strong>Возможно материал переехал в другой раздел.</strong>
                Или я сам накосячил с ссылкой)))<br>
                Напишите об этом в комментариях, проверю, поправлю))
            </div>
        </div>

        <aside class="error-sidebar">
            <h3>#из последних</h3>
            <ul>
                <?php foreach ($recentPosts as $p): ?>
                <li><a href="<?php echo SITE_URL; ?>/post/<?php echo h($p['slug']); ?>"><?php echo h($p['title']); ?></a></li>
                <?php endforeach; ?>
            </ul>

            <h3>#почитать про</h3>
            <div class="error-tags">
                <?php foreach (array_slice($allTags, 0, 20) as $tag): ?>
                <a href="<?php echo SITE_URL; ?>/search.php?q=<?php echo urlencode($tag['name']); ?>"><?php echo h($tag['name']); ?></a>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>

    <!-- Комментарии к 404 -->
    <?php if (!empty($comments404)): ?>
    <div class="error-comments">
        <h3>💬 Сообщения о битых ссылках</h3>
        <?php foreach ($comments404 as $c): ?>
        <div class="error-comment-item">
            <span class="comment-name"><?php echo h($c['author_name']); ?></span>
            <span class="comment-date"><?php echo date('d.m.Y H:i', strtotime($c['created_at'])); ?></span>
            <div class="comment-text"><?php echo nl2br(h($c['content'])); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="error-comment-form">
        <?php if ($commentOk): ?>
            <div class="error-comment-ok">✅ Спасибо! Комментарий отправлен на модерацию.</div>
        <?php else: ?>
            <?php if ($commentError): ?>
                <div class="error-comment-error"><?php echo h($commentError); ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="comment_404" value="1">
                <?php echo generateHoneypot(); ?>
                <input type="text" name="author_name" placeholder="Ваше имя" required value="<?php echo h($_POST['author_name'] ?? ''); ?>">
                <input type="email" name="author_email" placeholder="Email (необязательно)" value="<?php echo h($_POST['author_email'] ?? ''); ?>">
                <textarea name="content" placeholder="Опишите, что искали..." required><?php echo h($_POST['content'] ?? ''); ?></textarea>
                <div class="captcha-row">
                    <span class="captcha-display"><?php echo $captchaQuestion; ?></span>
                    <input type="text" name="captcha" placeholder="Ответ" required autocomplete="off" style="width:auto;flex:1;">
                </div>
                <button type="submit">Отправить</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
