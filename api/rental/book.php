rental/<?php
/**
 * KINAS AUTOMOBILE — Create a rental booking request.
 * Validates dates, checks availability, creates a 'pending' booking,
 * and notifies both the renter (confirmation) and the agent (new request).
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/email.php';
require_once '../../includes/notify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in to request a booking.']);
    exit;
}
$userId = SessionManager::getUserId();

Security::rateLimitDB('rental_book_' . Security::getClientIP(), 10, 600);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$carId = (int)($data['car_id'] ?? 0);
$startDate = $data['start_date'] ?? '';
$endDate = $data['end_date'] ?? '';

if (!$carId || !$startDate || !$endDate) {
    http_response_code(422);
    echo json_encode(['error' => 'Car, start date, and end date are required']);
    exit;
}

$start = DateTime::createFromFormat('Y-m-d', $startDate);
$end = DateTime::createFromFormat('Y-m-d', $endDate);
$today = new DateTime('today');

if (!$start || !$end || $start >= $end) {
    http_response_code(422);
    echo json_encode(['error' => 'End date must be after start date']);
    exit;
}
if ($start < $today) {
    http_response_code(422);
    echo json_encode(['error' => 'Start date cannot be in the past']);
    exit;
}

$totalDays = (int)$start->diff($end)->days;
if ($totalDays > 90) {
    http_response_code(422);
    echo json_encode(['error' => 'Bookings are limited to 90 days']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id, title, price, agent_id, listing_type, status FROM car_listings WHERE id = ?");
    $stmt->execute([$carId]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$car || $car['status'] !== 'active') {
        http_response_code(404);
        echo json_encode(['error' => 'This vehicle is not available']);
        exit;
    }
    if ($car['listing_type'] !== 'rental') {
        http_response_code(422);
        echo json_encode(['error' => 'This vehicle is not listed for rental']);
        exit;
    }

    // Overlap check: any pending/confirmed/active booking for this car
    // whose range intersects the requested range blocks the new request.
    $overlap = $db->prepare("
        SELECT id FROM car_rental_bookings
        WHERE car_id = ?
          AND status IN ('pending', 'confirmed', 'active')
          AND start_date <= ? AND end_date >= ?
        LIMIT 1
    ");
    $overlap->execute([$carId, $endDate, $startDate]);
    if ($overlap->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'This vehicle is already booked (or pending confirmation) for part of that date range']);
        exit;
    }

    $pricePerDay = (float)$car['price'];
    $totalPrice = $pricePerDay * $totalDays;

    $stmt = $db->prepare("
        INSERT INTO car_rental_bookings
            (car_id, user_id, agent_id, start_date, end_date, total_days, price_per_day, total_price, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$carId, $userId, $car['agent_id'], $startDate, $endDate, $totalDays, $pricePerDay, $totalPrice]);
    $bookingId = $db->lastInsertId();

    Security::logActivity($userId, 'rental_booking_requested', "Booking #$bookingId for car #$carId ($startDate to $endDate)");

    // Notify the renter
    $stmt = $db->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $renter = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalFmt = number_format($totalPrice);
    Notify::email(
        $renter['email'],
        "Your rental booking request for {$car['title']} has been received",
        "Hi {$renter['name']},\n\nYour booking request has been sent to the agent for approval:\n\nVehicle: {$car['title']}\nDates: {$startDate} to {$endDate} ({$totalDays} day" . ($totalDays === 1 ? '' : 's') . ")\nEstimated total: ₦{$totalFmt}\n\nThe agent will confirm or decline this request shortly — you'll receive another email either way.",
        null,
        INFO_EMAIL,
        'KINAS GROUP'
    );

    // Notify the agent
    $stmt = $db->prepare("SELECT name, email, phone, phone_verified_at FROM users WHERE id = ?");
    $stmt->execute([$car['agent_id']]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($agent) {
        Notify::email(
            $agent['email'],
            "New rental booking request: {$car['title']}",
            "Hi {$agent['name']},\n\n{$renter['name']} ({$renter['email']}) has requested to rent {$car['title']} from {$startDate} to {$endDate} ({$totalDays} day" . ($totalDays === 1 ? '' : 's') . ", ₦{$totalFmt} total).\n\nLog in to your dashboard to confirm or decline this booking.",
            null,
            SUPPORT_EMAIL,
            'KINAS GROUP Notifications'
        );
        if (!empty($agent['phone']) && !empty($agent['phone_verified_at'])) {
            Notify::sms($agent['phone'], "New rental booking request for {$car['title']} from {$renter['name']}. Check your dashboard.", 'NEW_INQUIRY');
        }
    }

    echo json_encode(['success' => true, 'message' => "Booking request sent! Estimated total: ₦{$totalFmt}. The agent will confirm shortly."]);

} catch (Exception $e) {
    error_log('Rental booking error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong. Please try again.']);
}
