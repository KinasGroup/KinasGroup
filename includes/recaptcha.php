<?php
/**
 * Multi-domain reCAPTCHA helper
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

// Verify reCAPTCHA
function verifyRecaptcha($response) {
    $keys = getRecaptchaKeys();
    if (empty($keys['secret'])) return true; // Bypass if no key (dev)

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $keys['secret'],
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $result = json_decode($result);

    return $result && $result->success;
}
