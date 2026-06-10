<?php
header('Content-Type: text/plain');
$log = '/tmp/db_debug.log';
if (file_exists($log)) {
    echo file_get_contents($log);
} else {
    echo "No debug log found yet. Visit the homepage first to trigger connection.\n";
}
