<?php
/**
 * ВК OAuth — обработка ответа
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/vk_auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

if (!vkOAuthEnabled()) {
    $_SESSION['oauth_error'] = __('vk_oauth_disabled');
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$state = $_GET['state'] ?? '';
$sessionState = $_SESSION['vk_oauth_state'] ?? '';
unset($_SESSION['vk_oauth_state']);

if (empty($state) || !hash_equals($sessionState, $state)) {
    $_SESSION['oauth_error'] = __('csrf_token_invalid');
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    $error = $_GET['error'] ?? 'unknown';
    $_SESSION['oauth_error'] = __('vk_auth_error') . ' (' . h($error) . ')';
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$tokenData = exchangeVkCode($code);
if (!$tokenData) {
    $_SESSION['oauth_error'] = __('vk_auth_error');
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$userInfo = getVkUserInfo($tokenData);
if (!$userInfo) {
    $_SESSION['oauth_error'] = __('vk_auth_error');
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$result = vkLoginOrRegister($userInfo);
if (isset($result['error'])) {
    $_SESSION['oauth_error'] = $result['error'];
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

doVkLogin($result);

header('Location: ' . SITE_URL . '/index.php');
exit;
