<?php
/** Idibia — Admin API: Revenue, Payments & Reconciliation */

function idibia_admin_revenue_analytics(): void {
    global $wpdb;

    // Accept optional date range; default to the current calendar month.
    $date_from = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) );
    $date_to   = sanitize_text_field( wp_unslash( $_GET['date_to']   ?? '' ) );
    if ( ! $date_from || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
        $date_from = gmdate( 'Y-m-01' );
    }
    if ( ! $date_to || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
        $date_to = gmdate( 'Y-m-d' );
    }

    $fare_expr       = "COALESCE(NULLIF(final_fare,0), NULLIF(fare_estimate,0), fare, 0)";
    $commission_expr = "$fare_expr * platform_pct / 100";

    $monthly_revenue = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM($commission_expr),0) FROM `{$wpdb->prefix}sd_trips` WHERE status='completed' AND DATE(COALESCE(completed_at,created_at)) >= %s AND DATE(COALESCE(completed_at,created_at)) <= %s",
        $date_from, $date_to
    ) );

    $driver_payouts = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM `{$wpdb->prefix}sd_payouts` WHERE status='paid' AND DATE(updated_at) >= %s AND DATE(updated_at) <= %s",
        $date_from, $date_to
    ) );

    $total_refunds = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM `{$wpdb->prefix}sd_payments` WHERE status='refunded' AND DATE(COALESCE(reviewed_at, updated_at)) >= %s AND DATE(COALESCE(reviewed_at, updated_at)) <= %s",
        $date_from, $date_to
    ) );

    $pending_payouts = (float) $wpdb->get_var(
        "SELECT COALESCE(SUM(amount),0) FROM `{$wpdb->prefix}sd_payouts` WHERE status IN ('pending','processing')"
    );

    $days_elapsed = max( 1, (int) ( ( strtotime( $date_to ) - strtotime( $date_from ) ) / 86400 ) + 1 );
    $avg_daily    = $monthly_revenue / $days_elapsed;

    // Per-day revenue for the entire selected period.
    $day_rows = $wpdb->get_results( $wpdb->prepare( "
        SELECT DATE(COALESCE(completed_at,created_at)) AS day,
               COALESCE(SUM($commission_expr),0) AS revenue
        FROM `{$wpdb->prefix}sd_trips`
        WHERE status='completed'
          AND DATE(COALESCE(completed_at,created_at)) >= %s
          AND DATE(COALESCE(completed_at,created_at)) <= %s
        GROUP BY DATE(COALESCE(completed_at,created_at))
        ORDER BY day ASC
    ", $date_from, $date_to ), ARRAY_A ) ?: [];

    $day_map = [];
    foreach ( $day_rows as $r ) { $day_map[ $r['day'] ] = (float) $r['revenue']; }

    $daily_breakdown = [];
    for ( $ts = strtotime( $date_from ); $ts <= strtotime( $date_to ); $ts += 86400 ) {
        $d = gmdate( 'Y-m-d', $ts );
        $daily_breakdown[] = [ 'date' => $d, 'label' => gmdate( 'D', $ts ), 'revenue' => $day_map[ $d ] ?? 0.0 ];
    }
    $weekly_chart = array_slice( $daily_breakdown, -7 ); // last 7 days for the bar chart

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

    // Gateway success rate (all-time)
    $total_payments   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payments` WHERE status NOT IN ('pending')" );
    $success_payments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payments` WHERE status IN ('captured','approved')" );
    $gateway_success_rate = $total_payments > 0 ? round( $success_payments / $total_payments * 100, 1 ) : 0;

    // Completed trips in the selected period
    $same_day_trips = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` WHERE status='completed' AND DATE(COALESCE(completed_at,created_at)) >= %s AND DATE(COALESCE(completed_at,created_at)) <= %s",
        $date_from, $date_to
    ) );

    wp_send_json_success( [
        'date_from'             => $date_from,
        'date_to'               => $date_to,
        'monthly_revenue'       => $monthly_revenue,
        'net_commission'        => $monthly_revenue,
        'driver_payouts'        => $driver_payouts,
        'avg_daily'             => $avg_daily,
        'total_refunds_issued'  => $total_refunds,
        'total_pending_payouts' => $pending_payouts,
        'net_platform_revenue'  => $monthly_revenue - $total_refunds,
        'weekly_chart'          => $weekly_chart,
        'daily_breakdown'       => $daily_breakdown,
        'category_chart'        => $category_chart,
        'gateway_success_rate'  => $gateway_success_rate,
        'same_day_trips'        => $same_day_trips,
    ] );
}

function idibia_admin_export_tax_summary(): void {
    global $wpdb;
    [ $date_from, $date_to ] = idibia_rev_date_range();

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT d.full_name, d.email, COALESCE(SUM(p.amount), 0) AS total_payouts
         FROM `{$wpdb->prefix}sd_drivers` d
         LEFT JOIN `{$wpdb->prefix}sd_payouts` p
               ON p.driver_id = d.id AND p.status = 'paid'
              AND DATE(p.updated_at) >= %s AND DATE(p.updated_at) <= %s
         GROUP BY d.id
         ORDER BY total_payouts DESC",
        $date_from, $date_to
    ), ARRAY_A );

    idibia_send_csv_headers( "tax_summary_{$date_from}_to_{$date_to}.csv" );
    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, [ 'Driver Name', 'Email', 'Total Payouts (₦)', 'Period' ] );
    foreach ( $rows as $row ) {
        fputcsv( $out, [ $row['full_name'], $row['email'], $row['total_payouts'], "$date_from to $date_to" ] );
    }
    fclose( $out );
    exit;
}

function idibia_admin_export_driver_wht(): void {
    global $wpdb;
    [ $date_from, $date_to ] = idibia_rev_date_range();

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT d.full_name, d.email, d.bank_name, d.account_number,
                COALESCE(SUM(p.amount), 0) AS total_payouts
         FROM `{$wpdb->prefix}sd_drivers` d
         LEFT JOIN `{$wpdb->prefix}sd_payouts` p
               ON p.driver_id = d.id AND p.status = 'paid'
              AND DATE(p.updated_at) >= %s AND DATE(p.updated_at) <= %s
         GROUP BY d.id
         ORDER BY total_payouts DESC",
        $date_from, $date_to
    ), ARRAY_A );

    idibia_send_csv_headers( "driver_wht_{$date_from}_to_{$date_to}.csv" );
    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, [ 'Driver Name', 'Email', 'Bank Name', 'Account Number', 'Total Payouts (₦)', 'WHT 5% (₦)', 'Period' ] );
    foreach ( $rows as $row ) {
        $wht = round( (float) $row['total_payouts'] * 0.05, 2 );
        fputcsv( $out, [ $row['full_name'], $row['email'], $row['bank_name'], $row['account_number'], $row['total_payouts'], $wht, "$date_from to $date_to" ] );
    }
    fclose( $out );
    exit;
}

function idibia_admin_export_vat_schedule(): void {
    global $wpdb;
    [ $date_from, $date_to ] = idibia_rev_date_range();

    $fare_expr = "COALESCE(NULLIF(final_fare,0), NULLIF(fare_estimate,0), fare, 0)";
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT trip_ref, $fare_expr AS fare_amount, platform_pct
         FROM `{$wpdb->prefix}sd_trips`
         WHERE status = 'completed'
           AND DATE(COALESCE(completed_at, created_at)) >= %s
           AND DATE(COALESCE(completed_at, created_at)) <= %s
         ORDER BY COALESCE(completed_at, created_at) ASC",
        $date_from, $date_to
    ), ARRAY_A );

    idibia_send_csv_headers( "vat_schedule_{$date_from}_to_{$date_to}.csv" );
    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, [ 'Trip Ref', 'Total Fare (₦)', 'Platform Commission (₦)', 'VAT 7.5% of Commission (₦)', 'Period' ] );
    foreach ( $rows as $row ) {
        $commission = round( (float) $row['fare_amount'] * ( (float) $row['platform_pct'] / 100 ), 2 );
        $vat        = round( $commission * 0.075, 2 );
        fputcsv( $out, [ $row['trip_ref'], $row['fare_amount'], $commission, $vat, "$date_from to $date_to" ] );
    }
    fclose( $out );
    exit;
}

/** Helper: parse and validate date_from / date_to from GET params. */
function idibia_rev_date_range(): array {
    $from = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) );
    $to   = sanitize_text_field( wp_unslash( $_GET['date_to']   ?? '' ) );
    if ( ! $from || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) { $from = gmdate( 'Y-m-01' ); }
    if ( ! $to   || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to   ) ) { $to   = gmdate( 'Y-m-d'  ); }
    return [ $from, $to ];
}

/** Helper: send the right HTTP headers for a CSV file download. */
function idibia_send_csv_headers( string $filename ): void {
    while ( ob_get_level() ) { ob_end_clean(); }
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Cache-Control: no-cache, must-revalidate' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );
}

function idibia_admin_get_pnl_summary(): void {
    global $wpdb;
    [ $date_from, $date_to ] = idibia_rev_date_range();

    $fare_expr       = "COALESCE(NULLIF(final_fare,0), NULLIF(fare_estimate,0), fare, 0)";
    $commission_expr = "$fare_expr * platform_pct / 100";

    // Gross revenue = sum of platform commissions earned on completed trips.
    $gross_revenue = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM($commission_expr), 0) FROM `{$wpdb->prefix}sd_trips`
         WHERE status='completed' AND DATE(COALESCE(completed_at, created_at)) >= %s AND DATE(COALESCE(completed_at, created_at)) <= %s",
        $date_from, $date_to
    ) );

    // Total money actually paid out to drivers.
    $total_payouts = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM `{$wpdb->prefix}sd_payouts`
         WHERE status='paid' AND DATE(updated_at) >= %s AND DATE(updated_at) <= %s",
        $date_from, $date_to
    ) );

    // Total refunded to customers.
    $total_refunds = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM `{$wpdb->prefix}sd_payments`
         WHERE status='refunded' AND DATE(COALESCE(reviewed_at, updated_at)) >= %s AND DATE(COALESCE(reviewed_at, updated_at)) <= %s",
        $date_from, $date_to
    ) );

    // Total bonuses paid to drivers (campaign completions + manual bonuses).
    $total_bonuses = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM `{$wpdb->prefix}sd_wallet_ledger`
         WHERE entry_type='bonus' AND amount > 0 AND DATE(created_at) >= %s AND DATE(created_at) <= %s",
        $date_from, $date_to
    ) );

    // Payouts still sitting in pending/processing (all-time, not date-filtered).
    $outstanding_payouts = (float) $wpdb->get_var(
        "SELECT COALESCE(SUM(amount), 0) FROM `{$wpdb->prefix}sd_payouts` WHERE status IN ('pending','processing')"
    );

    $net_profit     = $gross_revenue - $total_payouts - $total_refunds - $total_bonuses;
    $payout_rate    = $gross_revenue > 0 ? round( $total_payouts / $gross_revenue * 100, 1 ) : 0.0;
    $running_balance = $gross_revenue - $total_payouts - $total_refunds;

    // Compare against the previous period of the same length.
    $from_ts   = strtotime( $date_from );
    $to_ts     = strtotime( $date_to );
    $duration  = max( 1, $to_ts - $from_ts );
    $prev_to   = gmdate( 'Y-m-d', $from_ts - 86400 );
    $prev_from = gmdate( 'Y-m-d', $from_ts - $duration - 86400 );

    $prev_revenue = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM($commission_expr), 0) FROM `{$wpdb->prefix}sd_trips`
         WHERE status='completed' AND DATE(COALESCE(completed_at, created_at)) >= %s AND DATE(COALESCE(completed_at, created_at)) <= %s",
        $prev_from, $prev_to
    ) );

    $revenue_change_pct = $prev_revenue > 0
        ? round( ( $gross_revenue - $prev_revenue ) / $prev_revenue * 100, 1 )
        : null;

    wp_send_json_success( [
        'date_from'                  => $date_from,
        'date_to'                    => $date_to,
        'gross_revenue'              => $gross_revenue,
        'platform_commission_earned' => $gross_revenue,
        'total_payouts_made'         => $total_payouts,
        'total_refunds_issued'       => $total_refunds,
        'total_bonuses_paid'         => $total_bonuses,
        'net_profit'                 => $net_profit,
        'payout_rate'                => $payout_rate,
        'outstanding_payouts'        => $outstanding_payouts,
        'running_balance'            => $running_balance,
        'prev_period'                => [
            'date_from' => $prev_from,
            'date_to'   => $prev_to,
            'revenue'   => $prev_revenue,
        ],
        'revenue_change_pct'         => $revenue_change_pct,
    ] );
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
