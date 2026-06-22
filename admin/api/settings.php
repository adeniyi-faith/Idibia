<?php
/** Idibia — Admin API: Platform Settings & Operational Zones */

function idibia_admin_save_settings(): void {
    global $wpdb;
    $raw = file_get_contents( 'php://input' );
    $settings = json_decode( $raw, true );
    if ( ! is_array( $settings ) ) {
        $settings = $_POST['settings'] ?? $_POST;
    }
    unset( $settings['action'] );

    if ( ! empty( $settings ) ) {
        $values       = [];
        $placeholders = [];
        foreach ( $settings as $key => $value ) {
            $sanitized_key = sanitize_key( $key );
            $sanitized_value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : wp_json_encode( $value );

            // Field-level validation
            if ( in_array( $sanitized_key, ['platform_commission_pct', 'surge_multiplier_cap', 'min_fare', 'max_delivery_radius_km'] ) ) {
                if ( ! is_numeric( $sanitized_value ) || (float) $sanitized_value < 0 ) {
                    wp_send_json_error( [ 'message' => "Invalid value for {$sanitized_key}. Must be a positive number." ] );
                }
            }

            if ( in_array( $sanitized_key, ['dispatch_retry_limit', 'trip_timeout_minutes', 'scheduled_dispatch_advance_minutes'] ) ) {
                if ( ! ctype_digit( $sanitized_value ) || (int) $sanitized_value < 1 ) {
                    wp_send_json_error( [ 'message' => "Invalid value for {$sanitized_key}. Must be a positive integer." ] );
                }
            }

            // Do not overwrite secrets if they are blank or masked
            if ( in_array( $sanitized_key, ['paystack_secret_key', 'flutterwave_secret_key', 'pusher_secret'] ) ) {
                if ( $sanitized_value === '' || $sanitized_value === '********' ) {
                    continue; // Skip updating this secret
                }
            }

            $placeholders[] = '(%s, %s)';
            $values[]       = $sanitized_key;
            $values[]       = $sanitized_value;
        }

        if ( ! empty( $placeholders ) ) {
            $query = "REPLACE INTO `{$wpdb->prefix}sd_settings` (`setting_key`, `setting_value`) VALUES " . implode( ', ', $placeholders );
            $wpdb->query( $wpdb->prepare( $query, $values ) );

            // Log the action
            idibia_admin_audit_log( 'save_settings', 'settings', 0, array_keys($settings) );
        }
    }

    wp_send_json_success( [ 'message' => 'Settings saved.' ] );
}

function idibia_admin_get_zones(): void {
    global $wpdb;
    $zones = $wpdb->get_results(
        "SELECT id, name, center_lat, center_lng, radius_km, is_active, created_at FROM `{$wpdb->prefix}sd_operational_zones` ORDER BY created_at DESC",
        ARRAY_A
    ) ?: [];
    foreach ( $zones as &$z ) {
        $z['id']         = (int) $z['id'];
        $z['center_lat'] = (float) $z['center_lat'];
        $z['center_lng'] = (float) $z['center_lng'];
        $z['radius_km']  = (float) $z['radius_km'];
        $z['is_active']  = (bool) $z['is_active'];
    }
    wp_send_json_success( [ 'zones' => $zones ] );
}

