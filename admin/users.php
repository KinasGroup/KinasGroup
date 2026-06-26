<?php
/**
 * Admin: Users Management
 *
 * This page was a duplicate of user-management.php (same purpose,
 * older/simpler implementation). Consolidated into one canonical
 * Users page; this file now just redirects so old links/bookmarks
 * still work.
 */
require_once __DIR__ . '/../includes/session.php';
header('Location: /admin/user-management.php');
exit;
