<?php
/**
* KINAS GROUP — Create a listing (any division)
*/
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/validation.php';
require_once '../../includes/file-upload.php';
require_once '../../includes/video-compress.php';
$isFormSubmit = !empty($_POST) || !empty($_FILES);
if ($isFormSubmit) {
$data = $_POST;
} else {
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) { $data = $_POST; }
}
if (empty($data)) {
if ($isFormSubmit) { $_SESSION['flash_error'] = 'No data received. Please fill in the form.'; header('Location: /agent/add-listing.php'); exit; }
http_response_code(400); echo json_encode(['error' => 'No data received']); exit;
}
$listingType = (string)($data['listing_type'] ?? '');
$tables = ['car'=>'car_listings','property'=>'property_listings','solar'=>'solar_listings','marketplace'=>'marketplace_listings'];
if (!isset($tables[$listingType])) {
if ($isFormSubmit) { $_SESSION['flash_error'] = 'Invalid listing type.'; header('Location: /agent/add-listing.php'); exit; }
http_response_code(400); echo json_encode(['error' => 'Invalid listing type']); exit;
}
$table = $tables[$listingType];
require_once __DIR__ . '/../config/constants.php';
$listingTypeToDivision = ['car'=>DIVISION_AUTOMOBILE,'property'=>DIVISION_REAL_ESTATE,'solar'=>DIVISION_SOLAR,'marketplace'=>DIVISION_MARKETPLACE];
$agentDivision = $_SESSION['user_division'] ?? null;
$isSuperAgent  = !empty($_SESSION['is_super_agent']);
if (SessionManager::getUserRole() !== 'admin' && !$isSuperAgent) {
if (!$agentDivision || $agentDivision !== $listingTypeToDivision[$listingType]) {
$errorMsg = 'You can only create listings in your assigned division (' . ($agentDivision ? $agentDivision : 'none assigned') . ').';
if ($isFormSubmit) { $_SESSION['flash_error'] = $errorMsg; header('Location: /agent/add-listing.php'); exit; }
http_response_code(403); echo json_encode(['error' => $errorMsg]); exit;
}
}
$title = Security::sanitizeInput((string)($data['title'] ?? ''));
$price = (float)($data['price'] ?? 0);
$description = Security::sanitizeInput((string)($data['description'] ?? ''));
if ($title === '' || mb_strlen($title) < 3) { if ($isFormSubmit) { $_SESSION['flash_error'] = 'Title is required (min 3 characters)'; header('Location: /agent/add-listing.php'); exit; } http_response_code(422); echo json_encode(['error' => 'Title is required (min 3 characters)']); exit; }
if ($price <= 0) { if ($isFormSubmit) { $_SESSION['flash_error'] = 'Price must be greater than zero'; header('Location: /agent/add-listing.php'); exit; } http_response_code(422); echo json_encode(['error' => 'Price must be greater than zero']); exit; }
if ($price > 999999999999) { if ($isFormSubmit) { $_SESSION['flash_error'] = 'Price exceeds maximum allowed value'; header('Location: /agent/add-listing.php'); exit; } http_response_code(422); echo json_encode(['error' => 'Price exceeds maximum allowed value']); exit; }
$agentId = (int)$_SESSION['user_id'];
$s = function (string $k, int $max = 255) use ($data) { return Security::sanitizeInput(mb_substr((string)($data[$k] ?? ''), 0, $max)); };
$int = function (string $k) use ($data): ?int { $v = $data[$k] ?? null; if ($v === '' || $v === null) return null; return (int)$v; };
$num = function (string $k) use ($data): ?float { $v = $data[$k] ?? null; if ($v === '' || $v === null) return null; return (float)$v; };
function extractMileage($value) { if ($value === null || $value === '') return null; if (is_numeric($value)) return (int)$value; preg_match('/^(\d+)/', $value, $m); return !empty($m[1]) ? (int)$m[1] : null; }
try {
$db = Database::getInstance()->getConnection();
$agentVerified = false;
try { $c = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?"); $c->execute([$agentId]); $agentVerified = ($c->fetchColumn() === 'approved'); } catch (Exception $e) { $agentVerified = false; }
$listingStatus = $agentVerified ? 'active' : 'pending';
$dupStmt = $db->prepare("SELECT id FROM $table WHERE agent_id = ? AND title = ? AND price = ? AND created_at >= (NOW() - INTERVAL 60 SECOND) ORDER BY id DESC LIMIT 1");
$dupStmt->execute([$agentId, $title, $price]);
if ($dupStmt->fetchColumn()) { $_SESSION['flash_success'] = $agentVerified ? 'Listing published successfully!' : 'Listing submitted for review.'; header('Location: /agent/listings.php'); exit; }

if ($listingType === 'car') {
$mileageValue = extractMileage($s('mileage', 100));
$requestedCarType = ($data['car_listing_type'] ?? 'sale') === 'rental' ? 'rental' : 'sale';
if ($requestedCarType === 'rental') {
$bizCheck = $db->prepare("SELECT company_name, kyb_status FROM agent_profiles WHERE user_id = ?"); $bizCheck->execute([$agentId]); $bizRow = $bizCheck->fetch(PDO::FETCH_ASSOC) ?: [];
if (!(trim((string)($bizRow['company_name'] ?? '')) !== '' && ($bizRow['kyb_status'] ?? '') === 'approved')) { $requestedCarType = 'sale'; }
}
$stmt = $db->prepare("INSERT INTO car_listings (agent_id, title, brand, model, year, price, mileage, fuel_type, transmission, color, condition_status, body_type, drivetrain, doors, engine, gearbox, car_type, drive, drive_train, vin, interior_color, seats, features, country, description, city, state, listing_type, inspection_fee, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?, ?, NOW(), NOW())");
$stmt->execute([$agentId,$title,$s('brand',100)?:'Other',$s('model',100)?:'Other',$int('year')??date('Y'),$price,$mileageValue,$s('fuel_type',50),$s('transmission',50)?:$s('gearbox',50),$s('color',50),$s('condition_status',50)?:$s('condition',50),$s('body_type',50)?:$s('car_type',50),$s('drivetrain',50)?:$s('drive_train',50),$int('doors'),$s('engine',100),$s('gearbox',50),$s('car_type',50),$s('drive',10),$s('drive_train',50),$s('vin',50),$s('interior_color',50),$int('seats'),!empty($data['features'])?$s('features',1000):null,$s('country',100)?:'Nigeria',$description,$s('city',100),$s('state',100),$requestedCarType,$num('inspection_fee'),$listingStatus]);
} elseif ($listingType === 'property') {
$stmt = $db->prepare("INSERT INTO property_listings (agent_id, title, property_type, listing_type, price, beds, baths, sqft, lot_size, year_built, address, city, state, zip_code, country, latitude, longitude, description, features, amenities, view_type, hoa_fees, inspection_fee, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?, ?, NOW(), NOW())");
$stmt->execute([$agentId,$title,$s('property_type',100)?:'Residential',in_array(($data['listing_type_purpose']??''),['sale','rent'],true)?$data['listing_type_purpose']:'sale',$price,$int('beds')??$int('bedrooms'),$int('baths')??$int('bathrooms'),$int('sqft')??$int('area'),$num('lot_size'),$int('year_built'),$s('address',500),$s('city',100),$s('state',100),$s('zip_code',20),$s('country',100)?:'Nigeria',$num('latitude'),$num('longitude'),$description,!empty($data['features'])?json_encode($data['features']):null,!empty($data['amenities'])?json_encode($data['amenities']):null,$s('view_type',100),$num('hoa_fees'),$num('inspection_fee'),$listingStatus]);
} elseif ($listingType === 'solar') {
// ── SOLAR HARDWARE PARTITIONING (unit-aware capacities) ─────────────
$hardwareType = in_array(($data['hardware_type'] ?? ''), ['solar_panel','inverter','battery','power_station'], true) ? $data['hardware_type'] : 'solar_panel';
$panelWatts  = $int('panel_watts');            // W
$inverterKva = $num('inverter_kva');           // kW/kVA
$batteryKwh  = $num('battery_kwh');            // kWh
// Legacy capacity_kw kept in sync (kW) for old readers / calculator fallback.
$capacityKw = $num('capacity_kw') ?? $num('capacity');
if ($capacityKw === null) {
if ($hardwareType === 'solar_panel' && $panelWatts !== null) { $capacityKw = round($panelWatts / 1000, 3); }
elseif (($hardwareType === 'inverter' || $hardwareType === 'power_station') && $inverterKva !== null) { $capacityKw = $inverterKva; }
}
$cols = ['agent_id','title','service_type','price','brand','capacity_kw','warranty_years','description','features','city','state','country','status'];
$vals = [$agentId,$title,
in_array(($data['service_type']??''),['residential','commercial','industrial','maintenance','financing'],true)?$data['service_type']:($s('solar_type',50)?strtolower($s('solar_type',50)):'residential'),
$price,$s('brand',100),$capacityKw,$int('warranty_years'),$description,!empty($data['features'])?json_encode($data['features']):null,$s('city',100),$s('state',100),$s('country',100)?:'Nigeria',$listingStatus];
$colCheck = $db->query("SHOW COLUMNS FROM solar_listings")->fetchAll(PDO::FETCH_COLUMN);
if (in_array('hardware_type', $colCheck)) { $cols[]='hardware_type'; $vals[]=$hardwareType; }
if (in_array('panel_watts', $colCheck))  { $cols[]='panel_watts';  $vals[]=$panelWatts; }
if (in_array('inverter_kva', $colCheck)) { $cols[]='inverter_kva'; $vals[]=$inverterKva; }
if (in_array('battery_kwh', $colCheck))  { $cols[]='battery_kwh';  $vals[]=$batteryKwh; }
$ph = implode(',', array_fill(0, count($cols), '?'));
$stmt = $db->prepare("INSERT INTO solar_listings (" . implode(',', $cols) . ", created_at) VALUES ($ph, NOW())");
$stmt->execute($vals);
} else { // marketplace
$categoryId = $int('category_id') ?? $int('category');
$stmt = $db->prepare("INSERT INTO marketplace_listings (agent_id, title, category_id, price, description, condition_status, brand, city, state, country, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?, ?, NOW(), NOW())");
$stmt->execute([$agentId,$title,$categoryId,$price,$description,$s('condition_status',50)?:$s('condition',50),$s('brand',100),$s('city',100),$s('state',100),$s('country',100)?:'Nigeria',$listingStatus]);
}
$listingId = (int)$db->lastInsertId();
$imagesAttempted = 0; $imagesSaved = 0; $imageErrors = [];
if (!empty($_FILES['images']['name'][0]) && $_FILES['images']['name'][0] !== '') {
$subDirMap = ['car'=>'cars','property'=>'properties','solar'=>'products','marketplace'=>'products'];
$subDir = $subDirMap[$listingType] ?? 'general';
$uploader = new FileUpload($subDir);
$imageCount = count($_FILES['images']['name']); $imagesAttempted = $imageCount;
$imgStmt = $db->prepare("INSERT INTO listing_images (listing_id, listing_type, url, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())");
$sortOrder = 0;
for ($i = 0; $i < $imageCount; $i++) {
if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
$fileArr = ['name'=>$_FILES['images']['name'][$i],'type'=>$_FILES['images']['type'][$i],'tmp_name'=>$_FILES['images']['tmp_name'][$i],'error'=>$_FILES['images']['error'][$i],'size'=>$_FILES['images']['size'][$i]];
$result = $uploader->upload($fileArr, ['prefix'=>"listing_{$listingId}_",'maxWidth'=>1920,'maxHeight'=>1080,'quality'=>85]);
if (!$result['success']) { $imageErrors[] = $result['error'] ?? 'Unknown upload error'; continue; }
$publicUrl = isset($result['key']) ? $result['filepath'] : '/uploads/' . $subDir . '/' . $result['filename'];
if ($imgStmt->execute([$listingId, $listingType, $publicUrl, $sortOrder])) { $imagesSaved++; $sortOrder++; }
}
}
if (!empty($imageErrors)) error_log('Image upload errors for listing ' . $listingId . ': ' . implode(', ', $imageErrors));
if ($listingType === 'property') {
$vtType = ($data['virtual_tour_type'] ?? 'link') === 'video' ? 'video' : 'link';
$vtUrl = null; $vtThumbnail = null; $vtError = null;
if ($vtType === 'link') { $link = trim($s('virtual_tour_url', 500) ?? ''); if ($link !== '' && filter_var($link, FILTER_VALIDATE_URL)) $vtUrl = $link; }
elseif (!empty($_FILES['virtual_tour_video']['name']) && $_FILES['virtual_tour_video']['error'] !== UPLOAD_ERR_NO_FILE) {
if ($_FILES['virtual_tour_video']['error'] === UPLOAD_ERR_OK) {
$detectedMime = @mime_content_type($_FILES['virtual_tour_video']['tmp_name']) ?: $_FILES['virtual_tour_video']['type'];
$compression = compressVideoIfPossible($_FILES['virtual_tour_video']['tmp_name'], $detectedMime);
$videoUploader = new FileUpload('properties', ['video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm'], 150*1024*1024);
$videoResult = $videoUploader->upload(['name'=>$compression['compressed']?preg_replace('/\.[a-zA-Z0-9]+$/','.mp4',$_FILES['virtual_tour_video']['name']):$_FILES['virtual_tour_video']['name'],'type'=>$compression['compressed']?'video/mp4':$_FILES['virtual_tour_video']['type'],'tmp_name'=>$_FILES['virtual_tour_video']['tmp_name'],'error'=>$_FILES['virtual_tour_video']['error'],'size'=>$compression['compressed']?$compression['new_size']:$_FILES['virtual_tour_video']['size']], ['prefix'=>"listing_{$listingId}_tour_"]);
if ($videoResult['success']) {
$vtUrl = isset($videoResult['key']) ? $videoResult['filepath'] : '/uploads/properties/' . $videoResult['filename'];
$posterPath = generateVideoThumbnail($_FILES['virtual_tour_video']['tmp_name']);
if ($posterPath !== null) { $pu = new FileUpload('properties'); $pr = $pu->uploadGeneratedFile($posterPath, 'image/jpeg', ['prefix'=>"listing_{$listingId}_tour_thumb_"]); if ($pr['success']) $vtThumbnail = isset($pr['key']) ? $pr['filepath'] : '/uploads/properties/' . $pr['filename']; @unlink($posterPath); }
} else { $vtError = $videoResult['error'] ?? 'Unknown upload error'; }
} else { $vtError = 'Video upload failed (error code ' . $_FILES['virtual_tour_video']['error'] . ')'; }
}
if ($vtUrl !== null) { $db->prepare("UPDATE property_listings SET virtual_tour_url = ?, virtual_tour_type = ?, virtual_tour_thumbnail = ? WHERE id = ?")->execute([$vtUrl, $vtType, $vtThumbnail, $listingId]); }
elseif ($vtError !== null) error_log("Virtual tour upload error for listing $listingId: $vtError");
}
if (!empty($data['featured'])) { $db->prepare("UPDATE $table SET featured = 1 WHERE id = ?")->execute([$listingId]); }
Security::logActivity($agentId, 'listing_created', "Created $listingType listing #$listingId");
$message = $agentVerified ? 'Listing published successfully!' : 'Listing submitted for review.';
if ($imagesAttempted > 0 && $imagesSaved < $imagesAttempted) { $message .= $imagesSaved === 0 ? ' Note: none of your photos could be saved — please try re-uploading them from Edit Listing.' : " Note: only {$imagesSaved} of {$imagesAttempted} photos were saved — you can add the rest from Edit Listing."; }
if (isset($vtError) && $vtError !== null) { $message .= ' Note: the virtual tour video could not be saved — please try re-uploading it from Edit Listing.'; }
$_SESSION['flash_success'] = $message; header('Location: /agent/listings.php'); exit;
} catch (\PDOException $e) {
error_log('Listing creation error: ' . $e->getMessage());
if ($isFormSubmit) { $_SESSION['flash_error'] = 'Failed to create listing: ' . $e->getMessage(); header('Location: /agent/add-listing.php'); exit; }
http_response_code(500); echo json_encode(['error' => 'Failed to create listing: ' . $e->getMessage()]); exit;
}
?>
