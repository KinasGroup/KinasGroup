<?php
/**
 * tests/login_regression.php
 * -----------------------------------------------------------------------------
 * Regression test for the Admin / Agent / Buyer login flows.
 *
 * Verifies the following scenarios for each of the three roles:
 *
 *   1. GET /<role>/login.php  →  200, body contains "Sign In"
 *   2. POST /api/auth/login.php with wrong password → 401 + "Invalid credentials"
 *   3. POST /api/auth/login.php with correct creds → 200 + JSON success
 *   4. After login, GET /<role>/dashboard.php with the same cookie jar → 200
 *      and the title is the dashboard title (not a 500 error page)
 *   5. Refresh /<role>/dashboard.php → still 200, still authenticated
 *   6. GET /  (homepage) with the same cookies → body shows "Sign Out" and
 *      the role-specific dashboard link, NOT "Sign In"
 *
 * Exit codes:
 *   0  all checks passed
 *   1  one or more checks failed
 *   2  environment is not usable (e.g. PHP not found, no DB)
 *
 * Usage (from the project root):
 *
 *     # 1. Start the dev server (PHP built-in) — leave running
 *     php -S 127.0.0.1:8080 -t . >/tmp/kinas-server.log 2>&1 &
 *
 *     # 2. Run the test
 *     php tests/login_regression.php
 *     # or with a custom base URL:
 *     php tests/login_regression.php http://127.0.0.1:8080
 *     # or with a custom credentials file:
 *     php tests/login_regression.php http://127.0.0.1:8080 tests/creds.json
 *
 *     tests/creds.json (optional — defaults match seed-accounts.php):
 *     {
 *       "admin": { "email": "admin@kinas-group.com",   "password": "Admin@Kinas2025!" },
 *       "agent": { "email": "listing@kinas-group.com", "password": "Agent@Kinas2025!" },
 *       "buyer": { "email": "buyer@test.local",        "password": "BuyerPass!2025" }
 *     }
 *
 * The test uses CURLOPT_COOKIEJAR / COOKIEFILE so the same session is reused
 * across requests — this is the exact path that broke before (admin/agent
 * login appeared to "succeed" server-side but the cookie was dropped, so the
 * dashboard returned a 500 / redirect loop).
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This test must be run from the command line.\n");
    exit(2);
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "PHP cURL extension is required. Run: apt install php-curl\n");
    exit(2);
}

// ── Config ──────────────────────────────────────────────────────────────────

$BASE_URL = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$CRED_FILE = $argv[2] ?? null;

$DEFAULT_CREDS = [
    'admin' => ['email' => 'admin@kinas-group.com',   'password' => 'Admin@Kinas2025!'],
    'agent' => ['email' => 'listing@kinas-group.com', 'password' => 'Agent@Kinas2025!'],
    'buyer' => ['email' => 'buyer@test.local',        'password' => 'BuyerPass!2025'],
];

$creds = $DEFAULT_CREDS;
if ($CRED_FILE && is_file($CRED_FILE)) {
    $loaded = json_decode((string)file_get_contents($CRED_FILE), true);
    if (is_array($loaded)) {
        $creds = array_merge($DEFAULT_CREDS, $loaded);
    }
}

$ROLES = [
    'admin' => [
        'login_path'   => '/admin/login.php',
        'dashboard'    => '/admin/dashboard.php',
        'title_match'  => 'Admin',                    // any case-insensitive substring
        'home_link'    => 'Admin Panel',              // shown on homepage when logged in as admin
    ],
    'agent' => [
        'login_path'   => '/agent/login.php',
        'dashboard'    => '/agent/dashboard.php',
        'title_match'  => 'Agent',
        'home_link'    => 'Agent Dashboard',
    ],
    'buyer' => [
        'login_path'   => '/auth/login.php',
        'dashboard'    => '/user/dashboard.php',
        'title_match'  => 'Dashboard',                // user/dashboard.php title
        'home_link'    => 'Dashboard',                // generic "Dashboard" link on homepage
    ],
];

// ── Tiny test runner ────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;
$failures = [];

function ok(string $label, bool $cond, string $detail = ''): void {
    global $passed, $failed, $failures;
    if ($cond) {
        $passed++;
        echo "  \033[32m✓\033[0m  $label\n";
    } else {
        $failed++;
        $failures[] = $label . ($detail ? "  ($detail)" : '');
        echo "  \033[31m✗\033[0m  $label" . ($detail ? "  \033[2m$detail\033[0m" : '') . "\n";
    }
}

function header(string $title): void {
    echo "\n\033[1m── $title ──\033[0m\n";
}

// ── HTTP helpers ────────────────────────────────────────────────────────────

/**
 * Returns [body, status, headers] for a request, with cookies persisted
 * across calls into a per-test cookie jar.
 */
