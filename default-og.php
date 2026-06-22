<?php
/**
 * Генерация OG-картинки по умолчанию (1200×630)
 * Используется как fallback для Open Graph / Twitter Cards
 */
require_once __DIR__ . '/includes/functions.php';

$cacheFile = __DIR__ . '/cache/default-og.png';

// Кешируем на 24 часа
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    readfile($cacheFile);
    exit;
}

$width = 1200;
$height = 630;
$img = imagecreatetruecolor($width, $height);

// Фон — градиент
$bgTop = imagecolorallocate($img, 26, 26, 46);
$bgBottom = imagecolorallocate($img, 42, 54, 78);
for ($y = 0; $y < $height; $y++) {
    $r = 26 + (42 - 26) * ($y / $height);
    $g = 26 + (54 - 26) * ($y / $height);
    $b = 46 + (78 - 46) * ($y / $height);
    $color = imagecolorallocate($img, $r, $g, $b);
    imageline($img, 0, $y, $width, $y, $color);
}

// Текст
$siteName = getSetting('site_name') ?: '4SLAS CMS';
$textColor = imagecolorallocate($img, 255, 255, 255);

// Ищем шрифт
$fontFile = null;
$fontPaths = [
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
    '/System/Library/Fonts/Helvetica.ttc',
    '/System/Library/Fonts/Arial.ttf',
];
foreach ($fontPaths as $fp) {
    if (file_exists($fp)) {
        $fontFile = $fp;
        break;
    }
}

$fontSize = 48;
$textBox = function_exists('imagettfbbox') && $fontFile
    ? imagettfbbox($fontSize, 0, $fontFile, $siteName)
    : null;

if ($textBox) {
    $textW = $textBox[2] - $textBox[0];
    $textH = $textBox[1] - $textBox[7];
    $x = ($width - $textW) / 2;
    $y = ($height + $textH) / 2;
    imagettftext($img, $fontSize, 0, $x, $y, $textColor, $fontFile, $siteName);
} else {
    imagestring($img, 5, ($width - strlen($siteName) * 10) / 2, $height / 2 - 10, $siteName, $textColor);
}

// Сохраняем в кеш
if (!is_dir(__DIR__ . '/cache')) mkdir(__DIR__ . '/cache', 0755, true);
imagepng($img, $cacheFile);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
imagepng($img);
imagedestroy($img);
?>
