document.addEventListener('click', function(e) {
    var btn = e.target.closest('.share-btn');
    if (btn) {
        e.stopPropagation();
        var container = btn.closest('.share-container');
        var dropdown = container.querySelector('.share-dropdown');
        if (!dropdown) return;

        var isActive = dropdown.classList.contains('active');
        closeAllShareDropdowns();
        if (!isActive) {
            dropdown.classList.add('active');
            var url = encodeURIComponent(btn.dataset.url || window.location.href);
            var title = encodeURIComponent(btn.dataset.title || document.title);
            var copyLink = dropdown.querySelector('.share-link.copy-link');
            var shareLinks = dropdown.querySelectorAll('.share-link[data-share]');
            var shareUrls = {
                telegram: 'https://t.me/share/url?url=' + url + '&text=' + title,
                vk: 'https://vk.com/share.php?url=' + url + '&title=' + title,
                whatsapp: 'https://api.whatsapp.com/send?text=' + title + '%20' + url,
                twitter: 'https://twitter.com/intent/tweet?text=' + title + '&url=' + url,
                ok: 'https://connect.ok.ru/offer?url=' + url + '&title=' + title
            };
            shareLinks.forEach(function(link) {
                var type = link.getAttribute('data-share');
                if (shareUrls[type]) {
                    link.setAttribute('href', shareUrls[type]);
                    link.setAttribute('target', '_blank');
                    link.setAttribute('rel', 'noopener noreferrer');
                }
            });
            if (copyLink) {
                var oldHandler = copyLink._clickHandler;
                if (oldHandler) copyLink.removeEventListener('click', oldHandler);
                var handler = function(ce) {
                    ce.preventDefault();
                    navigator.clipboard.writeText(btn.dataset.url || window.location.href).then(function() {
                        var orig = copyLink.innerHTML;
                        copyLink.innerHTML = '&#10003;&#65039; ' + (copyLink.dataset.copiedText || 'Copied!');
                        setTimeout(function() { copyLink.innerHTML = orig; }, 2000);
                    })['catch'](function() {
                        alert('Copy failed');
                    });
                };
                copyLink._clickHandler = handler;
                copyLink.addEventListener('click', handler);
            }
        }
        return;
    }

    var closeBtn = e.target.closest('.share-close');
    if (closeBtn) {
        closeBtn.closest('.share-dropdown').classList.remove('active');
        return;
    }

    if (!e.target.closest('.share-dropdown')) {
        closeAllShareDropdowns();
    }
});

function closeAllShareDropdowns() {
    document.querySelectorAll('.share-dropdown.active').forEach(function(d) {
        d.classList.remove('active');
    });
}
