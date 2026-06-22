(function() {
    function loadVKScript(containerId, pageId, appId) {
        var vk = document.createElement('script');
        vk.src = 'https://vk.com/js/api/openapi.js?169';
        vk.onload = function() {
            if (appId) VK.init({ apiId: appId });
            var options = { limit: 10, attach: "*" };
            VK.Widgets.Comments(containerId, options, pageId);
        };
        document.head.appendChild(vk);
    }

    document.addEventListener('DOMContentLoaded', function() {
        var vkEnabled = window.vkEnabled === true;
        var vkAppId = window.vkAppId || '';
        var postId = window.postId || 0;

        if (!vkEnabled) return;

        // Переключение для авторизованных
        var switchBtns = document.querySelectorAll('.comment-switch-btn');
        var localBlock = document.getElementById('local-comments-block');
        var vkBlock = document.getElementById('vk-comments-block');
        var vkLoaded = false;

        if (switchBtns.length && localBlock && vkBlock) {
            switchBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    var mode = this.dataset.mode;
                    switchBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    if (mode === 'local') {
                        localBlock.style.display = 'block';
                        vkBlock.style.display = 'none';
                    } else {
                        localBlock.style.display = 'none';
                        vkBlock.style.display = 'block';
                        if (!vkLoaded) {
                            if (typeof VK === 'undefined') {
                                loadVKScript('vk_comments_auth', postId, vkAppId);
                            } else {
                                var options = { limit: 10, attach: "*" };
                                VK.Widgets.Comments('vk_comments_auth', options, postId);
                            }
                            vkLoaded = true;
                        }
                    }
                });
            });
        }

        // Для неавторизованных – кнопка "Комментировать через ВК"
        var vkGuestBtn = document.getElementById('vk-guest-btn');
        var vkGuestContainer = document.getElementById('vk-guest-container');
        var guestButtonsContainer = document.getElementById('guest-buttons-container');
        var vkInfoMessage = document.getElementById('vk-info-message');

        if (vkGuestBtn && vkGuestContainer && guestButtonsContainer && vkInfoMessage) {
            vkGuestBtn.addEventListener('click', function() {
                guestButtonsContainer.style.display = 'none';
                vkInfoMessage.style.display = 'block';
                vkGuestContainer.style.display = 'block';
                var container = document.getElementById('vk_comments_guest');
                if (container && container.innerHTML.trim() === '') {
                    loadVKScript('vk_comments_guest', postId, vkAppId);
                } else if (typeof VK !== 'undefined') {
                    var options = { limit: 10, attach: "*" };
                    VK.Widgets.Comments('vk_comments_guest', options, postId);
                }
            });
        }
    });
})();