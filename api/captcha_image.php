<?php
/**
 * Генерация графической капчи (GD)
 */
session_start();
require_once __DIR__ . '/../includes/config.php';

// Проверяем GD
if (!extension_loaded('gd')) {
    $_SESSION['captcha_type'] = 'text';
    $_SESSION[CAPTCHA_SESSION_KEY] = '8';
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

// Символы капчи (исключаем похожие: 0/O, 1/l/I)
$chars = '23456789abcdefghijkmnpqrstuvwxyz';
$length = 5;
$code = '';
for ($i = 0; $i < $length; $i++) {
    $code .= $chars[mt_rand(0, strlen($chars) - 1)];
}

// Сохраняем ответ
$_SESSION['captcha_image_answer'] = $code;

// Размеры
$width = 160;
$height = 50;

// Создаём изображение
$img = imagecreatetruecolor($width, $height);

// Фон — случайный светлый
$bgR = mt_rand(230, 250);
$bgG = mt_rand(230, 250);
$bgB = mt_rand(230, 250);
$bgColor = imagecolorallocate($img, $bgR, $bgG, $bgB);
imagefilledrectangle($img, 0, 0, $width, $height, $bgColor);

// Шум — линии
for ($i = 0; $i < 4; $i++) {
    $lineColor = imagecolorallocatealpha($img, mt_rand(100, 200), mt_rand(100, 200), mt_rand(100, 200), 40);
    imageline($img, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height), $lineColor);
}

// Шум — точки
for ($i = 0; $i < 80; $i++) {
    $dotColor = imagecolorallocatealpha($img, mt_rand(100, 200), mt_rand(100, 200), mt_rand(100, 200), 60);
    imagesetpixel($img, mt_rand(0, $width), mt_rand(0, $height), $dotColor);
}

// Рисуем символы
$charWidth = $width / ($length + 1);
for ($i = 0; $i < $length; $i++) {
    $char = $code[$i];
    
    // Случайный тёмный цвет
    $textColor = imagecolorallocate($img, mt_rand(20, 80), mt_rand(20, 80), mt_rand(20, 80));
    
    // Случайный размер шрифта
    $fontSize = mt_rand(20, 26);
    
    // Позиция с небольшим смещением
    $x = $charWidth * ($i + 0.5) - $fontSize / 2;
    $y = $height / 2 + $fontSize / 3;
    
    // Случайный угол наклона
    $angle = mt_rand(-15, 15);
    
    // Используем встроенный шрифт или fallback
    $fontFile = null;
    $fontPaths = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/TTF/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu-sans/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        '/System/Library/Fonts/Helvetica.ttc',
        '/System/Library/Fonts/Arial.ttf',
    ];
    foreach ($fontPaths as $fp) {
        if (file_exists($fp)) {
            $fontFile = $fp;
            break;
        }
    }
    
    if ($fontFile && function_exists('imagettftext')) {
        imagettftext($img, $fontSize, $angle, $x, $y, $textColor, $fontFile, $char);
    } else {
        // Fallback: встроенный шрифт (без вращения)
        imagestring($img, 5, $x, $y - 15, $char, $textColor);
    }
}

// Ещё немного шума — дуги
for ($i = 0; $i < 2; $i++) {
    $arcColor = imagecolorallocatealpha($img, mt_rand(100, 180), mt_rand(100, 180), mt_rand(100, 180), 50);
    imagearc($img, mt_rand(0, $width), mt_rand(0, $height), mt_rand(30, 80), mt_rand(20, 50), mt_rand(0, 360), mt_rand(0, 360), $arcColor);
}

// Вывод
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
imagepng($img);
imagedestroy($img);
?>
