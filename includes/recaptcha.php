<?php
/**
 * KINAS GROUP - Multi-Domain reCAPTCHA Helper
 * 
 * Supports multiple domains with separate reCAPTCHA keys.
 * In development mode (when no keys are configured), CAPTCHA is bypassed.
 */

/**
 * Get reCAPTCHA site and secret keys for the current domain
 * 
 * @return array{site: string, secret: string} Array with 'site' and 'secret' keys
 */
function getRecaptchaKeys(): array {
    // Get host without port and www
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $host = explode(':', $host)[0]; // Remove port
    $host = preg_replace('/^www\./', '', $host); // Remove www.
    
    // If no host (CLI, etc.), return default
    if (empty($host)) {
        return getDefaultRecaptchaKeys();
    }
    
    // Log the host for debugging
    error_log("reCAPTCHA: Detected host: " . $host);
    
    // Domain-specific key mapping
    $domainKeys = [
        'kinasvolt.com' => [
            'site' => $_ENV['CAPTCHA_SITE_KEY_KINASVOLT'] ?? null,
            'secret' => $_ENV['CAPTCHA_SECRET_KEY_KINASVOLT'] ?? null,
        ],
        'kinasauto.com' => [
            'site' => $_ENV['CAPTCHA_SITE_KEY_KINASAUTO'] ?? null,
            'secret' => $_ENV['CAPTCHA_SECRET_KEY_KINASAUTO'] ?? null,
        ],
        'kinasstore.com' => [
            'site' => $_ENV['CAPTCHA_SITE_KEY_KINASSTORE'] ?? null,
            'secret' => $_ENV['CAPTCHA_SECRET_KEY_KINASSTORE'] ?? null,
        ],
        'williamsconnecthome.com' => [
            'site' => $_ENV['CAPTCHA_SITE_KEY_WILLIAMS'] ?? null,
            'secret' => $_ENV['CAPTCHA_SECRET_KEY_WILLIAMS'] ?? null,
        ],
    ];
    
    // Try exact match first
    if (isset($domainKeys[$host])) {
        $keys = $domainKeys[$host];
        error_log("reCAPTCHA: Exact match found for: " . $host);
        // If both keys are set, return them
        if (!empty($keys['site']) && !empty($keys['secret'])) {
            return $keys;
        }
    }
    
    // Try matching by base domain (supports subdomains)
    foreach ($domainKeys as $domain => $keys) {
        if (strpos($host, $domain) !== false) {
            if (!empty($keys['site']) && !empty($keys['secret'])) {
                error_log("reCAPTCHA: Base domain match found: " . $domain . " for host: " . $host);
                return $keys;
            }
        }
    }
    
    // If no domain-specific keys found, try default keys
    $defaultKeys = getDefaultRecaptchaKeys();
    if (!empty($defaultKeys['site']) && !empty($defaultKeys['secret'])) {
        error_log("reCAPTCHA: Using default keys for host: " . $host);
        return $defaultKeys;
    }
    
    // No keys configured at all - return empty (CAPTCHA will be disabled)
    error_log("reCAPTCHA: No keys configured for host: " . $host);
    return ['site' => '', 'secret' => ''];
}

/**
 * Get default reCAPTCHA keys from environment
 */
function getDefaultRecaptchaKeys(): array {
    return [
        'site' => $_ENV['CAPTCHA_SITE_KEY'] ?? getenv('CAPTCHA_SITE_KEY') ?? '',
        'secret' => $_ENV['CAPTCHA_SECRET_KEY'] ?? getenv('CAPTCHA_SECRET_KEY') ?? '',
    ];
}

/**
 * Verify a reCAPTCHA token with Google's API
 * 
 * @param string $response The reCAPTCHA token from the client
 * @return bool True if verified, false otherwise
 */
function verifyRecaptcha(string $response): bool {
    $keys = getRecaptchaKeys();
    
    // Check if secret key is configured
    if (empty($keys['secret'])) {
        error_log('reCAPTCHA secret key not configured');
        // In production, fail closed. In development, bypass.
        if (isDevelopmentEnvironment()) {
            return true; // Bypass in development only
        }
        return false;
    }
    
    // Token is required
    if (empty($response)) {
        error_log('reCAPTCHA verification failed: No token provided');
        return false;
    }
    
    // Build verification request
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = http_build_query([
        'secret' => $keys['secret'],
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => $data,
            'timeout' => 5, // Add timeout
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ];
    
    $context = stream_context_create($options);
    
    // Make the request with error handling
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        error_log('reCAPTCHA verification network error');
        return false; // Fail closed
    }
    
    $result = json_decode($result, true);
    
    // Validate response
    if (!is_array($result) || !isset($result['success'])) {
        error_log('reCAPTCHA verification: Invalid response from Google');
        return false;
    }
    
    // Check if verification succeeded
    if ($result['success'] !== true) {
        $errorCodes = $result['error-codes'] ?? [];
        error_log('reCAPTCHA verification failed: ' . implode(', ', $errorCodes));
        return false;
    }
    
    // Optional: Check the hostname matches
    if (isset($result['hostname']) && !empty($_SERVER['HTTP_HOST'])) {
        $expectedHost = strtolower($_SERVER['HTTP_HOST']);
        $expectedHost = explode(':', $expectedHost)[0];
        if ($result['hostname'] !== $expectedHost && 
            $result['hostname'] !== 'www.' . $expectedHost) {
            error_log('reCAPTCHA hostname mismatch: ' . $result['hostname'] . ' vs ' . $expectedHost);
            return false;
        }
    }
    
    return true;
}

/**
 * Check if we're in a development environment
 */
function isDevelopmentEnvironment(): bool {
    // Check for common development indicators
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (in_array($host, ['localhost', '127.0.0.1', '::1'])) {
        return true;
    }
    if (strpos($host, '.test') !== false || strpos($host, '.dev') !== false) {
        return true;
    }
    return (defined('ENVIRONMENT') && strtolower(ENVIRONMENT) === 'development');
}
