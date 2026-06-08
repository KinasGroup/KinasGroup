<?php
// KINAS GROUP - Helper Functions

function asset($path) {
    return '/assets/' . ltrim($path, '/');
}

function url($path = '') {
    return 'https://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($path, '/');
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
}

function old($key, $default = '') {
    return htmlspecialchars($_SESSION['old_input'][$key] ?? $default);
}

function error($key) {
    if (isset($_SESSION['errors'][$key])) {
        $error = $_SESSION['errors'][$key];
        unset($_SESSION['errors'][$key]);
        return '<span class="field-error">' . $error . '</span>';
    }
    return '';
}


function formatPrice($price, $currency = '₦') {
    return '₦' . number_format($price, 0, '.', ',');
}
function flash($key) {
    return SessionManager::getFlash($key);
}


function formatNumber($number) {
    return number_format($number);
}

function truncate($text, $length = 100) {
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }
    return $text;
}

function timeAgo($datetime) {
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

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text ?: 'n-a';
}

function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function paginate($total, $current, $perPage = 12) {
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
?>
