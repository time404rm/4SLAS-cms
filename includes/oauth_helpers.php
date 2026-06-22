<?php
/**
 * Вспомогательные функции для OAuth
 * Единая точка HTTP-запросов (cURL с fallback на file_get_contents)
 */

function oauthHttpGet($url, $headers = [])
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response !== false ? $response : false;
    }

    if (ini_get('allow_url_fopen')) {
        if (!empty($headers)) {
            $opts = ['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers) . "\r\n", 'timeout' => 10]];
            $context = stream_context_create($opts);
            return @file_get_contents($url, false, $context);
        }
        return @file_get_contents($url);
    }

    error_log('OAuth HTTP: neither cURL nor allow_url_fopen is available');
    return false;
}

function oauthHttpPost($url, $postFields, $headers = [])
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POSTFIELDS => is_array($postFields) ? http_build_query($postFields) : $postFields,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response !== false ? $response : false;
    }

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => is_array($postFields) ? http_build_query($postFields) : $postFields,
            'timeout' => 10,
        ],
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    return $response !== false ? $response : false;
}

/**
 * Транслитерация строки в латиницу (безопасный username)
 */
function oauthTransliterate($text)
{
    if (function_exists('transliterator_transliterate')) {
        $result = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        if ($result !== false) return $result;
    }

    $cyr = ['а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я',
            'А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я'];
    $lat = ['a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','ts','ch','sh','sch','','y','','e','yu','ya',
            'A','B','V','G','D','E','Yo','Zh','Z','I','Y','K','L','M','N','O','P','R','S','T','U','F','H','Ts','Ch','Sh','Sch','','Y','','E','Yu','Ya'];
    return str_replace($cyr, $lat, $text);
}

/**
 * Генерация безопасного username из OAuth-имени
 */
function oauthMakeUsername($rawText, $fallback)
{
    $base = oauthTransliterate(trim($rawText));
    $base = preg_replace('/[^a-zA-Z0-9_]/', '_', $base);
    $base = trim($base, '_');
    if (empty($base)) $base = $fallback;
    return mb_substr($base, 0, 45, 'UTF-8');
}
