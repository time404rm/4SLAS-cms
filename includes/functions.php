<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

// ========== ЯЗЫК ==========
function __($key, $params = []) {
    static $langData = null;
    if ($langData === null) {
        $lang = $_SESSION['lang'] ?? 'ru';
        if (!in_array($lang, ['ru', 'en'])) $lang = 'ru';
        $file = __DIR__ . "/../lang/{$lang}.php";
        $langData = file_exists($file) ? include $file : include __DIR__ . "/../lang/ru.php";
    }
    $text = $langData[$key] ?? $key;
    foreach ($params as $k => $v) $text = str_replace("{{$k}}", $v, $text);
    return $text;
}

// ========== НАСТРОЙКИ ==========
function getSetting($key) {
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $db = getDb();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $cache[$key] = $stmt->fetchColumn();
    return $cache[$key];
}

// ========== ТЕГИ ==========
function getAllTags() {
    $db = getDb();
    $stmt = $db->query("SELECT h.name, COUNT(ph.post_id) as count 
                        FROM hashtags h 
                        JOIN post_hashtags ph ON h.id = ph.hashtag_id 
                        JOIN posts p ON ph.post_id = p.id 
                        WHERE p.status = 'published' 
                        GROUP BY h.name 
                        ORDER BY count DESC, h.name ASC");
    return $stmt->fetchAll();
}

// ========== БЕЗОПАСНОСТЬ ==========
function h($string) { return htmlspecialchars($string, ENT_QUOTES, 'UTF-8'); }

function slugify($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $converter = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ' ' => '-', '+' => '-', '_' => '-', ',' => '-', '.' => '-', '?' => '',
    ];
    $text = strtr($text, $converter);
    $text = preg_replace('/[^a-z0-9\-]+/i', '-', $text);
    $text = trim($text, '-');
    if (empty($text)) return 'post-' . uniqid();
    return $text;
}

// ========== ЗАЩИТА ОТ БРУТФОРСА ==========
function getClientIP() {
    // Используем только REMOTE_ADDR — заголовки X_FORWARDED_FOR легко спуфятся
    // Если сервер за обратным прокси, настройте доверенные прокси отдельно
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function cleanOldAttempts($table, $minutes = 15) {
    $allowed = ['login_attempts', 'comment_attempts'];
    if (!in_array($table, $allowed)) return;
    $db = getDb();
    $time = date('Y-m-d H:i:s', time() - $minutes * 60);
    $stmt = $db->prepare("DELETE FROM `$table` WHERE attempt_time < ?");
    $stmt->execute([$time]);
}

function checkRateLimit($table, $maxAttempts, $minutes, $ip) {
    $allowed = ['login_attempts', 'comment_attempts'];
    if (!in_array($table, $allowed)) return false;
    $db = getDb();
    $time = date('Y-m-d H:i:s', time() - $minutes * 60);
    $stmt = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE ip = ? AND attempt_time > ?");
    $stmt->execute([$ip, $time]);
    $count = $stmt->fetchColumn();
    return $count < $maxAttempts;
}

function recordAttempt($table, $ip) {
    $allowed = ['login_attempts', 'comment_attempts'];
    if (!in_array($table, $allowed)) return;
    $db = getDb();
    $stmt = $db->prepare("INSERT INTO `$table` (ip, attempt_time) VALUES (?, NOW())");
    $stmt->execute([$ip]);
}

function isAdmin() { return isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin'; }

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) { return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token); }

function yandexOAuthConfigured() {
    return (getSetting('yandex_oauth_enabled') == '1' && getSetting('yandex_client_id') && getSetting('yandex_client_secret'));
}
function vkOAuthConfigured() {
    return (getSetting('vk_oauth_enabled') == '1' && getSetting('vk_client_id') && getSetting('vk_client_secret'));
}

