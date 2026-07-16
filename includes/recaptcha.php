<?php
function getRecaptchaKeys() {
    $host = str_replace('www.', '', strtolower($_SERVER['HTTP_HOST'] ?? ''));

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
