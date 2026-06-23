<?php
/** Idibia — Admin API: Platform Settings & Operational Zones */

function idibia_admin_save_settings(): void {
    global $wpdb, $admin_id;

    $raw      = file_get_contents( 'php://input' );
    $settings = json_decode( $raw, true );
    if ( ! is_array( $settings ) ) {
        $settings = $_POST['settings'] ?? $_POST;
    }
    unset( $settings['action'] );

    if ( empty( $settings ) ) {
        wp_send_json_success( [ 'message' => 'Settings saved.' ] );
    }

    // Build a clean key→value map, validating and filtering as we go.
    $to_save = [];
    foreach ( $settings as $key => $value ) {
        $sanitized_key   = sanitize_key( $key );
        $sanitized_value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : wp_json_encode( $value );

        if ( in_array( $sanitized_key, [ 'platform_commission_pct', 'surge_multiplier_cap', 'min_fare', 'max_delivery_radius_km' ] ) ) {
            if ( ! is_numeric( $sanitized_value ) || (float) $sanitized_value < 0 ) {
                wp_send_json_error( [ 'message' => "Invalid value for {$sanitized_key}. Must be a positive number." ] );
            }
        }
        if ( in_array( $sanitized_key, [ 'dispatch_retry_limit', 'trip_timeout_minutes', 'scheduled_dispatch_advance_minutes', 'trip_expiry_hours' ] ) ) {
            if ( ! ctype_digit( $sanitized_value ) || (int) $sanitized_value < 1 ) {
                wp_send_json_error( [ 'message' => "Invalid value for {$sanitized_key}. Must be a positive integer." ] );
            }
        }
        if ( in_array( $sanitized_key, [ 'paystack_secret_key', 'flutterwave_secret_key', 'pusher_secret' ] ) ) {
            if ( $sanitized_value === '' || $sanitized_value === '********' ) {
                continue; // Never overwrite masked or empty secrets
            }
        }
        $to_save[ $sanitized_key ] = $sanitized_value;
    }

    if ( empty( $to_save ) ) {
        wp_send_json_success( [ 'message' => 'Settings saved.' ] );
    }

    // Ensure the history table exists (safe no-op if it was already created by migration v19).
    $history_table = $wpdb->prefix . 'sd_settings_history';
    $wpdb->query( "CREATE TABLE IF NOT EXISTS `$history_table` (
        `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `setting_key`         VARCHAR(80)     NOT NULL,
        `old_value`           TEXT            NULL,
        `new_value`           TEXT            NULL,
        `changed_by_admin_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `changed_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `setting_key`         (`setting_key`),
        KEY `changed_by_admin_id` (`changed_by_admin_id`),
        KEY `changed_at`          (`changed_at`)
    ) " . $wpdb->get_charset_collate() );

    // Read the current values so we can record what changed.
    $key_placeholders = implode( ',', array_fill( 0, count( $to_save ), '%s' ) );
    $current_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT setting_key, setting_value FROM `{$wpdb->prefix}sd_settings` WHERE setting_key IN ($key_placeholders)",
        array_keys( $to_save )
    ), ARRAY_A );
    $current_values = [];
    foreach ( $current_rows as $row ) {
        $current_values[ $row['setting_key'] ] = $row['setting_value'];
    }

    // Write one history row per key that is actually changing.
    $resolved_admin_id = ( isset( $admin_id ) && $admin_id > 0 ) ? (int) $admin_id : get_current_user_id();
    $now               = gmdate( 'Y-m-d H:i:s' );
    foreach ( $to_save as $key => $new_value ) {
        $old_value = $current_values[ $key ] ?? null;
        if ( $old_value !== $new_value ) {
            $wpdb->insert( $history_table, [
                'setting_key'         => $key,
                'old_value'           => $old_value,
                'new_value'           => $new_value,
                'changed_by_admin_id' => $resolved_admin_id,
                'changed_at'          => $now,
            ], [ '%s', '%s', '%s', '%d', '%s' ] );
        }
    }

    // Save the new values.
    $placeholders = [];
    $values       = [];
    foreach ( $to_save as $key => $val ) {
        $placeholders[] = '(%s, %s)';
        $values[]       = $key;
        $values[]       = $val;
    }
    $query = "REPLACE INTO `{$wpdb->prefix}sd_settings` (`setting_key`, `setting_value`) VALUES " . implode( ', ', $placeholders );
    $wpdb->query( $wpdb->prepare( $query, $values ) );

    // Audit log: record the full before/after snapshot.
    idibia_admin_audit_log( 'save_settings', 'settings', 0, [], $current_values, $to_save );

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

function idibia_admin_get_settings_history(): void {
    global $wpdb;

    [ $page, $per_page, $offset ] = idibia_page_args();
    $setting_key = sanitize_key( $_GET['setting_key'] ?? '' );
    $key_prefix  = sanitize_key( $_GET['key_prefix']  ?? '' );

    $where = [ '1=1' ];
    $args  = [];
    if ( $setting_key ) {
        $where[] = 'h.setting_key = %s';
        $args[]  = $setting_key;
    } elseif ( $key_prefix ) {
        $where[] = 'h.setting_key LIKE %s';
        $args[]  = $wpdb->esc_like( $key_prefix ) . '%';
    }

    $sql_where = implode( ' AND ', $where );
    $table     = $wpdb->prefix . 'sd_settings_history';

    $total = (int) $wpdb->get_var( idibia_sql(
        "SELECT COUNT(*) FROM `$table` h WHERE $sql_where",
        $args
    ) );

    $rows = $wpdb->get_results( idibia_sql(
        "SELECT h.*, u.full_name AS changed_by_name
         FROM `$table` h
         LEFT JOIN `{$wpdb->prefix}sd_admin_users` u ON u.id = h.changed_by_admin_id
         WHERE $sql_where
         ORDER BY h.changed_at DESC
         LIMIT %d OFFSET %d",
        array_merge( $args, [ $per_page, $offset ] )
    ), ARRAY_A ) ?: [];

    foreach ( $rows as &$row ) {
        $row['id']                  = (int) $row['id'];
        $row['changed_by_admin_id'] = (int) $row['changed_by_admin_id'];
    }
    unset( $row );

    wp_send_json_success( [
        'history'  => $rows,
        'page'     => $page,
        'per_page' => $per_page,
        'total'    => $total,
    ] );
}
