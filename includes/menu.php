<?php
require_once __DIR__ . '/db.php';

// Получение пунктов меню с защитой от циклов
function getMenuItems($parentId = 0, &$processed = [], $maxDepth = 10) {
    static $currentDepth = 0;
    $currentDepth++;
    if ($currentDepth > $maxDepth) { $currentDepth--; return []; }
    $db = getDb();
    if ($parentId == 0) {
    $stmt = $db->prepare("SELECT * FROM menu_items WHERE (parent_id = 0 OR parent_id IS NULL) AND status = 1 ORDER BY sort_order ASC");
    $stmt->execute();
} else {
    $stmt = $db->prepare("SELECT * FROM menu_items WHERE parent_id = ? AND status = 1 ORDER BY sort_order ASC");
    $stmt->execute([$parentId]);
}
    $items = $stmt->fetchAll();
    foreach ($items as &$item) {
        if (in_array($item['id'], $processed)) { $item['children'] = []; continue; }
        $processed[] = $item['id'];
        $item['children'] = getMenuItems($item['id'], $processed, $maxDepth);
    }
    $currentDepth--;
    return $items;
}

function getAllMenuItems() {
    $db = getDb();
    return $db->query("SELECT * FROM menu_items ORDER BY sort_order")->fetchAll();
}

function addMenuItem($title, $url, $parentId = null, $target = '_self', $icon = '', $sortOrder = 0) {
    // Если parentId == 0, делаем null
    if ($parentId == 0) $parentId = null;
    $db = getDb();
    $stmt = $db->prepare("INSERT INTO menu_items (title, url, parent_id, target, icon, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$title, $url, $parentId, $target, $icon, $sortOrder]);
}

function updateMenuItem($id, $title, $url, $parentId, $target, $icon, $sortOrder, $status) {
    if ($parentId == 0) $parentId = null;
    $db = getDb();
    $stmt = $db->prepare("UPDATE menu_items SET title=?, url=?, parent_id=?, target=?, icon=?, sort_order=?, status=? WHERE id=?");
    return $stmt->execute([$title, $url, $parentId, $target, $icon, $sortOrder, $status, $id]);
}

function deleteMenuItem($id) {
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
    return $stmt->execute([$id]);
}

// Функция для отрисовки HTML-меню (рекурсивная)
function renderMenu($items) {
    if (empty($items)) return '';
    $html = '<ul class="nav-menu">';
    foreach ($items as $item) {
        $target = ($item['target'] ?? '_self') === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '';
        $iconHtml = !empty($item['icon']) ? '<i class="' . htmlspecialchars($item['icon']) . '"></i> ' : '';
        $html .= '<li>';
        $html .= '<a href="' . htmlspecialchars($item['url']) . '"' . $target . '>' . $iconHtml . htmlspecialchars($item['title']) . '</a>';
        if (!empty($item['children'])) {
            $html .= renderMenu($item['children']);
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}
?>