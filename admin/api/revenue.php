<?php
/** Idibia — Admin API: Revenue, Payments & Reconciliation */

function idibia_admin_revenue_analytics(): void {
    global $wpdb;
    $fare_expr = "COALESCE(NULLIF(final_fare,0), NULLIF(fare_estimate,0), fare, 0)";
    $commission_expr = "$fare_expr * platform_pct / 100";

    $month_start = gmdate( 'Y-m-01' );
    $today       = gmdate( 'Y-m-d' );

    $monthly_revenue = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM($commission_expr),0) FROM `{$wpdb->prefix}sd_trips` WHERE status='completed' AND DATE(COALESCE(completed_at,created_at)) >= %s AND DATE(COALESCE(completed_at,created_at)) <= %s",
        $month_start, $today
    ) );

    $driver_payouts = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM `{$wpdb->prefix}sd_payouts` WHERE status='paid' AND DATE(created_at) >= %s AND DATE(created_at) <= %s",
        $month_start, $today
    ) );

    $days_elapsed = max( 1, (int) gmdate( 'j' ) );
    $avg_daily = $monthly_revenue / $days_elapsed;

    // Revenue per day for the last 7 days
    $week_rows = $wpdb->get_results( "
        SELECT DATE(COALESCE(completed_at,created_at)) AS day,
               COALESCE(SUM($commission_expr),0) AS revenue
        FROM `{$wpdb->prefix}sd_trips`
        WHERE status='completed' AND DATE(COALESCE(completed_at,created_at)) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(COALESCE(completed_at,created_at))
        ORDER BY day ASC
    ", ARRAY_A ) ?: [];
    $week_map = [];
    foreach ( $week_rows as $r ) { $week_map[ $r['day'] ] = (float) $r['revenue']; }
    $weekly_chart = [];
    for ( $i = 6; $i >= 0; $i-- ) {
        $d = gmdate( 'Y-m-d', strtotime( "-{$i} day" ) );
        $weekly_chart[] = [ 'date' => $d, 'label' => gmdate( 'D', strtotime( $d ) ), 'revenue' => $week_map[ $d ] ?? 0.0 ];
    }

    // Revenue by service category
    $cat_rows = $wpdb->get_results( "
        SELECT COALESCE(NULLIF(service_category,''), NULLIF(category,''), 'Other') AS cat,
               COALESCE(SUM($commission_expr),0) AS revenue
        FROM `{$wpdb->prefix}sd_trips`
        WHERE status='completed'
        GROUP BY cat
        ORDER BY revenue DESC
        LIMIT 6
    ", ARRAY_A ) ?: [];
    $category_chart = array_map( fn($r) => [ 'label' => $r['cat'], 'revenue' => (float) $r['revenue'] ], $cat_rows );

    // Gateway success rate (captured vs total non-pending)
    $total_payments   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payments` WHERE status NOT IN ('pending')" );
    $success_payments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payments` WHERE status IN ('captured','approved')" );
    $gateway_success_rate = $total_payments > 0 ? round( $success_payments / $total_payments * 100, 1 ) : 0;

    // Same-day completed trips this month
    $same_day_trips = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` WHERE status='completed' AND DATE(COALESCE(completed_at,created_at)) >= %s",
        $month_start
    ) );

    wp_send_json_success( [
        'monthly_revenue'      => $monthly_revenue,
        'net_commission'       => $monthly_revenue,
        'driver_payouts'       => $driver_payouts,
        'avg_daily'            => $avg_daily,
        'weekly_chart'         => $weekly_chart,
        'category_chart'       => $category_chart,
        'gateway_success_rate' => $gateway_success_rate,
        'same_day_trips'       => $same_day_trips,
    ] );
}

function idibia_admin_export_tax_summary(): void {
    global $wpdb;
    $rows = $wpdb->get_results( "SELECT d.full_name, d.email, COALESCE(SUM(p.amount), 0) as total_payouts FROM `{$wpdb->prefix}sd_drivers` d LEFT JOIN `{$wpdb->prefix}sd_payouts` p ON p.driver_id = d.id AND p.status = 'paid' GROUP BY d.id", ARRAY_A );

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tax_summary.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Driver Name', 'Email', 'Total Payouts']);
    foreach ($rows as $row) {
        fputcsv($output, [$row['full_name'], $row['email'], $row['total_payouts']]);
    }
    fclose($output);
    exit;
}

function idibia_admin_export_driver_wht(): void {
    global $wpdb;
    $rows = $wpdb->get_results( "SELECT d.full_name, d.email, d.bank_name, d.account_number, COALESCE(SUM(p.amount), 0) as total_payouts FROM `{$wpdb->prefix}sd_drivers` d LEFT JOIN `{$wpdb->prefix}sd_payouts` p ON p.driver_id = d.id AND p.status = 'paid' GROUP BY d.id", ARRAY_A );

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="driver_wht.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Driver Name', 'Email', 'Bank Name', 'Account Number', 'Total Payouts', 'WHT Withheld (e.g. 5%)']);
    foreach ($rows as $row) {
        $wht = $row['total_payouts'] * 0.05;
        fputcsv($output, [$row['full_name'], $row['email'], $row['bank_name'], $row['account_number'], $row['total_payouts'], $wht]);
    }
    fclose($output);
    exit;
}

function idibia_admin_export_vat_schedule(): void {
    global $wpdb;
    $rows = $wpdb->get_results( "SELECT id, trip_ref, fare, platform_pct FROM `{$wpdb->prefix}sd_trips` WHERE status = 'completed'", ARRAY_A );

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="vat_schedule.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Trip Ref', 'Total Fare', 'Platform Commission', 'VAT (e.g. 7.5% of Commission)']);
    foreach ($rows as $row) {
        $commission = $row['fare'] * ($row['platform_pct'] / 100);
        $vat = $commission * 0.075;
        fputcsv($output, [$row['trip_ref'], $row['fare'], $commission, $vat]);
    }
    fclose($output);
    exit;
}

function idibia_admin_manual_payments(): void {
    global $wpdb;
    idibia_ensure_manual_payment_columns();
    [ $page, $per_page, $offset ] = idibia_page_args();
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? 'proof_submitted' ) );
    $where = [ "p.provider = 'manual_transfer'" ];
    $args = [];
    if ( $status && $status !== 'all' ) { $where[] = 'p.status = %s'; $args[] = $status; }
    $sql_where = implode( ' AND ', $where );
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payments` p WHERE $sql_where", $args ) );
    $sql = "SELECT p.*, t.trip_ref, t.status AS trip_status, t.dispatch_status, c.full_name AS customer_name, c.phone AS customer_phone
            FROM `{$wpdb->prefix}sd_payments` p
            INNER JOIN `{$wpdb->prefix}sd_trips` t ON t.id = p.trip_id
            LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = p.customer_id
            WHERE $sql_where
            ORDER BY p.updated_at DESC, p.created_at DESC
            LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results( idibia_sql( $sql, array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A ) ?: [];
    $upload = wp_upload_dir();
    $baseurl = empty( $upload['error'] ) ? trailingslashit( $upload['baseurl'] ) : '';
    foreach ( $rows as &$row ) {
        $row['id'] = (int) $row['id'];
        $row['trip_id'] = (int) $row['trip_id'];
        $row['customer_id'] = (int) $row['customer_id'];
        $row['amount'] = (float) $row['amount'];
        $row['proof_url'] = ! empty( $row['proof_path'] ) && $baseurl ? $baseurl . ltrim( $row['proof_path'], '/' ) : '';
    }
    unset( $row );
    wp_send_json_success( [ 'payments' => $rows, 'page' => $page, 'per_page' => $per_page, 'total' => $total ] );
}

function idibia_admin_reconciliation_data(): void {
    global $wpdb;
    [ $page, $per_page, $offset ] = idibia_page_args();
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? 'all' ) );
    $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );

    $where = [ '1=1' ];
    $args = [];

    if ( $status && $status !== 'all' ) {
        $where[] = 'p.status = %s';
        $args[]  = $status;
    }

    if ( $search ) {
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $where[] = '(t.trip_ref LIKE %s OR c.full_name LIKE %s)';
        $args[] = $like;
        $args[] = $like;
    }

    $start_date = sanitize_text_field( wp_unslash( $_GET['start_date'] ?? '' ) );
    $end_date = sanitize_text_field( wp_unslash( $_GET['end_date'] ?? '' ) );

    if ( $start_date ) {
        $where[] = 'DATE(p.created_at) >= %s';
        $args[] = $start_date;
    }
    if ( $end_date ) {
        $where[] = 'DATE(p.created_at) <= %s';
        $args[] = $end_date;
    }

    $sql_where = implode( ' AND ', $where );
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payments` p LEFT JOIN `{$wpdb->prefix}sd_trips` t ON t.id = p.trip_id LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = p.customer_id WHERE $sql_where", $args ) );

    $sql = "SELECT p.*, t.trip_ref, t.status AS trip_status, c.full_name AS customer_name
            FROM `{$wpdb->prefix}sd_payments` p
            LEFT JOIN `{$wpdb->prefix}sd_trips` t ON t.id = p.trip_id
            LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = p.customer_id
            WHERE $sql_where
            ORDER BY p.created_at DESC
            LIMIT %d OFFSET %d";

    $rows = $wpdb->get_results( idibia_sql( $sql, array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A ) ?: [];

    foreach ( $rows as &$row ) {
        if ( in_array( $row['status'], [ 'approved', 'captured' ], true ) ) {
            $token = hash_hmac( 'sha256', $row['trip_id'], wp_salt( 'auth' ) );
            $row['receipt_url'] = '/receipt-handler.php?trip_id=' . $row['trip_id'] . '&token=' . $token;
        } else {
            $row['receipt_url'] = null;
        }
    }
    unset($row);

    wp_send_json_success( [ 'reconciliation' => $rows, 'page' => $page, 'per_page' => $per_page, 'total' => $total ] );
}

function idibia_admin_review_manual_payment(): void {
    global $wpdb;
    idibia_ensure_manual_payment_columns();
    $payment_id = absint( $_POST['payment_id'] ?? 0 );
    $decision = sanitize_key( $_POST['decision'] ?? '' );
    $notes = sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) );
    if ( $payment_id <= 0 || ! in_array( $decision, [ 'approve', 'reject' ], true ) ) {
        wp_send_json_error( [ 'message' => 'Select a valid payment review action.' ] );
    }

    $payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_payments` WHERE id = %d AND provider = 'manual_transfer' LIMIT 1", $payment_id ), ARRAY_A );
    if ( ! $payment ) {
        wp_send_json_error( [ 'message' => 'Payment record not found.' ] );
    }
    if ( $decision === 'approve' && empty( $payment['proof_path'] ) ) {
        wp_send_json_error( [ 'message' => 'A receipt/proof file is required before approval.' ] );
    }

    $new_status = $decision === 'approve' ? 'captured' : 'rejected';
    idibia_transaction_start();
    $updated = $wpdb->update(
        $wpdb->prefix . 'sd_payments',
        [
            'status'      => $new_status,
            'admin_notes' => $notes,
            'reviewed_by' => get_current_user_id(),
            'reviewed_at' => gmdate( 'Y-m-d H:i:s' ),
        ],
        [ 'id' => $payment_id ],
        [ '%s', '%s', '%d', '%s' ],
        [ '%d' ]
    );
    if ( false === $updated ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Could not review payment.' ] );
    }

    $wpdb->update( $wpdb->prefix . 'sd_trips', [ 'payment_status' => $new_status ], [ 'id' => (int) $payment['trip_id'] ], [ '%s' ], [ '%d' ] );
    idibia_log_event( (int) $payment['trip_id'], $decision === 'approve' ? 'payment_approved' : 'payment_rejected', [ 'payment_id' => $payment_id, 'admin_id' => get_current_user_id() ] );
    idibia_notify_trip_participants( (int) $payment['trip_id'], $decision === 'approve' ? 'payment_approved' : 'payment_rejected', [ 'body' => $notes ?: null ] );
    if ( $decision === 'approve' ) {
        idibia_notify_trip_participants( (int) $payment['trip_id'], 'payment_captured' );
        idibia_credit_driver_for_trip( (int) $payment['trip_id'] );
    }
    idibia_transaction_commit();
    idibia_admin_audit_log( $decision === 'approve' ? 'approve_manual_payment' : 'reject_manual_payment', 'payment', $payment_id, [ 'decision' => $decision, 'trip_id' => (int) $payment['trip_id'], 'notes' => $notes ] );

    wp_send_json_success( [ 'message' => $decision === 'approve' ? 'Payment approved.' : 'Payment rejected.', 'payment' => idibia_payment_public_payload( (int) $payment['trip_id'] ) ] );
}
