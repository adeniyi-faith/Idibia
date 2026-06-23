<?php
/** Idibia — Customer Wallet Top-Up */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

$auth_type = 'customer';
require_once __DIR__ . '/auth-helper.php';

$action = sanitize_key( $_POST['action'] ?? $_GET['action'] ?? '' );

global $wpdb;
$customer_id = (int) $GLOBALS['auth_customer_id'];

if ( $action === 'init_topup' ) {
    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
        http_response_code( 405 );
        wp_send_json_error( [ 'message' => 'POST required.' ] );
    }

    $amount   = (float) ( $_POST['amount'] ?? 0 );
    $provider = sanitize_key( $_POST['provider'] ?? '' );

    if ( $amount < 100 ) {
        wp_send_json_error( [ 'message' => 'Minimum top-up amount is ₦100.' ], 400 );
    }

    if ( ! in_array( $provider, [ 'paystack', 'flutterwave' ], true ) ) {
        wp_send_json_error( [ 'message' => 'provider must be paystack or flutterwave.' ], 400 );
    }

    $payment_settings = idibia_payment_settings();
    $active_provider  = $payment_settings['active_provider'] ?? 'manual_transfer';

    if ( $active_provider === 'manual_transfer' ) {
        wp_send_json_error( [ 'message' => 'Online payments are not yet enabled. Please contact support to add funds to your wallet.' ], 503 );
    }

    $customer = $wpdb->get_row( $wpdb->prepare( "SELECT email, full_name FROM `{$wpdb->prefix}sd_customers` WHERE id = %d LIMIT 1", $customer_id ), ARRAY_A );
    if ( ! $customer ) {
        wp_send_json_error( [ 'message' => 'Customer not found.' ] );
    }

    // Build a unique reference so the webhook can identify this top-up
    $reference = 'TOPUP-' . $customer_id . '-' . time();

    if ( $provider === 'paystack' ) {
        $secret = idibia_get_setting( 'paystack_secret_key', '' );
        if ( ! $secret ) {
            wp_send_json_error( [ 'message' => 'Paystack is not configured. Please try again later.' ] );
        }

        $response = wp_remote_post( 'https://api.paystack.co/transaction/initialize', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secret,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'email'     => $customer['email'],
                'amount'    => (int) ( $amount * 100 ),
                'reference' => $reference,
                'metadata'  => [
                    'topup'       => true,
                    'customer_id' => $customer_id,
                ],
                'callback_url' => home_url( '/wallet' ),
            ] ),
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'Could not reach Paystack: ' . $response->get_error_message() ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['status'] ) || empty( $body['data']['authorization_url'] ) ) {
            wp_send_json_error( [ 'message' => $body['message'] ?? 'Paystack initialisation failed.' ] );
        }

        wp_send_json_success( [
            'payment_url' => $body['data']['authorization_url'],
            'reference'   => $reference,
            'provider'    => 'paystack',
        ] );

    } else {
        // Flutterwave
        $secret = idibia_get_setting( 'flutterwave_secret_key', '' );
        if ( ! $secret ) {
            wp_send_json_error( [ 'message' => 'Flutterwave is not configured. Please try again later.' ] );
        }

        $response = wp_remote_post( 'https://api.flutterwave.com/v3/payments', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secret,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'tx_ref'       => $reference,
                'amount'       => $amount,
                'currency'     => 'NGN',
                'redirect_url' => home_url( '/wallet' ),
                'customer'     => [
                    'email' => $customer['email'],
                    'name'  => $customer['full_name'],
                ],
                'meta' => [
                    'topup'       => true,
                    'customer_id' => $customer_id,
                ],
                'customizations' => [
                    'title'       => 'Idibia Wallet Top-Up',
                    'description' => 'Add funds to your Idibia wallet',
                ],
            ] ),
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'Could not reach Flutterwave: ' . $response->get_error_message() ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ( $body['status'] ?? '' ) !== 'success' || empty( $body['data']['link'] ) ) {
            wp_send_json_error( [ 'message' => $body['message'] ?? 'Flutterwave initialisation failed.' ] );
        }

        wp_send_json_success( [
            'payment_url' => $body['data']['link'],
            'reference'   => $reference,
            'provider'    => 'flutterwave',
        ] );
    }

} elseif ( $action === 'request_topup_manual' ) {
    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
        http_response_code( 405 );
        wp_send_json_error( [ 'message' => 'POST required.' ] );
    }

    $amount   = (float) ( $_POST['amount'] ?? 0 );
    $bank_ref = sanitize_text_field( wp_unslash( $_POST['bank_ref'] ?? '' ) );

    if ( $amount < 100 ) {
        wp_send_json_error( [ 'message' => 'Minimum top-up amount is ₦100.' ], 400 );
    }
    if ( empty( $_FILES['proof'] ) || ! empty( $_FILES['proof']['error'] ) ) {
        wp_send_json_error( [ 'message' => 'Please upload your bank transfer receipt.' ] );
    }

    idibia_ensure_wallet_topup_table_customer();

    $proof_path = idibia_save_topup_proof_upload( 'proof', $customer_id );
    if ( ! $proof_path ) {
        wp_send_json_error( [ 'message' => 'Could not save receipt. Please try again.' ] );
    }

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'sd_wallet_topup_requests',
        [
            'customer_id' => $customer_id,
            'amount'      => $amount,
            'bank_ref'    => $bank_ref ?: null,
            'proof_path'  => $proof_path,
            'status'      => 'pending',
            'created_at'  => gmdate( 'Y-m-d H:i:s' ),
        ],
        [ '%d', '%f', '%s', '%s', '%s', '%s' ]
    );
    if ( false === $inserted ) {
        wp_send_json_error( [ 'message' => 'Could not submit request. Please try again.' ] );
    }

    wp_send_json_success( [ 'message' => 'Receipt received! We will review and credit your wallet shortly.' ] );

} elseif ( $action === 'get_topup_requests' ) {
    if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
        http_response_code( 405 );
        wp_send_json_error( [ 'message' => 'GET required.' ] );
    }

    idibia_ensure_wallet_topup_table_customer();

    $requests = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, amount, bank_ref, status, admin_notes, created_at
         FROM `{$wpdb->prefix}sd_wallet_topup_requests`
         WHERE customer_id = %d ORDER BY created_at DESC LIMIT 10",
        $customer_id
    ), ARRAY_A ) ?: [];

    foreach ( $requests as &$row ) {
        $row['id']     = (int) $row['id'];
        $row['amount'] = (float) $row['amount'];
    }
    unset( $row );

    wp_send_json_success( [ 'requests' => $requests ] );

} elseif ( $action === 'get_wallet' ) {
    if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
        http_response_code( 405 );
        wp_send_json_error( [ 'message' => 'GET required.' ] );
    }

    $balance = (float) $wpdb->get_var( $wpdb->prepare( "SELECT wallet_balance FROM `{$wpdb->prefix}sd_customers` WHERE id = %d", $customer_id ) );

    $ledger = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT amount, entry_type, description, created_at
             FROM `{$wpdb->prefix}sd_customer_wallet_ledger`
             WHERE customer_id = %d ORDER BY created_at DESC LIMIT 20",
            $customer_id
        ),
        ARRAY_A
    );

    wp_send_json_success( [
        'balance' => $balance,
        'ledger'  => $ledger ?: [],
    ] );

} else {
    wp_send_json_error( [ 'message' => 'Unknown action.' ], 400 );
}

