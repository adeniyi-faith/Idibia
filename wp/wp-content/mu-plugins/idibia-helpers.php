<?php
/**
 * Plugin Name: Idibia Core Helpers
 * Description: Shared helper functions for Idibia backend. Place in /wp-content/mu-plugins/
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rewrite upload URLs to use the current request's host.
 * Prevents broken image URLs when WordPress's siteurl in the DB doesn't
 * match the domain the app is actually served from.
 */
add_filter( 'upload_dir', static function ( $dirs ) {
    if ( empty( $_SERVER['HTTP_HOST'] ) ) {
        return $dirs;
    }
    $scheme  = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
    $origin  = $scheme . '://' . $_SERVER['HTTP_HOST'];
    foreach ( [ 'url', 'baseurl' ] as $key ) {
        if ( ! empty( $dirs[ $key ] ) ) {
            $dirs[ $key ] = preg_replace( '#^https?://[^/]+#', $origin, $dirs[ $key ] );
        }
    }
    return $dirs;
} );

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
    global $wpdb, $idibia_transaction_depth;
    $idibia_transaction_depth = (int) ( $idibia_transaction_depth ?? 0 );
    if ( $idibia_transaction_depth === 0 ) {
        $wpdb->query( 'START TRANSACTION' );
    }
    $idibia_transaction_depth++;
}

function idibia_transaction_commit() {
    global $wpdb, $idibia_transaction_depth, $idibia_after_commit_pusher_events;
    $idibia_transaction_depth = max( 0, (int) ( $idibia_transaction_depth ?? 0 ) - 1 );
    if ( $idibia_transaction_depth === 0 ) {
        $wpdb->query( 'COMMIT' );
        $events = $idibia_after_commit_pusher_events ?? [];
        $idibia_after_commit_pusher_events = [];
        foreach ( $events as $event ) {
            idibia_pusher_trigger_now( $event['channels'], $event['event_name'], $event['payload'] );
        }
    }
}

function idibia_transaction_rollback() {
    global $wpdb, $idibia_transaction_depth, $idibia_after_commit_pusher_events;
    $idibia_transaction_depth = 0;
    $idibia_after_commit_pusher_events = [];
    $wpdb->query( 'ROLLBACK' );
}

function idibia_transaction_is_active(): bool {
    global $idibia_transaction_depth;
    return (int) ( $idibia_transaction_depth ?? 0 ) > 0;
}

function idibia_queue_pusher_after_commit( $channels, string $event_name, array $payload ): void {
    global $idibia_after_commit_pusher_events;
    if ( ! is_array( $idibia_after_commit_pusher_events ?? null ) ) {
        $idibia_after_commit_pusher_events = [];
    }
    $idibia_after_commit_pusher_events[] = [
        'channels'   => $channels,
        'event_name' => $event_name,
        'payload'    => $payload,
    ];
}

/**
 * Processes a Paystack webhook payload (Stub).
 */
function idibia_process_paystack_webhook( array $payload ): void {
    global $wpdb;

    $event = $payload['event'] ?? '';
    if ( $event !== 'charge.success' ) {
        return;
    }

    $data      = $payload['data'] ?? [];
    $reference = $data['reference'] ?? '';
    $metadata  = $data['metadata'] ?? [];
    $amount_k  = (int) ( $data['amount'] ?? 0 ); // Paystack sends kobo

    if ( empty( $metadata['topup'] ) || empty( $metadata['customer_id'] ) ) {
        return;
    }

    $customer_id = (int) $metadata['customer_id'];
    $amount      = $amount_k / 100;

    idibia_credit_customer_wallet_topup( $customer_id, $amount, $reference );
}

/**
 * Processes a Flutterwave webhook payload.
 */
function idibia_process_flutterwave_webhook( array $payload ): void {
    global $wpdb;

    $event = $payload['event'] ?? '';
    if ( $event !== 'charge.completed' ) {
        return;
    }

    $data   = $payload['data'] ?? [];
    $status = $data['status'] ?? '';
    if ( $status !== 'successful' ) {
        return;
    }

    $reference = $data['tx_ref'] ?? '';
    $meta      = $data['meta'] ?? [];
    $amount    = (float) ( $data['amount'] ?? 0 );

    if ( empty( $meta['topup'] ) || empty( $meta['customer_id'] ) ) {
        return;
    }

    $customer_id = (int) $meta['customer_id'];
    idibia_credit_customer_wallet_topup( $customer_id, $amount, $reference );
}

/**
 * Credits a customer wallet after a confirmed top-up payment.
 * Idempotent: skips if the reference already exists in the ledger.
 */
