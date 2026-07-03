<?php
require_once '../includes/functions.php';
if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

$db = getDb();
$message = '';
$error = '';
$csrf_token = generateCsrfToken();

// Обработка сохранения настроек кеша и минификации CSS/JS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'CSRF token invalid';
    } else {
        $cache_enabled = isset($_POST['cache_enabled']) ? 1 : 0;
        $cache_ttl = (int)($_POST['cache_ttl'] ?? 3600);
        if ($cache_ttl < 60) $cache_ttl = 60;
        if ($cache_ttl > 86400) $cache_ttl = 86400;
        $css_minify_enabled = isset($_POST['css_minify_enabled']) ? 1 : 0;
        $js_minify_enabled = isset($_POST['js_minify_enabled']) ? 1 : 0;
        $js_obfuscate_enabled = isset($_POST['js_obfuscate_enabled']) ? 1 : 0;

        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute(['cache_enabled', $cache_enabled]);
        $stmt->execute(['cache_ttl', $cache_ttl]);
        $stmt->execute(['css_minify_enabled', $css_minify_enabled]);
        $stmt->execute(['js_minify_enabled', $js_minify_enabled]);
        $stmt->execute(['js_obfuscate_enabled', $js_obfuscate_enabled]);

        // При изменении настройки минификации очищаем соответствующий кеш
        if ($css_minify_enabled != (int)getSetting('css_minify_enabled')) {
            clearCssCache();
        }
        if ($js_minify_enabled != (int)getSetting('js_minify_enabled')) {
            $jsCacheDir = CACHE_DIR . 'js/';
            if (is_dir($jsCacheDir)) {
                $files = glob($jsCacheDir . '*');
                foreach ($files as $file) if (is_file($file)) unlink($file);
            }
        }

        $message = 'Настройки сохранены';
    }
}

// Очистка всего кеша страниц
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        $error = 'CSRF token invalid';
    } else {
        clearCache();
        header('Location: cache.php?cleared=1');
        exit;
    }
}

// Очистка кеша CSS
if (isset($_GET['action']) && $_GET['action'] === 'clear_css') {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        $error = 'CSRF token invalid';
    } else {
        clearCssCache();
        header('Location: cache.php?css_cleared=1');
        exit;
    }
}

// Очистка кеша JavaScript
if (isset($_GET['action']) && $_GET['action'] === 'clear_js') {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        $error = 'CSRF token invalid';
    } else {
        $jsCacheDir = CACHE_DIR . 'js/';
        if (is_dir($jsCacheDir)) {
            $files = glob($jsCacheDir . '*');
            foreach ($files as $file) if (is_file($file)) unlink($file);
        }
        header('Location: cache.php?js_cleared=1');
        exit;
    }
}

// Удаление одного кеш-файла страницы
if (isset($_GET['delete_file']) && isset($_GET['csrf_token'])) {
    if (!verifyCsrfToken($_GET['csrf_token'])) {
        die('CSRF failed');
    }
    $filename = basename($_GET['delete_file']);
    $filepath = CACHE_DIR . $filename;
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    header('Location: cache.php?deleted=1');
    exit;
}

// Текущие настройки
$cache_enabled = (int)getSetting('cache_enabled');
$cache_ttl = (int)getSetting('cache_ttl') ?: 3600;
$css_minify_enabled = (int)getSetting('css_minify_enabled');
$js_minify_enabled = (int)getSetting('js_minify_enabled');
$js_obfuscate_enabled = (int)getSetting('js_obfuscate_enabled');

// Статистика кеша страниц
$cacheDir = CACHE_DIR;
$cacheFiles = [];
$totalSize = 0;
$fileCount = 0;
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '*.html');
    $fileCount = count($files);
    foreach ($files as $file) {
        $totalSize += filesize($file);
        $cacheFiles[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'mtime' => filemtime($file)
        ];
    }
    usort($cacheFiles, function($a, $b) {
        return $b['mtime'] - $a['mtime'];
    });
}
$totalSizeFormatted = round($totalSize / 1024, 2) . ' KB';
if ($totalSize > 1024*1024) $totalSizeFormatted = round($totalSize / (1024*1024), 2) . ' MB';

// Статистика кеша CSS
$cssCacheDir = CACHE_DIR . 'css/';
$cssFileCount = 0;
$cssSize = 0;
if (is_dir($cssCacheDir)) {
    $cssFiles = glob($cssCacheDir . '*.css');
    $cssFileCount = count($cssFiles);
    foreach ($cssFiles as $file) {
        $cssSize += filesize($file);
    }
}
$cssSizeFormatted = round($cssSize / 1024, 2) . ' KB';

// Статистика кеша JavaScript
$jsCacheDir = CACHE_DIR . 'js/';
$jsFileCount = 0;
$jsSize = 0;
if (is_dir($jsCacheDir)) {
    $jsFiles = glob($jsCacheDir . '*.js');
    $jsFileCount = count($jsFiles);
    foreach ($jsFiles as $file) {
        $jsSize += filesize($file);
    }
}
$jsSizeFormatted = round($jsSize / 1024, 2) . ' KB';

