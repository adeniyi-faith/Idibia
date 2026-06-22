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
$format  = sanitize_key( $_GET['format'] ?? 'html' );

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
$trip = $wpdb->get_row( $wpdb->prepare(
    "SELECT t.*,
            c.full_name AS customer_name,
            c.email     AS customer_email,
            d.full_name AS driver_name
     FROM `{$wpdb->prefix}sd_trips` t
     LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.customer_id
     LEFT JOIN `{$wpdb->prefix}sd_drivers`   d ON d.id = t.driver_id
     WHERE t.id = %d LIMIT 1",
    $trip_id
), ARRAY_A );

if ( ! $trip ) {
    http_response_code( 404 );
    die( 'Trip not found.' );
}

$payment = idibia_payment_public_payload( $trip_id );

if ( ! in_array( $payment['status'], [ 'approved', 'captured' ], true ) ) {
    http_response_code( 403 );
    die( 'Receipt is only available for approved payments.' );
}

// ── Fare calculations ──────────────────────────────────────────────────────────
$amount       = (float) ( $trip['final_fare'] ?: $trip['fare_estimate'] ?: $trip['fare'] );
$base_fare    = (float) idibia_get_setting( 'base_fare', 500 );
$platform_pct = min( 100, max( 0, (float) ( $trip['platform_pct'] ?? 20 ) ) );
$commission   = round( $amount * $platform_pct / 100, 2 );
$driver_net   = round( $amount - $commission, 2 );
$distance_fare = max( 0.0, $amount - $base_fare );
$distance_km   = (float) ( $trip['distance_km'] ?? 0 );
$date          = gmdate( 'F j, Y, g:i A', strtotime( $trip['created_at'] ) );
$payment_method = ucwords( str_replace( '_', ' ', $payment['provider'] ?? 'bank transfer' ) );
$pickup  = $trip['pickup_address'] ?: $trip['pickup']  ?: '';
$dropoff = $trip['dropoff_address'] ?: $trip['dropoff'] ?: '';
$trip_ref = $trip['trip_ref'] ?? '';

// ── PDF output ─────────────────────────────────────────────────────────────────
if ( $format === 'pdf' ) {
    require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-pdf.php';

    ob_end_clean();

    $pdf = new IdibiaPDF();
    $ml  = IdibiaPDF::ML;
    $pw  = IdibiaPDF::PW;
    $mr  = IdibiaPDF::MR;
    $rw  = $pw - $mr; // right-edge x for right-aligned text

    // ── Header block ──────────────────────────────────────
    $pdf->fill_rect( $ml, 45, $pw - $ml - $mr, 54, 0.102, 0.102, 0.18 ); // dark navy background

    // White text on dark bg
    $pdf->text( $ml + 16, 68, 'IDIBIA', 'F2', 22 );
    $pdf->text( $ml + 16, 86, 'Official Payment Receipt', 'F1', 10 );
    $pdf->text_r( $rw - 16, 68, "Trip #" . $trip_ref, 'F2', 11 );
    $pdf->text_r( $rw - 16, 83, $date . ' UTC', 'F1', 9 );

    $pdf->set_y( 118 );

    // ── Section: Trip details ────────────────────────────
    $pdf->set_font( 'B', 9 );
    $pdf->text( $ml, $pdf->get_y(), 'TRIP DETAILS' );
    $pdf->ln( 4 );
    $pdf->hline( $pdf->get_y() );
    $pdf->ln( 8 );

    $row_h   = 16;
    $col_val = $rw;

    $rows_detail = [
        [ 'Customer',       $trip['customer_name'] ?? '' ],
        [ 'Driver',         $trip['driver_name']   ?? 'N/A' ],
        [ 'Pickup',         $pickup ],
        [ 'Drop-off',       $dropoff ],
        [ 'Distance',       $distance_km > 0 ? number_format( $distance_km, 1 ) . ' km' : 'N/A' ],
        [ 'Payment Method', $payment_method ],
        [ 'Payment Status', ucfirst( $payment['status'] ) ],
    ];

    foreach ( $rows_detail as [ $label, $value ] ) {
        $pdf->check_break( $row_h + 4 );
        $pdf->set_font( '', 10 );
        $pdf->text( $ml, $pdf->get_y(), $label );
        $pdf->set_font( 'B', 10 );
        $pdf->text_r( $col_val, $pdf->get_y(), (string) $value );
        $pdf->ln( $row_h );
    }

    $pdf->ln( 6 );

    // ── Section: Fare breakdown ───────────────────────────
    $pdf->set_font( 'B', 9 );
    $pdf->text( $ml, $pdf->get_y(), 'FARE BREAKDOWN' );
    $pdf->ln( 4 );
    $pdf->hline( $pdf->get_y() );
    $pdf->ln( 8 );

    $fare_rows = [
        [ 'Base Fare',                      sprintf( 'NGN %s', number_format( $base_fare, 2 ) ) ],
        [ 'Distance Fare (' . ( $distance_km > 0 ? number_format( $distance_km, 1 ) . ' km' : '--' ) . ')',
          sprintf( 'NGN %s', number_format( $distance_fare, 2 ) ) ],
        [ "Platform Commission ({$platform_pct}%)", sprintf( 'NGN %s', number_format( $commission, 2 ) ) ],
        [ 'Driver Earnings',                 sprintf( 'NGN %s', number_format( $driver_net, 2 ) ) ],
    ];

    foreach ( $fare_rows as [ $label, $value ] ) {
        $pdf->check_break( $row_h + 4 );
        $pdf->set_font( '', 10 );
        $pdf->text( $ml, $pdf->get_y(), $label );
        $pdf->set_font( '', 10 );
        $pdf->text_r( $col_val, $pdf->get_y(), $value );
        $pdf->ln( $row_h );
    }

    // Total row with shaded background
    $pdf->check_break( 28 );
    $pdf->ln( 4 );
    $pdf->fill_rect( $ml, $pdf->get_y() - 2, $pw - $ml - $mr, 22, 0.941, 0.941, 0.980 );
    $pdf->set_font( 'B', 12 );
    $pdf->text( $ml + 8, $pdf->get_y() + 13, 'TOTAL PAID' );
    $pdf->text_r( $col_val - 8, $pdf->get_y() + 13, sprintf( 'NGN %s', number_format( $amount, 2 ) ), 'F2', 12 );
    $pdf->ln( 28 );

    // ── Footer ────────────────────────────────────────────
    $pdf->check_break( 40 );
    $pdf->ln( 10 );
    $pdf->hline( $pdf->get_y(), null, null, 0.2 );
    $pdf->ln( 10 );
    $pdf->set_font( 'I', 8 );
    $footer_text = 'This is an official receipt from Idibia. If you have questions, please contact support.';
    $footer_x    = ( IdibiaPDF::PW - $pdf->str_width( $footer_text, 8, 'F3' ) ) / 2;
    $pdf->text( $footer_x, $pdf->get_y(), $footer_text, 'F3', 8 );

    $bytes = $pdf->output();
    $fname = 'receipt-' . preg_replace( '/[^A-Za-z0-9_\-]/', '', $trip_ref ) . '.pdf';

    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="' . $fname . '"' );
    header( 'Content-Length: ' . strlen( $bytes ) );
    header( 'Cache-Control: private, max-age=0, must-revalidate' );
    echo $bytes;
    exit;
}

