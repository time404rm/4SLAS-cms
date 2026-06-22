<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';

// Получаем контактную информацию
$db = getDb();
$stmt = $db->prepare("SELECT * FROM contact_info WHERE id = 1");
$stmt->execute();
$contact = $stmt->fetch();

$pageTitle = 'Обо мне';
$pageDescription = 'Свяжитесь с автором блога';
$canonicalUrl = SITE_URL . '/contact';

include __DIR__ . '/templates/header.php';
?>

<div class="contact-page">
    <h1>Обо мне</h1>
    
    <div class="contact-card">
        <?php if ($contact && $contact['photo']): ?>
            <div class="contact-avatar">
                <img src="<?php echo SITE_URL . $contact['photo']; ?>" alt="<?php echo h($contact['full_name']); ?>" loading="lazy">
            </div>
        <?php endif; ?>
        <div class="contact-info">
            <?php if ($contact && $contact['full_name']): ?>
                <h2><?php echo h($contact['full_name']); ?></h2>
            <?php endif; ?>
            <?php if ($contact && $contact['position']): ?>
                <p class="contact-position"><?php echo h($contact['position']); ?></p>
            <?php endif; ?>
            <?php if ($contact && $contact['bio']): ?>
                <div class="contact-bio"><?php echo $contact['bio']; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Блок соцсетей (если есть) -->
    <?php echo renderContactSocialIcons(); ?>
</div>

<?php
include __DIR__ . '/templates/footer.php';
?>