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

function kpf_replace_block(string $content, string $startMarker, string $endMarker, string $replacement): ?string
{
    $start = strpos($content, $startMarker);

    if ($start === false) {
        return null;
    }

    $end = strpos($content, $endMarker, $start);

    if ($end === false) {
        return null;
    }

    $end += strlen($endMarker);

    return substr($content, 0, $start) . $replacement . substr($content, $end);
}

function kpf_patch_division(string $file, string $label, string $var, string $functionName): void
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
        $startMarkers = [
            '$' . $var . ' = $db->query(',
            '$' . $var . '=$db->query(',
            '$' . $var . ' = $db->query (',
        ];

        $replacement = "// PRODUCT ROTATION / FAIR VISIBILITY\n"
            . '$' . $var . ' = ' . $functionName . '($db, 12);';

        $newContent = null;

        foreach ($startMarkers as $startMarker) {
            $newContent = kpf_replace_block(
                $content,
                $startMarker,
                '->fetchAll();',
                $replacement
            );

            if ($newContent !== null) {
                break;
            }
        }

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
        $possibleVars = [
            'properties',
            'homes',
            'listings',
            'items',
            'rows',
            'result',
            'data',
            'property',
            'propertyListings',
        ];

        $patched = false;

        foreach ($possibleVars as $var) {
            $startMarkers = [
                '$' . $var . ' = $db->query(',
                '$' . $var . '=$db->query(',
                '$' . $var . ' = $db->query (',
            ];

            foreach ($startMarkers as $startMarker) {
                $start = strpos($content, $startMarker);

                if ($start === false) {
                    continue;
                }

                $end = strpos($content, '->fetchAll();', $start);

                if ($end === false) {
                    continue;
                }

                $propertyTablePosition = strpos($content, 'property_listings', $start);

                if ($propertyTablePosition === false || $propertyTablePosition > $end) {
                    continue;
                }

                $end += strlen('->fetchAll();');

                $replacement = "// PRODUCT ROTATION / FAIR VISIBILITY\n"
                    . '$' . $var . ' = kinas_get_rotated_properties($db, 12);';

                $content = substr($content, 0, $start) . $replacement . substr($content, $end);

                $content = str_replace(
                    'array_slice($' . $var . ', 0, 9)',
                    'array_slice($' . $var . ', 0, 12)',
                    $content
                );

                $patched = true;
                break 2;
            }
        }

        if (!$patched) {
            throw new RuntimeException('Could not find the Williams Connect Home property query.');
        }

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
        '$db = Database::getInstance()->getConnection();',
        $homepageRequire,
        'homepage rotation include'
    )) {
        $homepageChanged = true;
    }

    if (strpos($homepageContent, 'kinas_get_home_rotated_listings') === false) {
        $homeStartMarker = '// Get featured listings from all divisions';
        $homeEndMarker = '$featuredListings = array_slice($featuredListings, 0, 8);';

        $homeReplacement = "// ============================================================\n"
            . "// PRODUCT ROTATION / FAIR VISIBILITY\n"
            . "// ============================================================\n"
            . '$featuredListings = kinas_get_home_rotated_listings($db, 12, 6);';

        $patchedHomepage = kpf_replace_block(
            $homepageContent,
            $homeStartMarker,
            $homeEndMarker,
            $homeReplacement
        );

        if ($patchedHomepage === null) {
            throw new RuntimeException('Could not find the homepage featured listings block.');
        }

        $homepageContent = $patchedHomepage;
        $homepageChanged = true;
    }

    if ($homepageChanged) {
        kpf_write_file($homepageFile, $homepageContent);
        kpf_message('Main homepage updated with product rotation.', 'success');
    } else {
        kpf_message('Main homepage already appears to have product rotation.', 'info');
    }

    // ============================================================
    // PATCH DIVISION PAGES
    // ============================================================

    kpf_patch_division(
        $root . '/divisions/kinas-automobile/index.php',
        'KINAS Automobile',
        'cars',
        'kinas_get_rotated_cars'
    );

    kpf_patch_division(
        $root . '/divisions/kinas-volt/index.php',
        'KINAS Volt',
        'systems',
        'kinas_get_rotated_solar'
    );

    kpf_patch_division(
        $root . '/divisions/kinas-marketplace/index.php',
        'KINAS Marketplace',
        'items',
        'kinas_get_rotated_marketplace'
    );

    kpf_patch_williams(
        $root . '/divisions/williams-connect-home/index.php'
    );

    kpf_message('All available files have been processed successfully.', 'success');
    kpf_message('Backups are inside: ' . $backupDir, 'info');
    kpf_message('Now refresh the homepage and division pages several times to confirm rotation.', 'info');
    kpf_message('IMPORTANT: Delete apply-product-rotation-fix.php from the server after use.', 'warning');

} catch (Throwable $e) {
    kpf_message('ERROR: ' . $e->getMessage(), 'error');
}

kpf_footer();
