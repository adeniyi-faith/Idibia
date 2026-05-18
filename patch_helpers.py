with open('wp/wp-content/mu-plugins/idibia-helpers.php', 'r') as f:
    content = f.read()

helpers = """
/**
 * Retrieves a single setting from the sd_settings table.
 */
function idibia_get_setting( string $key, $default = null ) {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT setting_value FROM `{$wpdb->prefix}sd_settings` WHERE setting_key = %s LIMIT 1", $key ), ARRAY_A );
    if ( ! $row ) {
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

    return array_merge( idibia_payment_settings(), [
        'trip_id'      => (int) $trip['id'],
        'amount'       => $amount,
        'status'       => $payment ? $payment['status'] : 'pending',
        'provider'     => $payment ? $payment['provider'] : idibia_get_setting('payment_active_provider', 'manual_transfer'),
        'proof_url'    => $payment && ! empty( $payment['proof_path'] ) ? $baseurl . ltrim( $payment['proof_path'], '/' ) : null,
        'admin_notes'  => $payment && ! empty( $payment['admin_notes'] ) ? $payment['admin_notes'] : null,
        'reviewed_at'  => $payment && ! empty( $payment['reviewed_at'] ) ? $payment['reviewed_at'] : null,
    ] );
}
"""

content = content + helpers

with open('wp/wp-content/mu-plugins/idibia-helpers.php', 'w') as f:
    f.write(content)
