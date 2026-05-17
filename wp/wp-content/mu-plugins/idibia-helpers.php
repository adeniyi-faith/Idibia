<?php
/**
 * Plugin Name: Idibia Core Helpers
 * Description: Shared helper functions for Idibia backend. Place in /wp-content/mu-plugins/
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sends a clean JSON response, ensuring output buffers are cleared.
 */
function idibia_json_response( array $data, int $status_code = 200 ) {
    while ( ob_get_level() > 0 ) {
        ob_end_clean();
    }
    http_response_code( $status_code );
    header( 'Content-Type: application/json; charset=utf-8' );
    echo wp_json_encode( $data );
    exit;
}

/**
 * Returns the current authenticated actor (customer or driver) ID, or 0 if not logged in.
 */
function idibia_get_current_actor( string $type ): int {
    if ( ! is_user_logged_in() ) {
        return 0;
    }

    $user_id = get_current_user_id();
    $account_type = get_user_meta( $user_id, 'idibia_account_type', true );

    if ( $account_type !== $type ) {
        return 0;
    }

    $meta_key = $type === 'driver' ? 'idibia_driver_id' : 'idibia_customer_id';
    return (int) get_user_meta( $user_id, $meta_key, true );
}

/**
 * Logs a trip event to the sd_trip_events table.
 */
function idibia_log_event( int $trip_id, string $event_type, array $event_data = [] ): bool {
    global $wpdb;

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'sd_trip_events',
        [
            'trip_id'    => $trip_id,
            'event_type' => $event_type,
            'event_data' => ! empty( $event_data ) ? wp_json_encode( $event_data ) : null,
        ],
        [ '%d', '%s', '%s' ]
    );

    return $inserted !== false;
}

/**
 * Transaction helpers.
 */
function idibia_transaction_start() {
    global $wpdb;
    $wpdb->query( 'START TRANSACTION' );
}

function idibia_transaction_commit() {
    global $wpdb;
    $wpdb->query( 'COMMIT' );
}

function idibia_transaction_rollback() {
    global $wpdb;
    $wpdb->query( 'ROLLBACK' );
}

/**
 * Rate limiting helper using WordPress Transients API.
 * Returns true if allowed, false if rate limited.
 */
function idibia_check_rate_limit( string $action, string $ip, int $max_attempts = 5, int $timeout = 300 ): bool {
    $transient_name = 'rate_limit_' . md5( $action . '_' . $ip );
    $attempts = (int) get_transient( $transient_name );

    if ( $attempts >= $max_attempts ) {
        return false;
    }

    set_transient( $transient_name, $attempts + 1, $timeout );
    return true;
}
/**
 * Creates an in-app notification for a customer, driver, or admin actor.
 */
function idibia_notify_user( int $user_id, string $user_type, string $title, string $body ): bool {
    global $wpdb;

    if ( $user_id <= 0 || ! in_array( $user_type, [ 'customer', 'driver', 'admin' ], true ) ) {
        return false;
    }

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'sd_notifications',
        [
            'user_id'   => $user_id,
            'user_type' => $user_type,
            'title'     => wp_strip_all_tags( $title ),
            'body'      => wp_strip_all_tags( $body ),
            'is_read'   => 0,
        ],
        [ '%d', '%s', '%s', '%s', '%d' ]
    );

    return $inserted !== false;
}

/**
 * Sends lifecycle notifications to the trip customer, assigned driver, and admins.
 */