// ── HTML output (original behaviour) ──────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt - Trip #<?php echo esc_html( $trip_ref ); ?></title>
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
    .btn-row { display: flex; gap: 10px; justify-content: center; margin-top: 20px; flex-wrap: wrap; }
    .btn { padding: 9px 18px; border: none; border-radius: 4px; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 600; }
    .btn-dark { background: #1a1a2e; color: #fff; }
    .btn-outline { background: #fff; color: #1a1a2e; border: 1.5px solid #1a1a2e; }
    @media print {
        body { background: #fff; padding: 0; }
        .receipt { box-shadow: none; border: none; padding: 0; max-width: 100%; }
        .btn-row { display: none; }
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
        <span class="value">#<?php echo esc_html( $trip_ref ); ?></span>
    </div>
    <div class="row">
        <span class="label">Customer</span>
        <span class="value"><?php echo esc_html( $trip['customer_name'] ?? '' ); ?></span>
    </div>
    <?php if ( $trip['driver_name'] ) : ?>
    <div class="row">
        <span class="label">Driver</span>
        <span class="value"><?php echo esc_html( $trip['driver_name'] ); ?></span>
    </div>
    <?php endif; ?>
    <?php if ( $pickup ) : ?>
    <div class="row">
        <span class="label">Pickup</span>
        <span class="value" style="max-width:65%;word-break:break-word"><?php echo esc_html( $pickup ); ?></span>
    </div>
    <?php endif; ?>
    <?php if ( $dropoff ) : ?>
    <div class="row">
        <span class="label">Drop-off</span>
        <span class="value" style="max-width:65%;word-break:break-word"><?php echo esc_html( $dropoff ); ?></span>
    </div>
    <?php endif; ?>
    <div class="row">
        <span class="label">Payment Method</span>
        <span class="value"><?php echo esc_html( $payment_method ); ?></span>
    </div>

    <div style="margin: 30px 0;">
        <div class="row">
            <span class="label">Base Fare</span>
            <span class="value">₦<?php echo number_format( $base_fare, 2 ); ?></span>
        </div>
        <div class="row">
            <span class="label">Distance Fare (<?php echo esc_html( $distance_km > 0 ? number_format( $distance_km, 1 ) . ' km' : '--' ); ?>)</span>
            <span class="value">₦<?php echo number_format( $distance_fare, 2 ); ?></span>
        </div>
        <div class="row">
            <span class="label">Platform Commission (<?php echo (int) $platform_pct; ?>%)</span>
            <span class="value">₦<?php echo number_format( $commission, 2 ); ?></span>
        </div>
        <div class="row">
            <span class="label">Driver Earnings</span>
            <span class="value">₦<?php echo number_format( $driver_net, 2 ); ?></span>
        </div>
        <div class="row total">
            <span class="label">Total Paid</span>
            <span class="value">₦<?php echo number_format( $amount, 2 ); ?></span>
        </div>
    </div>
    <div class="meta">
        <p>If you have any questions about this receipt, please contact support.</p>
        <div class="btn-row" id="action-buttons">
            <button class="btn btn-dark" onclick="window.print()" id="print-btn">Print / Save PDF</button>
            <button class="btn btn-outline" onclick="downloadPDF()" id="dl-btn">Download PDF</button>
        </div>
    </div>
</div>
<script>
(function () {
    var dlBtn = document.getElementById('dl-btn');
    var pdfUrl = window.location.href.replace(/([?&])format=[^&]*/g, '$1').replace(/[?&]$/, '');
    pdfUrl += (pdfUrl.indexOf('?') === -1 ? '?' : '&') + 'format=pdf';
    if (dlBtn) dlBtn.href = pdfUrl;

    window.downloadPDF = function () {
        window.location.href = pdfUrl;
    };

    if (window.matchMedia) {
        var mql = window.matchMedia('print');
        mql.addListener(function (m) {
            var btns = document.getElementById('action-buttons');
            if (btns) btns.style.display = m.matches ? 'none' : 'flex';
        });
    }
})();
</script>
</body>
</html>
<?php

