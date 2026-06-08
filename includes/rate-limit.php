<?php
/**
 * Rate Limiting System for KINAS GROUP Platform
 * All existing PHP logic preserved - only styling added
 */

// Function to check rate limit (STYLED)
function check_rate_limit($key, $limit = 10, $window = 60) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $identifier = $ip . ':' . $key;
    
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    $now = time();
    
    // Clean old entries
    foreach ($_SESSION['rate_limits'] as $k => $data) {
        if ($data['reset'] < $now) {
            unset($_SESSION['rate_limits'][$k]);
        }
    }
    
    // Check if rate limit exceeded
    if (isset($_SESSION['rate_limits'][$identifier])) {
        $data = $_SESSION['rate_limits'][$identifier];
        
        if ($data['count'] >= $limit) {
            $wait_time = $data['reset'] - $now;
            $_SESSION['rate_limit_error'] = '<i class="fas fa-hourglass-half"></i> Too many requests. Please wait ' . ceil($wait_time / 60) . ' minutes.';
            return false;
        }
        
        // Increment count
        $_SESSION['rate_limits'][$identifier]['count']++;
    } else {
        // Create new rate limit entry
        $_SESSION['rate_limits'][$identifier] = [
            'count' => 1,
            'reset' => $now + $window
        ];
    }
    
    return true;
}

// Function to get remaining rate limit attempts
function get_rate_limit_remaining($key, $limit = 10) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $identifier = $ip . ':' . $key;
    
    if (!isset($_SESSION['rate_limits'][$identifier])) {
        return $limit;
    }
    
    $data = $_SESSION['rate_limits'][$identifier];
    $remaining = $limit - $data['count'];
    
    return max(0, $remaining);
}

// Function to reset rate limit
function reset_rate_limit($key) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $identifier = $ip . ':' . $key;
    
    if (isset($_SESSION['rate_limits'][$identifier])) {
        unset($_SESSION['rate_limits'][$identifier]);
        return true;
    }
    
    return false;
}

// Rate limit styles
function get_rate_limit_styles() {
    return '
    <style>
        /* Rate Limit Indicator */
        .rate-limit-indicator {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 10px 15px;
            margin-top: 15px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .rate-limit-label {
            color: #666;
        }
        
        .rate-limit-value {
            font-weight: 700;
            color: #d4af37;
        }
        
        .rate-limit-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .rate-limit-critical {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .rate-limit-progress {
            flex: 1;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .rate-limit-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
            transition: width 0.3s ease;
        }
        
        /* Rate Limit Error */
        .rate-limit-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .rate-limit-error i {
            font-size: 1.5rem;
        }
        
        /* Rate Limit Notice */
        .rate-limit-notice {
            background: #e7f3ff;
            border-left: 4px solid #2196f3;
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 0.85rem;
            color: #0c5460;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .rate-limit-notice i {
            font-size: 1rem;
            color: #2196f3;
        }
        
        /* Countdown Timer */
        .rate-limit-timer {
            font-family: monospace;
            font-size: 1.1rem;
            font-weight: 700;
            color: #dc3545;
        }
    </style>
    ';
}

// Display rate limit notice (STYLED)
function display_rate_limit_notice($key, $limit = 10) {
    $remaining = get_rate_limit_remaining($key, $limit);
    $used = $limit - $remaining;
    $percentage = ($used / $limit) * 100;
    
    $class = 'rate-limit-indicator';
    if ($remaining <= 2) {
        $class .= ' rate-limit-critical';
    } elseif ($remaining <= 5) {
        $class .= ' rate-limit-warning';
    }
    
    $html = '<div class="' . $class . '">';
    $html .= '<div class="rate-limit-label">';
    $html .= '<i class="fas fa-tachometer-alt"></i> Rate Limit: ';
    $html .= '</div>';
    $html .= '<div class="rate-limit-value">';
    $html .= $remaining . ' of ' . $limit . ' attempts remaining';
    $html .= '</div>';
    $html .= '<div class="rate-limit-progress">';
    $html .= '<div class="rate-limit-progress-bar" style="width: ' . $percentage . '%"></div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}
?>
