<?php
require_once __DIR__ . '/wp-auth-config.php';

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : '';
if ( ! wp_verify_nonce( $nonce, 'idibia_logout' ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
}

wp_logout();
wp_send_json_success( [ 'message' => 'Logged out successfully.' ] );
