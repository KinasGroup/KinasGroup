<?php
/**
 * Admin: Index
 *
 * This file was previously an exact duplicate of blog/index.php
 * (copy-paste mistake). /admin/ has no landing page of its own —
 * route straight to the dashboard (which itself enforces admin login).
 */
require_once __DIR__ . '/../includes/session.php';
header('Location: /admin/dashboard.php');
exit;
