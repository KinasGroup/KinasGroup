<?php
/**
 * KINAS GROUP — Create a listing (any division)
 *
 * Accepts:
 *   - listing_type: 'car' | 'property' | 'solar' | 'marketplace'
 *   - The full field set for that type (only stored fields are
 *     extracted; unknown fields are ignored so old clients keep
 *     working)
 *
 * Inserts into the correct table with sanitization and a
 * parameterized query (table name is whitelisted).
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/validation.php';

// Was this submitted by a script (fetch/AJAX) expecting JSON, or by a plain
// browser <form> post? The Add Listing form is a normal multipart form post
// with no JS interception, so without this check the browser just navigated
// to this endpoint and rendered the raw JSON text as a blank-looking page.
$wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
    || stripos($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'xmlhttprequest') !== false;

if ($wantsJson) {
    header('Content-Type: application/json');
}

/**
 * Send a response appropriate to how the request was made: JSON for
 * AJAX callers, or a flash message + redirect for plain form posts.
 */
function respond(int $httpCode, bool $success, string $message, array $extra = []): void {
    global $wantsJson;
    http_response_code($httpCode);
    if ($wantsJson) {
        echo json_encode(array_merge(['success' => $success, $success ? 'message' : 'error' => $message], $extra));
    } else {
        $_SESSION[$success ? 'flash_success' : 'flash_error'] = $message;
        header('Location: ' . ($success ? '/agent/listings.php' : '/agent/add-listing.php'));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Method not allowed');
}

SessionManager::requireLogin();
SessionManager::requireVerified();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    // Allow form-encoded
    $data = $_POST;
}
if (!$data) {
    respond(400, false, 'No data received');
}

$listingType = (string)($data['listing_type'] ?? '');

$tables = [
    'car'        => 'car_listings',
    'property'   => 'property_listings',
    'solar'      => 'solar_listings',
    'marketplace' => 'marketplace_listings',
];

if (!isset($tables[$listingType])) {
    respond(400, false, 'Invalid listing type');
}
$table = $tables[$listingType];

// ── Division guard ────────────────────────────────────────────────────────────
// Admins can list anywhere. Super agents (is_super_agent=1) can list in
// any of the 4 divisions. Regular agents are LOCKED to the single division
// they chose at registration (agent_profiles.division). The form-level
// <select> is also restricted, but this server-side check is the source of
// truth — never trust the client.
require_once __DIR__ . '/../config/constants.php';

$listingTypeToDivision = [
    'car'         => DIVISION_AUTOMOBILE,    // 'kinas-automobile'
    'property'    => DIVISION_REAL_ESTATE,   // 'williams-connect-home'
    'solar'       => DIVISION_SOLAR,         // 'kinas-volt'
    'marketplace' => DIVISION_MARKETPLACE,   // 'kinas-marketplace'
];
$agentDivision = $_SESSION['user_division'] ?? null;
$isSuperAgent  = !empty($_SESSION['is_super_agent']);

if (SessionManager::getUserRole() !== 'admin' && !$isSuperAgent) {
    if (!$agentDivision || $agentDivision !== $listingTypeToDivision[$listingType]) {
        respond(403, false, 'You can only create listings in your assigned division (' .
            ($agentDivision ? $agentDivision : 'none assigned') . ').');
    }
}

$title = Security::sanitizeInput((string)($data['title'] ?? ''));
$price = (float)($data['price'] ?? 0);
$description = Security::sanitizeInput((string)($data['description'] ?? ''));

if ($title === '' || mb_strlen($title) < 3) {
    respond(422, false, 'Title is required (min 3 characters)');
}
if ($price <= 0) {
    respond(422, false, 'Price must be greater than zero');
}
if ($price > 999999999999) {
    respond(422, false, 'Price exceeds maximum allowed value');
}

$agentId = (int)$_SESSION['user_id'];
$s = function (string $k, int $max = 255) use ($data) {
    return Security::sanitizeInput(mb_substr((string)($data[$k] ?? ''), 0, $max));
};
$int = function (string $k) use ($data): ?int {
    $v = $data[$k] ?? null;
    if ($v === '' || $v === null) return null;
    return (int)$v;
};
$num = function (string $k) use ($data): ?float {
    $v = $data[$k] ?? null;
    if ($v === '' || $v === null) return null;
    return (float)$v;
};

