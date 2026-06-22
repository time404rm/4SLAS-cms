<?php
/**
 * Яндекс OAuth — обработка ответа
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/yandex_auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

if (!yandexOAuthEnabled()) {
    $_SESSION['oauth_error'] = __('yandex_oauth_disabled');
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$state = $_GET['state'] ?? '';
$sessionState = $_SESSION['yandex_oauth_state'] ?? '';
unset($_SESSION['yandex_oauth_state']);

if (empty($state) || !hash_equals($sessionState, $state)) {
    $_SESSION['oauth_error'] = __('csrf_token_invalid');
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    $error = $_GET['error'] ?? 'unknown';
    $_SESSION['oauth_error'] = __('yandex_auth_error') . ' (' . h($error) . ')';
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$accessToken = exchangeYandexCode($code);
if (!$accessToken) {
    $_SESSION['oauth_error'] = __('yandex_auth_error');
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$userInfo = getYandexUserInfo($accessToken);
if (!$userInfo) {
    $_SESSION['oauth_error'] = __('yandex_auth_error');
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$result = yandexLoginOrRegister($userInfo);
if (isset($result['error'])) {
    $_SESSION['oauth_error'] = $result['error'];
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

doYandexLogin($result);

header('Location: ' . SITE_URL . '/index.php');
exit;
