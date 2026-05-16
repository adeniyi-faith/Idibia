<?php ob_start();
/** Idibia — Driver Registration Handler */

require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';

$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
if ( ! idibia_check_rate_limit( 'driver_register', $ip, 5, 300 ) ) {
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



$first_name  = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
$middle_name = sanitize_text_field( wp_unslash( $_POST['middle_name'] ?? '' ) );
$last_name   = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
$full_name   = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? trim( $first_name . ' ' . ( $middle_name ? $middle_name . ' ' : '' ) . $last_name ) ) );
$phone       = preg_replace( '/[\s\-()]/', '', sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
$email       = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
$password    = (string) ( $_POST['password'] ?? '' );
$language    = sanitize_text_field( wp_unslash( $_POST['language'] ?? 'English' ) );
$dob         = sanitize_text_field( wp_unslash( $_POST['date_of_birth'] ?? '' ) );
$gender      = sanitize_text_field( wp_unslash( $_POST['gender'] ?? '' ) );
$state       = sanitize_text_field( wp_unslash( $_POST['state_of_origin'] ?? '' ) );
$vehicle_type = sanitize_text_field( wp_unslash( $_POST['vehicle_type'] ?? 'bike' ) );
$errors    = [];

if ( strlen( $full_name ) < 2 ) $errors[] = 'Please enter your full name.';
if ( ! is_email( $email ) ) $errors[] = 'Enter a valid email address.';
if ( ! preg_match( '/^(\+?234|0)[789][01]\d{8}$/', $phone ) ) $errors[] = 'Enter a valid Nigerian phone number.';
if ( strlen( $password ) < 6 ) $errors[] = 'Password must be at least 6 characters.';
if ( $errors ) wp_send_json_error( [ 'message' => implode( ' ', $errors ) ] );

if ( username_exists( $phone ) ) wp_send_json_error( [ 'message' => 'Phone already registered. Try logging in.' ] );
if ( email_exists( $email ) ) wp_send_json_error( [ 'message' => 'Email already in use. Try logging in.' ] );

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

if ( is_wp_error( $user_id ) ) wp_send_json_error( [ 'message' => $user_id->get_error_message() ?: 'Something went wrong. Please try again.' ] );

update_user_meta( $user_id, 'idibia_account_type', 'driver' );
update_user_meta( $user_id, 'idibia_account_status', 'pending' );
update_user_meta( $user_id, 'idibia_kyc_status', 'pending' );
update_user_meta( $user_id, 'idibia_phone', $phone );
update_user_meta( $user_id, 'idibia_driver_language', $language );
update_user_meta( $user_id, 'idibia_driver_middle_name', $middle_name );
update_user_meta( $user_id, 'idibia_driver_date_of_birth', $dob );
update_user_meta( $user_id, 'idibia_driver_gender', $gender );
update_user_meta( $user_id, 'idibia_driver_state_of_origin', $state );

$driver_id = idibia_find_or_create_profile_row( $user_id, 'driver', [ 'full_name' => $full_name, 'email' => $email, 'phone' => $phone ] );
global $wpdb;
$vehicle_type = in_array( $vehicle_type, [ 'bike', 'car', 'van', 'keke' ], true ) ? $vehicle_type : 'bike';
$wpdb->update( $wpdb->prefix . 'sd_drivers', [ 'vehicle_type' => $vehicle_type ], [ 'id' => $driver_id ], [ '%s' ], [ '%d' ] );
$otp = sprintf('%05d', wp_rand(0, 99999));
$expires = gmdate('Y-m-d H:i:s', time() + 30 * MINUTE_IN_SECONDS);

$verify_updated = $wpdb->update(
    $wpdb->prefix . 'sd_drivers',
    [ 'verify_code' => wp_hash_password($otp), 'verify_expires' => $expires, 'email_verified' => 0, 'status' => 'pending' ],
    [ 'id' => $driver_id ],
    [ '%s', '%s', '%d', '%s' ],
    [ '%d' ]
);
if ( false === $verify_updated ) {
    wp_send_json_error( [ 'message' => 'Could not start email verification. Please try again.' ] );
}

wp_mail( $email, 'Your Idibia Driver Verification Code', "Your code is: $otp" );

if ( ! session_id() ) session_start();
$_SESSION['sd_pending_driver_id'] = $driver_id;
$_SESSION['sd_pending_driver_email'] = $email;

wp_send_json_success( [
    'first_name' => $first_name,
    'driver_id'   => $driver_id,
    'kyc_status'  => 'pending',
    'status'      => 'pending',
    'is_approved' => false,
    'is_online'   => false,
    'nonces'      => [
        'toggle_online' => wp_create_nonce( 'idibia_toggle_online' ),
        'driver_action' => wp_create_nonce( 'idibia_driver_action' ),
    ],
    'message'    => 'Driver account created. Please verify your email.',
] );
