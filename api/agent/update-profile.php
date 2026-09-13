<?php
/**
* Agent: update own profile.
*
* AMENDED:
* - Removed social link fields (facebook, twitter, instagram, linkedin, youtube).
* - Loads constants.php.
* - Validates avatar uploads properly.
* - Falls back to local upload with clear error messages.
* - Updates only agent_profiles columns that actually exist.
* - Saves extra business fields when columns exist.
* - Prevents silent avatar upload failure.
*/
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

$r2UploadFile = __DIR__ . '/../../includes/r2-upload.php';
if (file_exists($r2UploadFile)) {
    require_once $r2UploadFile;
}

/**
* Respond either as JSON or redirect with flash message.
*/
function agentUpdateRespond(bool $success, string $message, int $code = 200, string $redirect = '/agent/profile.php'): void
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '';

    $wantsJson =
        stripos($accept, 'application/json') !== false ||
        stripos($contentType, 'application/json') !== false;

    if ($wantsJson) {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'success' => $success,
            'message' => $message,
        ]);
        exit;
    }

    $_SESSION[$success ? 'flash_success' : 'flash_error'] = $message;
    header('Location: ' . $redirect);
    exit;
}

/**
* Human-readable PHP upload error.
*/
function agentUploadErrorCode(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded image exceeds the server upload_max_filesize limit. Increase upload_max_filesize and post_max_size.';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded image exceeds the form limit.';
        case UPLOAD_ERR_PARTIAL:
            return 'The image was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Server is missing the temporary upload folder.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'The server could not write the uploaded image to disk. Check permissions.';
        case UPLOAD_ERR_EXTENSION:
            return 'A server extension stopped the upload.';
        default:
            return 'Unknown upload error.';
    }
}

/**
* Handle avatar upload.
*
* Returns:
* [
*   'url' => string|null,
*   'error' => string|null,
* ]
*/
function agentHandleAvatar(array $file, int $userId): array
{
    $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return [
            'url' => null,
            'error' => null,
        ];
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        return [
            'url' => null,
            'error' => 'Image upload failed: ' . agentUploadErrorCode($errorCode),
        ];
    }

    $size = (int)($file['size'] ?? 0);

    if ($size <= 0) {
        return [
            'url' => null,
            'error' => 'Image upload failed: uploaded file is empty.',
        ];
    }

    $maxSize = 5 * 1024 * 1024; // 5MB

    if ($size > $maxSize) {
        return [
            'url' => null,
            'error' => 'Image upload failed: image is too large. Maximum allowed size is 5MB.',
        ];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $allowedExtToMime = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];

    if (!isset($allowedExtToMime[$ext])) {
        return [
            'url' => null,
            'error' => 'Image upload failed: only JPG, JPEG, PNG, WEBP or GIF images are allowed.',
        ];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mime !== $allowedExtToMime[$ext]) {
        return [
            'url' => null,
            'error' => 'Image upload failed: file content does not match the selected image type.',
        ];
    }

    $avatarUrl = null;
    $r2Error = '';

    // Try Cloudflare R2 first if available and enabled.
    if (class_exists('R2Upload') && defined('R2_ENABLED') && R2_ENABLED) {
        try {
            $allowedMimeToExt = array_flip($allowedExtToMime);
            $uploader = new R2Upload('general', $allowedMimeToExt, $maxSize);
            $result = $uploader->upload($file, [
                'prefix' => 'avatar_' . $userId . '_',
            ]);

            if (!empty($result['success']) && !empty($result['filepath'])) {
                $avatarUrl = $result['filepath'];
            } else {
                $r2Error = $result['error'] ?? 'R2 upload failed.';
            }
        } catch (Throwable $e) {
            $r2Error = $e->getMessage();
            error_log('Agent avatar R2 upload failed: ' . $e->getMessage());
        }
    }

    // Fallback to local storage.
    if ($avatarUrl === null) {
        $uploadRoot = defined('UPLOAD_DIR')
            ? rtrim(UPLOAD_DIR, '/')
            : dirname(__DIR__, 2) . '/uploads';

        $uploadDir = $uploadRoot . '/avatars/' . $userId;

        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return [
                'url' => null,
                'error' => 'Image upload failed: could not create avatar upload directory.',
            ];
        }

        if (!is_writable($uploadDir)) {
            return [
                'url' => null,
                'error' => 'Image upload failed: avatar upload directory is not writable. Check server permissions.',
            ];
        }

        $newName = 'avatar_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $uploadDir . '/' . $newName;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return [
                'url' => null,
                'error' => 'Image upload failed: could not move uploaded image into storage. Check upload limits and permissions.',
            ];
        }

        $avatarUrl = '/uploads/avatars/' . $userId . '/' . $newName;
    }

    return [
        'url' => $avatarUrl,
        'error' => null,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    agentUpdateRespond(false, 'Method not allowed', 405);
}

SessionManager::requireAgent();

$contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '';
$isJson = stripos($contentType, 'application/json') !== false;

