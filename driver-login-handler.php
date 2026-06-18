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
$is_approved = $kyc_status === 'approved' && $status === 'active';
$email_verified = ! empty( $driver_row['email_verified'] );

// An approved driver is fully onboarded — heal any stale email_verified=0 row.
if ( $is_approved && ! $email_verified ) {
    $wpdb->update( $wpdb->prefix . 'sd_drivers', [ 'email_verified' => 1 ], [ 'id' => $driver_id ], [ '%d' ], [ '%d' ] );
    $email_verified = true;
}

$upload_dir     = wp_upload_dir();
$upload_baseurl = rtrim( $upload_dir['baseurl'], '/' );

// Dashboard stats and trip history — needed so the SPA login path can hydrate
// the trips panel and earnings stats without requiring a full page reload.
$today_start = gmdate( 'Y-m-d 00:00:00' );
$today_end   = gmdate( 'Y-m-d 23:59:59' );
$today_stats = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(fare) as today_earnings, COUNT(id) as today_trips FROM `{$wpdb->prefix}sd_trips` WHERE driver_id = %d AND status = 'completed' AND completed_at >= %s AND completed_at <= %s", $driver_id, $today_start, $today_end ), ARRAY_A );

$week_start = gmdate( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) );
$week_end   = gmdate( 'Y-m-d 23:59:59', strtotime( 'sunday this week' ) );
$weekly_stats = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(fare) as week_earnings, COUNT(id) as week_trips FROM `{$wpdb->prefix}sd_trips` WHERE driver_id = %d AND status = 'completed' AND completed_at >= %s AND completed_at <= %s", $driver_id, $week_start, $week_end ), ARRAY_A );

$daily_breakdown_raw = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(completed_at) as date, SUM(fare) as daily_earnings FROM `{$wpdb->prefix}sd_trips` WHERE driver_id = %d AND status = 'completed' AND completed_at >= %s AND completed_at <= %s GROUP BY DATE(completed_at)", $driver_id, $week_start, $week_end ), ARRAY_A );
$daily_breakdown = [];
foreach ( $daily_breakdown_raw as $row ) {
    $daily_breakdown[ $row['date'] ] = (float) $row['daily_earnings'];
}

$avg_rating = (float) $wpdb->get_var( $wpdb->prepare( "SELECT AVG(rating) FROM `{$wpdb->prefix}sd_ratings` WHERE subject_id = %d AND reviewer_type = 'customer'", $driver_id ) );
$avg_rating = $avg_rating > 0 ? round( $avg_rating, 1 ) : 0.0;

$trips_history = $wpdb->get_results( $wpdb->prepare( "SELECT id, trip_ref, pickup, dropoff, fare, status, created_at, completed_at FROM `{$wpdb->prefix}sd_trips` WHERE driver_id = %d ORDER BY created_at DESC LIMIT 100", $driver_id ), ARRAY_A );

// Return the full profile payload so the SPA login path hydrates the dashboard
// overlay with the same data the server-rendered (hard reload) path produces.
wp_send_json_success( [
    'redirect'    => '/driver.php',
    'first_name'  => idibia_first_name_from_user( $user ),
    'full_name'   => $driver_row['full_name'] ?? $user->display_name,
    'phone'       => $driver_row['phone'] ?? '',
    'driver_id'   => $driver_id,
    'kyc_status'  => $kyc_status,
    'status'      => $status,
    'is_approved' => $is_approved,
    'is_online'   => ! empty( $driver_row['is_online'] ),
    'email_verified' => $email_verified,
    'vehicle_type' => $driver_row['vehicle_type'] ?? '',
    'rating'      => $driver_row['rating'] ?? '0.00',
    'total_trips' => $driver_row['total_trips'] ?? 0,
    'avatar_path' => $driver_row['avatar_path'] ?? '',
    'selfie_path' => $driver_row['selfie_path'] ?? '',
    'upload_baseurl' => $upload_baseurl,
    'dashboard_stats' => [
        'today_earnings'  => (float) ( $today_stats['today_earnings'] ?? 0 ),
        'today_trips'     => (int) ( $today_stats['today_trips'] ?? 0 ),
        'week_earnings'   => (float) ( $weekly_stats['week_earnings'] ?? 0 ),
        'week_trips'      => (int) ( $weekly_stats['week_trips'] ?? 0 ),
        'daily_breakdown' => $daily_breakdown,
        'avg_rating'      => $avg_rating,
    ],
    'trips_history' => $trips_history ?: [],
] );
