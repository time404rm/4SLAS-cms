<?php
require_once __DIR__ . '/db.php';

function getPageBySlug($slug) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published'");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

function getAllPages() {
    $db = getDb();
    return $db->query("SELECT id, title, slug, status, created_at FROM pages ORDER BY created_at DESC")->fetchAll();
}

function createPage($title, $slug, $content, $status, $metaTitle = '', $metaDesc = '', $metaKeywords = '') {
    $db = getDb();
    $stmt = $db->prepare("INSERT INTO pages (title, slug, content, status, meta_title, meta_description, meta_keywords) VALUES (?,?,?,?,?,?,?)");
    return $stmt->execute([$title, $slug, $content, $status, $metaTitle, $metaDesc, $metaKeywords]);
}

function updatePage($id, $title, $slug, $content, $status, $metaTitle, $metaDesc, $metaKeywords) {
    $db = getDb();
    $stmt = $db->prepare("UPDATE pages SET title=?, slug=?, content=?, status=?, meta_title=?, meta_description=?, meta_keywords=? WHERE id=?");
    return $stmt->execute([$title, $slug, $content, $status, $metaTitle, $metaDesc, $metaKeywords, $id]);
}

function deletePage($id) {
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM pages WHERE id = ?");
    return $stmt->execute([$id]);
}
?>