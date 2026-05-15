<?php
/**
 * Idibia WordPress authentication bootstrap and helpers.
 */

if ( ! defined( 'COOKIEPATH' ) ) {
    define( 'COOKIEPATH', '/' );
}

if ( ! defined( 'SITECOOKIEPATH' ) ) {
    define( 'SITECOOKIEPATH', '/' );
}

if ( ! defined( 'WP_USE_THEMES' ) ) {
    define( 'WP_USE_THEMES', false );
}

if ( ! defined( 'ABSPATH' ) ) {
    if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) && file_exists( rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) . '/wp-load.php' ) ) {
        require_once rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) . '/wp-load.php';
    } elseif ( file_exists( __DIR__ . '/wp/wp-load.php' ) ) {
        require_once __DIR__ . '/wp/wp-load.php';
    } else {
        die( 'WordPress not found.' );
    }
}

add_filter(
    'auth_cookie_expiration',
    static function ( $seconds, $user_id, $remember ) {
        return 30 * DAY_IN_SECONDS;
    },
    99,
    3
);

function idibia_clean_json_buffer(): void {
    while ( ob_get_level() > 0 ) {
        ob_end_clean();
    }
}

function idibia_finish_wordpress_login( WP_User $user ): void {
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, true, is_ssl() );
    do_action( 'wp_login', $user->user_login, $user );
}

function idibia_split_full_name( string $full_name ): array {
    $parts      = preg_split( '/\s+/', trim( $full_name ) );
    $first_name = $parts[0] ?? '';
    $last_name  = trim( implode( ' ', array_slice( $parts ?: [], 1 ) ) );

    return [ $first_name, $last_name ];
}

function idibia_first_name_from_user( WP_User $user ): string {
    $first_name = (string) get_user_meta( $user->ID, 'first_name', true );
    if ( $first_name !== '' ) {
        return $first_name;
    }

    $parts = preg_split( '/\s+/', trim( $user->display_name ) );
    return $parts[0] ?? '';
}

function idibia_find_user_login_by_identifier( string $identifier ): string {
    $identifier = trim( $identifier );

    if ( strpos( $identifier, '@' ) !== false ) {
        $user = get_user_by( 'email', sanitize_email( $identifier ) );
        if ( $user instanceof WP_User ) {
            return $user->user_login;
        }
    }

    return sanitize_text_field( preg_replace( '/[\s\-()]/', '', $identifier ) );
}

function idibia_find_or_create_profile_row( int $user_id, string $type, array $args = [] ): int {
    global $wpdb;

    $table     = $type === 'driver' ? $wpdb->prefix . 'sd_drivers' : $wpdb->prefix . 'sd_customers';
    $meta_key  = $type === 'driver' ? 'idibia_driver_id' : 'idibia_customer_id';
    $stored_id = (int) get_user_meta( $user_id, $meta_key, true );

    if ( $stored_id > 0 ) {
        $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `$table` WHERE id = %d LIMIT 1", $stored_id ) );
        if ( $exists ) {
            return $stored_id;
        }
    }

    $user      = get_userdata( $user_id );
    $full_name = trim( (string) ( $args['full_name'] ?? $user->display_name ?? '' ) );
    $email     = sanitize_email( (string) ( $args['email'] ?? $user->user_email ?? '' ) );
    $phone     = sanitize_text_field( preg_replace( '/[\s\-()]/', '', (string) ( $args['phone'] ?? $user->user_login ?? '' ) ) );

    $existing_by_email = $email ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `$table` WHERE email = %s LIMIT 1", $email ) ) : 0;
    if ( $existing_by_email ) {
        // Normalize: sync email/phone/full_name
        $wpdb->update(
            $table,
            [ 'full_name' => $full_name ?: $phone, 'phone' => $phone ],
            [ 'id' => $existing_by_email ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        update_user_meta( $user_id, $meta_key, $existing_by_email );
        return $existing_by_email;
    }

    $data = [
        'full_name'      => $full_name ?: $phone,
        'email'          => $email,
        'phone'          => $phone,
        'password_hash'  => '',
        'email_verified' => 1,
        'verify_code'    => null,
        'verify_expires' => null,
        'status'         => $type === 'driver' ? 'pending' : 'active',
    ];

    $formats = [ '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ];

    if ( $type === 'customer' ) {
        $data['referral_code'] = 'IDIBIA-' . strtoupper( substr( md5( $email . $user_id . time() ), 0, 5 ) );
        $formats[]             = '%s';
    } else {
        $data['kyc_status'] = 'pending';
        $formats[]          = '%s';
    }

    $wpdb->insert( $table, $data, $formats );
    $profile_id = (int) $wpdb->insert_id;

    if ( $profile_id > 0 ) {
        update_user_meta( $user_id, $meta_key, $profile_id );
    }

    return $profile_id;
}