function idibia_credit_customer_wallet_topup( int $customer_id, float $amount, string $reference ): void {
    global $wpdb;

    if ( $customer_id <= 0 || $amount <= 0 ) {
        return;
    }

    // Idempotency: don't credit the same reference twice
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM `{$wpdb->prefix}sd_customer_wallet_ledger`
         WHERE customer_id = %d AND description LIKE %s LIMIT 1",
        $customer_id,
        '%' . $wpdb->esc_like( $reference ) . '%'
    ) );

    if ( $exists ) {
        return;
    }

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE `{$wpdb->prefix}sd_customers` SET wallet_balance = wallet_balance + %f WHERE id = %d",
            $amount,
            $customer_id
        )
    );

    $wpdb->insert(
        $wpdb->prefix . 'sd_customer_wallet_ledger',
        [
            'customer_id'  => $customer_id,
            'amount'       => $amount,
            'entry_type'   => 'topup',
            'reference_id' => 0,
            'description'  => 'Wallet top-up via ' . $reference,
        ],
        [ '%d', '%f', '%s', '%d', '%s' ]
    );

    idibia_notify_user( $customer_id, 'customer', 'Wallet Topped Up', "₦" . number_format( $amount, 2 ) . " has been added to your wallet." );
}

/**
 * Low-level wallet ledger credit: inserts a ledger row and bumps wallet_balance.
 * Does NOT check for duplicates — callers must guard idempotency themselves.
 */
function idibia_credit_driver_wallet( int $driver_id, float $amount, string $entry_type, int $reference_id, string $description ): bool {
    global $wpdb;
    if ( $driver_id <= 0 || $amount <= 0 ) {
        return false;
    }
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'sd_wallet_ledger',
        [
            'driver_id'    => $driver_id,
            'amount'       => $amount,
            'entry_type'   => $entry_type,
            'reference_id' => $reference_id,
            'description'  => $description,
            'created_at'   => gmdate( 'Y-m-d H:i:s' ),
        ],
        [ '%d', '%f', '%s', '%d', '%s', '%s' ]
    );
    if ( false === $inserted ) {
        return false;
    }
    $wpdb->query( $wpdb->prepare(
        "UPDATE `{$wpdb->prefix}sd_drivers` SET wallet_balance = wallet_balance + %f WHERE id = %d",
        $amount,
        $driver_id
    ) );
    return true;
}

/**
 * Credits a driver's wallet once for a captured/completed trip and logs the platform commission.
 */
