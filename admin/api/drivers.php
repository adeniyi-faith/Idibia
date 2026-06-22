<?php
/** Idibia — Admin API: Drivers & KYC */

function idibia_admin_paginated_drivers(): void {
    global $wpdb;
    [ $page, $per_page, $offset ] = idibia_page_args();
    $where = [ '1=1' ];
    $args = [];
    $kyc_status     = sanitize_text_field( wp_unslash( $_GET['kyc_status'] ?? '' ) );
    $status         = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
    $search         = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
    $is_resubmit    = ! empty( $_GET['is_resubmission'] );
    if ( $kyc_status ) { $where[] = 'kyc_status = %s'; $args[] = $kyc_status; }
    if ( $is_resubmit ) { $where[] = "kyc_rejection_history IS NOT NULL AND kyc_rejection_history != ''"; }
    if ( $status && in_array( $status, [ 'pending', 'active', 'suspended' ], true ) ) { $where[] = 'status = %s'; $args[] = $status; }
    if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = '(full_name LIKE %s OR email LIKE %s OR phone LIKE %s)'; array_push( $args, $like, $like, $like ); }
    $sql_where = implode( ' AND ', $where );
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE $sql_where", $args ) );
    $rows = $wpdb->get_results( idibia_sql( "SELECT * FROM `{$wpdb->prefix}sd_drivers` WHERE $sql_where ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A );
    $rows = array_map( 'idibia_admin_prepare_driver', $rows ?: [] );
    wp_send_json_success( [
        'drivers'         => $rows,
        'page'            => $page,
        'per_page'        => $per_page,
        'total'           => $total,
        'upload_base_url' => trailingslashit( wp_upload_dir()['baseurl'] ?? '' ),
    ] );
}

function idibia_admin_prepare_driver( array $driver ): array {
    $user = ! empty( $driver['email'] ) ? get_user_by( 'email', $driver['email'] ) : false;
    if ( $user instanceof WP_User ) {
        $driver['user_id']         = $user->ID;
        $driver['first_name']      = (string) get_user_meta( $user->ID, 'first_name', true );
        $driver['last_name']       = (string) get_user_meta( $user->ID, 'last_name', true );
        $driver['language']        = (string) get_user_meta( $user->ID, 'idibia_driver_language', true );
        $driver['middle_name']     = (string) get_user_meta( $user->ID, 'idibia_driver_middle_name', true );
        $driver['date_of_birth']   = (string) get_user_meta( $user->ID, 'idibia_driver_date_of_birth', true );
        $driver['gender']          = (string) get_user_meta( $user->ID, 'idibia_driver_gender', true );
        $driver['state_of_origin'] = (string) get_user_meta( $user->ID, 'idibia_driver_state_of_origin', true );
    }

    return $driver;
}

function idibia_admin_kyc_action(): void {
    global $wpdb;
    $driver_id = absint( $_POST['driver_id'] ?? 0 );
    $decision  = sanitize_text_field( wp_unslash( $_POST['decision'] ?? '' ) );
    $notes     = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
    if ( ! in_array( $decision, [ 'approved', 'rejected' ], true ) ) wp_send_json_error( [ 'message' => 'Invalid decision.' ] );
    $current = $wpdb->get_row( $wpdb->prepare( "SELECT kyc_status, kyc_rejection_history FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $driver_id ) );
    if ( ! $current || $current->kyc_status !== 'under_review' ) {
        wp_send_json_error( [ 'message' => 'This KYC record has already been resolved.' ] );
    }
    $status = $decision === 'approved' ? 'active' : 'pending';
    $update_data = [ 'kyc_status' => $decision, 'status' => $status, 'kyc_notes' => $notes ];
    $update_fmt  = [ '%s', '%s', '%s' ];
    $updated = $wpdb->update( $wpdb->prefix . 'sd_drivers', $update_data, [ 'id' => $driver_id, 'kyc_status' => 'under_review' ], $update_fmt, [ '%d', '%s' ] );
    if ( false === $updated ) wp_send_json_error( [ 'message' => 'Could not update driver.' ] );
    $driver = $wpdb->get_row( $wpdb->prepare( "SELECT email, full_name FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d", $driver_id ) );
    if ( $driver ) {
        $user = get_user_by( 'email', $driver->email );
        if ( $user instanceof WP_User ) {
            update_user_meta( $user->ID, 'idibia_kyc_status', $decision );
            update_user_meta( $user->ID, 'idibia_account_status', $status );
        }
        $is_resubmission = ! empty( $current->kyc_rejection_history );
        $subject = $is_resubmission
            ? '[Idibia] Driver resubmission ' . $decision
            : '[Idibia] Driver application update';
        idibia_send_mail(
            $driver->email,
            $subject,
            "Hi {$driver->full_name},\n\nYour KYC application was $decision.\n\n$notes",
            [ 'Content-Type: text/plain; charset=UTF-8' ]
        );
    }
    idibia_admin_audit_log( $decision === 'approved' ? 'approve_kyc' : 'reject_kyc', 'driver', $driver_id, [ 'decision' => $decision, 'notes' => $notes ] );
    wp_send_json_success( [ 'message' => 'KYC updated.' ] );
}

function idibia_admin_get_kyc_policy(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'sd_kyc_policy';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
        wp_send_json_success( [ 'policies' => [] ] );
    }
    $rows = $wpdb->get_results( "SELECT vehicle_type, required_documents, selfie_required, min_age FROM `$table`", ARRAY_A ) ?: [];
    $policies = [];
    foreach ( $rows as $row ) {
        $policies[ $row['vehicle_type'] ] = [
            'required_documents' => json_decode( $row['required_documents'] ?? '[]', true ) ?: [],
            'selfie_required'    => (bool) $row['selfie_required'],
            'min_age'            => (int) $row['min_age'],
        ];
    }
    wp_send_json_success( [ 'policies' => $policies ] );
}

function idibia_admin_save_kyc_policy(): void {
    global $wpdb;
    $raw      = file_get_contents( 'php://input' );
    $body     = json_decode( $raw, true );
    $policies = $body['policies'] ?? $_POST['policies'] ?? null;
    if ( is_string( $policies ) ) {
        $policies = json_decode( $policies, true );
    }
    if ( ! is_array( $policies ) ) {
        wp_send_json_error( [ 'message' => 'Invalid policy data.' ] );
    }
    $table        = $wpdb->prefix . 'sd_kyc_policy';
    $valid_types  = [ 'bike', 'car', 'van', 'keke' ];
    $valid_docs   = [ 'government_id', 'drivers_license', 'vehicle_insurance', 'vehicle_registration', 'proof_of_ownership' ];
    foreach ( $policies as $vehicle_type => $policy ) {
        $vehicle_type = sanitize_key( $vehicle_type );
        if ( ! in_array( $vehicle_type, $valid_types, true ) ) continue;
        $docs = is_array( $policy['required_documents'] ?? null )
            ? array_values( array_filter( $policy['required_documents'], fn( $d ) => in_array( $d, $valid_docs, true ) ) )
            : [];
        $selfie  = ! empty( $policy['selfie_required'] ) ? 1 : 0;
        $min_age = max( 16, min( 99, (int) ( $policy['min_age'] ?? 18 ) ) );
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO `$table` (vehicle_type, required_documents, selfie_required, min_age)
             VALUES (%s, %s, %d, %d)
             ON DUPLICATE KEY UPDATE required_documents = VALUES(required_documents), selfie_required = VALUES(selfie_required), min_age = VALUES(min_age), updated_at = NOW()",
            $vehicle_type, wp_json_encode( $docs ), $selfie, $min_age
        ) );
    }
    idibia_admin_audit_log( 'save_kyc_policy', 'settings', 0, array_keys( $policies ) );
    wp_send_json_success( [ 'message' => 'KYC policy saved.' ] );
}

