<?php
/**
* Agent: update own profile.
* - Updates the users row (name, phone)
* - Upserts the agent_profiles row (bio, company, license, years, socials, website, avatar)
* - Uses R2 storage for avatar when available
*/
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$token = $data['csrf_token'] ?? '';
if ($token === '' || !Security::verifyCSRFToken($token)) {
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) { header('Content-Type: application/json'); http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token']); }
    else { $_SESSION['flash_error'] = 'Please refresh the page and try again.'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/agent/profile.php')); }
    exit;
}

$userId = (int)$_SESSION['user_id'];
$redirectAfter = $_SERVER['HTTP_REFERER'] ?? '/agent/profile.php';
if (!preg_match('#^/[a-zA-Z0-9_\-/]*(\.php)?(\?.*)?$#', $redirectAfter)) {
    $redirectAfter = '/agent/profile.php';
}

try {
    $db = Database::getInstance()->getConnection();

    // 1. Update users row
    $userUpdates = [];
    $userParams = [];

    if (isset($data['name']) && trim($data['name']) !== '') {
        $userUpdates[] = 'name = ?';
        $userParams[] = trim($data['name']);
        $_SESSION['user_name'] = trim($data['name']);
    }

    if (isset($data['phone'])) {
        $userUpdates[] = 'phone = ?';
        $userParams[] = trim($data['phone']);
    }

    if (!empty($userUpdates)) {
        $userParams[] = $userId;
        $db->prepare('UPDATE users SET ' . implode(', ', $userUpdates) . ' WHERE id = ?')
           ->execute($userParams);
    }

    // 2. Upsert agent_profiles row
    $exists = $db->prepare("SELECT id FROM agent_profiles WHERE user_id = ?");
    $exists->execute([$userId]);
    $profileId = $exists->fetchColumn();

    $profileFields = [
        'bio'            => 'bio',
        'company_name'   => 'company_name',
        'company_legal_name' => 'company_legal_name',
        'license_number' => 'license_number',
        'website'        => 'website',
        'facebook'       => 'facebook',
        'twitter'        => 'twitter',
        'instagram'      => 'instagram',
        'linkedin'       => 'linkedin',
        'youtube'        => 'youtube',
        'years_in_business' => 'years_in_business',
        'professional_affiliations' => 'professional_affiliations',
    ];

    $yibMap = [
        'lt_1'   => 'lt_1',
        '1_3'    => '1_3',
        '3_5'    => '3_5',
        '5_plus' => '5_plus',
    ];

    $updates = [];
    $params = [];

    foreach ($profileFields as $formName => $col) {
        if (!array_key_exists($formName, $data)) continue;
        $val = trim((string)$data[$formName]);
        if ($col === 'years_in_business' && isset($yibMap[$val])) {
            $val = $yibMap[$val];
        }
        $updates[] = "$col = ?";
        $params[] = $val;
    }

    if ($profileId) {
        if (!empty($updates)) {
            $params[] = $userId;
            $db->prepare("UPDATE agent_profiles SET " . implode(', ', $updates) . " WHERE user_id = ?")
               ->execute($params);
        }
    } else {
        $division = $_SESSION['user_division'] ?? 'kinas-automobile';
        $divisionMap = [
            'kinas-automobile'      => 'automobile',
            'williams-connect-home' => 'real_estate',
            'kinas-volt'            => 'solar',
            'kinas-marketplace'     => 'marketplace',
        ];
        $dbDiv = $divisionMap[$division] ?? 'automobile';

        $ins = ['user_id', 'division'];
        $val = [$userId, $dbDiv];

        foreach ($updates as $i => $expr) {
            $col = trim(explode('=', $expr)[0]);
            $ins[] = $col;
            $val[] = $params[$i];
        }

        $placeholders = implode(',', array_fill(0, count($ins), '?'));
        $db->prepare("INSERT INTO agent_profiles (" . implode(',', $ins) . ") VALUES ($placeholders)")
           ->execute($val);
    }

    // 3. Optional avatar upload — uses R2 when available
    if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (in_array($ext, $allowedExts, true) && $_FILES['avatar']['size'] <= 5 * 1024 * 1024) {
            $avatarUrl = null;

            // Try R2 first
            if (class_exists('R2Upload', false) || (file_exists(__DIR__ . '/../../includes/r2-upload.php') && require_once __DIR__ . '/../../includes/r2-upload.php')) {
                try {
                    $uploader = new R2Upload('general', [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                        'image/gif'  => 'gif',
                    ], 5 * 1024 * 1024);
                    
                    $result = $uploader->upload($_FILES['avatar'], [
                        'prefix' => 'avatar_' . $userId . '_',
                    ]);

                    if (!empty($result['success']) && !empty($result['filepath'])) {
                        $avatarUrl = $result['filepath'];
                    }
                } catch (Throwable $e) {
                    error_log('R2 avatar upload failed: ' . $e->getMessage());
                }
            }

            // Fallback to local storage
            if ($avatarUrl === null) {
                $uploadDir = __DIR__ . '/../../uploads/avatars/' . $userId . '/';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                
                $newName = 'avatar_' . uniqid() . '.' . $ext;
                $target = $uploadDir . $newName;
                
                if (@move_uploaded_file($_FILES['avatar']['tmp_name'], $target)) {
                    $avatarUrl = '/uploads/avatars/' . $userId . '/' . $newName;
                }
            }

            // Save avatar URL
            if ($avatarUrl !== null) {
                $db->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$avatarUrl, $userId]);
                $db->prepare("UPDATE agent_profiles SET avatar = ? WHERE user_id = ?")->execute([$avatarUrl, $userId]);
            }
        }
    }

    // 4. Password change
    if (!empty($data['new_password'])) {
        $cur = (string)($data['current_password'] ?? '');
        $new = (string)$data['new_password'];
        $confirm = (string)($data['confirm_password'] ?? '');

        if (strlen($new) < 8) {
            $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
            if ($isJson) { header('Content-Type: application/json'); http_response_code(422); echo json_encode(['error' => 'Password must be at least 8 characters.']); }
            else { $_SESSION['flash_error'] = 'Password must be at least 8 characters.'; header('Location: ' . $redirectAfter); }
            exit;
        }

        if ($new !== $confirm) {
            $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
            if ($isJson) { header('Content-Type: application/json'); http_response_code(422); echo json_encode(['error' => 'Password confirmation does not match.']); }
            else { $_SESSION['flash_error'] = 'Password confirmation does not match.'; header('Location: ' . $redirectAfter); }
            exit;
        }

        $row = $db->prepare("SELECT password FROM users WHERE id = ?");
        $row->execute([$userId]);
        $hash = $row->fetchColumn();

        if (!$hash || !password_verify($cur, $hash)) {
            $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
            if ($isJson) { header('Content-Type: application/json'); http_response_code(422); echo json_encode(['error' => 'Current password is incorrect.']); }
            else { $_SESSION['flash_error'] = 'Current password is incorrect.'; header('Location: ' . $redirectAfter); }
            exit;
        }

        $newHash = password_hash($new, PASSWORD_BCRYPT);
        $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $userId]);
    }

    Security::logActivity($userId, 'profile_updated', 'Agent updated own profile');

    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Profile updated']);
    } else {
        $_SESSION['flash_success'] = 'Profile updated successfully.';
        header('Location: ' . $redirectAfter);
        exit;
    }

} catch (Exception $e) {
    error_log('update-profile error: ' . $e->getMessage());
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update profile: ' . $e->getMessage()]);
    } else {
        $_SESSION['flash_error'] = 'Failed to update profile.';
        header('Location: ' . $redirectAfter);
        exit;
    }
}
