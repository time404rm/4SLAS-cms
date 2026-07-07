<?php
require_once __DIR__ . '/db.php';

function ensurePageColumns() {
    $db = getDb();
    try {
        $db->exec("ALTER TABLE pages ADD COLUMN display_author VARCHAR(255) DEFAULT NULL AFTER meta_keywords");
    } catch (PDOException $e) {}
    try {
        $db->exec("ALTER TABLE pages ADD COLUMN canonical_url VARCHAR(500) DEFAULT NULL AFTER display_author");
    } catch (PDOException $e) {}
}

function getPageBySlug($slug) {
    $db = getDb();
    if (isAdmin()) {
        $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ?");
    } else {
        $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published'");
    }
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

function getAllPages() {
    $db = getDb();
    return $db->query("SELECT id, title, slug, status, created_at FROM pages ORDER BY created_at DESC")->fetchAll();
}

function createPage($title, $slug, $content, $status, $metaTitle = '', $metaDesc = '', $metaKeywords = '', $displayAuthor = '', $canonicalUrl = '') {
    $db = getDb();
    $stmt = $db->prepare("INSERT INTO pages (title, slug, content, status, meta_title, meta_description, meta_keywords, display_author, canonical_url) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$title, $slug, $content, $status, $metaTitle, $metaDesc, $metaKeywords, $displayAuthor, $canonicalUrl]);
    return $db->lastInsertId();
}

function updatePage($id, $title, $slug, $content, $status, $metaTitle, $metaDesc, $metaKeywords, $displayAuthor = '', $canonicalUrl = '') {
    $db = getDb();
    $stmt = $db->prepare("UPDATE pages SET title=?, slug=?, content=?, status=?, meta_title=?, meta_description=?, meta_keywords=?, display_author=?, canonical_url=? WHERE id=?");
    return $stmt->execute([$title, $slug, $content, $status, $metaTitle, $metaDesc, $metaKeywords, $displayAuthor, $canonicalUrl, $id]);
}

function deletePage($id) {
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM pages WHERE id = ?");
    return $stmt->execute([$id]);
}
?>