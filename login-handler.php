<?php ob_start();
/** Idibia — Customer Login Handler */

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

$identifier = sanitize_text_field( wp_unslash( $_POST['phone'] ?? $_POST['email'] ?? '' ) );
$password   = (string) ( $_POST['password'] ?? '' );

if ( ! $identifier || ! $password ) {
    wp_send_json_error( [ 'message' => 'Enter your phone/email and password.' ] );
}

$creds = [
    'user_login'    => idibia_find_user_login_by_identifier( $identifier ),
    'user_password' => $password,
    'remember'      => true,
];

$user = wp_signon( $creds, is_ssl() );

if ( is_wp_error( $user ) ) {
    wp_send_json_error( [ 'message' => 'Invalid login details.' ] );
}

if ( get_user_meta( $user->ID, 'idibia_account_type', true ) !== 'customer' ) {
    wp_logout();
    wp_send_json_error( [ 'message' => 'Use a customer account to sign in here.' ] );
}

if ( get_user_meta( $user->ID, 'idibia_account_status', true ) === 'suspended' ) {
    wp_logout();
    wp_send_json_error( [ 'message' => 'Your account is not active. Contact support.' ] );
}

idibia_finish_wordpress_login( $user );
idibia_find_or_create_profile_row( $user->ID, 'customer' );

if ( ! idibia_wants_json_response() ) {
    header( 'Location: dashboard.php' );
    exit;
}

wp_send_json_success( [
    'redirect'   => 'dashboard.php',
    'first_name' => idibia_first_name_from_user( $user ),
] );
