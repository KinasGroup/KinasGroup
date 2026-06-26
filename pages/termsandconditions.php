<?php
/**
 * KINAS GROUP — Terms & Conditions
 */
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/api/config/constants.php';

$pageTitle = 'Terms & Conditions - KINAS GROUP';
$pageDescription = 'Read the Terms & Conditions governing purchases and transactions with KINAS GROUP OF COMPANIES LIMITED.';

include dirname(__DIR__) . '/templates/header.php';
?>

<div class="je-page-header" style="background-image: linear-gradient(135deg, rgba(10,10,10,0.85), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=2000&q=80'); background-size:cover; background-position:center;">
    <h1>Terms & Conditions</h1>
    <p>Governing purchases, bookings, and commercial activities</p>
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
        <p style="color:#444; line-height:1.8; font-size:15px;">These Terms & Conditions govern the purchase of products, booking of services, enquiries, transactions, and all commercial activities conducted through KINAS GROUP OF COMPANIES LIMITED ("KINAS GROUP", "Company", "we", "our", or "us").</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">By using this website or engaging with any of our business divisions, you acknowledge that you have read, understood, and agreed to these Terms & Conditions.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">2. Scope</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">These Terms apply to all products and services offered through:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>KINAS Automobile</li>
            <li>Williams Connect Home</li>
            <li>KINAS Volt</li>
            <li>KINAS Marketplace</li>
        </ul>
        <p style="color:#444; line-height:1.8; font-size:15px;">Additional division-specific terms may apply where necessary.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">3. Products & Services</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">All products and services displayed on the website are subject to availability.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">KINAS GROUP reserves the right to:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Modify product specifications.</li>
            <li>Update prices.</li>
            <li>Discontinue products or services.</li>
            <li>Correct pricing or content errors without prior notice.</li>
        </ul>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">4. Pricing</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">All prices displayed are subject to change without notice unless otherwise agreed in writing.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Taxes, delivery charges, installation fees, or other applicable charges may be added where required.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">5. Orders & Enquiries</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Submitting an enquiry or order request does not automatically create a binding agreement.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Orders are only confirmed after review and acceptance by KINAS GROUP or the relevant business division.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">6. Payments</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Payments must be made using approved payment methods.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Where applicable:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Deposits may be required before processing.</li>
            <li>Outstanding balances must be settled before delivery or service completion.</li>
            <li>Payment confirmation may be required before an order proceeds.</li>
        </ul>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">7. Property Transactions</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Property listings are provided for informational purposes.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Availability, pricing, and specifications may change without prior notice.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Final transactions remain subject to contractual agreements between the relevant parties.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">8. Vehicle Transactions</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Vehicle specifications, mileage, features, pricing, and availability may change without prior notice.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Customers are encouraged to inspect vehicles before completing any purchase.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">9. Solar Products & Installations</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Solar system recommendations are based on information supplied by the customer.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Actual performance may vary depending on installation conditions, usage patterns, weather, and other technical factors.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">10. Marketplace Products</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Products sold through KINAS Marketplace may be supplied directly by KINAS GROUP or approved vendors.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Product images are for illustration purposes and may differ slightly from the final delivered product.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">11. Warranties</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">Manufacturer warranties apply where provided.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Additional warranties may be offered depending on the product or service.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Warranty claims may require proof of purchase and inspection.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">12. Limitation of Liability</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">To the maximum extent permitted by law, KINAS GROUP shall not be liable for indirect, incidental, special, or consequential damages arising from:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Product delays</li>
            <li>Third-party actions</li>
            <li>Technical failures</li>
            <li>Service interruptions</li>
            <li>Force majeure events</li>
            <li>Incorrect information supplied by users</li>
        </ul>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">13. Force Majeure</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">KINAS GROUP shall not be responsible for delays or failure to perform obligations caused by events beyond its reasonable control, including:</p>
        <ul style="color:#444; line-height:1.8; font-size:15px; padding-left:20px;">
            <li>Natural disasters</li>
            <li>Government actions</li>
            <li>Power failures</li>
            <li>Internet outages</li>
            <li>Labour disputes</li>
            <li>Transportation disruptions</li>
        </ul>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">14. Intellectual Property</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">All trademarks, logos, graphics, photographs, text, software, and other materials on this website remain the exclusive property of KINAS GROUP OF COMPANIES LIMITED unless otherwise stated.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">No material may be copied or reproduced without prior written permission.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">15. Amendments</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">KINAS GROUP reserves the right to amend these Terms & Conditions at any time.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Updated versions become effective immediately upon publication on the website.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">16. Governing Law</h3>
        <p style="color:#444; line-height:1.8; font-size:15px;">These Terms & Conditions shall be governed by the laws of the Federal Republic of Nigeria.</p>
        <p style="color:#444; line-height:1.8; font-size:15px;">Any disputes shall be subject to the exclusive jurisdiction of the competent courts of Nigeria.</p>

        <h3 style="font-family:'Prata',serif; font-size:20px; color:#0A0A0A; margin-top:30px;">17. Contact Information</h3>
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
