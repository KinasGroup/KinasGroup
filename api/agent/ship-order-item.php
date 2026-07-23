<?php
/**
 * KINAS MARKETPLACE — Agent marks an order item shipped or delivered.
 */
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/email.php';
require_once '../../includes/notify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

SessionManager::requireAgent();
$agentId = SessionManager::getUserId();

$token = $_POST['csrf_token'] ?? '';
if (!Security::verifyCSRFToken($token)) {
    $_SESSION['sales_flash'] = ['type' => 'error', 'message' => 'Security token expired. Please try again.'];
    header('Location: /agent/sales.php');
    exit;
}

$itemId = (int)($_POST['item_id'] ?? 0);
$action = $_POST['action'] ?? '';
$trackingNumber = trim(Security::sanitizeInput($_POST['tracking_number'] ?? ''));

if (!$itemId || !in_array($action, ['ship', 'deliver'], true)) {
    $_SESSION['sales_flash'] = ['type' => 'error', 'message' => 'Invalid request.'];
    header('Location: /agent/sales.php');
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->prepare("
        SELECT oi.*, o.email AS buyer_email, o.reference, o.shipping_address
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE oi.id = ? AND oi.agent_id = ?
    ");
    $stmt->execute([$itemId, $agentId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        $_SESSION['sales_flash'] = ['type' => 'error', 'message' => 'Order item not found.'];
        header('Location: /agent/sales.php');
        exit;
    }

    if ($action === 'ship') {
        if ($item['shipping_status'] !== 'pending') {
            $_SESSION['sales_flash'] = ['type' => 'error', 'message' => 'This item has already been marked shipped.'];
            header('Location: /agent/sales.php');
            exit;
        }
        $db->prepare("UPDATE order_items SET shipping_status = 'shipped', tracking_number = ?, shipped_at = NOW() WHERE id = ?")
           ->execute([$trackingNumber ?: null, $itemId]);

        Security::logActivity($agentId, 'order_item_shipped', "Item #$itemId ({$item['title']}) for order {$item['reference']}");

        $trackingLine = $trackingNumber ? "\n\nTracking number: {$trackingNumber}" : '';
        Notify::email(
            $item['buyer_email'],
            "Your order is on its way — {$item['title']}",
            "Good news! \"{$item['title']}\" from your order {$item['reference']} has shipped.\n\nShipping to: {$item['shipping_address']}{$trackingLine}",
            null,
            SALES_EMAIL,
            'KINAS Marketplace'
        );

        $_SESSION['sales_flash'] = ['type' => 'success', 'message' => 'Marked as shipped. The buyer has been notified.'];

    } else { // deliver
        if ($item['shipping_status'] !== 'shipped') {
            $_SESSION['sales_flash'] = ['type' => 'error', 'message' => 'This item must be shipped before it can be marked delivered.'];
            header('Location: /agent/sales.php');
            exit;
        }
        $db->prepare("UPDATE order_items SET shipping_status = 'delivered', delivered_at = NOW() WHERE id = ?")
           ->execute([$itemId]);

        Security::logActivity($agentId, 'order_item_delivered', "Item #$itemId ({$item['title']}) for order {$item['reference']}");

        Notify::email(
            $item['buyer_email'],
            "Your order has been delivered — {$item['title']}",
            "\"{$item['title']}\" from your order {$item['reference']} has been marked as delivered. We hope you love it!\n\nIf you haven't received it, please contact us at " . SUPPORT_EMAIL . ".",
            null,
            SALES_EMAIL,
            'KINAS Marketplace'
        );

        $_SESSION['sales_flash'] = ['type' => 'success', 'message' => 'Marked as delivered. The buyer has been notified.'];
    }

    header('Location: /agent/sales.php');
    exit;

} catch (Exception $e) {
    error_log('Ship order item error: ' . $e->getMessage());
    $_SESSION['sales_flash'] = ['type' => 'error', 'message' => 'Something went wrong. Please try again.'];
    header('Location: /agent/sales.php');
    exit;
}
