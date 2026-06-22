<?php
/**
 * Шаблон страницы "Сайт на реконструкции"
 * Доступен только администратору для изменения стилей
 */
$title = getSetting('maintenance_title') ?: 'Сайт на реконструкции';
$message = getSetting('maintenance_message') ?: 'Ведутся технические работы. Скоро всё заработает!';
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$siteUrl = $protocol . $host . $base;

header('HTTP/1.1 503 Service Temporarily Unavailable');
header('Retry-After: 3600');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/maintenance.css">
</head>
<body>
<div class="maintenance-container">
    <div class="icon">🔧</div>
    <h1><?php echo htmlspecialchars($title); ?></h1>
    <p><?php echo nl2br(htmlspecialchars($message)); ?></p>
    <small>Администратор может войти по <a href="<?php echo $siteUrl; ?>/admin/">ссылке</a></small>
</div>
</body>
</html>
<?php
exit;
?>