<?php
/**
 * KINAS GROUP — Frequently Asked Questions (FAQ)
 */
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/api/config/constants.php';

$pageTitle = 'FAQ - KINAS GROUP';
$pageDescription = 'Frequently asked questions about KINAS GROUP OF COMPANIES LIMITED and our services.';

include dirname(__DIR__) . '/templates/header.php';
?>

<div class="je-page-header" style="background-image: linear-gradient(135deg, rgba(10,10,10,0.85), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=2000&q=80'); background-size:cover; background-position:center;">
    <h1>Frequently Asked Questions</h1>
    <p>Quick answers to the most common questions about KINAS GROUP</p>
</div>

<section style="max-width: 900px; margin: 0 auto; padding: clamp(32px, 8vw, 60px) clamp(16px, 5vw, 30px);">
    <div style="background: #fff; border: 1px solid #e8e8e8; border-radius: 4px; padding: clamp(20px, 6vw, 50px);">
        
        <h2 style="font-family:'Prata',serif; font-size:24px; color:#0A0A0A; margin-bottom:30px; text-align:center;">General Questions</h2>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">What is KINAS GROUP OF COMPANIES LIMITED?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">KINAS GROUP OF COMPANIES LIMITED is a diversified corporate group operating across multiple industries, including Real Estate, Automobiles, Renewable Energy, Hospitality, Global Trade, and Commerce.</p>
        </div>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">What services do you provide?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Our business divisions include:</p>
            <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
                <li>KINAS Automobile</li>
                <li>Williams Connect Home</li>
                <li>KINAS Volt</li>
                <li>KINAS Marketplace</li>
            </ul>
            <p style="color:#444; line-height:1.8; font-size:15px;">Each division offers specialized products and services while operating under one trusted corporate ecosystem.</p>
        </div>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">Where is your office located?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Gwarinpa, 900108, Federal Capital Territory, Nigeria.</p>
        </div>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">How can I contact customer support?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Phone: <a href="tel:+2349137175523" style="color:#C6A43F; text-decoration:none;">+234 913 717 5523</a></p>
            <p style="color:#444; line-height:1.8; font-size:15px;">Email: <a href="mailto:support@kinas-group.com" style="color:#C6A43F; text-decoration:none;">support@kinas-group.com</a></p>
            <p style="color:#444; line-height:1.8; font-size:15px;">Business Hours: 24 Hours / 7 Days</p>
        </div>

        <h2 style="font-family:'Prata',serif; font-size:24px; color:#0A0A0A; margin: 40px 0 30px; text-align:center;">Account & Registration</h2>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">Do I need to create an account?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Some services require registration, while others may be accessed without an account.</p>
        </div>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">Why do I need to verify my account?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Verification helps protect our users, reduce fraud, and maintain the integrity of the KINAS GROUP platform.</p>
        </div>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">What documents may be required for verification?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Depending on the service, you may be asked to provide:</p>
            <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
                <li>Government-issued ID</li>
                <li>Phone number verification (OTP)</li>
                <li>Email verification</li>
                <li>Proof of address</li>
            </ul>
        </div>

        <h2 style="font-family:'Prata',serif; font-size:24px; color:#0A0A0A; margin: 40px 0 30px; text-align:center;">Williams Connect Home</h2>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">Can I list my property?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Yes. Property owners and approved agents may submit properties for review before publication.</p>
        </div>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">Does KINAS GROUP own all listed properties?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">No. Listings may be owned by KINAS GROUP, partners, developers, agencies, or individual property owners unless otherwise stated.</p>
        </div>

        <h2 style="font-family:'Prata',serif; font-size:24px; color:#0A0A0A; margin: 40px 0 30px; text-align:center;">KINAS Automobile</h2>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">Can I purchase vehicles through the website?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Yes. Available vehicles can be viewed online, and our team will assist you throughout the purchasing process.</p>
        </div>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">Do you offer vehicle sourcing?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Yes. Subject to availability, we can assist clients in sourcing specific vehicle models.</p>
        </div>

        <h2 style="font-family:'Prata',serif; font-size:24px; color:#0A0A0A; margin: 40px 0 30px; text-align:center;">KINAS Volt</h2>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">What solar products do you supply?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">We supply:</p>
            <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
                <li>Solar Panels</li>
                <li>Inverters</li>
                <li>Lithium Batteries</li>
                <li>Solar Street Lights</li>
                <li>Solar Security Cameras</li>
                <li>Complete Solar Systems</li>
                <li>Installation Services</li>
            </ul>
        </div>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">Do you provide installation?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Yes. Installation services are available for qualifying products and projects.</p>
        </div>

        <h2 style="font-family:'Prata',serif; font-size:24px; color:#0A0A0A; margin: 40px 0 30px; text-align:center;">KINAS Marketplace</h2>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">What products are available?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">The marketplace offers a variety of products, including:</p>
            <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
                <li>Smart Security Products</li>
                <li>Solar Products</li>
                <li>Electronics</li>
                <li>Lighting Solutions</li>
                <li>Building Materials</li>
                <li>Imported Products</li>
                <li>Commercial Merchandise</li>
            </ul>
        </div>

        <h2 style="font-family:'Prata',serif; font-size:24px; color:#0A0A0A; margin: 40px 0 30px; text-align:center;">Payments</h2>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">Which payment methods do you accept?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Accepted payment methods will be displayed during checkout or communicated by our sales team where applicable.</p>
        </div>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">Are deposits refundable?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Refund eligibility depends on the product or service purchased. Please refer to our <a href="refundpolicy.php" style="color:#C6A43F; text-decoration:none;">Refund Policy</a>.</p>
        </div>

        <h2 style="font-family:'Prata',serif; font-size:24px; color:#0A0A0A; margin: 40px 0 30px; text-align:center;">Security</h2>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">Is my information secure?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Yes. We implement industry-standard security measures to protect your personal information and transactions.</p>
        </div>

        <h2 style="font-family:'Prata',serif; font-size:24px; color:#0A0A0A; margin: 40px 0 30px; text-align:center;">Business Enquiries</h2>

        <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">Can I become a partner or agent?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Yes. We welcome partnership opportunities and qualified agents across our business divisions.</p>
            <p style="color:#444; line-height:1.8; font-size:15px;">Please contact us for further information.</p>
        </div>

        <h2 style="font-family:'Prata',serif; font-size:24px; color:#0A0A0A; margin: 40px 0 30px; text-align:center;">Careers</h2>

        <div style="margin-bottom: 30px;">
            <h4 style="font-size:17px; color:#0A0A0A; margin-bottom:10px;">How do I apply for a job?</h4>
            <p style="color:#444; line-height:1.8; font-size:15px;">Career opportunities are published periodically.</p>
            <p style="color:#444; line-height:1.8; font-size:15px;">Applications may be submitted to:</p>
            <p style="color:#444; line-height:1.8; font-size:15px;"><a href="mailto:support@kinas-group.com" style="color:#C6A43F; text-decoration:none;">support@kinas-group.com</a></p>
            <p style="color:#444; line-height:1.8; font-size:15px;">Please include the position you are applying for in the email subject line.</p>
        </div>

        <div style="background:#f9f9f9; padding:20px; border-radius:4px; margin-top:20px;">
            <p style="color:#444; line-height:1.8; font-size:15px; text-align:center;">Still have questions? <a href="contact.php" style="color:#C6A43F; text-decoration:none; font-weight:600;">Contact us</a></p>
        </div>
    </div>
</section>

<?php include dirname(__DIR__) . '/templates/footer.php'; ?>