function idibia_admin_create_zone(): void {
    global $wpdb;
    $name       = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
    $center_lat = isset( $_POST['center_lat'] ) ? (float) $_POST['center_lat'] : null;
    $center_lng = isset( $_POST['center_lng'] ) ? (float) $_POST['center_lng'] : null;
    $radius_km  = isset( $_POST['radius_km'] )  ? (float) $_POST['radius_km']  : null;
    $is_active  = isset( $_POST['is_active'] )  ? (int) (bool) $_POST['is_active'] : 1;

    if ( ! $name || $center_lat === null || $center_lng === null || $radius_km === null ) {
        wp_send_json_error( [ 'message' => 'name, center_lat, center_lng, and radius_km are required.' ] );
    }
    if ( $center_lat < -90 || $center_lat > 90 || $center_lng < -180 || $center_lng > 180 ) {
        wp_send_json_error( [ 'message' => 'Invalid coordinates.' ] );
    }
    if ( $radius_km <= 0 ) {
        wp_send_json_error( [ 'message' => 'radius_km must be greater than 0.' ] );
    }

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'sd_operational_zones',
        [ 'name' => $name, 'center_lat' => $center_lat, 'center_lng' => $center_lng, 'radius_km' => $radius_km, 'is_active' => $is_active ],
        [ '%s', '%f', '%f', '%f', '%d' ]
    );
    if ( false === $inserted ) {
        wp_send_json_error( [ 'message' => 'Failed to create zone.' ] );
    }
    idibia_admin_audit_log( 'create_zone', 'operational_zone', (int) $wpdb->insert_id );
    wp_send_json_success( [ 'zone_id' => (int) $wpdb->insert_id, 'message' => 'Zone created.' ] );
}

function idibia_admin_update_zone(): void {
    global $wpdb;
    $zone_id = absint( $_POST['zone_id'] ?? 0 );
    if ( ! $zone_id ) {
        wp_send_json_error( [ 'message' => 'zone_id is required.' ] );
    }
    $zone = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM `{$wpdb->prefix}sd_operational_zones` WHERE id = %d LIMIT 1", $zone_id ) );
    if ( ! $zone ) {
        wp_send_json_error( [ 'message' => 'Zone not found.' ] );
    }

    $fields  = [];
    $formats = [];
    if ( isset( $_POST['name'] ) ) {
        $fields['name'] = sanitize_text_field( wp_unslash( $_POST['name'] ) );
        $formats[]      = '%s';
    }
    if ( isset( $_POST['center_lat'] ) ) {
        $lat = (float) $_POST['center_lat'];
        if ( $lat < -90 || $lat > 90 ) wp_send_json_error( [ 'message' => 'Invalid latitude.' ] );
        $fields['center_lat'] = $lat;
        $formats[]            = '%f';
    }
    if ( isset( $_POST['center_lng'] ) ) {
        $lng = (float) $_POST['center_lng'];
        if ( $lng < -180 || $lng > 180 ) wp_send_json_error( [ 'message' => 'Invalid longitude.' ] );
        $fields['center_lng'] = $lng;
        $formats[]            = '%f';
    }
    if ( isset( $_POST['radius_km'] ) ) {
        $r = (float) $_POST['radius_km'];
        if ( $r <= 0 ) wp_send_json_error( [ 'message' => 'radius_km must be greater than 0.' ] );
        $fields['radius_km'] = $r;
        $formats[]           = '%f';
    }
    if ( isset( $_POST['is_active'] ) ) {
        $fields['is_active'] = (int) (bool) $_POST['is_active'];
        $formats[]           = '%d';
    }

    if ( empty( $fields ) ) {
        wp_send_json_error( [ 'message' => 'No fields to update.' ] );
    }

    $wpdb->update( $wpdb->prefix . 'sd_operational_zones', $fields, [ 'id' => $zone_id ], $formats, [ '%d' ] );
    idibia_admin_audit_log( 'update_zone', 'operational_zone', $zone_id );
    wp_send_json_success( [ 'message' => 'Zone updated.' ] );
}

function idibia_admin_delete_zone(): void {
    global $wpdb;
    $zone_id = absint( $_POST['zone_id'] ?? 0 );
    if ( ! $zone_id ) {
        wp_send_json_error( [ 'message' => 'zone_id is required.' ] );
    }
    $deleted = $wpdb->delete( $wpdb->prefix . 'sd_operational_zones', [ 'id' => $zone_id ], [ '%d' ] );
    if ( false === $deleted || $deleted === 0 ) {
        wp_send_json_error( [ 'message' => 'Zone not found or could not be deleted.' ] );
    }
    idibia_admin_audit_log( 'delete_zone', 'operational_zone', $zone_id );
    wp_send_json_success( [ 'message' => 'Zone deleted.' ] );
}