// ========== ПОСТЫ ==========
function getPosts($limit, $offset) {
    $db = getDb();
    $sql = "SELECT p.*,
            (SELECT GROUP_CONCAT(c.name SEPARATOR ',') 
             FROM post_categories pc 
             JOIN categories c ON pc.category_id = c.id 
             WHERE pc.post_id = p.id) as categories,
            (SELECT GROUP_CONCAT(h.name SEPARATOR ',') 
             FROM post_hashtags ph 
             JOIN hashtags h ON ph.hashtag_id = h.id 
             WHERE ph.post_id = p.id) as hashtags,
            u.username as author_name,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.status = 'published'
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

function searchPosts($query, $limit, $offset) {
    $db = getDb();
    $searchTerm = '%' . $query . '%';
    
    $sql = "SELECT p.*,
            (SELECT GROUP_CONCAT(c.name SEPARATOR ',') 
             FROM post_categories pc 
             JOIN categories c ON pc.category_id = c.id 
             WHERE pc.post_id = p.id) as categories,
            (SELECT GROUP_CONCAT(h.name SEPARATOR ',') 
             FROM post_hashtags ph 
             JOIN hashtags h ON ph.hashtag_id = h.id 
             WHERE ph.post_id = p.id) as hashtags,
            u.username as author_name,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE (p.title LIKE ? 
               OR p.content LIKE ? 
               OR EXISTS (SELECT 1 FROM post_hashtags ph2 
                          JOIN hashtags h2 ON ph2.hashtag_id = h2.id 
                          WHERE ph2.post_id = p.id AND h2.name LIKE ?))
               AND p.status = 'published'
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
    return $stmt->fetchAll();
}

function getSearchPostsCount($query) {
    $db = getDb();
    $searchTerm = '%' . $query . '%';
    $sql = "SELECT COUNT(*) FROM posts p
            WHERE (p.title LIKE ? 
               OR p.content LIKE ? 
               OR EXISTS (SELECT 1 FROM post_hashtags ph2 
                          JOIN hashtags h2 ON ph2.hashtag_id = h2.id 
                          WHERE ph2.post_id = p.id AND h2.name LIKE ?))
               AND p.status = 'published'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    return (int)$stmt->fetchColumn();
}

function getPostBySlug($slug) {
    $db = getDb();
    if (isAdmin()) {
        $stmt = $db->prepare("SELECT p.*, u.username as author_name
                               FROM posts p
                               LEFT JOIN users u ON p.user_id = u.id
                               WHERE p.slug = ?");
    } else {
        $stmt = $db->prepare("SELECT p.*, u.username as author_name
                               FROM posts p
                               LEFT JOIN users u ON p.user_id = u.id
                               WHERE p.slug = ? AND p.status = 'published'");
    }
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
    if ($post) {
        $stmt = $db->prepare("SELECT c.id, c.name, c.slug FROM categories c JOIN post_categories pc ON c.id = pc.category_id WHERE pc.post_id = ?");
        $stmt->execute([$post['id']]);
        $post['categories'] = $stmt->fetchAll();
        $stmt = $db->prepare("SELECT h.id, h.name FROM hashtags h JOIN post_hashtags ph ON h.id = ph.hashtag_id WHERE ph.post_id = ?");
        $stmt->execute([$post['id']]);
        $post['hashtags'] = $stmt->fetchAll();
    }
    return $post;
}

function userLiked($postId, $userId = null) {
    if (!$userId && !isset($_SESSION['user_id'])) return false;
    $userId = $userId ?? $_SESSION['user_id'];
    $db = getDb();
    $stmt = $db->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function addLike($postId, $userId) {
    $db = getDb();
    $stmt = $db->prepare("INSERT IGNORE INTO likes (post_id, user_id) VALUES (?, ?)");
    $stmt->execute([$postId, $userId]);
    if ($stmt->rowCount()) {
        $db->prepare("UPDATE posts SET likes_count = likes_count + 1 WHERE id = ?")->execute([$postId]);
        return true;
    }
    return false;
}

function getComments($postId, $page = 1, $perPage = 10) {
    $db = getDb();
    $offset = ($page - 1) * $perPage;
    $stmt = $db->prepare("SELECT * FROM comments WHERE post_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$postId, $perPage, $offset]);
    return $stmt->fetchAll();
}

function getCommentsTree($postId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM comments WHERE post_id = ? AND status = 'approved' ORDER BY created_at ASC");
    $stmt->execute([$postId]);
    $comments = $stmt->fetchAll();
    $indexed = [];
    foreach ($comments as $comment) {
        $comment['children'] = [];
        $indexed[$comment['id']] = $comment;
    }
    $tree = [];
    foreach ($indexed as $id => $comment) {
        $parentId = $comment['parent_id'];
        if ($parentId == 0) {
            $tree[] = &$indexed[$id];
        } elseif (isset($indexed[$parentId])) {
            $indexed[$parentId]['children'][] = &$indexed[$id];
        } else {
            $tree[] = &$indexed[$id];
        }
    }
    return $tree;
}

function renderCommentsTree($comments, $level = 0) {
    if (empty($comments)) return '';
    $html = '';
    foreach ($comments as $comment) {
        $html .= '<div class="comment" data-id="' . $comment['id'] . '" style="margin-left: ' . ($level * 30) . 'px;">';
        $html .= '<strong>' . htmlspecialchars($comment['author_name']) . '</strong>';
        $html .= '<span class="comment-date">' . date('d.m.Y H:i', strtotime($comment['created_at'])) . '</span>';
        $html .= '<div class="comment-content">' . maskEmails(nl2br(htmlspecialchars($comment['content']))) . '</div>';
        if ($level < 3 && isset($_SESSION['user_id'])) {
            $html .= '<button class="reply-btn" data-id="' . $comment['id'] . '" data-author="' . htmlspecialchars($comment['author_name']) . '">Ответить</button>';
        }
        if (!empty($comment['children'])) {
            $html .= renderCommentsTree($comment['children'], $level + 1);
        }
        $html .= '</div>';
    }
    return $html;
}

function validateImage($file) {
    $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) return false;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedMime)) return false;
    if (!getimagesize($file['tmp_name'])) return false;
    return true;
}

function activateHashtags($content) {
    if ($content === null) return '';
    $pattern = '/(^|\s)#([\p{L}\p{N}_]+)/u';
    $replacement = '$1<a href="' . SITE_URL . '/search.php?q=$2" class="post-hashtag">#$2</a>';
    return preg_replace($pattern, $replacement, $content);
}

function getAllCategories() {
    $db = getDb();
    return $db->query("SELECT id, name, slug FROM categories ORDER BY name")->fetchAll();
}

// ========== АВТОРЫ ПОСТОВ ==========
function getAllUsersForSelect() {
    $db = getDb();
    return $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll();
}

function getAuthorsForSelect() {
    return getAllUsersForSelect();
}

function getTopPostsByLikes($limit = 5) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, title, slug, likes_count FROM posts WHERE status = 'published' AND likes_count >= 5 ORDER BY likes_count DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getPostsCount() {
    $db = getDb();
    $stmt = $db->query("SELECT COUNT(*) FROM posts WHERE status = 'published'");
    return (int)$stmt->fetchColumn();
}

function getVideoEmbed($url) {
    if (empty($url)) return '';
    $url = trim($url);
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/', $url, $matches)) {
        $videoId = $matches[1];
        return '<iframe width="100%" height="auto" src="https://www.youtube.com/embed/' . $videoId . '" frameborder="0" allowfullscreen></iframe>';
    }
    if (preg_match('/vkvideo\.ru\/video([-_0-9]+)/', $url, $matches)) {
        $videoId = $matches[1];
        return '<iframe src="https://vkvideo.ru/video_ext.php?oid=' . explode('_', $videoId)[0] . '&id=' . explode('_', $videoId)[1] . '&hash=" width="100%" height="auto" frameborder="0" allowfullscreen></iframe>';
    }
    if (preg_match('/rutube\.ru\/video\/([a-f0-9]+)/', $url, $matches)) {
        $videoId = $matches[1];
        return '<iframe src="https://rutube.ru/play/embed/' . $videoId . '" width="100%" height="auto" frameborder="0" allowfullscreen></iframe>';
    }
    return '<a href="' . htmlspecialchars($url) . '" target="_blank">Смотреть видео</a>';
}

function getExcerpt($text, $postType = null, $postLength = null) {
    if ($text === null) return '';
    if ($postType && $postLength) {
        $type = $postType;
        $length = $postLength;
    } else {
        $type = getSetting('excerpt_type') ?: 'chars';
        $length = (int)getSetting('excerpt_length') ?: 200;
    }
    $plainText = strip_tags($text);
    $plainText = preg_replace('/\s+/', ' ', $plainText);
    $plainText = trim($plainText);
    if ($type === 'words') {
        $words = explode(' ', $plainText);
        if (count($words) <= $length) return $plainText;
        $words = array_slice($words, 0, $length);
        return implode(' ', $words) . '…';
    } else {
        if (mb_strlen($plainText, 'UTF-8') <= $length) return $plainText;
        return mb_substr($plainText, 0, $length, 'UTF-8') . '…';
    }
}

// ========== ГАЛЕРЕЯ ПОСТА ==========
function getGalleryImages($postId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, image, sort_order FROM post_gallery WHERE post_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$postId]);
    return $stmt->fetchAll();
}

