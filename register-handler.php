<?php ob_start();
/** Idibia — Customer Registration Handler */

require_once __DIR__ . '/wp-auth-config.php';

if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
    idibia_clean_json_buffer();
    http_response_code( 204 );
    exit;
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

$full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
$phone     = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
$email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
$password  = (string) ( $_POST['password'] ?? '' );
$terms     = ! empty( $_POST['terms'] );
$phone     = preg_replace( '/[\s\-()]/', '', $phone );
$errors    = [];

if ( strlen( $full_name ) < 2 ) $errors[] = 'Please enter your full name.';
if ( ! is_email( $email ) ) $errors[] = 'Enter a valid email address.';
if ( ! preg_match( '/^(\+?234|0)[789][01]\d{8}$/', $phone ) ) $errors[] = 'Enter a valid Nigerian phone number (e.g. 08012345678).';
if ( strlen( $password ) < 6 ) $errors[] = 'Password must be at least 6 characters.';
if ( ! $terms ) $errors[] = 'You must agree to the Terms of Service to continue.';

if ( $errors ) {
    wp_send_json_error( [ 'message' => implode( ' ', $errors ) ] );
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
update_user_meta( $user_id, 'idibia_account_status', 'active' );
update_user_meta( $user_id, 'idibia_credit_score', 50 );
update_user_meta( $user_id, 'idibia_phone', $phone );

idibia_find_or_create_profile_row( $user_id, 'customer', [
    'full_name' => $full_name,
    'email'     => $email,
    'phone'     => $phone,
] );

$user = get_user_by( 'id', $user_id );
idibia_finish_wordpress_login( $user );

if ( ! idibia_wants_json_response() ) {
    header( 'Location: dashboard.php' );
    exit;
}

wp_send_json_success( [
    'redirect'   => 'dashboard.php',
    'first_name' => $first_name,
    'message'    => 'Account created successfully.',
] );
