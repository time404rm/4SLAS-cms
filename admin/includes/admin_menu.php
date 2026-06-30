<script>
(function(){
    var vp = document.querySelector('meta[name="viewport"]');
    if (!vp) {
        var m = document.createElement('meta');
        m.name = 'viewport';
        m.content = 'width=device-width, initial-scale=1.0';
        document.head.appendChild(m);
    }
    var fav = document.querySelector('link[rel*="icon"]');
    if (fav) return;
    <?php
    $favicon = getSetting('favicon');
    if ($favicon && file_exists($_SERVER['DOCUMENT_ROOT'] . $favicon)):
    ?>
    var l1 = document.createElement('link'); l1.rel = 'icon'; l1.href = '<?php echo SITE_URL . $favicon; ?>';
    var l2 = document.createElement('link'); l2.rel = 'shortcut icon'; l2.href = '<?php echo SITE_URL . $favicon; ?>';
    <?php else: ?>
    var l1 = document.createElement('link'); l1.rel = 'icon'; l1.href = '<?php echo SITE_URL; ?>/favicon.ico'; l1.type = 'image/x-icon';
    var l2 = document.createElement('link'); l2.rel = 'shortcut icon'; l2.href = '<?php echo SITE_URL; ?>/favicon.ico';
    <?php endif; ?>
    document.head.appendChild(l1);
    document.head.appendChild(l2);
})();
</script>
<div class="admin-nav">
    <ul>
        <?php if (canAccessAdmin()): ?><li><a href="index.php">📊 ДАШБОРД</a></li><?php endif; ?>

             <?php if (canManagePosts()): ?>
        <li class="dropdown">
            <a href="#" class="dropdown-toggle">📝 БЛОГ ▾</a>
            <ul class="dropdown-menu">
                <li><a href="posts.php">Посты</a></li>
                <li><a href="categories.php">Категории</a></li>
                <?php if (canManageComments()): ?><li><a href="comments.php">Комментарии</a></li><?php endif; ?>
                <li><a href="blocks.php">Свободные блоки</a></li>
                <li><a href="social.php">Соцсети</a></li>
                <?php if (isAdmin()): ?><li><a href="contact.php">Контакты</a></li><?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        
        <?php if (canManagePosts()): ?><li><a href="menu.php">🍔 МЕНЮ</a></li><?php endif; ?>
        <?php if (canManagePosts()): ?><li><a href="pages.php">📄 СТРАНИЦЫ</a></li><?php endif; ?>

 <?php if (canManageComments()): ?>
        <li class="dropdown">
            <a href="#" class="dropdown-toggle">⚙️ НАСТРОЙКИ ▾</a>
            <ul class="dropdown-menu">
                <?php if (isAdmin()): ?><li><a href="settings.php">Общие</a></li><?php endif; ?>
                <?php if (isAdmin()): ?><li><a href="users.php">Пользователи</a></li><?php endif; ?>
                <?php if (isAdmin()): ?><li><a href="notifications.php">Уведомления</a></li><?php endif; ?>
                <?php if (isAdmin()): ?><li><a href="mailer.php">SMTP</a></li><?php endif; ?>
                <?php if (isAdmin()): ?><li><a href="seo.php">SEO</a></li><?php endif; ?>
                <?php if (isAdmin()): ?><li><a href="cache.php">Кеш</a></li><?php endif; ?>
                <?php if (isAdmin()): ?><li><a href="sitemap.php">Sitemap</a></li><?php endif; ?>
                <?php if (isAdmin()): ?><li><a href="widgets.php">Виджеты</a></li><?php endif; ?>
                <?php if (isAdmin()): ?><li><a href="redirect.php">Редиректы</a></li><?php endif; ?>
                <?php if (isAdmin()): ?><li><a href="seo-score.php">📊 SEO Score</a></li><?php endif; ?>
                <?php if (isAdmin()): ?><li><a href="import-md.php">📥 Импорт MD</a></li><?php endif; ?>
                <?php if (isAdmin()): ?><li><a href="404-report.php">🔍 404 мониторинг</a></li><?php endif; ?>
                <?php if (isAdmin()): ?><li><a href="backup.php">Backup</a></li><?php endif; ?>
                <?php if (isAdmin()): ?><li><a href="update.php">🔄 Обновление</a></li><?php endif; ?>
                    
            </ul>
        </li>
        <?php endif; ?>
        

        <li><a href="../index.php" target="_blank">🌐 НА САЙТ</a></li>
        <li><a href="../logout.php">🚪 ВЫХОД</a></li>
    </ul>
</div>