function idibia_notify_trip_participants( int $trip_id, string $event_type, array $context = [] ): void {
    global $wpdb;

    $trip = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, trip_ref, customer_id, driver_id, payment_status, dispatch_status FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1",
        $trip_id
    ), ARRAY_A );

    if ( ! $trip ) {
        return;
    }

    $labels = [
        'trip_created'            => [ 'Trip created', 'We are searching for a verified driver for trip #' . $trip['trip_ref'] . '.' ],
        'dispatch_offers_created' => [ 'Finding your driver', 'Your request has been sent to nearby verified drivers.' ],
        'dispatch_no_driver'      => [ 'No driver available yet', 'We are still watching for nearby drivers and will update this trip.' ],
        'offer_accepted'          => [ 'Driver assigned', 'A driver has accepted trip #' . $trip['trip_ref'] . '.' ],
        'driver_en_route'         => [ 'Driver arriving', 'Your driver is on the way to the pickup location.' ],
        'driver_arrived_pickup'   => [ 'Driver at pickup', 'Your driver has arrived at the pickup location.' ],
        'package_picked_up'       => [ 'Package picked up', 'Your package is now on the way.' ],
        'driver_arrived_dropoff'  => [ 'Near drop-off', 'Your driver has arrived at the drop-off location.' ],
        'trip_completed'          => [ 'Delivered', 'Trip #' . $trip['trip_ref'] . ' has been completed.' ],
        'trip_cancelled'          => [ 'Trip cancelled', 'Trip #' . $trip['trip_ref'] . ' was cancelled.' ],
        'payment_captured'        => [ 'Payment captured', 'Payment for trip #' . $trip['trip_ref'] . ' has been captured.' ],
        'payment_proof_uploaded'  => [ 'Payment proof uploaded', 'Payment proof for trip #' . $trip['trip_ref'] . ' is waiting for admin review.' ],
        'payment_approved'        => [ 'Payment approved', 'Manual transfer for trip #' . $trip['trip_ref'] . ' has been approved.' ],
        'payment_rejected'        => [ 'Payment needs attention', 'Manual transfer proof for trip #' . $trip['trip_ref'] . ' was rejected. Please upload a clearer proof.' ],
        'payment_refunded'        => [ 'Refund updated', 'A refund update is available for trip #' . $trip['trip_ref'] . '.' ],
        'support_reply'           => [ 'Support replied', 'Support has replied about trip #' . $trip['trip_ref'] . '.' ],
    ];

    if ( ! isset( $labels[ $event_type ] ) ) {
        return;
    }

    [ $title, $body ] = $labels[ $event_type ];
    if ( ! empty( $context['body'] ) ) {
        $body = (string) $context['body'];
    }

    idibia_notify_user( (int) $trip['customer_id'], 'customer', $title, $body );
    if ( ! empty( $trip['driver_id'] ) ) {
        idibia_notify_user( (int) $trip['driver_id'], 'driver', $title, $body );
    }

    $admins = get_users( [ 'role__in' => [ 'administrator' ], 'fields' => [ 'ID' ], 'number' => 20 ] );
    foreach ( $admins as $admin ) {
        idibia_notify_user( (int) $admin->ID, 'admin', $title, $body );
    }

    idibia_pusher_broadcast_trip( $trip_id, $event_type, [ 'title' => $title, 'body' => $body ] );
}

/**
 * Returns public Pusher settings for browser clients.
 */
function idibia_pusher_public_config(): array {
    require_once __DIR__ . '/idibia-config.php';

    $key     = (string) ( defined( 'IDIBIA_PUSHER_KEY' ) ? IDIBIA_PUSHER_KEY : '' );
    $cluster = (string) ( defined( 'IDIBIA_PUSHER_CLUSTER' ) ? IDIBIA_PUSHER_CLUSTER : '' );

    return [
        'enabled'      => idibia_pusher_is_configured(),
        'key'          => $key,
        'cluster'      => $cluster,
        'authEndpoint' => '/pusher-auth-api.php',
        'authNonce'    => wp_create_nonce( 'idibia_pusher_auth' ),
    ];
}

/**
 * Returns true once all server-side Pusher credentials have been replaced.
 */
function idibia_pusher_is_configured(): bool {
    require_once __DIR__ . '/idibia-config.php';

    $values = [
        defined( 'IDIBIA_PUSHER_APP_ID' ) ? IDIBIA_PUSHER_APP_ID : '',
        defined( 'IDIBIA_PUSHER_KEY' ) ? IDIBIA_PUSHER_KEY : '',
        defined( 'IDIBIA_PUSHER_SECRET' ) ? IDIBIA_PUSHER_SECRET : '',
        defined( 'IDIBIA_PUSHER_CLUSTER' ) ? IDIBIA_PUSHER_CLUSTER : '',
    ];

    foreach ( $values as $value ) {
        if ( $value === '' || strpos( (string) $value, 'REPLACE_ME' ) !== false ) {
            return false;
        }
    }

    return true;
}

function idibia_pusher_trip_channel( int $trip_id ): string {
    return 'private-trip-' . $trip_id;
}

function idibia_pusher_driver_channel( int $driver_id ): string {
    return 'private-driver-' . $driver_id;
}

/**
 * Broadcasts an event through Pusher's HTTP API. No-ops while placeholders are configured.
 */
