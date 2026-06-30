<?php
require_once '../includes/functions.php';
if (!canManagePosts()) { header('Location: login.php'); exit; }

$db = getDb();
$id = (int)($_GET['id'] ?? 0);
$post = null;
if ($id) {
    $stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
}

$allCategories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$postCategories = [];
if ($id) {
    $stmt = $db->prepare("SELECT category_id FROM post_categories WHERE post_id = ?");
    $stmt->execute([$id]);
    $postCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if (!$id) {
    $defaultCategory = $db->query("SELECT id FROM categories WHERE name = 'Без категории'")->fetchColumn();
    if ($defaultCategory && !in_array($defaultCategory, $postCategories)) {
        $postCategories[] = $defaultCategory;
    }
}

$postHashtags = [];
if ($id) {
    $stmt = $db->prepare("SELECT h.name FROM hashtags h JOIN post_hashtags ph ON h.id = ph.hashtag_id WHERE ph.post_id = ?");
    $stmt->execute([$id]);
    $postHashtags = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$allUsers = getAllUsersForSelect();
$csrf_token = generateCsrfToken();

if (isset($_GET['delete_gallery']) && isset($_GET['id']) && isset($_GET['csrf_token'])) {
    if (!verifyCsrfToken($_GET['csrf_token'])) die('CSRF failed');
    $imageId = (int)$_GET['delete_gallery'];
    $postId = (int)$_GET['id'];
    if ($imageId && $postId) {
        deleteGalleryImage($imageId, $postId);
        clearCache();
        header('Location: ?id=' . $postId);
        exit;
    }
}

if (isset($_POST['upload_gallery']) && $id) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) die('CSRF failed');
    $uploadDir = UPLOAD_DIR . 'gallery/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    foreach ($_FILES['gallery_files']['tmp_name'] as $i => $tmp) {
        if ($_FILES['gallery_files']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($_FILES['gallery_files']['name'][$i], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) continue;
        $newName = uniqid() . '.' . $ext;
        if (move_uploaded_file($tmp, $uploadDir . $newName)) {
            addGalleryImage($id, $newName);
        }
    }
    clearCache();
    header('Location: ?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['upload_gallery'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $title = trim($_POST['title']);
    $slug = !empty($_POST['slug']) ? slugify($_POST['slug']) : slugify($title);
    $content = $_POST['content'];
    $status = $_POST['status'] ?? 'draft';
    $commentsEnabled = isset($_POST['comments_enabled']) ? 1 : 0;
    $categories = $_POST['categories'] ?? [];
    $hashtagsInput = trim($_POST['hashtags'] ?? '');
    $hashtags = array_map('trim', explode(',', $hashtagsInput));

    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $meta_keywords = trim($_POST['meta_keywords'] ?? '');

    $created_at = trim($_POST['created_at'] ?? '');
    if ($created_at) {
        $created_at = date('Y-m-d H:i:s', strtotime($created_at));
    } else {
        $created_at = date('Y-m-d H:i:s');
    }

    $display_author = trim($_POST['display_author'] ?? '');
    $canonical_url = trim($_POST['canonical_url'] ?? '');

    $video_url = trim($_POST['video_url'] ?? '');

    $excerpt_type = $_POST['excerpt_type'] ?? '';
    if (!in_array($excerpt_type, ['', 'chars', 'words'])) $excerpt_type = '';
    if ($excerpt_type === '') $excerpt_type = null;

    $excerpt_length = isset($_POST['excerpt_length']) && $_POST['excerpt_length'] !== '' ? (int)$_POST['excerpt_length'] : null;
    if ($excerpt_length !== null && $excerpt_length <= 0) $excerpt_length = null;

    if (isAdmin()) {
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : $_SESSION['user_id'];
    } else {
        $user_id = $_SESSION['user_id'] ?? null;
    }

    $imageName = $post['intro_image'] ?? null;
    if (isset($_FILES['intro_image']) && $_FILES['intro_image']['error'] === UPLOAD_ERR_OK) {
        if (!validateImage($_FILES['intro_image'])) die('Invalid image file');
        $tmpPath = $_FILES['intro_image']['tmp_name'];
        $ext = pathinfo($_FILES['intro_image']['name'], PATHINFO_EXTENSION);
        $tempName = uniqid() . '.' . $ext;
        $tempFullPath = POSTS_IMG_DIR . $tempName;
        if (!is_dir(POSTS_IMG_DIR)) mkdir(POSTS_IMG_DIR, 0755, true);
        if (move_uploaded_file($tmpPath, $tempFullPath)) {
            $webpPath = convertToWebP($tempFullPath);
            if ($webpPath) {
                $imageName = basename($webpPath);
            } else {
                $imageName = $tempName;
            }
        }
        if ($post && $post['intro_image'] && file_exists(POSTS_IMG_DIR . $post['intro_image'])) {
            unlink(POSTS_IMG_DIR . $post['intro_image']);
        }
    }

    $stmt = $db->prepare("SELECT id FROM posts WHERE slug = ? AND id != ?");
    $stmt->execute([$slug, $id]);
    if ($stmt->fetch()) {
        $slug = $slug . '-' . time();
    }

    if ($id) {
        $stmt = $db->prepare("UPDATE posts SET 
            user_id=?, title=?, slug=?, content=?, intro_image=?, video_url=?, comments_enabled=?, 
            status=?, created_at=?, meta_title=?, meta_description=?, meta_keywords=?,
            excerpt_type=?, excerpt_length=?, display_author=?, canonical_url=?
            WHERE id=?");
        $stmt->execute([
            $user_id, $title, $slug, $content, $imageName, $video_url, $commentsEnabled,
            $status, $created_at, $meta_title, $meta_description, $meta_keywords,
            $excerpt_type, $excerpt_length, $display_author, $canonical_url, $id
        ]);
    } else {
        $stmt = $db->prepare("INSERT INTO posts (
            user_id, title, slug, content, intro_image, video_url, comments_enabled,
            status, created_at, meta_title, meta_description, meta_keywords,
            excerpt_type, excerpt_length, display_author, canonical_url
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $user_id, $title, $slug, $content, $imageName, $video_url, $commentsEnabled,
            $status, $created_at, $meta_title, $meta_description, $meta_keywords,
            $excerpt_type, $excerpt_length, $display_author, $canonical_url
        ]);
        $id = $db->lastInsertId();
    }

    $db->prepare("DELETE FROM post_categories WHERE post_id = ?")->execute([$id]);
    foreach ($categories as $catId) {
        $db->prepare("INSERT INTO post_categories (post_id, category_id) VALUES (?,?)")->execute([$id, $catId]);
    }

    $db->prepare("DELETE FROM post_hashtags WHERE post_id = ?")->execute([$id]);
    foreach ($hashtags as $tagName) {
        if ($tagName === '') continue;
        $stmt = $db->prepare("INSERT IGNORE INTO hashtags (name) VALUES (?)");
        $stmt->execute([$tagName]);
        $tagId = $db->lastInsertId();
        if (!$tagId) {
            $stmt = $db->prepare("SELECT id FROM hashtags WHERE name = ?");
            $stmt->execute([$tagName]);
            $tagId = $stmt->fetchColumn();
        }
        $db->prepare("INSERT INTO post_hashtags (post_id, hashtag_id) VALUES (?,?)")->execute([$id, $tagId]);
    }
    clearCache();

    if (isset($_POST['save_and_close'])) {
        header('Location: posts.php?msg=updated');
    } else {
        header('Location: ?id=' . $id . '&msg=updated');
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $id ? 'Редактировать пост' : 'Новый пост'; ?></title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .form-row { margin-bottom: 15px; }
        .form-row label { display: inline-block; width: 180px; vertical-align: top; font-weight: bold; }
        .form-row input[type="text"], .form-row textarea, .form-row select, .form-row input[type="datetime-local"] { width: 65%; padding: 8px; }
        textarea { font-family: monospace; height: 400px; }
        .categories-group label { width: auto; margin-right: 15px; }
        button, .button { background: #2563eb; color: white; border: none; padding: 8px 20px; cursor: pointer; border-radius: 4px; text-decoration: none; display: inline-block; }
        button:hover, .button:hover { background: #1e40af; }
        .button-preview { background: #2c3e50; }
        .button-preview:hover { background: #1e2a3e; }
        small { display: block; color: #888; margin-top: 4px; }
        .seo-section { border-top: 1px solid #2a3650; margin-top: 15px; padding-top: 15px; }
        .gallery-details { background: #1e2a3e; border-radius: 5px; margin-bottom: 15px; }
        .gallery-details summary { padding: 15px; font-weight: bold; cursor: pointer; }
        .gallery-content { padding: 15px; background: #0f1422; border-radius: 5px; }
        .gallery-preview { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
        .gallery-item { position: relative; width: 100px; height: 100px; border: 1px solid #2a3650; border-radius: 4px; overflow: hidden; }
        .gallery-item img { width: 100%; height: 100%; object-fit: cover; }
        .delete-gallery-img { position: absolute; top: 2px; right: 2px; background: rgba(0,0,0,0.6); color: white; border: none; border-radius: 50%; width: 20px; height: 20px; text-align: center; line-height: 20px; font-size: 12px; cursor: pointer; text-decoration: none; }
        .delete-gallery-img:hover { background: #ef4444; }
        .action-buttons { margin: 20px 0; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1><?php echo $id ? 'Редактировать пост' : 'Новый пост'; ?></h1>
    
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="action-buttons">
            <button type="submit">💾 Сохранить</button>
            <button type="submit" name="save_and_close" value="1">💾 Сохранить и закрыть</button>
            <?php if ($id): ?>
                <a href="<?php echo SITE_URL; ?>/post/<?php echo h($post['slug']); ?>" target="_blank" class="button button-preview">👁️ Предпросмотр</a>
            <?php endif; ?>
            <a href="posts.php" class="button">❌ Отмена</a>
        </div>

        <div class="form-row">
            <label>Заголовок:</label>
            <input type="text" name="title" value="<?php echo $post ? htmlspecialchars($post['title']) : ''; ?>" required>
        </div>
        <div class="form-row">
            <label>Slug (оставьте пустым):</label>
            <input type="text" name="slug" value="<?php echo $post ? htmlspecialchars($post['slug']) : ''; ?>">
        </div>
        <div class="form-row">
            <label>Intro-изображение:</label>
            <input type="file" name="intro_image" accept="image/*">
            <?php if ($post && $post['intro_image']): ?>
                <img src="<?php echo SITE_URL; ?>/uploads/posts/<?php echo htmlspecialchars($post['intro_image']); ?>" width="100" style="display:block; margin-top:5px;">
            <?php endif; ?>
        </div>
        <div class="form-row">
            <label>Видео (ссылка):</label>
            <input type="text" name="video_url" value="<?php echo $post ? htmlspecialchars($post['video_url'] ?? '') : ''; ?>" placeholder="https://vkvideo.ru/video-123_456 / https://rutube.ru/video/...">
            <small>Если указано, будет показано вместо изображения.</small>
        </div>

        <div class="form-row">
            <label>Содержание:</label>
            <div id="post-editor" class="custom-editor" contenteditable="true"><?php echo $post ? $post['content'] : ''; ?></div>
            <textarea name="content" id="post-content-hidden" style="display:none;"><?php echo $post ? htmlspecialchars($post['content']) : ''; ?></textarea>
        </div>

        <div class="form-row">
            <label>Категории:</label>
            <div class="categories-group">
                <?php foreach ($allCategories as $cat): ?>
                    <label>
                        <input type="checkbox" name="categories[]" value="<?php echo $cat['id']; ?>"
                            <?php echo in_array($cat['id'], $postCategories) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </label>
                <?php endforeach; ?>
                <a href="categories.php" target="_blank">➕ Управлять категориями</a>
            </div>
        </div>

        <div class="form-row">
            <label>Хештеги (через запятую):</label>
            <input type="text" name="hashtags" value="<?php echo $post ? htmlspecialchars(implode(',', $postHashtags)) : ''; ?>" placeholder="php, laravel, javascript">
        </div>

        <div class="form-row">
            <label>
                <input type="checkbox" name="comments_enabled" value="1" <?php echo (!$post || $post['comments_enabled']) ? 'checked' : ''; ?>>
                Включить комментарии
            </label>
        </div>

        <div class="form-row">
            <label>Статус:</label>
            <select name="status">
                <option value="published" <?php echo ($post && $post['status'] == 'published') ? 'selected' : ''; ?>>Опубликовано</option>
                <option value="draft" <?php echo ($post && $post['status'] == 'draft') ? 'selected' : ''; ?>>Черновик</option>
            </select>
        </div>

        <?php if (isAdmin()): ?>
        <div class="form-row">
            <label>Автор:</label>
            <select name="user_id">
                <option value="">– Без автора –</option>
                <?php foreach ($allUsers as $u): ?>
                    <option value="<?php echo $u['id']; ?>"
                        <?php echo ( ($post && $post['user_id'] == $u['id']) || (!$post && $u['id'] == $_SESSION['user_id']) ) ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialchars($u['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Кому принадлежит пост (будет отображаться на сайте как @юзернэйм). По умолчанию – текущий пользователь.</small>
        </div>
        <?php else: ?>
            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
        <?php endif; ?>

        <div class="form-row">
            <label>Дата создания:</label>
            <input type="datetime-local" name="created_at" value="<?php echo $post ? date('Y-m-d\TH:i', strtotime($post['created_at'])) : ''; ?>">
            <small>Оставьте пустым для текущей даты.</small>
        </div>

        <div class="form-row">
            <label>Тип обрезки анонса:</label>
            <select name="excerpt_type">
                <option value="" <?php echo empty($post['excerpt_type']) ? 'selected' : ''; ?>>По умолчанию</option>
                <option value="chars" <?php echo ($post['excerpt_type'] ?? '') == 'chars' ? 'selected' : ''; ?>>Символы</option>
                <option value="words" <?php echo ($post['excerpt_type'] ?? '') == 'words' ? 'selected' : ''; ?>>Слова</option>
            </select>
        </div>

        <div class="form-row">
            <label>Длина анонса:</label>
            <input type="number" name="excerpt_length" value="<?php echo $post['excerpt_length'] ?? ''; ?>" min="0" max="2000" step="1">
            <small>0 – использовать глобальную настройку.</small>
        </div>

        <div class="seo-section" style="border-top:none;margin-top:0;padding-top:0;">
            <div class="form-row">
                <label>Автор (отображаемое имя):</label>
                <input type="text" name="display_author" value="<?php echo $post ? htmlspecialchars($post['display_author'] ?? '') : ''; ?>" size="80" placeholder="Оставьте пустым для использования логина">
            </div>
            <div class="form-row">
                <label>Canonical URL:</label>
                <input type="text" name="canonical_url" value="<?php echo $post ? htmlspecialchars($post['canonical_url'] ?? '') : ''; ?>" size="80" placeholder="Оставьте пустым для авто-генерации">
            </div>
        </div>

        <div class="seo-section">
            <h3>SEO-настройки</h3>
            <div class="form-row">
                <label>Meta Title:</label>
                <input type="text" name="meta_title" value="<?php echo $post ? htmlspecialchars($post['meta_title'] ?? '') : ''; ?>" size="80">
            </div>
            <div class="form-row">
                <label>Meta Description:</label>
                <textarea name="meta_description" rows="3" cols="80"><?php echo $post ? htmlspecialchars($post['meta_description'] ?? '') : ''; ?></textarea>
            </div>
            <div class="form-row">
                <label>Meta Keywords:</label>
                <input type="text" name="meta_keywords" value="<?php echo $post ? htmlspecialchars($post['meta_keywords'] ?? '') : ''; ?>" size="80">
            </div>
        </div>

        <div class="form-row">
            <button type="submit">Сохранить</button>
            <a href="posts.php">Отмена</a>
        </div>
    </form>

    <details class="gallery-details" <?php if ($id && !empty(getGalleryImages($id))) echo 'open'; ?>>
        <summary>📷 Фотогалерея (добавить/удалить изображения)</summary>
        <div class="gallery-content">
            <form method="post" enctype="multipart/form-data" action="?id=<?php echo $id; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="file" name="gallery_files[]" multiple accept="image/jpeg,image/png,image/gif,image/webp">
                <button type="submit" name="upload_gallery">Загрузить</button>
            </form>
            <div class="gallery-preview">
                <?php
                if ($id) {
                    $galleryImages = getGalleryImages($id);
                    if (!empty($galleryImages)) {
                        foreach ($galleryImages as $img) {
                            echo '<div class="gallery-item">';
                            echo '<img src="' . SITE_URL . '/uploads/gallery/' . htmlspecialchars($img['image']) . '" alt="">';
                            echo '<a href="?id=' . $id . '&delete_gallery=' . $img['id'] . '&csrf_token=' . $csrf_token . '" class="delete-gallery-img" onclick="return confirm(\'Удалить изображение?\')">✕</a>';
                            echo '</div>';
                        }
                    } else {
                        echo '<p>Изображения не загружены.</p>';
                    }
                } else {
                    echo '<p>Сначала сохраните пост, чтобы загрузить галерею.</p>';
                }
                ?>
            </div>
        </div>
    </details>

    <script src="<?php echo SITE_URL; ?>/src/4SLASeditor.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        window.currentPostId = <?php echo $id; ?>;
        new SimpleEditor('post-editor', 'post-content-hidden');
    });
    </script>
    <?php include __DIR__ . '/includes/admin_footer.php'; ?>
</body>
</html>
