(function() {
    const emojis = ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '🥵', '🥶', '🥴', '😵', '🤯', '🤠', '🥳', '😎', '🤓', '🧐', '😕', '😟', '🙁', '☹️', '😮', '😯', '😲', '😳', '🥺', '😦', '😧', '😨', '😰', '😥', '😢', '😭', '😱', '😖', '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠', '🤬', '😈', '👿', '💀', '☠️', '💩', '🤡', '👹', '👺', '👻', '👽', '👾', '🤖', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾', '🙈', '🙉', '🙊'];

    function addEmojiAccordion(textarea) {
        if (textarea.parentNode.querySelector('.emoji-toolbar')) return;

        // Создаём контейнер
        const toolbar = document.createElement('div');
        toolbar.className = 'emoji-toolbar';

        // Кнопка
        const btn = document.createElement('button');
        btn.className = 'emoji-btn';
        btn.textContent = '😀';
        btn.type = 'button';
        btn.title = 'Вставить смайлик';
        toolbar.appendChild(btn);

        // Палитра
        const picker = document.createElement('div');
        picker.className = 'emoji-picker';
        emojis.forEach(emoji => {
            const span = document.createElement('span');
            span.textContent = emoji;
            span.style.cursor = 'pointer';
            span.style.padding = '4px';
            span.addEventListener('click', (e) => {
                e.stopPropagation();
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const val = textarea.value;
                textarea.value = val.substring(0, start) + emoji + val.substring(end);
                textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
                textarea.focus();
                // Не закрываем палитру автоматически – пользователь может захотеть вставить несколько смайлов
                // Если нужно закрывать – раскомментируйте: picker.classList.remove('visible');
            });
            picker.appendChild(span);
        });
        toolbar.appendChild(picker);

        // Вставляем после textarea
        textarea.insertAdjacentElement('afterend', toolbar);

        // Переключение аккордеона
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            picker.classList.toggle('visible');
        });

        // Закрытие при клике вне палитры
        document.addEventListener('click', function closePicker(e) {
            if (picker.classList.contains('visible') && !picker.contains(e.target) && e.target !== btn) {
                picker.classList.remove('visible');
            }
        });
    }

    function init() {
        const forms = ['#comment-form', '#reply-form'];
        forms.forEach(sel => {
            const form = document.querySelector(sel);
            if (form) {
                const ta = form.querySelector('textarea');
                if (ta && !ta.hasAttribute('data-emoji')) {
                    addEmojiAccordion(ta);
                    ta.setAttribute('data-emoji', 'true');
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();