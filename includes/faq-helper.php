<?php
/**
 * FAQPage Schema — шорткод [faq] для вопросов-ответов
 * 
 * Формат в редакторе:
 * [faq]
 * Вопрос: Текст вопроса
 * Ответ: Текст ответа
 * 
 * Вопрос: Следующий вопрос
 * Ответ: Следующий ответ
 * [/faq]
 */

function faqParse($content) {
    $pattern = '/\[faq\](.*?)\[\/faq\]/s';
    return preg_replace_callback($pattern, 'faqRender', $content);
}

function faqRender($matches) {
    $raw = trim($matches[1]);
    if (empty($raw)) return '';

    $lines = explode("\n", $raw);
    $items = [];
    $currentQ = '';
    $currentA = '';
    $step = 'q';

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        if (preg_match('/^Вопрос\s*:\s*(.*)/iu', $line, $m)) {
            if ($currentQ && $currentA) {
                $items[] = ['q' => $currentQ, 'a' => $currentA];
            }
            $currentQ = trim($m[1]);
            $currentA = '';
            $step = 'a';
        } elseif (preg_match('/^Ответ\s*:\s*(.*)/iu', $line, $m)) {
            $currentA = trim($m[1]);
            $step = 'done';
        } elseif ($step === 'a') {
            $currentQ .= "\n" . $line;
        } elseif ($step === 'done') {
            $currentA .= "\n" . $line;
        }
    }

    if ($currentQ && $currentA) {
        $items[] = ['q' => $currentQ, 'a' => $currentA];
    }

    if (empty($items)) return '';

    $faqId = 'faq-' . md5($raw);
    $html = '<div class="faq-block" id="' . $faqId . '" itemscope="" itemtype="https://schema.org/FAQPage" style="margin:20px 0;border-left:3px solid #4a8cff;padding:4px 0 4px 16px;background:rgba(74,140,255,0.05);border-radius:0 6px 6px 0;">' . "\n";
    $html .= '<style>.faq-item{margin:12px 0}.faq-item:first-child{margin-top:0}.faq-question{margin:0 0 4px;font-size:1.05em;color:inherit;cursor:default}.faq-answer{font-size:.95em;color:inherit;opacity:.85;line-height:1.5}</style>' . "\n";

    foreach ($items as $i => $item) {
        $html .= '  <div class="faq-item" itemscope="" itemprop="mainEntity" itemtype="https://schema.org/Question">' . "\n";
        $html .= '    <h3 class="faq-question" itemprop="name">' . htmlspecialchars($item['q']) . '</h3>' . "\n";
        $html .= '    <div class="faq-answer" itemscope="" itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">' . "\n";
        $html .= '      <div itemprop="text">' . nl2br(htmlspecialchars($item['a'])) . '</div>' . "\n";
        $html .= '    </div>' . "\n";
        $html .= '  </div>' . "\n";
    }

    $html .= '</div>' . "\n";

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => []
    ];

    foreach ($items as $item) {
        $jsonLd['mainEntity'][] = [
            '@type' => 'Question',
            'name' => $item['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $item['a']
            ]
        ];
    }

    $GLOBALS['_faq_jsonld'][] = $jsonLd;

    return $html;
}

function faqRenderJsonLd() {
    if (empty($GLOBALS['_faq_jsonld'])) return '';

    $out = '';
    foreach ($GLOBALS['_faq_jsonld'] as $ld) {
        $out .= '<script type="application/ld+json">' . "\n"
              . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
              . '</script>' . "\n";
    }
    return $out;
}
