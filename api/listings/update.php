<?php
/**
 * Update a listing owned by the current agent.
 *
 * FIXED:
 * - Saves solar hardware fields:
 *   hardware_type, panel_watts, inverter_kva, battery_kwh
 * - Maps solar_type/service_type to lowercase valid values.
 * - Keeps legacy capacity_kw synced.
 */

require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/file-upload.php';
require_once '../../includes/video-compress.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'], true)) {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

SessionManager::requireAgent();

$contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

$data = (stripos($contentType, 'application/json') !== false)
    ? (json_decode(file_get_contents('php://input'), true) ?: [])
    : $_POST;

$token = $data['csrf_token'] ?? '';

if ($token !== '' && !Security::verifyCSRFToken($token)) {
    $_SESSION['flash_error'] = 'Please refresh the page and try again.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/agent/listings.php'));
    exit;
}

$listingId = (int)($data['listing_id'] ?? $_GET['id'] ?? 0);
$listingType = $data['listing_type'] ?? 'car';

$tableMap = [
    'car' => 'car_listings',
    'property' => 'property_listings',
    'solar' => 'solar_listings',
    'marketplace' => 'marketplace_listings',
];

$table = $tableMap[$listingType] ?? null;
$redirectAfter = $data['redirect'] ?? '/agent/listings.php';

if (!preg_match('#^/[a-zA-Z0-9_\-/]*(\.php)?(\?.*)?$#', $redirectAfter)) {
    $redirectAfter = '/agent/listings.php';
}

