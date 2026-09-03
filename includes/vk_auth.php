<?php
/**
 * ВКонтакте (OAuth 2.0) — авторизация
 * 4SLAS CMS
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/oauth_helpers.php';

function vkOAuthEnabled()
{
    $enabled = getSetting('vk_oauth_enabled');
    $clientId = getSetting('vk_client_id');
    $serviceToken = getSetting('vk_service_token');
    return ($enabled == '1' && !empty($clientId) && !empty($serviceToken));
}

function getVkAuthUrl()
{
    $clientId = getSetting('vk_client_id');
    $redirectUri = SITE_URL . '/oauth/vk_callback.php';

    // PKCE: случайный code_verifier -> code_challenge (S256, base64url)
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';
    $codeVerifier = '';
    for ($i = 0; $i < 64; $i++) {
        $codeVerifier .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $_SESSION['vk_code_verifier'] = $codeVerifier;

    $state = bin2hex(random_bytes(24)); // 48 hex-символов (>= 32)
    $_SESSION['vk_oauth_state'] = $state;

    $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

    $params = http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'state' => $state,
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
        'scope' => 'email vkid.personal_info',
    ]);
    return 'https://id.vk.ru/authorize?' . $params;
}

function exchangeVkCode($code, $deviceId = '')
{
    $clientId = getSetting('vk_client_id');
    $serviceToken = getSetting('vk_service_token');
    $redirectUri = SITE_URL . '/oauth/vk_callback.php';

    $body = [
        'grant_type' => 'authorization_code',
        'code_verifier' => $_SESSION['vk_code_verifier'] ?? '',
        'redirect_uri' => $redirectUri,
        'code' => $code,
        'client_id' => $clientId,
        'device_id' => $deviceId,
        'state' => $_SESSION['vk_oauth_state'] ?? '',
    ];
    // Для конфиденциальных приложений VK ID требуется сервисный ключ
    if ($serviceToken) {
        $body['service_token'] = $serviceToken;
    }

    $response = oauthHttpPost('https://id.vk.ru/oauth2/auth', $body, ['Content-Type: application/x-www-form-urlencoded']);

    if (!$response) {
        error_log('VK ID token exchange failed: no response');
        return false;
    }
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        error_log('VK ID token error: ' . ($data['error_description'] ?? $data['error']));
        return false;
    }
    return isset($data['access_token']) ? $data : false;
}

function getVkUserInfo($tokenData)
{
    $accessToken = $tokenData['access_token'] ?? '';
    if (!$accessToken) {
        error_log('VK ID user_info failed: no access_token');
        return false;
    }
    $clientId = getSetting('vk_client_id');

    $response = oauthHttpPost('https://id.vk.ru/oauth2/user_info', [
        'client_id' => $clientId,
        'access_token' => $accessToken,
    ], ['Content-Type: application/x-www-form-urlencoded']);

    if (!$response) {
        error_log('VK ID user_info failed: no response');
        return false;
    }

    $data = json_decode($response, true);
    $user = $data['user'] ?? [];
    if (empty($user)) {
        error_log('VK ID user_info error: ' . ($data['error_description'] ?? ($data['error'] ?? 'empty')));
        return false;
    }

    return [
        'id' => (string)($user['user_id'] ?? ($tokenData['user_id'] ?? '')),
        'email' => $user['email'] ?? '',
        'first_name' => $user['first_name'] ?? '',
        'last_name' => $user['last_name'] ?? '',
    ];
}

function vkLoginOrRegister($userInfo)
{
    if (empty($userInfo['email'])) {
        return ['error' => __('vk_email_missing')];
    }

    $db = getDb();
    $email = $userInfo['email'];

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

    $name = trim(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? ''));
    $baseUsername = oauthMakeUsername($name, 'vk_user');

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

    return [
        'user_id' => (int)$db->lastInsertId(),
        'username' => $username,
        'role' => 'user',
    ];
}

function doVkLogin($userData)
{
    $_SESSION['user_id'] = $userData['user_id'];
    $_SESSION['username'] = $userData['username'];
    $_SESSION['role'] = $userData['role'];
    session_regenerate_id(true);
}
