/**
 * Комментарии: отправка обычных комментариев и ответов с упоминанием автора
 */

document.addEventListener('DOMContentLoaded', function() {
    // Основная форма комментария
    const mainForm = document.getElementById('comment-form');
    // Форма ответа
    const replyContainer = document.getElementById('reply-form-container');
    const replyForm = document.getElementById('reply-form');
    const replyParentId = document.getElementById('reply-parent-id');
    const cancelReply = document.getElementById('cancel-reply');
    const replyTextarea = replyForm ? replyForm.querySelector('textarea') : null;

    // Обработка кликов по кнопкам "Ответить" (делегирование)
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('reply-btn')) {
            const commentId = e.target.dataset.id;
            const authorName = e.target.dataset.author;
            if (replyParentId) replyParentId.value = commentId;
            if (replyContainer) replyContainer.style.display = 'block';
            if (replyTextarea) {
                // Вставляем упоминание @Имя и пробел, затем фокус
                replyTextarea.value = 'ответ для @' + authorName + ': ';
                replyTextarea.focus();
            }
            if (replyContainer) replyContainer.scrollIntoView({ behavior: 'smooth' });
        }
    });

    // Отмена ответа: скрыть форму и очистить поля
    if (cancelReply) {
        cancelReply.addEventListener('click', function() {
            if (replyContainer) replyContainer.style.display = 'none';
            if (replyParentId) replyParentId.value = '0';
            if (replyTextarea) replyTextarea.value = '';
        });
    }

    // Отправка обычного комментария (корневого)
    if (mainForm) {
        mainForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const postId = this.dataset.postId;
            formData.append('post_id', postId);
            try {
                const response = await fetch('/api/add_comment.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Ошибка: ' + result.error);
                }
            } catch (err) {
                alert('Ошибка соединения');
            }
        });
    }

    // Отправка ответа
    if (replyForm) {
        replyForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const postId = mainForm ? mainForm.dataset.postId : null;
            formData.append('post_id', postId);
            try {
                const response = await fetch('/api/add_comment.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Ошибка: ' + result.error);
                }
            } catch (err) {
                alert('Ошибка соединения');
            }
        });
    }
});