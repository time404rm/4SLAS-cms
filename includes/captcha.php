<?php
/**
 * Система капчи: текстовая, графическая + honeypot
 */

/**
 * Генерирует капчу (текст или картинка — случайно)
 * @return string HTML или текст вопроса
 */
function generateCaptcha() {
    $type = (!function_exists('imagecreatetruecolor') || mt_rand(0, 1) === 0) ? 'text' : 'image';
    $_SESSION['captcha_type'] = $type;
    
    if ($type === 'image') {
        return '<div class="captcha-image-wrap">
            <img src="' . SITE_URL . '/api/captcha_image.php?t=' . time() . '" alt="Капча" class="captcha-img">
            <a href="#" class="captcha-refresh" title="Обновить">&#x21bb;</a>
        </div>';
    }
    
    return generateTextCaptcha();
}

/**
 * Текстовая капча — расширенный набор вопросов
 */
function generateTextCaptcha() {
    $lang = $_SESSION['lang'] ?? 'ru';
    
    if ($lang === 'en') {
        $questions = [
            ['q' => 'Type the word "hello"', 'a' => 'hello'],
            ['q' => 'What color is grass? (one word)', 'a' => 'green'],
            ['q' => 'How many days in a week? (write in letters)', 'a' => 'seven'],
            ['q' => 'Type the word "blog"', 'a' => 'blog'],
            ['q' => 'What year is it now?', 'a' => date('Y')],
            ['q' => 'What is 5 + 3?', 'a' => '8'],
            ['q' => 'Type the word "website"', 'a' => 'website'],
            ['q' => 'What color is the sky? (one word)', 'a' => 'blue'],
            ['q' => 'How many months in a year? (digits)', 'a' => '12'],
            ['q' => 'Type the word "computer"', 'a' => 'computer'],
            ['q' => 'What is 10 − 4?', 'a' => '6'],
            ['q' => 'Type the word "internet"', 'a' => 'internet'],
            ['q' => 'What color is snow? (one word)', 'a' => 'white'],
            ['q' => 'How many hours in a day? (digits)', 'a' => '24'],
            ['q' => 'Type the word "password"', 'a' => 'password'],
            ['q' => 'What is 3 × 3?', 'a' => '9'],
            ['q' => 'Type the word "keyboard"', 'a' => 'keyboard'],
            ['q' => 'What color is a banana? (one word)', 'a' => 'yellow'],
            ['q' => 'How many legs does a cat have? (digits)', 'a' => '4'],
            ['q' => 'Type the word "register"', 'a' => 'register'],
        ];
    } else {
        $questions = [
            ['q' => 'Напишите слово "привет"', 'a' => 'привет'],
            ['q' => 'Какого цвета трава? (одно слово)', 'a' => 'зелёного'],
            ['q' => 'Сколько дней в неделе? (буквами)', 'a' => 'семь'],
            ['q' => 'Напишите слово "блог"', 'a' => 'блог'],
            ['q' => 'Какой сейчас год?', 'a' => date('Y')],
            ['q' => 'Сколько будет 5 + 3?', 'a' => '8'],
            ['q' => 'Напишите слово "сайт"', 'a' => 'сайт'],
            ['q' => 'Какого цвета небо? (одно слово)', 'a' => 'голубого'],
            ['q' => 'Сколько месяцев в году? (цифрами)', 'a' => '12'],
            ['q' => 'Напишите слово "компьютер"', 'a' => 'компьютер'],
            ['q' => 'Сколько будет 10 − 4?', 'a' => '6'],
            ['q' => 'Напишите слово "интернет"', 'a' => 'интернет'],
            ['q' => 'Какого цвета снег? (одно слово)', 'a' => 'белого'],
            ['q' => 'Сколько часов в сутках? (цифрами)', 'a' => '24'],
            ['q' => 'Напишите слово "пароль"', 'a' => 'пароль'],
            ['q' => 'Сколько будет 3 × 3?', 'a' => '9'],
            ['q' => 'Напишите слово "клавиатура"', 'a' => 'клавиатура'],
            ['q' => 'Какого цвета банан? (одно слово)', 'a' => 'жёлтого'],
            ['q' => 'Сколько лап у кошки? (цифрами)', 'a' => '4'],
            ['q' => 'Напишите слово "регистрация"', 'a' => 'регистрация'],
        ];
    }
    
    $index = array_rand($questions);
    $answer = mb_strtolower(trim($questions[$index]['a']));
    $_SESSION[CAPTCHA_SESSION_KEY] = $answer;
    
    return $questions[$index]['q'];
}

/**
 * Проверяет ответ капчи (текст или картинка)
 */
function verifyCaptcha($answer) {
    $type = $_SESSION['captcha_type'] ?? 'text';
    
    if ($type === 'image') {
        if (!isset($_SESSION['captcha_image_answer'])) return false;
        $normalized = mb_strtolower(trim($answer));
        return $normalized === $_SESSION['captcha_image_answer'];
    }
    
    // text
    if (!isset($_SESSION[CAPTCHA_SESSION_KEY])) return false;
    $normalized = mb_strtolower(trim($answer));
    return $normalized === $_SESSION[CAPTCHA_SESSION_KEY];
}

/**
 * Очищает данные капчи из сессии (после проверки)
 */
function clearCaptcha() {
    unset($_SESSION['captcha_type']);
    unset($_SESSION[CAPTCHA_SESSION_KEY]);
    unset($_SESSION['captcha_image_answer']);
}

/**
 * Генерирует HTML honeypot-поля (скрытое от людей, видимое ботам)
 * @param string $name Имя поля
 * @return string HTML
 */
function generateHoneypot($name = 'website_url') {
    return '<div class="hp-field" aria-hidden="true">'
        . '<label for="hp_' . $name . '">Leave this empty</label>'
        . '<input type="text" name="' . $name . '" id="hp_' . $name . '" value="" tabindex="-1" autocomplete="off">'
        . '</div>';
}

/**
 * Проверяет honeypot — если заполнено, значит бот
 * @param string $name Имя поля
 * @return bool true = бот
 */
function isHoneypotFilled($name = 'website_url') {
    return !empty(trim($_POST[$name] ?? ''));
}
?>
