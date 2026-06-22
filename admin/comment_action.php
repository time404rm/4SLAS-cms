<?php
require_once '../includes/functions.php';
if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

if (!verifyCsrfToken($csrf)) {
    die('Неверный CSRF-токен.');
}

if ($id && in_array($action, ['approve', 'spam', 'delete'])) {
    $db = getDb();
    if ($action === 'approve') {
        $stmt = $db->prepare("UPDATE comments SET status = 'approved' WHERE id = ?");
    } elseif ($action === 'spam') {
        $stmt = $db->prepare("UPDATE comments SET status = 'spam' WHERE id = ?");
    } elseif ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM comments WHERE id = ?");
    }
    $stmt->execute([$id]);
}
header('Location: comments.php');
exit;