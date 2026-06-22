<?php
require_once '../includes/functions.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

$csrf_token = generateCsrfToken();
$error = '';
$zipAvailable = class_exists('ZipArchive');

// Состояние — проверка установленных компонентов
$hasGrapesJS = file_exists(__DIR__ . '/../src/vendor/grapes.min.js');
$hasSortable = file_exists(__DIR__ . '/../src/vendor/sortable.min.js');
$hasInitScript = file_exists(__DIR__ . '/../src/grapesjs-init.js');

// Обработка загрузки ZIP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upgrade_zip'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF token validation failed';
    } elseif (!$zipAvailable) {
        $error = 'PHP ZipArchive не установлен. Невозможно распаковать архив.';
    } elseif ($_FILES['upgrade_zip']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Ошибка загрузки файла (код: ' . $_FILES['upgrade_zip']['error'] . ')';
    } else {
        // Настройка стриминга вывода
        @ini_set('output_buffering', '0');
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/html; charset=utf-8');
        header('X-Accel-Buffering: no');

        echo '<!DOCTYPE html><html><head><title>Обновление...</title>';
        echo '<style>body{background:#0f1422;color:#e2e8f0;font-family:monospace;padding:20px;line-height:1.6}';
        echo '.log{background:#0a0a0a;padding:15px;border-radius:8px;max-height:80vh;overflow-y:auto}';
        echo '.done{color:#22c55e;font-weight:bold;font-size:1.1rem}</style></head><body>';
        echo '<h1>🔄 Обновление CMS</h1><div class="log" id="log">';
        flush();

        $tempDir = __DIR__ . '/../temp/_upgrade_' . time();
        $zipPath = $_FILES['upgrade_zip']['tmp_name'];
        $success = false;

        try {
            // Распаковка ZIP
            echo "[INFO] Распаковка архива...\n";
            flush();
            if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new Exception('Не удалось открыть ZIP-архив');
            }

            // Валидация содержимого ZIP перед распаковкой
            echo "[INFO] Проверка содержимого архива...\n";
            flush();
            $allowedExt = ['php', 'js', 'css', 'json', 'txt', 'md', 'sql', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot'];
            $foundUpdater = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                // Пропускаем директории
                if (substr($filename, -1) === '/') continue;
                // Защита от path traversal
                if (strpos($filename, '..') !== false) {
                    throw new Exception('Недопустимый путь в архиве: ' . $filename);
                }
                // Проверяем расширение
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt)) {
                    throw new Exception('Запрещённый тип файла в архиве: ' . $filename);
                }
                // Проверяем, что есть 4SUPDATE.php
                if (basename($filename) === '4SUPDATE.php') {
                    $foundUpdater = true;
                }
            }
            if (!$foundUpdater) {
                throw new Exception('В архиве не найден 4SUPDATE.php');
            }
            echo "[INFO] Содержимое архива проверено\n";
            flush();

            $zip->extractTo($tempDir);
            $zip->close();
            echo "[INFO] Архив распакован: $tempDir\n";
            flush();

            // Поиск 4SUPDATE.php
            $updaterPath = null;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->getFilename() === '4SUPDATE.php') {
                    $updaterPath = $file->getRealPath();
                    break;
                }
            }
            if (!$updaterPath) {
                throw new Exception('В архиве не найден 4SUPDATE.php');
            }
            echo "[INFO] Найден: $updaterPath\n";
            flush();

            // Запуск 4SUPDATE
            echo "[INFO] Запуск обновления...\n";
            flush();

            $CMS_ROOT = realpath(__DIR__ . '/..');
            define('_4SUP_CALLED_FROM_ADMIN', true);
            require $updaterPath;

            $success = !empty($GLOBALS['_4SUP_ok']);

        } catch (Exception $e) {
            echo "[ERROR] " . $e->getMessage() . "\n";
            flush();
        }

        // Очистка temp
        echo "[INFO] Очистка временных файлов...\n";
        flush();
        if (is_dir($tempDir)) {
            $rmdirRecursive = function($dir) use (&$rmdirRecursive) {
                if (!is_dir($dir)) return;
                $files = array_diff(scandir($dir), ['.', '..']);
                foreach ($files as $f) {
                    $p = $dir . '/' . $f;
                    is_dir($p) ? $rmdirRecursive($p) : unlink($p);
                }
                rmdir($dir);
            };
            $rmdirRecursive($tempDir);
        }
        echo "[INFO] Временные файлы удалены\n";

        // Финальное сообщение
        echo '</div>';
        if ($success) {
            echo '<p class="done">✅ Обновление успешно завершено!</p>';
        } else {
            echo '<p style="color:#ef4444;font-weight:bold;">❌ Обновление прервано из-за ошибки</p>';
        }
        echo '<p><a href="update.php" style="color:#60a5fa;">← Вернуться к странице обновления</a></p>';
        echo '</body></html>';
        exit;
    }
}

