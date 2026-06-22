document.addEventListener('click', function(e) {
    const btn = e.target.closest('.like-btn, .like-btn-list');
    if (!btn || btn.disabled) return;

    const postId = btn.dataset.postId;
    const formData = new FormData();
    formData.append('post_id', postId);

    fetch('/api/like_post.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '&#128077; ' + data.likes;
            btn.disabled = true;
        } else {
            alert(data.error);
        }
    })
    .catch(err => {
        alert('Ошибка: ' + err.message);
    });
});
