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

// Check if this is a form submission (multipart/form-data) or JSON
$isFormSubmit = !empty($_POST) || !empty($_FILES);

// Get data from either POST (form) or JSON (API)
if ($isFormSubmit) {
    // Form submission - use $_POST directly
    $data = $_POST;
} else {
    // JSON API request
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) {
        $data = $_POST;
    }
}

// If still no data, return error
if (empty($data)) {
    // For form submissions, redirect back with error
    if ($isFormSubmit) {
        $_SESSION['flash_error'] = 'No data received. Please fill in the form.';
        header('Location: /agent/add-listing.php');
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => 'No data received']);
    exit;
}

$listingType = (string)($data['listing_type'] ?? '');

$tables = [
    'car'        => 'car_listings',
    'property'   => 'property_listings',
    'solar'      => 'solar_listings',
    'marketplace' => 'marketplace_listings',
];

if (!isset($tables[$listingType])) {
    if ($isFormSubmit) {
        $_SESSION['flash_error'] = 'Invalid listing type.';
        header('Location: /agent/add-listing.php');
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => 'Invalid listing type']);
    exit;
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
        $errorMsg = 'You can only create listings in your assigned division (' .
            ($agentDivision ? $agentDivision : 'none assigned') . ').';
        if ($isFormSubmit) {
            $_SESSION['flash_error'] = $errorMsg;
            header('Location: /agent/add-listing.php');
            exit;
        }
        http_response_code(403);
        echo json_encode(['error' => $errorMsg]);
        exit;
    }
}

$title = Security::sanitizeInput((string)($data['title'] ?? ''));
$price = (float)($data['price'] ?? 0);
$description = Security::sanitizeInput((string)($data['description'] ?? ''));

if ($title === '' || mb_strlen($title) < 3) {
    if ($isFormSubmit) {
        $_SESSION['flash_error'] = 'Title is required (min 3 characters)';
        header('Location: /agent/add-listing.php');
        exit;
    }
    http_response_code(422);
    echo json_encode(['error' => 'Title is required (min 3 characters)']);
    exit;
}
if ($price <= 0) {
    if ($isFormSubmit) {
        $_SESSION['flash_error'] = 'Price must be greater than zero';
        header('Location: /agent/add-listing.php');
        exit;
    }
    http_response_code(422);
    echo json_encode(['error' => 'Price must be greater than zero']);
    exit;
}
if ($price > 999999999999) {
    if ($isFormSubmit) {
        $_SESSION['flash_error'] = 'Price exceeds maximum allowed value';
        header('Location: /agent/add-listing.php');
        exit;
    }
    http_response_code(422);
    echo json_encode(['error' => 'Price exceeds maximum allowed value']);
    exit;
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

// Function to extract numeric mileage from string like "19592 mi (31530 km)"
function extractMileage($value) {
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) {
        return (int)$value;
    }
    preg_match('/^(\d+)/', $value, $matches);
    if (!empty($matches[1])) {
        return (int)$matches[1];
    }
    return null;
}