if ($isJson) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $data = $_POST;
}

// If a multipart upload was too large, PHP may empty $_POST and $_FILES.
if (!$isJson && empty($_POST) && empty($_FILES)) {
    agentUpdateRespond(
        false,
        'The request was empty. If you uploaded an image, it may exceed the server upload_max_filesize or post_max_size limit.',
        400
    );
}

$token = $data['csrf_token'] ?? '';

if ($token === '' || !Security::verifyCSRFToken($token)) {
    agentUpdateRespond(false, 'Please refresh the page and try again.', 403);
}

$userId = (int)$_SESSION['user_id'];

$redirectAfter = $data['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/agent/profile.php');

if (!preg_match('#^/[a-zA-Z0-9_\-/]*(\.php)?(\?.*)?$#', $redirectAfter)) {
    $redirectAfter = '/agent/profile.php';
}

try {
    $db = Database::getInstance()->getConnection();

    // Get existing agent_profiles columns so we do not try to update missing columns.
    $profileColumns = [];

    try {
        $profileColumns = $db->query("SHOW COLUMNS FROM agent_profiles")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $profileColumns = [];
    }

    $profileColumns = array_flip($profileColumns);

    if (empty($profileColumns)) {
        agentUpdateRespond(
            false,
            'Agent profile table is not available. Please run the database migrations.',
            500,
            $redirectAfter
        );
    }

    // Combine first/last name server-side as fallback.
    if (empty($data['name'])) {
        $first = trim((string)($data['name_first'] ?? ''));
        $last = trim((string)($data['name_last'] ?? ''));

        if ($first !== '' || $last !== '') {
            $data['name'] = trim($first . ' ' . $last);
        }
    }

    // Validate password change early.
    $newPasswordHash = null;

    if (!empty($data['new_password'])) {
        $currentPassword = (string)($data['current_password'] ?? '');
        $newPassword = (string)$data['new_password'];
        $confirmPassword = (string)($data['confirm_password'] ?? '');

        if (strlen($newPassword) < 8) {
            agentUpdateRespond(false, 'Password must be at least 8 characters.', 422, $redirectAfter);
        }

        if ($newPassword !== $confirmPassword) {
            agentUpdateRespond(false, 'Password confirmation does not match.', 422, $redirectAfter);
        }

        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentHash = $stmt->fetchColumn();

        if (!$currentHash || !password_verify($currentPassword, $currentHash)) {
            agentUpdateRespond(false, 'Current password is incorrect.', 422, $redirectAfter);
        }

        $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // Validate phone.
    $phoneValue = null;
    $updatePhone = false;

    if (array_key_exists('phone', $data)) {
        $updatePhone = true;
        $phoneValue = trim((string)$data['phone']);

        if ($phoneValue === '') {
            $phoneValue = null;
        } elseif (!Security::isValidPhone($phoneValue)) {
            agentUpdateRespond(false, 'Phone number format is invalid.', 422, $redirectAfter);
        }
    }

    // Validate company email.
    $companyEmailValue = null;
    $updateCompanyEmail = false;

    if (array_key_exists('company_email', $data)) {
        $updateCompanyEmail = true;
        $companyEmailValue = trim((string)$data['company_email']);

        if ($companyEmailValue !== '' && !filter_var($companyEmailValue, FILTER_VALIDATE_EMAIL)) {
            agentUpdateRespond(false, 'Company email address is invalid.', 422, $redirectAfter);
        }
    }

    // Validate website.
    $websiteValue = null;
    $updateWebsite = false;

    if (array_key_exists('website', $data)) {
        $updateWebsite = true;
        $websiteValue = trim((string)$data['website']);

        if ($websiteValue !== '') {
            $websiteToTest = preg_match('#^https?://#i', $websiteValue)
                ? $websiteValue
                : 'https://' . $websiteValue;

            if (!filter_var($websiteToTest, FILTER_VALIDATE_URL)) {
                agentUpdateRespond(false, 'Website URL is invalid.', 422, $redirectAfter);
            }

            $websiteValue = $websiteToTest;
        }
    }

    // Handle avatar before database updates so upload failure does not partially save.
    $avatarUrl = null;

    if (!empty($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $avatarResult = agentHandleAvatar($_FILES['avatar'], $userId);

        if (!empty($avatarResult['error'])) {
            agentUpdateRespond(false, $avatarResult['error'], 422, $redirectAfter);
        }

        $avatarUrl = $avatarResult['url'];
    }

    // Update users table.
    $userUpdates = [];
    $userParams = [];

    if (isset($data['name']) && trim((string)$data['name']) !== '') {
        $name = trim((string)$data['name']);

        if (function_exists('mb_strlen') && mb_strlen($name) > 255) {
            agentUpdateRespond(false, 'Name is too long.', 422, $redirectAfter);
        }

        if (!function_exists('mb_strlen') && strlen($name) > 255) {
            agentUpdateRespond(false, 'Name is too long.', 422, $redirectAfter);
        }

        $userUpdates[] = 'name = ?';
        $userParams[] = $name;
        $_SESSION['user_name'] = $name;
    }

    if ($updatePhone) {
        $userUpdates[] = 'phone = ?';
        $userParams[] = $phoneValue;
    }

    if (array_key_exists('company_name', $data) && trim((string)$data['company_name']) !== '') {
        $userUpdates[] = 'company = ?';
        $userParams[] = trim((string)$data['company_name']);
    }

    if ($avatarUrl !== null) {
        $userUpdates[] = 'avatar = ?';
        $userParams[] = $avatarUrl;
    }

    if ($newPasswordHash !== null) {
        $userUpdates[] = 'password = ?';
        $userParams[] = $newPasswordHash;
    }

    if (!empty($userUpdates)) {
        $userParams[] = $userId;
        $db->prepare('UPDATE users SET ' . implode(', ', $userUpdates) . ' WHERE id = ?')
           ->execute($userParams);
    }

    // Build agent_profiles update only for existing columns.
    // Social fields removed — no longer processed.
    $profileSet = [];

    $profileFields = [
        'bio' => 'bio',
        'company_name' => 'company_name',
        'company_legal_name' => 'company_legal_name',
        'cac_number' => 'cac_number',
        'tin' => 'tin',
        'tax_id' => 'tax_id',
        'company_email' => 'company_email',
        'license_number' => 'license_number',
        'website' => 'website',
        'years_in_business' => 'years_in_business',
        'professional_affiliations' => 'professional_affiliations',
    ];

    $yearsMap = [
        'lt_1' => 'lt_1',
        '1_3' => '1_3',
        '3_5' => '3_5',
        '5_plus' => '5_plus',
    ];

    foreach ($profileFields as $formName => $columnName) {
        if (!isset($profileColumns[$columnName])) {
            continue;
        }

        if (!array_key_exists($formName, $data)) {
            continue;
        }

        $value = trim((string)$data[$formName]);

        if ($formName === 'years_in_business') {
            if ($value === '') {
                continue;
            }

            $value = $yearsMap[$value] ?? null;

            if ($value === null) {
                continue;
            }
        }

        if ($formName === 'company_email' && $updateCompanyEmail) {
            $value = (string)$companyEmailValue;
        }

        if ($formName === 'website' && $updateWebsite) {
            $value = (string)$websiteValue;
        }

        $profileSet[$columnName] = $value;
    }

    if ($avatarUrl !== null && isset($profileColumns['avatar'])) {
        $profileSet['avatar'] = $avatarUrl;
    }

    // Upsert agent_profiles row.
    $existsStmt = $db->prepare("SELECT id FROM agent_profiles WHERE user_id = ?");
    $existsStmt->execute([$userId]);
    $profileId = $existsStmt->fetchColumn();

    if ($profileId) {
        if (!empty($profileSet)) {
            $setSql = [];
            $setParams = [];

            foreach ($profileSet as $column => $value) {
                $setSql[] = "$column = ?";
                $setParams[] = $value;
            }

            $setParams[] = $userId;

            $db->prepare("UPDATE agent_profiles SET " . implode(', ', $setSql) . " WHERE user_id = ?")
               ->execute($setParams);
        }
    } else {
        $insertColumns = ['user_id'];
        $insertParams = [$userId];

        if (isset($profileColumns['division'])) {
            $division = $_SESSION['user_division'] ?? 'kinas-automobile';

            $divisionMap = [
                'kinas-automobile' => 'automobile',
                'williams-connect-home' => 'real_estate',
                'kinas-volt' => 'solar',
                'kinas-marketplace' => 'marketplace',
            ];

            $insertColumns[] = 'division';
            $insertParams[] = $divisionMap[$division] ?? 'automobile';
        }

        foreach ($profileSet as $column => $value) {
            $insertColumns[] = $column;
            $insertParams[] = $value;
        }

        $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));

        $db->prepare("INSERT INTO agent_profiles (" . implode(',', $insertColumns) . ") VALUES ($placeholders)")
           ->execute($insertParams);
    }

    Security::logActivity($userId, 'profile_updated', 'Agent updated own profile');

    if ($newPasswordHash !== null) {
        SessionManager::regenerateSession();
        Security::logActivity($userId, 'password_changed', 'Agent changed own password');
    }

    $message = 'Profile updated successfully.';

    if ($avatarUrl !== null && $newPasswordHash !== null) {
        $message = 'Profile, photo and password updated successfully.';
    } elseif ($avatarUrl !== null) {
        $message = 'Profile and photo updated successfully.';
    } elseif ($newPasswordHash !== null) {
        $message = 'Profile and password updated successfully.';
    }

    agentUpdateRespond(true, $message, 200, $redirectAfter);
} catch (Throwable $e) {
    error_log('update-profile error: ' . $e->getMessage());

    agentUpdateRespond(
        false,
        'Failed to update profile. Check the server error log for details.',
        500,
        $redirectAfter
    );
}
