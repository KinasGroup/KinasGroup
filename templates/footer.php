<?php
// KINAS GROUP footer — uses the JE luxury footer component
require_once __DIR__ . '/../includes/je-components.php';
je_render_footer('site');
?>

<!-- Shared transparent-header scroll effect (hero pages only) -->
<script src="/assets/js/header-scroll.js"></script>

<!-- NOTE: Mobile menu open/close logic lives ONLY in templates/header.php.
     Do not re-add a second listener on #mobileMenuBtn here — having two
     handlers toggle the same drawer causes it to open and instantly
     close again on every tap (looked like the button "did nothing"). -->
</body>
</html>
