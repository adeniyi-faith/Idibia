<?php
require_once __DIR__ . '/wp-auth-config.php';

$auth_type = 'driver';
require_once __DIR__ . '/auth-helper.php';

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed' ] );
}

$driver_id = $GLOBALS['auth_driver_id'];
$action = sanitize_text_field($_POST['action'] ?? '');

global $wpdb;

if ($action === 'get_wallet') {
    $driver = $wpdb->get_row($wpdb->prepare("SELECT wallet_balance, bank_name, account_number, rating, total_trips FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $driver_id), ARRAY_A);
    if (!$driver) {
        wp_send_json_error(['message' => 'Driver not found']);
    }

    $ledger = $wpdb->get_results($wpdb->prepare(
        "SELECT amount, entry_type, description, created_at FROM `{$wpdb->prefix}sd_wallet_ledger` WHERE driver_id = %d ORDER BY created_at DESC LIMIT 50",
        $driver_id
    ), ARRAY_A);

    $payouts = $wpdb->get_results($wpdb->prepare(
        "SELECT amount, status, provider_ref, created_at, updated_at FROM `{$wpdb->prefix}sd_payouts` WHERE driver_id = %d ORDER BY created_at DESC LIMIT 50",
        $driver_id
    ), ARRAY_A);

    // Earnings summary: week starts on Monday — read from trips for consistency with home screen
    $week_start = gmdate('Y-m-d', strtotime('monday this week'));
    $week_end   = gmdate('Y-m-d', strtotime('sunday this week'));
    $today      = gmdate('Y-m-d');

    $week_start_dt = $week_start . ' 00:00:00';
    $week_end_dt   = $week_end   . ' 23:59:59';
    $today_start   = $today . ' 00:00:00';
    $today_end     = $today . ' 23:59:59';

    $today_earnings = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(COALESCE(NULLIF(final_fare,0), fare, 0)), 0)
         FROM `{$wpdb->prefix}sd_trips`
         WHERE driver_id = %d AND status = 'completed' AND completed_at BETWEEN %s AND %s",
        $driver_id, $today_start, $today_end
    ));

    $week_row = $wpdb->get_row($wpdb->prepare(
        "SELECT COALESCE(SUM(COALESCE(NULLIF(final_fare,0), fare, 0)), 0) AS week_total, COUNT(*) AS week_trips
         FROM `{$wpdb->prefix}sd_trips`
         WHERE driver_id = %d AND status = 'completed' AND completed_at BETWEEN %s AND %s",
        $driver_id, $week_start_dt, $week_end_dt
    ), ARRAY_A);

    $week_earnings = (float) ($week_row['week_total'] ?? 0);
    $week_trips    = (int)   ($week_row['week_trips'] ?? 0);

    // Daily breakdown for the current week (Mon–Sun)
    $daily_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(completed_at) AS day, COALESCE(SUM(COALESCE(NULLIF(final_fare,0), fare, 0)), 0) AS total
         FROM `{$wpdb->prefix}sd_trips`
         WHERE driver_id = %d AND status = 'completed' AND completed_at BETWEEN %s AND %s
         GROUP BY DATE(completed_at)",
        $driver_id, $week_start_dt, $week_end_dt
    ), ARRAY_A);

    $daily_map = [];
    foreach ($daily_rows as $row) {
        $daily_map[$row['day']] = (float) $row['total'];
    }

    $daily_breakdown = [];
    for ($i = 0; $i < 7; $i++) {
        $date = gmdate('Y-m-d', strtotime("monday this week +{$i} days"));
        $daily_breakdown[] = [
            'label' => gmdate('D', strtotime($date)),
            'date'  => $date,
            'total' => $daily_map[$date] ?? 0,
        ];
    }

    wp_send_json_success([
        'wallet_balance'  => (float) $driver['wallet_balance'],
        'bank_name'       => $driver['bank_name'],
        'account_number'  => $driver['account_number'],
        'ledger'          => $ledger ?: [],
        'payouts'         => $payouts ?: [],
        'earnings' => [
            'week_total'      => $week_earnings,
            'today_total'     => $today_earnings,
            'week_trips'      => $week_trips,
            'rating'          => (float) $driver['rating'],
            'daily_breakdown' => $daily_breakdown,
        ],
    ]);
}

