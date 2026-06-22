<?php
/** Idibia — Admin API: Refunds & Driver Adjustments */

// -------------------------------------------------------------------------
// ISSUE REFUND (admin)
// -------------------------------------------------------------------------

function idibia_admin_issue_refund(): void {
    global $wpdb, $admin_id;

    $payment_id   = absint( $_POST['payment_id'] ?? 0 );
    $trip_id      = absint( $_POST['trip_id'] ?? 0 );
    $amount       = (float) ( $_POST['refund_amount'] ?? 0 );
    $refund_type  = sanitize_key( $_POST['refund_type'] ?? '' );
    $reason       = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

    if ( ! in_array( $refund_type, [ 'wallet_credit', 'bank_reversal' ], true ) ) {
        wp_send_json_error( [ 'message' => 'refund_type must be wallet_credit or bank_reversal.' ], 400 );
    }
    if ( $amount <= 0 ) {
        wp_send_json_error( [ 'message' => 'refund_amount must be greater than zero.' ], 400 );
    }
    if ( ! $reason ) {
        wp_send_json_error( [ 'message' => 'A reason is required for every refund.' ], 400 );
    }

    // Resolve the payment record
    if ( $payment_id ) {
        $payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_payments` WHERE id = %d LIMIT 1", $payment_id ), ARRAY_A );
    } elseif ( $trip_id ) {
        $payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_payments` WHERE trip_id = %d ORDER BY id DESC LIMIT 1", $trip_id ), ARRAY_A );
    } else {
        wp_send_json_error( [ 'message' => 'Either payment_id or trip_id is required.' ], 400 );
    }

    if ( ! $payment ) {
        wp_send_json_error( [ 'message' => 'Payment not found.' ], 404 );
    }

    $customer_id = (int) $payment['customer_id'];
    $payment_id  = (int) $payment['id'];

    if ( $refund_type === 'wallet_credit' ) {
        idibia_transaction_start();

        // Bump the customer's wallet balance
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE `{$wpdb->prefix}sd_customers` SET wallet_balance = wallet_balance + %f WHERE id = %d",
                $amount,
                $customer_id
            )
        );

        if ( false === $updated ) {
            idibia_transaction_rollback();
            wp_send_json_error( [ 'message' => 'Could not update customer wallet.' ] );
        }

        // Write ledger entry
        $wpdb->insert(
            $wpdb->prefix . 'sd_customer_wallet_ledger',
            [
                'customer_id'  => $customer_id,
                'amount'       => $amount,
                'entry_type'   => 'refund',
                'reference_id' => $payment_id,
                'description'  => $reason,
            ],
            [ '%d', '%f', '%s', '%d', '%s' ]
        );

        // Mark payment as refunded
        $wpdb->update(
            $wpdb->prefix . 'sd_payments',
            [ 'status' => 'refunded', 'admin_notes' => $reason, 'reviewed_by' => $admin_id, 'reviewed_at' => gmdate( 'Y-m-d H:i:s' ) ],
            [ 'id' => $payment_id ],
            [ '%s', '%s', '%d', '%s' ],
            [ '%d' ]
        );

        idibia_transaction_commit();

        // Notify the customer
        idibia_notify_user( $customer_id, 'customer', 'Refund Processed', "A refund of ₦" . number_format( $amount, 2 ) . " has been added to your wallet. Reason: $reason" );

        idibia_admin_audit_log( 'issue_refund', 'payment', $payment_id, [
            'refund_type'  => 'wallet_credit',
            'amount'       => $amount,
            'customer_id'  => $customer_id,
            'reason'       => $reason,
        ] );

        wp_send_json_success( [ 'message' => "Refund of ₦" . number_format( $amount, 2 ) . " added to customer wallet." ] );

    } else {
        // bank_reversal — call the payment provider's refund API
        $provider     = $payment['provider'] ?? 'manual_transfer';
        $provider_ref = $payment['provider_ref'] ?? '';

        if ( ! $provider_ref || $provider === 'manual_transfer' ) {
            wp_send_json_error( [ 'message' => 'Bank reversal is only available for online payments (Paystack / Flutterwave). This payment has no provider reference.' ], 400 );
        }

        $settings = idibia_payment_settings();
        $api_result = null;
        $error_msg  = '';

        if ( $provider === 'paystack' ) {
            $secret = idibia_get_setting( 'paystack_secret_key', '' );
            if ( ! $secret ) {
                wp_send_json_error( [ 'message' => 'Paystack secret key is not configured.' ] );
            }
            $response = wp_remote_post( 'https://api.paystack.co/refund', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( [ 'transaction' => $provider_ref, 'amount' => (int) ( $amount * 100 ) ] ),
                'timeout' => 20,
            ] );
            if ( is_wp_error( $response ) ) {
                $error_msg = $response->get_error_message();
            } else {
                $api_result = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( empty( $api_result['status'] ) ) {
                    $error_msg = $api_result['message'] ?? 'Paystack refund failed.';
                }
            }
        } elseif ( $provider === 'flutterwave' ) {
            $secret = idibia_get_setting( 'flutterwave_secret_key', '' );
            if ( ! $secret ) {
                wp_send_json_error( [ 'message' => 'Flutterwave secret key is not configured.' ] );
            }
            $response = wp_remote_post( "https://api.flutterwave.com/v3/transactions/{$provider_ref}/refund", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( [ 'amount' => $amount ] ),
                'timeout' => 20,
            ] );
            if ( is_wp_error( $response ) ) {
                $error_msg = $response->get_error_message();
            } else {
                $api_result = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( ( $api_result['status'] ?? '' ) !== 'success' ) {
                    $error_msg = $api_result['message'] ?? 'Flutterwave refund failed.';
                }
            }
        } else {
            wp_send_json_error( [ 'message' => "Unknown payment provider: $provider" ], 400 );
        }

        if ( $error_msg ) {
            wp_send_json_error( [ 'message' => "Provider refund error: $error_msg" ] );
        }

        // Mark payment as refunded
        $wpdb->update(
            $wpdb->prefix . 'sd_payments',
            [ 'status' => 'refunded', 'admin_notes' => $reason, 'reviewed_by' => $admin_id, 'reviewed_at' => gmdate( 'Y-m-d H:i:s' ) ],
            [ 'id' => $payment_id ],
            [ '%s', '%s', '%d', '%s' ],
            [ '%d' ]
        );

        idibia_notify_user( $customer_id, 'customer', 'Refund Initiated', "A bank refund of ₦" . number_format( $amount, 2 ) . " has been initiated. Reason: $reason. Please allow 3–7 business days." );

        idibia_admin_audit_log( 'issue_refund', 'payment', $payment_id, [
            'refund_type'  => 'bank_reversal',
            'provider'     => $provider,
            'provider_ref' => $provider_ref,
            'amount'       => $amount,
            'customer_id'  => $customer_id,
            'reason'       => $reason,
        ] );

        wp_send_json_success( [ 'message' => "Bank reversal of ₦" . number_format( $amount, 2 ) . " initiated via $provider." ] );
    }
}

