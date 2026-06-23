<?php
/** Idibia — Admin API: Customers */

function idibia_admin_paginated_customers(): void {
    global $wpdb;
    [ $page, $per_page, $offset ] = idibia_page_args();
    $where = [ '1=1' ]; $args = [];
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
    $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
    if ( $status ) { $where[] = 'status = %s'; $args[] = $status; }
    if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = '(full_name LIKE %s OR email LIKE %s OR phone LIKE %s)'; array_push( $args, $like, $like, $like ); }
    $sql_where = implode( ' AND ', $where );
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_customers` WHERE $sql_where", $args ) );
    $rows = $wpdb->get_results( idibia_sql( "SELECT id, full_name, email, phone, email_verified, status, created_at FROM `{$wpdb->prefix}sd_customers` WHERE $sql_where ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A );
    wp_send_json_success( [ 'customers' => $rows, 'page' => $page, 'per_page' => $per_page, 'total' => $total ] );
}

function idibia_admin_get_customer(): void {
    global $wpdb;
    $customer_id = absint( $_GET['customer_id'] ?? 0 );
    if ( ! $customer_id ) { wp_send_json_error( [ 'message' => 'customer_id required.' ], 400 ); }
    $c = $wpdb->get_row( $wpdb->prepare(
        "SELECT c.*,
            COUNT(DISTINCT t.id) AS total_trips,
            COALESCE(SUM(CASE WHEN t.status='completed' THEN COALESCE(NULLIF(t.final_fare,0),NULLIF(t.fare_estimate,0),t.fare,0) ELSE 0 END),0) AS total_spent,
            MAX(t.created_at) AS last_trip_at
        FROM `{$wpdb->prefix}sd_customers` c
        LEFT JOIN `{$wpdb->prefix}sd_trips` t ON t.customer_id = c.id
        WHERE c.id = %d
        GROUP BY c.id
        LIMIT 1",
        $customer_id
    ), ARRAY_A );
    if ( ! $c ) { wp_send_json_error( [ 'message' => 'Customer not found.' ] ); }
    $ratings_table = $wpdb->prefix . 'sd_ratings';
    $table_exists  = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $ratings_table ) );
    if ( $table_exists ) {
        $c['avg_rating'] = $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(rating) FROM `$ratings_table` WHERE subject_id = %d AND subject_type = 'customer'",
            $customer_id
        ) );
    } else {
        $c['avg_rating'] = null;
    }
    wp_send_json_success( [ 'customer' => $c ] );
}

function idibia_admin_suspend_customer(): void {
    global $wpdb;
    $customer_id = absint( $_POST['customer_id'] ?? 0 );
    $reason      = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
    if ( ! $customer_id ) { wp_send_json_error( [ 'message' => 'customer_id required.' ], 400 ); }
    $updated = $wpdb->update( $wpdb->prefix . 'sd_customers', [ 'status' => 'suspended' ], [ 'id' => $customer_id ], [ '%s' ], [ '%d' ] );
    if ( false === $updated ) { wp_send_json_error( [ 'message' => 'Could not suspend customer.' ] ); }
    idibia_admin_audit_log( 'suspend_customer', 'customer', $customer_id, [ 'reason' => $reason ] );
    wp_send_json_success( [ 'message' => 'Customer suspended.' ] );
}

function idibia_admin_reinstate_customer(): void {
    global $wpdb;
    $customer_id = absint( $_POST['customer_id'] ?? 0 );
    if ( ! $customer_id ) { wp_send_json_error( [ 'message' => 'customer_id required.' ], 400 ); }
    $updated = $wpdb->update( $wpdb->prefix . 'sd_customers', [ 'status' => 'active' ], [ 'id' => $customer_id ], [ '%s' ], [ '%d' ] );
    if ( false === $updated ) { wp_send_json_error( [ 'message' => 'Could not reinstate customer.' ] ); }
    idibia_admin_audit_log( 'reinstate_customer', 'customer', $customer_id, [] );
    wp_send_json_success( [ 'message' => 'Customer reinstated.' ] );
}

// -------------------------------------------------------------------------
// CUSTOMER DETAIL (admin)
// -------------------------------------------------------------------------

function idibia_admin_get_customer_detail(): void {
    global $wpdb;
    $customer_id = absint( $_GET['customer_id'] ?? 0 );
    if ( ! $customer_id ) {
        wp_send_json_error( [ 'message' => 'customer_id required.' ], 400 );
    }

    // Guard for columns added in DB migrations 20/21 that may not exist yet.
    $table   = $wpdb->prefix . 'sd_customers';
    $columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table`", 0 ) ?: [];
    if ( ! in_array( 'rating', $columns, true ) ) {
        $wpdb->query( "ALTER TABLE `$table` ADD COLUMN `rating` DECIMAL(3,2) NOT NULL DEFAULT 0.00 AFTER `status`" );
    }
    if ( ! in_array( 'total_trips', $columns, true ) ) {
        $wpdb->query( "ALTER TABLE `$table` ADD COLUMN `total_trips` INT UNSIGNED NOT NULL DEFAULT 0" );
    }
    if ( ! in_array( 'wallet_balance', $columns, true ) ) {
        $wpdb->query( "ALTER TABLE `$table` ADD COLUMN `wallet_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00" );
    }

    $customer = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, full_name, email, phone, email_verified, status, wallet_balance, rating, total_trips, created_at
             FROM `{$wpdb->prefix}sd_customers` WHERE id = %d LIMIT 1",
            $customer_id
        ),
        ARRAY_A
    );

    if ( ! $customer ) {
        wp_send_json_error( [ 'message' => 'Customer not found.' ], 404 );
    }

    $ledger = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT amount, entry_type, description, created_at
             FROM `{$wpdb->prefix}sd_customer_wallet_ledger`
             WHERE customer_id = %d ORDER BY created_at DESC LIMIT 20",
            $customer_id
        ),
        ARRAY_A
    );

    wp_send_json_success( [ 'customer' => $customer, 'ledger' => $ledger ?: [] ] );
}