function idibia_ensure_wallet_topup_table_customer(): void {
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

function idibia_save_topup_proof_upload( string $field, int $customer_id ): ?string {
    $file = $_FILES[ $field ];
    if ( ! is_uploaded_file( $file['tmp_name'] ) ) return null;
    if ( (int) $file['size'] > 8 * 1024 * 1024 ) {
        wp_send_json_error( [ 'message' => 'Receipt must be 8MB or smaller.' ] );
    }
    $allowed_mimes = [ 'image/jpeg', 'image/png', 'application/pdf' ];
    $filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
    if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed_mimes, true ) ) {
        wp_send_json_error( [ 'message' => 'Upload only JPG, PNG, or PDF receipts.' ] );
    }
    $upload = wp_upload_dir();
    if ( ! empty( $upload['error'] ) ) return null;
    $target_dir = trailingslashit( $upload['basedir'] ) . 'idibia-topups/' . $customer_id;
    if ( ! wp_mkdir_p( $target_dir ) ) return null;
    $original = sanitize_file_name( wp_unslash( $file['name'] ) );
    $filename = wp_unique_filename( $target_dir, 'topup-' . time() . '-' . $original );
    $target   = trailingslashit( $target_dir ) . $filename;
    if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) return null;
    return 'idibia-topups/' . $customer_id . '/' . $filename;
}