function addGalleryImage($postId, $imageName) {
    $db = getDb();
    $stmt = $db->prepare("SELECT MAX(sort_order) FROM post_gallery WHERE post_id = ?");
    $stmt->execute([$postId]);
    $max = (int)$stmt->fetchColumn();
    $stmt = $db->prepare("INSERT INTO post_gallery (post_id, image, sort_order) VALUES (?, ?, ?)");
    $stmt->execute([$postId, $imageName, $max + 1]);
    return $db->lastInsertId();
}

function deleteGalleryImage($id, $postId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT image FROM post_gallery WHERE id = ? AND post_id = ?");
    $stmt->execute([$id, $postId]);
    $image = $stmt->fetchColumn();
    if ($image) {
        $filePath = UPLOAD_DIR . 'gallery/' . $image;
        if (file_exists($filePath)) unlink($filePath);
    }
    $stmt = $db->prepare("DELETE FROM post_gallery WHERE id = ? AND post_id = ?");
    return $stmt->execute([$id, $postId]);
}

function updateGalleryOrder($postId, $order) {
    $db = getDb();
    foreach ($order as $id => $sort) {
        $stmt = $db->prepare("UPDATE post_gallery SET sort_order = ? WHERE id = ? AND post_id = ?");
        $stmt->execute([$sort, $id, $postId]);
    }
}

