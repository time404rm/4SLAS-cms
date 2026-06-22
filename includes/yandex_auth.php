<?php
/**
 * Яндекс ID (OAuth 2.0) — авторизация
 * 4SLAS CMS
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/oauth_helpers.php';

define('YANDEX_AUTH_URL', 'https://oauth.yandex.ru/authorize');
define('YANDEX_TOKEN_URL', 'https://oauth.yandex.ru/token');
define('YANDEX_USERINFO_URL', 'https://login.yandex.ru/info?format=json');

function yandexOAuthEnabled()
{
    $enabled = getSetting('yandex_oauth_enabled');
    $clientId = getSetting('yandex_client_id');
    $clientSecret = getSetting('yandex_client_secret');
    return ($enabled == '1' && !empty($clientId) && !empty($clientSecret));
}

function getYandexAuthUrl()
{
    $clientId = getSetting('yandex_client_id');
    $redirectUri = SITE_URL . '/oauth/yandex_callback.php';
    $state = bin2hex(random_bytes(16));
    $_SESSION['yandex_oauth_state'] = $state;

    $params = http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'state' => $state,
    ]);
    return YANDEX_AUTH_URL . '?' . $params;
}

function exchangeYandexCode($code)
{
    $clientId = getSetting('yandex_client_id');
    $clientSecret = getSetting('yandex_client_secret');

    $response = oauthHttpPost(YANDEX_TOKEN_URL, [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ], ['Content-Type: application/x-www-form-urlencoded']);

    if (!$response) {
        error_log('Yandex token exchange failed: no response');
        return false;
    }
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        error_log('Yandex token error: ' . ($data['error_description'] ?? $data['error']));
        return false;
    }
    return $data['access_token'] ?? false;
}

function getYandexUserInfo($accessToken)
{
    $response = oauthHttpGet(YANDEX_USERINFO_URL, ['Authorization: OAuth ' . $accessToken]);

    if (!$response) {
        error_log('Yandex userinfo failed: no response');
        return false;
    }
    return json_decode($response, true);
}

function yandexLoginOrRegister($userInfo)
{
    $email = $userInfo['default_email'] ?? '';
    if (empty($email)) {
        return ['error' => __('yandex_email_missing')];
    }

    $db = getDb();

    $stmt = $db->prepare("SELECT id, username, role, is_verified FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['is_verified'] != 1) {
            $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$existing['id']]);
        }
        return [
            'user_id' => $existing['id'],
            'username' => $existing['username'],
            'role' => $existing['role'],
        ];
    }

    $yandexLogin = $userInfo['login'] ?? '';
    $realName = $userInfo['real_name'] ?? $yandexLogin;

    $baseUsername = oauthMakeUsername($realName, 'yandex_user');

    $username = $baseUsername;
    $counter = 1;
    while (true) {
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        if ($checkStmt->fetchColumn() == 0) break;
        $username = $baseUsername . $counter;
        $counter++;
    }

    $fakeHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID);

    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, is_verified) VALUES (?, ?, ?, 'user', 1)");
    $stmt->execute([$username, $email, $fakeHash]);
    $newId = $db->lastInsertId();

    return [
        'user_id' => (int)$newId,
        'username' => $username,
        'role' => 'user',
    ];
}

function doYandexLogin($userData)
{
    $_SESSION['user_id'] = $userData['user_id'];
    $_SESSION['username'] = $userData['username'];
    $_SESSION['role'] = $userData['role'];
    session_regenerate_id(true);
}
