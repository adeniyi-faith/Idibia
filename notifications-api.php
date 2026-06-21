<?php
/** Idibia — Notification Polling API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( ! is_user_logged_in() ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
}

global $wpdb;

$action = sanitize_key( $_POST['action'] ?? $_GET['action'] ?? '' );

$wp_user_id   = get_current_user_id();
$account_type = get_user_meta( $wp_user_id, 'idibia_account_type', true );

if ( ! in_array( $account_type, [ 'customer', 'driver' ], true ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Forbidden.' ] );
}

$profile_id = idibia_find_or_create_profile_row( $wp_user_id, $account_type );
if ( ! $profile_id ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Profile not found.' ] );
}

switch ( $action ) {

    case 'get_notifications':
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, body, is_read, created_at
             FROM `{$wpdb->prefix}sd_notifications`
             WHERE user_id = %d AND user_type = %s
             ORDER BY created_at DESC
             LIMIT 20",
            $profile_id,
            $account_type
        ), ARRAY_A ) ?: [];

        $unread_count = 0;
        foreach ( $rows as &$row ) {
            $row['id']       = (int) $row['id'];
            $row['is_read']  = (bool) $row['is_read'];
            if ( ! $row['is_read'] ) $unread_count++;
        }
        unset( $row );

        wp_send_json_success( [
            'notifications' => $rows,
            'unread_count'  => $unread_count,
        ] );
        break;

    case 'mark_read':
        $notif_id = sanitize_text_field( wp_unslash( $_POST['notification_id'] ?? '' ) );

        if ( $notif_id === 'all' ) {
            $wpdb->update(
                $wpdb->prefix . 'sd_notifications',
                [ 'is_read' => 1 ],
                [ 'user_id' => $profile_id, 'user_type' => $account_type, 'is_read' => 0 ],
                [ '%d' ],
                [ '%d', '%s', '%d' ]
            );
        } elseif ( ctype_digit( (string) $notif_id ) ) {
            $wpdb->update(
                $wpdb->prefix . 'sd_notifications',
                [ 'is_read' => 1 ],
                [ 'id' => (int) $notif_id, 'user_id' => $profile_id, 'user_type' => $account_type ],
                [ '%d' ],
                [ '%d', '%d', '%s' ]
            );
        } else {
            wp_send_json_error( [ 'message' => 'Invalid notification_id.' ] );
        }

        wp_send_json_success( [ 'message' => 'Marked as read.' ] );
        break;

    default:
        wp_send_json_error( [ 'message' => 'Unknown action.' ] );
}
