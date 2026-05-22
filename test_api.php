<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/wp-auth-config.php';
$_GET['action'] = 'get_trip';
$_GET['trip_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'GET';

global $wpdb;
$wpdb = new stdClass();
$wpdb->prefix = 'wp_';
$wpdb->get_row = function($q) { return ['id'=>1, 'status'=>'active']; };
$wpdb->get_results = function($q) { return []; };
$wpdb->prepare = function($q, ...$args) { return $q; };

// Instead of hitting the actual API via HTTP, we just include it
// Note: It checks auth and nonces, so we need to bypass or mock them
