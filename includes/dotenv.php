<?php
/**
 * Simple .env file loader
 * Loads environment variables from .env file into $_ENV and getenv()
 */

function loadEnv($path = null) {
    if ($path === null) {
        $path = __DIR__ . '/.env';
    }

    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                $value = $matches[1];
            }

            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    return true;
}

// Auto-load .env file from project root (one level above /includes/)
loadEnv(__DIR__ . '/../.env');
?>