// -------------------------------------------------------------------------
// ADMIN MANUAL CUSTOMER WALLET CREDIT
// -------------------------------------------------------------------------

function idibia_admin_credit_customer_wallet(): void {
    global $wpdb, $admin_id;

    $customer_id = absint( $_POST['customer_id'] ?? 0 );
    $amount      = (float) ( $_POST['amount'] ?? 0 );
    $reason      = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

    if ( ! $customer_id ) {
        wp_send_json_error( [ 'message' => 'customer_id required.' ], 400 );
    }
    if ( $amount <= 0 ) {
        wp_send_json_error( [ 'message' => 'amount must be greater than zero.' ], 400 );
    }
    if ( ! $reason ) {
        wp_send_json_error( [ 'message' => 'A reason is required.' ], 400 );
    }

    $customer = $wpdb->get_row( $wpdb->prepare( "SELECT id, full_name FROM `{$wpdb->prefix}sd_customers` WHERE id = %d LIMIT 1", $customer_id ), ARRAY_A );
    if ( ! $customer ) {
        wp_send_json_error( [ 'message' => 'Customer not found.' ], 404 );
    }

    // Ensure wallet_balance column exists (guard for environments where the migration hasn't run)
    $wpdb->query( "ALTER TABLE `{$wpdb->prefix}sd_customers` ADD COLUMN IF NOT EXISTS `wallet_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00" );

    idibia_transaction_start();

    $updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE `{$wpdb->prefix}sd_customers` SET wallet_balance = wallet_balance + %f WHERE id = %d",
            $amount,
            $customer_id
        )
    );

    if ( false === $updated ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Could not update customer wallet.' ] );
    }

    $wpdb->insert(
        $wpdb->prefix . 'sd_customer_wallet_ledger',
        [
            'customer_id'  => $customer_id,
            'amount'       => $amount,
            'entry_type'   => 'credit',
            'reference_id' => 0,
            'description'  => $reason,
        ],
        [ '%d', '%f', '%s', '%d', '%s' ]
    );

    idibia_transaction_commit();

    idibia_notify_user( $customer_id, 'customer', 'Wallet Credited', "₦" . number_format( $amount, 2 ) . " has been added to your wallet. Reason: $reason" );

    idibia_admin_audit_log( 'admin_credit_customer_wallet', 'customer', $customer_id, [
        'amount' => $amount,
        'reason' => $reason,
    ] );

    $new_balance = (float) $wpdb->get_var( $wpdb->prepare( "SELECT wallet_balance FROM `{$wpdb->prefix}sd_customers` WHERE id = %d", $customer_id ) );

    wp_send_json_success( [
        'message'     => "₦" . number_format( $amount, 2 ) . " credited to customer wallet.",
        'new_balance' => $new_balance,
    ] );
}
