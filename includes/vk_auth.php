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
    $clientSecret = getSetting('vk_client_secret');
    return ($enabled == '1' && !empty($clientId) && !empty($clientSecret));
}

function getVkAuthUrl()
{
    $clientId = getSetting('vk_client_id');
    $redirectUri = SITE_URL . '/oauth/vk_callback.php';
    $state = bin2hex(random_bytes(16));
    $_SESSION['vk_oauth_state'] = $state;

    $params = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'email',
        'v' => '5.199',
        'state' => $state,
    ]);
    return 'https://oauth.vk.com/authorize?' . $params;
}

function exchangeVkCode($code)
{
    $clientId = getSetting('vk_client_id');
    $clientSecret = getSetting('vk_client_secret');
    $redirectUri = SITE_URL . '/oauth/vk_callback.php';

    $response = oauthHttpGet('https://oauth.vk.com/access_token?' . http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'code' => $code,
    ]));

    if (!$response) {
        error_log('VK token exchange failed: no response');
        return false;
    }
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        error_log('VK token error: ' . ($data['error_description'] ?? $data['error']));
        return false;
    }
    return isset($data['access_token'], $data['user_id']) ? $data : false;
}

function getVkUserInfo($tokenData)
{
    $accessToken = $tokenData['access_token'];
    $userId = $tokenData['user_id'];

    $response = oauthHttpGet('https://api.vk.com/method/users.get?' . http_build_query([
        'user_ids' => $userId,
        'fields' => 'first_name,last_name',
        'access_token' => $accessToken,
        'v' => '5.199',
    ]));

    if (!$response) {
        error_log('VK users.get failed: no response');
        return false;
    }

    $data = json_decode($response, true);
    $user = $data['response'][0] ?? [];

    return [
        'id' => $tokenData['user_id'],
        'email' => $tokenData['email'] ?? '',
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
