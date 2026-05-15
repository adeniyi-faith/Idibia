<?php
/** Idibia — Driver KYC Handler */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
idibia_clean_json_buffer();

$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : '';
if ( ! wp_verify_nonce( $nonce, 'idibia_driver_kyc' ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
}

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$auth_type = 'driver';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$driver_id = (int) $GLOBALS['auth_driver_id'];
$user_id   = get_current_user_id();
$table     = $wpdb->prefix . 'sd_drivers';

idibia_ensure_driver_kyc_columns( $table );

$vehicle_type = sanitize_text_field( wp_unslash( $_POST['vehicle_type'] ?? '' ) );
if ( ! in_array( $vehicle_type, [ 'bike', 'car', 'van', 'keke' ], true ) ) {
    wp_send_json_error( [ 'message' => 'Select a valid vehicle type.' ] );
}

$vehicle_plate  = strtoupper( sanitize_text_field( wp_unslash( $_POST['vehicle_plate'] ?? '' ) ) );
$vehicle_model  = sanitize_text_field( wp_unslash( $_POST['vehicle_model'] ?? '' ) );
$vehicle_year   = sanitize_text_field( wp_unslash( $_POST['vehicle_year'] ?? '' ) );
$vehicle_color  = sanitize_text_field( wp_unslash( $_POST['vehicle_color'] ?? '' ) );
$bank_name      = sanitize_text_field( wp_unslash( $_POST['bank_name'] ?? '' ) );
$account_holder = sanitize_text_field( wp_unslash( $_POST['account_holder_name'] ?? '' ) );
$account_number = preg_replace( '/\D+/', '', sanitize_text_field( wp_unslash( $_POST['account_number'] ?? '' ) ) );
$emergency_name = sanitize_text_field( wp_unslash( $_POST['emergency_name'] ?? '' ) );
$emergency_rel  = sanitize_text_field( wp_unslash( $_POST['emergency_relationship'] ?? '' ) );
$emergency_phone = preg_replace( '/[\s\-()]/', '', sanitize_text_field( wp_unslash( $_POST['emergency_phone'] ?? '' ) ) );
$emergency_addr = sanitize_text_field( wp_unslash( $_POST['emergency_address'] ?? '' ) );

$errors = [];
if ( $vehicle_plate === '' ) $errors[] = 'Enter your vehicle plate number.';
if ( $vehicle_model === '' ) $errors[] = 'Enter your vehicle details.';
if ( $account_holder === '' ) $errors[] = 'Enter the bank account holder name.';
if ( strlen( $account_number ) < 10 ) $errors[] = 'Enter a valid bank account number.';
if ( $bank_name === '' ) $errors[] = 'Select your bank.';
if ( $emergency_name === '' || $emergency_phone === '' ) $errors[] = 'Enter your emergency contact details.';

$required_files = [ 'selfie', 'id_front', 'id_back', 'vehicle_photo', 'vehicle_license_doc' ];
foreach ( $required_files as $required_file ) {
    if ( empty( $_FILES[ $required_file ] ) || ! empty( $_FILES[ $required_file ]['error'] ) ) {
        $errors[] = 'Upload all required identity and vehicle documents.';
        break;
    }
}

if ( $errors ) {
    wp_send_json_error( [ 'message' => implode( ' ', array_unique( $errors ) ) ] );
}

$data = [
    'nin'                    => idibia_encrypt_sensitive( sanitize_text_field( wp_unslash( $_POST['nin'] ?? '' ) ) ),
    'bvn'                    => idibia_encrypt_sensitive( sanitize_text_field( wp_unslash( $_POST['bvn'] ?? '' ) ) ),
    'id_doc_type'            => sanitize_text_field( wp_unslash( $_POST['id_doc_type'] ?? 'license_nin' ) ),
    'vehicle_type'           => $vehicle_type,
    'vehicle_year'           => $vehicle_year,
    'vehicle_plate'          => $vehicle_plate,
    'vehicle_model'          => $vehicle_model,
    'vehicle_color'          => $vehicle_color,
    'bank_name'              => $bank_name,
    'account_holder_name'    => $account_holder,
    'account_number'         => $account_number,
    'emergency_name'         => $emergency_name,
    'emergency_relationship' => $emergency_rel,
    'emergency_phone'        => $emergency_phone,
    'emergency_address'      => $emergency_addr,
    'kyc_status'             => 'under_review',
    'status'                 => 'pending',
    'is_online'              => 0,
];

$file_fields = [
    'selfie',
    'id_front',
    'id_back',
    'vehicle_photo',
    'vehicle_interior_photo',
    'vehicle_front_photo',
    'vehicle_rear_photo',
    'vehicle_license_doc',
    'insurance_doc',
];
foreach ( $file_fields as $field ) {
    $path = idibia_save_kyc_upload( $field, $driver_id );
    if ( $path ) {
        $data[ $field . '_path' ] = $path;
    }
}

$formats = [];
foreach ( $data as $key => $value ) {
    $formats[] = $key === 'is_online' ? '%d' : '%s';
}

$updated = $wpdb->update( $table, $data, [ 'id' => $driver_id ], $formats, [ '%d' ] );
if ( false === $updated ) {
    wp_send_json_error( [ 'message' => 'Could not submit KYC. Please try again.' ] );
}

update_user_meta( $user_id, 'idibia_kyc_status', 'under_review' );
update_user_meta( $user_id, 'idibia_account_status', 'pending' );
foreach ( $data as $key => $value ) {
    if ( substr( $key, -5 ) !== '_path' && ! in_array( $key, [ 'nin', 'bvn' ], true ) ) {
        update_user_meta( $user_id, 'idibia_driver_' . $key, $value );
    }
}

$driver = $wpdb->get_row( $wpdb->prepare( "SELECT full_name, email FROM `$table` WHERE id = %d LIMIT 1", $driver_id ) );
$admin_email = get_option( 'admin_email' );
if ( $admin_email && $driver ) {
    wp_mail(
        $admin_email,
        '[Idibia] Driver KYC application submitted',
        "Driver #$driver_id ({$driver->full_name}, {$driver->email}) submitted KYC documents for review.",
        [ 'Content-Type: text/plain; charset=UTF-8' ]
    );
}

wp_send_json_success( [
    'message'    => "Application submitted. We'll review within 24–48 hours.",
    'driver_id'  => $driver_id,
    'kyc_status' => 'under_review',
] );

function idibia_ensure_driver_kyc_columns( string $table ): void {
    global $wpdb;

    $columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table`", 0 );
    if ( ! is_array( $columns ) ) {
        return;
    }

    $definitions = [
        'id_doc_type'                 => "VARCHAR(40) NULL",
        'vehicle_year'                => "VARCHAR(4) NULL",
        'vehicle_color'               => "VARCHAR(40) NULL",
        'bank_name'                   => "VARCHAR(120) NULL",
        'account_holder_name'         => "VARCHAR(120) NULL",
        'account_number'              => "VARCHAR(20) NULL",
        'emergency_name'              => "VARCHAR(120) NULL",
        'emergency_relationship'      => "VARCHAR(40) NULL",
        'emergency_phone'             => "VARCHAR(30) NULL",
        'emergency_address'           => "VARCHAR(255) NULL",
        'selfie_path'                 => "VARCHAR(255) NULL",
        'id_front_path'               => "VARCHAR(255) NULL",
        'id_back_path'                => "VARCHAR(255) NULL",
        'vehicle_photo_path'          => "VARCHAR(255) NULL",
        'vehicle_interior_photo_path' => "VARCHAR(255) NULL",
        'vehicle_front_photo_path'    => "VARCHAR(255) NULL",
        'vehicle_rear_photo_path'     => "VARCHAR(255) NULL",
        'vehicle_license_doc_path'    => "VARCHAR(255) NULL",
        'insurance_doc_path'          => "VARCHAR(255) NULL",
    ];

    foreach ( $definitions as $column => $definition ) {
        if ( ! in_array( $column, $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `$table` ADD COLUMN `$column` $definition" );
        }
    }

    $wpdb->query( "ALTER TABLE `$table` MODIFY `nin` VARCHAR(255) NULL" );
    $wpdb->query( "ALTER TABLE `$table` MODIFY `bvn` VARCHAR(255) NULL" );
}

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

    $file = $_FILES[ $field ];
    if ( ! is_uploaded_file( $file['tmp_name'] ) ) return null;
    if ( (int) $file['size'] > 8 * 1024 * 1024 ) {
        wp_send_json_error( [ 'message' => 'Each file must be 8MB or smaller.' ] );
    }

    $allowed_mimes = [ 'image/jpeg', 'image/png', 'application/pdf' ];
    $filetype      = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
    if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed_mimes, true ) ) {
        wp_send_json_error( [ 'message' => 'Upload only JPG, PNG or PDF files.' ] );
    }

    $upload = wp_upload_dir();
    if ( ! empty( $upload['error'] ) ) return null;

    $target_dir = trailingslashit( $upload['basedir'] ) . 'idibia-kyc/' . $driver_id;
    if ( ! wp_mkdir_p( $target_dir ) ) return null;

    $original = sanitize_file_name( wp_unslash( $file['name'] ) );
    $filename = wp_unique_filename( $target_dir, $field . '-' . $original );
    $target   = trailingslashit( $target_dir ) . $filename;

    if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) return null;

    return 'idibia-kyc/' . $driver_id . '/' . $filename;
}
