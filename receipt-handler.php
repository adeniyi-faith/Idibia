<?php
/** Idibia — Receipt Generation Handler */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-load.php';
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';

if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
    http_response_code( 405 );
    die( 'Method not allowed.' );
}

$trip_id = absint( $_GET['trip_id'] ?? 0 );
$token   = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );

if ( $trip_id <= 0 || ! $token ) {
    http_response_code( 400 );
    die( 'Invalid request.' );
}

$expected_token = hash_hmac( 'sha256', $trip_id, wp_salt( 'auth' ) );
if ( ! hash_equals( $expected_token, $token ) ) {
    http_response_code( 403 );
    die( 'Invalid or expired receipt link.' );
}

global $wpdb;
$trip = $wpdb->get_row( $wpdb->prepare( "SELECT t.*, c.full_name AS customer_name, c.email AS customer_email FROM `{$wpdb->prefix}sd_trips` t LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.customer_id WHERE t.id = %d LIMIT 1", $trip_id ), ARRAY_A );

if ( ! $trip ) {
    http_response_code( 404 );
    die( 'Trip not found.' );
}

$payment = idibia_payment_public_payload( $trip_id );

if ( ! in_array( $payment['status'], [ 'approved', 'captured' ], true ) ) {
    http_response_code( 403 );
    die( 'Receipt is only available for approved payments.' );
}

$amount = (float) ( $trip['final_fare'] ?: $trip['fare_estimate'] ?: $trip['fare'] );
$base_fare = (float) idibia_get_setting('base_fare', 500);
$distance_fare = max(0, $amount - $base_fare);
$date = gmdate( 'F j, Y, g:i A', strtotime( $trip['created_at'] ) );

// Simple clean printable HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt - Trip #<?php echo esc_html( $trip['trip_ref'] ); ?></title>
<style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f9f9f9; color: #333; margin: 0; padding: 40px 20px; }
    .receipt { max-width: 500px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 20px; }
    .logo { font-size: 24px; font-weight: 800; color: #000; margin-bottom: 10px; }
    .title { font-size: 14px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin: 0; }
    .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f5f5f5; font-size: 14px; }
    .row.total { font-weight: 700; font-size: 18px; border-top: 2px solid #eee; border-bottom: none; margin-top: 10px; padding-top: 20px; }
    .label { color: #666; }
    .value { text-align: right; }
    .meta { font-size: 12px; color: #888; text-align: center; margin-top: 40px; }
    @media print {
        body { background: #fff; padding: 0; }
        .receipt { box-shadow: none; border: none; padding: 0; max-width: 100%; }
    }
</style>
</head>
<body>
<div class="receipt">
    <div class="header">
        <div class="logo">Idibia</div>
        <p class="title">Payment Receipt</p>
    </div>
    <div class="row">
        <span class="label">Date</span>
        <span class="value"><?php echo esc_html( $date ); ?> UTC</span>
    </div>
    <div class="row">
        <span class="label">Trip Reference</span>
        <span class="value">#<?php echo esc_html( $trip['trip_ref'] ); ?></span>
    </div>
    <div class="row">
        <span class="label">Customer</span>
        <span class="value"><?php echo esc_html( $trip['customer_name'] ); ?></span>
    </div>
    <div class="row">
        <span class="label">Payment Method</span>
        <span class="value"><?php echo esc_html( str_replace('_', ' ', ucfirst($payment['provider'])) ); ?></span>
    </div>

    <div style="margin: 30px 0;">
        <div class="row">
            <span class="label">Base Fare</span>
            <span class="value">₦<?php echo number_format( $base_fare, 2 ); ?></span>
        </div>
        <div class="row">
            <span class="label">Distance Fare (<?php echo esc_html( $trip['distance_km'] ?? '0' ); ?> km)</span>
            <span class="value">₦<?php echo number_format( $distance_fare, 2 ); ?></span>
        </div>
        <div class="row total">
            <span class="label">Total Paid</span>
            <span class="value">₦<?php echo number_format( $amount, 2 ); ?></span>
        </div>
    </div>
    <div class="meta">
        <p>If you have any questions about this receipt, please contact support.</p>
        <button onclick="window.print()" style="margin-top: 15px; padding: 8px 16px; background: #000; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-family: inherit; font-size: 13px;" id="print-btn">Print / Save PDF</button>
    </div>
</div>
<script>
    if (window.matchMedia) {
        var mediaQueryList = window.matchMedia('print');
        mediaQueryList.addListener(function(mql) {
            if (mql.matches) {
                document.getElementById('print-btn').style.display = 'none';
            } else {
                document.getElementById('print-btn').style.display = 'inline-block';
            }
        });
    }
</script>
</body>
</html>