function idibia_credit_driver_for_trip( int $trip_id ): bool {
    global $wpdb;

    $trip = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, driver_id, trip_ref, status, dispatch_status, payment_status, platform_pct, COALESCE(NULLIF(final_fare, 0), NULLIF(fare_estimate, 0), fare, 0) AS fare_amount FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1",
        $trip_id
    ), ARRAY_A );

    $is_completed = $trip && ( $trip['status'] === 'completed' || $trip['dispatch_status'] === 'completed' );
    if ( ! $trip || ! $is_completed || empty( $trip['driver_id'] ) || ! in_array( $trip['payment_status'], [ 'captured', 'approved' ], true ) ) {
        return false;
    }

    $existing = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_wallet_ledger` WHERE entry_type = 'earning' AND reference_id = %d",
        $trip_id
    ) );
    if ( $existing > 0 ) {
        return true;
    }

    $fare        = max( 0, (float) $trip['fare_amount'] );
    $platform_pct = min( 100, max( 0, (float) $trip['platform_pct'] ) );
    $net_amount   = round( $fare * ( 100 - $platform_pct ) / 100, 2 );
    $commission   = round( $fare - $net_amount, 2 );
    $driver_id    = (int) $trip['driver_id'];
    $trip_ref     = $trip['trip_ref'];

    if ( $net_amount <= 0 ) {
        return false;
    }

    $credited = idibia_credit_driver_wallet( $driver_id, $net_amount, 'earning', $trip_id, "Trip {$trip_ref} earnings" );
    if ( ! $credited ) {
        return false;
    }

    $wpdb->query( $wpdb->prepare(
        "UPDATE `{$wpdb->prefix}sd_drivers` SET total_trips = total_trips + 1 WHERE id = %d",
        $driver_id
    ) );

    if ( $commission > 0 ) {
        $wpdb->insert(
            $wpdb->prefix . 'sd_wallet_ledger',
            [
                'driver_id'    => $driver_id,
                'amount'       => $commission,
                'entry_type'   => 'commission',
                'reference_id' => $trip_id,
                'description'  => "Platform commission for trip {$trip_ref}",
                'created_at'   => gmdate( 'Y-m-d H:i:s' ),
            ],
            [ '%d', '%f', '%s', '%d', '%s', '%s' ]
        );
    }

    return true;
}

/**
 * After a trip is completed, check every active campaign to see if this driver
 * just hit the trip target for the first time. If so, credit the bonus and
 * send a congratulations notification.
 */
function idibia_check_campaign_bonuses( int $driver_id, int $trip_id ): void {
    global $wpdb;

    if ( $driver_id <= 0 ) {
        return;
    }

    $now = gmdate( 'Y-m-d H:i:s' );

    $campaigns = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM `{$wpdb->prefix}sd_campaigns`
         WHERE status = 'active'
           AND start_time <= %s
           AND end_time >= %s",
        $now, $now
    ), ARRAY_A ) ?: [];

    foreach ( $campaigns as $campaign ) {
        $campaign_id  = (int) $campaign['id'];
        $target_trips = (int) $campaign['target_trips'];
        $bonus_amount = (float) $campaign['bonus_amount'];

        // Skip if this driver already received a payout for this campaign.
        $already_paid = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_campaign_payouts`
             WHERE campaign_id = %d AND driver_id = %d",
            $campaign_id, $driver_id
        ) );
        if ( $already_paid > 0 ) {
            continue;
        }

        // Check vehicle type eligibility if the campaign restricts to specific types.
        if ( ! empty( $campaign['eligible_vehicle_types'] ) ) {
            $allowed_types = json_decode( $campaign['eligible_vehicle_types'], true );
            if ( is_array( $allowed_types ) && ! empty( $allowed_types ) ) {
                $driver_vehicle = $wpdb->get_var( $wpdb->prepare(
                    "SELECT vehicle_type FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1",
                    $driver_id
                ) );
                if ( ! in_array( $driver_vehicle, $allowed_types, true ) ) {
                    continue;
                }
            }
        }

        // Count trips completed by this driver within the campaign window.
        $trips_completed = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips`
             WHERE driver_id = %d
               AND status = 'completed'
               AND completed_at >= %s
               AND completed_at <= %s",
            $driver_id,
            $campaign['start_time'],
            $campaign['end_time']
        ) );

        if ( $trips_completed < $target_trips ) {
            continue;
        }

        // Driver has just hit the target. Credit the bonus.
        $credited = idibia_credit_driver_wallet(
            $driver_id,
            $bonus_amount,
            'bonus',
            $campaign_id,
            "Campaign bonus: {$campaign['title']}"
        );

        if ( ! $credited ) {
            continue;
        }

        // Record this payout so it cannot be double-paid.
        $wpdb->insert(
            $wpdb->prefix . 'sd_campaign_payouts',
            [
                'campaign_id'        => $campaign_id,
                'driver_id'          => $driver_id,
                'trips_at_completion' => $trips_completed,
                'bonus_paid'         => $bonus_amount,
                'paid_at'            => $now,
            ],
            [ '%d', '%d', '%d', '%f', '%s' ]
        );

        // Notify the driver.
        idibia_notify_user(
            $driver_id,
            'driver',
            'Bonus Earned! 🎉',
            "Congratulations! You've completed the \"{$campaign['title']}\" challenge and earned ₦" . number_format( $bonus_amount, 0 ) . ' bonus. It has been added to your wallet.'
        );
    }
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


function idibia_has_upload( string $field ): bool {
    return isset( $_FILES[ $field ] ) && (int) ( $_FILES[ $field ]['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_NO_FILE;
}

function idibia_validate_evidence_upload( string $field ): ?array {
    if ( ! idibia_has_upload( $field ) ) {
        return null;
    }

    $file = $_FILES[ $field ];
    if ( ! empty( $file['error'] ) ) {
        wp_send_json_error( [ 'message' => 'Evidence upload failed. Please choose the file again.' ] );
    }
    if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
        wp_send_json_error( [ 'message' => 'Evidence upload was not valid.' ] );
    }
    if ( (int) $file['size'] > 10 * 1024 * 1024 ) {
        wp_send_json_error( [ 'message' => 'Evidence must be 10MB or smaller.' ] );
    }

    $allowed_mimes = [ 'image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'video/mp4' ];
    $filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
    if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed_mimes, true ) ) {
        wp_send_json_error( [ 'message' => 'Upload JPG, PNG, WEBP, PDF, or MP4 evidence only.' ] );
    }

    return $file;
}

function idibia_save_evidence_upload( string $field, int $uploader_id, string $uploader_type, string $reference_type, int $reference_id ): ?string {
    global $wpdb;
    $file = idibia_validate_evidence_upload( $field );
    if ( ! $file ) {
        return null;
    }

    $upload = wp_upload_dir();
    if ( ! empty( $upload['error'] ) ) {
        return null;
    }

    $target_dir = trailingslashit( $upload['basedir'] ) . 'idibia-evidence/' . $reference_type . '/' . $reference_id;
    if ( ! wp_mkdir_p( $target_dir ) ) {
        return null;
    }

    $original = sanitize_file_name( wp_unslash( $file['name'] ) );
    $filename = wp_unique_filename( $target_dir, $reference_type . '-' . $reference_id . '-' . $original );
    $target = trailingslashit( $target_dir ) . $filename;
    if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
        return null;
    }

    $relative = 'idibia-evidence/' . $reference_type . '/' . $reference_id . '/' . $filename;
    $wpdb->insert(
        $wpdb->prefix . 'sd_uploaded_evidence',
        [
            'reference_id'   => $reference_id,
            'reference_type' => $reference_type,
            'uploader_id'    => $uploader_id,
            'uploader_type'  => $uploader_type,
            'file_path'      => $relative,
            'created_at'     => gmdate( 'Y-m-d H:i:s' ),
        ],
        [ '%d', '%s', '%d', '%s', '%s', '%s' ]
    );

    return $relative;
}
/**
 * Creates an in-app notification for a customer, driver, or admin actor.
 */
function idibia_notify_user( int $user_id, string $user_type, string $title, string $body ): bool {
    global $wpdb;

    if ( $user_id <= 0 || ! in_array( $user_type, [ 'customer', 'driver', 'admin' ], true ) ) {
        return false;
    }

    $clean_title = wp_strip_all_tags( $title );
    $clean_body  = wp_strip_all_tags( $body );

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'sd_notifications',
        [
            'user_id'   => $user_id,
            'user_type' => $user_type,
            'title'     => $clean_title,
            'body'      => $clean_body,
            'is_read'   => 0,
        ],
        [ '%d', '%s', '%s', '%s', '%d' ]
    );

    if ( $inserted && $user_type !== 'admin' ) {
        $channel = $user_type === 'customer'
            ? idibia_customer_channel( $user_id )
            : idibia_pusher_driver_channel( $user_id );

        idibia_pusher_trigger( [ $channel ], 'notification.new', [
            'title'      => $clean_title,
            'body'       => $clean_body,
            'created_at' => gmdate( 'c' ),
        ] );
    }

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
        'payment_proof_uploaded'  => [ 'Receipt received', 'Your payment receipt for trip #' . $trip['trip_ref'] . ' is being processed.' ],
        'payment_approved'        => [ 'Payment confirmed', 'Your bank transfer for trip #' . $trip['trip_ref'] . ' has been verified and confirmed.' ],
        'payment_rejected'        => [ 'Payment needs attention', 'We could not verify your receipt for trip #' . $trip['trip_ref'] . '. Please upload a clearer copy.' ],
        'payment_refunded'        => [ 'Refund updated', 'A refund update is available for trip #' . $trip['trip_ref'] . '.' ],
        'support_ticket_created'  => [ 'Support ticket opened', 'Support is reviewing your ticket for trip #' . $trip['trip_ref'] . '.' ],
        'support_reply'           => [ 'Support replied', 'Support has replied about trip #' . $trip['trip_ref'] . '.' ],
        'safety_report_created'   => [ 'Safety report received', 'Safety support has been alerted for trip #' . $trip['trip_ref'] . '.' ],
        'rating_submitted'        => [ 'Rating submitted', 'Thanks for rating trip #' . $trip['trip_ref'] . '.' ],
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

    $key     = (string) idibia_get_setting('pusher_key', defined( 'IDIBIA_PUSHER_KEY' ) ? IDIBIA_PUSHER_KEY : '');
    $cluster = (string) idibia_get_setting('pusher_cluster', defined( 'IDIBIA_PUSHER_CLUSTER' ) ? IDIBIA_PUSHER_CLUSTER : '');

    return [
        'enabled'      => idibia_pusher_is_configured(),
        'key'          => $key,
        'cluster'      => $cluster,
        'authEndpoint' => '/pusher-auth-api.php',
    ];
}

/**
 * Returns true once all server-side Pusher credentials have been replaced.
 */
function idibia_pusher_is_configured(): bool {
    require_once __DIR__ . '/idibia-config.php';

    $values = [
        idibia_get_setting('pusher_app_id', defined( 'IDIBIA_PUSHER_APP_ID' ) ? IDIBIA_PUSHER_APP_ID : ''),
        idibia_get_setting('pusher_key', defined( 'IDIBIA_PUSHER_KEY' ) ? IDIBIA_PUSHER_KEY : ''),
        idibia_get_setting('pusher_secret', defined( 'IDIBIA_PUSHER_SECRET' ) ? IDIBIA_PUSHER_SECRET : ''),
        idibia_get_setting('pusher_cluster', defined( 'IDIBIA_PUSHER_CLUSTER' ) ? IDIBIA_PUSHER_CLUSTER : ''),
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

function idibia_customer_channel( int $customer_id ): string {
    return 'private-customer-' . $customer_id;
}

/**
 * Broadcasts an event through Pusher's HTTP API. No-ops while placeholders are configured.
 */
function idibia_pusher_trigger( $channels, string $event_name, array $payload ): bool {
    if ( ! idibia_pusher_is_configured() ) {
        return false;
    }

    require_once __DIR__ . '/idibia-config.php';

    $pusher_app_id = idibia_get_setting('pusher_app_id', defined( 'IDIBIA_PUSHER_APP_ID' ) ? IDIBIA_PUSHER_APP_ID : '');
    $pusher_key    = idibia_get_setting('pusher_key', defined( 'IDIBIA_PUSHER_KEY' ) ? IDIBIA_PUSHER_KEY : '');
    $pusher_secret = idibia_get_setting('pusher_secret', defined( 'IDIBIA_PUSHER_SECRET' ) ? IDIBIA_PUSHER_SECRET : '');
    $pusher_cluster= idibia_get_setting('pusher_cluster', defined( 'IDIBIA_PUSHER_CLUSTER' ) ? IDIBIA_PUSHER_CLUSTER : '');

    $channels = array_values( array_unique( array_filter( (array) $channels ) ) );
    if ( empty( $channels ) ) {
        return false;
    }

    $body = wp_json_encode( [
        'name'     => $event_name,
        'channels' => $channels,
        'data'     => wp_json_encode( $payload ),
    ] );

    $path = '/apps/' . rawurlencode( $pusher_app_id ) . '/events';
    $query = [
        'auth_key'       => $pusher_key,
        'auth_timestamp' => time(),
        'auth_version'   => '1.0',
        'body_md5'       => md5( $body ),
    ];
    ksort( $query );

    $query_string = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
    $signature = hash_hmac( 'sha256', "POST\n{$path}\n{$query_string}", $pusher_secret );
    $url = sprintf(
        'https://api-%s.pusher.com%s?%s&auth_signature=%s',
        rawurlencode( $pusher_cluster ),
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
 * Override WordPress default sender details so emails come from Idibia, not "WordPress".
 * If SMTP settings are configured, the phpmailer_init hook below overrides From as well.
 */
add_filter( 'wp_mail_from_name', function( $name ) {
    $custom = (string) idibia_get_setting( 'smtp_from_name', '' );
    return $custom ?: 'Idibia';
} );
add_filter( 'wp_mail_from', function( $email ) {
    $custom = (string) idibia_get_setting( 'smtp_from_email', '' );
    if ( $custom ) return $custom;
    $host = parse_url( home_url(), PHP_URL_HOST ) ?: 'idibia.com';
    return 'no-reply@' . $host;
} );

/**
 * When SMTP credentials are saved in settings, configure PHPMailer to use them.
 * This makes every wp_mail() call go through the configured SMTP server.
 */
add_action( 'phpmailer_init', function( $phpmailer ) {
    $host = (string) idibia_get_setting( 'smtp_host', '' );
    $user = (string) idibia_get_setting( 'smtp_username', '' );
    $pass = (string) idibia_get_setting( 'smtp_password', '' );

    if ( ! $host || ! $user || ! $pass ) {
        return;
    }

    $port      = (int) idibia_get_setting( 'smtp_port', 587 );
    $from      = (string) idibia_get_setting( 'smtp_from_email', '' );
    $from_name = (string) idibia_get_setting( 'smtp_from_name', 'Idibia' );

    $phpmailer->isSMTP();
    $phpmailer->Host       = $host;
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Port       = $port;
    $phpmailer->Username   = $user;
    $phpmailer->Password   = $pass;
    $phpmailer->SMTPSecure = $port === 465 ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

    if ( $from ) {
        $phpmailer->From     = $from;
        $phpmailer->FromName = $from_name ?: 'Idibia';
    }
} );

/**
 * Send an email and log the result to sd_email_log.
 * Use this instead of wp_mail() everywhere in the app.
 */
function idibia_send_mail( string $to, string $subject, string $body, array $headers = [], array $attachments = [] ): bool {
    global $wpdb;
    $result = wp_mail( $to, $subject, $body, $headers, $attachments );
    $wpdb->insert(
        $wpdb->prefix . 'sd_email_log',
        [
            'to_email'      => $to,
            'subject'       => wp_strip_all_tags( $subject ),
            'status'        => $result ? 'sent' : 'failed',
            'error_message' => $result ? null : 'wp_mail() returned false',
            'sent_at'       => gmdate( 'Y-m-d H:i:s' ),
        ],
        [ '%s', '%s', '%s', '%s', '%s' ]
    );
    return $result;
}

/**
 * Builds and returns a professional HTML OTP verification email.
 *
 * @param string $first_name  Recipient's first name.
 * @param string $otp         The verification code to display.
 * @param string $context     'customer' or 'driver' — adjusts copy.
 * @return array{ subject: string, body: string, headers: string[] }
 */
function idibia_otp_email( string $first_name, string $otp, string $context = 'customer' ): array {
    $site_name = 'Idibia';
    $site_url  = home_url();
    $host      = parse_url( $site_url, PHP_URL_HOST ) ?: 'idibia.com';
    $from      = 'no-reply@' . $host;

    $role_line = $context === 'driver'
        ? 'Thank you for registering as an Idibia driver partner.'
        : 'Thank you for creating your Idibia account.';

    $subject = 'Your Idibia Verification Code: ' . $otp;

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Email Verification</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

          <!-- Header -->
          <tr>
            <td style="background-color:#1a1a2e;padding:32px 40px;text-align:center;">
              <span style="color:#ffffff;font-size:26px;font-weight:700;letter-spacing:1px;">IDIBIA</span>
              <p style="color:#a0aec0;font-size:13px;margin:6px 0 0;">Fast, Reliable Logistics</p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:40px 40px 32px;">
              <h1 style="margin:0 0 12px;font-size:22px;color:#1a1a2e;font-weight:600;">Verify Your Email Address</h1>
              <p style="margin:0 0 8px;font-size:15px;color:#4a5568;line-height:1.6;">Hi {$first_name},</p>
              <p style="margin:0 0 24px;font-size:15px;color:#4a5568;line-height:1.6;">{$role_line} Please use the verification code below to confirm your email address and activate your account.</p>

              <!-- OTP Box -->
              <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px;">
                <tr>
                  <td align="center" style="background-color:#f7fafc;border:2px dashed #e2e8f0;border-radius:8px;padding:24px;">
                    <p style="margin:0 0 6px;font-size:12px;color:#718096;text-transform:uppercase;letter-spacing:1.5px;font-weight:600;">Your Verification Code</p>
                    <span style="font-size:42px;font-weight:700;letter-spacing:10px;color:#1a1a2e;font-family:'Courier New',Courier,monospace;">{$otp}</span>
                    <p style="margin:10px 0 0;font-size:13px;color:#e53e3e;font-weight:500;">⏱ Expires in 30 minutes</p>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 8px;font-size:14px;color:#718096;line-height:1.6;">Enter this code in the app to complete your verification. For security, do not share this code with anyone.</p>
              <p style="margin:0;font-size:13px;color:#a0aec0;line-height:1.6;">If you did not create an Idibia account, you can safely ignore this email.</p>
            </td>
          </tr>

          <!-- Divider -->
          <tr>
            <td style="padding:0 40px;"><hr style="border:none;border-top:1px solid #e2e8f0;"></td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:24px 40px;text-align:center;">
              <p style="margin:0 0 6px;font-size:13px;color:#718096;">This is an automated message from <strong>Idibia</strong>. Please do not reply to this email.</p>
              <p style="margin:0;font-size:12px;color:#a0aec0;">&copy; {$site_name} &middot; <a href="{$site_url}" style="color:#a0aec0;text-decoration:none;">{$host}</a></p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <' . $from . '>',
    ];

    return [ 'subject' => $subject, 'body' => $html, 'headers' => $headers ];
}

/**
 * Retrieves a single setting from the sd_settings table.
 */
function idibia_get_setting( string $key, $default = null ) {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT setting_value FROM `{$wpdb->prefix}sd_settings` WHERE setting_key = %s LIMIT 1", $key ), ARRAY_A );
    if ( ! $row || $row['setting_value'] === '' ) {
        return $default;
    }

    $value = $row['setting_value'];
    // Try to decode JSON, fallback to string
    $decoded = json_decode( $value, true );
    if ( json_last_error() === JSON_ERROR_NONE ) {
        return $decoded;
    }
    return $value;
}

/**
 * Ensures manual payment review columns exist — idempotent, safe to call on every request.
 */
function idibia_ensure_manual_payment_columns(): void {
    idibia_add_manual_payment_columns();
}

/**
 * Returns payment settings for public consumption, specifically manual transfer details.
 */
function idibia_payment_settings(): array {
    return [
        'active_provider'      => idibia_get_setting('payment_active_provider', 'manual_transfer'),
        'bank_name'            => idibia_get_setting('manual_bank_name', ''),
        'account_name'         => idibia_get_setting('manual_account_name', ''),
        'account_number'       => idibia_get_setting('manual_account_number', ''),
        'payment_instructions' => idibia_get_setting('manual_payment_instructions', ''),
        'paystack_enabled'     => idibia_get_setting('paystack_enabled', '0'),
        'flutterwave_enabled'  => idibia_get_setting('flutterwave_enabled', '0'),
    ];
}

/**
 * Returns public payload for a specific trip, used by front-end clients to display payment details.
 */
function idibia_payment_public_payload( int $trip_id ): array {
    global $wpdb;
    $trip = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, payment_status, final_fare, fare_estimate, fare FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1", $trip_id ), ARRAY_A );
    if ( ! $trip ) {
        return [];
    }

    $payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_payments` WHERE trip_id = %d ORDER BY id DESC LIMIT 1", $trip_id ), ARRAY_A );

    $baseurl = '';
    $upload = wp_upload_dir();
    if ( empty( $upload['error'] ) ) {
        $baseurl = trailingslashit( $upload['baseurl'] );
    }

    $amount = (float) ( $trip['final_fare'] ?: $trip['fare_estimate'] ?: $trip['fare'] );

    $status = $payment ? $payment['status'] : 'pending';
    $receipt_url = null;
    if ( in_array( $status, [ 'approved', 'captured' ], true ) ) {
        $token = hash_hmac( 'sha256', $trip_id, wp_salt( 'auth' ) );
        $receipt_url = '/receipt-handler.php?trip_id=' . $trip_id . '&token=' . $token;
    }

    return array_merge( idibia_payment_settings(), [
        'trip_id'      => (int) $trip['id'],
        'amount'       => $amount,
        'status'       => $status,
        'provider'     => $payment ? $payment['provider'] : idibia_get_setting('payment_active_provider', 'manual_transfer'),
        'proof_url'    => $payment && ! empty( $payment['proof_path'] ) ? $baseurl . ltrim( $payment['proof_path'], '/' ) : null,
        'admin_notes'  => $payment && ! empty( $payment['admin_notes'] ) ? $payment['admin_notes'] : null,
        'reviewed_at'  => $payment && ! empty( $payment['reviewed_at'] ) ? $payment['reviewed_at'] : null,
        'receipt_url'  => $receipt_url,
    ] );
}

