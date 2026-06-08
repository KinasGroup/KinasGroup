<?php
/**
 * KINAS GROUP — Contact
 */
require_once dirname(__DIR__) . '/includes/session.php';

$pageTitle = 'Contact Us - KINAS GROUP';
$pageDescription = 'Get in touch with KINAS GROUP — we\'re here to help 24/7.';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // In production, dispatch via EmailService
        $success = true;
    }
}

include dirname(__DIR__) . '/templates/header.php';
?>

<div class="je-page-header" style="background-image: linear-gradient(135deg, rgba(10,10,10,0.85), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1423666639041-f56000c27a37?w=2000&q=80'); background-size:cover; background-position:center;">
    <h1>Contact Us</h1>
    <p>We're here to help — 24 hours a day, 7 days a week.</p>
</div>

<section style="max-width: 1200px; margin: 0 auto; padding: 80px 30px;">
    <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 60px;">

        <div>
            <h2 style="font-family:'Prata',serif; font-size:24px; color:#0A0A0A; margin-bottom:30px;">Get in touch</h2>

            <div style="display: flex; gap: 16px; margin-bottom: 26px;">
                <div style="width: 44px; height: 44px; background: rgba(198,164,63,0.1); color: #C6A43F; border-radius: 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-envelope"></i></div>
                <div>
                    <h4 style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #999; margin-bottom: 4px;">Email</h4>
                    <p style="font-size: 14px; color: #0A0A0A;"><a href="mailto:hello@kinasgroup.com" style="color: #C6A43F; text-decoration: none;">hello@kinasgroup.com</a></p>
                </div>
            </div>

            <div style="display: flex; gap: 16px; margin-bottom: 26px;">
                <div style="width: 44px; height: 44px; background: rgba(198,164,63,0.1); color: #C6A43F; border-radius: 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-headset"></i></div>
                <div>
                    <h4 style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #999; margin-bottom: 4px;">Support</h4>
                    <p style="font-size: 14px; color: #0A0A0A;"><a href="mailto:support@kinasgroup.com" style="color: #C6A43F; text-decoration: none;">support@kinasgroup.com</a></p>
                </div>
            </div>

            <div style="display: flex; gap: 16px; margin-bottom: 26px;">
                <div style="width: 44px; height: 44px; background: rgba(198,164,63,0.1); color: #C6A43F; border-radius: 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-newspaper"></i></div>
                <div>
                    <h4 style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #999; margin-bottom: 4px;">Press</h4>
                    <p style="font-size: 14px; color: #0A0A0A;"><a href="mailto:press@kinasgroup.com" style="color: #C6A43F; text-decoration: none;">press@kinasgroup.com</a></p>
                </div>
            </div>

            <div style="display: flex; gap: 16px; margin-bottom: 26px;">
                <div style="width: 44px; height: 44px; background: rgba(198,164,63,0.1); color: #C6A43F; border-radius: 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-clock"></i></div>
                <div>
                    <h4 style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #999; margin-bottom: 4px;">Hours</h4>
                    <p style="font-size: 14px; color: #0A0A0A;">Concierge &amp; support — 24/7<br>Office — Mon–Fri 9:00–18:00 WAT</p>
                </div>
            </div>
        </div>

        <div style="background: #fff; border: 1px solid #e8e8e8; border-radius: 4px; padding: 40px;">
            <h2 style="font-family:'Prata',serif; font-size:24px; color:#0A0A0A; margin-bottom:24px;">Send us a message</h2>

            <?php if ($success): ?>
                <div style="background: #E8F5E9; border: 1px solid #A7F3D0; color: #1B5E20; padding: 16px 20px; border-radius: 4px; font-size: 14px;">
                    <i class="fas fa-check-circle"></i> Thank you — your message has been received. We'll respond within 24 hours.
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div style="background: #FEF2F2; border: 1px solid #FECACA; color: #B71C1C; padding: 16px 20px; border-radius: 4px; font-size: 14px; margin-bottom: 18px;">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="contact.php">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label style="display:block; font-size: 12px; font-weight: 600; color: #333; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">Name *</label>
                            <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" style="width: 100%; padding: 12px 14px; border: 1px solid #e0e0e0; border-radius: 3px; font-family: Inter, sans-serif; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display:block; font-size: 12px; font-weight: 600; color: #333; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">Email *</label>
                            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" style="width: 100%; padding: 12px 14px; border: 1px solid #e0e0e0; border-radius: 3px; font-family: Inter, sans-serif; font-size: 14px;">
                        </div>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label style="display:block; font-size: 12px; font-weight: 600; color: #333; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">Subject</label>
                        <input type="text" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" style="width: 100%; padding: 12px 14px; border: 1px solid #e0e0e0; border-radius: 3px; font-family: Inter, sans-serif; font-size: 14px;">
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="display:block; font-size: 12px; font-weight: 600; color: #333; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">Message *</label>
                        <textarea name="message" rows="6" required style="width: 100%; padding: 12px 14px; border: 1px solid #e0e0e0; border-radius: 3px; font-family: Inter, sans-serif; font-size: 14px; resize: vertical;"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="je-btn je-btn-gold" style="width:100%;">Send Message</button>
                </form>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php include dirname(__DIR__) . '/templates/footer.php'; ?>