function wrapImagesWithLightbox($content, $postId = null) {
    if ($content === null) return '';
    $introImage = '';
    if ($postId) $introImage = getIntroImage($postId);
    $pattern = '/<img(.*?)src=["\'](.*?)["\'](.*?)>/i';
    $content = preg_replace_callback($pattern, function($matches) use ($introImage) {
        $src = $matches[2];
        if ($introImage && strpos($src, $introImage) !== false) return $matches[0];
        if (strpos($src, '/icons/') !== false || strpos($src, '/emojis/') !== false || strpos($src, '/uploads/icons/') !== false) return $matches[0];
        return '<a href="' . htmlspecialchars($src) . '" data-lightbox="content-images" data-title="Изображение">' . $matches[0] . '</a>';
    }, $content);
    return $content;
}

function getIntroImage($postId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT intro_image FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    return $stmt->fetchColumn();
}

function convertToWebP($sourcePath, $quality = 82) {
    if (!function_exists('imagewebp')) return null;
    $info = getimagesize($sourcePath);
    if (!$info) return null;
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $image = imagecreatefromjpeg($sourcePath); break;
        case 'image/png':  $image = imagecreatefrompng($sourcePath); imagepalettetotruecolor($image); imagealphablending($image, true); imagesavealpha($image, true); break;
        case 'image/gif':  $image = imagecreatefromgif($sourcePath); break;
        case 'image/webp': return null;
        default: return null;
    }
    if (!$image) return null;
    $targetPath = preg_replace('/\.[^.]+$/', '.webp', $sourcePath);
    if (imagewebp($image, $targetPath, $quality)) {
        imagedestroy($image);
        unlink($sourcePath);
        return $targetPath;
    }
    imagedestroy($image);
    return null;
}

function maskEmails($text) {
    if ($text === null) return '';
    return preg_replace_callback('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', function($matches) {
        $email = $matches[0];
        $masked = str_replace('@', '&#64;', $email);
        $masked = str_replace('.', '&#46;', $masked);
        return $masked;
    }, $text);
}

// ========== КЕШИРОВАНИЕ ==========
function isCacheEnabled() {
    $enabled = getSetting('cache_enabled');
    if ($enabled === null) return false;
    return (int)$enabled === 1;
}

function getCacheKey($url) {
    return md5($url) . '.html';
}

function getCache($key, $ttl = null) {
    if ($ttl === null) {
        $ttl = (int)getSetting('cache_ttl') ?: 3600;
    }
    $file = CACHE_DIR . $key;
    if (file_exists($file) && (time() - filemtime($file) < $ttl)) {
        return file_get_contents($file);
    }
    return false;
}

