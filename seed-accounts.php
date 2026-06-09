<?php
/**
 * KINAS GROUP — Account Seeder
 * -------------------------------------------------------
 * Seeds the Super Admin and Super Agent accounts.
 * Run ONCE from the project root on the server:
 *
 *   php seed-accounts.php
 *
 * It is safe to re-run: existing accounts are skipped.
 * DELETE this file from the server after running it.
 * -------------------------------------------------------
 */

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/includes/dotenv.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/config/constants.php';

// ── Account Definitions ───────────────────────────────────────────────────────

$accounts = [

    // ── 1. Super Admin ────────────────────────────────────────────────────────
    [
        'type'     => 'admin',
        'name'     => 'Kinas Admin',
        'email'    => 'admin@kinas-group.com',
        'password' => 'Admin@Kinas2025!',   // ← CHANGE before going live
        'phone'    => '+2348107576042',
        'role'     => 'admin',
        'division' => null,                 // admins have no division
        'status'   => 'active',
        'verified' => 1,
        // no agent_profiles row needed
        'agent'    => false,
    ],

    // ── 2. Super Agent ────────────────────────────────────────────────────────
    [
        'type'     => 'agent',
        'name'     => 'Kinas Listing Agent',
        'email'    => 'listing@kinas-group.com',
        'password' => 'Agent@Kinas2025!',   // ← CHANGE before going live
        'phone'    => '+2348107576042',
        'role'     => 'agent',
        'division' => 'automobile',         // primary division; admin can reassign
        'status'   => 'active',
        'verified' => 1,
        // agent_profiles row: fully approved, all KYC stages cleared
        'agent'    => true,
        'agent_profile' => [
            'division'            => 'automobile',
            'company_name'        => 'KINAS GROUP',
            'verification_status' => 'approved',
        ],
    ],
];

// ── Run ───────────────────────────────────────────────────────────────────────

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("❌  Cannot connect to database: " . $e->getMessage() . "\n");
}

foreach ($accounts as $account) {
    echo "\n── {$account['type']}: {$account['email']} ──\n";

    // Check for existing account
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$account['email']]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        echo "   ⚠  Already exists (id={$existing}) — skipped.\n";
        continue;
    }

    $db->beginTransaction();

    try {
        // Hash password with the same algo the app uses (PASSWORD_DEFAULT = bcrypt)
        $hash = password_hash($account['password'], PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            INSERT INTO users
                (name, email, password, phone, role, division, status, verified,
                 email_verified_at, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?,
                 NOW(), NOW(), NOW())
        ");
        $stmt->execute([
            $account['name'],
            $account['email'],
            $hash,
            $account['phone'],
            $account['role'],
            $account['division'],
            $account['status'],
            $account['verified'],
        ]);

        $userId = $db->lastInsertId();
        echo "   ✔  users row inserted (id={$userId})\n";

        // Agent profile row
        if ($account['agent'] && !empty($account['agent_profile'])) {
            $ap = $account['agent_profile'];
            $stmt = $db->prepare("
                INSERT INTO agent_profiles
                    (user_id, division, company_name, verification_status,
                     kyc_passed_at, created_at, updated_at)
                VALUES
                    (?, ?, ?, ?,
                     NOW(), NOW(), NOW())
            ");
            $stmt->execute([
                $userId,
                $ap['division'],
                $ap['company_name'],
                $ap['verification_status'],
            ]);
            echo "   ✔  agent_profiles row inserted (verification_status={$ap['verification_status']})\n";
        }

        $db->commit();
        echo "   ✅  Done.\n";

    } catch (Exception $e) {
        $db->rollBack();
        echo "   ❌  Failed: " . $e->getMessage() . "\n";
    }
}

echo "\n─────────────────────────────────────────────\n";
echo "Seeding complete.\n";
echo "⚠  Change the passwords above and DELETE this file from the server.\n\n";
