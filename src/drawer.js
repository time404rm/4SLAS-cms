/**
 * Управление левой выдвижной панелью (drawer)
 * - открытие/закрытие по гамбургеру (toggle)
 * - закрытие по крестику
 * - открытие при наведении на левый край экрана (десктоп)
 * - открытие/закрытие свайпом (тач)
 * - без оверлея, без закрытия по клику вне, без блокировки скролла
 */
document.addEventListener('DOMContentLoaded', function() {
    const menuIcon = document.getElementById('menu-icon');
    const drawer = document.getElementById('drawer');
    const closeBtn = document.getElementById('drawer-close');
    const siteWrapper = document.querySelector('.site-wrapper');

    let touchStartX = 0;
    let touchEndX = 0;
    const minSwipeDistance = 50;

    let hoverTimeout = null;
    let isHoverEnabled = true;

    function openDrawer() {
        if (!drawer) return;
        drawer.classList.add('open');
        isHoverEnabled = false;
    }

    function closeDrawer() {
        if (!drawer) return;
        drawer.classList.remove('open');
        isHoverEnabled = true;
    }

    function toggleDrawer() {
        if (drawer.classList.contains('open')) {
            closeDrawer();
        } else {
            openDrawer();
        }
    }

    // Открытие при наведении на левый край (только мобильные, < 769px)
    function onMouseMove(e) {
        if (window.innerWidth >= 769) return;
        if (!drawer.classList.contains('open') && isHoverEnabled) {
            const mouseX = e.clientX;
            if (mouseX <= 50) {
                if (hoverTimeout) clearTimeout(hoverTimeout);
                hoverTimeout = setTimeout(() => {
                    openDrawer();
                }, 200);
            } else {
                if (hoverTimeout) {
                    clearTimeout(hoverTimeout);
                    hoverTimeout = null;
                }
            }
        }
    }

    // Гамбургер и крестик
    if (menuIcon) menuIcon.addEventListener('click', toggleDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);

    // Закрытие по Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) {
            closeDrawer();
        }
    });

    // Свайпы (тач)
    function handleTouchStart(e) {
        touchStartX = e.changedTouches[0].screenX;
    }
    function handleTouchEnd(e) {
        if (!drawer) return;
        touchEndX = e.changedTouches[0].screenX;
        const deltaX = touchEndX - touchStartX;
        const isOpen = drawer.classList.contains('open');

        if (deltaX > minSwipeDistance && !isOpen) {
            openDrawer();
        } else if (deltaX < -minSwipeDistance && isOpen) {
            closeDrawer();
        }
    }

    if (siteWrapper) {
        siteWrapper.addEventListener('touchstart', handleTouchStart, { passive: true });
        siteWrapper.addEventListener('touchend', handleTouchEnd, { passive: true });
    } else {
        document.body.addEventListener('touchstart', handleTouchStart, { passive: true });
        document.body.addEventListener('touchend', handleTouchEnd, { passive: true });
    }

    // Отслеживание мыши для открытия по левому краю
    window.addEventListener('mousemove', onMouseMove);
});