<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/captcha.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/faq-helper.php';
require_once __DIR__ . '/includes/toc-helper.php';
require_once __DIR__ . '/includes/howto-helper.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$slug = $_GET['slug'] ?? '';
$post = getPostBySlug($slug);
if (!$post) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// SEO
$seo = getSeoData('post', $post['id']);
$pageTitle = !empty($post['meta_title']) ? $post['meta_title'] : $post['title'];
$pageDescription = !empty($post['meta_description']) ? $post['meta_description'] : mb_substr(strip_tags($post['content']), 0, 160);
$pageKeywords = !empty($post['meta_keywords']) ? $post['meta_keywords'] : implode(',', array_column($post['hashtags'], 'name'));
$ogImage = (!empty($seo['og_image']) ? $seo['og_image'] : $post['intro_image']);
$canonicalUrl = SITE_URL . '/post/' . $post['slug'];

// Генерируем капчу один раз
$captchaQuestion = generateCaptcha();

include __DIR__ . '/templates/header.php';
?>

<article>
    <div class="postlisttitle">
            <?php
                $logo = getSetting('site_logo');
                if ($logo && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo)): ?>
                <img src="<?php echo SITE_URL . $logo; ?>" alt="<?php echo $siteName; ?>" class="postlist-logo">
                <?php endif; ?>
            <div class="title-autor">
                <span class="post-author"><h1 class="postlist-title"><?php echo h($post['title']); ?></h1></span>
                <span class="post-author">@<?php echo !empty($post['author_name']) ? h($post['author_name']) : __('unknown_author'); ?></span>
        </div>
    </div>    
        <?php if (!empty($post['video_url'])): ?>
    <div class="post-video">
        <?php echo getVideoEmbed($post['video_url']); ?>
    </div>
    <?php elseif ($post['intro_image']): ?>
    <img src="<?php echo SITE_URL; ?>/uploads/posts/<?php echo h($post['intro_image']); ?>" alt="<?php echo h($post['title']); ?>" class="featured-image" loading="lazy">
    <?php endif; ?>
    <div class="post-meta">
        <span class="date"><?php echo date('d.m.Y', strtotime($post['created_at'])); ?></span>
        <span class="likes">&#9825; <span id="likes-count"><?php echo $post['likes_count']; ?></span></span>
    </div>
    <div class="content">
        <?php
        $content = wrapImagesWithLightbox($post['content'], $post['id']);
        $content = activateHashtags($content);
        $content = maskEmails($content);
        $content = faqParse($content);
        $content = howtoParse($content);
        $content = tocGenerate($content);
        echo $content;
        ?>
    </div>
    <?php
    $gallery = getGalleryImages($post['id']);
    if (!empty($gallery)): ?>
    <div class="post-gallery">
        <div class="gallery-grid">
            <?php foreach ($gallery as $img): ?>
               <a href="<?php echo SITE_URL; ?>/uploads/gallery/<?php echo h($img['image']); ?>" data-lightbox="post-gallery" data-title="<?php echo h($post['title']); ?>">
    <img src="<?php echo SITE_URL; ?>/uploads/gallery/<?php echo h($img['image']); ?>" alt="<?php echo h($post['title']); ?>" loading="lazy">
</a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    
   <div class="categories-tags">
    <?php 
    // Если пришёл массив — превращаем в строку через запятую, иначе используем как есть
    if (is_array($post['hashtags'] ?? null)) {
        // Старый формат: массив с ключами 'name'
        $names = array_column($post['hashtags'], 'name');
        $tagsString = implode(',', $names);
    } else {
        $tagsString = (string)($post['hashtags'] ?? '');
    }

    $tags = !empty($tagsString) ? explode(',', $tagsString) : [];
    foreach ($tags as $tag):
        $tag = trim($tag);
        if ($tag !== ''): ?>
            <a href="<?php echo SITE_URL; ?>/search.php?q=<?php echo urlencode($tag); ?>" class="hashtag-link">#<?php echo h($tag); ?></a>
        <?php endif;
    endforeach; ?>
</div>
</article>

<?php $prevPost = getPrevPost($post['id'], $post['created_at']); $nextPost = getNextPost($post['id'], $post['created_at']); if ($prevPost || $nextPost): ?>
<div class="post-nav">
    <div class="post-nav-prev"><?php if ($prevPost): ?><a href="<?php echo SITE_URL; ?>/post/<?php echo h($prevPost['slug']); ?>">← <?php echo h(mb_substr($prevPost['title'], 0, 50)); ?></a><?php endif; ?></div>
    <div class="post-nav-next"><?php if ($nextPost): ?><a href="<?php echo SITE_URL; ?>/post/<?php echo h($nextPost['slug']); ?>"><?php echo h(mb_substr($nextPost['title'], 0, 50)); ?> →</a><?php endif; ?></div>
