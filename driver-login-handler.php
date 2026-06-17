<?php ob_start();
/** Idibia — Driver Login Handler */

require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';

$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
if ( ! idibia_check_rate_limit( 'driver_login', $ip, 5, 300 ) ) {
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



$identifier = sanitize_text_field( wp_unslash( $_POST['phone'] ?? $_POST['email'] ?? '' ) );
$password   = (string) ( $_POST['password'] ?? '' );
if ( ! $identifier || ! $password ) wp_send_json_error( [ 'message' => 'Enter your phone/email and password.' ] );

$user = wp_signon( [
    'user_login'    => idibia_find_user_login_by_identifier( $identifier ),
    'user_password' => $password,
    'remember'      => true,
], is_ssl() );

if ( is_wp_error( $user ) ) wp_send_json_error( [ 'message' => 'Invalid login details.' ] );
if ( get_user_meta( $user->ID, 'idibia_account_type', true ) !== 'driver' ) { wp_logout(); wp_send_json_error( [ 'message' => 'Use a driver account to sign in here.' ] ); }
if ( get_user_meta( $user->ID, 'idibia_account_status', true ) === 'suspended' ) { wp_logout(); wp_send_json_error( [ 'message' => 'Your driver account is suspended. Contact support.' ] ); }

idibia_finish_wordpress_login( $user );
$driver_id = idibia_find_or_create_profile_row( $user->ID, 'driver' );
global $wpdb;
$driver_row = $wpdb->get_row( $wpdb->prepare( "SELECT kyc_status, status, is_online, email_verified, full_name, phone, vehicle_type, rating, total_trips, avatar_path, selfie_path FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $driver_id ), ARRAY_A );
$kyc_status = $driver_row['kyc_status'] ?? ( get_user_meta( $user->ID, 'idibia_kyc_status', true ) ?: 'pending' );
$status     = $driver_row['status'] ?? ( get_user_meta( $user->ID, 'idibia_account_status', true ) ?: 'pending' );
$email_verified = ! empty( $driver_row['email_verified'] );

$upload_dir     = wp_upload_dir();
$upload_baseurl = rtrim( $upload_dir['baseurl'], '/' );

// Return the full profile payload so the SPA login path hydrates the dashboard
// overlay with the same data the server-rendered (hard reload) path produces.
// Without these fields the avatar/name overlay would stay blank until a reload.
wp_send_json_success( [
    'redirect'    => '/driver.php',
    'first_name'  => idibia_first_name_from_user( $user ),
    'full_name'   => $driver_row['full_name'] ?? $user->display_name,
    'phone'       => $driver_row['phone'] ?? '',
    'driver_id'   => $driver_id,
    'kyc_status'  => $kyc_status,
    'status'      => $status,
    'is_approved' => $kyc_status === 'approved' && $status === 'active',
    'is_online'   => ! empty( $driver_row['is_online'] ),
    'email_verified' => $email_verified,
    'vehicle_type' => $driver_row['vehicle_type'] ?? '',
    'rating'      => $driver_row['rating'] ?? '0.00',
    'total_trips' => $driver_row['total_trips'] ?? 0,
    'avatar_path' => $driver_row['avatar_path'] ?? '',
    'selfie_path' => $driver_row['selfie_path'] ?? '',
    'upload_baseurl' => $upload_baseurl,
] );
