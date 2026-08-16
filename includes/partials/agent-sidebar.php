<?php
require_once __DIR__ . '/../../includes/je-sidebar.php';
require_once __DIR__ . '/../../includes/public-identity.php';
$currentPage = basename($_SERVER['PHP_SELF']);
je_render_sidebar('agent', $currentPage, 2);
?>
