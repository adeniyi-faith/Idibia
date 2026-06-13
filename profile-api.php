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
    $action = sanitize_text_field( wp_unslash( $_POST['action'] ?? '' ) );
    $customer_id = $GLOBALS['auth_customer_id'];

    if ( $action === 'upload_avatar' ) {
        if ( empty( $_FILES['avatar'] ) || ! empty( $_FILES['avatar']['error'] ) ) {
            wp_send_json_error( [ 'message' => 'No valid image uploaded.' ] );
        }

        $file = $_FILES['avatar'];
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

        $target_dir = trailingslashit( $upload['basedir'] ) . 'idibia-avatars/customer-' . $customer_id;
        if ( ! wp_mkdir_p( $target_dir ) ) {
            wp_send_json_error( [ 'message' => 'Could not create upload directory.' ] );
        }

        // Ensure images are served directly. Always overwrite to fix any bad .htaccess.
        $htaccess = trailingslashit( $upload['basedir'] ) . 'idibia-avatars/.htaccess';
        file_put_contents( $htaccess, "Options -Indexes\n" );

        $original = sanitize_file_name( wp_unslash( $file['name'] ) );
        $filename = wp_unique_filename( $target_dir, 'avatar-' . $original );
        $target   = trailingslashit( $target_dir ) . $filename;

        if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
            wp_send_json_error( [ 'message' => 'Could not move uploaded image.' ] );
        }

        $path = 'idibia-avatars/customer-' . $customer_id . '/' . $filename;
        $avatar_url = trailingslashit( $upload['baseurl'] ) . $path;

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'sd_customers',
            [ 'avatar_path' => $path ],
            [ 'id' => $customer_id ],
            [ '%s' ],
            [ '%d' ]
        );

        wp_send_json_success( [ 'message' => 'Avatar updated successfully.', 'avatar_path' => $path, 'avatar_url' => $avatar_url ] );
    }

    $full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
    $phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
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
            [ 'full_name' => $full_name, 'phone' => $phone ],
            [ 'id' => $customer_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
    }
    wp_send_json_success( [ 'message' => 'Profile updated successfully.' ] );
}

http_response_code( 405 );
wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
