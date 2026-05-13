<?php
/**
 * Idibia — Resend Verification Code Handler
 * Endpoint: POST /resend-code.php
 * Returns:  JSON { success, message }
 *
 * Flow:
 *  1. Pull pending customer_id from PHP session
 *  2. Check they're not already verified
 *  3. Enforce a 60-second cooldown (via verify_expires proximity check)
 *  4. Generate a fresh 5-digit code with a new 30-minute expiry
 *  5. Update the DB row
 *  6. Send the email again
 */

define( 'WP_USE_THEMES', false );
require_once __DIR__ . '/wp/wp-load.php';

// ── Only accept POST ──────────────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

// ── Pull session ──────────────────────────────────────────────────────────────
if ( ! session_id() ) session_start();

$customer_id = isset( $_SESSION['sd_pending_customer_id'] ) ? (int) $_SESSION['sd_pending_customer_id'] : 0;

if ( ! $customer_id ) {
    wp_send_json_error( [ 'message' => 'Session expired. Please register again.' ] );
}

// ── Fetch customer ────────────────────────────────────────────────────────────
global $wpdb;
$table = $wpdb->prefix . 'sd_customers';

$customer = $wpdb->get_row( $wpdb->prepare(
    "SELECT id, full_name, email, email_verified, verify_expires
     FROM `$table`
     WHERE id = %d LIMIT 1",
    $customer_id
) );

if ( ! $customer ) {
    wp_send_json_error( [ 'message' => 'Account not found. Please register again.' ] );
}

// ── Already verified — nothing to resend ─────────────────────────────────────
if ( $customer->email_verified ) {
    wp_send_json_error( [ 'message' => 'This account is already verified. Please log in.' ] );
}

// ── Cooldown: don't allow resend if last code was issued less than 60s ago ───
// verify_expires is set 30 min from issue time, so "issued at" = expires - 30 min
if ( $customer->verify_expires ) {
    $issued_at = strtotime( $customer->verify_expires ) - ( 30 * MINUTE_IN_SECONDS );
    $cooldown  = 60; // seconds
    if ( time() < $issued_at + $cooldown ) {
        $wait = ( $issued_at + $cooldown ) - time();
        wp_send_json_error( [ 'message' => "Please wait $wait second(s) before requesting another code." ] );
    }
}

// ── Generate fresh code ───────────────────────────────────────────────────────
$new_code       = (string) wp_rand( 10000, 99999 );
$new_expires    = gmdate( 'Y-m-d H:i:s', time() + ( 30 * MINUTE_IN_SECONDS ) );

$updated = $wpdb->update(
    $table,
    [
        'verify_code'    => $new_code,
        'verify_expires' => $new_expires,
    ],
    [ 'id' => $customer_id ],
    [ '%s', '%s' ],
    [ '%d' ]
);

if ( false === $updated ) {
    error_log( "Idibia: resend-code failed DB update for customer_id=$customer_id" );
    wp_send_json_error( [ 'message' => 'Could not generate a new code. Please try again.' ] );
}

// ── Send the email ────────────────────────────────────────────────────────────
$first_name = explode( ' ', $customer->full_name )[0];
$site_name  = get_bloginfo( 'name' ) ?: 'Idibia';
$site_url   = home_url();

$subject = "[$site_name] Your new verification code is $new_code";

$body = "Hi $first_name,\n\n"
      . "You requested a new verification code. Enter it in the app:\n\n"
      . "    $new_code\n\n"
      . "This code expires in 30 minutes.\n\n"
      . "If you did not request this, you can ignore this email.\n\n"
      . "— The Idibia Team\n"
      . $site_url;

$headers = [
    'Content-Type: text/plain; charset=UTF-8',
    'From: ' . $site_name . ' <no-reply@' . parse_url( $site_url, PHP_URL_HOST ) . '>',
];

$mail_sent = wp_mail( $customer->email, $subject, $body, $headers );

if ( ! $mail_sent ) {
    error_log( "Idibia: resend wp_mail failed for customer_id=$customer_id email={$customer->email}" );
    // Code is updated in DB — not a total failure, but flag it
    wp_send_json_error( [ 'message' => 'Could not send the email. Check your address and try again.' ] );
}

wp_send_json_success( [ 'message' => 'New code sent! Check your inbox.' ] );
