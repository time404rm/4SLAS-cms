<?php
require_once __DIR__ . '/includes/functions.php';

$file = $_GET['file'] ?? '';
if (!preg_match('/^[a-zA-Z0-9_.-]+\.js$/', $file)) {
    http_response_code(404);
    exit;
}

$fullPath = __DIR__ . '/src/' . $file;
if (!file_exists($fullPath)) {
    http_response_code(404);
    exit;
}

$content = getMinifiedJs($fullPath);
if ($content === false) {
    http_response_code(500);
    die('Error reading file');
}

header('Content-Type: application/javascript');
header('Content-Length: ' . strlen($content));
echo $content;