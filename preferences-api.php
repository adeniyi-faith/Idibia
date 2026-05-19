<?php
/** Idibia — Notification Preferences API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

$auth_type = 'customer';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$customer_id = (int) $GLOBALS['auth_customer_id'];

if ( $_SERVER['REQUEST_METHOD'] === 'GET' ) {
    $customer = $wpdb->get_row( $wpdb->prepare( "SELECT notification_preferences FROM `{$wpdb->prefix}sd_customers` WHERE id = %d LIMIT 1", $customer_id ), ARRAY_A );

    $preferences = [
        'trip_updates' => true,
        'promotions' => false,
        'email_receipts' => true,
    ];

    if ( ! empty( $customer['notification_preferences'] ) ) {
        $saved_prefs = json_decode( $customer['notification_preferences'], true );
        if ( is_array( $saved_prefs ) ) {
            $preferences = array_merge( $preferences, $saved_prefs );
        }
    }

    wp_send_json_success( [ 'preferences' => $preferences ] );
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'idibia_verify' ) ) {
        http_response_code( 403 );
        wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
    }

    $prefs = [
        'trip_updates' => isset( $_POST['trip_updates'] ) && $_POST['trip_updates'] === 'true',
        'promotions' => isset( $_POST['promotions'] ) && $_POST['promotions'] === 'true',
        'email_receipts' => isset( $_POST['email_receipts'] ) && $_POST['email_receipts'] === 'true',
    ];

    $wpdb->update(
        $wpdb->prefix . 'sd_customers',
        [ 'notification_preferences' => wp_json_encode( $prefs ) ],
        [ 'id' => $customer_id ],
        [ '%s' ],
        [ '%d' ]
    );

    wp_send_json_success( [ 'message' => 'Preferences updated.', 'preferences' => $prefs ] );
}

http_response_code( 405 );
wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