function setCache($key, $content) {
    if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);
    file_put_contents(CACHE_DIR . $key, $content);
}

function clearCache() {
    if (!is_dir(CACHE_DIR)) return;
    $files = glob(CACHE_DIR . '*');
    foreach ($files as $file) if (is_file($file)) unlink($file);
}

// ========== МИНИМИЗАЦИЯ CSS ==========
function isCssMinifyEnabled() {
    return (int)getSetting('css_minify_enabled') === 1;
}

function minifyCss($css) {
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);
    $css = preg_replace('/\s*([{}|:;,])\s+/', '$1', $css);
    $css = preg_replace('/;\s*}/', '}', $css);
    $css = preg_replace('/:\s+/', ':', $css);
    $css = preg_replace('/\s*{\s*/', '{', $css);
    $css = preg_replace('/\s*}\s*/', '}', $css);
    $css = preg_replace('/;}/', '}', $css);
    return trim($css);
}

function getMinifiedCss($originalFile) {
    if (!file_exists($originalFile)) return '';
    $cacheDir = CACHE_DIR . 'css/';
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    $cacheKey = md5($originalFile . filemtime($originalFile)) . '.min.css';
    $cacheFile = $cacheDir . $cacheKey;
    if (isCssMinifyEnabled() && file_exists($cacheFile)) return file_get_contents($cacheFile);
    $content = file_get_contents($originalFile);
    $minified = minifyCss($content);
    if (isCssMinifyEnabled()) file_put_contents($cacheFile, $minified);
    return $minified;
}

function clearCssCache() {
    $cacheDir = CACHE_DIR . 'css/';
    if (!is_dir($cacheDir)) return;
    $files = glob($cacheDir . '*');
    foreach ($files as $file) if (is_file($file)) unlink($file);
}

// ========== ЗАГРУЗКА ИЗОБРАЖЕНИЙ ДЛЯ РЕДАКТОРА ==========
function uploadEditorImage($file, $type, $slug) {
    $slug = slugify($slug);
    if (empty($slug)) $slug = 'temp';
    if ($type === 'post') {
        $subfolder = 'posts/' . $slug;
    } elseif ($type === 'page') {
        $subfolder = 'pages/' . $slug;
    } else {
        $subfolder = 'content/pages';
    }
    $uploadDir = UPLOAD_DIR . $subfolder . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return null;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) return null;
    $newName = uniqid() . '.' . $ext;
    $target = $uploadDir . $newName;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        $webpPath = convertToWebP($target);
        if ($webpPath) $newName = basename($webpPath);
        return '/uploads/' . $subfolder . '/' . $newName;
    }
    return null;
}

// ========== РЕЗЕРВНОЕ КОПИРОВАНИЕ ==========
function getBackupPath($type = 'cms') {
    $base = __DIR__ . '/../backup/';
    return $base . $type . '/';
}

function getBackupFiles($type = 'cms') {
    $dir = getBackupPath($type);
    $files = glob($dir . '*');
    $list = [];
    foreach ($files as $file) {
        if (is_file($file)) {
            $list[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'mtime' => filemtime($file),
                'path' => $file
            ];
        }
    }
    usort($list, function($a, $b) { return $b['mtime'] - $a['mtime']; });
    return $list;
}

function deleteBackup($type, $filename) {
    $file = getBackupPath($type) . basename($filename);
    if (file_exists($file)) return unlink($file);
    return false;
}

