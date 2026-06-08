<?php
http_response_code(500);
$pageTitle = 'Server Error - KINAS GROUP';
include __DIR__ . '/../templates/header.php';
?>

<div style="min-height: calc(100vh - 66px - 400px); display: flex; align-items: center; justify-content: center; padding: 80px 30px; text-align: center;">
    <div>
        <div style="font-family:'Prata',serif; font-size: 120px; color: #C6A43F; line-height: 1; margin-bottom: 12px;">500</div>
        <h1 style="font-family:'Prata',serif; font-size: 32px; color: #0A0A0A; margin-bottom: 14px;">Something went wrong</h1>
        <p style="color: #666; font-size: 15px; max-width: 460px; margin: 0 auto 32px;">We've been notified and our team is looking into it. Please try again in a moment.</p>
        <a href="/" class="je-btn je-btn-gold">Back to Home</a>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
