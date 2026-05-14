<?php
/**
 * Idibia — Email Verification Handler
 * Endpoint: POST /verify-handler.php
 * Expects:  { code: "12345" }  (the 5-digit code from the OTP inputs)
 * Returns:  JSON { success, message, data? }
 *
 * Flow:
 *  1. Pull pending customer_id from PHP session (set by register-handler.php)
 *  2. Look up their row in wp_sd_customers
 *  3. Validate: code matches + not expired + not already verified
 *  4. Mark email_verified = 1, status = 'active', clear the code
 *  5. Create a session token in wp_sd_sessions
 *  6. Return success + first_name so the frontend can personalise the welcome toast
 */

require_once __DIR__ . '/wp-auth-config.php';

// ── Only accept POST ──────────────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

// ── CSRF: verify nonce ────────────────────────────────────────────────────────
$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : '';
if ( ! wp_verify_nonce( $nonce, 'idibia_verify' ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
}

// ── Start session and pull pending customer ───────────────────────────────────
if ( ! session_id() ) session_start();

$customer_id = isset( $_SESSION['sd_pending_customer_id'] ) ? (int) $_SESSION['sd_pending_customer_id'] : 0;

if ( ! $customer_id ) {
    wp_send_json_error( [ 'message' => 'Session expired. Please register again.' ] );
}

// ── Get the submitted code ────────────────────────────────────────────────────
$submitted_code = sanitize_text_field( $_POST['code'] ?? '' );

if ( ! preg_match( '/^\d{5}$/', $submitted_code ) ) {
    wp_send_json_error( [ 'message' => 'Enter the 5-digit code from your email.' ] );
}

// ── Fetch the customer row ────────────────────────────────────────────────────
global $wpdb;
$table = $wpdb->prefix . 'sd_customers';

$customer = $wpdb->get_row( $wpdb->prepare(
    "SELECT id, full_name, email, verify_code, verify_expires, email_verified, status
     FROM `$table`
     WHERE id = %d LIMIT 1",
    $customer_id
) );

if ( ! $customer ) {
    wp_send_json_error( [ 'message' => 'Account not found. Please register again.' ] );
}

// ── Already verified? ─────────────────────────────────────────────────────────
if ( $customer->email_verified ) {
    // Clean up session and let them through
    unset( $_SESSION['sd_pending_customer_id'], $_SESSION['sd_pending_email'] );
    $token = _idibia_create_session( $customer->id );
    wp_send_json_success( [
        'message'    => 'Already verified.',
        'first_name' => idibia_split_full_name( $customer->full_name )[0],
        'token'      => $token,
    ] );
}

// ── Code match ───────────────────────────────────────────────────────────────
if ( $customer->verify_code !== $submitted_code ) {
    wp_send_json_error( [ 'message' => 'Incorrect code. Double-check and try again.' ] );
}

// ── Expiry check ─────────────────────────────────────────────────────────────
$expires = strtotime( $customer->verify_expires );
if ( ! $expires || time() > $expires ) {
    wp_send_json_error( [ 'message' => 'That code has expired. Use the Resend Email button to get a fresh one.' ] );
}

// ── Mark verified + activate account ─────────────────────────────────────────
$updated = $wpdb->update(
    $table,
    [
        'email_verified' => 1,
        'status'         => 'active',
        'verify_code'    => null,
        'verify_expires' => null,
    ],
    [ 'id' => $customer_id ],
    [ '%d', '%s', '%s', '%s' ],
    [ '%d' ]
);

if ( false === $updated ) {
    error_log( "Idibia: failed to mark verified for customer_id=$customer_id" );
    wp_send_json_error( [ 'message' => 'Something went wrong. Please try again.' ] );
}

// ── Create a session token ────────────────────────────────────────────────────
$token = _idibia_create_session( $customer_id );

// ── Clean up the pending session keys ────────────────────────────────────────
unset( $_SESSION['sd_pending_customer_id'], $_SESSION['sd_pending_email'] );

// ── Respond ───────────────────────────────────────────────────────────────────
$first_name = idibia_split_full_name( $customer->full_name )[0];

wp_send_json_success( [
    'message'    => 'Email verified! Welcome to Idibia.',
    'first_name' => $first_name,
    'token'      => $token,
] );


/**
 * Creates a session row in wp_sd_sessions and returns the token.
 * Token is a 64-character hex string (32 random bytes).
 */
function _idibia_create_session( int $customer_id ): string {
    global $wpdb;

    $token      = bin2hex( random_bytes( 32 ) );
    $expires_at = gmdate( 'Y-m-d H:i:s', time() + ( 30 * DAY_IN_SECONDS ) );

    $ip         = sanitize_text_field( $_SERVER['REMOTE_ADDR']     ?? '' );
    $user_agent = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' );
    if ( strlen( $user_agent ) > 300 ) {
        $user_agent = substr( $user_agent, 0, 300 );
    }

    $wpdb->insert(
        $wpdb->prefix . 'sd_sessions',
        [
            'customer_id' => $customer_id,
            'token'       => $token,
            'expires_at'  => $expires_at,
            'ip'          => $ip,
            'user_agent'  => $user_agent,
        ],
        [ '%d', '%s', '%s', '%s', '%s' ]
    );

    return $token;
}