function backupFiles() {
    $source = realpath(__DIR__ . '/../');
    $destination = getBackupPath('cms') . 'backup_files_' . date('Y-m-d_H-i-s') . '.zip';
    $exclude = ['backup', 'cache', '.git', 'temp', 'logs'];
    if (!extension_loaded('zip')) return ['success' => false, 'error' => 'Расширение ZIP не установлено на сервере'];
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['success' => false, 'error' => 'Не удалось создать ZIP-архив. Проверьте права на папку backup/cms/'];
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    $addedCount = 0;
    foreach ($files as $file) {
        $filePath = $file->getRealPath();
        if (!$filePath) continue;
        $skip = false;
        foreach ($exclude as $excludedDir) {
            if (strpos($filePath, DIRECTORY_SEPARATOR . $excludedDir . DIRECTORY_SEPARATOR) !== false) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;
        $relativePath = ltrim(substr($filePath, strlen($source)), DIRECTORY_SEPARATOR);
        if (empty($relativePath)) continue;
        $zip->addFile($filePath, $relativePath);
        $addedCount++;
    }
    $zip->close();
    if ($addedCount === 0) return ['success' => false, 'error' => 'Не найдено файлов для архивации'];
    return ['success' => true, 'file' => basename($destination), 'size' => round(filesize($destination) / 1024, 2) . ' KB'];
}

function backupDatabase() {
    $db = getDb();
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $dump = "-- БЛОГ Т404 SQL Dump\n-- Дата: " . date('Y-m-d H:i:s') . "\n-- Хост: " . DB_HOST . "\n-- База: " . DB_NAME . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW CREATE TABLE `$table`");
        $create = $stmt->fetch();
        $dump .= "-- Структура таблицы `$table`\n";
        $dump .= $create['Create Table'] . ";\n\n";
        $stmt = $db->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) continue;
        $dump .= "-- Данные таблицы `$table`\n";
        foreach ($rows as $row) {
            $escaped = array_map(function($val) use ($db) {
                if ($val === null) return 'NULL';
                return $db->quote($val);
            }, $row);
            $dump .= "INSERT INTO `$table` (`" . implode('`, `', array_keys($row)) . "`) VALUES (" . implode(', ', $escaped) . ");\n";
        }
        $dump .= "\n";
    }
    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $filename = 'backup_db_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = getBackupPath('sql') . $filename;
    file_put_contents($filepath, $dump);
    $gzpath = $filepath . '.gz';
    $fp = gzopen($gzpath, 'w9');
    gzwrite($fp, $dump);
    gzclose($fp);
    unlink($filepath);
    return ['success' => true, 'file' => $filename . '.gz'];
}

function getRelatedPostsByTags($postId, $limit = 3) {
    $db = getDb();
    $stmt = $db->prepare("SELECT hashtag_id FROM post_hashtags WHERE post_id = ?");
    $stmt->execute([$postId]);
    $tagIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tagIds)) return [];
    $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
    $sql = "SELECT DISTINCT p.id, p.title, p.slug, p.intro_image, p.video_url, p.created_at
            FROM posts p
            JOIN post_hashtags ph ON p.id = ph.post_id
            WHERE ph.hashtag_id IN ($placeholders)
              AND p.id != ?
              AND p.status = 'published'
            ORDER BY p.created_at DESC
            LIMIT ?";
    $params = array_merge($tagIds, [$postId, $limit]);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getPostById($postId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    return $stmt->fetch();
}

// ========== УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЯМИ ==========
function getAllUsers($limit = null, $offset = 0) {
    $db = getDb();
    $sql = "SELECT id, username, email, role, created_at FROM users ORDER BY id";
    if ($limit) $sql .= " LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    if ($limit) {
        $stmt->execute([$limit, $offset]);
    } else {
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

function updateUserName($id, $newUsername) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$newUsername, $id]);
    if ($stmt->fetch()) return false;
    $stmt = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
    return $stmt->execute([$newUsername, $id]);
}

function updateUserEmail($id, $newEmail) {
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) return false;
    $db = getDb();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$newEmail, $id]);
    if ($stmt->fetch()) return false;
    $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
    return $stmt->execute([$newEmail, $id]);
}

function getUserById($id) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function updateUserRole($id, $role) {
    $db = getDb();
    $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
    return $stmt->execute([$role, $id]);
}

function updateUserPassword($id, $newPassword) {
    $db = getDb();
    $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    return $stmt->execute([$hash, $id]);
}

function deleteUser($id) {
    $db = getDb();
    if ($id == $_SESSION['user_id']) return false;
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    return $stmt->execute([$id]);
}

// ========== РОЛИ И ПРАВА ДОСТУПА ==========
function canAccessAdmin() {
    if (!isset($_SESSION['user_id'])) return false;
    $role = $_SESSION['role'] ?? '';
    return in_array($role, ['admin', 'editor', 'moderator']);
}

function canManagePosts() {
    $role = $_SESSION['role'] ?? '';
    return in_array($role, ['admin', 'editor']);
}

