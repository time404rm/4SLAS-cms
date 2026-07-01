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
</main>
<footer>
    <div class="footer-up">
        <?php echo renderSocialIcons(); ?>
    <?php
    $footerDb = getDb();
    try {
        $blockStmt = $footerDb->prepare("SELECT content FROM custom_blocks WHERE position = ? AND is_active = 1 ORDER BY id ASC LIMIT 1");
        $blockStmt->execute(['footer']);
        $blockContent = $blockStmt->fetchColumn();
        if ($blockContent) echo $blockContent;
    } catch (\PDOException $e) {}
    ?>
</div>
<div class="footer-down">
    <p>&copy; <?php echo date('Y'); ?> <?php echo h(getSetting('site_name')); ?>. <?php echo __('all_rights_reserved'); ?></p>
</div>
</footer>

</div> <!-- .site-wrapper -->

<!-- Подключаем все библиотеки и скрипты (defer — не блокируют рендеринг) -->
 <script defer src="<?php echo SITE_URL; ?>/src/social-link.js"></script>

<!-- Lightbox (содержит jQuery) -->
<script defer src="<?php echo SITE_URL; ?>/assets/lightbox/js/lightbox-plus-jquery.min.js"></script>

<!-- Highlight.js -->
<script defer src="<?php echo SITE_URL; ?>/assets/highlight/highlight.min.js"></script>
<script defer src="<?php echo SITE_URL; ?>/assets/highlight/js/highlightjs-line-numbers.min.js"></script>

<!-- Основные скрипты сайта -->
<?php if (isset($includeInfiniteScroll) && $includeInfiniteScroll): ?>
<script defer src="<?php echo SITE_URL; ?>/js_loader.php?file=infinite-scroll.js"></script>
<?php endif; ?>
<?php if (isset($includeComments) && $includeComments): ?>
<script defer src="<?php echo SITE_URL; ?>/js_loader.php?file=comments.js"></script>
<?php endif; ?>
<script defer src="<?php echo SITE_URL; ?>/js_loader.php?file=likes.js"></script>
<script defer src="<?php echo SITE_URL; ?>/js_loader.php?file=drawer.js"></script>
<script defer src="<?php echo SITE_URL; ?>/js_loader.php?file=emoji-picker.js"></script>

<!-- Инициализация Lightbox -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lightbox !== 'undefined') {
            lightbox.option({
                'albumLabel': 'Фото %1 из %2',
                'alwaysShowNavOnTouchDevices': true
            });
        }
    });
</script>

<!-- Инициализация Highlight.js (после загрузки всех скриптов) -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof hljs !== 'undefined') {
            hljs.highlightAll();
            if (typeof hljs.initLineNumbersOnLoad === 'function') {
                hljs.initLineNumbersOnLoad();
            } else if (typeof hljs.lineNumbersBlock === 'function') {
                document.querySelectorAll('pre code').forEach(function(block) {
                    hljs.lineNumbersBlock(block);
                });
            }
        }
    });
</script>

<?php
// Счётчик просмотров страниц
if (!defined('PAGE_VIEWS_RECORDED')) {
    define('PAGE_VIEWS_RECORDED', true);
    $db = getDb();
    $tableExists = $db->query("SHOW TABLES LIKE 'page_views'")->rowCount() > 0;
    if ($tableExists) {
        $currentUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $stmt = $db->prepare("INSERT INTO page_views (page_url, visit_date, visits) VALUES (?, CURDATE(), 1) ON DUPLICATE KEY UPDATE visits = visits + 1");
        $stmt->execute([$currentUrl]);
    }
}
?>

<!-- Cookie consent banner -->
<div id="cookie-consent" class="cookie-banner">
    <p class="cookie-text"><?php echo __('cookie_message'); ?></p>
    <button id="accept-cookies" class="cookie-accept-btn"><?php echo __('cookie_accept'); ?></button>
</div>
<script>
    (function() {
        var banner = document.getElementById('cookie-consent');
        if (localStorage.getItem('cookies_accepted') === 'true') {
            if (banner) banner.style.display = 'none';
        }
        var acceptBtn = document.getElementById('accept-cookies');
        if (acceptBtn) {
            acceptBtn.addEventListener('click', function() {
                localStorage.setItem('cookies_accepted', 'true');
                if (banner) banner.style.display = 'none';
            });
        }
    })();
</script>
<script defer src="<?php echo SITE_URL; ?>/js_loader.php?file=search-suggest.js"></script>
<?php if (function_exists('faqRenderJsonLd')) echo faqRenderJsonLd(); ?><?php if (function_exists('howtoRenderJsonLd')) echo howtoRenderJsonLd(); ?>

<button id="go-to-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Наверх">↑</button>
<style>
#go-to-top {
    position: fixed; bottom: 30px; right: 30px;
    width: 48px; height: 48px; border-radius: 50%;
    background: var(--accent,#2563eb); color: #fff;
    border: none; font-size: 22px; cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,.3);
    opacity: 0; visibility: hidden; transition: opacity .3s, visibility .3s;
    z-index: 999;
}
#go-to-top.show { opacity: 1; visibility: visible; }
</style>
<script>
(function(){
    var btn = document.getElementById('go-to-top');
    window.addEventListener('scroll', function(){
        btn.classList.toggle('show', window.scrollY > 400);
    });
})();
</script>
</body>
</html>
