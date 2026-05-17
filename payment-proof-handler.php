<?php
/** Idibia — Manual Transfer Proof Upload */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$auth_type = 'customer';
require_once __DIR__ . '/auth-helper.php';

$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
if ( ! wp_verify_nonce( $nonce, 'idibia_payment_proof' ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
}

$trip_id = absint( $_POST['trip_id'] ?? 0 );
if ( $trip_id <= 0 ) {
    wp_send_json_error( [ 'message' => 'Trip ID is required.' ] );
}

if ( empty( $_FILES['payment_proof'] ) || ! empty( $_FILES['payment_proof']['error'] ) ) {
    wp_send_json_error( [ 'message' => 'Upload your bank transfer receipt or screenshot.' ] );
}

global $wpdb;
$customer_id = (int) $GLOBALS['auth_customer_id'];
$trip = $wpdb->get_row( $wpdb->prepare( "SELECT id, customer_id FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1", $trip_id ), ARRAY_A );
if ( ! $trip || (int) $trip['customer_id'] !== $customer_id ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'You cannot upload proof for this trip.' ] );
}

idibia_ensure_manual_payment_columns();
$payment = $wpdb->get_row( $wpdb->prepare( "SELECT id, status FROM `{$wpdb->prefix}sd_payments` WHERE trip_id = %d AND customer_id = %d ORDER BY id DESC LIMIT 1", $trip_id, $customer_id ), ARRAY_A );
if ( ! $payment ) {
    wp_send_json_error( [ 'message' => 'Payment record not found for this trip.' ] );
}
if ( $payment['status'] === 'captured' ) {
    wp_send_json_error( [ 'message' => 'This payment is already approved.' ] );
}

$proof_path = idibia_save_payment_proof_upload( 'payment_proof', $customer_id, $trip_id );
if ( ! $proof_path ) {
    wp_send_json_error( [ 'message' => 'Could not save proof. Please try again.' ] );
}

$provider_ref = sanitize_text_field( wp_unslash( $_POST['bank_ref'] ?? '' ) );
$updated = $wpdb->update(
    $wpdb->prefix . 'sd_payments',
    [
        'provider_ref' => $provider_ref ?: null,
        'proof_path'   => $proof_path,
        'status'       => 'pending',
        'admin_notes'  => null,
        'reviewed_by'  => null,
        'reviewed_at'  => null,
    ],
    [ 'id' => (int) $payment['id'] ],
    [ '%s', '%s', '%s', '%s', '%s', '%s' ],
    [ '%d' ]
);

if ( false === $updated ) {
    wp_send_json_error( [ 'message' => 'Could not submit payment proof.' ] );
}

$wpdb->update( $wpdb->prefix . 'sd_trips', [ 'payment_status' => 'pending' ], [ 'id' => $trip_id ], [ '%s' ], [ '%d' ] );
idibia_log_event( $trip_id, 'payment_proof_uploaded', [ 'payment_id' => (int) $payment['id'] ] );
idibia_notify_trip_participants( $trip_id, 'payment_proof_uploaded' );

wp_send_json_success( [
    'message' => 'Payment proof uploaded. Admin will review it shortly.',
    'payment' => idibia_payment_public_payload( $trip_id ),
] );

function idibia_save_payment_proof_upload( string $field, int $customer_id, int $trip_id ): ?string {
    $file = $_FILES[ $field ];
    if ( ! is_uploaded_file( $file['tmp_name'] ) ) return null;
    if ( (int) $file['size'] > 8 * 1024 * 1024 ) {
        wp_send_json_error( [ 'message' => 'Payment proof must be 8MB or smaller.' ] );
    }

    $allowed_mimes = [ 'image/jpeg', 'image/png', 'application/pdf' ];
    $filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
    if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed_mimes, true ) ) {
        wp_send_json_error( [ 'message' => 'Upload only JPG, PNG or PDF payment proof.' ] );
    }

    $upload = wp_upload_dir();
    if ( ! empty( $upload['error'] ) ) return null;

    $target_dir = trailingslashit( $upload['basedir'] ) . 'idibia-payments/' . $customer_id;
    if ( ! wp_mkdir_p( $target_dir ) ) return null;

    $original = sanitize_file_name( wp_unslash( $file['name'] ) );
    $filename = wp_unique_filename( $target_dir, 'trip-' . $trip_id . '-proof-' . $original );
    $target = trailingslashit( $target_dir ) . $filename;

    if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) return null;

    return 'idibia-payments/' . $customer_id . '/' . $filename;
}
