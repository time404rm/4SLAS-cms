<?php
require_once __DIR__ . '/includes/functions.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (resendVerificationEmail($email)) {
        $message = __('verification_email_sent');
    } else {
        $error = __('user_not_found_or_already_verified');
    }
}

$pageTitle = __('resend_verification');
include __DIR__ . '/templates/header.php';
?>
<div class="auth-container">
    <h1><?php echo __('resend_verification'); ?></h1>
    <?php if ($message): ?>
        <div class="success-message"><?php echo h($message); ?></div>
    <?php elseif ($error): ?>
        <div class="error-message"><?php echo h($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label for="email"><?php echo __('email'); ?></label>
            <input type="email" name="email" id="email" required>
        </div>
        <button type="submit"><?php echo __('send_verification'); ?></button>
    </form>
    <p><a href="login.php"><?php echo __('back_to_login'); ?></a></p>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>