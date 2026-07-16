<?php
/**
 * KINAS GROUP - Multi-Domain reCAPTCHA Helper
 */

function getRecaptchaKeys() {
    $host = str_replace('www.', '', strtolower($_SERVER['HTTP_HOST'] ?? 'kinas-group.com'));

    $keys = [
        'kinasvolt.com' => [
            'site' => $_ENV['CAPTCHA_SITE_KEY_KINASVOLT'] ?? $_ENV['CAPTCHA_SITE_KEY'],
            'secret' => $_ENV['CAPTCHA_SECRET_KEY_KINASVOLT'] ?? $_ENV['CAPTCHA_SECRET_KEY']
        ],
        'kinasauto.com' => [
            'site' => $_ENV['CAPTCHA_SITE_KEY_KINASAUTO'] ?? $_ENV['CAPTCHA_SITE_KEY'],
            'secret' => $_ENV['CAPTCHA_SECRET_KEY_KINASAUTO'] ?? $_ENV['CAPTCHA_SECRET_KEY']
        ],
        'kinasstore.com' => [
            'site' => $_ENV['CAPTCHA_SITE_KEY_KINASSTORE'] ?? $_ENV['CAPTCHA_SITE_KEY'],
            'secret' => $_ENV['CAPTCHA_SECRET_KEY_KINASSTORE'] ?? $_ENV['CAPTCHA_SECRET_KEY']
        ],
        'williamsconnecthome.com' => [
            'site' => $_ENV['CAPTCHA_SITE_KEY_WILLIAMS'] ?? $_ENV['CAPTCHA_SITE_KEY'],
            'secret' => $_ENV['CAPTCHA_SECRET_KEY_WILLIAMS'] ?? $_ENV['CAPTCHA_SECRET_KEY']
        ]
    ];

    return $keys[$host] ?? [
        'site' => $_ENV['CAPTCHA_SITE_KEY'],
        'secret' => $_ENV['CAPTCHA_SECRET_KEY']
    ];
}

function verifyRecaptcha($response) {
    $keys = getRecaptchaKeys();
    if (empty($keys['secret']) || empty($response)) {
        return true; // Bypass in dev or if no key
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = http_build_query([
        'secret' => $keys['secret'],
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => $data
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $result = json_decode($result, true);

    return isset($result['success']) && $result['success'] === true;
}