/**
 * Checks if a specific admin user has a given permission.
 */
function idibia_admin_has_permission( string $permission, int $admin_id = 0 ): bool {
    global $wpdb;

    // If no specific admin_id passed, try to figure it out from cookie
    if ( ! $admin_id && isset($_COOKIE['idibia_admin_auth']) ) {
        $decoded = base64_decode($_COOKIE['idibia_admin_auth']);
        $parts = explode('|', $decoded);
        if ( count($parts) === 2 ) {
            $hash = hash_hmac('sha256', $parts[0], wp_salt('auth'));
            if ( hash_equals($hash, $parts[1]) ) {
                $payload = json_decode($parts[0], true);
                if ( $payload && isset($payload['id']) ) {
                    $admin_id = (int) $payload['id'];
                }
            }
        }
    }

    if ( ! $admin_id ) {
        return false;
    }

    // Get the user and their role
    $admin = $wpdb->get_row( $wpdb->prepare( "
        SELECT u.role_id, r.name as role_name
        FROM `{$wpdb->prefix}sd_admin_users` u
        JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id
        WHERE u.id = %d AND u.status = 'active'
    ", $admin_id ) );

    if ( ! $admin ) {
        return false;
    }

    // Super Admins have unrestricted access
    if ( $admin->role_name === 'Super Admin' ) {
        return true;
    }

    // Check for user-specific override first
    $override = $wpdb->get_var( $wpdb->prepare( "
        SELECT is_granted
        FROM `{$wpdb->prefix}sd_user_permission_overrides`
        WHERE admin_id = %d AND permission = %s
    ", $admin_id, $permission ) );

    if ( $override !== null ) {
        return (bool) $override;
    }

    // Fall back to role default
    $has_role_perm = $wpdb->get_var( $wpdb->prepare( "
        SELECT 1
        FROM `{$wpdb->prefix}sd_role_permissions`
        WHERE role_id = %d AND permission = %s
    ", $admin->role_id, $permission ) );

    return (bool) $has_role_perm;
}

/**
 * Ensures the `saved_addresses` column exists on the customers table.
 *
 * The schema migration adds this column with `ADD COLUMN IF NOT EXISTS`, which
 * is MariaDB-only syntax. On MySQL that statement is a syntax error, so the
 * column is never created even though the DB version option is bumped. This
 * defensive check (mirroring the pattern in driver-profile-api.php) guarantees
 * the column is present before we try to read or write it.
 *
 * @return bool True if the column exists (or was created), false otherwise.
 */
function idibia_ensure_saved_addresses_column(): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'sd_customers';

    $columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table`", 0 );
    if ( ! is_array( $columns ) ) {
        return false;
    }
    if ( in_array( 'saved_addresses', $columns, true ) ) {
        return true;
    }

    $wpdb->query( "ALTER TABLE `$table` ADD COLUMN `saved_addresses` TEXT NULL" );

    $columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table`", 0 );
    return is_array( $columns ) && in_array( 'saved_addresses', $columns, true );
}

/**
 * Returns a trip row if the given actor (customer or driver) is a participant,
 * or null if the trip doesn't exist or doesn't belong to them.
 */
function idibia_get_trip_for_actor( int $trip_id, string $actor_type, int $actor_id ): ?array {
    global $wpdb;
    $col = $actor_type === 'customer' ? 'customer_id' : 'driver_id';
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, customer_id, driver_id, status, dispatch_status, delivery_pin
         FROM `{$wpdb->prefix}sd_trips`
         WHERE id = %d AND `{$col}` = %d LIMIT 1",
        $trip_id, $actor_id
    ), ARRAY_A );
    return $row ?: null;
}

/**
 * Records an admin action in the audit log.
 */
function idibia_admin_audit_log( string $action, string $entity_type, int $entity_id, array $metadata = [] ): void {
    global $wpdb, $admin_id;

    // We try to grab the global $admin_id set by the auth check
    $current_admin_id = isset($admin_id) && $admin_id ? $admin_id : 0;

    if ( ! $current_admin_id && is_user_logged_in() ) {
        // Fallback if legacy WP admin is taking action
        $current_admin_id = 0; // 0 usually means legacy/system in this context
    }

    $wpdb->insert(
        "{$wpdb->prefix}sd_admin_audit_logs",
        [
            'admin_id'    => $current_admin_id,
            'action'      => substr( $action, 0, 80 ),
            'entity_type' => substr( $entity_type, 0, 80 ),
            'entity_id'   => $entity_id,
            'metadata'    => empty( $metadata ) ? null : wp_json_encode( $metadata ),
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 255 ) : null,
            'created_at'  => gmdate( 'Y-m-d H:i:s' )
        ],
        [ '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
    );
}

/**
 * Check whether a phone or email is on the blacklist.
 * Returns true if blacklisted; false if not found or table absent.
 */
function idibia_is_blacklisted( string $email = '', string $phone = '' ): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'sd_blacklist';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
        return false;
    }
    if ( $email ) {
        $hit = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `$table` WHERE identifier_type = 'email' AND identifier_value = %s LIMIT 1",
            $email
        ) );
        if ( $hit ) return true;
    }
    if ( $phone ) {
        $hit = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `$table` WHERE identifier_type = 'phone' AND identifier_value = %s LIMIT 1",
            $phone
        ) );
        if ( $hit ) return true;
    }
    return false;
}