try {
    $db = Database::getInstance()->getConnection();

    if ($listingType === 'car') {
        $stmt = $db->prepare("
            INSERT INTO car_listings
                (agent_id, title, brand, model, year, price, mileage,
                 fuel_type, transmission, color, condition_status,
                 body_type, drivetrain, doors,
                 description, features, vin,
                 city, state, country, status, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pending', NOW(), NOW())
        ");
        $stmt->execute([
            $agentId,
            $title,
            $s('brand', 100) ?: 'Other',
            $s('model', 100) ?: 'Other',
            $int('year') ?? date('Y'),
            $price,
            $int('mileage'),
            $s('fuel_type', 50),
            $s('transmission', 50),
            $s('color', 50),
            $s('condition_status', 50) ?: $s('condition', 50),
            $s('body_type', 50),
            $s('drivetrain', 50),
            $int('doors'),
            $description,
            !empty($data['features']) ? json_encode($data['features']) : null,
            $s('vin', 50),
            $s('city', 100),
            $s('state', 100),
            $s('country', 100) ?: 'Nigeria',
        ]);
    } elseif ($listingType === 'property') {
        $stmt = $db->prepare("
            INSERT INTO property_listings
                (agent_id, title, property_type, listing_type, price,
                 beds, baths, sqft, lot_size, year_built,
                 address, city, state, zip_code, country,
                 latitude, longitude, description, features, amenities, view_type, hoa_fees,
                 status, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pending', NOW(), NOW())
        ");
        $stmt->execute([
            $agentId,
            $title,
            $s('property_type', 100) ?: 'Residential',
            in_array(($data['listing_type_purpose'] ?? ''), ['sale','rent'], true) ? $data['listing_type_purpose'] : 'sale',
            $price,
            $int('beds') ?? $int('bedrooms'),
            $int('baths') ?? $int('bathrooms'),
            $int('sqft') ?? $int('area'),
            $num('lot_size'),
            $int('year_built'),
            $s('address', 500),
            $s('city', 100),
            $s('state', 100),
            $s('zip_code', 20),
            $s('country', 100) ?: 'Nigeria',
            $num('latitude'),
            $num('longitude'),
            $description,
            !empty($data['features']) ? json_encode($data['features']) : null,
            !empty($data['amenities']) ? json_encode($data['amenities']) : null,
            $s('view_type', 100),
            $num('hoa_fees'),
        ]);
    } elseif ($listingType === 'solar') {
        $stmt = $db->prepare("
            INSERT INTO solar_listings
                (agent_id, title, service_type, price, brand, capacity_kw,
                 warranty_years, description, features,
                 city, state, country, status, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'pending', NOW())
        ");
        $stmt->execute([
            $agentId,
            $title,
            in_array(($data['service_type'] ?? ''), ['residential','commercial','industrial','maintenance','financing'], true)
                ? $data['service_type']
                : ($s('solar_type', 50) ? strtolower($s('solar_type', 50)) : 'residential'),
            $price,
            $s('brand', 100),
            $num('capacity_kw') ?? $num('capacity'),
            $int('warranty_years'),
            $description,
            !empty($data['features']) ? json_encode($data['features']) : null,
            $s('city', 100),
            $s('state', 100),
            $s('country', 100) ?: 'Nigeria',
        ]);
    } else { // marketplace
        $categoryId = $int('category_id') ?? $int('category');
        $stmt = $db->prepare("
            INSERT INTO marketplace_listings
                (agent_id, title, category_id, price, description,
                 condition_status, brand,
                 city, state, country, status, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?, 'pending', NOW(), NOW())
        ");
        $stmt->execute([
            $agentId,
            $title,
            $categoryId,
            $price,
            $description,
            $s('condition_status', 50) ?: $s('condition', 50),
            $s('brand', 100),
            $s('city', 100),
            $s('state', 100),
            $s('country', 100) ?: 'Nigeria',
        ]);
    }

    $listingId = (int)$db->lastInsertId();

    // ── Image uploads ───────────────────────────────────────────────────
    // The form posts files as images[] (multipart). Previously these were
    // silently ignored — listings published with zero photos. Mirrors the
    // proven-working upload logic in api/listings/update.php so the stored
    // `url` is a real public path (/uploads/listings/{type}/{id}/{file}),
    // matching what api/listings/delete-image.php expects.
    if (!empty($_FILES['images']) && is_array($_FILES['images']['name'] ?? null) && !empty($_FILES['images']['name'][0])) {
        try {
            $uploadDir = __DIR__ . '/../../uploads/listings/' . $listingType . '/' . $listingId . '/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $imageCount = count($_FILES['images']['name']);
            $imgStmt = $db->prepare(
                "INSERT INTO listing_images (listing_id, listing_type, url, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())"
            );

            for ($i = 0; $i < $imageCount; $i++) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $tmpName = $_FILES['images']['tmp_name'][$i];
                $origName = basename($_FILES['images']['name'][$i]);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) continue;
                if ($_FILES['images']['size'][$i] > 10 * 1024 * 1024) continue;

                $newName = uniqid('img_', true) . '.' . $ext;
                $target = $uploadDir . $newName;
                if (!@move_uploaded_file($tmpName, $target)) continue;

                $publicUrl = '/uploads/listings/' . $listingType . '/' . $listingId . '/' . $newName;
                $imgStmt->execute([$listingId, $listingType, $publicUrl, $i]);
            }
        } catch (\Throwable $e) {
            // Don't fail listing creation just because image processing
            // had a problem — the listing itself was saved successfully.
            error_log('Listing image upload error: ' . $e->getMessage());
        }
    }

    if (!empty($data['featured'])) {
        $col = $tables[$listingType];
        $db->prepare("UPDATE $col SET featured = 1 WHERE id = ?")->execute([$listingId]);
    }

    Security::logActivity($agentId, 'listing_created', "Created $listingType listing #$listingId");

    respond(201, true, 'Listing submitted for review.', ['id' => $listingId]);
} catch (\PDOException $e) {
    error_log('Listing creation error: ' . $e->getMessage());
    respond(500, false, 'Failed to create listing. Please try again.');
}
