<?php
http_response_code(404);
$pageTitle = 'Page Not Found - KINAS GROUP';
include __DIR__ . '/../templates/header.php';
?>

<div style="min-height: calc(100vh - 66px - 400px); display: flex; align-items: center; justify-content: center; padding: 80px 30px; text-align: center;">
    <div>
        <div style="font-family:'Prata',serif; font-size: 120px; color: #C6A43F; line-height: 1; margin-bottom: 12px;">404</div>
        <h1 style="font-family:'Prata',serif; font-size: 32px; color: #0A0A0A; margin-bottom: 14px;">Page not found</h1>
        <p style="color: #666; font-size: 15px; max-width: 460px; margin: 0 auto 32px;">The listing or page you're looking for has either been removed, sold, or never existed.</p>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <a href="/" class="je-btn je-btn-gold">Back to Home</a>
            <a href="/divisions/kinas-automobile/search.php" class="je-btn je-btn-outline">Browse Inventory</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
