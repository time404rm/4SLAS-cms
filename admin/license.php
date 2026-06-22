<?php
require_once '../includes/functions.php';
if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Лицензия MIT';
include __DIR__ . '/includes/admin_menu.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Лицензия MIT - Админ-панель</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .license-container {
            max-width: 900px;
            margin: 20px auto;
            background: #1e2a3e;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .license-section {
            margin-bottom: 30px;
        }
        .license-section h2 {
            color: #60a5fa;
            border-bottom: 1px solid #2a3650;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .license-text {
            background: #0f1422;
            padding: 18px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
            color: #e2e8f0;
            border: 1px solid #2a3650;
        }
        .copy-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 10px;
            font-size: 0.8rem;
        }
        .copy-btn:hover {
            background: #1e40af;
        }
        hr {
            border-color: #2a3650;
            margin: 20px 0;
        }
    </style>
</head>
<body class="admin">
    <h1>Лицензия MIT</h1>
    <div class="license-container">
        <div class="license-section">
            <h2>Оригинальный текст (английский)</h2>
            <div class="license-text" id="original-text">
                Copyright © 2026 RuslanAbuzyaroff

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
            </div>
            <button class="copy-btn" data-target="original-text">📋 Копировать оригинал</button>
        </div>

        <hr>

        <div class="license-section">
            <h2>Машинный перевод (русский)</h2>
            <div class="license-text" id="russian-text">
                Авторское право © 2026 RuslanAbuzyaroff

Настоящим предоставляется бесплатное разрешение любому лицу, получающему копию данного программного обеспечения и связанных с ним файлов документации (далее “Программное обеспечение”), осуществлять операции с Программным обеспечением без ограничений, включая, без ограничения, права на использование, копирование, модификацию, объединение, публикацию, распространение, сублицензирование и/или продажу копий Программного обеспечения, и разрешать лицам, которым предоставляется Программное обеспечение, делать это при соблюдении следующих условий:

Вышеупомянутое уведомление об авторских правах и настоящее уведомление о разрешении должны быть включены во все копии или существенные части Программного обеспечения.

ПРОГРАММНОЕ ОБЕСПЕЧЕНИЕ ПРЕДОСТАВЛЯЕТСЯ «КАК ЕСТЬ», БЕЗ КАКИХ-ЛИБО ГАРАНТИЙ, ЯВНЫХ ИЛИ ПОДРАЗУМЕВАЕМЫХ, ВКЛЮЧАЯ, ПОМИМО ПРОЧЕГО, ГАРАНТИИ ТОВАРНОГО СОСТОЯНИЯ, ПРИГОДНОСТИ ДЛЯ ИСПОЛЬЗОВАНИЯ В КОНКРЕТНЫХ ЦЕЛЯХ И НЕНАРУШЕНИЯ АВТОРСКИХ ПРАВ. НИ ПРИ КАКИХ ОБСТОЯТЕЛЬСТВАХ АВТОРЫ ИЛИ ПРАВООБЛАДАТЕЛИ НЕ НЕСУТ ОТВЕТСТВЕННОСТИ ЗА ЛЮБЫЕ ПРЕТЕНЗИИ, УБЫТКИ ИЛИ ИНЫЕ МАТЕРИАЛЬНЫЕ УБЫТКИ, ВОЗНИКШИЕ В СВЯЗИ С ИСПОЛЬЗОВАНИЕМ ИЛИ ИНЫМ ПРИМЕНЕНИЕМ ПРОГРАММНОГО ОБЕСПЕЧЕНИЯ, А ТАКЖЕ В СВЯЗИ С ДОСТУПОМ К НЕМУ ИЛИ ЕГО ИСПОЛЬЗОВАНИЕМ.
            </div>
            <button class="copy-btn" data-target="russian-text">📋 Копировать перевод</button>
        </div>
    </div>

    <script>
        document.querySelectorAll('.copy-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const textElement = document.getElementById(targetId);
                const text = textElement.innerText;
                navigator.clipboard.writeText(text).then(() => {
                    const originalText = this.innerText;
                    this.innerText = '✅ Скопировано!';
                    setTimeout(() => {
                        this.innerText = originalText;
                    }, 2000);
                }).catch(() => {
                    alert('Не удалось копировать текст');
                });
            });
        });
    </script>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>