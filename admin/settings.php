<?php
/**
 * Админ-панель: управление глобальными настройками сайта
 * Позволяет редактировать название, описание, email, количество постов на страницу,
 * тему, комментарии, а также загружать логотип (JPG, PNG, SVG)
 */

require_once '../includes/functions.php';
if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

$db = getDb(); // <-- добавлено для работы с БД

$csrf_token = generateCsrfToken();
$message = '';
$error = '';

// Обработка загрузки favicon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['favicon']) && isset($_POST['upload_favicon'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = __('csrf_token_invalid');
    } else {
        $uploadDir = UPLOAD_DIR . 'favicon/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $file = $_FILES['favicon'];
        $allowedExt = ['png', 'ico', 'svg'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file['error'] === UPLOAD_ERR_OK && in_array($ext, $allowedExt)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowedMime = ['image/png', 'image/x-icon', 'image/svg+xml'];
            if (in_array($mime, $allowedMime)) {
                $newName = 'favicon.' . $ext;
                $targetPath = $uploadDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Удаляем старый favicon
                    $oldFavicon = getSetting('favicon');
                    if ($oldFavicon && file_exists($_SERVER['DOCUMENT_ROOT'] . $oldFavicon)) {
                        unlink($_SERVER['DOCUMENT_ROOT'] . $oldFavicon);
                    }
                    if ($ext === 'svg') {
                        $svg = file_get_contents($targetPath);
                        $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg);
                        $svg = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $svg);
                        file_put_contents($targetPath, $svg);
                    }
                    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'favicon'");
                    $stmt->execute(['/uploads/favicon/' . $newName]);
                    $message = 'Favicon успешно загружен';
                } else {
                    $error = 'Не удалось сохранить файл';
                }
            } else {
                $error = 'Недопустимый формат (только PNG, ICO, SVG)';
            }
        } else {
            $error = 'Ошибка загрузки или неверный формат';
        }
    }
}

// Удаление favicon
if (isset($_GET['delete_favicon']) && isset($_GET['csrf_token'])) {
    if (!verifyCsrfToken($_GET['csrf_token'])) die('CSRF failed');
    $currentFavicon = getSetting('favicon');
    if ($currentFavicon && file_exists($_SERVER['DOCUMENT_ROOT'] . $currentFavicon)) {
        unlink($_SERVER['DOCUMENT_ROOT'] . $currentFavicon);
    }
    $db->prepare("UPDATE settings SET setting_value = '' WHERE setting_key = 'favicon'")->execute();
    header('Location: settings.php?favicon_deleted=1');
    exit;
}

// ========== ОБРАБОТКА ЗАГРУЗКИ ЛОГОТИПА (отдельная форма) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['site_logo'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = __('csrf_token_invalid');
    } else {
        $uploadDir = UPLOAD_DIR . 'logo/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $file = $_FILES['site_logo'];
        $allowedExt = ['jpg', 'jpeg', 'png', 'svg'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file['error'] === UPLOAD_ERR_OK && in_array($ext, $allowedExt)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowedMime = ['image/jpeg', 'image/png', 'image/svg+xml'];
            if (in_array($mime, $allowedMime)) {
                $newName = 'logo.' . $ext;
                $targetPath = $uploadDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Удаляем старый логотип, если он был (файл)
                    $oldLogo = getSetting('site_logo');
                    if ($oldLogo && file_exists($_SERVER['DOCUMENT_ROOT'] . $oldLogo)) {
                        unlink($_SERVER['DOCUMENT_ROOT'] . $oldLogo);
                    }
                    if ($ext === 'svg') {
                        $svg = file_get_contents($targetPath);
                        $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg);
                        $svg = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $svg);
                        file_put_contents($targetPath, $svg);
                    }
                    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'site_logo'");
                    $stmt->execute(['/uploads/logo/' . $newName]);
                    $message = 'Логотип успешно загружен';
                } else {
                    $error = 'Не удалось сохранить файл';
                }
            } else {
                $error = 'Недопустимый формат изображения (только JPG, PNG, SVG)';
            }
        } else {
            $error = 'Ошибка загрузки или неверный формат';
        }
    }
    // После загрузки логотипа перенаправляем, чтобы избежать повторной отправки
    if (empty($error)) {
        header('Location: settings.php?logo_uploaded=1');
        exit;
    }
}

