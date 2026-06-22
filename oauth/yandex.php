<?php
/**
 * Яндекс OAuth — инициация входа
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

header('Location: ' . getYandexAuthUrl());
exit;
