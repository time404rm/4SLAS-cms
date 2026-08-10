<?php
/**
 * API для запуска News Daily через веб (админка)
 * Вызывается из admin/news-daily.php
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Увеличиваем таймаут — SATI может отвечать долго
set_time_limit(180);

$input = json_decode(file_get_contents('php://input'), true);
$topic = $input['topic'] ?? 'it';
$promptOverride = !empty($input['prompt_override']) ? trim($input['prompt_override']) : null;

require_once __DIR__ . '/../cron/news-daily.php';

$result = generateNewsDaily($topic, $promptOverride);
echo json_encode($result);
