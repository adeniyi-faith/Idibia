<?php
/** Idibia — Customer Login Handler */

define( 'WP_USE_THEMES', false );
require_once __DIR__ . '/wp/wp-load.php';
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

global $wpdb;

$identifier = sanitize_text_field( wp_unslash( $_POST['email'] ?? $_POST['phone'] ?? '' ) );
$password   = (string) ( $_POST['password'] ?? '' );

if ( ! $identifier || ! $password ) {
    wp_send_json_error( [ 'message' => 'Enter your email/phone and password.' ] );
}

$table = $wpdb->prefix . 'sd_customers';
$is_email = strpos( $identifier, '@' ) !== false;
$customer = $wpdb->get_row( $wpdb->prepare(
    "SELECT id, full_name, email, phone, password_hash, email_verified, status FROM `$table` WHERE " . ( $is_email ? 'email' : 'phone' ) . " = %s LIMIT 1",
    $is_email ? sanitize_email( $identifier ) : preg_replace( '/[\s\-()]/', '', $identifier )
) );

if ( ! $customer || ! wp_check_password( $password, $customer->password_hash ) ) {
    wp_send_json_error( [ 'message' => 'Invalid login details.' ] );
}

if ( ! (int) $customer->email_verified ) {
    wp_send_json_error( [ 'message' => 'Please verify your email first.' ] );
}

if ( $customer->status !== 'active' ) {
    wp_send_json_error( [ 'message' => 'Your account is not active. Contact support.' ] );
}

$token = idibia_create_customer_session( (int) $customer->id );

wp_send_json_success( [
    'token'      => $token,
    'first_name' => idibia_first_name( $customer->full_name ),
] );

function idibia_create_customer_session( int $customer_id ): string {
    global $wpdb;

    $token      = bin2hex( random_bytes( 32 ) );
    $expires_at = gmdate( 'Y-m-d H:i:s', time() + ( 30 * DAY_IN_SECONDS ) );
    $ip         = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    $user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );

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

function idibia_first_name( string $full_name ): string {
    $parts = preg_split( '/\s+/', trim( $full_name ) );
    return $parts[0] ?? '';
}