if (!$listingId || !$table) {
    $_SESSION['flash_error'] = 'Invalid listing reference.';
    header('Location: ' . $redirectAfter);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $own = $db->prepare("SELECT id, status FROM $table WHERE id = ? AND agent_id = ?");
    $own->execute([$listingId, $_SESSION['user_id']]);

    if (!$own->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['flash_error'] = 'Listing not found.';
        header('Location: ' . $redirectAfter);
        exit;
    }

    if ($listingType === 'property' && !empty($data['remove_virtual_tour'])) {
        $db->prepare("
            UPDATE property_listings
            SET virtual_tour_url = NULL,
                virtual_tour_type = NULL,
                virtual_tour_thumbnail = NULL
            WHERE id = ? AND agent_id = ?
        ")->execute([$listingId, $_SESSION['user_id']]);

        $_SESSION['flash_success'] = 'Virtual tour video removed.';
        header('Location: ' . $redirectAfter);
        exit;
    }

    $colStmt = $db->query("SHOW COLUMNS FROM $table");
    $existingColumns = [];

    while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $col['Field'];
    }

    $updates = [];
    $params = [];

    $allTextFields = [
        'title',
        'description',
        'city',
        'state',
        'country',
        'address',
        'color',
        'body_type',
        'drivetrain',
        'fuel_type',
        'transmission',
        'condition_status',
        'vin',
        'make',
        'model',
        'mileage',
        'brand',
        'capacity',
        'system_type',
        'category_id',
        'company_name',
        'agency',
        'license',
        'experience',
        'website',
        'linkedin',
        'twitter',
        'instagram',
        'bio',
        'first_name',
        'last_name',
        'specialties',
        'property_type',
        'view_type',
        'engine',
        'gearbox',
        'car_type',
        'drive',
        'drive_train',
        'interior_color',
        'inspection_fee',
    ];

    $numericNullableFields = [
        'mileage',
        'inspection_fee',
        'category_id',
    ];

    foreach ($allTextFields as $field) {
        if (array_key_exists($field, $data) && in_array($field, $existingColumns)) {
            $value = is_string($data[$field]) ? trim($data[$field]) : $data[$field];

            if (in_array($field, $numericNullableFields, true) && $value === '') {
                $value = null;
            }

            $updates[] = "`$field` = ?";
            $params[] = $value;
        }
    }

    // ------------------------------------------------------------
    // SOLAR: hardware type
    // ------------------------------------------------------------
    $hardwareType = null;

    if ($listingType === 'solar' && array_key_exists('hardware_type', $data) && in_array('hardware_type', $existingColumns)) {
        $allowedHardwareTypes = ['solar_panel', 'inverter', 'battery', 'power_station'];
        $postedHardwareType = strtolower(trim((string)$data['hardware_type']));

        if (in_array($postedHardwareType, $allowedHardwareTypes, true)) {
            $hardwareType = $postedHardwareType;
            $updates[] = "hardware_type = ?";
            $params[] = $hardwareType;
        }
    }

    // ------------------------------------------------------------
    // SOLAR: capacity fields
    // ------------------------------------------------------------
    $solarCapacityFields = ['panel_watts', 'inverter_kva', 'battery_kwh'];

    foreach ($solarCapacityFields as $field) {
        if (array_key_exists($field, $data) && in_array($field, $existingColumns)) {
            $value = $data[$field];

            if ($value === '' || $value === null) {
                $value = null;
            } else {
                $value = (float)str_replace(',', '', (string)$value);

                if ($value < 0) {
                    $value = null;
                }
            }

            $updates[] = "`$field` = ?";
            $params[] = $value;
        }
    }

    // ------------------------------------------------------------
    // SOLAR: service_type / solar_type mapping
    // ------------------------------------------------------------
    if ($listingType === 'solar' && in_array('service_type', $existingColumns)) {
        $allowedServiceTypes = [
            'residential',
            'commercial',
            'industrial',
            'maintenance',
            'financing',
            'solar_panel',
            'inverter',
            'battery',
            'charge_controller',
            'mounting_structure',
            'power_station',
        ];

        $rawServiceType = '';

        if (array_key_exists('service_type', $data) && trim((string)$data['service_type']) !== '') {
            $rawServiceType = strtolower(trim((string)$data['service_type']));
        } elseif (array_key_exists('solar_type', $data) && trim((string)$data['solar_type']) !== '') {
            $rawServiceType = strtolower(trim((string)$data['solar_type']));
        } elseif ($hardwareType !== null) {
            $rawServiceType = $hardwareType;
        }

        if ($rawServiceType !== '' && in_array($rawServiceType, $allowedServiceTypes, true)) {
            $updates[] = "service_type = ?";
            $params[] = $rawServiceType;
        } elseif ($hardwareType !== null) {
            $updates[] = "service_type = ?";
            $params[] = $hardwareType;
        }
    }

    // Non-solar service_type fallback, if needed.
    if ($listingType !== 'solar' && array_key_exists('service_type', $data) && in_array('service_type', $existingColumns)) {
        $updates[] = "service_type = ?";
        $params[] = trim((string)$data['service_type']);
    }

    // ------------------------------------------------------------
    // Features
    // ------------------------------------------------------------
    if (array_key_exists('features', $data) && in_array('features', $existingColumns)) {
        $featuresVal = $data['features'];

        if (is_string($featuresVal)) {
            $featuresVal = trim($featuresVal);
        }

        if (empty($featuresVal) || $featuresVal === '') {
            $featuresVal = null;
        } else {
            if (is_string($featuresVal) && !str_starts_with($featuresVal, '[')) {
                $items = array_filter(array_map('trim', explode(',', $featuresVal)));
                $featuresVal = empty($items) ? null : json_encode($items);
            } else {
                $decoded = json_decode($featuresVal, true);

                if ($decoded === null && $featuresVal !== null) {
                    $featuresVal = null;
                }
            }
        }

        $updates[] = "features = ?";
        $params[] = $featuresVal;
    }

    // ------------------------------------------------------------
    // Numeric fields
    // ------------------------------------------------------------
    foreach (['price', 'year', 'doors', 'seats', 'beds', 'baths', 'sqft'] as $numericField) {
        if (array_key_exists($numericField, $data) && in_array($numericField, $existingColumns)) {
            $value = ($data[$numericField] !== '' && $data[$numericField] !== null)
                ? (('price' === $numericField) ? (float)$data[$numericField] : (int)$data[$numericField])
                : null;

            $updates[] = "`$numericField` = ?";
            $params[] = $value;
        }
    }

    // ------------------------------------------------------------
    // SOLAR: keep legacy capacity_kw synced
    // ------------------------------------------------------------
    if ($listingType === 'solar' && in_array('capacity_kw', $existingColumns)) {
        $capacityKw = null;

        $panelWattsInput = $data['panel_watts'] ?? '';
        $inverterKvaInput = $data['inverter_kva'] ?? '';
        $batteryKwhInput = $data['battery_kwh'] ?? '';

        if ($hardwareType === 'solar_panel' && $panelWattsInput !== '' && $panelWattsInput !== null) {
            $capacityKw = round((float)str_replace(',', '', (string)$panelWattsInput) / 1000, 3);
        } elseif (($hardwareType === 'inverter' || $hardwareType === 'power_station') && $inverterKvaInput !== '' && $inverterKvaInput !== null) {
            $capacityKw = round((float)str_replace(',', '', (string)$inverterKvaInput), 3);
        } elseif ($hardwareType === 'battery' && $batteryKwhInput !== '' && $batteryKwhInput !== null) {
            $capacityKw = round((float)str_replace(',', '', (string)$batteryKwhInput), 3);
        }

        if ($capacityKw !== null) {
            $updates[] = "capacity_kw = ?";
            $params[] = $capacityKw;
        }
    }

    // ------------------------------------------------------------
    // Status
    // ------------------------------------------------------------
    if (array_key_exists('status', $data) && $data['status'] !== '' && in_array('status', $existingColumns)) {
        $agentVerified = false;

        try {
            $check = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
            $check->execute([$_SESSION['user_id']]);
            $agentVerified = ($check->fetchColumn() === 'approved');
        } catch (Exception $e) {
            // Ignore.
        }

        if ($agentVerified) {
            $updates[] = "status = ?";
            $params[] = $data['status'];
        }
    }

    // ------------------------------------------------------------
    // Featured
    // ------------------------------------------------------------
    if (array_key_exists('featured', $data) && in_array('featured', $existingColumns)) {
        $updates[] = "featured = ?";
        $params[] = !empty($data['featured']) ? 1 : 0;
    }

    if (empty($updates)) {
        $_SESSION['flash_success'] = 'No changes to save.';
        header('Location: ' . $redirectAfter);
        exit;
    }

    $updates[] = "updated_at = NOW()";
    $params[] = $listingId;
    $params[] = $_SESSION['user_id'];

    $stmt = $db->prepare("UPDATE $table SET " . implode(', ', $updates) . " WHERE id = ? AND agent_id = ?");
    $stmt->execute($params);

    // ------------------------------------------------------------
    // Images
    // ------------------------------------------------------------
    if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
        try {
            $subDirMap = [
                'car' => 'cars',
                'property' => 'properties',
                'solar' => 'products',
                'marketplace' => 'products',
            ];

            $subDir = $subDirMap[$listingType] ?? 'general';
            $uploader = new FileUpload($subDir);

            $baseSort = (int)$db->query("
                SELECT IFNULL(MAX(sort_order), 0)
                FROM listing_images
                WHERE listing_id = $listingId
                  AND listing_type = '$listingType'
            ")->fetchColumn();

            $imageCount = count($_FILES['images']['name']);

            for ($i = 0; $i < $imageCount; $i++) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $fileArr = [
                    'name' => $_FILES['images']['name'][$i],
                    'type' => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error' => $_FILES['images']['error'][$i],
                    'size' => $_FILES['images']['size'][$i],
                ];

                $result = $uploader->upload($fileArr, [
                    'prefix' => "listing_{$listingId}_",
                    'maxWidth' => 1920,
                    'maxHeight' => 1080,
                    'quality' => 85,
                ]);

                if (!$result['success']) {
                    continue;
                }

                $publicUrl = isset($result['key'])
                    ? $result['filepath']
                    : '/uploads/' . $subDir . '/' . $result['filename'];

                $db->prepare("
                    INSERT INTO listing_images (
                        listing_id,
                        listing_type,
                        url,
                        sort_order
                    ) VALUES (?, ?, ?, ?)
                ")->execute([$listingId, $listingType, $publicUrl, $baseSort + $i + 1]);
            }
        } catch (Exception $e) {
            error_log('listing image upload error: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------
    // Property virtual tour
    // ------------------------------------------------------------
    if ($listingType === 'property' && isset($data['virtual_tour_type'])) {
        $vtType = $data['virtual_tour_type'] === 'video' ? 'video' : 'link';
        $vtUrl = null;
        $vtThumbnail = null;
        $vtError = null;

        if ($vtType === 'link') {
            $link = trim($data['virtual_tour_url'] ?? '');

            if ($link !== '' && filter_var($link, FILTER_VALIDATE_URL)) {
                $vtUrl = $link;
            }
        } elseif (!empty($_FILES['virtual_tour_video']['name']) && $_FILES['virtual_tour_video']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['virtual_tour_video']['error'] === UPLOAD_ERR_OK) {
                $detectedMime = @mime_content_type($_FILES['virtual_tour_video']['tmp_name']) ?: $_FILES['virtual_tour_video']['type'];

                $compression = compressVideoIfPossible($_FILES['virtual_tour_video']['tmp_name'], $detectedMime);

                $videoUploader = new FileUpload(
                    'properties',
                    ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'],
                    150 * 1024 * 1024
                );

                $videoResult = $videoUploader->upload(
                    [
                        'name' => $compression['compressed']
                            ? preg_replace('/\.[a-zA-Z0-9]+$/', '.mp4', $_FILES['virtual_tour_video']['name'])
                            : $_FILES['virtual_tour_video']['name'],
                        'type' => $compression['compressed'] ? 'video/mp4' : $_FILES['virtual_tour_video']['type'],
                        'tmp_name' => $_FILES['virtual_tour_video']['tmp_name'],
                        'error' => $_FILES['virtual_tour_video']['error'],
                        'size' => $compression['compressed'] ? $compression['new_size'] : $_FILES['virtual_tour_video']['size'],
                    ],
                    [
                        'prefix' => "listing_{$listingId}_tour_",
                    ]
                );

                if ($videoResult['success']) {
                    $vtUrl = isset($videoResult['key'])
                        ? $videoResult['filepath']
                        : '/uploads/properties/' . $videoResult['filename'];

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
                $existing = $db->prepare("
                    SELECT virtual_tour_url, virtual_tour_thumbnail
                    FROM property_listings
                    WHERE id = ? AND virtual_tour_type = 'video'
                ");

                $existing->execute([$listingId]);
                $existingRow = $existing->fetch(PDO::FETCH_ASSOC) ?: [];

                $vtUrl = $existingRow['virtual_tour_url'] ?: null;
                $vtThumbnail = $existingRow['virtual_tour_thumbnail'] ?: null;
            }
        } else {
            $vtError = 'Video upload failed (error code ' . $_FILES['virtual_tour_video']['error'] . ')';
        }

        if ($vtError !== null) {
            error_log("Virtual tour upload error for listing $listingId: $vtError");
        }

        $db->prepare("
            UPDATE property_listings
            SET virtual_tour_url = ?,
                virtual_tour_type = ?,
                virtual_tour_thumbnail = ?
            WHERE id = ?
        ")->execute([
            $vtUrl,
            $vtUrl !== null ? $vtType : null,
            $vtUrl !== null ? $vtThumbnail : null,
            $listingId,
        ]);
    }

    // ------------------------------------------------------------
    // Car rental/sale type
    // ------------------------------------------------------------
    if ($listingType === 'car' && isset($data['car_listing_type'])) {
        $requestedCarType = $data['car_listing_type'] === 'rental' ? 'rental' : 'sale';

        if ($requestedCarType === 'rental') {
            $bizCheck = $db->prepare("
                SELECT ap.company_name, ap.kyb_status
                FROM agent_profiles ap
                JOIN car_listings c ON c.agent_id = ap.user_id
                WHERE c.id = ?
            ");

            $bizCheck->execute([$listingId]);
            $bizRow = $bizCheck->fetch(PDO::FETCH_ASSOC) ?: [];

            $hasBusiness = trim((string)($bizRow['company_name'] ?? '')) !== '';
            $kybApproved = ($bizRow['kyb_status'] ?? '') === 'approved';

            if (!($hasBusiness && $kybApproved)) {
                $requestedCarType = 'sale';
            }
        }

        $db->prepare("UPDATE car_listings SET listing_type = ? WHERE id = ?")->execute([$requestedCarType, $listingId]);
    }

    Security::logActivity($_SESSION['user_id'], 'listing_updated', "Updated $listingType listing $listingId");

    $updateMessage = 'Listing updated successfully.';

    if (isset($vtError) && $vtError !== null) {
        $updateMessage = 'Listing updated, but the virtual tour video could not be saved — please try re-uploading it.';
    }

    $_SESSION['flash_success'] = $updateMessage;
    header('Location: ' . $redirectAfter);
    exit;
} catch (Exception $e) {
    error_log('listing update error: ' . $e->getMessage());

    $_SESSION['flash_error'] = 'Failed to update listing: ' . $e->getMessage();
    header('Location: ' . $redirectAfter);
    exit;
}
