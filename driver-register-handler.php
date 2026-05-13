<?php
/** Idibia — Driver Registration Handler */

define( 'WP_USE_THEMES', false );
require_once __DIR__ . '/wp/wp-load.php';
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

global $wpdb;

$full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
$phone     = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
$email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
$password  = (string) ( $_POST['password'] ?? '' );
$phone_normalized = preg_replace( '/[\s\-()]/', '', $phone );
$errors = [];

if ( strlen( $full_name ) < 2 ) $errors[] = 'Please enter your full name.';
if ( ! is_email( $email ) ) $errors[] = 'Enter a valid email address.';
if ( ! preg_match( '/^(\+?234|0)[789][01]\d{8}$/', $phone_normalized ) ) $errors[] = 'Enter a valid Nigerian phone number.';
if ( strlen( $password ) < 8 ) $errors[] = 'Password must be at least 8 characters.';
if ( $errors ) wp_send_json_error( [ 'message' => implode( ' ', $errors ) ] );

$table = $wpdb->prefix . 'sd_drivers';
$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `$table` WHERE email = %s LIMIT 1", $email ) );
if ( $exists ) wp_send_json_error( [ 'message' => 'A driver account with that email already exists.' ] );

$verify_code    = (string) wp_rand( 10000, 99999 );
$verify_expires = gmdate( 'Y-m-d H:i:s', time() + ( 30 * MINUTE_IN_SECONDS ) );
$password_hash  = wp_hash_password( $password );

$inserted = $wpdb->insert(
    $table,
    [
        'full_name'      => $full_name,
        'email'          => $email,
        'phone'          => $phone_normalized,
        'password_hash'  => $password_hash,
        'verify_code'    => $verify_code,
        'verify_expires' => $verify_expires,
        'status'         => 'pending',
        'kyc_status'     => 'pending',
    ],
    [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
);

if ( ! $inserted ) wp_send_json_error( [ 'message' => 'Something went wrong. Please try again.' ] );

$driver_id  = (int) $wpdb->insert_id;
$first_name = idibia_first_name( $full_name );
$site_name  = get_bloginfo( 'name' ) ?: 'Idibia';
$site_url   = home_url();
$subject    = "[$site_name] Your driver verification code is $verify_code";
$body       = "Hi $first_name,\n\nWelcome to Idibia Driver. Enter this code to verify your email:\n\n    $verify_code\n\nThis code expires in 30 minutes.\n\n— The Idibia Team\n$site_url";
$headers    = [ 'Content-Type: text/plain; charset=UTF-8', 'From: ' . $site_name . ' <no-reply@' . parse_url( $site_url, PHP_URL_HOST ) . '>' ];

if ( ! wp_mail( $email, $subject, $body, $headers ) ) {
    error_log( "Idibia: driver wp_mail failed for driver_id=$driver_id email=$email" );
}

if ( ! session_id() ) session_start();
$_SESSION['sd_pending_driver_id']    = $driver_id;
$_SESSION['sd_pending_driver_email'] = $email;

wp_send_json_success( [
    'masked_email' => preg_replace( '/(?<=.{2}).(?=.*@)/u', '*', $email ),
    'first_name'   => $first_name,
] );

function idibia_first_name( string $full_name ): string {
    $parts = preg_split( '/\s+/', trim( $full_name ) );
    return $parts[0] ?? '';
}