function idibia_admin_get_blacklist(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'sd_blacklist';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
        wp_send_json_success( [ 'blacklist' => [] ] );
    }
    $rows = $wpdb->get_results(
        "SELECT bl.*, au.full_name AS banned_by_name
         FROM `$table` bl
         LEFT JOIN `{$wpdb->prefix}sd_admin_users` au ON au.id = bl.banned_by_admin_id
         ORDER BY bl.created_at DESC LIMIT 500",
        ARRAY_A
    ) ?: [];
    wp_send_json_success( [ 'blacklist' => $rows ] );
}

function idibia_admin_add_to_blacklist(): void {
    global $wpdb, $admin_id;
    $identifier_type  = sanitize_key( $_POST['identifier_type'] ?? '' );
    $identifier_value = sanitize_text_field( wp_unslash( $_POST['identifier_value'] ?? '' ) );
    $reason           = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
    if ( ! in_array( $identifier_type, [ 'phone', 'email', 'device_id' ], true ) ) {
        wp_send_json_error( [ 'message' => 'Invalid identifier type.' ] );
    }
    if ( $identifier_value === '' ) {
        wp_send_json_error( [ 'message' => 'Identifier value is required.' ] );
    }
    if ( $reason === '' ) {
        wp_send_json_error( [ 'message' => 'A reason is required.' ] );
    }
    $table = $wpdb->prefix . 'sd_blacklist';
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM `$table` WHERE identifier_type = %s AND identifier_value = %s LIMIT 1",
        $identifier_type, $identifier_value
    ) );
    if ( $existing ) {
        wp_send_json_error( [ 'message' => 'This identifier is already blacklisted.' ] );
    }
    $inserted = $wpdb->insert( $table, [
        'identifier_type'    => $identifier_type,
        'identifier_value'   => $identifier_value,
        'reason'             => $reason,
        'banned_by_admin_id' => (int) $admin_id,
        'created_at'         => gmdate( 'Y-m-d H:i:s' ),
    ], [ '%s', '%s', '%s', '%d', '%s' ] );
    if ( ! $inserted ) {
        wp_send_json_error( [ 'message' => 'Could not add to blacklist.' ] );
    }
    idibia_admin_audit_log( 'add_to_blacklist', 'blacklist', (int) $wpdb->insert_id, [
        'identifier_type'  => $identifier_type,
        'identifier_value' => $identifier_value,
        'reason'           => $reason,
    ] );
    wp_send_json_success( [ 'message' => 'Added to blacklist.' ] );
}

