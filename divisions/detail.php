<?php
/**
 * KINAS BUILD: 2026.08.15.10
 * FILE: divisions/detail.php
 *
 * ORIGINAL PURPOSE:
 * Unified/generic detail page for any listing across all divisions.
 *
 * OPTIMIZED PURPOSE:
 * Legacy listing URL resolver and canonical redirector.
 *
 * This file now redirects old/generic detail URLs to the correct
 * dedicated division detail page:
 *
 *   car         -> /divisions/kinas-automobile/detail.php
 *   property    -> /divisions/williams-connect-home/detail.php
 *   solar       -> /divisions/kinas-volt/detail.php
 *   marketplace -> /divisions/kinas-marketplace/detail.php
 *
 * Why:
 * - Dedicated pages contain the correct division features.
 * - Avoids duplicate content for SEO.
 * - Preserves old links.
 * - Prevents users from landing on a weaker generic page.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../api/config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$division = isset($_GET['division'])
    ? strtolower(trim((string)$_GET['division']))
    : '';

/**
 * Canonical division targets.
 */
$targets = [
    'car' => [
        'table'  => 'car_listings',
        'folder' => 'kinas-automobile',
    ],
    'property' => [
        'table'  => 'property_listings',
        'folder' => 'williams-connect-home',
    ],
    'solar' => [
        'table'  => 'solar_listings',
        'folder' => 'kinas-volt',
    ],
    'marketplace' => [
        'table'  => 'marketplace_listings',
        'folder' => 'kinas-marketplace',
    ],
];

/**
 * Accept common aliases so old links do not break.
 */
$aliases = [
    // Automobile
    'car'                  => 'car',
    'cars'                 => 'car',
    'automobile'           => 'car',
    'automobiles'          => 'car',
    'kinas-automobile'     => 'car',
    'kinas_automobile'     => 'car',
    'kinasauto'            => 'car',

    // Property
    'property'             => 'property',
    'properties'           => 'property',
    'real-estate'          => 'property',
    'real_estate'          => 'property',
    'realestate'           => 'property',
    'williams-connect-home'=> 'property',
    'williams_connect_home'=> 'property',
    'williamsconnecthome'  => 'property',

    // Solar
    'solar'                => 'solar',
    'volt'                 => 'solar',
    'kinas-volt'           => 'solar',
    'kinas_volt'           => 'solar',
    'kinasvolt'            => 'solar',

    // Marketplace
    'marketplace'          => 'marketplace',
    'market'               => 'marketplace',
    'store'                => 'marketplace',
    'shop'                 => 'marketplace',
    'kinas-marketplace'    => 'marketplace',
    'kinas_marketplace'    => 'marketplace',
    'kinasstore'           => 'marketplace',
];

if ($division !== '') {
    $division = $aliases[$division] ?? '';
}

/**
 * Permanently redirect to the dedicated division detail page.
 */
function kinas_generic_detail_redirect(int $id, string $folder): void
{
    $location = '/divisions/' . $folder . '/detail.php?id=' . $id;

    header('Location: ' . $location, true, 301);
    exit;
}

/**
 * Show 404 when the listing cannot be resolved.
 */
function kinas_generic_detail_not_found(): void
{
    http_response_code(404);

    $notFoundPage = __DIR__ . '/../pages/404.php';

    if (file_exists($notFoundPage)) {
        include $notFoundPage;
    } else {
        echo '<h1>404</h1><p>Listing not found.</p>';
    }

    exit;
}

/**
 * Check whether a listing exists in a table.
 */
function kinas_generic_detail_exists(PDO $db, string $table, int $id): bool
{
    $stmt = $db->prepare("SELECT id FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);

    return (bool)$stmt->fetchColumn();
}

// Invalid listing ID.
if ($id <= 0) {
    kinas_generic_detail_not_found();
}

// Try to get DB connection.
try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    // If the database is unavailable but the division is known,
    // still redirect to the dedicated page and allow that page
    // to handle the error/404 gracefully.
    if ($division !== '' && isset($targets[$division])) {
        kinas_generic_detail_redirect($id, $targets[$division]['folder']);
    }

    kinas_generic_detail_not_found();
}

// If a valid division was provided, try that division first.
if ($division !== '' && isset($targets[$division])) {
    $target = $targets[$division];

    try {
        if (kinas_generic_detail_exists($db, $target['table'], $id)) {
            kinas_generic_detail_redirect($id, $target['folder']);
        }
    } catch (Throwable $e) {
        // If table check fails but division is known, redirect anyway.
        kinas_generic_detail_redirect($id, $target['folder']);
    }
}

// If division was missing, invalid, or the listing was not found in the
// provided division, search all listing tables by ID.
foreach ($targets as $target) {
    try {
        if (kinas_generic_detail_exists($db, $target['table'], $id)) {
            kinas_generic_detail_redirect($id, $target['folder']);
        }
    } catch (Throwable $e) {
        // Continue checking other tables.
    }
}

// Listing could not be resolved.
kinas_generic_detail_not_found();
