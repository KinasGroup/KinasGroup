<?php
/**
 * Core Functions for KINAS GROUP Platform
 * All existing PHP logic preserved - only styling comments added
 */

// Function to sanitize user input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to generate random string
function generate_random_string($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Function to format price in Nigerian Naira
function format_price($price) {
    return '₦' . number_format($price, 2);
}

// Function to get time ago string
function time_ago($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just Now";
    } else if ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return ($days == 1) ? "yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}

// Function to truncate text
function truncate_text($text, $length = 100, $ending = '...') {
    if (strlen($text) > $length) {
        $text = substr($text, 0, $length - strlen($ending)) . $ending;
    }
    return $text;
}

// Function to get listing status badge HTML (STYLED)
function get_status_badge($status) {
    $badge_classes = [
        'active' => 'status-badge status-active',
        'pending' => 'status-badge status-pending',
        'sold' => 'status-badge status-sold',
        'expired' => 'status-badge status-expired',
        'flagged' => 'status-badge status-flagged'
    ];
    
    $class = $badge_classes[$status] ?? 'status-badge status-default';
    $icons = [
        'active' => 'fa-check-circle',
        'pending' => 'fa-clock',
        'sold' => 'fa-check-double',
        'expired' => 'fa-hourglass-end',
        'flagged' => 'fa-flag'
    ];
    
    $icon = $icons[$status] ?? 'fa-info-circle';
    
    return '<span class="' . $class . '"><i class="fas ' . $icon . '"></i> ' . ucfirst($status) . '</span>';
}

// Function to log user activity
function log_activity($action, $details = null) {
    global $pdo;
    
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $details_json = $details ? json_encode(['message' => $details]) : null;
    
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    return $stmt->execute([$user_id, $action, $details_json, $ip_address, $user_agent]);
}

// Function to get user role badge (STYLED)
function get_role_badge($role) {
    $role_classes = [
        'admin' => 'role-badge role-admin',
        'agent' => 'role-badge role-agent',
        'user' => 'role-badge role-user',
        'pending_agent' => 'role-badge role-pending'
    ];
    
    $class = $role_classes[$role] ?? 'role-badge role-default';
    $icons = [
        'admin' => 'fa-crown',
        'agent' => 'fa-user-tie',
        'user' => 'fa-user',
        'pending_agent' => 'fa-hourglass-half'
    ];
    
    $icon = $icons[$role] ?? 'fa-user';
    $labels = [
        'admin' => 'Administrator',
        'agent' => 'Verified Agent',
        'user' => 'Member',
        'pending_agent' => 'Pending Approval'
    ];
    
    $label = $labels[$role] ?? ucfirst($role);
    
    return '<span class="' . $class . '"><i class="fas ' . $icon . '"></i> ' . $label . '</span>';
}

// Function to send email (preserved logic)
function send_email($to, $subject, $message, $from = null) {
    // Email sending logic preserved
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . ($from ?? 'noreply@kinas-group.com') . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Function to upload image (preserved logic)
function upload_image($file, $target_dir = '../uploads/') {
    $target_file = $target_dir . time() . '_' . basename($file['name']);
    $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        return ['success' => false, 'error' => 'File is not an image'];
    }
    
    if ($file['size'] > 5000000) {
        return ['success' => false, 'error' => 'File is too large (max 5MB)'];
    }
    
    if (!in_array($image_file_type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        return ['success' => false, 'error' => 'Only JPG, JPEG, PNG, GIF & WEBP files are allowed'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['success' => true, 'path' => str_replace('../', '', $target_file)];
    }
    
    return ['success' => false, 'error' => 'Failed to upload file'];
}

// Function to get division icon (STYLED)
function get_division_icon($division) {
    $icons = [
        'automobile' => 'fa-car',
        'real-estate' => 'fa-building',
        'solar' => 'fa-solar-panel',
        'marketplace' => 'fa-store'
    ];
    
    return $icons[$division] ?? 'fa-tag';
}

// Function to get division color (STYLED)
function get_division_color($division) {
    $colors = [
        'automobile' => '#d4af37',
        'real-estate' => '#2c3e50',
        'solar' => '#f39c12',
        'marketplace' => '#27ae60'
    ];
    
    return $colors[$division] ?? '#666';
}

// Function to generate breadcrumb (STYLED)
function generate_breadcrumb() {
    $current_url = $_SERVER['REQUEST_URI'];
    $segments = explode('/', trim(parse_url($current_url, PHP_URL_PATH), '/'));
    $html = '<nav class="luxury-breadcrumb" aria-label="breadcrumb">';
    $html .= '<ol class="breadcrumb-list">';
    $html .= '<li class="breadcrumb-item"><a href="/"><i class="fas fa-home"></i> Home</a></li>';
    
    $path = '';
    foreach ($segments as $index => $segment) {
        if (empty($segment)) continue;
        
        $path .= '/' . $segment;
        $is_last = ($index == count($segments) - 1);
        $label = ucfirst(str_replace(['-', '_'], ' ', $segment));
        
        if ($is_last) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . $path . '">' . $label . '</a></li>';
        }
    }
    
    $html .= '</ol></nav>';
    return $html;
}

// Function to display flash messages (STYLED)
function display_flash_messages() {
    $html = '';
    if (isset($_SESSION['success'])) {
        $html .= '<div class="flash-message flash-success animate-slide-down">';
        $html .= '<i class="fas fa-check-circle"></i>';
        $html .= '<span>' . htmlspecialchars($_SESSION['success']) . '</span>';
        $html .= '<button class="flash-close" onclick="this.parentElement.remove()">&times;</button>';
        $html .= '</div>';
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['error'])) {
        $html .= '<div class="flash-message flash-error animate-slide-down">';
        $html .= '<i class="fas fa-exclamation-circle"></i>';
        $html .= '<span>' . htmlspecialchars($_SESSION['error']) . '</span>';
        $html .= '<button class="flash-close" onclick="this.parentElement.remove()">&times;</button>';
        $html .= '</div>';
        unset($_SESSION['error']);
    }
    
    if (isset($_SESSION['warning'])) {
        $html .= '<div class="flash-message flash-warning animate-slide-down">';
        $html .= '<i class="fas fa-exclamation-triangle"></i>';
        $html .= '<span>' . htmlspecialchars($_SESSION['warning']) . '</span>';
        $html .= '<button class="flash-close" onclick="this.parentElement.remove()">&times;</button>';
        $html .= '</div>';
        unset($_SESSION['warning']);
    }
    
    if (isset($_SESSION['info'])) {
        $html .= '<div class="flash-message flash-info animate-slide-down">';
        $html .= '<i class="fas fa-info-circle"></i>';
        $html .= '<span>' . htmlspecialchars($_SESSION['info']) . '</span>';
        $html .= '<button class="flash-close" onclick="this.parentElement.remove()">&times;</button>';
        $html .= '</div>';
        unset($_SESSION['info']);
    }
    
    return $html;
}

// Additional utility functions preserved below
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function is_agent() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'agent';
}