function canManageComments() {
    $role = $_SESSION['role'] ?? '';
    return in_array($role, ['admin', 'moderator']);
}

function canDeletePost() {
    $role = $_SESSION['role'] ?? '';
    return $role === 'admin';
}

function isEditor() {
    return isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'editor';
}

function isModerator() {
    return isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'moderator';
}

// ========== ОТПРАВКА EMAIL ==========
function sendVerificationEmail($email, $token) {
    $verificationLink = SITE_URL . '/verify.php?token=' . $token;
    $subject = "Подтверждение регистрации на сайте " . getSetting('site_name');
    $body = "Здравствуйте!\n\nДля завершения регистрации перейдите по ссылке:\n$verificationLink\n\nСсылка действительна 24 часа.\n\nС уважением, администрация сайта " . getSetting('site_name');
    return sendEmail($email, $subject, $body);
}

function sendPasswordResetEmail($email, $newPassword) {
    $subject = "Ваш новый пароль на сайте " . getSetting('site_name');
    $body = "Здравствуйте!\n\nВаш новый пароль: $newPassword\n\nРекомендуем изменить его после входа в личный кабинет.\n\nС уважением, администрация сайта " . getSetting('site_name');
    return sendEmail($email, $subject, $body);
}

// ========== ПОДТВЕРЖДЕНИЕ EMAIL ==========
function generateVerificationToken() { return bin2hex(random_bytes(32)); }
function isStrongPassword($password) {
    return (strlen($password) >= 6 &&
            preg_match('/[A-Z]/', $password) &&
            preg_match('/[0-9]/', $password) &&
            preg_match('/[^a-zA-Z0-9]/', $password));
}
function verifyUser($token) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id FROM users WHERE verification_token = ? AND token_expires > NOW() AND is_verified = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $db->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, token_expires = NULL WHERE id = ?")->execute([$user['id']]);
        return true;
    }
    return false;
}
function resendVerificationEmail($email) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND is_verified = 0");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $newToken = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400);
        $db->prepare("UPDATE users SET verification_token = ?, token_expires = ? WHERE id = ?")->execute([$newToken, $expires, $user['id']]);
        sendVerificationEmail($email, $newToken);
        return true;
    }
    return false;
}

// ========== УВЕДОМЛЕНИЯ О КОММЕНТАРИЯХ ==========
function getPostTitle($postId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT title FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    return $stmt->fetchColumn();
}
function getPostSlug($postId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT slug FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    return $stmt->fetchColumn();
}
function getCommentNotificationEmails($postId = null, $includeAuthor = true) {
    $emails = [];
    $db = getDb();
    if (getSetting('notify_on_comment')) {
        $adminEmail = getSetting('admin_email');
        if ($adminEmail) $emails[] = $adminEmail;
    }
    if (getSetting('notify_moderators')) {
        $stmt = $db->prepare("SELECT email FROM users WHERE role = 'moderator' AND email IS NOT NULL AND email != ''");
        $stmt->execute();
        $moderatorEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $emails = array_merge($emails, $moderatorEmails);
    }
    if ($postId && getSetting('notify_author') && $includeAuthor) {
        $stmt = $db->prepare("SELECT u.email FROM posts p JOIN users u ON p.user_id = u.id WHERE p.id = ? AND u.email IS NOT NULL");
        $stmt->execute([$postId]);
        $authorEmail = $stmt->fetchColumn();
        if ($authorEmail && !in_array($authorEmail, $emails)) $emails[] = $authorEmail;
    }
    return array_unique($emails);
}

// ========== РЕЖИМ РЕКОНСТРУКЦИИ ==========
function isMaintenanceMode() { return (int)getSetting('maintenance_mode') === 1; }
function maintenanceModeActive() {
    if (!isMaintenanceMode()) return false;
    if (isset($_SESSION['user_id']) && isAdmin()) return false;
    return true;
}
function renderMaintenancePage() {
    include __DIR__ . '/../includes/maintenance.php';
    exit;
}

function renderYoomoneyButton() {
    $bill = getSetting('yoomoney_bill_number');
    if (!$bill) return '';
    return '<div class="donut-button">' . __('donut_text') . '</div>' .
        '<iframe src="https://yoomoney.ru/quickpay/fundraise/button?billNumber=' . urlencode($bill) . '&" width="330" height="55" frameborder="0" allowtransparency="true" scrolling="no"></iframe>';
}

