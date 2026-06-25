<?php
// 404 Page — используется через header/footer шаблоны
http_response_code(404);
$pageTitle = '404 — Страница не найдена';
$pageDescription = 'Запрашиваемая страница не найдена';
$is_404 = true;

// Логирование 404
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

$recentPosts = getPosts(10, 0);
$allTags = getAllTags();

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
.error-social {
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
}
.error-social a {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    text-decoration: none;
    font-size: 12px;
    font-weight: bold;
    transition: opacity .2s;
}
.error-social a:hover { opacity: .8; }
.error-social .vk { background: #4a76a8; }
.error-social .tg { background: #0088cc; }
.error-social .ok { background: #ee8208; }
.error-social .tw { background: #1a1a1a; }
.error-social .wa { background: #25d366; }
.error-social .fb { background: #4267b2; }
.error-social .li { background: #0077b5; }
.error-social .em { background: #ea4335; }

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

@media (max-width: 900px) {
    .error-layout { grid-template-columns: 1fr; }
    .error-image-wrap { height: 250px; }
    .error-robot { font-size: 80px; }
}
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

            <div class="error-social">
                <a href="#" class="vk" title="ВКонтакте">VK</a>
                <a href="#" class="tg" title="Telegram">TG</a>
                <a href="#" class="ok" title="Одноклассники">OK</a>
                <a href="#" class="tw" title="Twitter">X</a>
                <a href="#" class="wa" title="WhatsApp">WA</a>
                <a href="#" class="fb" title="Facebook">FB</a>
                <a href="#" class="li" title="LinkedIn">in</a>
                <a href="#" class="em" title="Email">@</a>
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
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
