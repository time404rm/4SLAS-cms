<?php
/**
 * MD Parser — конвертер Markdown в HTML
 * Без зависимостей, чистыми регулярками
 */

function mdParse($text) {
    if (empty($text)) return '';

    // Извлекаем frontmatter (--- title: ... ---)
    $frontmatter = [];
    if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $text, $fm)) {
        $text = substr($text, strlen($fm[0]));
        foreach (explode("\n", $fm[1]) as $line) {
            if (preg_match('/^(\w+):\s*(.*)$/', trim($line), $m)) {
                $frontmatter[strtolower($m[1])] = trim($m[2]);
            }
        }
    }

    // Экранирование HTML-сущностей в тексте (кроме блоков кода)
    $text = _mdEscapeHtml($text);

    // Обработка блоков
    $text = _mdCodeBlocks($text);
    $text = _mdHeaders($text);
    $text = _mdBlockquotes($text);
    $text = _mdLists($text);
    $text = _mdHorizontalRules($text);
    $text = _mdParagraphs($text);

    // Inline-разметка
    $text = _mdInline($text);

    return ['html' => trim($text), 'meta' => $frontmatter];
}

function _mdEscapeHtml($text) {
    // Не экранируем внутри блоков кода
    return preg_replace_callback('/```.*?```/s', function($m) { return $m[0]; },
        preg_replace_callback('/`[^`]+`/', function($m) { return $m[0]; },
            str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text)
        )
    );
}

function _mdCodeBlocks($text) {
    // Fenced code blocks ```lang ... ```
    $text = preg_replace_callback('/```(\w*)\s*\n(.*?)```/s', function($m) {
        $lang = htmlspecialchars($m[1]);
        $code = trim($m[2]);
        $escaped = htmlspecialchars($code);
        if ($lang) {
            return "<pre><code class=\"language-{$lang}\">{$escaped}</code></pre>";
        }
        return "<pre><code>{$escaped}</code></pre>";
    }, $text);

    // Indented code blocks (4 пробела)
    $text = preg_replace_callback('/(?:^ {4}.+?\n)+/m', function($m) {
        $code = '';
        foreach (explode("\n", trim($m[0])) as $line) {
            $code .= substr($line, 4) . "\n";
        }
        return '<pre><code>' . htmlspecialchars(trim($code)) . '</code></pre>';
    }, $text);

    return $text;
}

function _mdHeaders($text) {
    // H1-H6: ## text
    for ($i = 6; $i >= 1; $i--) {
        $pat = '/^' . str_repeat('#', $i) . '\s+(.+)$/m';
        $text = preg_replace($pat, "<h{$i}>$1</h{$i}>", $text);
    }
    // Setext-style H1/H2 (=== / --- под строкой)
    $text = preg_replace('/^(.+)\n=+\s*$/m', '<h1>$1</h1>', $text);
    $text = preg_replace('/^(.+)\n-+\s*$/m', '<h2>$1</h2>', $text);
    return $text;
}

function _mdBlockquotes($text) {
    return preg_replace_callback('/^(>\s?.+)$/m', function($m) {
        $content = preg_replace('/^>\s?/m', '', $m[0]);
        return '<blockquote>' . trim($content) . '</blockquote>';
    }, $text);
}

function _mdLists($text) {
    // Нумерованные списки
    $text = preg_replace_callback('/(?:^(\d+)\.\s.+)\n?(?:\n\1\.\s.+)*/m', function($m) {
        $items = preg_split('/\n(?=\d+\.\s)/', trim($m[0]));
        $html = "<ol>\n";
        foreach ($items as $item) {
            $html .= '<li>' . preg_replace('/^\d+\.\s/', '', trim($item)) . "</li>\n";
        }
        return $html . "</ol>\n";
    }, $text);

    // Маркированные списки
    $text = preg_replace_callback('/(?:^[-*+]\s.+)\n?(?:\n[-*+]\s.+)*/m', function($m) {
        $items = explode("\n", trim($m[0]));
        $html = "<ul>\n";
        foreach ($items as $item) {
            $html .= '<li>' . preg_replace('/^[-*+]\s/', '', trim($item)) . "</li>\n";
        }
        return $html . "</ul>\n";
    }, $text);

    return $text;
}

function _mdHorizontalRules($text) {
    return preg_replace('/^([-*_]){3,}\s*$/m', '<hr>', $text);
}

function _mdParagraphs($text) {
    // Оборачиваем строки, не являющиеся блоками, в <p>
    $blocks = 'h[1-6]|ul|ol|li|blockquote|pre|hr|table';
    $lines = explode("\n", $text);
    $inParagraph = false;
    $result = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed)) {
            if ($inParagraph) { $result[] = '</p>'; $inParagraph = false; }
            continue;
        }
        if (preg_match("/^<({$blocks})/", $trimmed) || preg_match("/^<\/({$blocks})>/", $trimmed)) {
            if ($inParagraph) { $result[] = '</p>'; $inParagraph = false; }
            $result[] = $line;
            continue;
        }
        if (!$inParagraph) { $result[] = '<p>'; $inParagraph = true; }
        $result[] = $line;
    }
    if ($inParagraph) $result[] = '</p>';

    return implode("\n", $result);
}

function _mdInline($text) {
    // Код `code`
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

    // Ссылки [text](url)
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);

    // Изображения ![alt](url)
    $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" style="max-width:100%">', $text);

    // Bold **text** или __text__
    $text = preg_replace('/\*\*(.+?)\*\*|__(.+?)__/s', '<strong>$1$2</strong>', $text);

    // Italic *text* или _text_
    $text = preg_replace('/\*(.+?)\*|_(.+?)_/s', '<em>$1$2</em>', $text);

    // Striketrough ~~text~~
    $text = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $text);

    return $text;
}

function mdSlugify($text) {
    $text = mb_strtolower(trim($text), 'UTF-8');
    $cyr = ['а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я',' ','-','_','—','–','+'];
    $lat = ['a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','ts','ch','sh','sch','','y','','e','yu','ya','-','-','-','','',''];
    $text = str_replace($cyr, $lat, $text);
    $text = preg_replace('/[^a-z0-9-]/', '', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}
