<?php
/**
 * KINAS GROUP — .env loader
 * Parses the root .env file into $_ENV / putenv() if the variables
 * are not already set (e.g. by the server or a framework).
 * Safe to include multiple times — skips already-set keys.
 */

(function () {
    // Walk up from /api/config/ to the project root
    $envFile = dirname(__DIR__, 2) . '/.env';

    if (!file_exists($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and lines without '='
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);

        // Strip surrounding quotes (single or double)
        if (
            strlen($val) >= 2 &&
            (($val[0] === '"' && $val[-1] === '"') ||
             ($val[0] === "'" && $val[-1] === "'"))
        ) {
            $val = substr($val, 1, -1);
        }

        // Only set if not already provided by the environment
        if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
            $_ENV[$key]   = $val;
            putenv("$key=$val");
        }
    }
})();
