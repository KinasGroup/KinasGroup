<?php
// KINAS GROUP footer — uses the JE luxury footer component
require_once __DIR__ . '/../includes/je-components.php';
// Site-wide toast/confirm modal system (kinasToast / kinasConfirm) — this
// replaces native browser alert()/confirm()/prompt() popups everywhere.
// Pages that also require_once this directly are unaffected: require_once
// resolves by realpath, so it is never loaded twice.
require_once __DIR__ . '/../includes/kinas-ui.php';
je_render_footer('site');
?>

<!-- Shared transparent-header scroll effect (hero pages only) -->
<script src="/assets/js/header-scroll.js"></script>
<!-- Newsletter subscribe forms (footer widget + blog pages) -->
<script src="/assets/js/newsletter.js"></script>

<!-- NOTE: Mobile menu open/close logic lives ONLY in templates/header.php.
     Do not re-add a second listener on #mobileMenuBtn here — having two
     handlers toggle the same drawer causes it to open and instantly
     close again on every tap (looked like the button "did nothing"). -->
</body>
</html>