if ($action === 'request_payout') {
    $driver = $wpdb->get_row($wpdb->prepare("SELECT wallet_balance, bank_name, account_number FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $driver_id), ARRAY_A);
    if (!$driver) {
        wp_send_json_error(['message' => 'Driver not found']);
    }

    $amount = (float) ($_POST['amount'] ?? 0);
    if ($amount <= 0) {
        wp_send_json_error(['message' => 'Invalid amount']);
    }

    if ($amount > (float) $driver['wallet_balance']) {
        wp_send_json_error(['message' => 'Insufficient wallet balance']);
    }

    if (empty($driver['bank_name']) || empty($driver['account_number'])) {
        wp_send_json_error(['message' => 'Bank details missing. Please update your profile.']);
    }

    $idempotency_key = sanitize_text_field($_POST['idempotency_key'] ?? '');
    if ($idempotency_key) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM `{$wpdb->prefix}sd_payouts` WHERE provider_ref = %s LIMIT 1", $idempotency_key));
        if ($existing) {
            wp_send_json_error(['message' => 'Payout already requested']);
        }
    }

    idibia_transaction_start();

    // Deduct balance
    $updated = $wpdb->query($wpdb->prepare("UPDATE `{$wpdb->prefix}sd_drivers` SET wallet_balance = wallet_balance - %f WHERE id = %d AND wallet_balance >= %f", $amount, $driver_id, $amount));
    if (!$updated) {
        idibia_transaction_rollback();
        wp_send_json_error(['message' => 'Could not deduct balance. Please try again.']);
    }

    $provider_ref = $idempotency_key ?: ('req-' . $driver_id . '-' . time() . rand(100, 999));

    // Create payout
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'sd_payouts',
        [
            'driver_id' => $driver_id,
            'amount' => $amount,
            'status' => 'pending',
            'provider_ref' => $provider_ref,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ],
        ['%d', '%f', '%s', '%s', '%s', '%s']
    );

    if (!$inserted) {
        idibia_transaction_rollback();
        wp_send_json_error(['message' => 'Could not create payout request.']);
    }

    $payout_id = $wpdb->insert_id;

    // Create ledger entry
    $wpdb->insert(
        $wpdb->prefix . 'sd_wallet_ledger',
        [
            'driver_id' => $driver_id,
            'amount' => -$amount,
            'entry_type' => 'payout',
            'reference_id' => $payout_id,
            'description' => 'Payout requested',
            'created_at' => gmdate('Y-m-d H:i:s')
        ],
        ['%d', '%f', '%s', '%d', '%s', '%s']
    );

    idibia_transaction_commit();

    wp_send_json_success(['message' => 'Payout requested successfully']);
}

