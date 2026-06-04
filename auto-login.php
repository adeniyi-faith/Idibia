<?php
/**
 * Idibia — Token-based auto-login redirect.
 * Called after OTP verification: validates the custom session token,
 * establishes the WordPress auth cookie via a real page load (not AJAX),
 * then redirects to the appropriate destination.
 */

ob_start();
require_once __DIR__ . '/wp-auth-config.php';

$token       = sanitize_text_field( $_GET['token'] ?? '' );
$destination = sanitize_text_field( $_GET['to']    ?? 'dashboard.php' );

// Whitelist allowed destinations to prevent open redirect.
$allowed = [ 'dashboard.php', 'driver.php' ];
if ( ! in_array( $destination, $allowed, true ) ) {
    $destination = 'dashboard.php';
}

if ( ! $token ) {
    header( 'Location: index.php' );
    exit;
}

global $wpdb;

// Validate the token against wp_sd_sessions (customer).
$session = $wpdb->get_row( $wpdb->prepare(
    "SELECT customer_id, expires_at FROM `{$wpdb->prefix}sd_sessions`
     WHERE token = %s LIMIT 1",
    $token
) );

if ( $session && strtotime( $session->expires_at ) > time() ) {
    $customer_id = (int) $session->customer_id;

    // Find the WordPress user for this customer.
    $wp_user_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT meta_value FROM `{$wpdb->prefix}usermeta`
         WHERE meta_key = 'idibia_customer_id' AND meta_value = %d LIMIT 1",
        $customer_id
    ) );

    if ( ! $wp_user_id ) {
        // Fallback: look up via the customers table email.
        $email = $wpdb->get_var( $wpdb->prepare(
            "SELECT email FROM `{$wpdb->prefix}sd_customers` WHERE id = %d LIMIT 1",
            $customer_id
        ) );
        if ( $email ) {
            $user = get_user_by( 'email', $email );
            if ( $user ) {
                $wp_user_id = $user->ID;
            }
        }
    } else {
        $user = get_userdata( $wp_user_id );
    }

    if ( ! empty( $user ) && $user instanceof WP_User ) {
        idibia_finish_wordpress_login( $user );
    }
}

idibia_clean_json_buffer();
header( 'Location: ' . $destination );
exit;
