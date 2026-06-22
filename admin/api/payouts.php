<?php
/** Idibia — Admin API: Payouts */

function idibia_admin_sync_pending_payouts(): void {
    global $wpdb;
    $drivers = $wpdb->get_results(
        "SELECT d.id, d.wallet_balance
         FROM `{$wpdb->prefix}sd_drivers` d
         LEFT JOIN `{$wpdb->prefix}sd_payouts` p ON p.driver_id = d.id AND p.status IN ('pending','processing')
         WHERE d.wallet_balance > 0 AND p.id IS NULL",
        ARRAY_A
    ) ?: [];

    foreach ( $drivers as $driver ) {
        $wpdb->insert(
            $wpdb->prefix . 'sd_payouts',
            [
                'driver_id'    => (int) $driver['id'],
                'amount'       => (float) $driver['wallet_balance'],
                'status'       => 'pending',
                'provider_ref' => 'wallet-' . (int) $driver['id'] . '-' . gmdate( 'YmdHis' ),
                'created_at'   => gmdate( 'Y-m-d H:i:s' ),
                'updated_at'   => gmdate( 'Y-m-d H:i:s' ),
            ],
            [ '%d', '%f', '%s', '%s', '%s', '%s' ]
        );
    }
}

function idibia_admin_paginated_payouts(): void {
    global $wpdb;
    idibia_admin_sync_pending_payouts();
    [ $page, $per_page, $offset ] = idibia_page_args();
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? 'pending' ) );
    $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
    $where = [ '1=1' ]; $args = [];
    if ( $status && $status !== 'all' ) { $where[] = 'p.status = %s'; $args[] = $status; }
    if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = '(d.full_name LIKE %s OR d.email LIKE %s OR d.bank_name LIKE %s OR p.provider_ref LIKE %s)'; array_push( $args, $like, $like, $like, $like ); }
    $sql_where = implode( ' AND ', $where );
    $metrics = [
        'pending_amount' => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM `{$wpdb->prefix}sd_payouts` WHERE status IN ('pending','processing')" ),
        'pending_count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payouts` WHERE status IN ('pending','processing')" ),
        'processed_today_amount' => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM `{$wpdb->prefix}sd_payouts` WHERE status = 'paid' AND DATE(updated_at) = UTC_DATE()" ),
        'processed_today_count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payouts` WHERE status = 'paid' AND DATE(updated_at) = UTC_DATE()" ),
        'failed_count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payouts` WHERE status = 'failed'" ),
        'avg_payout' => (float) $wpdb->get_var( "SELECT COALESCE(AVG(amount), 0) FROM `{$wpdb->prefix}sd_payouts` WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY" ),
        'wallet_balance' => (float) $wpdb->get_var( "SELECT COALESCE(SUM(wallet_balance), 0) FROM `{$wpdb->prefix}sd_drivers`" ),
    ];
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payouts` p LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = p.driver_id WHERE $sql_where", $args ) );
    $rows = $wpdb->get_results( idibia_sql( "SELECT p.*, d.full_name AS driver_name, d.bank_name, d.account_number, d.wallet_balance, d.total_trips FROM `{$wpdb->prefix}sd_payouts` p LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = p.driver_id WHERE $sql_where ORDER BY p.updated_at DESC, p.created_at DESC LIMIT %d OFFSET %d", array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A ) ?: [];
    wp_send_json_success( [ 'payouts' => $rows, 'metrics' => $metrics, 'page' => $page, 'per_page' => $per_page, 'total' => $total ] );
}

function idibia_admin_process_payout(): void {
    global $wpdb;
    $payout_id = absint( $_POST['payout_id'] ?? 0 );
    $status = sanitize_key( $_POST['status'] ?? 'paid' );
    if ( ! in_array( $status, [ 'processing', 'paid', 'failed' ], true ) ) wp_send_json_error( [ 'message' => 'Invalid payout status.' ] );
    $payout = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_payouts` WHERE id = %d LIMIT 1", $payout_id ), ARRAY_A );
    if ( ! $payout ) wp_send_json_error( [ 'message' => 'Payout not found.' ] );
    if ( $payout['status'] === 'paid' && $status === 'paid' ) {
        wp_send_json_error( [ 'message' => 'This payout has already been released.' ] );
    }

    idibia_transaction_start();
    $updated = $wpdb->update( $wpdb->prefix . 'sd_payouts', [ 'status' => $status, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'id' => $payout_id ], [ '%s', '%s' ], [ '%d' ] );
    if ( false === $updated ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Could not update payout.' ] );
    }
    if ( $status === 'paid' ) {
        $ledger_exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_wallet_ledger` WHERE entry_type = 'payout' AND reference_id = %d", $payout_id ) );
        if ( $ledger_exists === 0 ) {
            $wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->prefix}sd_drivers` SET wallet_balance = GREATEST(wallet_balance - %f, 0) WHERE id = %d", (float) $payout['amount'], (int) $payout['driver_id'] ) );
            $wpdb->insert( $wpdb->prefix . 'sd_wallet_ledger', [ 'driver_id' => (int) $payout['driver_id'], 'amount' => -abs( (float) $payout['amount'] ), 'entry_type' => 'payout', 'reference_id' => $payout_id, 'description' => 'Admin payout released', 'created_at' => gmdate( 'Y-m-d H:i:s' ) ], [ '%d', '%f', '%s', '%d', '%s', '%s' ] );
        }
    }
    idibia_transaction_commit();
    idibia_admin_audit_log( 'payout', 'payout', $payout_id, [ 'status' => $status, 'driver_id' => (int) $payout['driver_id'], 'amount' => (float) $payout['amount'] ] );
    wp_send_json_success( [ 'message' => 'Payout updated.' ] );
}