try {
    $db = Database::getInstance()->getConnection();

    // ── Check if agent is verified ───────────────────────────────────────────────
    // Verified agents = verification_status = 'approved' in agent_profiles
    $agentVerified = false;
    try {
        $checkStmt = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
        $checkStmt->execute([$agentId]);
        $verificationStatus = $checkStmt->fetchColumn();
        $agentVerified = ($verificationStatus === 'approved');
    } catch (Exception $e) {
        // If table doesn't exist, default to pending
        $agentVerified = false;
    }

    // Set listing status: 'active' for verified agents, 'pending' for unverified
    $listingStatus = $agentVerified ? 'active' : 'pending';

    if ($listingType === 'car') {
        // Extract mileage as integer if possible
        $mileageRaw = $s('mileage', 100);
        $mileageValue = extractMileage($mileageRaw);
        
        $stmt = $db->prepare("
            INSERT INTO car_listings
                (agent_id, title, brand, model, year, price, mileage,
                 fuel_type, transmission, color, condition_status,
                 body_type, drivetrain, doors,
                 engine, gearbox, car_type, drive, drive_train, vin,
                 interior_color, seats, features, country,
                 description, city, state, status, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $agentId,
            $title,
            $s('brand', 100) ?: 'Other',
            $s('model', 100) ?: 'Other',
            $int('year') ?? date('Y'),
            $price,
            $mileageValue,
            $s('fuel_type', 50),
            $s('transmission', 50) ?: $s('gearbox', 50),
            $s('color', 50),
            $s('condition_status', 50) ?: $s('condition', 50),
            $s('body_type', 50) ?: $s('car_type', 50),
            $s('drivetrain', 50) ?: $s('drive_train', 50),
            $int('doors'),
            $s('engine', 100),
            $s('gearbox', 50),
            $s('car_type', 50),
            $s('drive', 10),
            $s('drive_train', 50),
            $s('vin', 50),
            $s('interior_color', 50),
            $int('seats'),
            !empty($data['features']) ? $s('features', 1000) : null,
            $s('country', 100) ?: 'Nigeria',
            $description,
            $s('city', 100),
            $s('state', 100),
            $listingStatus, // 'active' for verified, 'pending' for unverified
        ]);
    } elseif ($listingType === 'property') {
        $stmt = $db->prepare("
            INSERT INTO property_listings
                (agent_id, title, property_type, listing_type, price,
                 beds, baths, sqft, lot_size, year_built,
                 address, city, state, zip_code, country,
                 latitude, longitude, description, features, amenities, view_type, hoa_fees,
                 status, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?, NOW(), NOW())
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
            $listingStatus, // 'active' for verified, 'pending' for unverified
        ]);
    } elseif ($listingType === 'solar') {
        $stmt = $db->prepare("
            INSERT INTO solar_listings
                (agent_id, title, service_type, price, brand, capacity_kw,
                 warranty_years, description, features,
                 city, state, country, status, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?, ?, NOW())
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
            $listingStatus, // 'active' for verified, 'pending' for unverified
        ]);
    } else { // marketplace
        $categoryId = $int('category_id') ?? $int('category');
        $stmt = $db->prepare("
            INSERT INTO marketplace_listings
                (agent_id, title, category_id, price, description,
                 condition_status, brand,
                 city, state, country, status, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?, ?, NOW(), NOW())
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
            $listingStatus, // 'active' for verified, 'pending' for unverified
        ]);
    }

    $listingId = (int)$db->lastInsertId();

    // ── Image uploads ───────────────────────────────────────────────────
    $imagesAttempted = 0;
    $imagesSaved = 0;
    if (!empty($_FILES['images']) && is_array($_FILES['images']['name'] ?? null) && !empty($_FILES['images']['name'][0])) {
        try {
            $uploadDir = __DIR__ . '/../../uploads/listings/' . $listingType . '/' . $listingId . '/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $imageCount = count($_FILES['images']['name']);
            $imagesAttempted = $imageCount;
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
                $imagesSaved++;
            }
        } catch (\Throwable $e) {
            error_log('Listing image upload error: ' . $e->getMessage());
        }
    }

    if (!empty($data['featured'])) {
        $db->prepare("UPDATE $table SET featured = 1 WHERE id = ?")->execute([$listingId]);
    }

    Security::logActivity($agentId, 'listing_created', "Created $listingType listing #$listingId");

    $message = $agentVerified ? 'Listing published successfully!' : 'Listing submitted for review.';
    if ($imagesAttempted > 0 && $imagesSaved < $imagesAttempted) {
        $message .= $imagesSaved === 0
            ? ' Note: none of your photos could be saved — please try re-uploading them from Edit Listing.'
            : " Note: only {$imagesSaved} of {$imagesAttempted} photos were saved — you can add the rest from Edit Listing.";
    }

    // Redirect to listings page with success message
    $_SESSION['flash_success'] = $message;
    header('Location: /agent/listings.php');
    exit;

} catch (\PDOException $e) {
    error_log('Listing creation error: ' . $e->getMessage());
    
    // Log the actual error for debugging
    error_log('SQL Error: ' . $e->getMessage());
    error_log('SQL State: ' . $e->getCode());
    
    if ($isFormSubmit) {
        $_SESSION['flash_error'] = 'Failed to create listing: ' . $e->getMessage();
        header('Location: /agent/add-listing.php');
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create listing: ' . $e->getMessage()]);
    exit;
}
?>
