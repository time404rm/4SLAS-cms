<?php
/**
 * Table of Contents — авто-оглавление из H2/H3 заголовков
 */

function tocGenerate($content) {
    // Собираем заголовки
    preg_match_all('/<h([23])([^>]*)>(.*?)<\/h[23]>/si', $content, $matches, PREG_SET_ORDER);

    if (count($matches) < 3) return $content; // TOC только если 3+ заголовка

    $tocItems = [];
    foreach ($matches as $m) {
        $level = $m[1];
        $attrs = $m[2];
        $title = strip_tags($m[3]);
        $id = 'h-' . mb_strtolower(trim(preg_replace('/[^a-zA-Zа-яА-Я0-9\s-]/u', '', $title)));
        $id = preg_replace('/[\s]+/u', '-', $id);
        $id = trim($id, '-');

        // Если ID уже существует, добавить суффикс
        $seenIds = array_column($tocItems, 'id');
        if (in_array($id, $seenIds)) {
            $counter = 2;
            while (in_array($id . '-' . $counter, $seenIds)) $counter++;
            $id = $id . '-' . $counter;
        }

        // Добавить ID к заголовку в контенте
        $oldTag = '<h' . $level . $attrs . '>' . $title . '</h' . $level . '>';
        $newTag = '<h' . $level . ' id="' . $id . '"' . $attrs . '>' . $title . '</h' . $level . '>';
        $content = str_replace($oldTag, $newTag, $content);

        $tocItems[] = [
            'level' => (int)$level,
            'title' => $title,
            'id' => $id
        ];
    }

    // HTML оглавления
    $html = '<nav class="toc" role="navigation" aria-label="Оглавление">' . "\n";
    $html .= '<div class="toc-title">📋 Содержание</div>' . "\n";
    $html .= '<ol class="toc-list">' . "\n";

    foreach ($tocItems as $item) {
        $class = $item['level'] === 3 ? ' class="toc-h3"' : '';
        $html .= '  <li' . $class . '><a href="#' . $item['id'] . '">' . htmlspecialchars($item['title']) . '</a></li>' . "\n";
    }

    $html .= '</ol>' . "\n";
    $html .= '</nav>' . "\n";

    // Добавить CSS
    $html .= '<style>
.toc { background: rgba(74,140,255,0.05); border: 1px solid rgba(74,140,255,0.15); border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; }
.toc-title { font-weight: bold; font-size: 1.05em; margin-bottom: 8px; color: inherit; }
.toc-list { list-style: none; padding: 0; margin: 0; }
.toc-list li { padding: 3px 0; }
.toc-list li.toc-h3 { padding-left: 20px; font-size: .93em; }
.toc-list a { color: #4a8cff; text-decoration: none; }
.toc-list a:hover { text-decoration: underline; }
</style>' . "\n";

    return $html . $content;
}