</div>
<style>
.post-nav { display: flex; justify-content: space-between; gap: 16px; margin: 20px 0; padding: 16px 0; border-top: 1px solid #2a3650; border-bottom: 1px solid #2a3650; }
.post-nav a { color: #4a8cff; text-decoration: none; font-size: 14px; }
.post-nav a:hover { text-decoration: underline; }
.post-nav-prev { text-align: left; flex: 1; }
.post-nav-next { text-align: right; flex: 1; }
</style>
<?php endif; ?>

<div class="socialcontent">
<div class="likes-section">
        <?php if (isset($_SESSION['user_id'])): ?>
            <button class="like-btn" data-post-id="<?php echo $post['id']; ?>">&#9825; <?php echo __('like'); ?></button>
        <?php else: ?>
            <button class="like-btn" disabled><?php echo __('like_login'); ?></button>
        <?php endif; ?>
    </div>
    <!-- Кнопка "Поделиться" -->
<div class="share-container">
    <button class="share-btn" data-url="<?php echo SITE_URL; ?>/post/<?php echo h($post['slug']); ?>" data-title="<?php echo h($post['title']); ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px; vertical-align: middle;">
            <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M16 6l-4-4-4 4M12 2v13"/>
        </svg>
        Поделиться
    </button>
    <div class="share-dropdown">
        <div class="share-dropdown-header">
            <span>Поделиться через</span>
            <button class="share-close">&times;</button>
        </div>
        <ul class="share-list">
            <li><a href="#" class="share-link copy-link">📋 Копировать ссылку</a></li>
            <li><a href="#" class="share-link telegram" data-share="telegram">📱 Telegram</a></li>
            <li><a href="#" class="share-link vk" data-share="vk">💙 VK</a></li>
            <li><a href="#" class="share-link whatsapp" data-share="whatsapp">💚 WhatsApp</a></li>
            <li><a href="#" class="share-link twitter" data-share="twitter">🐦 X (Twitter)</a></li>
            <li><a href="#" class="share-link ok" data-share="ok">👥 Одноклассники</a></li>
        </ul>
    </div>
</div>
        </div>
        <?php $yoomoneyBill = getSetting('yoomoney_bill_number'); ?>
        <?php if ($yoomoneyBill): ?>
        <div class="donut-button">
        <?php echo __('donut_text'); ?>
        </div>
        <iframe src="https://yoomoney.ru/quickpay/fundraise/button?billNumber=<?php echo urlencode($yoomoneyBill); ?>&" width="330" height="55" frameborder="0" allowtransparency="true" scrolling="no"></iframe>
        <?php endif; ?>

<?php
$relatedPosts = getRelatedPostsByTags($post['id'], 3);
if (!empty($relatedPosts)):
?>
<div class="related-posts">
    <h3><?php echo __('related_posts'); ?></h3>
    <div class="related-posts-list">
        <?php foreach ($relatedPosts as $rel): ?>
            <div class="related-post">
                <a href="<?php echo SITE_URL; ?>/post/<?php echo h($rel['slug']); ?>">
                    <?php if ($rel['intro_image']): ?>
                        <img src="<?php echo SITE_URL; ?>/uploads/posts/<?php echo h($rel['intro_image']); ?>" alt="<?php echo h($rel['title']); ?>" loading="lazy">
                    <?php elseif ($rel['video_url']): ?>
                        <div class="video-placeholder">🎬</div>
                    <?php else: ?>
                        <div class="no-image-placeholder">📄</div>
                    <?php endif; ?>
                </a>
                <h4><a href="<?php echo SITE_URL; ?>/post/<?php echo h($rel['slug']); ?>"><?php echo h($rel['title']); ?></a></h4>
                <span class="date"><?php echo date('d.m.Y', strtotime($rel['created_at'])); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

        
<section id="comments-section">
    <h2><?php echo __('comments'); ?></h2>
    <?php if (!$post['comments_enabled']): ?>
        <p><?php echo __('comments_disabled'); ?></p>
    <?php else: ?>
        <?php
        // Читаем настройки виджета VK
        $vkEnabled = (int)getSetting('vk_widget_enabled');
        $vkAppId = trim(getSetting('vk_app_id'));
        ?>

        <div id="comments-container">
            <!-- Список стандартных комментариев (всегда виден) -->
            <div id="comments-list">
                <?php
                $commentsTree = getCommentsTree($post['id']);
                echo renderCommentsTree($commentsTree);
                ?>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- АВТОРИЗОВАННЫЙ ПОЛЬЗОВАТЕЛЬ -->
                <?php if ($vkEnabled): ?>
                    <!-- Переключатель между стандартной формой и виджетом ВК -->
                    <div class="comments-switch">
                        <button class="comment-switch-btn active" data-mode="local"><?php echo __('on_site'); ?></button>
                        <button class="comment-switch-btn" data-mode="vk"><?php echo __('via_vk'); ?></button>
                    </div>
                <?php endif; ?>

                <!-- Стандартная форма (локальные комментарии) -->
                <div id="local-comments-block" class="comments-block">
                    <!-- Форма ответа -->
                    <div id="reply-form-container" style="display:none; margin-top:20px">
                        <h4><?php echo __('reply_to_comment'); ?></h4>
                        <form id="reply-form">
                            <input type="hidden" name="parent_id" id="reply-parent-id" value="0">
                            <input type="text" name="author" value="<?php echo h($_SESSION['username']); ?>" readonly>
                            <textarea name="content" class="textcomment" placeholder="<?php echo __('comment_text'); ?>" required></textarea>
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <button class="comment_button" type="submit"><?php echo __('submit'); ?></button>
                            <button class="comment_button cancelled" type="button" id="cancel-reply"><?php echo __('cancel'); ?></button>
                        </form>
                    </div>
                    <!-- Форма нового комментария -->
                    <h3><?php echo __('leave_comment'); ?></h3>
                    <form id="comment-form" data-post-id="<?php echo $post['id']; ?>">
                        <input type="hidden" name="parent_id" id="comment-parent-id" value="0">
                        <input type="text" name="author" value="<?php echo h($_SESSION['username']); ?>" readonly>
                        <textarea name="content" class="textcomment" placeholder="<?php echo __('comment_text'); ?>" required></textarea>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <button class="comment_button" type="submit"><?php echo __('submit'); ?></button>
                    </form>
                </div>

                <?php if ($vkEnabled): ?>
                    <!-- Блок виджета ВК -->
                    <div id="vk-comments-block" class="comments-block" style="display:none;">
                        <div id="vk_comments_auth"></div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- НЕАВТОРИЗОВАННЫЙ ПОЛЬЗОВАТЕЛЬ -->
                <?php if (!isset($_SESSION['user_id'])): ?>
    <div class="comments-guest">
    <div class="comments-guest-buttons" id="guest-buttons-container">
        <p><?php echo __('comments_login_required'); ?></p>
        <a href="<?php echo SITE_URL; ?>/login.php" class="btn-login">
            <img src="<?php echo SITE_URL; ?>/templates/icons/reg.svg" alt="Логин" class="icons_post">
        </a>
        <button id="vk-guest-btn" class="btn-vk"><img src="<?php echo SITE_URL; ?>/templates/icons/comm.svg" alt="Логин" class="icons_post"></button>
    </div>
        <div id="vk-info-message" style="display:none;">
            <p><?php echo __('vk_chosen_message'); ?></p><a href="<?php echo SITE_URL; ?>/login.php"><img src="<?php echo SITE_URL; ?>/templates/icons/reg.svg" alt="Логин" class="icons_st"></a>
        </div>
        <?php if ($vkEnabled): ?>
            <div id="vk-guest-container" style="display:none; margin-top:20px;">
                <div id="vk_comments_guest"></div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<script src="<?php echo SITE_URL; ?>/js_loader.php?file=likes.js"></script>
<script src="<?php echo SITE_URL; ?>/js_loader.php?file=comments.js"></script>

<?php
// Передаём настройки виджета в JavaScript
$vkEnabled = (int)getSetting('vk_widget_enabled');
$vkAppId = getSetting('vk_app_id');
?>
<script>
    window.postId = <?php echo (int)$post['id']; ?>;
    window.vkEnabled = <?php echo $vkEnabled ? 'true' : 'false'; ?>;
    window.vkAppId = '<?php echo addslashes($vkAppId); ?>';
</script>
<script src="<?php echo SITE_URL; ?>/js_loader.php?file=vk-comments.js"></script>

<?php
// JSON-LD Article для поисковиков
$articleAuthor = !empty($post['author_name']) ? h($post['author_name']) : h(getSetting('site_name'));
$articleImage = $post['intro_image'] ? SITE_URL . '/uploads/posts/' . h($post['intro_image']) : SITE_URL . '/default-og.php';
$articleDate = date('c', strtotime($post['created_at']));
$articleModified = $post['updated_at'] ? date('c', strtotime($post['updated_at'])) : $articleDate;

// Категории для articleSection
$catStmt = getDb()->prepare("SELECT c.name FROM post_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.post_id = ?");
$catStmt->execute([$post['id']]);
$articleCategories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
$articleSection = !empty($articleCategories) ? implode(', ', $articleCategories) : '';
?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": <?php echo json_encode($post['title']); ?>,
  "description": <?php echo json_encode(mb_substr(strip_tags($post['content']), 0, 160)); ?>,
  "image": <?php echo json_encode($articleImage); ?>,
  "datePublished": <?php echo json_encode($articleDate); ?>,
  "dateModified": <?php echo json_encode($articleModified); ?>,
  "author": {
    "@type": "Person",
    "name": <?php echo json_encode($articleAuthor); ?>,
    "url": <?php echo json_encode(SITE_URL); ?>
  },
  "publisher": {
    "@type": "Organization",
    "name": <?php echo json_encode(getSetting('site_name')); ?>,
    "logo": {
      "@type": "ImageObject",
      "url": <?php echo json_encode(SITE_URL . '/default-og.png'); ?>
    }
  },
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": <?php echo json_encode($canonicalUrl); ?>
  }<?php if ($articleSection): ?>,
  "articleSection": <?php echo json_encode($articleSection); ?><?php endif; ?><?php if (!empty($post['hashtags'])): ?>,
  "keywords": <?php echo json_encode(implode(', ', array_column($post['hashtags'], 'name'))); ?><?php endif; ?>
}
</script>

<?php
include __DIR__ . '/templates/footer.php';
?>