<?php
/**
 * KINAS GROUP — Cookie Policy
 */
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/api/config/constants.php';

$pageTitle = 'Cookie Policy - KINAS GROUP';
$pageDescription = 'Learn how KINAS GROUP OF COMPANIES LIMITED uses cookies on our website.';

include dirname(__DIR__) . '/templates/header.php';
?>

<div class="je-page-header" style="background-image: linear-gradient(135deg, rgba(10,10,10,0.85), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1517433670267-08bbd4be890f?w=2000&q=80'); background-size:cover; background-position:center;">
    <h1>Cookie Policy</h1>
    <p>Understanding how we use cookies on kinas-group.com</p>
</div>

<section style="max-width: 900px; margin: 0 auto; padding: clamp(32px, 8vw, 60px) clamp(16px, 5vw, 30px);">
    <div style="background: #fff; border: 1px solid #e8e8e8; border-radius: 4px; padding: clamp(20px, 6vw, 50px);">
        
        <div style="text-align: center; margin-bottom: 40px;">
            <h2 style="font-family:'Prata',serif; font-size:28px; color:#0A0A0A;">KINAS GROUP OF COMPANIES LIMITED</h2>
            <p style="color:#666; font-size:14px;">RC Number: 7997266</p>
            <p style="color:#666; font-size:14px;">Website: kinas-group.com</p>
        </div>

        <hr style="border: none; border-top: 2px solid #C6A43F; margin: 30px 0;">

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">1. Introduction</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">This Cookie Policy explains how KINAS GROUP OF COMPANIES LIMITED ("KINAS GROUP", "Company", "we", "our", or "us") uses cookies and similar technologies on kinas-group.com.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">By continuing to use our website, you consent to our use of cookies in accordance with this Cookie Policy.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">2. What Are Cookies?</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Cookies are small text files stored on your computer, tablet, or mobile device when you visit a website.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">They help websites function properly, improve user experience, remember preferences, enhance security, and provide website usage analytics.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">3. Types of Cookies We Use</h3>
        
        <p style="color:#444; line-height:1.8; font-size:15px; font-weight:600;">Essential Cookies</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">These cookies are necessary for the operation of the website and cannot be disabled.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">They help with:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Secure login</li>
            <li>User authentication</li>
            <li>Account sessions</li>
            <li>Security protection</li>
            <li>Navigation</li>
        </ul>

        <p style="color:#444; line-height:1.8; font-size:15px; font-weight:600; margin-top:20px;">Performance Cookies</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">These cookies help us understand how visitors interact with our website by collecting anonymous usage information.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">They allow us to improve:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Website speed</li>
            <li>User experience</li>
            <li>Page performance</li>
            <li>Navigation</li>
        </ul>

        <p style="color:#444; line-height:1.8; font-size:15px; font-weight:600; margin-top:20px;">Functional Cookies</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">These cookies remember user preferences such as:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Preferred language</li>
            <li>Saved settings</li>
            <li>Login preferences</li>
            <li>Recently viewed content</li>
        </ul>

        <p style="color:#444; line-height:1.8; font-size:15px; font-weight:600; margin-top:20px;">Security Cookies</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Security cookies help us:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Detect suspicious activity</li>
            <li>Prevent fraud</li>
            <li>Protect user accounts</li>
            <li>Maintain platform security</li>
        </ul>

        <p style="color:#444; line-height:1.8; font-size:15px; font-weight:600; margin-top:20px;">Marketing Cookies</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Where applicable, marketing cookies may be used to:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Display relevant advertisements</li>
            <li>Measure advertising performance</li>
            <li>Improve marketing campaigns</li>
        </ul>
        <p style="color:#444; line-height:1.8; font-size:15px;">We will only use these cookies where legally permitted.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">4. Why We Use Cookies</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Cookies help us:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Improve website functionality</li>
            <li>Maintain secure login sessions</li>
            <li>Protect user accounts</li>
            <li>Remember preferences</li>
            <li>Improve customer experience</li>
            <li>Measure website performance</li>
            <li>Improve our services</li>
        </ul>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">5. Third-Party Cookies</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Certain third-party services integrated into our website may place cookies on your device.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">These may include:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Google Analytics</li>
            <li>Google Maps</li>
            <li>Social media platforms</li>
            <li>Payment gateways</li>
            <li>Email services</li>
            <li>SMS verification providers</li>
        </ul>
        <p style="color:#444; line-height:1.8; font-size:15px;">These providers operate under their own privacy and cookie policies.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">6. Managing Cookies</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Most web browsers allow users to:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Accept cookies</li>
            <li>Reject cookies</li>
            <li>Delete stored cookies</li>
            <li>Block certain cookies</li>
        </ul>
        <p style="color:#444; line-height:1.8; font-size:15px;">Please note that disabling essential cookies may affect website functionality and prevent access to some services.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">7. Updates to This Cookie Policy</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">We may update this Cookie Policy periodically to reflect changes in technology, legal requirements, or our business operations.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">The latest version will always be published on our website.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">8. Contact Us</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">If you have any questions regarding this Cookie Policy, please contact:</p>
        <div style="background:#f9f9f9; padding:20px; border-radius:4px; margin-top:10px;">
            <p style="color:#0A0A0A; line-height:1.8; font-size:15px; font-weight:600;">KINAS GROUP OF COMPANIES LIMITED</p>
            <p style="color:#444; line-height:1.8; font-size:15px;">RC Number: 7997266</p>
            <p style="color:#444; line-height:1.8; font-size:15px;">Gwarinpa, 900108, Federal Capital Territory, Nigeria</p>
            <p style="color:#444; line-height:1.8; font-size:15px;">Phone: <a href="tel:+2348107576042" style="color:#C6A43F; text-decoration:none;">+234 810 757 6042</a></p>
            <p style="color:#444; line-height:1.8; font-size:15px;">Email: <a href="mailto:support@kinas-group.com" style="color:#C6A43F; text-decoration:none;">support@kinas-group.com</a></p>
            <p style="color:#444; line-height:1.8; font-size:15px;">Website: <a href="https://kinas-group.com" style="color:#C6A43F; text-decoration:none;">https://kinas-group.com</a></p>
        </div>
    </div>
</section>

<?php include dirname(__DIR__) . '/templates/footer.php'; ?>
