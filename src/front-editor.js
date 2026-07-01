(function() {
    'use strict';

    const STORAGE_KEY = 'front_editor_draft';
    const pageId = window.frontEditorData?.pageId || 0;
    const pageType = window.frontEditorData?.pageType || 'post';
    const editMode = window.location.search.includes('edit=1');

    if (pageId) {
        if (pageType === 'post') window.currentPostId = pageId;
        else window.currentPageId = pageId;
    }

    if (!pageId) return;

    function createEditButton() {
        const btn = document.createElement('a');
        btn.id = 'fe-edit-btn';
        btn.href = '?edit=1';
        btn.innerHTML = '✏️';
        btn.title = 'Редактировать';
        btn.style.cssText = `
            position: fixed; bottom: 24px; right: 24px; z-index: 9999;
            width: 48px; height: 48px; background: #3a4a6a; color: #fff;
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-size: 22px; text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3); cursor: pointer;
            transition: transform 0.2s;
        `;
        btn.onmouseover = () => btn.style.transform = 'scale(1.1)';
        btn.onmouseout = () => btn.style.transform = '';
        document.body.appendChild(btn);
    }

    function createToolbar() {
        const bar = document.createElement('div');
        bar.id = 'fe-toolbar';
        bar.style.cssText = `
            position: sticky; top: 0; z-index: 9998;
            background: #1e2a3e; padding: 10px 16px;
            display: flex; gap: 8px; align-items: center;
            border-bottom: 1px solid #2a3650; flex-wrap: wrap;
        `;

        bar.innerHTML = `
            <span style="color:#8a9bd5;font-size:13px;margin-right:8px;">✏️ Редактирование</span>
            <button id="fe-save" style="padding:6px 16px;background:#1b5e3f;color:#fff;border:none;border-radius:4px;cursor:pointer;">💾 Сохранить</button>
            <button id="fe-preview" style="padding:6px 16px;background:#3a4a6a;color:#e2e8f0;border:1px solid #4a5a7a;border-radius:4px;cursor:pointer;">👁 Предпросмотр</button>
            <a href="?edit=0" style="padding:6px 16px;background:#2a3650;color:#8a9bd5;border:1px solid #2a3650;border-radius:4px;cursor:pointer;text-decoration:none;">✕ Отмена</a>
            <span id="fe-status" style="margin-left:auto;font-size:12px;color:#8a9bd5;"></span>
        `;
        document.body.prepend(bar);

        document.getElementById('fe-save').addEventListener('click', saveContent);
        if (document.getElementById('fe-preview')) {
            document.getElementById('fe-preview').addEventListener('click', togglePreview);
        }
    }

    function enterEditMode() {
        const contentArea = document.getElementById('fe-content');
        const titleEl = document.getElementById('fe-title');
        if (!contentArea || !titleEl) return;

        contentArea.style.display = 'none';

        const titleInput = document.createElement('input');
        titleInput.id = 'fe-title-input';
        titleInput.type = 'text';
        titleInput.value = titleEl.textContent.trim();
        titleInput.style.cssText = `
            width: 100%; font-size: 1.8em; padding: 8px 12px;
            background: #0f1422; color: #e2e8f0; border: 1px solid #2a3650;
            border-radius: 6px; margin-bottom: 12px; font-family: inherit;
        `;
        titleEl.style.display = 'none';
        titleEl.parentNode.insertBefore(titleInput, titleEl.nextSibling);

        const script = document.createElement('script');
        script.src = '/src/4SLASeditor.js';
        script.onload = () => {
            const editorDiv = document.createElement('div');
            editorDiv.id = 'fe-editor';
            editorDiv.contentEditable = 'true';
            editorDiv.innerHTML = contentArea.innerHTML;
            editorDiv.style.cssText = `
                min-height: 400px; padding: 16px; background: #0f1422;
                color: #e2e8f0; border: 1px solid #2a3650; border-radius: 6px;
                outline: none; line-height: 1.6; font-family: inherit;
            `;
            contentArea.parentNode.insertBefore(editorDiv, contentArea.nextSibling);

            const hiddenTA = document.createElement('textarea');
            hiddenTA.id = 'fe-hidden';
            hiddenTA.style.display = 'none';
            editorDiv.parentNode.appendChild(hiddenTA);

            window._simpleEditor = new SimpleEditor('fe-editor', 'fe-hidden');
            setStatus('✅ Редактор загружен', '#4caf50');

            restoreDraft();
            initContextMenu(editorDiv);
        };
        document.head.appendChild(script);

        addSeoBlock();
    }

    function initContextMenu(editor) {
        const menu = document.createElement('div');
        menu.id = 'fe-ctx-menu';
        menu.style.cssText = `
            position: fixed; z-index: 10001; display: none;
            background: #1e2a3e; border: 1px solid #2a3650;
            border-radius: 8px; padding: 6px 0; min-width: 210px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            font-size: 13px; color: #e2e8f0;
        `;
        menu.innerHTML = `
            <div class="fe-ctx-item" data-cmd="bold"><span style="font-weight:bold">B</span> Жирный <span style="float:right;color:#6a7a9a">Ctrl+B</span></div>
            <div class="fe-ctx-item" data-cmd="italic"><span style="font-style:italic">I</span> Курсив <span style="float:right;color:#6a7a9a">Ctrl+I</span></div>
            <div class="fe-ctx-item" data-cmd="underline"><span style="text-decoration:underline">U</span> Подчёркнутый <span style="float:right;color:#6a7a9a">Ctrl+U</span></div>
            <div class="fe-ctx-item" data-cmd="strikeThrough"><span style="text-decoration:line-through">S</span> Зачёркнутый</div>
            <div class="fe-ctx-divider"></div>
            <div class="fe-ctx-item" data-cmd="formatBlock" data-val="h2">H2 Заголовок 2</div>
            <div class="fe-ctx-item" data-cmd="formatBlock" data-val="h3">H3 Заголовок 3</div>
            <div class="fe-ctx-item" data-cmd="formatBlock" data-val="blockquote">❝ Цитата</div>
            <div class="fe-ctx-divider"></div>
            <div class="fe-ctx-item" data-cmd="insertUnorderedList">📋 Марк. список</div>
            <div class="fe-ctx-item" data-cmd="insertOrderedList">📋 Нум. список</div>
            <div class="fe-ctx-divider"></div>
            <div class="fe-ctx-item" data-cmd="justifyLeft">⬅ Влево</div>
            <div class="fe-ctx-item" data-cmd="justifyCenter">⬅ Центр</div>
            <div class="fe-ctx-item" data-cmd="justifyRight">⬅ Вправо</div>
            <div class="fe-ctx-divider"></div>
            <div class="fe-ctx-item" data-cmd="link">🔗 Вставить ссылку</div>
            <div class="fe-ctx-item" data-cmd="image">🖼 Загрузить фото</div>
            <div class="fe-ctx-item" data-cmd="code">💻 Вставить код</div>
            <div class="fe-ctx-divider"></div>
            <div class="fe-ctx-item" data-cmd="cut">✂️ Вставить cut</div>
        `;

        const style = document.createElement('style');
        style.textContent = `
            .fe-ctx-item { padding: 8px 16px; cursor: pointer; transition: background 0.15s; display: flex; align-items: center; gap: 8px; }
            .fe-ctx-item:hover { background: #2a3650; }
            .fe-ctx-divider { height: 1px; background: #2a3650; margin: 4px 0; }
        `;
        document.head.appendChild(style);
        document.body.appendChild(menu);

        editor.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            const x = Math.min(e.clientX, window.innerWidth - 220);
            const y = Math.min(e.clientY, window.innerHeight - 400);
            menu.style.left = x + 'px';
            menu.style.top = y + 'px';
            menu.style.display = 'block';
        });

        document.addEventListener('click', (e) => {
            if (!menu.contains(e.target)) {
                menu.style.display = 'none';
            }
        });

        menu.addEventListener('click', (e) => {
            const item = e.target.closest('.fe-ctx-item');
            if (!item) return;

            const cmd = item.dataset.cmd;
            const val = item.dataset.val;
            menu.style.display = 'none';

            if (cmd === 'link') {
                if (window._simpleEditor) window._simpleEditor.insertLink();
                return;
            }
            if (cmd === 'image') {
                if (window._simpleEditor) window._simpleEditor.uploadImage();
                return;
            }
            if (cmd === 'code') {
                if (window._simpleEditor) window._simpleEditor.insertCodeBlock();
                return;
            }
            if (cmd === 'cut') {
                insertCut();
                return;
            }

            if (window._simpleEditor) {
                window._simpleEditor.execCommand(cmd, val);
            } else {
                document.execCommand(cmd, false, val || null);
            }
            editor.focus();
        });
    }

    function insertCut() {
        const editor = document.getElementById('fe-editor');
        if (!editor) return;

        const sel = window.getSelection();
        if (!sel.rangeCount) return;

        const range = sel.getRangeAt(0);
        const cutDiv = document.createElement('div');
        cutDiv.className = 'fe-cut';
        cutDiv.contentEditable = 'false';
        cutDiv.style.cssText = `
            text-align: center; padding: 12px; margin: 16px 0;
            border: 2px dashed #4a5a7a; border-radius: 6px;
            color: #8a9bd5; font-size: 13px; cursor: default;
            user-select: none;
        `;
        cutDiv.innerHTML = '— ✂️ Cut — далее полный текст —';

        range.deleteContents();
        range.insertNode(cutDiv);

        const newRange = document.createRange();
        newRange.setStartAfter(cutDiv);
        newRange.collapse(true);
        sel.removeAllRanges();
        sel.addRange(newRange);

        if (window._simpleEditor) window._simpleEditor.syncToHidden();
    }

    function addSeoBlock() {
        const seoDiv = document.createElement('div');
        seoDiv.id = 'fe-seo';
        seoDiv.style.cssText = `
            margin-top: 20px; padding: 16px; background: #1a2640;
            border-radius: 8px; border: 1px solid #2a3650;
        `;
        seoDiv.innerHTML = `
            <div style="font-weight:bold;margin-bottom:8px;color:#8a9bd5;">📝 SEO-настройки</div>
            <input id="fe-meta-title" placeholder="Meta Title" style="width:100%;padding:8px;margin-bottom:6px;background:#0f1422;color:#e2e8f0;border:1px solid #2a3650;border-radius:4px;" value="${window.frontEditorData?.metaTitle || ''}">
            <input id="fe-meta-desc" placeholder="Meta Description" style="width:100%;padding:8px;margin-bottom:6px;background:#0f1422;color:#e2e8f0;border:1px solid #2a3650;border-radius:4px;" value="${window.frontEditorData?.metaDesc || ''}">
            <input id="fe-meta-kw" placeholder="Meta Keywords (через запятую)" style="width:100%;padding:8px;background:#0f1422;color:#e2e8f0;border:1px solid #2a3650;border-radius:4px;" value="${window.frontEditorData?.metaKw || ''}">
        `;
        document.getElementById('fe-content')?.parentNode?.insertBefore(seoDiv, document.getElementById('fe-content')?.nextSibling);
    }

    async function saveContent() {
        const btn = document.getElementById('fe-save');
        btn.disabled = true;
        setStatus('⏳ Сохранение...', '#ffc107');

        let content = '';
        let title = '';

        const titleInput = document.getElementById('fe-title-input');
        if (titleInput) title = titleInput.value.trim();

        if (window._simpleEditor) {
            window._simpleEditor.syncToHidden();
            content = document.getElementById('fe-hidden')?.value || '';
        } else {
            content = document.getElementById('fe-content')?.innerHTML || '';
        }

        if (!title) { setStatus('⚠️ Заголовок не может быть пустым', '#ffc107'); btn.disabled = false; return; }

        const metaTitle = document.getElementById('fe-meta-title')?.value || '';
        const metaDesc = document.getElementById('fe-meta-desc')?.value || '';
        const metaKw = document.getElementById('fe-meta-kw')?.value || '';

        try {
            const resp = await fetch('/api/quick-save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: pageType,
                    id: pageId,
                    title: title,
                    content: content,
                    meta_title: metaTitle,
                    meta_description: metaDesc,
                    meta_keywords: metaKw
                })
            });
            const data = await resp.json();

            if (data.success) {
                setStatus('✅ ' + (data.message || 'Сохранено'), '#4caf50');
                localStorage.removeItem(STORAGE_KEY + '_' + pageId);
                setTimeout(() => { window.location.href = data.url; }, 1500);
            } else {
                setStatus('❌ ' + (data.error || 'Ошибка'), '#e74c3c');
            }
        } catch(e) {
            setStatus('❌ Ошибка сети', '#e74c3c');
        }
        btn.disabled = false;
    }

    function togglePreview() {
        const previewBtn = document.getElementById('fe-preview');
        const isPreview = previewBtn.textContent === '👁 Предпросмотр';

        if (isPreview) {
            if (window._simpleEditor) {
                document.getElementById('fe-editor').style.display = 'none';
                document.getElementById('fe-content').style.display = 'block';
                window._simpleEditor.syncToHidden();
                document.getElementById('fe-content').innerHTML = document.getElementById('fe-hidden')?.value || '';
            }
            previewBtn.textContent = '✏️ Редактор';
        } else {
            if (window._simpleEditor) {
                document.getElementById('fe-editor').style.display = 'block';
                document.getElementById('fe-content').style.display = 'none';
            }
            previewBtn.textContent = '👁 Предпросмотр';
        }
    }

    function restoreDraft() {
        try {
            const draft = localStorage.getItem(STORAGE_KEY + '_' + pageId);
            if (draft && window._simpleEditor) {
                const data = JSON.parse(draft);
                const editor = document.getElementById('fe-editor');
                if (editor && data.content) {
                    editor.innerHTML = data.content;
                    window._simpleEditor.syncToHidden();
                }
                const titleInput = document.getElementById('fe-title-input');
                if (titleInput && data.title) titleInput.value = data.title;
                setStatus('✅ Черновик восстановлен', '#4caf50');
            }
        } catch(e) {}
    }

    function autoSaveDraft() {
        const titleInput = document.getElementById('fe-title-input');
        const editor = document.getElementById('fe-editor');
        if (!titleInput && !editor) return;

        try {
            const draft = {
                title: titleInput?.value || '',
                content: editor?.innerHTML || '',
                saved: new Date().toISOString()
            };
            localStorage.setItem(STORAGE_KEY + '_' + pageId, JSON.stringify(draft));
        } catch(e) {}
    }

    function setStatus(msg, color) {
        const el = document.getElementById('fe-status');
        if (el) { el.textContent = msg; el.style.color = color || '#8a9bd5'; }
    }

    let autoSaveTimer = null;
    document.addEventListener('input', () => {
        if (autoSaveTimer) clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(autoSaveDraft, 5000);
    });

    if (editMode) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                createToolbar();
                enterEditMode();
            });
        } else {
            createToolbar();
            enterEditMode();
        }
    } else {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', createEditButton);
        } else {
            createEditButton();
        }
    }
})();
