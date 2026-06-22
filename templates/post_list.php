<?php
// 4SLAS-cms
// Автор: ruslanabuzyaroff
// Telegram: https://t.me/time4_04
// Сайт: time404.ru
// E-mail: ruslan@time404.ru
// Лицензия: MIT
// Если этот CMS понравился, можете оставить монетку автору на чашечку кофе или дать ссылку на проект.
// Если планируете модифицировать и улучшать, буду рад если поделитесь доработками
?>
<?php
$customBlock = null;
$db = getDb();
$stmt = $db->prepare("SELECT content FROM custom_blocks WHERE position = 'after_first_post' AND is_active = 1 LIMIT 1");
$stmt->execute();
$customBlock = $stmt->fetchColumn();
$postCounter = 0;
?>

<?php if (empty($posts)): ?>
    <p><?php echo __('no_posts'); ?></p>
<?php else: ?>
    <?php foreach ($posts as $post): ?>
        <?php
        $excerpt = getExcerpt(
            $post['content'],
            $post['excerpt_type'] ?? null,
            $post['excerpt_length'] ?? null
        );
        $postCounter++;
        ?>
        <article class="post" data-post-id="<?php echo $post['id']; ?>">
            <div class="postlisttitle">
                <?php
                $logo = getSetting('site_logo');
                if ($logo && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo)): ?>
                    <a href="<?php echo SITE_URL; ?>/post/<?php echo h($post['slug']); ?>">
                        <img src="<?php echo SITE_URL . $logo; ?>" alt="<?php echo h(getSetting('site_name')); ?>" class="postlist-logo" loading="lazy">
                    </a>
                <?php endif; ?>
                <div class="title-autor">
                    <span class="post-author">
                        <h2 class="postlist-title"><a href="<?php echo SITE_URL; ?>/post/<?php echo h($post['slug']); ?>"><?php echo h($post['title']); ?></a></h2>
                    </span>
                    <span class="post-author">@<?php echo !empty($post['author_name']) ? h($post['author_name']) : __('unknown_author'); ?></span>
                </div>
            </div>

            <?php if (!empty($post['video_url'])): ?>
                <div class="post-video">
                    <?php echo getVideoEmbed($post['video_url']); ?>
                </div>
            <?php elseif ($post['intro_image']): ?>
                <img src="<?php echo SITE_URL; ?>/uploads/posts/<?php echo h($post['intro_image']); ?>" alt="<?php echo h($post['title']); ?>" loading="lazy">
            <?php endif; ?>

            <div class="postbody">
                <p><?php echo maskEmails($excerpt); ?></p>
                <a href="<?php echo SITE_URL; ?>/post/<?php echo h($post['slug']); ?>" class="read-more"><?php echo __('read_more'); ?>...</a>
            </div>
            <div class="postlist-meta">
                <span class="post-date"><?php echo date('d.m.Y', strtotime($post['created_at'])); ?></span>
                <?php if (isset($_SESSION['user_id'])): ?>
                     <button class="like-btn-list" data-post-id="<?php echo $post['id']; ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:2px;pointer-events:none"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg> <?php echo $post['likes_count']; ?></button>
                <?php else: ?>
                    <button class="like-btn-list" disabled title="<?php echo __('like_login'); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:2px;pointer-events:none"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg> <?php echo $post['likes_count']; ?></button>
                <?php endif; ?>
                 <a href="<?php echo SITE_URL; ?>/post/<?php echo h($post['slug']); ?>#comments-section" class="comment-link"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:2px;pointer-events:none"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> <?php echo (int)($post['comment_count'] ?? 0); ?></a>
                <div class="share-container post-share">
                    <button class="share-btn" data-url="<?php echo SITE_URL; ?>/post/<?php echo h($post['slug']); ?>" data-title="<?php echo h($post['title']); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="pointer-events:none"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M16 6l-4-4-4 4M12 2v13"/></svg>
                    </button>
                    <div class="share-dropdown">
                        <div class="share-dropdown-header">
                            <span><?php echo __('share_title'); ?></span>
                            <button class="share-close">&times;</button>
                        </div>
                        <ul class="share-list">
                            <li><a href="#" class="share-link copy-link">📋 <?php echo __('copy_link'); ?></a></li>
                            <li><a href="#" class="share-link telegram" data-share="telegram">📱 Telegram</a></li>
                            <li><a href="#" class="share-link vk" data-share="vk">💙 VK</a></li>
                            <li><a href="#" class="share-link whatsapp" data-share="whatsapp">💚 WhatsApp</a></li>
                            <li><a href="#" class="share-link twitter" data-share="twitter">🐦 X (Twitter)</a></li>
                            <li><a href="#" class="share-link ok" data-share="ok">👥 <?php echo __('ok'); ?></a></li>
                        </ul>
                    </div>
                </div>
                <?php 
                $cats = !empty($post['categories']) ? explode(',', $post['categories']) : [];
                foreach ($cats as $cat):
                    $cat = trim($cat);
                    if ($cat !== ''): ?>
                        <span class="category"><?php //echo h($cat); ?></span>
                    <?php endif;
                endforeach; ?>

                <?php 
                $tags = !empty($post['hashtags']) ? explode(',', $post['hashtags']) : [];
                foreach ($tags as $tag):
                    $tag = trim($tag);
                    if ($tag !== ''): ?>
                        <a href="<?php echo SITE_URL; ?>/search.php?q=<?php echo urlencode($tag); ?>" class="hashtag-link">#<?php echo h($tag); ?></a>
                    <?php endif;
                endforeach; ?>
            </div>
        </article>

        <?php if ($postCounter === 1 && $customBlock): ?>
            <div class="custom-block-wrapper">
                <?php echo $customBlock; ?>
            </div>
        <?php endif; ?>

    <?php endforeach; ?>
<?php endif; ?>
<div style="display: none;" class="pagination-links">
    <?php
    if (isset($totalPages) && $totalPages > 1):
    echo '<a href="' . SITE_URL . '/" rel="start">1</a>';
    for ($i = 2; $i <= $totalPages; $i++) {
        echo '<a href="' . SITE_URL . '/?page=' . $i . '">' . $i . '</a>';
    }
    endif;
    ?>
</div>
