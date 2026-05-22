<?php
require_once __DIR__ . '/../wp/wp-load.php';
global $wpdb;
$payouts = $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}sd_payouts`", ARRAY_A);
print_r($payouts);
