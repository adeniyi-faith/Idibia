<?php
/**
 * Idibia — Registration Handler
 * Endpoint: POST /register-handler.php
 * Returns:  JSON { success, message, data? }
 */

define( 'WP_USE_THEMES', false );
require_once __DIR__ . '/wp/wp-load.php';

// ── Only accept POST ──────────────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

// ── CSRF: verify nonce ────────────────────────────────────────────────────────
$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : '';
if ( ! wp_verify_nonce( $nonce, 'idibia_register' ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
}

// ── Sanitise inputs ───────────────────────────────────────────────────────────
$full_name = sanitize_text_field( $_POST['full_name'] ?? '' );
$phone     = sanitize_text_field( $_POST['phone']     ?? '' );
$email     = sanitize_email(      $_POST['email']     ?? '' );
$password  = $_POST['password'] ?? '';
$terms     = ! empty( $_POST['terms'] );

// ── Validation ────────────────────────────────────────────────────────────────
$errors = [];

if ( strlen( $full_name ) < 2 ) {
    $errors[] = 'Please enter your full name.';
}

if ( ! is_email( $email ) ) {
    $errors[] = 'Enter a valid email address.';
}

if ( ! preg_match( '/^(\+?234|0)[789][01]\d{8}$/', preg_replace( '/[\s\-()]/', '', $phone ) ) ) {
    $errors[] = 'Enter a valid Nigerian phone number (e.g. 08012345678).';
}

if ( strlen( $password ) < 8 ) {
    $errors[] = 'Password must be at least 8 characters.';
}

if ( ! $terms ) {
    $errors[] = 'You must agree to the Terms of Service to continue.';
}

if ( $errors ) {
    wp_send_json_error( [ 'message' => implode( ' ', $errors ) ] );
}

// ── Check for duplicate email ─────────────────────────────────────────────────
global $wpdb;
$table = $wpdb->prefix . 'sd_customers';

$exists = $wpdb->get_var( $wpdb->prepare(
    "SELECT id FROM `$table` WHERE email = %s LIMIT 1",
    $email
) );

if ( $exists ) {
    wp_send_json_error( [ 'message' => 'An account with that email already exists. Try logging in.' ] );
}

// ── Generate verify code + referral code ──────────────────────────────────────
$verify_code    = (string) wp_rand( 10000, 99999 );
$verify_expires = gmdate( 'Y-m-d H:i:s', time() + ( 30 * MINUTE_IN_SECONDS ) );
$referral_code  = 'IDIBIA-' . strtoupper( substr( md5( $email . time() ), 0, 5 ) );
$password_hash  = wp_hash_password( $password );

// ── Insert customer ───────────────────────────────────────────────────────────
$inserted = $wpdb->insert(
    $table,
    [
        'full_name'      => $full_name,
        'email'          => $email,
        'phone'          => $phone,
        'password_hash'  => $password_hash,
        'verify_code'    => $verify_code,
        'verify_expires' => $verify_expires,
        'referral_code'  => $referral_code,
        'status'         => 'pending',
    ],
    [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
);

if ( ! $inserted ) {
    wp_send_json_error( [ 'message' => 'Something went wrong. Please try again.' ] );
}

$customer_id = $wpdb->insert_id;

// ── Send verification email ───────────────────────────────────────────────────
$first_name = explode( ' ', $full_name )[0];
$site_name  = get_bloginfo( 'name' ) ?: 'Idibia';
$site_url   = home_url();

$subject = "[$site_name] Your verification code is $verify_code";

$body = "Hi $first_name,\n\n"
      . "Welcome to Idibia! To activate your account, enter the code below in the app:\n\n"
      . "    $verify_code\n\n"
      . "This code expires in 30 minutes.\n\n"
      . "If you did not create an Idibia account, please ignore this email.\n\n"
      . "— The Idibia Team\n"
      . $site_url;

$headers = [
    'Content-Type: text/plain; charset=UTF-8',
    'From: ' . $site_name . ' <no-reply@' . parse_url( $site_url, PHP_URL_HOST ) . '>',
];

$mail_sent = wp_mail( $email, $subject, $body, $headers );

if ( ! $mail_sent ) {
    error_log( "Idibia: wp_mail failed for customer_id=$customer_id email=$email" );
}

// ── Store pending session data ────────────────────────────────────────────────
if ( ! session_id() ) session_start();
$_SESSION['sd_pending_customer_id'] = $customer_id;
$_SESSION['sd_pending_email']        = $email;

// ── Respond ───────────────────────────────────────────────────────────────────
$masked_email = preg_replace( '/(?<=.{2}).(?=.*@)/u', '*', $email );

wp_send_json_success( [
    'message'      => 'Account created! Check your email for the verification code.',
    'masked_email' => $masked_email,
    'first_name'   => $first_name,
] );
