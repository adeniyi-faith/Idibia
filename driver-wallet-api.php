<?php
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';

idibia_require_method('POST');

if (empty($GLOBALS['auth_driver_id'])) {
    wp_send_json_error(['message' => 'Unauthorized access'], 401);
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

    // Earnings summary: week starts on Monday
    $week_start = gmdate('Y-m-d', strtotime('monday this week'));
    $week_end   = gmdate('Y-m-d', strtotime('sunday this week'));
    $today      = gmdate('Y-m-d');

    $week_earnings = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM `{$wpdb->prefix}sd_wallet_ledger`
         WHERE driver_id = %d AND entry_type IN ('earning','bonus') AND DATE(created_at) BETWEEN %s AND %s",
        $driver_id, $week_start, $week_end
    ));

    $today_earnings = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM `{$wpdb->prefix}sd_wallet_ledger`
         WHERE driver_id = %d AND entry_type IN ('earning','bonus') AND DATE(created_at) = %s",
        $driver_id, $today
    ));

    $week_trips = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_wallet_ledger`
         WHERE driver_id = %d AND entry_type = 'earning' AND DATE(created_at) BETWEEN %s AND %s",
        $driver_id, $week_start, $week_end
    ));

    // Daily breakdown for the current week (Mon–Sun)
    $daily_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(created_at) AS day, COALESCE(SUM(amount), 0) AS total
         FROM `{$wpdb->prefix}sd_wallet_ledger`
         WHERE driver_id = %d AND entry_type IN ('earning','bonus') AND DATE(created_at) BETWEEN %s AND %s
         GROUP BY DATE(created_at)",
        $driver_id, $week_start, $week_end
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

wp_send_json_error(['message' => 'Invalid action']);