/**
 * Returns a translated string for the given key.
 *
 * Usage: echo __t('online');  // prints "Online" in English, "Akan Layi" in Hausa, etc.
 *
 * How it works:
 * - Looks up the current driver's saved language preference (stored in WP user meta).
 * - Loads the matching file from /lang/ (e.g. /lang/ha.php for Hausa).
 * - Returns the translated text, or falls back to English, or just returns the key itself.
 *
 * @param string      $key  A short code name like 'online', 'good_morning', etc.
 * @param string|null $lang Force a specific language code (e.g. 'ha'). If null, uses the logged-in driver's preference.
 */
function __t( string $key, ?string $lang = null ): string {
    static $loaded = [];

    if ( $lang === null ) {
        $lang = 'en';
        if ( is_user_logged_in() ) {
            $saved = get_user_meta( get_current_user_id(), 'idibia_driver_language', true );
            if ( $saved ) {
                $lang = sanitize_key( $saved );
            }
        }
    } else {
        $lang = sanitize_key( $lang );
    }

    $lang_dir = dirname( __DIR__, 3 ) . '/lang/';

    // Load requested language file (cached after first load)
    if ( ! array_key_exists( $lang, $loaded ) ) {
        $file = $lang_dir . $lang . '.php';
        $loaded[ $lang ] = file_exists( $file ) ? ( require $file ) : [];
    }

    if ( isset( $loaded[ $lang ][ $key ] ) ) {
        return (string) $loaded[ $lang ][ $key ];
    }

    // Fall back to English
    if ( $lang !== 'en' && ! array_key_exists( 'en', $loaded ) ) {
        $en_file = $lang_dir . 'en.php';
        $loaded['en'] = file_exists( $en_file ) ? ( require $en_file ) : [];
    }

    return isset( $loaded['en'][ $key ] ) ? (string) $loaded['en'][ $key ] : $key;
}