function is_user() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_user()) {
        $_SESSION['error'] = 'Please login to access this page';
        header('Location: /auth/login.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        $_SESSION['error'] = 'Access denied. Admin privileges required.';
        header('Location: /dashboard.php');
        exit;
    }
}

function require_agent() {
    require_login();
    if (!is_agent() && !is_admin()) {
        $_SESSION['error'] = 'Access denied. Agent privileges required.';
        header('Location: /dashboard.php');
        exit;
    }
}

// CSS styles for flash messages (to be included in header)
function get_flash_styles() {
    return '
    <style>
        /* Flash Messages - Premium Styling */
        .flash-message {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 10000;
            padding: 16px 20px;
            border-radius: 12px;
            font-family: "Inter", sans-serif;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(10px);
            max-width: 400px;
            min-width: 280px;
            animation: slideInRight 0.3s ease;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-slide-down {
            animation: slideDown 0.3s ease;
        }
        
        .flash-message.flash-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .flash-message.flash-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .flash-message.flash-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .flash-message.flash-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .flash-message i {
            font-size: 1.2rem;
        }
        
        .flash-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            margin-left: auto;
            padding: 0 5px;
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }
        
        .flash-close:hover {
            opacity: 1;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-sold {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-expired {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .status-flagged {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Role Badges */
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .role-admin {
            background: linear-gradient(135deg, #d4af37, #f4e4a1);
            color: #1a1a2e;
        }
        
        .role-agent {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .role-user {
            background: #6c757d;
            color: white;
        }
        
        .role-pending {
            background: #ffc107;
            color: #1a1a2e;
        }
        
        /* Luxury Breadcrumb */
        .luxury-breadcrumb {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid rgba(212, 175, 55, 0.2);
        }
        
        .breadcrumb-list {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .breadcrumb-item {
            font-size: 0.85rem;
        }
        
        .breadcrumb-item:not(:last-child)::after {
            content: "›";
            margin-left: 8px;
            color: #d4af37;
        }
        
        .breadcrumb-item a {
            color: #d4af37;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .breadcrumb-item a:hover {
            color: #b8941f;
            text-decoration: underline;
        }
        
        .breadcrumb-item.active {
            color: #666;
        }
        
        /* Loading Animation */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(212, 175, 55, 0.3);
            border-radius: 50%;
            border-top-color: #d4af37;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .flash-message {
                top: 70px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
            
            .luxury-breadcrumb {
                padding: 8px 15px;
            }
            
            .breadcrumb-item {
                font-size: 0.75rem;
            }
        }
    </style>
    ';
}
?>
