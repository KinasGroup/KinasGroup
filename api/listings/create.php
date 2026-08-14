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
require_once '../../includes/file-upload.php';
require_once '../../includes/video-compress.php';

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

    // ── Duplicate-submission guard ───────────────────────────────────────────────
    // Client-side JS disables the submit button, but that can still be beaten
    // by things outside its control (a slow/retried network request, the
    // browser re-POSTing on back-navigation, etc.). If this exact agent
    // already created a listing with the same title+price in this table in
    // the last 60 seconds, treat this request as a duplicate of that one
    // instead of inserting a second identical row.
    $dupStmt = $db->prepare(
        "SELECT id FROM $table
         WHERE agent_id = ? AND title = ? AND price = ?
           AND created_at >= (NOW() - INTERVAL 60 SECOND)
         ORDER BY id DESC LIMIT 1"
    );
    $dupStmt->execute([$agentId, $title, $price]);
    $existingId = $dupStmt->fetchColumn();
    if ($existingId) {
        $_SESSION['flash_success'] = $agentVerified ? 'Listing published successfully!' : 'Listing submitted for review.';
        header('Location: /agent/listings.php');
        exit;
    }

    if ($listingType === 'car') {
        // Extract mileage as integer if possible
        $mileageRaw = $s('mileage', 100);
        $mileageValue = extractMileage($mileageRaw);

        // Rental is restricted to verified registered businesses (company
        // name on file AND KYB approved) — re-checked here server-side
        // regardless of what the form submitted, since agent/add-listing.php
        // only hides the option in the UI, which isn't enforcement.
        $requestedCarType = ($data['car_listing_type'] ?? 'sale') === 'rental' ? 'rental' : 'sale';
        if ($requestedCarType === 'rental') {
            $bizCheck = $db->prepare("SELECT company_name, kyb_status FROM agent_profiles WHERE user_id = ?");
            $bizCheck->execute([$agentId]);
            $bizRow = $bizCheck->fetch(PDO::FETCH_ASSOC) ?: [];
            $isVerifiedBusiness = trim((string)($bizRow['company_name'] ?? '')) !== '' && ($bizRow['kyb_status'] ?? '') === 'approved';
            if (!$isVerifiedBusiness) {
                $requestedCarType = 'sale'; // silently fall back rather than reject the whole listing
                error_log("Rejected rental listing_type from non-business/non-KYB agent_id=$agentId — created as 'sale' instead");
            }
        }

        $stmt = $db->prepare("
            INSERT INTO car_listings
                (agent_id, title, brand, model, year, price, mileage,
                 fuel_type, transmission, color, condition_status,
                 body_type, drivetrain, doors,
                 engine, gearbox, car_type, drive, drive_train, vin,
                 interior_color, seats, features, country,
                 description, city, state, listing_type, inspection_fee, status, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?, ?, NOW(), NOW())
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
            $requestedCarType,
            $num('inspection_fee'),
            $listingStatus, // 'active' for verified, 'pending' for unverified
        ]);
    } elseif ($listingType === 'property') {
        $stmt = $db->prepare("
            INSERT INTO property_listings
                (agent_id, title, property_type, listing_type, price,
                 beds, baths, sqft, lot_size, year_built,
                 address, city, state, zip_code, country,
                 latitude, longitude, description, features, amenities, view_type, hoa_fees,
                 inspection_fee, status, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?, ?, NOW(), NOW())
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
            $num('inspection_fee'),
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

    // ── FIXED: Image uploads ───────────────────────────────────────────────────
    // IMPORTANT: This now goes through the FileUpload class (includes/file-upload.php)
    // instead of writing directly to local disk via move_uploaded_file(). The old
    // code saved files to <project>/uploads/listings/... on the server's local
    // filesystem. On Railway (and most modern hosts) the filesystem is EPHEMERAL —
    // it gets rebuilt from git on every deploy, so any file written there at
    // runtime is silently wiped the next time you push code. That's why listing
    // photos kept "breaking"/disappearing after every code change: the images
    // were never actually persisted anywhere durable.
    //
    // FileUpload automatically uses Cloudflare R2 (persistent, survives deploys)
    // when R2_* env vars are configured, and only falls back to local disk if
    // R2 isn't set up. Make sure R2_BUCKET / R2_ACCOUNT_ID / R2_ACCESS_KEY_ID /
    // R2_SECRET_ACCESS_KEY / R2_PUBLIC_URL are set in your environment so this
    // fallback is never actually used in production.
    $imagesAttempted = 0;
    $imagesSaved = 0;
    $imageErrors = [];

    // Check if any images were uploaded
    if (!empty($_FILES['images']['name'][0]) && $_FILES['images']['name'][0] !== '') {
        $subDirMap = [
            'car'         => 'cars',
            'property'    => 'properties',
            'solar'       => 'products',
            'marketplace' => 'products',
        ];
        $subDir = $subDirMap[$listingType] ?? 'general';
        $uploader = new FileUpload($subDir);

        $imageCount = count($_FILES['images']['name']);
        $imagesAttempted = $imageCount;

        $imgStmt = $db->prepare(
            "INSERT INTO listing_images (listing_id, listing_type, url, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())"
        );

        $sortOrder = 0;
        for ($i = 0; $i < $imageCount; $i++) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $fileArr = [
                'name'     => $_FILES['images']['name'][$i],
                'type'     => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error'    => $_FILES['images']['error'][$i],
                'size'     => $_FILES['images']['size'][$i],
            ];

            $result = $uploader->upload($fileArr, [
                'prefix'    => "listing_{$listingId}_",
                'maxWidth'  => 1920,
                'maxHeight' => 1080,
                'quality'   => 85,
            ]);

            if (!$result['success']) {
                $imageErrors[] = $result['error'] ?? 'Unknown upload error';
                continue;
            }

            // R2Upload::upload() includes a 'key' in its result; the local
            // fallback (FileUpload::uploadLocal()) does not. Checking the
            // uploader's static isUsingR2() flag here was wrong: it only
            // says R2 is configured for this uploader instance, not that
            // THIS particular file actually made it to R2. If one upload in
            // a batch fell back to local storage (R2 hiccup, etc.), the
            // code would still treat its local filesystem path as a public
            // R2 URL and store that raw path in the database — an image
            // URL that can never load, so the browser just shows a tiny
            // broken-image icon instead of a photo filling the listing card.
            $publicUrl = isset($result['key'])
                ? $result['filepath']
                : '/uploads/' . $subDir . '/' . $result['filename'];

            if ($imgStmt->execute([$listingId, $listingType, $publicUrl, $sortOrder])) {
                $imagesSaved++;
                $sortOrder++;
            }
        }
    }

    // Log any image issues
    if (!empty($imageErrors)) {
        error_log('Image upload errors for listing ' . $listingId . ': ' . implode(', ', $imageErrors));
    }

    // Virtual tour (Homes only): either a pasted link, or an uploaded
    // video file. Same R2-vs-local URL handling as images above.
    if ($listingType === 'property') {
        $vtType = ($data['virtual_tour_type'] ?? 'link') === 'video' ? 'video' : 'link';
        $vtUrl = null;
        $vtThumbnail = null;
        $vtError = null;

        if ($vtType === 'link') {
            $link = trim($s('virtual_tour_url', 500) ?? '');
            if ($link !== '' && filter_var($link, FILTER_VALIDATE_URL)) {
                $vtUrl = $link;
            }
        } elseif (!empty($_FILES['virtual_tour_video']['name']) && $_FILES['virtual_tour_video']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['virtual_tour_video']['error'] === UPLOAD_ERR_OK) {
                $detectedMime = @mime_content_type($_FILES['virtual_tour_video']['tmp_name']) ?: $_FILES['virtual_tour_video']['type'];
                $compression = compressVideoIfPossible($_FILES['virtual_tour_video']['tmp_name'], $detectedMime);
                if ($compression['compressed']) {
                    error_log(sprintf(
                        'Virtual tour video compressed for listing %d: %s -> %s (%.0f%% smaller)',
                        $listingId, formatBytes($compression['original_size']), formatBytes($compression['new_size']),
                        (1 - $compression['new_size'] / max(1, $compression['original_size'])) * 100
                    ));
                }

                $videoUploader = new FileUpload(
                    'properties',
                    ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'],
                    150 * 1024 * 1024 // 150MB — a walkthrough clip is far larger than a photo
                );
                // tmp_name is always the ORIGINAL upload path — compression
                // (if it ran) already overwrote its bytes in place. Passing
                // any other path here fails FileUpload's is_uploaded_file()
                // check, which only recognizes paths PHP itself registered
                // during the actual HTTP upload — this silently dropped
                // every compressed video before this fix.
                $videoResult = $videoUploader->upload([
                    'name'     => $compression['compressed']
                        ? preg_replace('/\.[a-zA-Z0-9]+$/', '.mp4', $_FILES['virtual_tour_video']['name'])
                        : $_FILES['virtual_tour_video']['name'],
                    'type'     => $compression['compressed'] ? 'video/mp4' : $_FILES['virtual_tour_video']['type'],
                    'tmp_name' => $_FILES['virtual_tour_video']['tmp_name'],
                    'error'    => $_FILES['virtual_tour_video']['error'],
                    'size'     => $compression['compressed'] ? $compression['new_size'] : $_FILES['virtual_tour_video']['size'],
                ], ['prefix' => "listing_{$listingId}_tour_"]);

                if ($videoResult['success']) {
                    $vtUrl = isset($videoResult['key'])
                        ? $videoResult['filepath']
                        : '/uploads/properties/' . $videoResult['filename'];

                    // Poster-frame thumbnail (best-effort — see
                    // includes/video-compress.php::generateVideoThumbnail).
                    // Gives the agent dashboard something to show besides a
                    // bare text link. A miss here never fails the upload:
                    // the video itself already succeeded above.
                    $posterPath = generateVideoThumbnail($_FILES['virtual_tour_video']['tmp_name']);
                    if ($posterPath !== null) {
                        $posterUploader = new FileUpload('properties');
                        $posterResult = $posterUploader->uploadGeneratedFile(
                            $posterPath,
                            'image/jpeg',
                            ['prefix' => "listing_{$listingId}_tour_thumb_"]
                        );
                        if ($posterResult['success']) {
                            $vtThumbnail = isset($posterResult['key'])
                                ? $posterResult['filepath']
                                : '/uploads/properties/' . $posterResult['filename'];
                        }
                        @unlink($posterPath);
                    }
                } else {
                    $vtError = $videoResult['error'] ?? 'Unknown upload error';
                }
            } else {
                $vtError = 'Video upload failed (error code ' . $_FILES['virtual_tour_video']['error'] . ')';
            }
        }

        if ($vtUrl !== null) {
            $db->prepare("UPDATE property_listings SET virtual_tour_url = ?, virtual_tour_type = ?, virtual_tour_thumbnail = ? WHERE id = ?")
               ->execute([$vtUrl, $vtType, $vtThumbnail, $listingId]);
        } elseif ($vtError !== null) {
            error_log("Virtual tour upload error for listing $listingId: $vtError");
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
    if (isset($vtError) && $vtError !== null) {
        $message .= ' Note: the virtual tour video could not be saved — please try re-uploading it from Edit Listing.';
    }

    // Redirect to listings page with success message
    $_SESSION['flash_success'] = $message;
    header('Location: /agent/listings.php');
    exit;

} catch (\PDOException $e) {
    error_log('Listing creation error: ' . $e->getMessage());
    
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