// ====== СТРАНИЦА С ФОРМОЙ (GET / ошибка) ======
?>
<!DOCTYPE html>
<html>
<head>
    <title>Обновление CMS</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .status-card { background: #1e2a3e; border: 1px solid #2a3650; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .status-card h3 { margin: 0 0 10px; color: #60a5fa; }
        .status-item { display: flex; align-items: center; gap: 10px; padding: 6px 0; border-bottom: 1px solid #2a3650; }
        .status-item:last-child { border-bottom: none; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .status-dot.installed { background: #22c55e; }
        .status-dot.missing { background: #6b7280; }
        .upload-zone { border: 2px dashed #3b4e6e; border-radius: 12px; padding: 40px; text-align: center; background: #0f1422; cursor: pointer; transition: border-color 0.2s; }
        .upload-zone:hover { border-color: #60a5fa; }
        .upload-zone.has-file { border-color: #22c55e; background: #0a1a1a; }
        .btn { background: #2563eb; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #1e40af; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-secondary { background: #2a3650; }
        .btn-secondary:hover { background: #3b4e6e; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>Обновление CMS</h1>

    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="status-card">
        <h3>Текущее состояние</h3>
        <div class="status-item">
            <span class="status-dot <?php echo $hasGrapesJS ? 'installed' : 'missing'; ?>"></span>
            GrapesJS (визуальный конструктор) — <?php echo $hasGrapesJS ? 'установлен' : 'не установлен'; ?>
        </div>
        <div class="status-item">
            <span class="status-dot <?php echo $hasSortable ? 'installed' : 'missing'; ?>"></span>
            SortableJS (сортировка блоков) — <?php echo $hasSortable ? 'установлен' : 'не установлен'; ?>
        </div>
        <div class="status-item">
            <span class="status-dot <?php echo $hasInitScript ? 'installed' : 'missing'; ?>"></span>
            Скрипт инициализации — <?php echo $hasInitScript ? 'установлен' : 'не установлен'; ?>
        </div>
        <div class="status-item">
            <span class="status-dot <?php echo $zipAvailable ? 'installed' : 'missing'; ?>"></span>
            PHP ZipArchive — <?php echo $zipAvailable ? 'доступен' : 'не доступен'; ?>
        </div>
    </div>

    <div class="status-card">
        <h3>Установка обновления</h3>
        <p style="color:#b9c7e6; margin-bottom:15px;">Загрузите ZIP-пакет обновления (dgr_update). Процесс обновления будет отображаться в реальном времени.</p>
        <form method="post" enctype="multipart/form-data" id="upgrade-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="upload-zone" id="upload-zone" onclick="document.getElementById('file-input').click()">
                <div style="font-size:3rem; margin-bottom:10px;">📦</div>
                <p style="color:#e2e8f0; margin:0;">Нажмите, чтобы выбрать ZIP-файл</p>
                <p style="color:#6b7fa0; font-size:0.85rem; margin:5px 0 0;">или перетащите его сюда</p>
                <input type="file" name="upgrade_zip" id="file-input" accept=".zip" style="display:none;" required>
            </div>
            <div style="margin-top:15px; display:flex; gap:10px; align-items:center;">
                <button type="submit" class="btn" id="upgrade-btn" disabled>Обновить</button>
                <span id="file-name" style="color:#6b7fa0;"></span>
            </div>
        </form>
    </div>

    <div class="status-card">
        <h3>Ручная установка</h3>
        <p style="color:#b9c7e6; font-size:0.9rem;">Если автоматическая установка недоступна (нет ZipArchive):</p>
        <ol style="color:#b9c7e6; font-size:0.85rem; line-height:1.8;">
            <li>Скопируйте папку <code>dgr_update</code> в корень сайта через FTP</li>
            <li>Запустите в терминале: <code>php dgr_update/4SUPDATE.php</code></li>
        </ol>
    </div>

    <script>
    var zone = document.getElementById('upload-zone');
    var input = document.getElementById('file-input');
    var btn = document.getElementById('upgrade-btn');
    var fileName = document.getElementById('file-name');

    zone.addEventListener('dragover', function(e) {
        e.preventDefault();
        zone.classList.add('has-file');
    });
    zone.addEventListener('dragleave', function() {
        zone.classList.remove('has-file');
    });
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        zone.classList.remove('has-file');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            onFileSelect();
        }
    });
    input.addEventListener('change', onFileSelect);

    function onFileSelect() {
        if (input.files.length && input.files[0].name.endsWith('.zip')) {
            btn.disabled = false;
            fileName.textContent = 'Выбран: ' + input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(1) + ' KB)';
        } else {
            btn.disabled = true;
            fileName.textContent = input.files.length ? 'Неверный формат (.zip)' : '';
        }
    }

    document.getElementById('upgrade-form').addEventListener('submit', function(e) {
        if (!confirm('Запустить обновление? Будет произведена замена системных файлов.')) {
            e.preventDefault();
            return;
        }
        btn.disabled = true;
        btn.textContent = '⏳ Обновление...';
    });
    </script>

    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>
