<?php
/** Idibia — Admin API: Customer Wallet Top-Up Requests */

function idibia_ensure_wallet_topup_table(): void {
    global $wpdb;
    static $checked = false;
    if ( $checked ) return;
    $checked = true;
    $table = $wpdb->prefix . 'sd_wallet_topup_requests';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) return;
    $charset = $wpdb->get_charset_collate();
    $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$table}` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `customer_id` bigint(20) NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `bank_ref` varchar(100) DEFAULT NULL,
        `proof_path` varchar(500) NOT NULL,
        `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        `admin_notes` text DEFAULT NULL,
        `reviewed_by` bigint(20) DEFAULT NULL,
        `reviewed_at` datetime DEFAULT NULL,
        `created_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `customer_id` (`customer_id`),
        KEY `status` (`status`)
    ) {$charset}" );
}

function idibia_admin_get_wallet_topups(): void {
    global $wpdb;
    idibia_ensure_wallet_topup_table();
    [ $page, $per_page, $offset ] = idibia_page_args();
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? 'pending' ) );
    $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
    $where = [ '1=1' ];
    $args  = [];
    if ( $status && $status !== 'all' ) {
        $where[] = 'r.status = %s';
        $args[]  = $status;
    }
    if ( $search ) {
        $like    = '%' . $wpdb->esc_like( $search ) . '%';
        $where[] = '(c.full_name LIKE %s OR c.email LIKE %s OR r.bank_ref LIKE %s)';
        array_push( $args, $like, $like, $like );
    }
    $sql_where = implode( ' AND ', $where );

    $metrics = [
        'pending_count'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_wallet_topup_requests` WHERE status = 'pending'" ),
        'pending_amount'        => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM `{$wpdb->prefix}sd_wallet_topup_requests` WHERE status = 'pending'" ),
        'approved_today'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_wallet_topup_requests` WHERE status = 'approved' AND DATE(reviewed_at) = UTC_DATE()" ),
        'approved_today_amount' => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM `{$wpdb->prefix}sd_wallet_topup_requests` WHERE status = 'approved' AND DATE(reviewed_at) = UTC_DATE()" ),
        'week_amount'           => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM `{$wpdb->prefix}sd_wallet_topup_requests` WHERE status = 'approved' AND created_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY" ),
        'rejected_count'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_wallet_topup_requests` WHERE status = 'rejected'" ),
    ];

    $total = (int) $wpdb->get_var( idibia_sql(
        "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_wallet_topup_requests` r
         LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = r.customer_id
         WHERE {$sql_where}",
        $args
    ) );

    $rows = $wpdb->get_results( idibia_sql(
        "SELECT r.*, c.full_name AS customer_name, c.email AS customer_email, c.phone AS customer_phone
         FROM `{$wpdb->prefix}sd_wallet_topup_requests` r
         LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = r.customer_id
         WHERE {$sql_where} ORDER BY r.created_at DESC LIMIT %d OFFSET %d",
        array_merge( $args, [ $per_page, $offset ] )
    ), ARRAY_A ) ?: [];

    $upload  = wp_upload_dir();
    $baseurl = empty( $upload['error'] ) ? trailingslashit( $upload['baseurl'] ) : '';
    foreach ( $rows as &$row ) {
        $row['id']          = (int) $row['id'];
        $row['customer_id'] = (int) $row['customer_id'];
        $row['amount']      = (float) $row['amount'];
        $row['proof_url']   = $row['proof_path'] && $baseurl ? $baseurl . ltrim( $row['proof_path'], '/' ) : '';
    }
    unset( $row );

    wp_send_json_success( [ 'requests' => $rows, 'metrics' => $metrics, 'page' => $page, 'per_page' => $per_page, 'total' => $total ] );
}

function idibia_admin_review_wallet_topup(): void {
    global $wpdb, $admin_id;
    idibia_ensure_wallet_topup_table();
    $request_id = absint( $_POST['request_id'] ?? 0 );
    $action     = sanitize_key( $_POST['review_action'] ?? '' );
    $notes      = sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) );

    if ( ! in_array( $action, [ 'approved', 'rejected' ], true ) ) {
        wp_send_json_error( [ 'message' => 'Invalid action.' ] );
    }
    $req = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM `{$wpdb->prefix}sd_wallet_topup_requests` WHERE id = %d LIMIT 1",
        $request_id
    ), ARRAY_A );
    if ( ! $req ) wp_send_json_error( [ 'message' => 'Request not found.' ] );
    if ( $req['status'] !== 'pending' ) wp_send_json_error( [ 'message' => 'This request has already been reviewed.' ] );

    idibia_transaction_start();
    $updated = $wpdb->update(
        $wpdb->prefix . 'sd_wallet_topup_requests',
        [
            'status'      => $action,
            'admin_notes' => $notes ?: null,
            'reviewed_by' => $admin_id ?: null,
            'reviewed_at' => gmdate( 'Y-m-d H:i:s' ),
        ],
        [ 'id' => $request_id ],
        [ '%s', '%s', '%d', '%s' ],
        [ '%d' ]
    );
    if ( false === $updated ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Could not update request.' ] );
    }

    if ( $action === 'approved' ) {
        $amount  = (float) $req['amount'];
        $cust_id = (int) $req['customer_id'];
        $wpdb->query( $wpdb->prepare(
            "UPDATE `{$wpdb->prefix}sd_customers` SET wallet_balance = wallet_balance + %f WHERE id = %d",
            $amount, $cust_id
        ) );
        $wpdb->insert( $wpdb->prefix . 'sd_customer_wallet_ledger', [
            'customer_id'  => $cust_id,
            'amount'       => $amount,
            'entry_type'   => 'topup',
            'reference_id' => $request_id,
            'description'  => 'Manual bank transfer top-up — approved by admin',
            'created_at'   => gmdate( 'Y-m-d H:i:s' ),
        ], [ '%d', '%f', '%s', '%d', '%s', '%s' ] );
    }

    idibia_transaction_commit();
    idibia_admin_audit_log( 'review_wallet_topup', 'wallet_topup', $request_id, [
        'action'      => $action,
        'customer_id' => (int) $req['customer_id'],
        'amount'      => (float) $req['amount'],
    ] );
    wp_send_json_success( [ 'message' => $action === 'approved'
        ? 'Top-up approved. Customer wallet has been credited ₦' . number_format( (float) $req['amount'], 2 ) . '.'
        : 'Request rejected.' ] );
}
