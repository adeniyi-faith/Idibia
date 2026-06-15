<?php
/** Idibia — Get Addresses API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$auth_type = 'customer';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$customer_id = (int) $GLOBALS['auth_customer_id'];

if ( ! idibia_ensure_saved_addresses_column() ) {
    // Column unavailable — treat as no saved addresses rather than erroring.
    wp_send_json_success( [ 'addresses' => [] ] );
}

$customer = $wpdb->get_row( $wpdb->prepare( "SELECT saved_addresses FROM `{$wpdb->prefix}sd_customers` WHERE id = %d LIMIT 1", $customer_id ), ARRAY_A );

$addresses = [];
if ( ! empty( $customer['saved_addresses'] ) ) {
    $addresses = json_decode( $customer['saved_addresses'], true ) ?: [];
}

wp_send_json_success( [ 'addresses' => $addresses ] );
