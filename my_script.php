<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/wp/wp-load.php';

global $wpdb;

$tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
foreach($tables as $t) {
    if (strpos($t[0], 'sd_') !== false) {
        echo $t[0] . "\n";
    }
}
