<?php
/** Idibia — Book Trip Handler */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-dispatch-helpers.php';
idibia_clean_json_buffer();


if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
if ( ! idibia_check_rate_limit( 'booking', $ip, 10, 60 ) ) {
    http_response_code( 429 );
    wp_send_json_error( [ 'message' => 'Too many requests. Please try again later.' ] );
}

$auth_type = 'customer';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$customer_id = (int) $GLOBALS['auth_customer_id'];
$quote_id    = sanitize_text_field( wp_unslash( $_POST['quote_id'] ?? '' ) );
$package_metadata = sanitize_text_field( wp_unslash( $_POST['package_details'] ?? '' ) );

if ( ! $quote_id ) wp_send_json_error( [ 'message' => 'Quote ID is required.' ] );

$quote_data = get_transient( 'idibia_quote_' . $quote_id );
if ( ! $quote_data ) {
    wp_send_json_error( [ 'message' => 'Quote has expired or is invalid. Please request a new quote.' ] );
}

// ── MODE DETECTION ──────────────────────────────────────────────────────────
// MODE A: customer sent GPS coords in the booking POST (pickup_lat present).
// MODE B: classic text booking — everything comes from $quote_data unchanged.
$gps_lat = isset( $_POST['pickup_lat'] ) ? (float) $_POST['pickup_lat'] : null;
$gps_lng = isset( $_POST['pickup_lng'] ) ? (float) $_POST['pickup_lng'] : null;
$mode_a  = ( $gps_lat !== null );

if ( $mode_a ) {
    // 1. Validate that the coordinates are real Nigeria coords.
    if ( ! idibia_is_valid_nigeria_coords( $gps_lat, $gps_lng ) ) {
        wp_send_json_error( [ 'message' => 'Invalid GPS coordinates. Please share your location and try again.' ] );
    }

    // 2. Reverse geocode: ask Nominatim to give us a human-readable street label.
    //    This is best-effort only — a failure here does NOT block the booking.
    $pickup_label  = 'Customer GPS Location';
    $nominatim_url = add_query_arg(
        [ 'lat' => $gps_lat, 'lon' => $gps_lng, 'format' => 'json' ],
        'https://nominatim.openstreetmap.org/reverse'
    );
    $nom_response = wp_remote_get( $nominatim_url, [
        'timeout' => 5,
        'headers' => [ 'User-Agent' => 'Idibia Rides/1.0 (contact@idibia.com)' ],
    ] );
    if ( ! is_wp_error( $nom_response ) ) {
        $nom_body = json_decode( wp_remote_retrieve_body( $nom_response ), true );
        if ( ! empty( $nom_body['display_name'] ) ) {
            $pickup_label = sanitize_text_field( $nom_body['display_name'] );
        }
    }

    // 3. Dropoff address label comes directly from the frontend (Google Places formatted it).
    $dropoff_label = sanitize_text_field( wp_unslash( $_POST['dropoff_address'] ?? '' ) );

    // 4. Overwrite the quote_data fields that will be written to sd_trips.
    //    Dropoff coords stay as-is from the quote (already resolved when quote was created).
    $quote_data['pickup_lat']      = $gps_lat;
    $quote_data['pickup_lng']      = $gps_lng;
    $quote_data['pickup_address']  = $pickup_label;
    $quote_data['dropoff_address'] = $dropoff_label ?: $quote_data['dropoff_address'];
}
// ── END MODE DETECTION ───────────────────────────────────────────────────────

$trip_ref = strtoupper( substr( md5( uniqid( '', true ) ), 0, 8 ) );
$delivery_pin = (string) random_int( 1000, 9999 );
$platform_pct = (int) ( $quote['platform_pct'] ?? idibia_get_setting('platform_commission_pct', 20) );

idibia_transaction_start();

$inserted = $wpdb->insert(
    $wpdb->prefix . 'sd_trips',
    [
        'trip_ref'       => $trip_ref,
        'customer_id'    => $customer_id,
        'pickup'         => $quote_data['pickup_address'], // Keep for legacy
        'dropoff'        => $quote_data['dropoff_address'], // Keep for legacy
        'pickup_address' => $quote_data['pickup_address'],
        'dropoff_address'=> $quote_data['dropoff_address'],
        'pickup_lat'     => $quote_data['pickup_lat'],
        'pickup_lng'     => $quote_data['pickup_lng'],
        'dropoff_lat'    => $quote_data['dropoff_lat'],
        'dropoff_lng'    => $quote_data['dropoff_lng'],
        'category'       => $quote_data['vehicle_type'], // Legacy maps vehicle to category
        'service_category'=> $quote_data['service_category'],
        'vehicle_type'   => $quote_data['vehicle_type'],
        'package_metadata'=> $package_metadata,
        'distance_km'    => $quote_data['distance_km'],
        'duration_mins'  => $quote_data['duration_mins'],
        'fare'           => $quote_data['fare_estimate'], // Legacy
        'fare_estimate'  => $quote_data['fare_estimate'],
        'status'         => 'pending',
        'dispatch_status'=> 'searching',
        'delivery_pin'   => $delivery_pin,
        'platform_pct'   => $platform_pct,
        'scheduled_time' => $quote_data['scheduled_time'] ?? null,
    ],
    [
        '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%f', '%d', '%f', '%f', '%s', '%s', '%s', '%d', '%s'
    ]
);

if ( ! $inserted ) {
    idibia_transaction_rollback();
    error_log( "Idibia: Failed to insert trip for customer $customer_id" );
    wp_send_json_error( [ 'message' => 'Could not create your trip. Please try again.' ] );
}

$trip_id = $wpdb->insert_id;
idibia_ensure_manual_payment_columns();
$payment_inserted = $wpdb->insert(
    $wpdb->prefix . 'sd_payments',
    [
        'trip_id'     => $trip_id,
        'customer_id' => $customer_id,
        'amount'      => $quote_data['fare_estimate'],
        'provider'    => 'manual_transfer',
        'status'      => 'pending',
    ],
    [ '%d', '%d', '%f', '%s', '%s' ]
);
if ( false === $payment_inserted ) {
    idibia_transaction_rollback();
    wp_send_json_error( [ 'message' => 'Could not create the payment record. Please try again.' ] );
}

idibia_log_event( $trip_id, 'trip_created', [ 'quote_id' => $quote_id, 'fare' => $quote_data['fare_estimate'] ] );
idibia_notify_trip_participants( $trip_id, 'trip_created' );

idibia_transaction_commit();

$is_scheduled = ! empty( $quote_data['scheduled_time'] ) && strtotime( $quote_data['scheduled_time'] ) > time();
$dispatch_result = [];
if ( ! $is_scheduled ) {
    $dispatch_result = idibia_dispatch_trip( $trip_id );
} else {
    $dispatch_result = [ 'message' => 'Trip scheduled. Dispatch delayed.' ];
}

// Clean up quote so it can't be reused
delete_transient( 'idibia_quote_' . $quote_id );

wp_send_json_success( [
    'message'  => $is_scheduled ? 'Trip scheduled successfully.' : 'Trip created successfully. Searching for a driver.',
    'trip_id'  => $trip_id,
    'trip_ref' => $trip_ref,
    'fare'     => $quote_data['fare_estimate'],
    'dispatch' => $dispatch_result,
] );