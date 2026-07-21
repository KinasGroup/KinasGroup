<?php
/**
 * Authentication System for KINAS GROUP Platform
 * All existing PHP logic preserved - only styling added for error messages
 */

session_start();

// Regenerate session ID to prevent fixation
if (!isset($_SESSION['initialized'])) {
    session_regenerate_id(true);
    $_SESSION['initialized'] = true;
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
}

// Function to require login
function require_login() {
    if (!is_logged_in()) {
        $_SESSION['error'] = '<i class="fas fa-lock"></i> Please login to access this page.';
        header('Location: /auth/login.php');
        exit;
    }
}

// Function to require logout (redirect if logged in)
function require_logout() {
    if (is_logged_in()) {
        header('Location: /dashboard.php');
        exit;
    }
}

// Function to require admin role
function require_admin() {
    require_login();
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        $_SESSION['error'] = '<i class="fas fa-shield-alt"></i> Access denied. Administrator privileges required.';
        header('Location: /dashboard.php');
        exit;
    }
}

// Function to require agent role
function require_agent() {
    require_login();
    if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'agent' && $_SESSION['user_role'] !== 'admin')) {
        $_SESSION['error'] = '<i class="fas fa-user-tie"></i> Access denied. Agent privileges required.';
        header('Location: /dashboard.php');
        exit;
    }
}

// Function to verify user password
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Function to hash password
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
}

// Function to attempt login with rate limiting (STYLED error messages)
function attempt_login($email, $password, $pdo) {
    // Check rate limiting
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    $attempts = $stmt->fetch()['attempts'];
    
    if ($attempts >= 5) {
        return ['success' => false, 'error' => '<i class="fas fa-hourglass-half"></i> Too many login attempts. Please try again in 15 minutes.'];
    }
    
    // Get user by email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Log failed attempt
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, email, success) VALUES (?, ?, 0)");
        $stmt->execute([$ip, $email]);
        return ['success' => false, 'error' => '<i class="fas fa-envelope"></i> Invalid email or password.'];
    }
    
    if (!verify_password($password, $user['password'])) {
        // Log failed attempt
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, email, success) VALUES (?, ?, 0)");
        $stmt->execute([$ip, $email]);
        return ['success' => false, 'error' => '<i class="fas fa-key"></i> Invalid email or password.'];
    }
    
    if ($user['status'] === 'suspended') {
        return ['success' => false, 'error' => '<i class="fas fa-ban"></i> Your account has been suspended. Please contact support.'];
    }
    
    // Log successful login
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, email, success) VALUES (?, ?, 1)");
    $stmt->execute([$ip, $email]);
    
    // Clear old attempts
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute();
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Log activity
    log_activity('login', "User {$user['email']} logged in successfully");
    
    return ['success' => true, 'user' => $user];
}

// Function to logout
function logout() {
    if (isset($_SESSION['user_id'])) {
        log_activity('logout', "User logged out");
    }
    
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

// Function to register new user (STYLED validation messages)
function register_user($name, $email, $password, $phone, $pdo) {
    // Validate inputs with premium error styling
    if (empty($name)) {
        return ['success' => false, 'error' => '<i class="fas fa-user"></i> Full name is required.'];
    }
    
    if (!validate_email($email)) {
        return ['success' => false, 'error' => '<i class="fas fa-envelope"></i> Please enter a valid email address.'];
    }
    
    if (strlen($password) < 8) {
        return ['success' => false, 'error' => '<i class="fas fa-lock"></i> Password must be at least 8 characters long.'];
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return ['success' => false, 'error' => '<i class="fas fa-lock"></i> Password must contain at least one uppercase letter.'];
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return ['success' => false, 'error' => '<i class="fas fa-lock"></i> Password must contain at least one lowercase letter.'];
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return ['success' => false, 'error' => '<i class="fas fa-lock"></i> Password must contain at least one number.'];
    }
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => '<i class="fas fa-envelope"></i> Email already registered. <a href="/auth/login.php">Login instead?</a>'];
    }
    
    // Create user
    $hashed_password = hash_password($password);
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, phone, role, created_at) 
        VALUES (?, ?, ?, ?, 'user', NOW())
    ");
    
    if ($stmt->execute([$name, $email, $hashed_password, $phone])) {
        $user_id = $pdo->lastInsertId();
        
        // Send welcome email
        $subject = "Welcome to KINAS GROUP";
        $message = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #d4af37;'>Welcome to KINAS GROUP! 🎉</h2>
                <p>Dear {$name},</p>
                <p>Thank you for joining KINAS GROUP - your premier luxury marketplace platform.</p>
                <p>Start exploring our premium listings and connect with verified agents today!</p>
                <a href='https://kinas-group.com' style='display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #d4af37, #f4e4a1); color: #1a1a2e; text-decoration: none; border-radius: 8px; margin-top: 20px;'>Explore Now</a>
                <hr style='margin: 30px 0;'>
                <p style='color: #666; font-size: 12px;'>KINAS GROUP OF COMPANY LIMITED</p>
            </div>
        ";
        
        send_email($email, $subject, $message);
        
        return ['success' => true, 'user_id' => $user_id];
    }
    
    return ['success' => false, 'error' => '<i class="fas fa-exclamation-circle"></i> Registration failed. Please try again.'];
}

