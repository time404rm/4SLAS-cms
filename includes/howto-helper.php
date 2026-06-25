<?php
/**
 * HowTo Schema — шорткод [howto] для пошаговых инструкций
 * 
 * Формат:
 * [howto]
 * Шаг 1: Описание первого шага
 * Шаг 2: Описание второго шага
 * Шаг 3: Описание третьего шага
 * [/howto]
 */

function howtoParse($content) {
    $pattern = '/\[howto\](.*?)\[\/howto\]/s';
    return preg_replace_callback($pattern, 'howtoRender', $content);
}

function howtoRender($matches) {
    $raw = trim($matches[1]);
    if (empty($raw)) return '';

    $lines = explode("\n", $raw);
    $steps = [];
    $currentStep = '';
    $currentText = '';

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        if (preg_match('/^Шаг\s*(\d+)\s*:\s*(.*)/iu', $line, $m)) {
            if ($currentStep) {
                $steps[] = ['name' => $currentStep, 'text' => $currentText];
            }
            $currentStep = trim($m[2]);
            $currentText = '';
        } elseif ($currentStep) {
            $currentText .= ($currentText ? "\n" : '') . $line;
        }
    }

    if ($currentStep) {
        $steps[] = ['name' => $currentStep, 'text' => $currentText];
    }

    if (empty($steps)) return '';

    $html = '<div class="howto-block" itemscope="" itemtype="https://schema.org/HowTo" style="margin:20px 0;border-left:3px solid #4caf50;padding:4px 0 4px 16px;background:rgba(76,175,80,0.05);border-radius:0 6px 6px 0;">';
    $html .= '<div class="howto-title" style="font-weight:bold;font-size:1.05em;margin-bottom:12px;">📋 Пошаговая инструкция</div>';
    $html .= '<meta itemprop="name" content="Инструкция">';
    $html .= '<ol itemprop="step" itemscope="" itemtype="https://schema.org/ItemList" style="margin:0;padding-left:20px;">';

    foreach ($steps as $i => $step) {
        $pos = $i + 1;
        $html .= '<li itemprop="itemListElement" itemscope="" itemtype="https://schema.org/ListItem" style="margin:8px 0;">';
        $html .= '<meta itemprop="position" content="' . $pos . '">';
        $html .= '<div itemprop="item" itemscope="" itemtype="https://schema.org/HowToStep">';
        $html .= '<strong itemprop="name">' . htmlspecialchars($step['name']) . '</strong>';
        if ($step['text']) {
            $html .= '<div itemprop="text" style="margin-top:4px;font-size:.95em;opacity:.85;">' . nl2br(htmlspecialchars($step['text'])) . '</div>';
        }
        $html .= '</div></li>';
    }

    $html .= '</ol></div>';

    // JSON-LD
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => 'Инструкция',
        'step' => []
    ];
    foreach ($steps as $i => $step) {
        $jsonLd['step'][] = [
            '@type' => 'HowToStep',
            'position' => $i + 1,
            'name' => $step['name'],
            'text' => $step['text']
        ];
    }

    $GLOBALS['_howto_jsonld'][] = $jsonLd;

    return $html;
}

function howtoRenderJsonLd() {
    if (empty($GLOBALS['_howto_jsonld'])) return '';
    $out = '';
    foreach ($GLOBALS['_howto_jsonld'] as $ld) {
        $out .= '<script type="application/ld+json">' . "\n"
              . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
              . '</script>' . "\n";
    }
    return $out;
}
