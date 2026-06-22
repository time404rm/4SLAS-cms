<div class="auth-links">
                <?php if (isset($_SESSION['user_id'])): ?>
    <?php if (canAccessAdmin()): ?>
        <a href="<?php echo SITE_URL; ?>/admin/">
            <img src="<?php echo SITE_URL; ?>/templates/icons/adm.svg" alt="Админ-панель" class="icons_st">
        </a>
    <?php endif; ?>
    <a href="<?php echo SITE_URL; ?>/logout.php">
        <img src="<?php echo SITE_URL; ?>/templates/icons/out.svg" alt="выход" class="icons_st">
    </a>
<?php else: ?>
    <a href="<?php echo SITE_URL; ?>/register.php">
        <img src="<?php echo SITE_URL; ?>/templates/icons/reg.svg" alt="Регистрация" class="icons_st">
    </a>
    <a href="<?php echo SITE_URL; ?>/login.php">
        <img src="<?php echo SITE_URL; ?>/templates/icons/login.svg" alt="Логин" class="icons_st">
    </a>
<?php endif; ?>
    <a href="<?php echo SITE_URL; ?>/rss.php">
        <img src="<?php echo SITE_URL; ?>/templates/icons/rss.svg" alt="RSS" class="icons_st">
    </a>
            </div>
<?php
// 4SLAS-cms
// Автор: ruslanabuzyaroff
// Telegram: https://t.me/time4_04
// Сайт: time404.ru
// E-mail: ruslan@time404.ru
// Лицензия: MIT
// Если этот CMS понравился, можете оставить монетку автору на чашечку кофе или дать ссылку на проект.
// Если планируете модифицировать и улучшать, буду рад если поделитесь доработками
?>