<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * KINAS GROUP — Review Detail Page Integration Installer
 *
 * This automatically inserts includes/reviews-detail.php into the
 * product detail pages before the footer include.
 *
 * Run:
 * /apply-review-detail-integration.php?apply=1
 */

$apply = isset($_GET['apply']) && $_GET['apply'] === '1';

if (PHP_SAPI === 'cli') {
    $apply = true;
}

$root = __DIR__;
$backupDir = $root . '/backups/review-detail-integration-' . date('Ymd-His');

function krd_header(): void
{
    echo '<html><body style="font-family:Arial,sans-serif;background:#0f0f0f;color:#fff;padding:30px;">';
    echo '<h1 style="color:#C6A43F;">KINAS Review Detail Page Integration</h1>';
}

function krd_footer(): void
{
    echo '</body></html>';
}

function krd_message(string $message, string $type = 'info'): void
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

function krd_backup_file(string $root, string $backupDir, string $file): void
{
    $relative = ltrim(str_replace($root, '', $file), '/');
    $destination = $backupDir . '/' . $relative;

    if (!is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0775, true);
    }

    if (!copy($file, $destination)) {
        throw new RuntimeException('Could not backup ' . $relative);
    }
}

if (!$apply) {
    krd_header();
    krd_message('This script will insert the product review section into the division detail pages.', 'info');
    krd_message('Make sure includes/reviews-detail.php has already been uploaded.', 'warning');
    krd_message('Then open: /apply-review-detail-integration.php?apply=1', 'success');
    krd_footer();
    exit;
}

krd_header();

try {
    $loaderFile = $root . '/includes/reviews-detail.php';

    if (!file_exists($loaderFile)) {
        throw new RuntimeException('includes/reviews-detail.php was not found. Upload that file first.');
    }

    $detailFiles = glob($root . '/divisions/*/detail.php') ?: [];

    $genericDetail = $root . '/divisions/detail.php';

    if (file_exists($genericDetail)) {
        $detailFiles[] = $genericDetail;
    }

    if (empty($detailFiles)) {
        throw new RuntimeException('No detail.php files were found under /divisions.');
    }

    $patched = [];
    $skipped = [];
    $errors = [];

    foreach ($detailFiles as $file) {
        $relative = ltrim(str_replace($root, '', $file), '/');

        $content = file_get_contents($file);

        if ($content === false) {
            $errors[] = $relative . ' could not be read.';
            continue;
        }

        if (strpos($content, 'reviews-detail.php') !== false) {
            $skipped[] = $relative . ' already has review integration.';
            continue;
        }

        if (!is_writable($file)) {
            $errors[] = $relative . ' is not writable. Check file permissions.';
            continue;
        }

        $dir = dirname($relative);
        $depth = ($dir === '.' || $dir === '') ? 0 : count(explode('/', $dir));
        $loaderPath = str_repeat('../', $depth) . 'includes/reviews-detail.php';

        $phpLoaderLine = "// ============================================================\n"
            . "// KINAS PRODUCT REVIEWS — DETAIL PAGE INTEGRATION\n"
            . "// ============================================================\n"
            . "require_once __DIR__ . '/{$loaderPath}';\n\n";

        $integrated = false;

        // ------------------------------------------------------------
        // Try common footer include markers first
        // ------------------------------------------------------------

        $footerMarkers = [
            "include_once '../../templates/footer.php';",
            "require_once '../../templates/footer.php';",
            "include '../../templates/footer.php';",
            "require '../../templates/footer.php';",
            'include_once "../../templates/footer.php";',
            'require_once "../../templates/footer.php";',
            'include "../../templates/footer.php";',
            'require "../../templates/footer.php";',
            "include_once __DIR__ . '/../../templates/footer.php';",
            "require_once __DIR__ . '/../../templates/footer.php';",
            "include __DIR__ . '/../../templates/footer.php';",
            "require __DIR__ . '/../../templates/footer.php';",
            "include_once __DIR__ . '/../templates/footer.php';",
            "require_once __DIR__ . '/../templates/footer.php';",
            "include __DIR__ . '/../templates/footer.php';",
            "require __DIR__ . '/../templates/footer.php';",
        ];

        foreach ($footerMarkers as $marker) {
            $pos = strpos($content, $marker);

            if ($pos !== false) {
                krd_backup_file($root, $backupDir, $file);

                $content = substr($content, 0, $pos)
                    . $phpLoaderLine
                    . substr($content, $pos);

                if (file_put_contents($file, $content) === false) {
                    $errors[] = $relative . ' could not be written after patching.';
                } else {
                    $patched[] = $relative . ' patched before footer include.';
                    $integrated = true;
                }

                break;
            }
        }

        // ------------------------------------------------------------
        // Fallback: regex footer include
        // ------------------------------------------------------------

        if (!$integrated) {
            $footerPattern = '/(?:include|require)(?:_once)?\s*(?:\(\s*)?(?:__DIR__\s*\.\s*)?[\'"][^\'"]*templates\/footer\.php[\'"]\s*\)?\s*;/';

            if (preg_match($footerPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $offset = $matches[0][1];

                krd_backup_file($root, $backupDir, $file);

                $content = substr($content, 0, $offset)
                    . $phpLoaderLine
                    . substr($content, $offset);

                if (file_put_contents($file, $content) === false) {
                    $errors[] = $relative . ' could not be written after regex patching.';
                } else {
                    $patched[] = $relative . ' patched using regex footer detection.';
                    $integrated = true;
                }
            }
        }

        // ------------------------------------------------------------
        // Final fallback: insert before </body>
        // ------------------------------------------------------------

        if (!$integrated) {
            $bodyPos = strripos($content, '</body>');

            if ($bodyPos !== false) {
                $loaderBlock = "\n<?php require_once __DIR__ . '/{$loaderPath}'; ?>\n";

                krd_backup_file($root, $backupDir, $file);

                $content = substr($content, 0, $bodyPos)
                    . $loaderBlock
                    . substr($content, $bodyPos);

                if (file_put_contents($file, $content) === false) {
                    $errors[] = $relative . ' could not be written before </body>.';
                } else {
                    $patched[] = $relative . ' patched before </body>.';
                    $integrated = true;
                }
            }
        }

        if (!$integrated) {
            $errors[] = $relative . ' could not be automatically patched. Send that file for manual integration.';
        }
    }

    foreach ($patched as $message) {
        krd_message($message, 'success');
    }

    foreach ($skipped as $message) {
        krd_message($message, 'info');
    }

    foreach ($errors as $message) {
        krd_message($message, 'error');
    }

    if (!empty($patched) || !empty($skipped)) {
        krd_message('Product review detail integration process completed.', 'success');
        krd_message('Backups are inside: ' . $backupDir, 'info');
        krd_message('Now open any product detail page and scroll to the Customer Reviews section.', 'info');
        krd_message('IMPORTANT: Delete apply-review-detail-integration.php from the server after use.', 'warning');
    } else {
        krd_message('No detail page was patched. Check the errors above.', 'error');
    }

} catch (Throwable $e) {
    krd_message('ERROR: ' . $e->getMessage(), 'error');
}

krd_footer();
