<?php
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/validation.php';
require_once '../../includes/file-upload.php';
require_once '../../includes/video-compress.php';
$isFormSubmit = !empty($_POST) || !empty($_FILES);
if ($isFormSubmit) { $data = $_POST; } else { $raw = file_get_contents('php://input'); $data = json_decode($raw, true); if (!$data) $data = $_POST; }
if (empty($data)) { if ($isFormSubmit) { $_SESSION['flash_error'] = 'No data received. Please fill in the form.'; header('Location: /agent/add-listing.php'); exit; } http_response_code(400); echo json_encode(['error' => 'No data received']); exit; }
$listingType = (string)($data['listing_type'] ?? '');
$tables = ['car'=>'car_listings','property'=>'property_listings','solar'=>'solar_listings','marketplace'=>'marketplace_listings'];
if (!isset($tables[$listingType])) { if ($isFormSubmit) { $_SESSION['flash_error'] = 'Invalid listing type.'; header('Location: /agent/add-listing.php'); exit; } http_response_code(400); echo json_encode(['error' => 'Invalid listing type']); exit; }
$table = $tables[$listingType];
require_once __DIR__ . '/../config/constants.php';
$listingTypeToDivision = ['car'=>DIVISION_AUTOMOBILE,'property'=>DIVISION_REAL_ESTATE,'solar'=>DIVISION_SOLAR,'marketplace'=>DIVISION_MARKETPLACE];
$agentDivision = $_SESSION['user_division'] ?? null;
$isSuperAgent  = !empty($_SESSION['is_super_agent']);
if (SessionManager::getUserRole() !== 'admin' && !$isSuperAgent) {
if (!$agentDivision || $agentDivision !== $listingTypeToDivision[$listingType]) {
$errorMsg = 'You can only create listings in your assigned division (' . ($agentDivision ? $agentDivision : 'none assigned') . ').';
if ($isFormSubmit) { $_SESSION['flash_error'] = $errorMsg; header('Location: /agent/add-listing.php'); exit; }
http_response_code(403); echo json_encode(['error' => $errorMsg]); exit; }
}
$title = Security::sanitizeInput((string)($data['title'] ?? ''));
$price = (float)($data['price'] ?? 0);
$description = Security::sanitizeInput((string)($data['description'] ?? ''));
if ($title === '' || mb_strlen($title) < 3) { if ($isFormSubmit) { $_SESSION['flash_error'] = 'Title is required (min 3 characters)'; header('Location: /agent/add-listing.php'); exit; } http_response_code(422); echo json_encode(['error' => 'Title is required']); exit; }
if ($price <= 0) { if ($isFormSubmit) { $_SESSION['flash_error'] = 'Price must be greater than zero'; header('Location: /agent/add-listing.php'); exit; } http_response_code(422); echo json_encode(['error' => 'Price must be greater than zero']); exit; }
$agentId = (int)$_SESSION['user_id'];
$s = function (string $k, int $max = 255) use ($data) { return Security::sanitizeInput(mb_substr((string)($data[$k] ?? ''), 0, $max)); };
$int = function (string $k) use ($data): ?int { $v = $data[$k] ?? null; if ($v === '' || $v === null) return null; return (int)$v; };
$num = function (string $k) use ($data): ?float { $v = $data[$k] ?? null; if ($v === '' || $v === null) return null; return (float)$v; };
function extractMileage($value) { if ($value === null || $value === '') return null; if (is_numeric($value)) return (int)$value; preg_match('/^(\d+)/', $value, $m); return !empty($m[1]) ? (int)$m[1] : null; }
try {
$db = Database::getInstance()->getConnection();
$agentVerified = false;
try { $c = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?"); $c->execute([$agentId]); $agentVerified = ($c->fetchColumn() === 'approved'); } catch (Exception $e) {}
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
$stmt = $db->prepare("INSERT INTO car_listings (agent_id,title,brand,model,year,price,mileage,fuel_type,transmission,color,condition_status,body_type,drivetrain,doors,engine,gearbox,car_type,drive,drive_train,vin,interior_color,seats,features,country,description,city,state,listing_type,inspection_fee,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
$stmt->execute([$agentId,$title,$s('brand',100)?:'Other',$s('model',100)?:'Other',$int('year')??date('Y'),$price,$mileageValue,$s('fuel_type',50),$s('transmission',50)?:$s('gearbox',50),$s('color',50),$s('condition_status',50)?:$s('condition',50),$s('body_type',50)?:$s('car_type',50),$s('drivetrain',50)?:$s('drive_train',50),$int('doors'),$s('engine',100),$s('gearbox',50),$s('car_type',50),$s('drive',10),$s('drive_train',50),$s('vin',50),$s('interior_color',50),$int('seats'),!empty($data['features'])?$s('features',1000):null,$s('country',100)?:'Nigeria',$description,$s('city',100),$s('state',100),$requestedCarType,$num('inspection_fee'),$listingStatus]);
} elseif ($listingType === 'property') {
$stmt = $db->prepare("INSERT INTO property_listings (agent_id,title,property_type,listing_type,price,beds,baths,sqft,lot_size,year_built,address,city,state,zip_code,country,latitude,longitude,description,features,amenities,view_type,hoa_fees,inspection_fee,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
$stmt->execute([$agentId,$title,$s('property_type',100)?:'Residential',in_array(($data['listing_type_purpose']??''),['sale','rent'],true)?$data['listing_type_purpose']:'sale',$price,$int('beds')??$int('bedrooms'),$int('baths')??$int('bathrooms'),$int('sqft')??$int('area'),$num('lot_size'),$int('year_built'),$s('address',500),$s('city',100),$s('state',100),$s('zip_code',20),$s('country',100)?:'Nigeria',$num('latitude'),$num('longitude'),$description,!empty($data['features'])?json_encode($data['features']):null,!empty($data['amenities'])?json_encode($data['amenities']):null,$s('view_type',100),$num('hoa_fees'),$num('inspection_fee'),$listingStatus]);
} elseif ($listingType === 'solar') {
// ── SOLAR HARDWARE PARTITIONING ─────────────────────────────
$hardwareType = in_array(($data['hardware_type'] ?? ''), ['solar_panel','inverter','battery','power_station'], true) ? $data['hardware_type'] : 'solar_panel';
$panelWatts  = $
  
