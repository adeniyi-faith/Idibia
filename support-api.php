<?php
/** Idibia — Support, Evidence, and Safety API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

if ( ! is_user_logged_in() ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
}

$user_id = get_current_user_id();
$actor_type = get_user_meta( $user_id, 'idibia_account_type', true );
if ( ! in_array( $actor_type, [ 'customer', 'driver' ], true ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Only customers and drivers can contact support here.' ] );
}

$actor_id = idibia_find_or_create_profile_row( $user_id, $actor_type );
$action = sanitize_key( $_POST['action'] ?? 'create_ticket' );

global $wpdb;

if ( $action === 'create_ticket' || $action === 'safety_report' ) {
    $trip_id = absint( $_POST['trip_id'] ?? 0 );
    $category = sanitize_key( $_POST['category'] ?? ( $action === 'safety_report' ? 'emergency_safety' : 'general' ) );
    $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
    if ( $message === '' ) {
        wp_send_json_error( [ 'message' => 'Describe what happened so support can help.' ] );
    }
    if ( $trip_id > 0 && ! idibia_get_trip_for_actor( $trip_id, $actor_type, $actor_id ) ) {
        http_response_code( 403 );
        wp_send_json_error( [ 'message' => 'You cannot open support for this trip.' ] );
    }

    $has_evidence = idibia_has_upload( 'evidence' );
    idibia_validate_evidence_upload( 'evidence' );

    $status = $action === 'safety_report' ? 'in_progress' : 'open';
    if ( $action === 'safety_report' ) {
        $severity = sanitize_key( $_POST['severity'] ?? 'high' );
        if ( $severity === 'high' ) {
            $status = 'escalated';
        }
    }

    idibia_transaction_start();
    $wpdb->insert( $wpdb->prefix . 'sd_support_tickets', [
        'creator_id'   => $actor_id,
        'creator_type' => $actor_type,
        'trip_id'      => $trip_id ?: null,
        'category'     => $category,
        'status'       => $status,
    ], [ '%d', '%s', '%d', '%s', '%s' ] );
    $ticket_id = (int) $wpdb->insert_id;
    if ( $ticket_id <= 0 ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Could not open support ticket.' ] );
    }

    $message_inserted = $wpdb->insert( $wpdb->prefix . 'sd_support_messages', [
        'ticket_id'   => $ticket_id,
        'sender_id'   => $actor_id,
        'sender_type' => $actor_type,
        'message'     => $message,
    ], [ '%d', '%d', '%s', '%s' ] );
    if ( false === $message_inserted ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Could not save the support message.' ] );
    }

    $evidence_path = idibia_save_evidence_upload( 'evidence', $actor_id, $actor_type, 'ticket', $ticket_id );
    if ( $has_evidence && ! $evidence_path ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Could not save evidence. Please try again.' ] );
    }
    idibia_transaction_commit();

    if ( $trip_id > 0 ) {
        $metadata = [ 'ticket_id' => $ticket_id, 'category' => $category ];
        if ( $action === 'safety_report' && isset($severity) ) {
            $metadata['severity'] = $severity;
        }
        idibia_log_event( $trip_id, $action === 'safety_report' ? 'safety_report_created' : 'support_ticket_created', $metadata );
        idibia_notify_trip_participants( $trip_id, $action === 'safety_report' ? 'safety_report_created' : 'support_ticket_created' );
    }

    $admins = get_users( [ 'role__in' => [ 'administrator' ], 'fields' => [ 'ID' ], 'number' => 20 ] );
    foreach ( $admins as $admin ) {
        idibia_notify_user(
            (int) $admin->ID,
            'admin',
            $action === 'safety_report' ? 'Emergency safety report' : 'New support ticket',
            ucfirst( $actor_type ) . " #$actor_id opened ticket #$ticket_id" . ( $trip_id ? " for trip #$trip_id." : '.' )
        );
    }

    wp_send_json_success( [
        'message'       => $action === 'safety_report' ? 'Safety report sent. Support has been alerted.' : 'Support ticket opened.',
        'ticket_id'     => $ticket_id,
        'evidence_path' => $evidence_path,
    ] );
}

if ( $action === 'add_message' || $action === 'upload_evidence' ) {
    $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
    $ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_support_tickets` WHERE id = %d LIMIT 1", $ticket_id ), ARRAY_A );
    if ( ! $ticket || (int) $ticket['creator_id'] !== $actor_id || $ticket['creator_type'] !== $actor_type ) {
        http_response_code( 403 );
        wp_send_json_error( [ 'message' => 'You cannot update this support ticket.' ] );
    }

    $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
    if ( $action === 'add_message' && $message === '' ) {
        wp_send_json_error( [ 'message' => 'Write a message first.' ] );
    }
    $has_evidence = idibia_has_upload( 'evidence' );
    idibia_validate_evidence_upload( 'evidence' );

    idibia_transaction_start();
    if ( $message !== '' ) {
        $message_inserted = $wpdb->insert( $wpdb->prefix . 'sd_support_messages', [
            'ticket_id'   => $ticket_id,
            'sender_id'   => $actor_id,
            'sender_type' => $actor_type,
            'message'     => $message,
        ], [ '%d', '%d', '%s', '%s' ] );
        if ( false === $message_inserted ) {
            idibia_transaction_rollback();
            wp_send_json_error( [ 'message' => 'Could not save the support message.' ] );
        }
        $wpdb->update( $wpdb->prefix . 'sd_support_tickets', [ 'status' => 'in_progress' ], [ 'id' => $ticket_id ], [ '%s' ], [ '%d' ] );
    }

    $evidence_path = idibia_save_evidence_upload( 'evidence', $actor_id, $actor_type, 'ticket', $ticket_id );
    if ( $has_evidence && ! $evidence_path ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Could not save evidence. Please try again.' ] );
    }
    idibia_transaction_commit();
    wp_send_json_success( [ 'message' => 'Support ticket updated.', 'ticket_id' => $ticket_id, 'evidence_path' => $evidence_path ] );
}

wp_send_json_error( [ 'message' => 'Unknown support action.' ] );
