<?php
// KINAS GROUP — Session Management
// This file MUST be the single point that starts the session.
// Calling session_start() twice is a PHP warning; we guard it.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../api/config/database.php';

class SessionManager {

    public static function setUser(array $user): void {
        $_SESSION['user_id']       = (int)$user['id'];
        $_SESSION['user_name']     = $user['name'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['user_verified'] = (bool)($user['verified'] ?? false);
        $_SESSION['logged_in']     = true;
    }

    public static function isLoggedIn(): bool {
        return !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /auth/login.php');
            exit;
        }
    }

    public static function requireRole(string $role): void {
        self::requireLogin();
        if (($_SESSION['user_role'] ?? '') !== $role) {
            http_response_code(403);
            include __DIR__ . '/../pages/403.php';
            exit;
        }
    }

    public static function requireAdmin(): void {
        self::requireRole('admin');
    }

    public static function requireAgent(): void {
        // Both agent and admin can access agent pages
        self::requireLogin();
        if (!in_array($_SESSION['user_role'] ?? '', ['agent', 'admin'], true)) {
            http_response_code(403);
            include __DIR__ . '/../pages/403.php';
            exit;
        }
    }

    public static function requireVerified(): void {
        self::requireLogin();
        $role = self::getUserRole();
        $userId = self::getUserId();

        // Admins don't need verification
        if ($role === 'admin') return;

        // Personal identity (MetaMap) is required for everyone
        if (empty($_SESSION['user_verified'])) {
            header('Location: /agent/verification.php');
            exit;
        }

        // Agents additionally need business verification (admin-approved CAC)
        if ($role === 'agent' && $userId) {
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
                $stmt->execute([$userId]);
                $status = $stmt->fetchColumn();
                if ($status !== 'approved') {
                    header('Location: /agent/verification.php');
                    exit;
                }
            } catch (Throwable $e) {
                // If the table isn't migrated yet, fall back to the legacy check
            }
        }
    }

    /**
     * True when the user can create listings. Same logic as
     * requireVerified() but returns a bool instead of redirecting.
     */
    public static function canCreateListings(): bool {
        if (!self::isLoggedIn()) return false;
        $role = self::getUserRole();
        if ($role === 'admin') return true;
        if ($role !== 'agent') return false;
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT ap.verification_status, u.verified
                                  FROM agent_profiles ap
                                  JOIN users u ON u.id = ap.user_id
                                  WHERE ap.user_id = ?");
            $stmt->execute([self::getUserId()]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row && (int)$row['verified'] === 1 && $row['verification_status'] === 'approved';
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function getUserId(): ?int {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function getUserRole(): ?string {
        return $_SESSION['user_role'] ?? null;
    }

    public static function regenerateSession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function setFlash(string $key, string $message): void {
        $_SESSION['flash'][$key] = $message;
    }

    public static function getFlash(string $key): ?string {
        if (isset($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $message;
        }
        return null;
    }
}
