<?php
/** Idibia — Delete Address API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$auth_type = 'customer';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$customer_id = (int) $GLOBALS['auth_customer_id'];

if ( ! idibia_ensure_saved_addresses_column() ) {
    error_log( 'Idibia delete-address: saved_addresses column missing and could not be created. ' . $wpdb->last_error );
    wp_send_json_error( [ 'message' => 'Saved addresses are temporarily unavailable. Please try again shortly.' ] );
}

$label = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );

if ( empty( $label ) ) {
    wp_send_json_error( [ 'message' => 'Label is required.' ] );
}

$customer = $wpdb->get_row( $wpdb->prepare( "SELECT saved_addresses FROM `{$wpdb->prefix}sd_customers` WHERE id = %d LIMIT 1", $customer_id ), ARRAY_A );

$addresses = [];
if ( ! empty( $customer['saved_addresses'] ) ) {
    $addresses = json_decode( $customer['saved_addresses'], true ) ?: [];
}

$new_addresses = [];
foreach ( $addresses as $addr ) {
    if ( strcasecmp( $addr['label'], $label ) !== 0 ) {
        $new_addresses[] = $addr;
    }
}

$updated = $wpdb->update(
    $wpdb->prefix . 'sd_customers',
    [ 'saved_addresses' => json_encode( $new_addresses ) ],
    [ 'id' => $customer_id ],
    [ '%s' ],
    [ '%d' ]
);

if ( false === $updated ) {
    error_log( 'Idibia delete-address: update failed for customer ' . $customer_id . '. ' . $wpdb->last_error );
    wp_send_json_error( [ 'message' => 'Failed to delete address.' ] );
}

wp_send_json_success( [ 'message' => 'Address deleted.', 'addresses' => $new_addresses ] );