function http(string $method, string $url, array $opts = [], ?string $jar = null): array {
    $ch = curl_init($url);
    $body = $opts['body'] ?? null;
    $headers = [];
    $defaultHeaders = [
        'Accept: text/html,application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,         // we want to see the 302 ourselves
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $defaultHeaders,
    ]);

    if ($body !== null) {
        if (is_array($body) && ($opts['json'] ?? true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            $defaultHeaders[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $defaultHeaders);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
    }

    if ($jar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR,  $jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    }

    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $respHeaders = substr((string)$raw, 0, $hdrSize);
    $respBody    = substr((string)$raw, $hdrSize);
    curl_close($ch);

    return [$respBody, $status, $respHeaders];
}

/** Extract a cookie value (e.g. PHPSESSID) from a raw response header block. */
function extractCookie(string $headerBlock, string $name): ?string {
    if (preg_match('/^Set-Cookie:\s*' . preg_quote($name, '/') . '=([^;]+)/mi', $headerBlock, $m)) {
        return trim($m[1]);
    }
    return null;
}

/** Extract the CSRF token from a login page HTML. */
function extractCsrf(string $html): ?string {
    if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/value=["\']([^"\']+)["\']\s+name=["\']csrf_token["\']/i', $html, $m)) {
        return $m[1];
    }
    return null;
}

// ── Scenarios ───────────────────────────────────────────────────────────────

function runRole(string $role, array $conf, array $cred, string $base): void {
    $jar = sys_get_temp_dir() . "/kinas_test_{$role}_" . posix_getpid() . ".cookies";
    @unlink($jar);

    header("Role: $role");

    // 1) Login page is reachable
    [$html, $status] = http('GET', $base . $conf['login_path'], [], $jar);
    ok("GET {$conf['login_path']} → 200", $status === 200, "got $status");
    ok("  body contains the login form (email field)", $status === 200 && stripos($html, 'name="email"') !== false);
    $csrf = extractCsrf($html);
    ok("  CSRF token present in login form", $csrf !== null);

    // 2) Wrong password is rejected with 401 + "Invalid credentials"
    if ($csrf !== null) {
        [$badBody, $badStatus] = http('POST', $base . '/api/auth/login.php', [
            'body' => [
                'email'        => $cred['email'],
                'password'     => 'definitely-wrong-' . bin2hex(random_bytes(4)),
                'csrf_token'   => $csrf,
                'captcha_token'=> '',
            ],
        ], $jar);
        ok("Wrong password → 401", $badStatus === 401, "got $badStatus");
        ok("  body says 'Invalid credentials'", stripos($badBody, 'Invalid credentials') !== false,
            "body: " . substr($badBody, 0, 120));
    }

    // Reset cookies for the real login attempt
    @unlink($jar);
    [$html, ] = http('GET', $base . $conf['login_path'], [], $jar);
    $csrf = extractCsrf($html);

    // 3) Correct creds succeed
    [$loginBody, $loginStatus] = http('POST', $base . '/api/auth/login.php', [
        'body' => [
            'email'         => $cred['email'],
            'password'      => $cred['password'],
            'csrf_token'    => $csrf,
            'captcha_token' => '',
        ],
    ], $jar);
    ok("Correct login → 200", $loginStatus === 200, "got $loginStatus");
    ok("  body has success=true", stripos($loginBody, '"success":true') !== false,
        "body: " . substr($loginBody, 0, 200));

    // 4) Dashboard loads with the cookie jar intact
    [$dashHtml, $dashStatus] = http('GET', $base . $conf['dashboard'], [], $jar);
    ok("GET {$conf['dashboard']} → 200", $dashStatus === 200, "got $dashStatus");
    ok("  dashboard title looks like the real page (contains '{$conf['title_match']}')",
        $dashStatus === 200 && stripos($dashHtml, $conf['title_match']) !== false,
        "title snippet: " . substr(strip_tags((string)preg_match('/<title>(.*?)<\/title>/is', $dashHtml, $m) ? $m[1] : ''), 0, 80));
    ok("  no PHP fatal-error banner", $dashStatus === 200 && stripos($dashHtml, 'Fatal error') === false);
    ok("  no 500 text in the body", $dashStatus === 200 && stripos($dashHtml, 'Internal Server Error') === false);

    // 5) Refresh keeps the session
    [$refreshHtml, $refreshStatus] = http('GET', $base . $conf['dashboard'], [], $jar);
    ok("Refresh dashboard → 200", $refreshStatus === 200, "got $refreshStatus");
    ok("  still authenticated (title still matches)", $refreshStatus === 200 && stripos($refreshHtml, $conf['title_match']) !== false);

    // 6) Homepage reflects logged-in state
    [$homeHtml, $homeStatus] = http('GET', $base . '/', [], $jar);
    ok("GET / → 200", $homeStatus === 200, "got $homeStatus");
    ok("  homepage shows 'Sign Out'", $homeStatus === 200 && stripos($homeHtml, 'Sign Out') !== false,
        "home status: $homeStatus, has Sign Out: " . (stripos($homeHtml, 'Sign Out') !== false ? 'yes' : 'no'));
    ok("  homepage does NOT show 'Sign In' button", stripos($homeHtml, '>Sign In<') === false && stripos($homeHtml, 'Sign In</a>') === false);
    ok("  homepage shows role-specific link ('{$conf['home_link']}')",
        stripos($homeHtml, $conf['home_link']) !== false);

    @unlink($jar);
}

