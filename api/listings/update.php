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

    // Build dynamic UPDATE based on type
    $updates = [];
    $params = [];

    // UPDATED: Added all new automobile fields to the textFields array
    $textFields = [
        'title', 'description', 'city', 'state', 'country', 'address', 
        'color', 'body_type', 'drivetrain', 'fuel_type', 'transmission', 
        'condition_status', 'vin', 'make', 'model', 'mileage', 'brand', 
        'capacity', 'system_type', 'service_type', 'category_id', 
        'company_name', 'agency', 'license', 'experience', 'website', 
        'linkedin', 'twitter', 'instagram', 'bio', 'first_name', 'last_name', 
        'specialties', 'property_type', 'listing_type', 'view_type',
        // NEW AUTOMOBILE FIELDS:
        'engine', 'gearbox', 'car_type', 'drive', 'drive_train', 
        'interior_color', 'seats', 'features'
    ];
    foreach ($textFields as $f) {
        if (array_key_exists($f, $data)) {
            $val = is_string($data[$f]) ? trim($data[$f]) : $data[$f];
            $updates[] = "`$f` = ?";
            $params[] = $val;
        }
    }

    if (array_key_exists('price', $data) && $data['price'] !== '') {
        $updates[] = "price = ?";
        $params[] = (float)$data['price'];
    }
    if (array_key_exists('year', $data) && $data['year'] !== '') {
        $updates[] = "year = ?";
        $params[] = (int)$data['year'];
    }
    if (array_key_exists('doors', $data) && $data['doors'] !== '') {
        $updates[] = "doors = ?";
        $params[] = (int)$data['doors'];
    }
    if (array_key_exists('beds', $data) && $data['beds'] !== '') {
        $updates[] = "beds = ?";
        $params[] = (int)$data['beds'];
    }
    if (array_key_exists('baths', $data) && $data['baths'] !== '') {
        $updates[] = "baths = ?";
        $params[] = (int)$data['baths'];
    }
    if (array_key_exists('sqft', $data) && $data['sqft'] !== '') {
        $updates[] = "sqft = ?";
        $params[] = (int)$data['sqft'];
    }
    if (array_key_exists('capacity_kw', $data) && $data['capacity_kw'] !== '') {
        $updates[] = "capacity_kw = ?";
        $params[] = (float)$data['capacity_kw'];
    }
    
    // ── Status handling ─────────────────────────────────────────────────
    // Only allow status changes if the agent is verified (approved)
    // Verified agents can set status to 'active' or 'inactive'
    // Unverified agents cannot change status
    if (array_key_exists('status', $data) && $data['status'] !== '') {
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
        
        // Only allow status changes if verified
        if ($agentVerified) {
            $updates[] = "status = ?";
            $params[] = $data['status'];
        }
    }
    
    if (array_key_exists('featured', $data)) {
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
            // Non-fatal — proceed
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
        echo json_encode(['error' => 'Failed to update listing']);
    } else {
        $_SESSION['flash_error'] = 'Failed to update listing. Please try again.';
        header('Location: ' . $redirectAfter);
        exit;
    }
}
?>
