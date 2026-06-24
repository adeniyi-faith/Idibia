<?php
/** Idibia — Admin Pusher Channel Auth */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/../wp-auth-config.php';
require_once __DIR__ . '/../wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

// Validate admin session cookie
$admin_id = 0;
if ( isset( $_COOKIE['idibia_admin_auth'] ) ) {
    $decoded = base64_decode( $_COOKIE['idibia_admin_auth'] );
    $parts   = explode( '|', $decoded );
    if ( count( $parts ) === 2 ) {
        $hash = hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) );
        if ( hash_equals( $hash, $parts[1] ) ) {
            $payload  = json_decode( $parts[0], true );
            $admin_id = isset( $payload['id'] ) ? (int) $payload['id'] : 0;
        }
    }
}

if ( ! $admin_id ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
}

if ( ! idibia_pusher_is_configured() ) {
    http_response_code( 503 );
    wp_send_json_error( [ 'message' => 'Realtime is not configured.' ] );
}

$socket_id    = sanitize_text_field( wp_unslash( $_POST['socket_id'] ?? '' ) );
$channel_name = sanitize_text_field( wp_unslash( $_POST['channel_name'] ?? '' ) );

if ( ! preg_match( '/^\d+\.\d+$/', $socket_id ) || $channel_name !== 'private-admin' ) {
    http_response_code( 400 );
    wp_send_json_error( [ 'message' => 'Invalid channel.' ] );
}

require_once __DIR__ . '/../wp/wp-content/mu-plugins/idibia-config.php';
$pusher_key    = (string) idibia_get_setting( 'pusher_key', defined( 'IDIBIA_PUSHER_KEY' ) ? IDIBIA_PUSHER_KEY : '' );
$pusher_secret = (string) idibia_get_setting( 'pusher_secret', defined( 'IDIBIA_PUSHER_SECRET' ) ? IDIBIA_PUSHER_SECRET : '' );

$signature = hash_hmac( 'sha256', $socket_id . ':' . $channel_name, $pusher_secret );
wp_send_json( [ 'auth' => $pusher_key . ':' . $signature ] );
