<?php
/**
 * Update a listing owned by the current agent.
 * Accepts POST (form with csrf_token + multipart for images).
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'], true)) {
    http_response_code(405);
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) { header('Content-Type: application/json'); echo json_encode(['error' => 'Method not allowed']); }
    else echo 'Method not allowed';
    exit;
}

SessionManager::requireAgent();

$contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $data = $_POST;
}

// CSRF
$token = $data['csrf_token'] ?? '';
if ($token !== '' && !Security::verifyCSRFToken($token)) {
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) { header('Content-Type: application/json'); http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token']); }
    else { $_SESSION['flash_error'] = 'Invalid security token. Please reload the page.'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/agent/listings.php')); }
    exit;
}

$listingId   = (int)($data['listing_id'] ?? $_GET['id'] ?? 0);
$listingType = $data['listing_type'] ?? 'car';

$tableMap = [
    'car'         => 'car_listings',
    'property'    => 'property_listings',
    'solar'       => 'solar_listings',
    'marketplace' => 'marketplace_listings',
];
$table = $tableMap[$listingType] ?? null;

$redirectAfter = $data['redirect'] ?? '/agent/listings.php';
if (!preg_match('#^/[a-zA-Z0-9_\-/]*(\.php)?(\?.*)?$#', $redirectAfter)) {
    $redirectAfter = '/agent/listings.php';
}

if (!$listingId || !$table) {
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) { header('Content-Type: application/json'); http_response_code(422); echo json_encode(['error' => 'Invalid listing reference']); }
    else { $_SESSION['flash_error'] = 'Invalid listing reference.'; header('Location: ' . $redirectAfter); }
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Verify ownership
    $own = $db->prepare("SELECT id, status FROM $table WHERE id = ? AND agent_id = ?");
    $own->execute([$listingId, $_SESSION['user_id']]);
    $existing = $own->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
        if ($isJson) { header('Content-Type: application/json'); http_response_code(404); echo json_encode(['error' => 'Listing not found']); }
        else { $_SESSION['flash_error'] = 'Listing not found.'; header('Location: ' . $redirectAfter); }
        exit;
    }

    // Get existing columns in the table
    $colStmt = $db->query("SHOW COLUMNS FROM $table");
    $existingColumns = [];
    while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $col['Field'];
    }

    // Build dynamic UPDATE based on type
    $updates = [];
    $params = [];

    // Define all possible text fields (will be filtered by existing columns)
    // EXCLUDING 'listing_type' which is used for sale/rent and shouldn't be updated
    $allTextFields = [
        'title', 'description', 'city', 'state', 'country', 'address', 
        'color', 'body_type', 'drivetrain', 'fuel_type', 'transmission', 
        'condition_status', 'vin', 'make', 'model', 'mileage', 'brand', 
        'capacity', 'system_type', 'service_type', 'category_id', 
        'company_name', 'agency', 'license', 'experience', 'website', 
        'linkedin', 'twitter', 'instagram', 'bio', 'first_name', 'last_name', 
        'specialties', 'property_type', 'view_type',
        // NEW AUTOMOBILE FIELDS (only update if column exists):
        'engine', 'gearbox', 'car_type', 'drive', 'drive_train', 
        'interior_color'
    ];

    // Only include text fields that exist in the table
    foreach ($allTextFields as $f) {
        if (array_key_exists($f, $data) && in_array($f, $existingColumns)) {
            $val = is_string($data[$f]) ? trim($data[$f]) : $data[$f];
            $updates[] = "`$f` = ?";
            $params[] = $val;
        }
    }

    // ── Features field (JSON) ──────────────────────────────────────────
    if (array_key_exists('features', $data) && in_array('features', $existingColumns)) {
        $featuresVal = $data['features'];
        // If it's a string, trim it
        if (is_string($featuresVal)) {
            $featuresVal = trim($featuresVal);
        }
        // If empty, set to null (MySQL JSON column accepts null)
        if (empty($featuresVal)) {
            $featuresVal = null;
        } else {
            // If it's a comma-separated string, convert to JSON array
            if (is_string($featuresVal) && !str_starts_with($featuresVal, '[')) {
                // Split by comma and trim each item
                $items = array_map('trim', explode(',', $featuresVal));
                $items = array_filter($items); // Remove empty items
                if (empty($items)) {
                    $featuresVal = null;
                } else {
                    $featuresVal = json_encode($items);
                }
            } else {
                // If it's already JSON, validate it
                $decoded = json_decode($featuresVal, true);
                if ($decoded === null && $featuresVal !== null) {
                    // Invalid JSON, treat as null
                    $featuresVal = null;
                }
            }
        }
        $updates[] = "features = ?";
        $params[] = $featuresVal;
    }

    // ── Integer fields (handle empty values as NULL) ──────────────
    if (array_key_exists('price', $data) && in_array('price', $existingColumns)) {
        $val = ($data['price'] !== '' && $data['price'] !== null) ? (float)$data['price'] : null;
        $updates[] = "price = ?";
        $params[] = $val;
    }
    if (array_key_exists('year', $data) && in_array('year', $existingColumns)) {
        $val = ($data['year'] !== '' && $data['year'] !== null) ? (int)$data['year'] : null;
        $updates[] = "year = ?";
        $params[] = $val;
    }
    if (array_key_exists('doors', $data) && in_array('doors', $existingColumns)) {
        $val = ($data['doors'] !== '' && $data['doors'] !== null) ? (int)$data['doors'] : null;
        $updates[] = "doors = ?";
        $params[] = $val;
    }
    if (array_key_exists('seats', $data) && in_array('seats', $existingColumns)) {
        $val = ($data['seats'] !== '' && $data['seats'] !== null) ? (int)$data['seats'] : null;
        $updates[] = "seats = ?";
        $params[] = $val;
    }
    if (array_key_exists('beds', $data) && in_array('beds', $existingColumns)) {
        $val = ($data['beds'] !== '' && $data['beds'] !== null) ? (int)$data['beds'] : null;
        $updates[] = "beds = ?";
        $params[] = $val;
    }
    if (array_key_exists('baths', $data) && in_array('baths', $existingColumns)) {
        $val = ($data['baths'] !== '' && $data['baths'] !== null) ? (int)$data['baths'] : null;
        $updates[] = "baths = ?";
        $params[] = $val;
    }
    if (array_key_exists('sqft', $data) && in_array('sqft', $existingColumns)) {
        $val = ($data['sqft'] !== '' && $data['sqft'] !== null) ? (int)$data['sqft'] : null;
        $updates[] = "sqft = ?";
        $params[] = $val;
    }
    if (array_key_exists('capacity_kw', $data) && in_array('capacity_kw', $existingColumns)) {
        $val = ($data['capacity_kw'] !== '' && $data['capacity_kw'] !== null) ? (float)$data['capacity_kw'] : null;
        $updates[] = "capacity_kw = ?";
        $params[] = $val;
    }
    
    // ── Status handling ─────────────────────────────────────────────────
    if (array_key_exists('status', $data) && $data['status'] !== '' && in_array('status', $existingColumns)) {
        // Check if agent is verified
        $agentVerified = false;
        try {
            $checkStmt = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
            $checkStmt->execute([$_SESSION['user_id']]);
            $verificationStatus = $checkStmt->fetchColumn();
            $agentVerified = ($verificationStatus === 'approved');
        } catch (Exception $e) {
            $agentVerified = false;
        }
        
        if ($agentVerified) {
            $updates[] = "status = ?";
            $params[] = $data['status'];
        }
    }
    
    if (array_key_exists('featured', $data) && in_array('featured', $existingColumns)) {
        $updates[] = "featured = ?";
        $params[] = !empty($data['featured']) ? 1 : 0;
    }

    if (empty($updates)) {
        $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
        if ($isJson) { header('Content-Type: application/json'); echo json_encode(['success' => true, 'message' => 'No changes to save']); }
        else { $_SESSION['flash_success'] = 'No changes to save.'; header('Location: ' . $redirectAfter); }
        exit;
    }

    $updates[] = "updated_at = NOW()";
    $params[] = $listingId;
    $params[] = $_SESSION['user_id'];

    $sql = "UPDATE $table SET " . implode(', ', $updates) . " WHERE id = ? AND agent_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // Handle new image uploads (best-effort)
    if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
        try {
            $uploadDir = __DIR__ . '/../../uploads/listings/' . $listingType . '/' . $listingId . '/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $baseSort = (int)$db->query("SELECT IFNULL(MAX(sort_order),0) FROM listing_images WHERE listing_id = $listingId AND listing_type = '$listingType'")->fetchColumn();

            $imageCount = count($_FILES['images']['name']);
            for ($i = 0; $i < $imageCount; $i++) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $tmpName = $_FILES['images']['tmp_name'][$i];
                $origName = basename($_FILES['images']['name'][$i]);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) continue;
                if ($_FILES['images']['size'][$i] > 10 * 1024 * 1024) continue;

                $newName = uniqid('img_', true) . '.' . $ext;
                $target = $uploadDir . $newName;
                if (!@move_uploaded_file($tmpName, $target)) continue;

                $publicUrl = '/uploads/listings/' . $listingType . '/' . $listingId . '/' . $newName;
                $db->prepare("INSERT INTO listing_images (listing_id, listing_type, url, sort_order) VALUES (?, ?, ?, ?)")
                   ->execute([$listingId, $listingType, $publicUrl, $baseSort + $i + 1]);
            }
        } catch (Exception $e) {
            error_log('listing image upload error: ' . $e->getMessage());
        }
    }

    Security::logActivity($_SESSION['user_id'], 'listing_updated', "Updated $listingType listing $listingId");

    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Listing updated']);
    } else {
        $_SESSION['flash_success'] = 'Listing updated successfully.';
        header('Location: ' . $redirectAfter);
        exit;
    }
} catch (Exception $e) {
    error_log('listing update error: ' . $e->getMessage());
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update listing: ' . $e->getMessage()]);
    } else {
        $_SESSION['flash_error'] = 'Failed to update listing: ' . $e->getMessage();
        header('Location: ' . $redirectAfter);
        exit;
    }
}
?>