if ($action === 'download_earnings_statement') {
    require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-pdf.php';

    // ── Determine date range ──────────────────────────────
    $period    = sanitize_key( $_POST['period'] ?? 'this_month' );
    $date_from = sanitize_text_field( $_POST['date_from'] ?? '' );
    $date_to   = sanitize_text_field( $_POST['date_to']   ?? '' );

    if ( $period === 'this_week' ) {
        $date_from = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
        $date_to   = gmdate( 'Y-m-d', strtotime( 'sunday this week' ) );
    } elseif ( $period === 'last_week' ) {
        $date_from = gmdate( 'Y-m-d', strtotime( 'monday last week' ) );
        $date_to   = gmdate( 'Y-m-d', strtotime( 'sunday last week' ) );
    } elseif ( $period === 'last_month' ) {
        $date_from = gmdate( 'Y-m-01', strtotime( 'first day of last month' ) );
        $date_to   = gmdate( 'Y-m-t',  strtotime( 'last day of last month' ) );
    } else {
        // Default: this_month or custom date_from/date_to
        if ( ! $date_from || ! $date_to ) {
            $date_from = gmdate( 'Y-m-01' );
            $date_to   = gmdate( 'Y-m-d' );
        }
        // Validate dates
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ||
             ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
            wp_send_json_error( [ 'message' => 'Invalid date format. Use YYYY-MM-DD.' ] );
        }
    }

    $dt_from = $date_from . ' 00:00:00';
    $dt_to   = $date_to   . ' 23:59:59';

    // ── Fetch driver info ─────────────────────────────────
    $driver = $wpdb->get_row( $wpdb->prepare(
        "SELECT full_name, email, vehicle_type FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1",
        $driver_id
    ), ARRAY_A );
    if ( ! $driver ) {
        wp_send_json_error( [ 'message' => 'Driver not found.' ] );
    }

    // ── Fetch completed trips in period ───────────────────
    $trips = $wpdb->get_results( $wpdb->prepare(
        "SELECT trip_ref, pickup_address, dropoff_address, pickup, dropoff,
                COALESCE(NULLIF(final_fare,0), NULLIF(fare_estimate,0), fare, 0) AS fare_amount,
                platform_pct, completed_at
         FROM `{$wpdb->prefix}sd_trips`
         WHERE driver_id = %d AND status = 'completed'
           AND completed_at BETWEEN %s AND %s
         ORDER BY completed_at ASC",
        $driver_id, $dt_from, $dt_to
    ), ARRAY_A ) ?: [];

    // ── Fetch bonuses & penalties from wallet ledger ──────
    $extras = $wpdb->get_results( $wpdb->prepare(
        "SELECT entry_type, amount, description, created_at
         FROM `{$wpdb->prefix}sd_wallet_ledger`
         WHERE driver_id = %d
           AND entry_type IN ('bonus','penalty','refund')
           AND created_at BETWEEN %s AND %s
         ORDER BY created_at ASC",
        $driver_id, $dt_from, $dt_to
    ), ARRAY_A ) ?: [];

    // ── Compute totals ────────────────────────────────────
    $total_gross    = 0.0;
    $total_comm     = 0.0;
    $total_net      = 0.0;
    $total_bonuses  = 0.0;
    $total_penalties = 0.0;

    foreach ( $trips as &$t ) {
        $fare     = (float) $t['fare_amount'];
        $pct      = min( 100, max( 0, (float) ( $t['platform_pct'] ?? 20 ) ) );
        $comm     = round( $fare * $pct / 100, 2 );
        $net      = round( $fare - $comm, 2 );
        $t['commission']  = $comm;
        $t['net_earned']  = $net;
        $total_gross     += $fare;
        $total_comm      += $comm;
        $total_net       += $net;
        $t['pickup_label']  = $t['pickup_address'] ?: $t['pickup'] ?: '';
        $t['dropoff_label'] = $t['dropoff_address'] ?: $t['dropoff'] ?: '';
    }
    unset( $t );

    foreach ( $extras as $e ) {
        if ( $e['entry_type'] === 'bonus' ) {
            $total_bonuses += (float) $e['amount'];
        } elseif ( in_array( $e['entry_type'], [ 'penalty', 'refund' ], true ) ) {
            $total_penalties += abs( (float) $e['amount'] );
        }
    }

    $net_payout = $total_net + $total_bonuses - $total_penalties;

    // ── Build PDF ─────────────────────────────────────────
    $pdf = new IdibiaPDF();
    $ml  = IdibiaPDF::ML;
    $pw  = IdibiaPDF::PW;
    $mr  = IdibiaPDF::MR;
    $rw  = $pw - $mr;
    $cw  = $pdf->cw();

    // Header band
    $pdf->fill_rect( $ml, 45, $cw, 60, 0.102, 0.102, 0.18 );
    $pdf->text( $ml + 16, 70, 'IDIBIA', 'F2', 20 );
    $pdf->text( $ml + 16, 88, 'Earnings Statement', 'F1', 10 );
    $pdf->text_r( $rw - 16, 70, strtoupper( $driver['vehicle_type'] ?? '' ), 'F2', 10 );
    $pdf->text_r( $rw - 16, 85, $driver['full_name'] ?? '', 'F1', 9 );
    $pdf->set_y( 124 );

    // Period line
    $period_label = gmdate( 'M j, Y', strtotime( $date_from ) ) . ' — ' . gmdate( 'M j, Y', strtotime( $date_to ) );
    $pdf->set_font( '', 9 );
    $pdf->text( $ml, $pdf->get_y(), 'Period: ' );
    $pdf->set_font( 'B', 9 );
    $pdf->text( $ml + 38, $pdf->get_y(), $period_label );
    $pdf->ln( 18 );

    // ── Trip table ────────────────────────────────────────
    $pdf->set_font( 'B', 9 );
    $pdf->text( $ml, $pdf->get_y(), 'TRIP DETAILS' );
    $pdf->ln( 5 );
    $pdf->hline( $pdf->get_y() );
    $pdf->ln( 8 );

    // Column positions
    $col = [
        'date'    => $ml,
        'ref'     => $ml + 52,
        'pickup'  => $ml + 108,
        'dropoff' => $ml + 218,
        'fare'    => $rw - 80,
        'comm'    => $rw - 40,
        'net'     => $rw,
    ];

    // Table header row
    $pdf->fill_rect( $ml, $pdf->get_y() - 2, $cw, 14, 0.93, 0.93, 0.96 );
    $pdf->set_font( 'B', 7.5 );
    $y = $pdf->get_y() + 9;
    $pdf->text( $col['date'],    $y, 'Date' );
    $pdf->text( $col['ref'],     $y, 'Ref' );
    $pdf->text( $col['pickup'],  $y, 'Pickup' );
    $pdf->text( $col['dropoff'], $y, 'Drop-off' );
    $pdf->text_r( $col['fare'],  $y, 'Fare' );
    $pdf->text_r( $col['comm'],  $y, 'Comm.' );
    $pdf->text_r( $col['net'],   $y, 'Net' );
    $pdf->ln( 18 );

    if ( empty( $trips ) ) {
        $pdf->set_font( 'I', 9 );
        $pdf->text( $ml, $pdf->get_y(), 'No completed trips in this period.' );
        $pdf->ln( 16 );
    } else {
        $row_h  = 14;
        $max_w  = 94; // max chars before truncation for address columns
        foreach ( $trips as $i => $t ) {
            $pdf->check_break( $row_h + 4 );

            // Alternating row shading
            if ( $i % 2 === 0 ) {
                $pdf->fill_rect( $ml, $pdf->get_y() - 2, $cw, $row_h, 0.975, 0.975, 0.985 );
            }

            $y = $pdf->get_y() + 9;
            $pdf->set_font( '', 7.5 );
            $trip_date = gmdate( 'M j', strtotime( $t['completed_at'] ) );
            $pickup_s  = mb_strimwidth( $t['pickup_label'],  0, 16, '..' );
            $dropoff_s = mb_strimwidth( $t['dropoff_label'], 0, 16, '..' );

            $pdf->text( $col['date'],    $y, $trip_date );
            $pdf->text( $col['ref'],     $y, $t['trip_ref'] ?? '' );
            $pdf->text( $col['pickup'],  $y, $pickup_s );
            $pdf->text( $col['dropoff'], $y, $dropoff_s );
            $pdf->text_r( $col['fare'],  $y, number_format( (float) $t['fare_amount'], 2 ) );
            $pdf->text_r( $col['comm'],  $y, number_format( $t['commission'], 2 ) );
            $pdf->text_r( $col['net'],   $y, number_format( $t['net_earned'], 2 ) );
            $pdf->ln( $row_h );
        }
    }

    $pdf->ln( 6 );

    // ── Extras (bonuses / penalties) ──────────────────────
    if ( ! empty( $extras ) ) {
        $pdf->check_break( 40 );
        $pdf->set_font( 'B', 9 );
        $pdf->text( $ml, $pdf->get_y(), 'ADJUSTMENTS' );
        $pdf->ln( 5 );
        $pdf->hline( $pdf->get_y() );
        $pdf->ln( 8 );
        foreach ( $extras as $e ) {
            $pdf->check_break( 14 );
            $label = ucfirst( $e['entry_type'] ) . ': ' . ( $e['description'] ?? '' );
            $label = mb_strimwidth( $label, 0, 70, '..' );
            $sign  = $e['entry_type'] === 'bonus' ? '+' : '-';
            $pdf->set_font( '', 9 );
            $pdf->text( $ml, $pdf->get_y(), $label );
            $pdf->set_font( 'B', 9 );
            $pdf->text_r( $rw, $pdf->get_y(), $sign . 'NGN ' . number_format( abs( (float) $e['amount'] ), 2 ) );
            $pdf->ln( 13 );
        }
        $pdf->ln( 4 );
    }

    // ── Summary totals ────────────────────────────────────
    $pdf->check_break( 90 );
    $pdf->set_font( 'B', 9 );
    $pdf->text( $ml, $pdf->get_y(), 'SUMMARY' );
    $pdf->ln( 5 );
    $pdf->hline( $pdf->get_y() );
    $pdf->ln( 8 );

    $summary_rows = [
        [ 'Gross Earnings (before commission)', sprintf( 'NGN %s', number_format( $total_gross,     2 ) ) ],
        [ 'Total Commission Deducted',          sprintf( 'NGN %s', number_format( $total_comm,      2 ) ) ],
        [ 'Net Trip Earnings',                  sprintf( 'NGN %s', number_format( $total_net,       2 ) ) ],
        [ 'Total Bonuses',                      sprintf( '+NGN %s', number_format( $total_bonuses,  2 ) ) ],
        [ 'Total Penalties / Deductions',       sprintf( '-NGN %s', number_format( $total_penalties, 2 ) ) ],
    ];

    $row_h = 15;
    foreach ( $summary_rows as [ $label, $val ] ) {
        $pdf->set_font( '', 10 );
        $pdf->text( $ml, $pdf->get_y(), $label );
        $pdf->set_font( '', 10 );
        $pdf->text_r( $rw, $pdf->get_y(), $val );
        $pdf->ln( $row_h );
    }

    // Net payout shaded row
    $pdf->check_break( 26 );
    $pdf->ln( 4 );
    $pdf->fill_rect( $ml, $pdf->get_y() - 2, $cw, 22, 0.102, 0.102, 0.18 );
    $pdf->set_font( 'B', 12 );
    $pdf->text( $ml + 8, $pdf->get_y() + 14, 'NET PAYOUT', 'F2', 12 );
    $pdf->text_r( $rw - 8, $pdf->get_y() + 14,
        sprintf( 'NGN %s', number_format( max( 0.0, $net_payout ), 2 ) ), 'F2', 12 );
    $pdf->ln( 28 );

    // ── Footer ────────────────────────────────────────────
    $pdf->ln( 10 );
    $pdf->hline( $pdf->get_y(), null, null, 0.2 );
    $pdf->ln( 8 );
    $pdf->set_font( 'I', 7.5 );
    $gen_date = gmdate( 'F j, Y' );
    $footer   = "Generated on {$gen_date} · Idibia Platform · All amounts in NGN";
    $fx       = ( IdibiaPDF::PW - $pdf->str_width( $footer, 7.5, 'F3' ) ) / 2;
    $pdf->text( $fx, $pdf->get_y(), $footer, 'F3', 7.5 );

    $bytes = $pdf->output();
    $from  = str_replace( '-', '', $date_from );
    $to    = str_replace( '-', '', $date_to   );
    $fname = "idibia-earnings-{$from}-{$to}.pdf";

    if ( ob_get_level() ) ob_end_clean();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="' . $fname . '"' );
    header( 'Content-Length: ' . strlen( $bytes ) );
    header( 'Cache-Control: private, max-age=0, must-revalidate' );
    echo $bytes;
    exit;
}

wp_send_json_error(['message' => 'Invalid action']);
