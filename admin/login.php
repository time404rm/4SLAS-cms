<?php
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/captcha.php';

if (isset($_SESSION['user_id']) && canAccessAdmin()) {
    header('Location: index.php');
    exit;
}

$csrf_token = generateCsrfToken();
$error = '';

// Защита от брутфорса
$ip = getClientIP();
cleanOldAttempts('login_attempts', 15);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = __('csrf_token_invalid');
    } elseif (isHoneypotFilled()) {
        header('Location: index.php');
        exit;
    } else {
        if (!checkRateLimit('login_attempts', 5, 15, $ip)) {
            $error = 'Слишком много неудачных попыток входа. Попробуйте через 15 минут.';
        } else {
            $captchaAnswer = $_POST['captcha'] ?? '';
            if (!verifyCaptcha($captchaAnswer)) {
                $error = __('captcha_failed');
                recordAttempt('login_attempts', $ip);
            } else {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                if (loginUser($username, $password)) {
                    if (!canAccessAdmin()) {
                        session_destroy();
                        $error = 'У вас нет прав доступа в админ-панель';
                    } else {
                        $db = getDb();
                        $db->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
                        header('Location: index.php');
                        exit;
                    }
                } else {
                    $error = __('invalid_credentials');
                    recordAttempt('login_attempts', $ip);
                }
            }
        }
    }
}

$captchaQuestion = generateCaptcha();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Вход в админ-панель</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/admin.css">
    <?php
    $favicon = getSetting('favicon');
    if ($favicon && file_exists($_SERVER['DOCUMENT_ROOT'] . $favicon)): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL . $favicon; ?>">
    <link rel="shortcut icon" href="<?php echo SITE_URL . $favicon; ?>">
    <?php else: ?>
    <link rel="icon" href="<?php echo SITE_URL; ?>/favicon.ico" type="image/x-icon">
    <?php endif; ?>
</head>
<body class="admin">
    <h1>Вход в админ-панель</h1>
    <?php if ($error): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <?php echo generateHoneypot(); ?>
        <div class="form-group"><label>Логин или Email</label><input type="text" name="username" required></div>
        <div class="form-group"><label>Пароль</label><input type="password" name="password" required></div>
        <div class="form-group captcha">
            <label>Капча</label>
            <div class="captcha-display"><?php echo $captchaQuestion; ?></div>
            <input type="text" name="captcha" required autocomplete="off">
        </div>
        <button type="submit">Войти</button>
    </form>
    <script>
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
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>