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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF token invalid';
    } elseif (isHoneypotFilled()) {
        header('Location: index.php');
        exit;
    } else {
        $captchaAnswer = $_POST['captcha'] ?? '';
        if (!verifyCaptcha($captchaAnswer)) {
            $error = __('captcha_failed');
        } else {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';

            if (empty($username) || empty($email) || empty($password)) {
                $error = 'Заполните все поля';
            } elseif (strlen($username) < 3) {
                $error = 'Имя пользователя слишком короткое';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Неверный email';
            } elseif (strlen($password) < 6) {
                $error = 'Пароль слишком короткий';
            } elseif ($password !== $password_confirm) {
                $error = 'Пароли не совпадают';
            } else {
                $userId = registerUser($username, $email, $password);
                if ($userId) {
                    $db = getDb();
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + 86400);
                    $stmt = $db->prepare("UPDATE users SET verification_token = ?, token_expires = ? WHERE id = ?");
                    $stmt->execute([$token, $expires, $userId]);

                    if (sendVerificationEmail($email, $token)) {
                        $success = 'Регистрация успешна! На ваш email отправлена ссылка для подтверждения.';
                    } else {
                        $success = 'Регистрация успешна, но не удалось отправить письмо. Обратитесь к администратору.';
                    }
                } else {
                    $error = 'Пользователь с таким именем или email уже существует.';
                }
            }
        }
    }
}

$pageTitle = __('register');
include __DIR__ . '/templates/header.php';
?>
<div class="auth-container">
    <h1><?php echo __('register'); ?></h1>
    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <p><a href="login.php"><?php echo __('login'); ?></a></p>
    <?php else: ?>
        <form method="post" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <?php echo generateHoneypot(); ?>
            <div class="form-group">
                <label for="username"><?php echo __('username'); ?></label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email"><?php echo __('email'); ?></label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password"><?php echo __('password'); ?></label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required>
                    <span class="toggle-password" onclick="togglePasswordVisibility('password')">👁️</span>
                </div>
            </div>
            <div class="form-group">
                <label for="password_confirm"><?php echo __('confirm_password'); ?></label>
                <div class="password-wrapper">
                    <input type="password" id="password_confirm" name="password_confirm" required>
                    <span class="toggle-password" onclick="togglePasswordVisibility('password_confirm')">👁️</span>
                </div>
            </div>

            <div class="form-group captcha">
                <label><?php echo __('captcha'); ?></label>
                <div class="captcha-display"><?php echo generateCaptcha(); ?></div>
                <input type="text" name="captcha" required autocomplete="off">
            </div>

            <div class="form-group" style="font-size:13px;color:#8a9bd5;">
                <label>
                    <input type="checkbox" name="privacy_agree" required>
                    <?php echo __('register_agree_privacy'); ?> <a href="<?php echo SITE_URL; ?>/page/privacy" target="_blank" style="color:#60a5fa;"><?php echo __('cookie_privacy'); ?></a>
                </label>
            </div>

            <button type="submit"><?php echo __('register'); ?></button>
        </form>
        <?php if (yandexOAuthEnabled() || vkOAuthEnabled()): ?>
            <div class="auth-separator"><span>или</span></div>
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
        <?php endif; ?>
        <p><?php echo __('already_have_account'); ?> <a href="login.php"><?php echo __('login'); ?></a></p>
    <?php endif; ?>
</div>

<script>
function togglePasswordVisibility(fieldId) {
    const input = document.getElementById(fieldId);
    const toggle = input.nextElementSibling;
    if (input.type === 'password') {
        input.type = 'text';
        toggle.textContent = '🙈';
    } else {
        input.type = 'password';
        toggle.textContent = '👁️';
    }
}
document.addEventListener('DOMContentLoaded', function() {
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
<?php include __DIR__ . '/templates/footer.php'; ?>