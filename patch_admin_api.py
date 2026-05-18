with open('admin/api.php', 'r') as f:
    content = f.read()

# 1. Update get_settings to mask secrets
old_get_settings = """        case 'get_settings':
            idibia_require_method( 'GET' );
            $rows = $wpdb->get_results( "SELECT setting_key, setting_value FROM `{$wpdb->prefix}sd_settings`", ARRAY_A );
            $settings = [];
            foreach ( $rows as $row ) $settings[ $row['setting_key'] ] = $row['setting_value'];
            wp_send_json_success( [ 'settings' => $settings, 'payment' => idibia_payment_settings() ] );"""

new_get_settings = """        case 'get_settings':
            idibia_require_method( 'GET' );
            $rows = $wpdb->get_results( "SELECT setting_key, setting_value FROM `{$wpdb->prefix}sd_settings`", ARRAY_A );
            $settings = [];
            foreach ( $rows as $row ) {
                if ( in_array( $row['setting_key'], ['paystack_secret_key', 'flutterwave_secret_key'] ) ) {
                    $settings[ $row['setting_key'] ] = !empty($row['setting_value']) ? '********' : '';
                } else {
                    $settings[ $row['setting_key'] ] = $row['setting_value'];
                }
            }
            wp_send_json_success( [ 'settings' => $settings, 'payment' => idibia_payment_settings() ] );"""

content = content.replace(old_get_settings, new_get_settings)

# 2. Update save_settings to validate pricing and prevent overwriting secrets with masked string
old_save_settings = """function idibia_admin_save_settings(): void {
    global $wpdb;
    $raw = file_get_contents( 'php://input' );
    $settings = json_decode( $raw, true );
    if ( ! is_array( $settings ) ) {
        $settings = $_POST['settings'] ?? $_POST;
    }
    unset( $settings['action'] );

    if ( ! empty( $settings ) ) {
        $values       = [];
        $placeholders = [];
        foreach ( $settings as $key => $value ) {
            $placeholders[] = '(%s, %s)';
            $values[]       = sanitize_key( $key );
            $values[]       = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : wp_json_encode( $value );
        }
        $query = "REPLACE INTO `{$wpdb->prefix}sd_settings` (`setting_key`, `setting_value`) VALUES " . implode( ', ', $placeholders );
        $wpdb->query( $wpdb->prepare( $query, $values ) );
    }

    wp_send_json_success( [ 'message' => 'Settings saved.' ] );
}"""

new_save_settings = """function idibia_admin_save_settings(): void {
    global $wpdb;
    $raw = file_get_contents( 'php://input' );
    $settings = json_decode( $raw, true );
    if ( ! is_array( $settings ) ) {
        $settings = $_POST['settings'] ?? $_POST;
    }
    unset( $settings['action'] );

    if ( ! empty( $settings ) ) {
        $values       = [];
        $placeholders = [];
        foreach ( $settings as $key => $value ) {
            $sanitized_key = sanitize_key( $key );
            $sanitized_value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : wp_json_encode( $value );

            // Field-level validation
            if ( in_array( $sanitized_key, ['platform_commission_pct', 'surge_multiplier_cap', 'min_fare', 'max_delivery_radius_km'] ) ) {
                if ( ! is_numeric( $sanitized_value ) || (float) $sanitized_value < 0 ) {
                    wp_send_json_error( [ 'message' => "Invalid value for {$sanitized_key}. Must be a positive number." ] );
                }
            }

            // Do not overwrite secrets if they are blank or masked
            if ( in_array( $sanitized_key, ['paystack_secret_key', 'flutterwave_secret_key'] ) ) {
                if ( $sanitized_value === '' || $sanitized_value === '********' ) {
                    continue; // Skip updating this secret
                }
            }

            $placeholders[] = '(%s, %s)';
            $values[]       = $sanitized_key;
            $values[]       = $sanitized_value;
        }

        if ( ! empty( $placeholders ) ) {
            $query = "REPLACE INTO `{$wpdb->prefix}sd_settings` (`setting_key`, `setting_value`) VALUES " . implode( ', ', $placeholders );
            $wpdb->query( $wpdb->prepare( $query, $values ) );

            // Log the action
            idibia_admin_audit_log( 'save_settings', 'settings', 0, array_keys($settings) );
        }
    }

    wp_send_json_success( [ 'message' => 'Settings saved.' ] );
}"""

content = content.replace(old_save_settings, new_save_settings)

with open('admin/api.php', 'w') as f:
    f.write(content)
