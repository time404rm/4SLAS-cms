<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/md-parser.php';
$csrf_token = generateCsrfToken();
if (!isAdmin()) { header('Location: login.php'); exit; }

$result = '';
$error = '';

// Обработка загрузки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['md_file'])) {
    $ch = curl_init(SITE_URL . '/api/import-md.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => ['md_file' => new CURLFile($_FILES['md_file']['tmp_name'], $_FILES['md_file']['type'], $_FILES['md_file']['name'])],
        CURLOPT_COOKIE => session_name() . '=' . session_id(),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($httpCode === 200 && $data && !empty($data['success'])) {
        $result = $data['message'];
    } else {
        $error = $data['error'] ?? 'Ошибка импорта';
    }
}

// Для AJAX-загрузки
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['session' => session_id(), 'session_name' => session_name()]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Импорт MD</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .import-section { background: #1e2a3e; padding: 24px; border-radius: 8px; max-width: 600px; margin-bottom: 20px; }
        .import-section h2 { margin-top: 0; }
        .import-result { padding: 12px 16px; border-radius: 6px; margin: 16px 0; }
        .import-success { background: #1b5e3f33; color: #4caf50; }
        .import-error { background: #7a202033; color: #e74c3c; }
        .drop-zone { border: 2px dashed #2a3650; border-radius: 8px; padding: 40px 20px; text-align: center; cursor: pointer; transition: border-color 0.2s; }
        .drop-zone:hover, .drop-zone.dragover { border-color: #4a8cff; background: rgba(74,140,255,0.05); }
        .drop-zone-icon { font-size: 48px; margin-bottom: 10px; }
        .drop-zone-text { color: #8a9bd5; }
        .drop-zone-text strong { color: #e2e8f0; }
        .preview { display: none; margin-top: 16px; padding: 12px; background: #0f1422; border-radius: 6px; max-height: 300px; overflow-y: auto; font-size: 13px; white-space: pre-wrap; }
        .btn { padding: 10px 24px; background: #3a4a6a; color: #e2e8f0; border: 1px solid #4a5a7a; border-radius: 6px; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #4a5a7a; }
        .btn-primary { background: #1b5e3f; border-color: #247a54; }
        .btn-primary:hover { background: #247a54; }
        .hidden { display: none; }
        .meta-preview { margin-top: 12px; padding: 10px; background: #1a2640; border-radius: 6px; font-size: 13px; }
        .meta-preview div { margin: 4px 0; }
        .meta-preview strong { color: #8a9bd5; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>📥 Импорт Markdown</h1>

    <?php if ($result): ?>
        <div class="import-result import-success"><?php echo $result; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="import-result import-error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <div class="import-section">
        <h2>Загрузить .md файл</h2>
        <form id="md-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="drop-zone" id="drop-zone">
                <div class="drop-zone-icon">📄</div>
                <div class="drop-zone-text">
                    <strong>Нажмите для выбора</strong> или перетащите .md файл<br>
                    <small>Поддерживаются файлы в формате Markdown (.md)</small>
                </div>
                <input type="file" name="md_file" id="md-file" accept=".md" style="display:none;">
            </div>

            <div id="file-info" class="hidden" style="margin-top:12px;">
                <span style="color:#8a9bd5;" id="file-name"></span>
                <a href="#" onclick="document.getElementById('md-file').value=''; document.getElementById('file-info').classList.add('hidden'); document.getElementById('preview').style.display='none'; return false;" style="color:#e74c3c;margin-left:8px;font-size:12px;">✕</a>
            </div>

            <div id="preview" class="preview"></div>
            <div id="meta-preview" class="meta-preview hidden"></div>

            <button type="submit" id="submit-btn" class="btn btn-primary hidden" style="margin-top:16px;">📥 Создать пост</button>
        </form>
    </div>

    <div class="import-section" style="background:#1a2640;">
        <h3>📝 Формат .md файла</h3>
        <pre style="font-size:13px;color:#8a9bd5;line-height:1.5;">
---
title: Заголовок поста
description: Краткое описание
tags: тег1, тег2
slug: moy-post
---

## Введение
Текст статьи...

- Список
- Элементы

**Жирный текст** и *курсив*

```php
echo 'Код';
```
        </pre>
    </div>

    <script>
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('md-file');
    const fileInfo = document.getElementById('file-info');
    const fileName = document.getElementById('file-name');
    const preview = document.getElementById('preview');
    const metaPreview = document.getElementById('meta-preview');
    const submitBtn = document.getElementById('submit-btn');

    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.classList.remove('dragover'); if (e.dataTransfer.files.length) fileInput.files = e.dataTransfer.files; handleFile(); });

    fileInput.addEventListener('change', handleFile);

    function handleFile() {
        const file = fileInput.files[0];
        if (!file || !file.name.endsWith('.md')) {
            showToast('Только .md файлы', 'error');
            return;
        }

        fileName.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        fileInfo.classList.remove('hidden');
        preview.style.display = 'block';
        submitBtn.classList.remove('hidden');

        const reader = new FileReader();
        reader.onload = function(e) {
            const text = e.target.result;
            preview.textContent = text;

            // Парсинг frontmatter для предпросмотра
            const fmMatch = text.match(/^---\s*\n(.*?)\n---\s*\n/s);
            if (fmMatch) {
                const lines = fmMatch[1].split('\n');
                let metaHtml = '<strong style="color:#4a8cff;">📋 Мета-данные:</strong>';
                lines.forEach(line => {
                    const m = line.match(/^(\w+):\s*(.*)$/);
                    if (m) metaHtml += '<div><strong>' + m[1] + ':</strong> ' + escHtml(m[2]) + '</div>';
                });
                metaPreview.innerHTML = metaHtml;
                metaPreview.classList.remove('hidden');
            } else {
                metaPreview.classList.add('hidden');
            }
        };
        reader.readAsText(file);
    }

    function escHtml(s) { return s ? s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }

    document.getElementById('md-form').addEventListener('submit', function(e) {
        submitBtn.disabled = true;
        submitBtn.textContent = '⏳ Импорт...';
    });

    function showToast(msg, type) {
        const toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;border-radius:6px;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,0.4);transition:opacity 0.3s;'
            + (type === 'error' ? 'background:#7a2020;color:#fff;' : 'background:#1b5e3f;color:#fff;');
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
    }
    </script>
</body>
</html>