function idibia_pusher_trigger( $channels, string $event_name, array $payload ): bool {
    if ( ! idibia_pusher_is_configured() ) {
        return false;
    }

    require_once __DIR__ . '/idibia-config.php';

    $channels = array_values( array_unique( array_filter( (array) $channels ) ) );
    if ( empty( $channels ) ) {
        return false;
    }

    $body = wp_json_encode( [
        'name'     => $event_name,
        'channels' => $channels,
        'data'     => wp_json_encode( $payload ),
    ] );

    $path = '/apps/' . rawurlencode( IDIBIA_PUSHER_APP_ID ) . '/events';
    $query = [
        'auth_key'       => IDIBIA_PUSHER_KEY,
        'auth_timestamp' => time(),
        'auth_version'   => '1.0',
        'body_md5'       => md5( $body ),
    ];
    ksort( $query );

    $query_string = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
    $signature = hash_hmac( 'sha256', "POST\n{$path}\n{$query_string}", IDIBIA_PUSHER_SECRET );
    $url = sprintf(
        'https://api-%s.pusher.com%s?%s&auth_signature=%s',
        rawurlencode( IDIBIA_PUSHER_CLUSTER ),
        $path,
        $query_string,
        $signature
    );

    $response = wp_remote_post( $url, [
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => $body,
        'timeout' => 3,
    ] );

    return ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) < 300;
}

/**
 * Broadcasts trip lifecycle changes to customers, assigned drivers, and admins watching the trip.
 */
function idibia_pusher_broadcast_trip( int $trip_id, string $event_type, array $context = [] ): bool {
    global $wpdb;

    if ( $trip_id <= 0 ) {
        return false;
    }

    $trip = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, trip_ref, customer_id, driver_id, status, dispatch_status, payment_status FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1",
        $trip_id
    ), ARRAY_A );

    if ( ! $trip ) {
        return false;
    }

    $payload = array_merge( [
        'event_type'      => $event_type,
        'trip_id'         => (int) $trip['id'],
        'trip_ref'        => $trip['trip_ref'],
        'customer_id'     => (int) $trip['customer_id'],
        'driver_id'       => ! empty( $trip['driver_id'] ) ? (int) $trip['driver_id'] : null,
        'status'          => $trip['status'],
        'dispatch_status' => $trip['dispatch_status'],
        'payment_status'  => $trip['payment_status'],
        'sent_at'         => gmdate( 'c' ),
    ], $context );

    return idibia_pusher_trigger( [ idibia_pusher_trip_channel( $trip_id ) ], 'trip.updated', $payload );
}

/**
 * Broadcasts to drivers when their offer list or active trip may have changed.
 */
function idibia_pusher_broadcast_driver_offers( array $driver_ids, string $event_type, array $context = [] ): void {
    $channels = [];
    foreach ( $driver_ids as $driver_id ) {
        $driver_id = (int) $driver_id;
        if ( $driver_id > 0 ) {
            $channels[] = idibia_pusher_driver_channel( $driver_id );
        }
    }

    if ( empty( $channels ) ) {
        return;
    }

    idibia_pusher_trigger( $channels, 'driver.offers.updated', array_merge( [
        'event_type' => $event_type,
        'sent_at'    => gmdate( 'c' ),
    ], $context ) );
}

/**
 * Broadcasts a driver's latest location to the active trip channel.
 */
function idibia_pusher_broadcast_driver_location( int $trip_id, int $driver_id, float $lat, float $lng, ?float $heading = null ): bool {
    return idibia_pusher_trigger( [ idibia_pusher_trip_channel( $trip_id ) ], 'driver.location.updated', [
        'trip_id'   => $trip_id,
        'driver_id' => $driver_id,
        'lat'       => $lat,
        'lng'       => $lng,
        'heading'   => $heading,
        'sent_at'   => gmdate( 'c' ),
    ] );
}

/**
 * Reads payment settings. Manual transfer is active now; gateways are placeholders for later.
 */
function idibia_payment_settings(): array {
    global $wpdb;
    require_once __DIR__ . '/idibia-config.php';

    $rows = $wpdb->get_results( "SELECT setting_key, setting_value FROM `{$wpdb->prefix}sd_settings`", ARRAY_A ) ?: [];
    $settings = [];
    foreach ( $rows as $row ) {
        $settings[ $row['setting_key'] ] = $row['setting_value'];
    }

    return [
        'active_provider' => $settings['payment_active_provider'] ?? 'manual_transfer',
        'manual_transfer' => [
            'bank_name'      => $settings['manual_bank_name'] ?? '',
            'account_name'   => $settings['manual_account_name'] ?? '',
            'account_number' => $settings['manual_account_number'] ?? '',
            'instructions'   => $settings['manual_payment_instructions'] ?? 'Transfer the exact fare, then upload your receipt for admin approval.',
        ],
        'paystack' => [
            'public_key' => $settings['paystack_public_key'] ?? ( defined( 'IDIBIA_PAYSTACK_PUBLIC_KEY' ) ? IDIBIA_PAYSTACK_PUBLIC_KEY : '' ),
            'secret_key' => $settings['paystack_secret_key'] ?? ( defined( 'IDIBIA_PAYSTACK_SECRET_KEY' ) ? IDIBIA_PAYSTACK_SECRET_KEY : '' ),
            'enabled'    => ! empty( $settings['paystack_enabled'] ),
        ],
        'flutterwave' => [
            'public_key' => $settings['flutterwave_public_key'] ?? ( defined( 'IDIBIA_FLUTTERWAVE_PUBLIC_KEY' ) ? IDIBIA_FLUTTERWAVE_PUBLIC_KEY : '' ),
            'secret_key' => $settings['flutterwave_secret_key'] ?? ( defined( 'IDIBIA_FLUTTERWAVE_SECRET_KEY' ) ? IDIBIA_FLUTTERWAVE_SECRET_KEY : '' ),
            'enabled'    => ! empty( $settings['flutterwave_enabled'] ),
        ],
    ];
}