$pageTitle = 'Управление кешем';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo h($pageTitle); ?></title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .stat-card {
            background: #1e2a3e;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .stat-card h3 {
            margin: 0 0 10px;
            color: #60a5fa;
        }
        .cache-table {
            width: 100%;
            border-collapse: collapse;
        }
        .cache-table th, .cache-table td {
            padding: 8px;
            border: 1px solid #2a3650;
            text-align: left;
        }
        .clear-btn {
            background: #7f1a1a;
            color: white;
            border: none;
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }
        .clear-btn:hover {
            background: #991b1b;
        }
        .save-btn {
            background: #2563eb;
            padding: 8px 16px;
        }
        .button-group {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .form-group {
            margin-bottom: 15px;
        }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>⚡ Управление кешем</h1>
    
    <?php if ($message): ?>
        <div class="success"><?php echo h($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?php echo h($error); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['cleared'])): ?>
        <div class="success">Кеш страниц очищен.</div>
    <?php endif; ?>
    <?php if (isset($_GET['css_cleared'])): ?>
        <div class="success">Кеш CSS очищен.</div>
    <?php endif; ?>
    <?php if (isset($_GET['js_cleared'])): ?>
        <div class="success">Кеш JavaScript очищен.</div>
    <?php endif; ?>
    
    <!-- Статистика кеша страниц -->
    <div class="stat-card">
        <h3>📊 Статистика кеша страниц</h3>
        <p>Файлов в кеше: <strong><?php echo $fileCount; ?></strong></p>
        <p>Общий размер: <strong><?php echo $totalSizeFormatted; ?></strong></p>
        <p>Папка кеша: <code><?php echo CACHE_DIR; ?></code></p>
    </div>
    
    <!-- Статистика кеша CSS (минификация) -->
    <div class="stat-card">
        <h3>🎨 Статистика кеша CSS (минификация)</h3>
        <p>Файлов в кеше CSS: <strong><?php echo $cssFileCount; ?></strong></p>
        <p>Общий размер: <strong><?php echo $cssSizeFormatted; ?></strong></p>
        <p>Папка кеша CSS: <code><?php echo CACHE_DIR . 'css/'; ?></code></p>
    </div>
    
    <!-- Статистика кеша JavaScript (минификация) -->
    <div class="stat-card">
        <h3>🔧 Статистика кеша JavaScript (минификация)</h3>
        <p>Файлов в кеше JS: <strong><?php echo $jsFileCount; ?></strong></p>
        <p>Общий размер: <strong><?php echo $jsSizeFormatted; ?></strong></p>
        <p>Папка кеша JS: <code><?php echo CACHE_DIR . 'js/'; ?></code></p>
    </div>
    
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <div class="form-group">
            <label>
                <input type="checkbox" name="cache_enabled" value="1" <?php echo $cache_enabled ? 'checked' : ''; ?>>
                Включить кеширование страниц (только для гостей)
            </label>
        </div>
        <div class="form-group">
            <label>Время жизни кеша страниц (секунды):</label>
            <input type="number" name="cache_ttl" value="<?php echo $cache_ttl; ?>" min="60" max="86400" step="60">
            <small>Минимум 60 секунд (1 минута), максимум 86400 (24 часа). По умолчанию 3600 (1 час).</small>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="css_minify_enabled" value="1" <?php echo $css_minify_enabled ? 'checked' : ''; ?>>
                Минимизировать CSS (сжатие style.css + theme.css)
            </label>
            <small>При включении CSS-файлы (style.css, theme.css) будут сжиматься и встраиваться в<head>. При выключении — загрузка через &#x3C;link&#x3E;.</small>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="js_minify_enabled" value="1" <?php echo $js_minify_enabled ? 'checked' : ''; ?>>
                Минимизировать JavaScript (удалять пробелы, комментарии)
            </label>
            <small>При включении JS-файлы будут сжиматься (удаление комментариев, лишних пробелов). Кеш JS будет обновляться автоматически.</small>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="js_obfuscate_enabled" value="1" <?php echo $js_obfuscate_enabled ? 'checked' : ''; ?>>
                Обфусцировать JavaScript (простая, может нарушить работу)
            </label>
            <small>Включайте только если все скрипты протестированы. Обфускация делает код трудным для чтения, но может вызывать ошибки.</small>
        </div>
        <button type="submit" name="save_settings" class="save-btn">Сохранить настройки</button>
    </form>
    
    <div class="button-group">
        <a href="?action=clear&csrf_token=<?php echo $csrf_token; ?>" class="clear-btn" onclick="return confirm('Очистить весь кеш страниц? Это удалит все закешированные страницы.')">🗑️ Очистить кеш страниц</a>
        <a href="?action=clear_css&csrf_token=<?php echo $csrf_token; ?>" class="clear-btn" onclick="return confirm('Очистить кеш CSS? Минифицированные файлы будут пересозданы при следующем запросе.')">🎨 Очистить кеш CSS</a>
        <a href="?action=clear_js&csrf_token=<?php echo $csrf_token; ?>" class="clear-btn" onclick="return confirm('Очистить кеш JavaScript? Минифицированные файлы будут пересозданы при следующем запросе.')">🔧 Очистить кеш JS</a>
    </div>
    
    <?php if ($fileCount > 0): ?>
    <h2>Кешированные страницы (последние 50)</h2>
    <table class="cache-table">
        <thead>
            <tr><th>URL (хеш)</th><th>Размер (KB)</th><th>Дата создания</th><th>Действие</th></tr>
        </thead>
        <tbody>
            <?php 
            $displayFiles = array_slice($cacheFiles, 0, 50);
            foreach ($displayFiles as $file): 
            ?>
            <tr>
                <td><code><?php echo h($file['name']); ?></code></td>
                <td><?php echo round($file['size'] / 1024, 2); ?> KB</td>
                <td><?php echo date('d.m.Y H:i:s', $file['mtime']); ?></td>
                <td><a href="?delete_file=<?php echo urlencode($file['name']); ?>&csrf_token=<?php echo $csrf_token; ?>" onclick="return confirm('Удалить этот файл?')">Удалить</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>