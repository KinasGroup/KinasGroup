<?php
/**
 * KINAS GROUP — Refund Policy
 */
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/api/config/constants.php';

$pageTitle = 'Refund Policy - KINAS GROUP';
$pageDescription = 'Understand the refund policy for products and services offered by KINAS GROUP OF COMPANIES LIMITED.';

include dirname(__DIR__) . '/templates/header.php';
?>

<div class="je-page-header" style="background-image: linear-gradient(135deg, rgba(10,10,10,0.85), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=2000&q=80'); background-size:cover; background-position:center;">
    <h1>Refund Policy</h1>
    <p>Understanding our refund and return policies</p>
</div>

<section style="max-width: 900px; margin: 0 auto; padding: 60px 30px;">
    <div style="background: #fff; border: 1px solid #e8e8e8; border-radius: 4px; padding: 50px;">
        
        <div style="text-align: center; margin-bottom: 40px;">
            <h2 style="font-family:'Prata',serif; font-size:28px; color:#0A0A0A;">KINAS GROUP OF COMPANIES LIMITED</h2>
            <p style="color:#666; font-size:14px;">RC Number: 7997266</p>
            <p style="color:#666; font-size:14px;">Website: kinas-group.com</p>
        </div>

        <hr style="border: none; border-top: 2px solid #C6A43F; margin: 30px 0;">

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">1. Introduction</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">This Refund Policy explains the circumstances under which refunds may be issued for products and services offered through KINAS GROUP OF COMPANIES LIMITED and its operating divisions.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">By purchasing products or services through our website, you acknowledge and agree to this Refund Policy.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">This policy applies to all divisions operating under the KINAS GROUP ecosystem, including:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>KINAS Automobile</li>
            <li>Williams Connect Home</li>
            <li>KINAS Volt</li>
            <li>KINAS Marketplace</li>
        </ul>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">2. General Policy</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">KINAS GROUP is committed to fairness, transparency, and customer satisfaction.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Because our platform offers different categories of products and services, refund eligibility depends on the nature of the transaction.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Each request will be reviewed individually.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">3. Product Purchases</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Products purchased through KINAS Marketplace or KINAS Volt may qualify for a refund or replacement where:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>The wrong product was supplied.</li>
            <li>The product arrives significantly damaged due to shipping.</li>
            <li>The product has a verified manufacturing defect.</li>
            <li>The product differs materially from its published description.</li>
        </ul>
        <p style="color:#444; line-height:1.8; font-size:15px;">Requests should be made within 7 days of delivery unless a different warranty period applies.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Products must be returned in their original condition with all accessories, manuals, and packaging where reasonably possible.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">4. Solar Products and Installations</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">For solar equipment and installation services provided through KINAS Volt:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Installed systems cannot be refunded once installation has been completed, except where required by applicable law.</li>
            <li>Manufacturing defects will be handled in accordance with the manufacturer's warranty.</li>
            <li>Installation issues resulting directly from our workmanship will be investigated and, where appropriate, corrected at no additional labour cost during the applicable service warranty period.</li>
        </ul>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">5. Automobile Transactions</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Vehicle sales are generally considered final once ownership has been transferred and all contractual obligations have been completed.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Where a refundable reservation or booking deposit is specifically agreed in writing, the refund will be governed by the terms of that agreement.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Rental cancellations and refunds will be subject to the cancellation policy applicable to the booking.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">6. Real Estate Services</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Property purchases are governed by the individual sale agreement and applicable laws.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Deposits, reservation fees, agency fees, or other payments made in connection with property transactions may not be refundable unless expressly stated in writing.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Rental and short-let cancellations will be handled according to the terms agreed at the time of booking.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">7. Marketplace Vendors</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Where products are sold by independent vendors through the KINAS Marketplace, refund requests may first be reviewed by the vendor.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">KINAS GROUP reserves the right to intervene where necessary to facilitate fair resolution between buyers and vendors.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">8. Services</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Professional services, consultations, inspections, sourcing services, logistics, installation planning, or similar services that have already been completed are generally non-refundable.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Where services have not yet commenced, cancellation requests may be considered subject to any administrative costs already incurred.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">9. Non-Refundable Items</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Refunds will generally not be granted for:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Change of mind after delivery.</li>
            <li>Incorrect orders placed by the customer.</li>
            <li>Products damaged through misuse or negligence.</li>
            <li>Customized or specially ordered products.</li>
            <li>Completed professional services.</li>
            <li>Digital products or downloadable materials once accessed, unless otherwise required by law.</li>
        </ul>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">10. Refund Request Procedure</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Customers requesting a refund should contact us using the official support channels.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Please provide:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Full Name</li>
            <li>Order Number or Reference Number</li>
            <li>Contact Information</li>
            <li>Reason for the request</li>
            <li>Supporting photographs or documentation where applicable</li>
        </ul>
        <p style="color:#444; line-height:1.8; font-size:15px;">Refund requests should be submitted promptly after the issue is identified.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">11. Refund Processing</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Approved refunds will be processed using the original payment method where reasonably practicable.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Processing times may vary depending on:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Banking institutions</li>
            <li>Payment providers</li>
            <li>Transaction verification</li>
            <li>Regulatory requirements</li>
        </ul>
        <p style="color:#444; line-height:1.8; font-size:15px;">Customers will be notified throughout the review process.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">12. Fraud Prevention</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">KINAS GROUP reserves the right to investigate refund requests to prevent fraudulent activity.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Refunds may be declined where:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>False information is provided.</li>
            <li>Fraudulent conduct is suspected.</li>
            <li>Supporting documentation is intentionally misleading.</li>
            <li>The request falls outside this Refund Policy.</li>
        </ul>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">13. Changes to this Policy</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">We reserve the right to update this Refund Policy from time to time.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">The latest version will always be available on our website.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Continued use of our services constitutes acceptance of any revised policy.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">14. Contact Us</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">For refund enquiries, please contact:</p>
        <div style="background:#f9f9f9; padding:20px; border-radius:4px; margin-top:10px;">
            <p style="color:#0A0A0A; line-height:1.8; font-size:15px; font-weight:600;">KINAS GROUP OF COMPANIES LIMITED</p>
            <p style="color:#444; line-height:1.8; font-size:15px;">RC Number: 7997266</p>
            <p style="color:#444; line-height:1.8; font-size:15px;">Gwarinpa, 900108, Federal Capital Territory, Nigeria</p>
            <p style="color:#444; line-height:1.8; font-size:15px;">Phone: <a href="tel:+2348107576042" style="color:#C6A43F; text-decoration:none;">+234-810-757-6042</a></p>
            <p style="color:#444; line-height:1.8; font-size:15px;">Email: <a href="mailto:support@kinas-group.com" style="color:#C6A43F; text-decoration:none;">support@kinas-group.com</a></p>
            <p style="color:#444; line-height:1.8; font-size:15px;">Website: <a href="https://kinas-group.com" style="color:#C6A43F; text-decoration:none;">https://kinas-group.com</a></p>
        </div>
    </div>
</section>

<?php include dirname(__DIR__) . '/templates/footer.php'; ?>
