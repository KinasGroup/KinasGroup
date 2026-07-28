<?php
/**
 * KINAS GROUP — Buyer removes an order from their own history.
 * Only allowed for orders that never charged them (failed/abandoned) or
 * are fully delivered — never for anything still pending/in progress.
 * transactions.order_id is ON DELETE SET NULL, so any commission record
 * already tied to a delivered order survives this untouched.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

SessionManager::requireLogin();
$userId = (int)($_SESSION['user_id'] ?? 0);

$token = $_POST['csrf_token'] ?? '';
if (!Security::verifyCSRFToken($token)) {
    $_SESSION['orders_flash'] = ['type' => 'error', 'message' => 'Security token expired. Please try again.'];
    header('Location: /user/orders.php');
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
if (!$orderId) {
    $_SESSION['orders_flash'] = ['type' => 'error', 'message' => 'Invalid order.'];
    header('Location: /user/orders.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id, status FROM orders WHERE id = ? AND buyer_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['orders_flash'] = ['type' => 'error', 'message' => 'Order not found.'];
        header('Location: /user/orders.php');
        exit;
    }

    $canDelete = in_array($order['status'], ['failed', 'abandoned'], true);

    if (!$canDelete && $order['status'] === 'paid') {
        $itemsStmt = $db->prepare("SELECT COUNT(*) AS total, SUM(shipping_status = 'delivered') AS delivered FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$orderId]);
        $row = $itemsStmt->fetch(PDO::FETCH_ASSOC);
        $canDelete = (int)$row['total'] > 0 && (int)$row['total'] === (int)$row['delivered'];
    }

    if (!$canDelete) {
        $_SESSION['orders_flash'] = ['type' => 'error', 'message' => 'This order is still in progress and cannot be removed.'];
        header('Location: /user/orders.php');
        exit;
    }

    // order_items.order_id has ON DELETE CASCADE; transactions.order_id
    // has ON DELETE SET NULL, so a delivered order's commission record
    // survives this with its financial fields intact.
    $db->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);

    Security::logActivity($userId, 'order_removed', "Order #$orderId removed from buyer history");

    $_SESSION['orders_flash'] = ['type' => 'success', 'message' => 'Order removed.'];
    header('Location: /user/orders.php');
    exit;

} catch (Exception $e) {
    error_log('delete-order.php error: ' . $e->getMessage());
    $_SESSION['orders_flash'] = ['type' => 'error', 'message' => 'Something went wrong. Please try again.'];
    header('Location: /user/orders.php');
    exit;
}
