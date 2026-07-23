<?php
/**
 * KINAS AUTOMOBILE — Agent confirms or rejects a rental booking request.
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
    $_SESSION['rb_flash'] = ['type' => 'error', 'message' => 'Security token expired. Please try again.'];
    header('Location: /agent/rental-bookings.php');
    exit;
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$bookingId || !in_array($action, ['confirm', 'reject'], true)) {
    $_SESSION['rb_flash'] = ['type' => 'error', 'message' => 'Invalid request.'];
    header('Location: /agent/rental-bookings.php');
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->prepare("
        SELECT b.*, c.title AS car_title, u.name AS renter_name, u.email AS renter_email
        FROM car_rental_bookings b
        JOIN car_listings c ON c.id = b.car_id
        JOIN users u ON u.id = b.user_id
        WHERE b.id = ? AND b.agent_id = ?
    ");
    $stmt->execute([$bookingId, $agentId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $_SESSION['rb_flash'] = ['type' => 'error', 'message' => 'Booking not found.'];
        header('Location: /agent/rental-bookings.php');
        exit;
    }
    if ($booking['status'] !== 'pending') {
        $_SESSION['rb_flash'] = ['type' => 'error', 'message' => 'This booking has already been ' . $booking['status'] . '.'];
        header('Location: /agent/rental-bookings.php');
        exit;
    }

    $newStatus = $action === 'confirm' ? 'confirmed' : 'rejected';
    $db->prepare("UPDATE car_rental_bookings SET status = ? WHERE id = ?")->execute([$newStatus, $bookingId]);

    Security::logActivity($agentId, 'rental_booking_' . $newStatus, "Booking #$bookingId for {$booking['car_title']}");

    $dateRange = date('M j', strtotime($booking['start_date'])) . ' – ' . date('M j, Y', strtotime($booking['end_date']));
    $totalFmt = number_format((float)$booking['total_price']);

    if ($newStatus === 'confirmed') {
        $body = "Hi {$booking['renter_name']},\n\nGreat news — your rental booking has been confirmed!\n\nVehicle: {$booking['car_title']}\nDates: {$dateRange}\nTotal: ₦{$totalFmt}\n\nThe agent will be in touch with pickup/handover details.";
        $subject = "Your rental booking for {$booking['car_title']} is confirmed";
    } else {
        $body = "Hi {$booking['renter_name']},\n\nUnfortunately, your rental booking request could not be confirmed:\n\nVehicle: {$booking['car_title']}\nDates: {$dateRange}\n\nThis may be because the vehicle became unavailable for those dates. Please feel free to browse other available rentals or try different dates.";
        $subject = "Your rental booking request for {$booking['car_title']} was not confirmed";
    }

    Notify::email($booking['renter_email'], $subject, $body, null, INFO_EMAIL, 'KINAS GROUP');

    $_SESSION['rb_flash'] = ['type' => 'success', 'message' => 'Booking ' . $newStatus . '. The renter has been notified.'];
    header('Location: /agent/rental-bookings.php');
    exit;

} catch (Exception $e) {
    error_log('Rental booking response error: ' . $e->getMessage());
    $_SESSION['rb_flash'] = ['type' => 'error', 'message' => 'Something went wrong. Please try again.'];
    header('Location: /agent/rental-bookings.php');
    exit;
}
