<?php
function checkRedirect() {
    $requestUri = $_SERVER['REQUEST_URI'];
    $path = parse_url($requestUri, PHP_URL_PATH);
    $db = getDb();
    $stmt = $db->prepare("SELECT new_url, http_code FROM redirects WHERE old_url = ?");
    $stmt->execute([$path]);
    $row = $stmt->fetch();
    if ($row) {
        $code = ($row['http_code'] == '302') ? 302 : 301;
        http_response_code($code);
        header('Location: ' . SITE_URL . $row['new_url']);
        exit;
    }
}
?>