<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$token = $_GET['token'] ?? '';
$message = '';
$error = '';

if (empty($token)) {
    $error = __('invalid_verification_link');
} else {
    if (verifyUser($token)) {
        $message = __('email_verified_success');
    } else {
        $error = __('verification_link_expired');
    }
}

$pageTitle = __('email_verification');
include __DIR__ . '/templates/header.php';
?>

<div class="auth-container">
    <h1><?php echo __('email_verification'); ?></h1>
    <?php if ($message): ?>
        <div class="success-message"><?php echo h($message); ?></div>
        <p><a href="login.php"><?php echo __('login'); ?></a></p>
    <?php elseif ($error): ?>
        <div class="error-message"><?php echo h($error); ?></div>
        <p><?php echo __('resend_verification_link'); ?></p>
        <form method="post" action="resend_verification.php">
            <input type="email" name="email" placeholder="<?php echo __('email'); ?>" required>
            <button type="submit"><?php echo __('resend'); ?></button>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>