// ========== СОЦИАЛЬНЫЕ СЕТИ ==========
function renderSocialIcons() {
    $vk = getSetting('social_vk');
    $telegram = getSetting('social_telegram');
    $email = getSetting('social_email');
    $icon_size = (int)getSetting('social_icon_size') ?: 32;
    $icon_gap = (int)getSetting('social_icon_gap') ?: 15;
    if (!$vk && !$telegram && !$email) return '';
    $html = '<div class="social-icons" style="display: flex; gap: ' . $icon_gap . 'px;">';
    if ($vk) $html .= '<a href="' . h($vk) . '" target="_blank" rel="noopener noreferrer"><img src="' . SITE_URL . '/templates/icons/vk.svg" alt="VK" width="' . $icon_size . '" height="' . $icon_size . '"></a>';
    if ($telegram) $html .= '<a href="' . h($telegram) . '" target="_blank" rel="noopener noreferrer"><img src="' . SITE_URL . '/templates/icons/telegram.svg" alt="Telegram" width="' . $icon_size . '" height="' . $icon_size . '"></a>';
    if ($email) $html .= '<a href="mailto:' . h($email) . '"><img src="' . SITE_URL . '/templates/icons/mail.svg" alt="Email" width="' . $icon_size . '" height="' . $icon_size . '"></a>';
    $html .= '</div>';
    return $html;
}

function renderContactSocialIcons() {
    $db = getDb();
    $links = $db->query("SELECT name, icon, url FROM contact_social_links ORDER BY sort_order")->fetchAll();
    if (empty($links)) return '';
    $icon_size = (int)getSetting('contact_social_icon_size') ?: 32;
    $icon_gap = (int)getSetting('contact_social_icon_gap') ?: 15;
    $html = '<div class="contact-social-icons" style="display: flex; justify-content: center; gap: ' . $icon_gap . 'px; margin-top: 30px;">';
    foreach ($links as $link) {
        if (empty($link['url'])) continue;
        $html .= '<a href="' . h($link['url']) . '" target="_blank" rel="noopener noreferrer" title="' . h($link['name']) . '">';
        $html .= '<img src="' . SITE_URL . $link['icon'] . '" alt="' . h($link['name']) . '" width="' . $icon_size . '" height="' . $icon_size . '">';
        $html .= '</a>';
    }
    $html .= '</div>';
    return $html;
}

// ========== МИНИМИЗАЦИЯ JS ==========
function isJsMinifyEnabled() { return (int)getSetting('js_minify_enabled') === 1; }
function isJsObfuscateEnabled() { return (int)getSetting('js_obfuscate_enabled') === 1; }
function minifyJs($code) {
    if ($code === null || $code === '') return '';
    $code = preg_replace('#/\*.*?\*/#s', '', $code);
    $code = preg_replace('#^\s*//.*$#m', '', $code);
    $code = preg_replace('/\s+/', ' ', $code);
    $code = str_replace(['; ', ' {', ' }', '{ ', ' }', '( ', ' )'], [';', '{', '}', '{', '}', '(', ')'], $code);
    $code = preg_replace('#\s*([=<>!+\-*/%&|^~?:;,])\s*#', '$1', $code);
    return trim($code);
}
function getMinifiedJs($originalFile) {
    if (!file_exists($originalFile)) return '';
    $cacheDir = CACHE_DIR . 'js/';
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    $cacheKey = md5($originalFile . filemtime($originalFile)) . '.min.js';
    $cacheFile = $cacheDir . $cacheKey;
    if (isJsMinifyEnabled() && file_exists($cacheFile)) return file_get_contents($cacheFile);
    $content = file_get_contents($originalFile);
    $minified = minifyJs($content);
    if (isJsMinifyEnabled()) file_put_contents($cacheFile, $minified);
    return $minified;
}

//-- НИЖЕ ЭТОГО НИЧЕГО НЕ ДОБАВЛЯТЬ--
function getPostSlugForCache($postId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT slug FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    return $stmt->fetchColumn();
}
function clearCacheForUrl($url) {
    $key = getCacheKey($url);
    $file = CACHE_DIR . $key;
    if (file_exists($file)) unlink($file);
}
?>