// Function to send password reset email (STYLED email template)
function send_password_reset($email, $pdo) {
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'error' => '<i class="fas fa-envelope"></i> No account found with this email address.'];
    }
    
    // Generate reset token
    $token = generate_random_string(64);
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $pdo->prepare("
        INSERT INTO password_resets (user_id, token, expires_at) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        token = ?, expires_at = ?, created_at = NOW()
    ");
    $stmt->execute([$user['id'], $token, $expires, $token, $expires]);
    
    // Send email
    $reset_link = "https://kinas-group.com/auth/reset-password.php?token=" . urlencode($token);
    $subject = "Reset Your KINAS GROUP Password";
    $message = "
        <div style='font-family: 'Inter', Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8f9fa; border-radius: 20px; overflow: hidden;'>
            <div style='background: linear-gradient(135deg, #1a1a2e, #16213e); padding: 30px; text-align: center;'>
                <h1 style='color: #d4af37; font-family: 'Prata', serif; margin: 0;'>KINAS GROUP</h1>
            </div>
            <div style='padding: 30px;'>
                <h2 style='color: #1a1a2e;'>Password Reset Request</h2>
                <p>Dear {$user['name']},</p>
                <p>We received a request to reset your password for your KINAS GROUP account.</p>
                <p>Click the button below to create a new password:</p>
                <a href='{$reset_link}' style='display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #d4af37, #f4e4a1); color: #1a1a2e; text-decoration: none; border-radius: 10px; font-weight: 600; margin: 20px 0;'>Reset Password</a>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request this, please ignore this email.</p>
                <hr style='margin: 30px 0;'>
                <p style='color: #666; font-size: 12px;'>For security, never share this link with anyone.</p>
            </div>
        </div>
    ";
    
    send_email($email, $subject, $message);
    
    return ['success' => true, 'message' => 'Password reset link sent to your email.'];
}

// Function to reset password using token
function reset_password($token, $new_password, $pdo) {
    $stmt = $pdo->prepare("
        SELECT user_id FROM password_resets 
        WHERE token = ? AND expires_at > NOW() AND used = 0
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        return ['success' => false, 'error' => '<i class="fas fa-clock"></i> Invalid or expired reset token.'];
    }
    
    if (strlen($new_password) < 8) {
        return ['success' => false, 'error' => '<i class="fas fa-lock"></i> Password must be at least 8 characters.'];
    }
    
    $hashed_password = hash_password($new_password);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed_password, $reset['user_id']]);
    
    // Mark token as used
    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
    $stmt->execute([$token]);
    
    return ['success' => true, 'message' => 'Password reset successfully. You can now login.'];
}

// Authentication styles for login/register pages
function get_auth_styles() {
    return '
    <style>
        /* Authentication Pages Premium Styling */
        .auth-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .auth-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 100%;
            overflow: hidden;
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .auth-header {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            padding: 40px;
            text-align: center;
        }
        
        .auth-header h1 {
            font-family: "Prata", serif;
            color: #d4af37;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .auth-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }
        
        .auth-body {
            padding: 40px;
        }
        
        .auth-form .form-group {
            margin-bottom: 25px;
        }
        
        .auth-form label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        .auth-form label i {
            color: #d4af37;
            margin-right: 8px;
        }
        
        .auth-form input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-family: "Inter", sans-serif;
            transition: all 0.3s ease;
        }
        
        .auth-form input:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }
        
        .auth-btn {
            width: 100%;
            background: linear-gradient(135deg, #d4af37, #f4e4a1);
            color: #1a1a2e;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .auth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }
        
        .auth-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
        }
        
        .auth-footer a {
            color: #d4af37;
            text-decoration: none;
            font-weight: 600;
        }
        
        .auth-footer a:hover {
            text-decoration: underline;
        }
    </style>
    ';
}
?>
