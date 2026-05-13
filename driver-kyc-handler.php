<?php
/** Idibia — Driver KYC Handler */

define( 'WP_USE_THEMES', false );
require_once __DIR__ . '/wp/wp-load.php';
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$auth_type = 'driver';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$driver_id = (int) $GLOBALS['auth_driver_id'];
$table     = $wpdb->prefix . 'sd_drivers';

$vehicle_type = sanitize_text_field( wp_unslash( $_POST['vehicle_type'] ?? '' ) );
if ( $vehicle_type && ! in_array( $vehicle_type, [ 'bike', 'car', 'van', 'keke' ], true ) ) {
    wp_send_json_error( [ 'message' => 'Select a valid vehicle type.' ] );
}

$data = [
    'nin'             => idibia_encrypt_sensitive( sanitize_text_field( wp_unslash( $_POST['nin'] ?? '' ) ) ),
    'bvn'             => idibia_encrypt_sensitive( sanitize_text_field( wp_unslash( $_POST['bvn'] ?? '' ) ) ),
    'id_doc_type'     => sanitize_text_field( wp_unslash( $_POST['id_doc_type'] ?? '' ) ),
    'vehicle_type'    => $vehicle_type ?: null,
    'vehicle_plate'   => sanitize_text_field( wp_unslash( $_POST['vehicle_plate'] ?? '' ) ),
    'vehicle_model'   => sanitize_text_field( wp_unslash( $_POST['vehicle_model'] ?? '' ) ),
    'bank_name'       => sanitize_text_field( wp_unslash( $_POST['bank_name'] ?? '' ) ),
    'account_number'  => sanitize_text_field( wp_unslash( $_POST['account_number'] ?? '' ) ),
    'emergency_name'  => sanitize_text_field( wp_unslash( $_POST['emergency_name'] ?? '' ) ),
    'emergency_phone' => sanitize_text_field( wp_unslash( $_POST['emergency_phone'] ?? '' ) ),
    'kyc_status'      => 'under_review',
];

$file_fields = [ 'selfie', 'id_front', 'id_back', 'vehicle_photo', 'insurance_doc' ];
foreach ( $file_fields as $field ) {
    $path = idibia_save_kyc_upload( $field, $driver_id );
    if ( $path ) {
        $data[ $field . '_path' ] = $path;
    }
}

$formats = array_fill( 0, count( $data ), '%s' );

$updated = $wpdb->update( $table, $data, [ 'id' => $driver_id ], $formats, [ '%d' ] );
if ( false === $updated ) {
    wp_send_json_error( [ 'message' => 'Could not submit KYC. Please try again.' ] );
}

$driver = $wpdb->get_row( $wpdb->prepare( "SELECT full_name, email FROM `$table` WHERE id = %d LIMIT 1", $driver_id ) );
$admin_email = get_option( 'admin_email' );
if ( $admin_email ) {
    wp_mail(
        $admin_email,
        '[Idibia] Driver KYC application submitted',
        "Driver #$driver_id ({$driver->full_name}, {$driver->email}) submitted KYC documents for review.",
        [ 'Content-Type: text/plain; charset=UTF-8' ]
    );
}

wp_send_json_success( [ 'message' => "Application submitted. We'll review within 24–48 hours." ] );

function idibia_encrypt_sensitive( string $value ): ?string {
    $value = trim( $value );
    if ( $value === '' ) return null;

    $secret = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : wp_salt() );
    $key    = hash( 'sha256', $secret, true );
    $iv     = random_bytes( 16 );
    $cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

    if ( false === $cipher ) return null;

    return base64_encode( $iv . $cipher );
}

function idibia_save_kyc_upload( string $field, int $driver_id ): ?string {
    if ( empty( $_FILES[ $field ] ) || ! empty( $_FILES[ $field ]['error'] ) ) return null;

    $upload = wp_upload_dir();
    if ( ! empty( $upload['error'] ) ) return null;

    $target_dir = trailingslashit( $upload['basedir'] ) . 'idibia-kyc/' . $driver_id;
    if ( ! wp_mkdir_p( $target_dir ) ) return null;

    $original = sanitize_file_name( wp_unslash( $_FILES[ $field ]['name'] ) );
    $filename = wp_unique_filename( $target_dir, $field . '-' . $original );
    $target   = trailingslashit( $target_dir ) . $filename;

    if ( ! is_uploaded_file( $_FILES[ $field ]['tmp_name'] ) ) return null;
    if ( ! move_uploaded_file( $_FILES[ $field ]['tmp_name'], $target ) ) return null;

    return 'idibia-kyc/' . $driver_id . '/' . $filename;
}