function idibia_public_payment_settings(): array {
    $settings = idibia_payment_settings();
    return [
        'active_provider' => $settings['active_provider'],
        'manual_transfer' => $settings['manual_transfer'],
        'paystack'        => [
            'public_key' => $settings['paystack']['public_key'],
            'enabled'    => (bool) $settings['paystack']['enabled'],
        ],
        'flutterwave'    => [
            'public_key' => $settings['flutterwave']['public_key'],
            'enabled'    => (bool) $settings['flutterwave']['enabled'],
        ],
    ];
}

function idibia_ensure_manual_payment_columns(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'sd_payments';
    $wpdb->query( "ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `proof_path` VARCHAR(255) NULL AFTER `provider_ref`" );
    $wpdb->query( "ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `admin_notes` TEXT NULL AFTER `status`" );
    $wpdb->query( "ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `reviewed_by` BIGINT UNSIGNED NULL AFTER `admin_notes`" );
    $wpdb->query( "ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `reviewed_at` DATETIME NULL AFTER `reviewed_by`" );
}

function idibia_get_trip_payment( int $trip_id ): ?array {
    global $wpdb;
    $payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_payments` WHERE trip_id = %d ORDER BY id DESC LIMIT 1", $trip_id ), ARRAY_A );
    return $payment ?: null;
}

function idibia_payment_public_payload( int $trip_id ): array {
    $payment = idibia_get_trip_payment( $trip_id );
    $settings = idibia_public_payment_settings();
    $upload = wp_upload_dir();
    $proof_url = '';
    if ( $payment && ! empty( $payment['proof_path'] ?? '' ) && empty( $upload['error'] ) ) {
        $proof_url = trailingslashit( $upload['baseurl'] ) . ltrim( $payment['proof_path'], '/' );
    }

    return [
        'settings' => $settings,
        'record'   => $payment ? [
            'id'           => (int) $payment['id'],
            'amount'       => (float) $payment['amount'],
            'provider'     => $payment['provider'],
            'provider_ref' => $payment['provider_ref'],
            'proof_path'   => $payment['proof_path'] ?? null,
            'proof_url'    => $proof_url,
            'status'       => $payment['status'],
            'admin_notes'  => $payment['admin_notes'] ?? null,
            'reviewed_at'  => $payment['reviewed_at'] ?? null,
        ] : null,
    ];
}

/**
 * Credits the driver wallet once per completed/captured trip.
 */
function idibia_credit_driver_for_trip( int $trip_id ): bool {
    global $wpdb;

    $trip = $wpdb->get_row( $wpdb->prepare( "SELECT id, trip_ref, driver_id, final_fare, fare_estimate, fare, platform_pct, status, payment_status FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1", $trip_id ), ARRAY_A );
    if ( ! $trip || empty( $trip['driver_id'] ) || $trip['status'] !== 'completed' || $trip['payment_status'] !== 'captured' ) {
        return false;
    }

    $already_credited = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_wallet_ledger` WHERE entry_type = 'earning' AND reference_id = %d", $trip_id ) );
    if ( $already_credited > 0 ) {
        return false;
    }

    $fare = (float) ( $trip['final_fare'] ?: $trip['fare_estimate'] ?: $trip['fare'] );
    $driver_share = round( $fare * ( 100 - (int) $trip['platform_pct'] ) / 100, 2 );
    if ( $driver_share <= 0 ) {
        return false;
    }

    $wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->prefix}sd_drivers` SET total_trips = total_trips + 1, wallet_balance = wallet_balance + %f WHERE id = %d", $driver_share, (int) $trip['driver_id'] ) );
    $wpdb->insert( $wpdb->prefix . 'sd_wallet_ledger', [
        'driver_id'    => (int) $trip['driver_id'],
        'amount'       => $driver_share,
        'entry_type'   => 'earning',
        'reference_id' => $trip_id,
        'description'  => 'Trip ' . $trip['trip_ref'] . ' earning',
    ], [ '%d', '%f', '%s', '%d', '%s' ] );

    return true;
}