// ========== УДАЛЕНИЕ ЛОГОТИПА ==========
if (isset($_GET['delete_logo'])) {
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        die('CSRF token validation failed');
    }
    $currentLogo = getSetting('site_logo');
    if ($currentLogo && file_exists($_SERVER['DOCUMENT_ROOT'] . $currentLogo)) {
        unlink($_SERVER['DOCUMENT_ROOT'] . $currentLogo);
    }
    $db->prepare("UPDATE settings SET setting_value = '' WHERE setting_key = 'site_logo'")->execute();
    header('Location: settings.php?logo_deleted=1');
    exit;
}

// ========== ОБРАБОТКА СОХРАНЕНИЯ ОСНОВНЫХ НАСТРОЕК ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = __('csrf_token_invalid');
    } else {
        $settings = [
            'site_name' => trim($_POST['site_name'] ?? ''),
            'site_description' => trim($_POST['site_description'] ?? ''),
            'admin_email' => trim($_POST['admin_email'] ?? ''),
            'posts_per_page' => (int)($_POST['posts_per_page'] ?? 10),
            'comments_per_page' => (int)($_POST['comments_per_page'] ?? 10),
            'theme' => preg_replace('/[^a-z0-9_-]/i', '', $_POST['theme'] ?? 'default'),
            'comment_moderation' => isset($_POST['comment_moderation']) ? 1 : 0,
            'excerpt_type' => $_POST['excerpt_type'] ?? 'chars',
            'excerpt_length' => (int)($_POST['excerpt_length'] ?? 200),
            'title_format' => $_POST['title_format'] ?? 'site_page',
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'maintenance_title' => trim($_POST['maintenance_title'] ?? ''),
            'maintenance_message' => trim($_POST['maintenance_message'] ?? ''),
            'yandex_oauth_enabled' => isset($_POST['yandex_oauth_enabled']) ? 1 : 0,
            'yandex_client_id' => trim($_POST['yandex_client_id'] ?? ''),
            'yandex_client_secret' => trim($_POST['yandex_client_secret'] ?? ''),
            'vk_oauth_enabled' => isset($_POST['vk_oauth_enabled']) ? 1 : 0,
            'vk_client_id' => trim($_POST['vk_client_id'] ?? ''),
            'vk_client_secret' => trim($_POST['vk_client_secret'] ?? ''),
            'yandex_metrica_id' => trim($_POST['yandex_metrica_id'] ?? ''),
            'yoomoney_bill_number' => trim($_POST['yoomoney_bill_number'] ?? ''),
        ];
        if (empty($settings['site_name'])) {
            $error = __('site_name_required');
        } elseif (!filter_var($settings['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $error = __('invalid_admin_email');
        } elseif ($settings['posts_per_page'] < 1 || $settings['posts_per_page'] > 100) {
            $error = __('invalid_posts_per_page');
        } elseif ($settings['comments_per_page'] < 1 || $settings['comments_per_page'] > 100) {
            $error = __('invalid_comments_per_page');
        } elseif ($settings['excerpt_length'] < 10 || $settings['excerpt_length'] > 1000) {
            $error = 'Длина анонса должна быть от 10 до 1000';
        } else {
            foreach ($settings as $key => $value) {
                $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$key, (string)$value]);
            }
            $message = __('settings_saved');
        }
    }
}

// Загружаем текущие настройки
$site_name = getSetting('site_name') ?: 'БЛОГ Т404';
$site_description = getSetting('site_description') ?: 'Современная CMS для блога';
$admin_email = getSetting('admin_email') ?: 'admin@example.com';
$posts_per_page = (int)getSetting('posts_per_page');
$posts_per_page = $posts_per_page ?: 10;

$comments_per_page = (int)getSetting('comments_per_page');
$comments_per_page = $comments_per_page ?: 10;

