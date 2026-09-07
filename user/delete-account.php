<?php
/**
* KINAS GROUP — Delete Account page
*
* Soft-delete confirmation page.
* Users/agents can delete their account here.
* They can later log in again to reactivate it.
*/

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';

SessionManager::requireLogin();

$db = Database::getInstance()->getConnection();
$userId = (int)SessionManager::getUserId();

$stmt = $db->prepare(
    "SELECT id, name, email, role, status
     FROM users
     WHERE id = ?
     LIMIT 1"
);

$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    SessionManager::logout();
    header('Location: /auth/login.php');
    exit;
}

if (($user['status'] ?? '') === 'deleted') {
    SessionManager::logout();
    header('Location: /auth/login.php?deleted=1');
    exit;
}

$csrfToken = Security::generateCSRFToken();
$userRole = (string)($user['role'] ?? 'user');
?>
<!DOCTYPE html>
<html lang="en" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="only light">
    <meta name="theme-color" content="#ffffff">

    <title>Delete Account - KINAS GROUP</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #F5F7FA;
            color: #111;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .delete-card {
            width: 100%;
            max-width: 560px;
            background: #fff;
            border: 1px solid #E0E0E0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
        }

        .delete-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        h1 {
            font-family: 'Prata', serif;
            font-size: 28px;
            font-weight: 400;
            color: #0A0A0A;
            margin-bottom: 12px;
        }

        .intro {
            font-size: 14px;
            line-height: 1.7;
            color: #555;
            margin-bottom: 20px;
        }

        .notice {
            background: #FFF8E1;
            border: 1px solid #FFE082;
            color: #7A5B00;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 22px;
        }

        .notice strong {
            display: block;
            margin-bottom: 4px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #D5D5D5;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            background: #fff;
        }

        input[type="password"]:focus {
            outline: none;
            border-color: #DC2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.08);
        }

        .confirm-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #FAFAFA;
            border: 1px solid #EEE;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 22px;
            font-size: 13px;
            line-height: 1.6;
            color: #444;
        }

        .confirm-row input {
            margin-top: 2px;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 999px;
            padding: 12px 22px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-back {
            background: #F3F4F6;
            color: #374151;
            border: 1px solid #E5E7EB;
        }

        .btn-back:hover {
            background: #E5E7EB;
        }

        .btn-delete {
            background: #DC2626;
            color: #fff;
        }

        .btn-delete:hover {
            background: #B91C1C;
        }

        .btn-delete:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .message {
            display: none;
            margin-top: 18px;
            border-radius: 10px;
            padding: 13px 16px;
            font-size: 13px;
            line-height: 1.6;
        }

        .message.error {
            display: block;
            background: #FEF2F2;
            color: #B91C1C;
            border: 1px solid #FECACA;
        }

        .message.success {
            display: block;
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #A7F3D0;
        }

        @media (max-width: 600px) {
            .delete-card {
                padding: 24px;
            }

            h1 {
                font-size: 24px;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="delete-card">
        <div class="delete-badge">
            <i class="fas fa-user-slash"></i>
            Danger Zone
        </div>

        <h1>Delete Account</h1>

        <p class="intro">
            You are about to delete your KINAS GROUP account:
            <strong><?= htmlspecialchars($user['email'] ?? '') ?></strong>.
        </p>

        <div class="notice">
            <strong>What happens next?</strong>

            <?php if ($userRole === 'agent'): ?>
                Your account will be deactivated and your listings will be hidden from public view.
                If you log in again with the same email and password, you will be given the option
                to reactivate your account and restore your listings.
            <?php else: ?>
                Your account will be deactivated.
                If you log in again with the same email and password, you will be given the option
                to reactivate your account.
            <?php endif; ?>
        </div>

        <form id="deleteAccountForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label for="password">Enter your password to confirm</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Your account password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="confirm-row">
                <input type="checkbox" id="confirmDelete" required>
                <label for="confirmDelete" style="margin:0; font-weight:500;">
                    I understand that my account will be deleted and that I can sign in again later
                    to reactivate it.
                </label>
            </div>

            <div class="actions">
                <a href="/user/settings.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Go Back
                </a>

                <button type="submit" class="btn btn-delete" id="deleteBtn">
                    <i class="fas fa-trash-alt"></i>
                    Delete My Account
                </button>
            </div>
        </form>

        <div id="deleteMessage" class="message"></div>
    </div>

    <script>
        document.getElementById('deleteAccountForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const form = this;
            const deleteBtn = document.getElementById('deleteBtn');
            const messageBox = document.getElementById('deleteMessage');
            const password = document.getElementById('password').value;
            const confirmDelete = document.getElementById('confirmDelete').checked;

            messageBox.className = 'message';
            messageBox.textContent = '';

            if (!password) {
                messageBox.className = 'message error';
                messageBox.textContent = 'Please enter your password.';
                return;
            }

            if (!confirmDelete) {
                messageBox.className = 'message error';
                messageBox.textContent = 'Please confirm that you want to delete your account.';
                return;
            }

            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

            try {
                const res = await fetch('/api/user/delete-account.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        password: password,
                        csrf_token: form.csrf_token.value
                    })
                });

                const data = await res.json();

                if (data.success) {
                    messageBox.className = 'message success';
                    messageBox.textContent = data.message || 'Your account has been deleted. Redirecting to login...';

                    setTimeout(function() {
                        window.location.href = data.redirect || '/auth/login.php?deleted=1';
                    }, 1800);

                    return;
                }

                messageBox.className = 'message error';
                messageBox.textContent = data.error || 'Unable to delete account. Please try again.';

                deleteBtn.disabled = false;
                deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete My Account';
            } catch (err) {
                console.error(err);

                messageBox.className = 'message error';
                messageBox.textContent = 'Network error. Please try again.';

                deleteBtn.disabled = false;
                deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete My Account';
            }
        });
    </script>
</body>
</html>
