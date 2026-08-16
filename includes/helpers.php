<?php

// KINAS GROUP - Helper Functions

function asset($path)
{
    return '/assets/' . ltrim($path, '/');
}

function url($path = '')
{
    return 'https://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($path, '/');
}

if (!function_exists('redirect')) {
    function redirect($url)
    {
        header("Location: $url");
        exit();
    }
}

function old($key, $default = '')
{
    return htmlspecialchars($_SESSION['old_input'][$key] ?? $default);
}

function error($key)
{
    if (isset($_SESSION['errors'][$key])) {
        $error = $_SESSION['errors'][$key];
        unset($_SESSION['errors'][$key]);
        return '<span class="field-error">' . $error . '</span>';
    }

    return '';
}

function formatPrice($price, $currency = '₦')
{
    return '₦' . number_format($price, 0, '.', ',');
}

/**
 * The buyer-facing price for a KINAS Marketplace listing.
 */
function marketplaceBuyerPrice(float $rawPrice): float
{
    if ($rawPrice <= 0) {
        return $rawPrice;
    }

    if (!class_exists('PaystackService')) {
        require_once __DIR__ . '/paystack.php';
    }

    $passFeesToBuyer = strtolower(getenv('PAYSTACK_PASS_FEES_TO_BUYER') ?: 'true') !== 'false';

    return $passFeesToBuyer ? PaystackService::grossUpForFee($rawPrice) : $rawPrice;
}

function flash($key)
{
    return SessionManager::getFlash($key);
}

function formatNumber($number)
{
    return number_format($number);
}

function truncate($text, $length = 100)
{
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }

    return $text;
}

function timeAgo($datetime)
{
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . 'm ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . 'h ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . 'd ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

function slugify($text)
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);

    return $text ?: 'n-a';
}

function getClientIP()
{
    $ipaddress = '';

    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }

    return $ipaddress;
}

function paginate($total, $current, $perPage = 12)
{
    $totalPages = ceil($total / $perPage);
    $pages = [];

    for ($i = max(1, $current - 2); $i <= min($totalPages, $current + 2); $i++) {
        $pages[] = $i;
    }

    return [
        'current' => $current,
        'total' => $totalPages,
        'pages' => $pages,
        'hasPrev' => $current > 1,
        'hasNext' => $current < $totalPages,
        'prevUrl' => '?page=' . ($current - 1),
        'nextUrl' => '?page=' . ($current + 1)
    ];
}

/* ============================================================
COUNTRY PHONE HELPERS
============================================================ */

function kinas_unicode_chr(int $code): string
{
    if ($code < 0x80) {
        return chr($code);
    }

    if ($code < 0x800) {
        return chr(0xC0 | ($code >> 6))
            . chr(0x80 | ($code & 0x3F));
    }

    if ($code < 0x10000) {
        return chr(0xE0 | ($code >> 12))
            . chr(0x80 | (($code >> 6) & 0x3F))
            . chr(0x80 | ($code & 0x3F));
    }

    return chr(0xF0 | ($code >> 18))
        . chr(0x80 | (($code >> 12) & 0x3F))
        . chr(0x80 | (($code >> 6) & 0x3F))
        . chr(0x80 | ($code & 0x3F));
}

function kinas_country_flag(string $iso2): string
{
    $iso2 = strtoupper(trim($iso2));

    if (strlen($iso2) !== 2 || !ctype_alpha($iso2)) {
        return '🌐';
    }

    $base = 0x1F1E6 - 65;

    return kinas_unicode_chr($base + ord($iso2[0]))
        . kinas_unicode_chr($base + ord($iso2[1]));
}