function runLoggedOutHomepage(string $base): void {
    header('Role: anonymous (logged out)');
    $jar = sys_get_temp_dir() . '/kinas_test_anon_' . posix_getpid() . '.cookies';
    @unlink($jar);

    [$html, $status] = http('GET', $base . '/', [], $jar);
    ok("GET / (logged out) → 200", $status === 200, "got $status");
    ok("  shows 'Sign In'", stripos($html, 'Sign In') !== false);
    ok("  does NOT show 'Sign Out'", stripos($html, 'Sign Out') === false);

    @unlink($jar);
}

function runLoginAsAdminEndsOnAdminDashboard(string $base): void {
    // The original bug: an admin logs in, gets redirected, but the cookie
    // is dropped along the way and the dashboard 500s.  We already cover
    // this in runRole('admin', ...).  This is a placeholder for any extra
    // role-mismatch assertions (e.g. an agent trying to log in via the
    // admin form should be rejected, not silently dropped to a 500).
    header('Role: role-mismatch guards');
    $jar = sys_get_temp_dir() . '/kinas_test_mismatch_' . posix_getpid() . '.cookies';
    @unlink($jar);

    // Anonymous agent tries the admin form — the page should not 500.
    [$html, $status] = http('GET', $base . '/admin/login.php', [], $jar);
    ok("Anon can reach /admin/login.php without 500", $status === 200, "got $status");

    @unlink($jar);
}

// ── Run ─────────────────────────────────────────────────────────────────────

header('Smoke: server reachable');
[$_, $ping] = http('GET', $BASE_URL . '/', [], null);
ok("Server at $BASE_URL is reachable", $ping > 0, "got $ping");
if ($ping === 0 || $ping >= 500) {
    fwrite(STDERR, "\n\033[31mServer is not responding. Start it with:\n" .
        "  php -S 127.0.0.1:8080 -t . >/tmp/kinas-server.log 2>&1 &\033[0m\n\n");
    exit(2);
}

runLoggedOutHomepage($BASE_URL);
runLoginAsAdminEndsOnAdminDashboard($BASE_URL);
runRole('admin', $ROLES['admin'], $creds['admin'], $BASE_URL);
runRole('agent', $ROLES['agent'], $creds['agent'], $BASE_URL);
runRole('buyer', $ROLES['buyer'], $creds['buyer'], $BASE_URL);

// ── Summary ─────────────────────────────────────────────────────────────────

echo "\n\033[1m── Result ──\033[0m\n";
echo "  passed: \033[32m$passed\033[0m\n";
echo "  failed: " . ($failed === 0 ? "\033[32m0\033[0m" : "\033[31m$failed\033[0m") . "\n";

if ($failed > 0) {
    echo "\n\033[31mFailures:\033[0m\n";
    foreach ($failures as $f) {
        echo "  • $f\n";
    }
    exit(1);
}

echo "\n\033[32mAll login regressions passed.\033[0m\n";
exit(0);
