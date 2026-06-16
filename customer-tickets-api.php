<?php
/** Idibia — Customer Support Tickets API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( ! is_user_logged_in() ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
}

$user_id    = get_current_user_id();
$actor_type = get_user_meta( $user_id, 'idibia_account_type', true );
if ( ! in_array( $actor_type, [ 'customer', 'driver' ], true ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Access denied.' ] );
}

$actor_id  = idibia_find_or_create_profile_row( $user_id, $actor_type );
$ticket_id = absint( $_GET['ticket_id'] ?? 0 );

global $wpdb;

if ( $ticket_id > 0 ) {
    $ticket = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM `{$wpdb->prefix}sd_support_tickets` WHERE id = %d LIMIT 1",
        $ticket_id
    ), ARRAY_A );

    if ( ! $ticket || (int) $ticket['creator_id'] !== $actor_id || $ticket['creator_type'] !== $actor_type ) {
        http_response_code( 403 );
        wp_send_json_error( [ 'message' => 'Ticket not found.' ] );
    }

    $messages = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, sender_id, sender_type, message, created_at
         FROM `{$wpdb->prefix}sd_support_messages`
         WHERE ticket_id = %d
         ORDER BY created_at ASC",
        $ticket_id
    ), ARRAY_A );

    wp_send_json_success( [
        'ticket' => [
            'id'         => (int) $ticket['id'],
            'category'   => $ticket['category'],
            'status'     => $ticket['status'],
            'created_at' => $ticket['created_at'],
            'updated_at' => $ticket['updated_at'],
        ],
        'messages' => array_map( function ( $m ) use ( $actor_id, $actor_type ) {
            return [
                'id'          => (int) $m['id'],
                'sender_type' => $m['sender_type'],
                'is_mine'     => ( $m['sender_type'] === $actor_type && (int) $m['sender_id'] === $actor_id ),
                'message'     => $m['message'],
                'created_at'  => $m['created_at'],
            ];
        }, $messages ?: [] ),
    ] );
}

$tickets = $wpdb->get_results( $wpdb->prepare(
    "SELECT t.id, t.category, t.status, t.created_at,
            (SELECT message FROM `{$wpdb->prefix}sd_support_messages`
             WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) AS last_message,
            (SELECT COUNT(*) FROM `{$wpdb->prefix}sd_support_messages`
             WHERE ticket_id = t.id) AS message_count
     FROM `{$wpdb->prefix}sd_support_tickets` t
     WHERE t.creator_id = %d AND t.creator_type = %s
     ORDER BY COALESCE(t.updated_at, t.created_at) DESC
     LIMIT 50",
    $actor_id,
    $actor_type
), ARRAY_A );

wp_send_json_success( [
    'tickets' => array_map( function ( $t ) {
        return [
            'id'            => (int) $t['id'],
            'category'      => $t['category'],
            'status'        => $t['status'],
            'created_at'    => $t['created_at'],
            'last_message'  => $t['last_message'] ?? '',
            'message_count' => (int) $t['message_count'],
        ];
    }, $tickets ?: [] ),
] );
