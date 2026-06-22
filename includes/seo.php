<?php
require_once __DIR__ . '/db.php';

function getSeoData($pageType, $pageId = null) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM seo_meta WHERE page_type = ? AND (page_id = ? OR (page_id IS NULL AND ? IS NULL))");
    $stmt->execute([$pageType, $pageId, $pageId]);
    $data = $stmt->fetch();
    if (!$data) {
        return ['meta_title'=>'', 'meta_description'=>'', 'meta_keywords'=>'', 'og_title'=>'', 'og_description'=>'', 'og_image'=>''];
    }
    return $data;
}

function updateSeoData($pageType, $pageId, $fields) {
    $db = getDb();
    $exists = $db->prepare("SELECT id FROM seo_meta WHERE page_type = ? AND (page_id = ? OR (page_id IS NULL AND ? IS NULL))");
    $exists->execute([$pageType, $pageId, $pageId]);
    if ($exists->fetch()) {
        $stmt = $db->prepare("UPDATE seo_meta SET meta_title=?, meta_description=?, meta_keywords=?, og_title=?, og_description=?, og_image=? WHERE page_type=? AND (page_id=? OR (page_id IS NULL AND ? IS NULL))");
        $stmt->execute([$fields['meta_title'], $fields['meta_description'], $fields['meta_keywords'], $fields['og_title'], $fields['og_description'], $fields['og_image'], $pageType, $pageId, $pageId]);
    } else {
        $stmt = $db->prepare("INSERT INTO seo_meta (page_type, page_id, meta_title, meta_description, meta_keywords, og_title, og_description, og_image) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$pageType, $pageId, $fields['meta_title'], $fields['meta_description'], $fields['meta_keywords'], $fields['og_title'], $fields['og_description'], $fields['og_image']]);
    }
}
?>