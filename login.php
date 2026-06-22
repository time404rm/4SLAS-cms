<?php
// 4Tim-cms
// Автор: ruslanabuzyaroff
// Telegram: https://t.me/time4_04
// Сайт: time404.ru
// E-mail: ruslan@time404.ru
// Лицензия: MIT
// Если эта CMS понравилась, можете оставить монетку автору на чашечку кофе или дать ссылку на проект.
// Если планируете модифицировать и улучшать, буду рад если поделитесь доработками
?>
<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/captcha.php';
require_once __DIR__ . '/includes/yandex_auth.php';
require_once __DIR__ . '/includes/vk_auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$csrf_token = generateCsrfToken();
$error = '';

// Защита от брутфорса: получение IP и очистка старых записей
$ip = getClientIP();
cleanOldAttempts('login_attempts', 15); // удаляем попытки старше 15 минут

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = __('csrf_token_invalid');
    } elseif (isHoneypotFilled()) {
        // Honeypot сработал — молча редиректим (бот)
        header('Location: index.php');
        exit;
    } else {
        // Проверяем лимит попыток (не более 5 за 15 минут)
        if (!checkRateLimit('login_attempts', 5, 15, $ip)) {
            $error = 'Слишком много неудачных попыток входа. Попробуйте через 15 минут.';
        } else {
            $captchaAnswer = $_POST['captcha'] ?? '';
            if (!verifyCaptcha($captchaAnswer)) {
                $error = __('captcha_failed');
                // Записываем неудачную попытку (капча не пройдена)
                recordAttempt('login_attempts', $ip);
            } else {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                if (loginUser($username, $password)) {
                    // Успешный вход – очищаем все попытки для этого IP
                    $db = getDb();
                    $db->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
                    header('Location: index.php');
                    exit;
                } else {
                    recordAttempt('login_attempts', $ip);
                    $error = __('invalid_credentials');
                }
            }
        }
    }
}

$captchaQuestion = generateCaptcha();
$pageTitle = __('login');
include __DIR__ . '/templates/header.php';
?>
<div class="auth-container">
    <h1><?php echo __('login'); ?></h1>
    <?php if ($error): ?><div class="error-message"><?php echo h($error); ?></div><?php endif; ?>
    <?php if (isset($_SESSION['oauth_error'])): ?>
        <div class="error-message"><?php echo h($_SESSION['oauth_error']); unset($_SESSION['oauth_error']); ?></div>
    <?php endif; ?>
    <?php if (yandexOAuthEnabled() || vkOAuthEnabled()): ?>
        <div class="oauth-section">
            <?php if (yandexOAuthEnabled()): ?>
            <a href="<?php echo SITE_URL; ?>/oauth/yandex.php" class="yandex-btn">
                <svg viewBox="0 0 24 24" width="20" height="20" style="vertical-align:middle;margin-right:6px;"><rect width="24" height="24" rx="4" fill="#FC3F1D"/><text x="12" y="17" text-anchor="middle" font-family="Arial,sans-serif" font-weight="bold" font-size="14" fill="#fff">Я</text></svg>
                <?php echo __('login_via_yandex'); ?>
            </a>
            <?php endif; ?>
            <?php if (vkOAuthEnabled()): ?>
            <a href="<?php echo SITE_URL; ?>/oauth/vk.php" class="vk-btn">
                <svg viewBox="0 0 24 24" width="20" height="20" style="vertical-align:middle;margin-right:6px;"><rect width="24" height="24" rx="4" fill="#0077FF"/><text x="12" y="17" text-anchor="middle" font-family="Arial,sans-serif" font-weight="bold" font-size="13" fill="#fff">VK</text></svg>
                <?php echo __('login_via_vk'); ?>
            </a>
            <?php endif; ?>
        </div>
        <div class="auth-separator"><span>или</span></div>
    <?php endif; ?>
    <form method="post" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <?php echo generateHoneypot(); ?>
        <div class="form-group">
            <label for="username"><?php echo __('username_or_email'); ?></label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="password"><?php echo __('password'); ?></label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" required>
                <span class="toggle-password" data-target="password">👁️</span>
            </div>
        </div>
        <div class="form-group captcha">
            <label><?php echo __('captcha'); ?></label>
            <div class="captcha-display"><?php echo $captchaQuestion; ?></div>
            <input type="text" name="captcha" required autocomplete="off">
        </div>
        <button type="submit"><?php echo __('login'); ?></button>
    </form>
    <p><?php echo __('no_account'); ?> <a href="register.php"><?php echo __('register'); ?></a></p>
    <?php if (isset($error) && $error == __('email_not_verified')): ?>
        <p><a href="resend_verification.php"><?php echo __('resend_verification_link'); ?></a></p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggles = document.querySelectorAll('.toggle-password');
    toggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    this.textContent = '🙈';
                } else {
                    input.type = 'password';
                    this.textContent = '👁️';
                }
            }
        });
    });

    // Обновление капчи-картинки по клику на ↻
    const refreshBtn = document.querySelector('.captcha-refresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const img = this.parentElement.querySelector('.captcha-img');
            if (img) {
                img.src = img.src.split('?')[0] + '?t=' + Date.now();
            }
        });
    }
});
</script>
<?php
include __DIR__ . '/templates/footer.php';
?>