function kinas_countries(): array
{
    static $countries = null;

    if ($countries !== null) {
        return $countries;
    }

    $raw = [
        ['NG', 'Nigeria', '234'],
        ['GB', 'United Kingdom', '44'],
        ['US', 'United States', '1'],
        ['CA', 'Canada', '1'],
        ['GH', 'Ghana', '233'],
        ['ZA', 'South Africa', '27'],
        ['KE', 'Kenya', '254'],
        ['CM', 'Cameroon', '237'],
        ['SN', 'Senegal', '221'],
        ['CI', "Côte d'Ivoire", '225'],
        ['BF', 'Burkina Faso', '226'],
        ['ML', 'Mali', '223'],
        ['NE', 'Niger', '227'],
        ['TD', 'Chad', '235'],
        ['BJ', 'Benin', '229'],
        ['TG', 'Togo', '228'],
        ['GW', 'Guinea-Bissau', '245'],
        ['GN', 'Guinea', '224'],
        ['SL', 'Sierra Leone', '232'],
        ['LR', 'Liberia', '231'],
        ['GM', 'Gambia', '220'],
        ['CV', 'Cape Verde', '238'],
        ['ST', 'São Tomé and Príncipe', '239'],
        ['GQ', 'Equatorial Guinea', '240'],
        ['GA', 'Gabon', '241'],
        ['CG', 'Congo - Brazzaville', '242'],
        ['CD', 'Congo - Kinshasa', '243'],
        ['AO', 'Angola', '244'],
        ['ZM', 'Zambia', '260'],
        ['ZW', 'Zimbabwe', '263'],
        ['MW', 'Malawi', '265'],
        ['MZ', 'Mozambique', '258'],
        ['TZ', 'Tanzania', '255'],
        ['UG', 'Uganda', '256'],
        ['RW', 'Rwanda', '250'],
        ['BI', 'Burundi', '257'],
        ['ET', 'Ethiopia', '251'],
        ['SO', 'Somalia', '252'],
        ['SS', 'South Sudan', '211'],
        ['SD', 'Sudan', '249'],
        ['ER', 'Eritrea', '291'],
        ['DJ', 'Djibouti', '253'],
        ['EG', 'Egypt', '20'],
        ['LY', 'Libya', '218'],
        ['TN', 'Tunisia', '216'],
        ['DZ', 'Algeria', '213'],
        ['MA', 'Morocco', '212'],
        ['MR', 'Mauritania', '222'],
        ['FR', 'France', '33'],
        ['DE', 'Germany', '49'],
        ['IT', 'Italy', '39'],
        ['ES', 'Spain', '34'],
        ['PT', 'Portugal', '351'],
        ['NL', 'Netherlands', '31'],
        ['BE', 'Belgium', '32'],
        ['LU', 'Luxembourg', '352'],
        ['IE', 'Ireland', '353'],
        ['CH', 'Switzerland', '41'],
        ['AT', 'Austria', '43'],
        ['PL', 'Poland', '48'],
        ['CZ', 'Czech Republic', '420'],
        ['SK', 'Slovakia', '421'],
        ['HU', 'Hungary', '36'],
        ['RO', 'Romania', '40'],
        ['BG', 'Bulgaria', '359'],
        ['GR', 'Greece', '30'],
        ['SE', 'Sweden', '46'],
        ['NO', 'Norway', '47'],
        ['DK', 'Denmark', '45'],
        ['FI', 'Finland', '358'],
        ['EE', 'Estonia', '372'],
        ['LV', 'Latvia', '371'],
        ['LT', 'Lithuania', '370'],
        ['UA', 'Ukraine', '380'],
        ['BY', 'Belarus', '375'],
        ['RU', 'Russia', '7'],
        ['TR', 'Türkiye', '90'],
        ['IS', 'Iceland', '354'],
        ['AE', 'United Arab Emirates', '971'],
        ['SA', 'Saudi Arabia', '966'],
        ['QA', 'Qatar', '974'],
        ['KW', 'Kuwait', '965'],
        ['BH', 'Bahrain', '973'],
        ['OM', 'Oman', '968'],
        ['JO', 'Jordan', '962'],
        ['LB', 'Lebanon', '961'],
        ['SY', 'Syria', '963'],
        ['IQ', 'Iraq', '964'],
        ['IR', 'Iran', '98'],
        ['IL', 'Israel', '972'],
        ['PS', 'Palestine', '970'],
        ['YE', 'Yemen', '967'],
        ['IN', 'India', '91'],
        ['PK', 'Pakistan', '92'],
        ['BD', 'Bangladesh', '880'],
        ['LK', 'Sri Lanka', '94'],
        ['NP', 'Nepal', '977'],
        ['BT', 'Bhutan', '975'],
        ['MV', 'Maldives', '960'],
        ['CN', 'China', '86'],
        ['JP', 'Japan', '81'],
        ['KR', 'South Korea', '82'],
        ['TW', 'Taiwan', '886'],
        ['HK', 'Hong Kong', '852'],
        ['MO', 'Macau', '853'],
        ['MN', 'Mongolia', '976'],
        ['SG', 'Singapore', '65'],
        ['MY', 'Malaysia', '60'],
        ['ID', 'Indonesia', '62'],
        ['PH', 'Philippines', '63'],
        ['TH', 'Thailand', '66'],
        ['VN', 'Vietnam', '84'],
        ['KH', 'Cambodia', '855'],
        ['LA', 'Laos', '856'],
        ['MM', 'Myanmar', '95'],
        ['BN', 'Brunei', '673'],
        ['TL', 'Timor-Leste', '670'],
        ['AU', 'Australia', '61'],
        ['NZ', 'New Zealand', '64'],
        ['FJ', 'Fiji', '679'],
        ['PG', 'Papua New Guinea', '675'],
        ['SB', 'Solomon Islands', '677'],
        ['VU', 'Vanuatu', '678'],
        ['MX', 'Mexico', '52'],
        ['BR', 'Brazil', '55'],
        ['AR', 'Argentina', '54'],
        ['CL', 'Chile', '56'],
        ['CO', 'Colombia', '57'],
        ['PE', 'Peru', '51'],
        ['VE', 'Venezuela', '58'],
        ['EC', 'Ecuador', '593'],
        ['BO', 'Bolivia', '591'],
        ['PY', 'Paraguay', '595'],
        ['UY', 'Uruguay', '598'],
        ['GY', 'Guyana', '592'],
        ['SR', 'Suriname', '597'],
        ['CR', 'Costa Rica', '506'],
        ['PA', 'Panama', '507'],
        ['GT', 'Guatemala', '502'],
        ['BZ', 'Belize', '501'],
        ['SV', 'El Salvador', '503'],
        ['HN', 'Honduras', '504'],
        ['NI', 'Nicaragua', '505'],
        ['CU', 'Cuba', '53'],
        ['DO', 'Dominican Republic', '1'],
        ['HT', 'Haiti', '509'],
        ['JM', 'Jamaica', '1'],
        ['TT', 'Trinidad and Tobago', '1'],
    ];

    $list = [];

    foreach ($raw as $row) {
        $iso2 = $row[0];
        $name = $row[1];
        $dial = $row[2];

        $list[] = [
            'iso2' => $iso2,
            'name' => $name,
            'dial' => $dial,
            'flag' => kinas_country_flag($iso2),
        ];
    }

    usort($list, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    $nigeria = null;

    foreach ($list as $index => $country) {
        if ($country['iso2'] === 'NG') {
            $nigeria = $country;
            unset($list[$index]);
            break;
        }
    }

    if ($nigeria !== null) {
        array_unshift($list, $nigeria);
    }

    $countries = array_values($list);

    return $countries;
}

function kinas_country_by_iso(?string $iso2): ?array
{
    $iso2 = strtoupper(trim((string)$iso2));

    if ($iso2 === '') {
        return null;
    }

    foreach (kinas_countries() as $country) {
        if ($country['iso2'] === $iso2) {
            return $country;
        }
    }

    return null;
}

function kinas_normalize_phone(?string $countryIso2, ?string $phone): ?string
{
    $country = kinas_country_by_iso($countryIso2);

    if (!$country) {
        return null;
    }

    $phone = (string)$phone;
    $digits = preg_replace('/\D+/', '', $phone);

    if ($digits === '') {
        return null;
    }

    // Remove common international prefixes.
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    } elseif (str_starts_with($digits, '011')) {
        $digits = substr($digits, 3);
    }

    $dial = (string)$country['dial'];

    // If the user already included the country code, remove it.
    if (str_starts_with($digits, $dial)) {
        $digits = substr($digits, strlen($dial));
    }

    // Remove common local trunk prefix zeros.
    $digits = ltrim($digits, '0');

    if ($digits === '') {
        return null;
    }

    $normalized = '+' . $dial . $digits;

    // Rough E.164-style length guard.
    if (strlen($normalized) < 8 || strlen($normalized) > 16) {
        return null;
    }

    return $normalized;
}

function kinas_phone_error(?string $countryIso2, ?string $phone): ?string
{
    $countryIso2 = strtoupper(trim((string)$countryIso2));

    if ($countryIso2 === '') {
        return 'Please select your country.';
    }

    if (!kinas_country_by_iso($countryIso2)) {
        return 'Please select a valid country.';
    }

    if (trim((string)$phone) === '') {
        return 'Phone is required.';
    }

    $normalized = kinas_normalize_phone($countryIso2, $phone);

    if ($normalized === null) {
        return 'Please enter a valid phone number.';
    }

    return null;
}
