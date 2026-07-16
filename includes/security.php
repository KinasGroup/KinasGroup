<?php
// KINAS GROUP — Security Functions (MINIMAL VERSION - WORKING)

class Security {

    // ── Input sanitisation ──────────────────────────────────────────────────

    public static function sanitizeInput(mixed $data): mixed {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        if (!is_string($data)) {
            return $data;
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    public static function preventXSS(string $data): string {
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function stripAllTags(string $data): string {
        return strip_tags(trim($data));
    }

    // ── Password ────────────────────────────────────────────────────────────

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // ── Token generation ────────────────────────────────────────────────────

    public static function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }

    public static function generateOTP(int $digits = 6): string {
        return str_pad((string)random_int(0, (int)str_repeat('9', $digits)), $digits, '0', STR_PAD_LEFT);
    }

    // ── CSRF ────────────────────────────────────────────────────────────────

    public static function generateCSRFToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function generate_csrf_token(): string {
        return self::generateCSRFToken();
    }

    public static function validateCSRFToken(string $token): bool {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF token validation failed']);
            exit;
        }
        unset($_SESSION['csrf_token']);
        return true;
    }

    public static function verifyCSRFToken(?string $token): bool {
        $token = (string)($token ?? '');
        if ($token === '' || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals((string)$_SESSION['csrf_token'], $token);
    }

    public static function csrfField(): string {
        return '<input type="hidden" name="csrf_token" value="' . self::preventXSS(self::generate_csrf_token()) . '">';
    }

    // ── Rate limiting ──────────────────────────────────────────────────────

    public static function rateLimitDB(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
        try {
            $db = Database::getInstance()->getConnection();
            $db->prepare("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)")
               ->execute([$windowSeconds]);

            $row = $db->prepare("SELECT attempts FROM rate_limits WHERE rate_key = ? AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)");
            $row->execute([$key, $windowSeconds]);
            $record = $row->fetch();

            if ($record) {
                if ($record['attempts'] >= $maxAttempts) {
                    http_response_code(429);
                    header('Retry-After: ' . $windowSeconds);
                    echo json_encode(['error' => 'Too many attempts. Please try again later.']);
                    exit;
                }
                $db->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE rate_key = ?")
                   ->execute([$key]);
            } else {
                $db->prepare("INSERT INTO rate_limits (rate_key, attempts, window_start) VALUES (?, 1, NOW())")
                   ->execute([$key]);
            }
            return true;
        } catch (\Exception $e) {
            return self::rateLimitSession($key, $maxAttempts, $windowSeconds);
        }
    }

    private static function rateLimitSession(string $key, int $maxAttempts, int $windowSeconds): bool {
        $attempts = $_SESSION['rate_limit'][$key] ?? ['count' => 0, 'time' => time()];
        if (time() - $attempts['time'] > $windowSeconds) {
            $attempts = ['count' => 0, 'time' => time()];
        }
        $attempts['count']++;
        $_SESSION['rate_limit'][$key] = $attempts;
        if ($attempts['count'] > $maxAttempts) {
            http_response_code(429);
            header('Retry-After: ' . $windowSeconds);
            echo json_encode(['error' => 'Too many attempts. Please try again later.']);
            exit;
        }
        return true;
    }

    // ── Bearer token validation ─────────────────────────────────────────────

    public static function validateBearerToken(): ?array {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $token = substr($header, 7);
        if (strlen($token) < 20) {
            return null;
        }
        try {
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT u.id, u.name, u.email, u.role, u.verified
                 FROM sessions s
                 JOIN users u ON s.user_id = u.id
                 WHERE s.token = ? AND s.expires_at > NOW() AND u.status = 'active'"
            );
            $stmt->execute([$token]);
            return $stmt->fetch() ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function requireAuth(): void {
        if (SessionManager::isLoggedIn()) return;
        $user = self::validateBearerToken();
        if ($user) {
            SessionManager::setUser($user);
            return;
        }
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorised']);
        exit;
    }

    // ── IP helper ───────────────────────────────────────────────────────────

    public static function getClientIP(): string {
        return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }

    // ── Path traversal prevention ───────────────────────────────────────────

    public static function sanitizePath(string $path): string {
        $path = str_replace("\0", '', $path);
        $path = str_replace(['/', '\\', '..'], '', $path);
        return basename($path);
    }

    public static function isPathSafe(string $path, string $allowedDir): bool {
        $realPath = realpath($path);
        $realAllowed = realpath($allowedDir);
        if ($realPath === false || $realAllowed === false) {
            return false;
        }
        return str_starts_with($realPath, $realAllowed);
    }

    public static function validateDirectory(string $dirName, array $allowedDirs): string {
        $sanitized = self::sanitizePath($dirName);
        if (!in_array($sanitized, $allowedDirs, true)) {
            return $allowedDirs[0] ?? 'general';
        }
        return $sanitized;
    }

    // ── Input validation helpers ────────────────────────────────────────────

    public static function isValidEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isValidUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public static function isValidPhone(string $phone): bool {
        return preg_match('/^\+?[\d\s\-\(\)]{7,15}$/', $phone) === 1;
    }

    public static function isValidInt(mixed $value, ?int $min = null, ?int $max = null): bool {
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            return false;
        }
        if ($min !== null && $value < $min) return false;
        if ($max !== null && $value > $max) return false;
        return true;
    }

    public static function isValidFloat(mixed $value, ?float $min = null, ?float $max = null): bool {
        $value = (float)$value;
        if (!is_finite($value)) return false;
        if ($min !== null && $value < $min) return false;
        if ($max !== null && $value > $max) return false;
        return true;
    }

    public static function isValidLength(string $value, int $min = 0, int $max = 255): bool {
        $len = strlen($value);
        return $len >= $min && $len <= $max;
    }

    public static function inWhitelist(mixed $value, array $allowed): bool {
        return in_array($value, $allowed, true);
    }

    // ── HTTP security headers ───────────────────────────────────────────────

    /**
     * MINIMAL SECURITY HEADERS - Doesn't break Worker proxying
     */
    public static function secureHeaders(): void {
        // Basic security headers (safe - don't break anything)
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // ============================================================
        // TEMPORARILY DISABLED CSP - This was breaking the Worker
        // Re-enable gradually once Worker is confirmed working
        // ============================================================
        // header("Content-Security-Policy: ...");
        
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    // ── File upload validation ──────────────────────────────────────────────

    public static function validateFileUpload(array $file, array $allowedTypes = ['jpg','jpeg','png','pdf'], int $maxSize = 5242880): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload failed with error code: ' . $file['error']];
        }
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes, true)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File too large'];
        }
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowedMimes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        if (!isset($allowedMimes[$extension]) || $mimeType !== $allowedMimes[$extension]) {
            return ['valid' => false, 'error' => 'Invalid file content'];
        }
        return ['valid' => true];
    }

    // ── Activity logging ────────────────────────────────────────────────────

    public static function logActivity(?int $userId, string $action, string $details = ''): void {
        if ($userId === null || $userId < 1) {
            return;
        }
        try {
            $db = Database::getInstance()->getConnection();
            $db->prepare(
                "INSERT INTO activity_logs (user_id, action, details, ip_address, created_at)
                 VALUES (?, ?, ?, ?, NOW())"
            )->execute([$userId, $action, $details, self::getClientIP()]);
        } catch (\Exception $e) {
            error_log('Activity log failed: ' . $e->getMessage());
        }
    }

    // ── Email deliverability ────────────────────────────────────────────────

    public static function checkEmailDeliverable(?string $email): ?string {
        if (!is_string($email) || $email === '') {
            return 'Email address is required.';
        }
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Please enter a valid email address.';
        }
        $domain = substr($email, strpos($email, '@') + 1);
        $blocked = [
            'example.com', 'example.org', 'example.net',
            'localhost', 'localdomain',
            'test.com', 'test.org', 'test.net',
            'invalid', 'localhost.localdomain',
        ];
        if (in_array($domain, $blocked, true) || str_ends_with($domain, '.test') || str_ends_with($domain, '.invalid') || str_ends_with($domain, '.localhost')) {
            return 'That email domain is not allowed.';
        }
        $hasMx = false;
        if (function_exists('getmxrr')) {
            $mxHosts = [];
            if (@getmxrr($domain, $mxHosts) && !empty($mxHosts)) {
                $hasMx = true;
            }
        }
        if ($hasMx) {
            return null;
        }
        $a = @gethostbyname($domain);
        if ($a !== $domain && filter_var($a, FILTER_VALIDATE_IP)) {
            return null;
        }
        return 'We could not find a mail server for that email domain. Please double-check the address and try again.';
    }
}

// Apply security headers on every PHP request
Security::secureHeaders();
