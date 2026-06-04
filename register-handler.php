<?php ob_start();
/** Idibia — Customer Registration Handler */

require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';

$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
if ( ! idibia_check_rate_limit( 'register', $ip, 5, 300 ) ) {
    http_response_code( 429 );
    wp_send_json_error( [ 'message' => 'Too many requests. Please try again later.' ] );
}


if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
    idibia_clean_json_buffer();
    http_response_code( 204 );
    die();
}

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    idibia_clean_json_buffer();
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

idibia_clean_json_buffer();



function idibia_wants_json_response(): bool {
    $accept = strtolower( (string) ( $_SERVER['HTTP_ACCEPT'] ?? '' ) );
    return strpos( $accept, 'application/json' ) !== false || strtolower( (string) ( $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '' ) ) === 'xmlhttprequest';
}

$full_name     = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
$phone         = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
$email         = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
$password      = (string) ( $_POST['password'] ?? '' );
$terms         = ! empty( $_POST['terms'] );
$referral_code = strtoupper( sanitize_text_field( wp_unslash( $_POST['referral_code'] ?? '' ) ) );
$phone         = preg_replace( '/[\s\-()]/', '', $phone );
$errors        = [];

if ( strlen( $full_name ) < 2 ) $errors[] = 'Please enter your full name.';
if ( ! is_email( $email ) ) $errors[] = 'Enter a valid email address.';
if ( ! preg_match( '/^(\+?234|0)[789][01]\d{8}$/', $phone ) ) $errors[] = 'Enter a valid Nigerian phone number (e.g. 08012345678).';
if ( strlen( $password ) < 6 ) $errors[] = 'Password must be at least 6 characters.';
if ( ! $terms ) $errors[] = 'You must agree to the Terms of Service to continue.';

if ( $errors ) {
    wp_send_json_error( [ 'message' => implode( ' ', $errors ) ] );
}

// Validate referral code if provided
$referrer_customer_id = null;
if ( $referral_code !== '' ) {
    global $wpdb;
    $referrer_customer_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM `{$wpdb->prefix}sd_customers` WHERE referral_code = %s AND status = 'active' LIMIT 1",
        $referral_code
    ) );
    if ( ! $referrer_customer_id ) {
        wp_send_json_error( [ 'message' => 'Invalid referral code. Please check and try again.' ] );
    }
    $referrer_customer_id = (int) $referrer_customer_id;
}

if ( username_exists( $phone ) ) {
    wp_send_json_error( [ 'message' => 'Phone already registered. Try logging in.' ] );
}

if ( email_exists( $email ) ) {
    wp_send_json_error( [ 'message' => 'Email already in use. Try logging in.' ] );
}

[ $first_name, $last_name ] = idibia_split_full_name( $full_name );
$user_id = wp_insert_user( [
    'user_login'   => $phone,
    'user_pass'    => $password,
    'user_email'   => $email,
    'first_name'   => $first_name,
    'last_name'    => $last_name,
    'display_name' => $full_name,
    'role'         => 'subscriber',
] );

if ( is_wp_error( $user_id ) ) {
    wp_send_json_error( [ 'message' => $user_id->get_error_message() ?: 'Something went wrong. Please try again.' ] );
}

update_user_meta( $user_id, 'idibia_account_type', 'customer' );
update_user_meta( $user_id, 'idibia_account_status', 'pending' );
update_user_meta( $user_id, 'idibia_credit_score', 50 );
update_user_meta( $user_id, 'idibia_phone', $phone );

idibia_find_or_create_profile_row( $user_id, 'customer', [
    'full_name' => $full_name,
    'email'     => $email,
    'phone'     => $phone,
] );

// Link the referrer once the customer row exists
if ( $referrer_customer_id ) {
    $wpdb->update(
        $wpdb->prefix . 'sd_customers',
        [ 'referred_by' => $referrer_customer_id ],
        [ 'email' => $email ],
        [ '%d' ],
        [ '%s' ]
    );
}

$user = get_user_by( 'id', $user_id );

$otp = sprintf('%05d', wp_rand(0, 99999));
$expires = gmdate('Y-m-d H:i:s', time() + 15 * MINUTE_IN_SECONDS);

global $wpdb;
$wpdb->update(
    $wpdb->prefix . 'sd_customers',
    [ 'verify_code' => wp_hash_password($otp), 'verify_expires' => $expires, 'email_verified' => 0, 'status' => 'pending' ],
    [ 'email' => $email ],
    [ '%s', '%s', '%d', '%s' ],
    [ '%s' ]
);

wp_mail( $email, 'Your Idibia Verification Code', "Your code is: $otp" );

if ( ! session_id() ) session_start();
$customer_row = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM `{$wpdb->prefix}sd_customers` WHERE email = %s LIMIT 1", $email ) );
$_SESSION['sd_pending_customer_id'] = $customer_row->id;
$_SESSION['sd_pending_email']       = $email;


wp_send_json_success( [
    'first_name' => $first_name,
    'message'    => 'Account created. Please verify your email.',
] );
