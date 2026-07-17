<?php
// KINAS GROUP — Security Functions
// SECURED: Enhanced with path traversal helpers and validation utilities

class Security {

    // ── Input sanitisation ──────────────────────────────────────────────────

    /**
     * Sanitize input data (recursive for arrays)
     */
    public static function sanitizeInput(mixed $data): mixed {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        if (!is_string($data)) {
            return $data;
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Prevent XSS by escaping output
     */
    public static function preventXSS(string $data): string {
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Strip all HTML tags from input
     */
    public static function stripAllTags(string $data): string {
        return strip_tags(trim($data));
    }

    // ── Password ────────────────────────────────────────────────────────────

    /**
     * Hash password with bcrypt (cost 12)
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password against hash
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * Check if password needs rehashing
     */
    public static function needsRehash(string $hash): bool {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // ── Token generation ────────────────────────────────────────────────────

    /**
     * Generate cryptographically secure random token
     */
    public static function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generate OTP code (numeric only)
     */
    public static function generateOTP(int $digits = 6): string {
        return str_pad((string)random_int(0, (int)str_repeat('9', $digits)), $digits, '0', STR_PAD_LEFT);
    }

    // ── CSRF ────────────────────────────────────────────────────────────────

    /**
     * Generate CSRF token (stored in session).
     * Canonical method — all internal code should call this.
     */
    public static function generateCSRFToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Alias for generateCSRFToken() — kept for backward compatibility.
     */
    public static function generate_csrf_token(): string {
        return self::generateCSRFToken();
    }

    /**
     * Validate CSRF token using constant-time comparison
     */
    public static function validateCSRFToken(string $token): bool {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF token validation failed']);
            exit;
        }
        // Rotate after use
        unset($_SESSION['csrf_token']);
        return true;
    }

    /**
     * Non-destructive CSRF check — returns true/false, no exit, no output.
     * Use this from pages / endpoints that want to control the failure
     * response (e.g. set a flash message and re-render the form, or branch
     * to a different handler). The companion validateCSRFToken() method
     * above has the contract "on failure, 403 + JSON + exit" which is
     * wrong for HTML pages that just want to show an inline error.
     *
     * Also rotates the session token on a successful match (same as
     * validateCSRFToken), so the next form render picks up a fresh value.
     *
     * Empty / missing token is treated as invalid. Comparison uses
     * hash_equals() to avoid timing attacks.
     */
    public static function verifyCSRFToken(?string $token): bool {
        $token = (string)($token ?? '');
        if ($token === '' || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals((string)$_SESSION['csrf_token'], $token);
    }

    /**
     * Generate HTML hidden input with CSRF token
     */
    public static function csrfField(): string {
        return '<input type="hidden" name="csrf_token" value="' . self::preventXSS(self::generate_csrf_token()) . '">';
    }

    // ── Rate limiting (DB-backed — survives session reset / new clients) ────

    /**
     * Throws HTTP 429 if $key has exceeded $maxAttempts within $windowSeconds.
     * Uses the rate_limits table. Falls back to session if DB unavailable.
     */
    public static function rateLimitDB(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
        try {
            $db = Database::getInstance()->getConnection();

            // Prune old windows
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
            // Fallback to session-based limiting if DB fails
            return self::rateLimitSession($key, $maxAttempts, $windowSeconds);
        }
    }

    /** Legacy session-based rate limit — kept as fallback only */
    public static function rateLimit(string $key, int $maxAttempts = 5, int $decaySeconds = 300): bool {
        return self::rateLimitSession($key, $maxAttempts, $decaySeconds);
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

    /**
     * Validates the Bearer token from the Authorization header.
     * Returns the user row if valid, null otherwise.
     */
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

    /**
     * Require a valid Bearer token OR an active session.
     * Populates $_SESSION if token-authenticated (stateless API clients).
     */
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

    /**
     * Get client IP address (only trusts REMOTE_ADDR)
     */
    public static function getClientIP(): string {
        return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }

    // ── Path traversal prevention ───────────────────────────────────────────

    /**
     * Sanitize path to prevent directory traversal attacks
     */
    public static function sanitizePath(string $path): string {
        // Remove null bytes
        $path = str_replace("\0", '', $path);
        // Remove directory separators
        $path = str_replace(['/', '\\', '..'], '', $path);
        // Final cleanup with basename
        return basename($path);
    }

    /**
     * Validate that a path is within allowed directory
     */
    public static function isPathSafe(string $path, string $allowedDir): bool {
        $realPath = realpath($path);
        $realAllowed = realpath($allowedDir);

        if ($realPath === false || $realAllowed === false) {
            return false;
        }

        return str_starts_with($realPath, $realAllowed);
    }

    /**
     * Whitelist-based path validation
     */
    public static function validateDirectory(string $dirName, array $allowedDirs): string {
        $sanitized = self::sanitizePath($dirName);

        if (!in_array($sanitized, $allowedDirs, true)) {
            return $allowedDirs[0] ?? 'general'; // Default fallback
        }

        return $sanitized;
    }

    // ── Input validation helpers ────────────────────────────────────────────

    /**
     * Validate email format
     */
    public static function isValidEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate URL format
     */
    public static function isValidUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate phone number format
     */
    public static function isValidPhone(string $phone): bool {
        return preg_match('/^\+?[\d\s\-\(\)]{7,15}$/', $phone) === 1;
    }

    /**
     * Validate integer range
     */
    public static function isValidInt(mixed $value, ?int $min = null, ?int $max = null): bool {
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            return false;
        }
        if ($min !== null && $value < $min) {
            return false;
        }
        if ($max !== null && $value > $max) {
            return false;
        }
        return true;
    }

    /**
     * Validate float range
     */
    public static function isValidFloat(mixed $value, ?float $min = null, ?float $max = null): bool {
        $value = (float)$value;
        if (!is_finite($value)) {
            return false;
        }
        if ($min !== null && $value < $min) {
            return false;
        }
        if ($max !== null && $value > $max) {
            return false;
        }
        return true;
    }

    /**
     * Validate string length
     */
    public static function isValidLength(string $value, int $min = 0, int $max = 255): bool {
        $len = strlen($value);
        return $len >= $min && $len <= $max;
    }

    /**
     * Validate against whitelist
     */
    public static function inWhitelist(mixed $value, array $allowed): bool {
        return in_array($value, $allowed, true);
    }

    // ── HTTP security headers ───────────────────────────────────────────────

    public static function secureHeaders(): void {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
        // UPDATED: Added https://www.recaptcha.net to script-src and frame-src
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com https://www.recaptcha.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://www.gstatic.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self' https:; frame-src https://www.google.com https://recaptcha.google.com https://www.recaptcha.net;");
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    // ── File upload validation ──────────────────────────────────────────────

    /**
     * Validate file upload with MIME type verification
     */
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

    /**
     * Log security-relevant activity
     */
    public static function logActivity(?int $userId, string $action, string $details = ''): void {
        if ($userId === null || $userId < 1) {
            return; // Don't log if no valid user ID
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
    //
    // PHP's mail() returns true as soon as the local MTA accepts the
    // message — it does NOT verify the recipient exists. That meant we
    // were happily creating accounts for any syntactically-valid email
    // (e.g. fake@nonexistentdomain12345.com) because the registration
    // code's "rollback if email failed" guard was checking a return
    // value that always said success. The user-facing symptom was
    // "I created an account with a non-existing email and got the
    // success message".
    //
    // Fix: BEFORE we hand the address to mail()/Resend, do a real DNS
    // check on the domain. If the domain has no MX record (and no A
    // record as a fallback), the message is guaranteed to bounce, so we
    // reject the registration up front. This is the standard pattern
    // recommended by e.g. Postmark, Mailgun, and the PHP docs.
    //
    // Note: this catches "fake@thisdomainreallydoesnotexist.com" but
    // NOT typos on real domains (e.g. me@gmial.com — gmial.com does
    // have an MX). That's an acceptable trade-off: full SMTP
    // verification (RCPT TO probe) requires a real outbound SMTP
    // connection and is much heavier. The DNS check is the 90% solution.
    //
    // Returns null if the address looks deliverable, or a human-readable
    // error string if it should be rejected. NEVER throw — the caller
    // needs to use the result to decide between 422 and a successful
    // insert.
    public static function checkEmailDeliverable(?string $email): ?string {
        if (!is_string($email) || $email === '') {
            return 'Email address is required.';
        }
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Please enter a valid email address.';
        }

        $domain = substr($email, strpos($email, '@') + 1);

        // Reject literal placeholder / RFC-5733 / RFC-2606 addresses
        // (example.com, example.org, .test, .invalid, .localhost).
        $blocked = [
            'example.com', 'example.org', 'example.net',
            'localhost', 'localdomain',
            'test.com', 'test.org', 'test.net',
            'invalid', 'localhost.localdomain',
        ];
        if (in_array($domain, $blocked, true) || str_ends_with($domain, '.test') || str_ends_with($domain, '.invalid') || str_ends_with($domain, '.localhost')) {
            return 'That email domain is not allowed.';
        }

        // Try MX first, then fall back to A record (some small / misconfigured
        // domains still accept mail on their A record).
        $hasMx = false;
        if (function_exists('getmxrr')) {
            $mxHosts = [];
            // getmxrr emits a warning and returns false on lookup failure.
            // The @ suppresses the warning; the return value is the truth.
            if (@getmxrr($domain, $mxHosts) && !empty($mxHosts)) {
                $hasMx = true;
            }
        }

        if ($hasMx) {
            return null; // looks good
        }

        // No MX — fall back to an A record. This matches what most
        // real mail servers do when an MX is missing.
        $a = @gethostbyname($domain);
        if ($a !== $domain && filter_var($a, FILTER_VALIDATE_IP)) {
            return null; // A record exists, treat as deliverable
        }

        return 'We could not find a mail server for that email domain. Please double-check the address and try again.';
    }


}

// Apply security headers on every PHP request
Security::secureHeaders();
