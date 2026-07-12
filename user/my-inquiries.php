<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * KINAS GROUP — My Inquiries (Redirected to Messages)
 * This page has been replaced by /user/messages.php
 */
require_once __DIR__ . '/../includes/session.php';
SessionManager::requireLogin();

// Redirect to messages page
header('Location: /user/messages.php');
exit;
