/**
 * Инициализация GrapesJS (визуальный конструктор страниц)
 * Подключается на страницах с ?edit=1 для page.php
 */

function initGrapesEditor(pageId, content) {
    if (typeof grapesjs === 'undefined') return;

    var editor = grapesjs.init({
        container: '#gjs-editor',
        fromElement: true,
        height: '600px',
        storageManager: {
            type: 'remote',
            stepsBeforeSave: 1,
            urlStore: '/api/grapes-save.php',
            urlLoad: '/api/grapes-load.php?id=' + pageId,
            params: { id: pageId }
        },
        canvas: {
            styles: [
                'https://time404.ru/css/style.css'
            ]
        },
        plugins: [],
        pluginsOpts: {}
    });

    return editor;
}
