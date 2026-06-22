<?php
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? '';
if (!$token) die('Токен не указан');

$db = getDb();
$stmt = $db->prepare("SELECT id FROM users WHERE verification_token = ? AND token_expires > NOW() AND is_verified = 0");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) die('Ссылка недействительна или истекла.');

$db->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, token_expires = NULL WHERE id = ?")
   ->execute([$user['id']]);

echo 'Email успешно подтверждён. Теперь вы можете войти.';
echo '<br><a href="login.php">Перейти ко входу</a>';