function idibia_admin_remove_from_blacklist(): void {
    global $wpdb;
    $id = absint( $_POST['blacklist_id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( [ 'message' => 'Invalid ID.' ] );
    }
    $table   = $wpdb->prefix . 'sd_blacklist';
    $deleted = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
    if ( ! $deleted ) {
        wp_send_json_error( [ 'message' => 'Entry not found.' ] );
    }
    idibia_admin_audit_log( 'remove_from_blacklist', 'blacklist', $id );
    wp_send_json_success( [ 'message' => 'Removed from blacklist.' ] );
}

function idibia_admin_reset_driver_password(): void {
    global $wpdb, $admin_id;
    $driver_id = absint( $_POST['driver_id'] ?? 0 );
    if ( ! $driver_id ) {
        wp_send_json_error( [ 'message' => 'Driver ID required.' ] );
    }

    $driver = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, email, full_name FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1",
        $driver_id
    ) );
    if ( ! $driver ) {
        wp_send_json_error( [ 'message' => 'Driver not found.' ] );
    }

    // Generate a secure random temporary password (12 chars: letters + digits)
    $chars    = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $temp_pwd = '';
    for ( $i = 0; $i < 12; $i++ ) {
        $temp_pwd .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
    }

    $hash = wp_hash_password( $temp_pwd );

    // Update driver record: set flag and store hash
    $updated = $wpdb->update(
        $wpdb->prefix . 'sd_drivers',
        [
            'force_password_change' => 1,
            'temp_password_hash'    => $hash,
        ],
        [ 'id' => $driver_id ],
        [ '%d', '%s' ],
        [ '%d' ]
    );

    if ( false === $updated ) {
        wp_send_json_error( [ 'message' => 'Could not update driver record.' ] );
    }

    // Also update the WordPress user password so wp_signon() uses the new temp password
    $wp_user = get_user_by( 'email', $driver->email );
    if ( $wp_user instanceof WP_User ) {
        wp_set_password( $temp_pwd, $wp_user->ID );
    }

    // Send email with temp password
    $first_name = explode( ' ', trim( $driver->full_name ) )[0];
    $body = <<<BODY
Hi {$first_name},

Your Idibia driver account password has been reset by an administrator.

Your temporary password is: {$temp_pwd}

Please log in to the Idibia driver app and change your password immediately when prompted.

If you did not request this reset, please contact support immediately.

— Idibia Platform
BODY;

    idibia_send_mail(
        $driver->email,
        '[Idibia] Your driver account password has been reset',
        $body,
        [ 'Content-Type: text/plain; charset=UTF-8' ]
    );

    idibia_admin_audit_log(
        'reset_driver_password',
        'driver',
        $driver_id,
        [ 'admin_id' => $admin_id, 'email_sent_to' => $driver->email ]
    );

    wp_send_json_success( [ 'message' => 'Password reset. Temporary password sent to ' . $driver->email . '.' ] );
}

function idibia_admin_suspend_driver(): void {
    global $wpdb;
    $driver_id = absint( $_POST['driver_id'] ?? 0 );
    $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
    $updated = $wpdb->update( $wpdb->prefix . 'sd_drivers', [ 'status' => 'suspended', 'is_online' => 0, 'kyc_notes' => $reason ], [ 'id' => $driver_id ], [ '%s', '%d', '%s' ], [ '%d' ] );
    if ( false === $updated ) wp_send_json_error( [ 'message' => 'Could not suspend driver.' ] );
    $driver = $wpdb->get_row( $wpdb->prepare( "SELECT email, full_name FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d", $driver_id ) );
    if ( $driver ) {
        $user = get_user_by( 'email', $driver->email );
        if ( $user instanceof WP_User ) {
            update_user_meta( $user->ID, 'idibia_account_status', 'suspended' );
        }
        idibia_send_mail( $driver->email, '[Idibia] Driver account suspended', "Hi {$driver->full_name},\n\nYour driver account has been suspended.\n\nReason: $reason", [ 'Content-Type: text/plain; charset=UTF-8' ] );
    }
    idibia_admin_audit_log( 'suspend_driver', 'driver', $driver_id, [ 'reason' => $reason ] );
    wp_send_json_success( [ 'message' => 'Driver suspended.' ] );
}
