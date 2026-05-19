<?php
require_once __DIR__ . '/wp-auth-config.php';

$auth_type = 'driver';
require_once __DIR__ . '/auth-helper.php';

if ( $_SERVER['REQUEST_METHOD'] === 'GET' ) {
    $user = wp_get_current_user();
    wp_send_json_success([
        'full_name' => $user->display_name,
        'email' => $user->user_email,
        'phone' => get_user_meta( $user->ID, 'idibia_phone', true ),
        'language' => get_user_meta( $user->ID, 'idibia_driver_language', true ),
    ]);
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : '';
    if ( ! wp_verify_nonce( $nonce, 'idibia_driver_profile_update' ) ) {
        http_response_code( 403 );
        wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
    }

    global $wpdb;
    $driver_id = $GLOBALS['auth_driver_id'];
    $user_id = get_current_user_id();
    $action = sanitize_text_field( wp_unslash( $_POST['action'] ?? '' ) );

    if ( $action === 'update_personal' ) {
        $full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
        $phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );

        if ( empty( $full_name ) || empty( $phone ) ) {
            wp_send_json_error( [ 'message' => 'Full name and phone are required.' ] );
        }

        wp_update_user( [ 'ID' => $user_id, 'display_name' => $full_name ] );
        [ $first_name, $last_name ] = idibia_split_full_name( $full_name );
        update_user_meta( $user_id, 'first_name', $first_name );
        update_user_meta( $user_id, 'last_name', $last_name );
        update_user_meta( $user_id, 'idibia_phone', $phone );

        $wpdb->update(
            $wpdb->prefix . 'sd_drivers',
            [ 'full_name' => $full_name, 'phone' => $phone ],
            [ 'id' => $driver_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        wp_send_json_success( [ 'message' => 'Personal info updated successfully.' ] );
    }

    if ( $action === 'update_bank' ) {
        $bank_name = sanitize_text_field( wp_unslash( $_POST['bank_name'] ?? '' ) );
        $account_number = preg_replace( '/\D+/', '', sanitize_text_field( wp_unslash( $_POST['account_number'] ?? '' ) ) );

        if ( empty( $bank_name ) || empty( $account_number ) ) {
            wp_send_json_error( [ 'message' => 'Bank name and account number are required.' ] );
        }

        $wpdb->update(
            $wpdb->prefix . 'sd_drivers',
            [ 'bank_name' => $bank_name, 'account_number' => $account_number ],
            [ 'id' => $driver_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        wp_send_json_success( [ 'message' => 'Bank details updated successfully.' ] );
    }

    if ( $action === 'update_emergency' ) {
        $emergency_name = sanitize_text_field( wp_unslash( $_POST['emergency_name'] ?? '' ) );
        $emergency_phone = sanitize_text_field( wp_unslash( $_POST['emergency_phone'] ?? '' ) );

        if ( empty( $emergency_name ) || empty( $emergency_phone ) ) {
            wp_send_json_error( [ 'message' => 'Emergency contact details are required.' ] );
        }

        $wpdb->update(
            $wpdb->prefix . 'sd_drivers',
            [ 'emergency_name' => $emergency_name, 'emergency_phone' => $emergency_phone ],
            [ 'id' => $driver_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        wp_send_json_success( [ 'message' => 'Emergency contact updated successfully.' ] );
    }

    if ( $action === 'upload_avatar' ) {
        if ( empty( $_FILES['selfie'] ) || ! empty( $_FILES['selfie']['error'] ) ) {
            wp_send_json_error( [ 'message' => 'No valid image uploaded.' ] );
        }

        $file = $_FILES['selfie'];
        if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
            wp_send_json_error( [ 'message' => 'Invalid upload.' ] );
        }

        if ( (int) $file['size'] > 5 * 1024 * 1024 ) {
            wp_send_json_error( [ 'message' => 'Image must be 5MB or smaller.' ] );
        }

        $allowed_mimes = [ 'image/jpeg', 'image/png', 'image/webp' ];
        $filetype      = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
        if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed_mimes, true ) ) {
            wp_send_json_error( [ 'message' => 'Upload only JPG, PNG or WEBP images.' ] );
        }

        $upload = wp_upload_dir();
        if ( ! empty( $upload['error'] ) ) {
            wp_send_json_error( [ 'message' => 'Could not save uploaded image.' ] );
        }

        $target_dir = trailingslashit( $upload['basedir'] ) . 'idibia-kyc/' . $driver_id;
        if ( ! wp_mkdir_p( $target_dir ) ) {
            wp_send_json_error( [ 'message' => 'Could not create upload directory.' ] );
        }

        $original = sanitize_file_name( wp_unslash( $file['name'] ) );
        $filename = wp_unique_filename( $target_dir, 'avatar-' . $original );
        $target   = trailingslashit( $target_dir ) . $filename;

        if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
            wp_send_json_error( [ 'message' => 'Could not move uploaded image.' ] );
        }

        $path = 'idibia-kyc/' . $driver_id . '/' . $filename;

        $wpdb->update(
            $wpdb->prefix . 'sd_drivers',
            [ 'avatar_path' => $path ],
            [ 'id' => $driver_id ],
            [ '%s' ],
            [ '%d' ]
        );

        wp_send_json_success( [ 'message' => 'Avatar updated successfully.', 'avatar_path' => $path ] );
    }

    // Fallback if no specific action matched but full_name was provided (legacy compat)
    $full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
    $language = sanitize_text_field( wp_unslash( $_POST['language'] ?? '' ) );

    if ( ! empty( $full_name ) ) {
        wp_update_user( [ 'ID' => $user_id, 'display_name' => $full_name ] );
        [ $first_name, $last_name ] = idibia_split_full_name( $full_name );
        update_user_meta( $user_id, 'first_name', $first_name );
        update_user_meta( $user_id, 'last_name', $last_name );

        $wpdb->update(
            $wpdb->prefix . 'sd_drivers',
            [ 'full_name' => $full_name ],
            [ 'id' => $driver_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    if ( ! empty( $language ) ) {
        update_user_meta( $user_id, 'idibia_driver_language', $language );
    }

    wp_send_json_success( [ 'message' => 'Profile updated successfully.' ] );
}

http_response_code( 405 );
wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
