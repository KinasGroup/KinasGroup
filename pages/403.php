<?php
http_response_code(403);
$pageTitle = 'Access Denied - KINAS GROUP';
include __DIR__ . '/../templates/header.php';
?>

<div style="min-height: calc(100vh - 66px - 400px); display: flex; align-items: center; justify-content: center; padding: 80px 30px; text-align: center;">
    <div>
        <div style="font-family:'Prata',serif; font-size: 120px; color: #C6A43F; line-height: 1; margin-bottom: 12px;">403</div>
        <h1 style="font-family:'Prata',serif; font-size: 32px; color: #0A0A0A; margin-bottom: 14px;">Access denied</h1>
        <p style="color: #666; font-size: 15px; max-width: 460px; margin: 0 auto 32px;">You don't have permission to view this page. If you believe this is a mistake, please contact support.</p>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <a href="/" class="je-btn je-btn-gold">Back to Home</a>
            <a href="/pages/contact.php" class="je-btn je-btn-outline">Contact Support</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
