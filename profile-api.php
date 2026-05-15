<?php
require_once __DIR__ . '/wp-auth-config.php';

$auth_type = 'customer';
require_once __DIR__ . '/auth-helper.php';

if ( $_SERVER['REQUEST_METHOD'] === 'GET' ) {
    $user = wp_get_current_user();
    wp_send_json_success([
        'full_name' => $user->display_name,
        'email' => $user->user_email,
        'phone' => get_user_meta( $user->ID, 'idibia_phone', true )
    ]);
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : '';
    if ( ! wp_verify_nonce( $nonce, 'idibia_profile_update' ) ) {
        http_response_code( 403 );
        wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
    }

    $full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
    if ( ! empty( $full_name ) ) {
        $user_id = get_current_user_id();
        wp_update_user( [ 'ID' => $user_id, 'display_name' => $full_name ] );
        [ $first_name, $last_name ] = idibia_split_full_name( $full_name );
        update_user_meta( $user_id, 'first_name', $first_name );
        update_user_meta( $user_id, 'last_name', $last_name );

        global $wpdb;
        $customer_id = $GLOBALS['auth_customer_id'];
        $wpdb->update(
            $wpdb->prefix . 'sd_customers',
            [ 'full_name' => $full_name ],
            [ 'id' => $customer_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }
    wp_send_json_success( [ 'message' => 'Profile updated successfully.' ] );
}

http_response_code( 405 );
wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
