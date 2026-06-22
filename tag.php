<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';

$pageTitle = __('all_tags');
$pageDescription = __('all_tags_description');
$canonicalUrl = SITE_URL . '/tags.php';

include __DIR__ . '/templates/header.php';
?>

<div class="tags-page">
    <h1><?php echo __('all_tags'); ?></h1>
    
    <?php
    $allTags = getAllTags();
    if (empty($allTags)): ?>
        <p><?php echo __('no_tags'); ?></p>
    <?php else: ?>
        <div class="tags-list">
            <?php foreach ($allTags as $tag): ?>
                <a href="<?php echo SITE_URL; ?>/search.php?q=<?php echo urlencode($tag['name']); ?>" class="tag-item">
                    <?php echo h($tag['name']); ?>
                    <span class="tag-count">(<?php echo $tag['count']; ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/templates/footer.php';
?>