// -------------------------------------------------------------------------
// DRIVER PENALTY / BONUS (admin)
// -------------------------------------------------------------------------

function idibia_admin_issue_driver_adjustment(): void {
    global $wpdb, $admin_id;

    $driver_id       = absint( $_POST['driver_id'] ?? 0 );
    $amount          = (float) ( $_POST['amount'] ?? 0 );
    $adjustment_type = sanitize_key( $_POST['adjustment_type'] ?? '' );
    $reason          = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

    if ( ! $driver_id ) {
        wp_send_json_error( [ 'message' => 'driver_id required.' ], 400 );
    }
    if ( $amount <= 0 ) {
        wp_send_json_error( [ 'message' => 'amount must be greater than zero.' ], 400 );
    }
    if ( ! in_array( $adjustment_type, [ 'penalty', 'bonus' ], true ) ) {
        wp_send_json_error( [ 'message' => 'adjustment_type must be penalty or bonus.' ], 400 );
    }
    if ( ! $reason ) {
        wp_send_json_error( [ 'message' => 'A reason is required.' ], 400 );
    }

    $driver = $wpdb->get_row( $wpdb->prepare( "SELECT id, full_name, wallet_balance FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $driver_id ), ARRAY_A );
    if ( ! $driver ) {
        wp_send_json_error( [ 'message' => 'Driver not found.' ], 404 );
    }

    if ( $adjustment_type === 'penalty' ) {
        $current_balance = (float) $driver['wallet_balance'];
        if ( $amount > $current_balance ) {
            wp_send_json_error( [ 'message' => "Cannot deduct ₦" . number_format( $amount, 2 ) . " — driver wallet only has ₦" . number_format( $current_balance, 2 ) . "." ] );
        }
    }

    idibia_transaction_start();

    if ( $adjustment_type === 'bonus' ) {
        $balance_sql = "wallet_balance = wallet_balance + %f";
    } else {
        $balance_sql = "wallet_balance = GREATEST(0, wallet_balance - %f)";
    }

    $updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE `{$wpdb->prefix}sd_drivers` SET $balance_sql WHERE id = %d",
            $amount,
            $driver_id
        )
    );

    if ( false === $updated ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Could not update driver wallet.' ] );
    }

    $wpdb->insert(
        $wpdb->prefix . 'sd_wallet_ledger',
        [
            'driver_id'    => $driver_id,
            'amount'       => $adjustment_type === 'penalty' ? -$amount : $amount,
            'entry_type'   => $adjustment_type,
            'reference_id' => 0,
            'description'  => $reason,
        ],
        [ '%d', '%f', '%s', '%d', '%s' ]
    );

    idibia_transaction_commit();

    $label = $adjustment_type === 'bonus' ? 'Bonus Received' : 'Penalty Applied';
    $msg   = $adjustment_type === 'bonus'
        ? "A bonus of ₦" . number_format( $amount, 2 ) . " has been added to your wallet. Reason: $reason"
        : "A penalty of ₦" . number_format( $amount, 2 ) . " has been deducted from your wallet. Reason: $reason";

    idibia_notify_user( $driver_id, 'driver', $label, $msg );

    idibia_admin_audit_log( 'issue_driver_adjustment', 'driver', $driver_id, [
        'adjustment_type' => $adjustment_type,
        'amount'          => $amount,
        'reason'          => $reason,
    ] );

    $new_balance = (float) $wpdb->get_var( $wpdb->prepare( "SELECT wallet_balance FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d", $driver_id ) );

    wp_send_json_success( [
        'message'     => ucfirst( $adjustment_type ) . " of ₦" . number_format( $amount, 2 ) . " applied.",
        'new_balance' => $new_balance,
    ] );
}