$theme = getSetting('theme') ?: 'default';
$comment_moderation = (int)getSetting('comment_moderation');
// Если настройки нет (NULL), устанавливаем значение по умолчанию 1
if ($comment_moderation === null) $comment_moderation = 1;
$excerpt_type = getSetting('excerpt_type') ?: 'chars';
$excerpt_length = (int)getSetting('excerpt_length');
$excerpt_length = $excerpt_length ?: 200;
$title_format = getSetting('title_format') ?: 'site_page'; // добавлено
$logoPath = getSetting('site_logo');
$showLogoDelete = $logoPath && file_exists($_SERVER['DOCUMENT_ROOT'] . $logoPath);

$yandexOauthEnabled = (int)getSetting('yandex_oauth_enabled');
$yandexClientId = getSetting('yandex_client_id') ?: '';
$yandexClientSecret = getSetting('yandex_client_secret') ?: '';
$vkOauthEnabled = (int)getSetting('vk_oauth_enabled');
$vkClientId = getSetting('vk_client_id') ?: '';
$vkClientSecret = getSetting('vk_client_secret') ?: '';
$yandexMetricaId = getSetting('yandex_metrica_id') ?: '';
$yoomoneyBill = getSetting('yoomoney_bill_number') ?: '';

$pageTitle = __('settings');
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo h($pageTitle); ?></title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .settings-form { max-width: 800px; margin-top: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input[type="text"], .form-group input[type="email"],
        .form-group input[type="number"], .form-group select {
            width: 100%; padding: 8px; border: 1px solid #2a3650;
            background: #0f1422; color: #e2e8f0; border-radius: 4px;
        }
        .form-group small { display: block; color: #8a9bd5; font-size: 0.8rem; margin-top: 5px; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .success { background: #1b5e3f; color: #fff; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .error { background: #7f1a1a; color: #fff; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .delete-link { color: #ef4444; text-decoration: none; }
        .delete-link:hover { text-decoration: underline; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1><?php echo __('settings'); ?></h1>
    
    <?php if ($message): ?><div class="success"><?php echo h($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
    <?php if (isset($_GET['logo_uploaded'])): ?><div class="success">Логотип успешно загружен.</div><?php endif; ?>
    <?php if (isset($_GET['logo_deleted'])): ?><div class="success">Логотип удалён.</div><?php endif; ?>
    
    <!-- ОТДЕЛЬНАЯ ФОРМА ДЛЯ ЛОГОТИПА (размещена в самом верху, после заголовка) -->
    <div style="background: #1e2a3e; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="margin-top: 0;">Логотип сайта</h2>
        <?php if ($showLogoDelete): ?>
            <div class="form-group">
                <img src="<?php echo SITE_URL . $logoPath; ?>" alt="Логотип" style="max-width:200px; max-height:100px; border:1px solid #2a3650; padding:5px;">
                <br>
                <a href="?delete_logo=1&csrf_token=<?php echo $csrf_token; ?>" class="delete-link" onclick="return confirm('Удалить логотип?')">🗑️ Удалить логотип</a>
            </div>
        <?php else: ?>
            <p>Логотип не загружен.</p>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data" style="margin-bottom:0;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="form-group">
                <label for="site_logo">Загрузить новый логотип (JPG, PNG, SVG)</label>
                <input type="file" name="site_logo" id="site_logo" accept=".jpg,.jpeg,.png,.svg">
                <small>Рекомендуемая высота: до 100px. SVG – векторный, масштабируется без потерь.</small>
            </div>
            <button type="submit">Загрузить логотип</button>
        </form>
    </div>
    <hr>
    <h2>Favicon (иконка сайта)</h2>
    <?php $favicon = getSetting('favicon'); ?>
    <?php if ($favicon && file_exists($_SERVER['DOCUMENT_ROOT'] . $favicon)): ?>
        <div class="form-group">
            <img src="<?php echo SITE_URL . $favicon; ?>" alt="Favicon" style="max-width:32px; max-height:32px; border:1px solid #2a3650; padding:2px;">
            <br>
            <a href="?delete_favicon=1&csrf_token=<?php echo $csrf_token; ?>" class="delete-link" onclick="return confirm('Удалить favicon?')">🗑️ Удалить favicon</a>
        </div>
    <?php else: ?>
        <p>Favicon не загружен.</p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="margin-bottom:20px;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <div class="form-group">
            <label for="favicon">Загрузить favicon (PNG, ICO, SVG)</label>
            <input type="file" name="favicon" id="favicon" accept=".png,.ico,.svg">
            <small>Рекомендуемый размер: 16x16, 32x32, 48x48 пикселей.</small>
        </div>
        <button type="submit" name="upload_favicon">Загрузить favicon</button>
    </form>
    
    <!-- ОСНОВНАЯ ФОРМА НАСТРОЕК -->
    <form method="post" class="settings-form">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="save_settings" value="1">
        
        <div class="form-group">
            <label for="site_name"><?php echo __('site_name'); ?></label>
            <input type="text" id="site_name" name="site_name" value="<?php echo h($site_name); ?>" required>
        </div>
        <div class="form-group">
            <label for="site_description"><?php echo __('site_description'); ?></label>
            <input type="text" id="site_description" name="site_description" value="<?php echo h($site_description); ?>">
        </div>
        <div class="form-group">
            <label for="admin_email"><?php echo __('admin_email'); ?></label>
            <input type="email" id="admin_email" name="admin_email" value="<?php echo h($admin_email); ?>" required>
        </div>
        <div class="form-group">
            <label for="posts_per_page"><?php echo __('posts_per_page'); ?></label>
            <input type="number" id="posts_per_page" name="posts_per_page" value="<?php echo $posts_per_page; ?>" min="1" max="100">
        </div>
        <div class="form-group">
            <label for="comments_per_page"><?php echo __('comments_per_page'); ?></label>
            <input type="number" id="comments_per_page" name="comments_per_page" value="<?php echo $comments_per_page; ?>" min="1" max="100">
        </div>
        <div class="form-group checkbox-group">
            <input type="checkbox" id="comment_moderation" name="comment_moderation" value="1" <?php echo $comment_moderation ? 'checked' : ''; ?>>
            <label for="comment_moderation"><?php echo __('comment_moderation'); ?></label>
        </div>
        <div class="form-group">
            <label for="excerpt_type">Тип обрезки анонса</label>
            <select name="excerpt_type" id="excerpt_type">
                <option value="chars" <?php echo $excerpt_type === 'chars' ? 'selected' : ''; ?>>Символы</option>
                <option value="words" <?php echo $excerpt_type === 'words' ? 'selected' : ''; ?>>Слова</option>
            </select>
        </div>
        <div class="form-group">
            <label for="excerpt_length">Длина анонса</label>
            <input type="number" id="excerpt_length" name="excerpt_length" value="<?php echo $excerpt_length; ?>" min="10" max="1000">
        </div>
        
        <!-- ========== НОВОЕ ПОЛЕ: ФОРМАТ ЗАГОЛОВКА ========== -->
        <div class="form-group">
            <label for="title_format">Формат заголовка страницы (вкладка браузера)</label>
            <select name="title_format" id="title_format">
                <option value="site_page" <?php echo $title_format == 'site_page' ? 'selected' : ''; ?>>Сайт - Страница (пример: Мой блог - О нас)</option>
                <option value="page_site" <?php echo $title_format == 'page_site' ? 'selected' : ''; ?>>Страница - Сайт (пример: О нас - Мой блог)</option>
                <option value="page_only" <?php echo $title_format == 'page_only' ? 'selected' : ''; ?>>Только страница (пример: О нас)</option>
            </select>
            <small>Определяет, как будет отображаться заголовок вкладки браузера и в поисковой выдаче.</small>
        </div>
        <!-- ============================================= -->

        <fieldset style="border-top: 2px solid #2a3650; margin-top: 20px; padding-top: 20px;">
    <legend>Режим реконструкции</legend>
    <div class="form-group checkbox-group">
        <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?php echo (int)getSetting('maintenance_mode') ? 'checked' : ''; ?>>
        <label for="maintenance_mode">Включить режим "Сайт на реконструкции"</label>
        <small>При включении все пользователи, кроме администратора, увидят страницу-заглушку.</small>
    </div>
    <div class="form-group">
        <label for="maintenance_title">Заголовок страницы</label>
        <input type="text" id="maintenance_title" name="maintenance_title" value="<?php echo htmlspecialchars(getSetting('maintenance_title') ?: 'Сайт на реконструкции'); ?>">
    </div>
    <div class="form-group">
        <label for="maintenance_message">Сообщение</label>
        <textarea id="maintenance_message" name="maintenance_message" rows="3" style="width:100%;"><?php echo htmlspecialchars(getSetting('maintenance_message') ?: 'Ведутся технические работы. Скоро всё заработает!'); ?></textarea>
    </div>
</fieldset>

        <fieldset style="border-top: 2px solid #2a3650; margin-top: 20px; padding-top: 20px;">
            <legend>Яндекс ID (OAuth)</legend>
            <div class="form-group checkbox-group">
                <input type="checkbox" id="yandex_oauth_enabled" name="yandex_oauth_enabled" value="1" <?php echo $yandexOauthEnabled ? 'checked' : ''; ?>>
                <label for="yandex_oauth_enabled">Включить вход через Яндекс</label>
                <small>Позволяет пользователям авторизоваться с помощью Яндекс ID.</small>
            </div>
            <div class="form-group">
                <label for="yandex_client_id">Client ID</label>
                <input type="text" id="yandex_client_id" name="yandex_client_id" value="<?php echo h($yandexClientId); ?>" style="width:100%; max-width:500px;">
                <small>Получить на <a href="https://oauth.yandex.ru/client/new" target="_blank">oauth.yandex.ru</a>. Права: «Доступ к логину, имени и фамилии», «Доступ к адресу электронной почты». Redirect URI: <code><?php echo SITE_URL; ?>/oauth/yandex_callback.php</code></small>
            </div>
            <div class="form-group">
                <label for="yandex_client_secret">Client Secret</label>
                <input type="password" id="yandex_client_secret" name="yandex_client_secret" value="<?php echo h($yandexClientSecret); ?>" style="width:100%; max-width:500px;">
            </div>
        </fieldset>

        <fieldset style="border-top: 2px solid #2a3650; margin-top: 20px; padding-top: 20px;">
            <legend>ВКонтакте (OAuth)</legend>
            <div class="form-group checkbox-group">
                <input type="checkbox" id="vk_oauth_enabled" name="vk_oauth_enabled" value="1" <?php echo $vkOauthEnabled ? 'checked' : ''; ?>>
                <label for="vk_oauth_enabled">Включить вход через ВКонтакте</label>
                <small>Позволяет пользователям авторизоваться с помощью VK ID.</small>
            </div>
            <div class="form-group">
                <label for="vk_client_id">ID приложения</label>
                <input type="text" id="vk_client_id" name="vk_client_id" value="<?php echo h($vkClientId); ?>" style="width:100%; max-width:500px;">
                <small>Получить на <a href="https://vk.com/editapp?act=create" target="_blank">vk.com/editapp</a> (тип «Веб-сайт»). Redirect URI: <code><?php echo SITE_URL; ?>/oauth/vk_callback.php</code></small>
            </div>
            <div class="form-group">
                <label for="vk_client_secret">Защищённый ключ</label>
                <input type="password" id="vk_client_secret" name="vk_client_secret" value="<?php echo h($vkClientSecret); ?>" style="width:100%; max-width:500px;">
            </div>
        </fieldset>

        <fieldset style="border-top: 2px solid #2a3650; margin-top: 20px; padding-top: 20px;">
            <legend>Аналитика и донаты</legend>
            <div class="form-group">
                <label for="yandex_metrica_id">Яндекс Метрика ID</label>
                <input type="text" id="yandex_metrica_id" name="yandex_metrica_id" value="<?php echo h($yandexMetricaId); ?>" style="width:100%; max-width:300px;">
                <small>Числовой ID счётчика. Оставьте пустым чтобы отключить.</small>
            </div>
            <div class="form-group">
                <label for="yoomoney_bill_number">ЮMoney номер счёта</label>
                <input type="text" id="yoomoney_bill_number" name="yoomoney_bill_number" value="<?php echo h($yoomoneyBill); ?>" style="width:100%; max-width:300px;">
                <small>Номер счёта для кнопки «Автору на кофе». Оставьте пустым чтобы скрыть.</small>
            </div>
        </fieldset>
        
        <button type="submit"><?php echo __('save_settings'); ?></button>
        <a href="index.php"><?php echo __('cancel'); ?></a>
    </form>
    
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>