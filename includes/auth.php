<?php
require_once __DIR__ . '/db.php';

function registerUser($username, $email, $password) {
    $db = getDb();
    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    try {
        $stmt->execute([$username, $email, $hash]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

function loginUser($username, $password) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, username, password_hash, role, is_verified FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['is_verified'] != 1) {
            return false; // не пускаем неподтверждённых
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        return true;
    }
    return false;
}
?>