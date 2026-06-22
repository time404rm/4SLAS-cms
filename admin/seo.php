<?php
require_once '../includes/functions.php';
$csrf_token = generateCsrfToken();
require_once '../includes/seo.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

// ========== ГЛОБАЛЬНЫЕ SEO-НАСТРОЙКИ ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_global_seo'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF failed');
    $db = getDb();
    $settings = [
        'yandex_verification' => trim($_POST['yandex_verification'] ?? ''),
        'google_verification' => trim($_POST['google_verification'] ?? ''),
        'theme_color' => trim($_POST['theme_color'] ?? '#1a1a2e'),
    ];
    foreach ($settings as $key => $value) {
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
    }
    header('Location: seo.php?global_updated=1');
    exit;
}

// ========== SEO ДЛЯ СТРАНИЦ ==========
$pageType = $_GET['type'] ?? 'home';
$pageId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$seoData = getSeoData($pageType, $pageId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_page_seo'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF failed');
    $fields = [
        'meta_title' => $_POST['meta_title'] ?? '',
        'meta_description' => $_POST['meta_description'] ?? '',
        'meta_keywords' => $_POST['meta_keywords'] ?? '',
        'og_title' => $_POST['og_title'] ?? '',
        'og_description' => $_POST['og_description'] ?? '',
        'og_image' => $_POST['og_image'] ?? ''
    ];
    updateSeoData($pageType, $pageId, $fields);
    header('Location: seo.php?type=' . urlencode($pageType) . ($pageId ? '&id=' . $pageId : '') . '&updated=1');
    exit;
}

$yandexVerification = getSetting('yandex_verification');
$googleVerification = getSetting('google_verification');
$themeColor = getSetting('theme_color') ?: '#1a1a2e';
?>
<!DOCTYPE html>
<html>
<head>
    <title>SEO Settings</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .seo-section { background: #1e2a3e; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .seo-section h2 { margin-top: 0; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input[type="text"], .form-group textarea {
            width: 100%; padding: 8px; border: 1px solid #2a3650;
            background: #0f1422; color: #e2e8f0; border-radius: 4px; box-sizing: border-box;
        }
        .form-group small { display: block; color: #8a9bd5; font-size: 0.8rem; margin-top: 5px; }
        .success { background: #1b5e3f; color: #fff; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .nav-tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .nav-tabs a { padding: 8px 16px; background: #2a3650; color: #e2e8f0; text-decoration: none; border-radius: 4px; }
        .nav-tabs a:hover { background: #3a4a6a; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>SEO настройки</h1>
    <?php if (isset($_GET['updated'])) echo "<div class='success'>SEO данные страницы обновлены!</div>"; ?>
    <?php if (isset($_GET['global_updated'])) echo "<div class='success'>Глобальные SEO настройки сохранены!</div>"; ?>

    <!-- ГЛОБАЛЬНЫЕ SEO-НАСТРОЙКИ -->
    <div class="seo-section">
        <h2>Верификация и общие настройки</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="save_global_seo" value="1">
            <div class="form-group">
                <label for="yandex_verification">Код верификации Яндекс.Вебмастер</label>
                <input type="text" id="yandex_verification" name="yandex_verification" value="<?php echo h($yandexVerification); ?>" placeholder="Например: abc123def456">
                <small>Meta-тег из Яндекс.Вебмастер для подтверждения прав на сайт.</small>
            </div>
            <div class="form-group">
                <label for="google_verification">Код верификации Google Search Console</label>
                <input type="text" id="google_verification" name="google_verification" value="<?php echo h($googleVerification); ?>" placeholder="Например: xyz789abc">
                <small>Meta-тег из Google Search Console для подтверждения прав на сайт.</small>
            </div>
            <div class="form-group">
                <label for="theme_color">Цвет темы для мобильных браузеров</label>
                <input type="color" id="theme_color" name="theme_color" value="<?php echo h($themeColor); ?>" style="width:60px; height:40px; padding:0; border:none; cursor:pointer;">
                <small>Цвет адресной строки в мобильных браузерах (Chrome Android, Safari iOS).</small>
            </div>
            <button type="submit">Сохранить глобальные настройки</button>
        </form>
    </div>

    <!-- SEO ДЛЯ СТРАНИЦ -->
    <div class="seo-section">
        <h2>SEO для страниц</h2>
        <div class="nav-tabs">
            <a href="?type=home" <?php echo $pageType === 'home' ? 'style="background:#3a4a6a;"' : ''; ?>>Главная</a>
            <a href="?type=post" <?php echo $pageType === 'post' ? 'style="background:#3a4a6a;"' : ''; ?>>Посты</a>
            <a href="?type=category" <?php echo $pageType === 'category' ? 'style="background:#3a4a6a;"' : ''; ?>>Категории</a>
        </div>

        <?php if ($pageType === 'post' || $pageType === 'category'): ?>
        <div class="form-group">
            <label>ID записи: <input type="number" name="page_id_input" value="<?php echo $pageId ?: ''; ?>" style="width:80px;" onchange="window.location.href='?type=<?php echo $pageType; ?>&id='+this.value"></label>
        </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="save_page_seo" value="1">
            <div class="form-group">
                <label>Meta Title</label>
                <input type="text" name="meta_title" value="<?php echo h($seoData['meta_title']); ?>">
            </div>
            <div class="form-group">
                <label>Meta Description</label>
                <textarea name="meta_description" rows="3"><?php echo h($seoData['meta_description']); ?></textarea>
            </div>
            <div class="form-group">
                <label>Meta Keywords</label>
                <input type="text" name="meta_keywords" value="<?php echo h($seoData['meta_keywords']); ?>">
            </div>
            <div class="form-group">
                <label>OG Title</label>
                <input type="text" name="og_title" value="<?php echo h($seoData['og_title']); ?>">
            </div>
            <div class="form-group">
                <label>OG Description</label>
                <textarea name="og_description" rows="3"><?php echo h($seoData['og_description']); ?></textarea>
            </div>
            <div class="form-group">
                <label>OG Image (filename)</label>
                <input type="text" name="og_image" value="<?php echo h($seoData['og_image']); ?>" placeholder="example.jpg">
            </div>
            <button type="submit">Сохранить SEO страницы</button>
        </form>
    </div>

    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>
