<?php
require_once __DIR__ . '/../../includes/je-sidebar.php';
$currentPage = basename($_SERVER['PHP_SELF']);
je_render_sidebar('admin', $currentPage, 2);
?>
