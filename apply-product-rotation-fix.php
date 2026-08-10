<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$apply = isset($_GET['apply']) && $_GET['apply'] === '1';

if (PHP_SAPI === 'cli') {
    $apply = true;
}

$root = __DIR__;
$backupDir = $root . '/backups/product-rotation-fix-' . date('Ymd-His');

function kpf_header(): void
{
    echo '<html><body style="font-family:Arial,sans-serif;background:#0f0f0f;color:#fff;padding:30px;">';
    echo '<h1 style="color:#C6A43F;">KINAS Product Rotation Fix</h1>';
}

function kpf_footer(): void
{
    echo '</body></html>';
}

function kpf_message(string $message, string $type = 'info'): void
{
    $colors = [
        'info' => '#9ad0ff',
        'success' => '#7ee081',
        'warning' => '#ffd166',
        'error' => '#ff7b7b',
    ];

    $color = $colors[$type] ?? '#fff';

    echo '<div style="padding:10px 14px;margin:8px 0;border:1px solid ' . htmlspecialchars($color) . ';border-left:6px solid ' . htmlspecialchars($color) . ';background:#161616;">';
    echo htmlspecialchars($message);
    echo '</div>';
}

function kpf_backup_file(string $file): void
{
    global $root, $backupDir;

    if (!file_exists($file)) {
        return;
    }

    $relative = ltrim(str_replace($root, '', $file), '/');
    $destination = $backupDir . '/' . $relative;

    if (!is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0775, true);
    }

    if (!copy($file, $destination)) {
        throw new RuntimeException('Could not backup ' . $relative);
    }
}

function kpf_write_file(string $file, string $content): void
{
    global $root;

    if (!file_exists($file)) {
        throw new RuntimeException('File not found: ' . $file);
    }

    if (!is_writable($file)) {
        $relative = ltrim(str_replace($root, '', $file), '/');
        throw new RuntimeException($relative . ' is not writable. Check file permissions.');
    }

    kpf_backup_file($file);

    if (file_put_contents($file, $content) === false) {
        $relative = ltrim(str_replace($root, '', $file), '/');
        throw new RuntimeException('Could not write ' . $relative . '.');
    }
}

function kpf_add_require(string &$content, string $needle, string $require, string $label): bool
{
    if (strpos($content, $require) !== false) {
        return false;
    }

    $pos = strpos($content, $needle);

    if ($pos === false) {
        throw new RuntimeException('Could not find insertion point for ' . $label . '.');
    }

    $content = substr($content, 0, $pos + strlen($needle))
        . PHP_EOL . PHP_EOL
        . $require
        . substr($content, $pos + strlen($needle));

    return true;
}

function kpf_replace_once(string $pattern, string $replacement, string $subject): ?string
{
    if (!preg_match($pattern, $subject)) {
        return null;
    }

    return preg_replace_callback(
        $pattern,
        function () use ($replacement) {
            return $replacement;
        },
        $subject,
        1
    );
}

function kpf_patch_standard_division(string $file, string $label, string $var, string $functionName): void
{
    if (!file_exists($file)) {
        throw new RuntimeException($label . ' index file was not found.');
    }

    $content = file_get_contents($file);

    if ($content === false) {
        throw new RuntimeException('Could not read ' . $label . ' index file.');
    }

    $changed = false;

    $require = "require_once '../../includes/kinas-rotation.php';";

    if (kpf_add_require(
        $content,
        '$db = Database::getInstance()->getConnection();',
        $require,
        $label . ' rotation include'
    )) {
        $changed = true;
    }

    if (strpos($content, $functionName) === false) {
        $pattern = '/\$' . $var . '\s*=\s*\$db->query\(".*?LIMIT\s+\d+\s*"\)\s*->fetchAll\(\);/s';

        $replacement = "// ============================================================\n"
            . "// PRODUCT ROTATION / FAIR VISIBILITY\n"
            . "// ============================================================\n"
            . '$' . $var . ' = ' . $functionName . '($db, 12);';

        $newContent = kpf_replace_once($pattern, $replacement, $content);

        if ($newContent === null) {
            throw new RuntimeException('Could not find the listing query in ' . $label . '.');
        }

        $content = $newContent;

        $content = str_replace(
            'array_slice($' . $var . ', 0, 9)',
            'array_slice($' . $var . ', 0, 12)',
            $content
        );

        $changed = true;
    }

    if ($changed) {
        kpf_write_file($file, $content);
        kpf_message($label . ' updated with product rotation.', 'success');
    } else {
        kpf_message($label . ' already appears to have product rotation.', 'info');
    }
}

function kpf_patch_williams(string $file): void
{
    $label = 'Williams Connect Home';

    if (!file_exists($file)) {
        throw new RuntimeException($label . ' index file was not found.');
    }

    $content = file_get_contents($file);

    if ($content === false) {
        throw new RuntimeException('Could not read ' . $label . ' index file.');
    }

    $changed = false;

    $require = "require_once '../../includes/kinas-rotation.php';";

    if (kpf_add_require(
        $content,
        '$db = Database::getInstance()->getConnection();',
        $require,
        $label . ' rotation include'
    )) {
        $changed = true;
    }

    if (strpos($content, 'kinas_get_rotated_properties') === false) {
        $pattern = '/\$(\w+)\s*=\s*\$db->query\(".*?FROM property_listings.*?"\)\s*->fetchAll\(\);/is';

        if (!preg_match($pattern, $content, $matches)) {
            throw new RuntimeException('Could not find the Williams Connect Home property query. Send that file if you need a precise manual patch.');
        }

        $williamsVariable = $matches[1];

        $replacement = "// ============================================================\n"
            . "// PRODUCT ROTATION / FAIR VISIBILITY\n"
            . "// ============================================================\n"
            . '$' . $williamsVariable . ' = kinas_get_rotated_properties($db, 12);';

        $newContent = kpf_replace_once($pattern, $replacement, $content);

        if ($newContent === null) {
            throw new RuntimeException('Could not replace the Williams Connect Home property query.');
        }

        $content = $newContent;

        $content = preg_replace(
            '/array_slice\(\s*\$' . preg_quote($williamsVariable, '/') . '\s*,\s*0\s*,\s*9\s*\)/',
            'array_slice($' . $williamsVariable . ', 0, 12)',
            $content
        );

        $changed = true;
    }

    if ($changed) {
        kpf_write_file($file, $content);
        kpf_message($label . ' updated with product rotation.', 'success');
    } else {
        kpf_message($label . ' already appears to have product rotation.', 'info');
    }
}

if (!$apply) {
    kpf_header();
    kpf_message('This script will patch the homepage and all division pages to enable product rotation.', 'info');
    kpf_message('Make sure includes/kinas-rotation.php has already been uploaded.', 'warning');
    kpf_message('Then open: /apply-product-rotation-fix.php?apply=1', 'success');
    kpf_footer();
    exit;
}

kpf_header();

try {
    $rotationFile = $root . '/includes/kinas-rotation.php';

    if (!file_exists($rotationFile)) {
        throw new RuntimeException('includes/kinas-rotation.php was not found. Upload that file first.');
    }

    // ============================================================
    // PATCH HOMEPAGE
    // ============================================================

    $homepageFile = $root . '/index.php';

    if (!file_exists($homepageFile)) {
        throw new RuntimeException('index.php was not found in root.');
    }

    $homepageContent = file_get_contents($homepageFile);

    if ($homepageContent === false) {
        throw new RuntimeException('Could not read index.php.');
    }

    $homepageChanged = false;

    $homepageRequire = "require_once 'includes/kinas-rotation.php';";

    if (kpf_add_require(
        $homepageContent,
