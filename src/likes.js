document.addEventListener('click', function(e) {
    const btn = e.target.closest('.like-btn, .like-btn-list');
    if (!btn || btn.disabled) return;

    const postId = btn.dataset.postId;
    // Гость (кнопка без data-post-id) → на форму входа
    if (!postId) {
        window.location.href = '/login.php';
        return;
    }
    // Защита от повторного клика, пока идёт запрос
    if (btn.dataset.busy) return;
    btn.dataset.busy = '1';

    const formData = new FormData();
    formData.append('post_id', postId);

    fetch('/api/like_post.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Первый лайк — без модалки, просто обновляем счётчик
            btn.innerHTML = '&#128077; ' + data.likes;
        } else {
            // Повторная попытка — показываем «Вы уже поставили лайк»
            alert(data.error);
        }
    })
    .catch(err => {
        alert('Ошибка: ' + err.message);
    })
    .finally(function() {
        delete btn.dataset.busy;
    });
});
