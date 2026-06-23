<?php ob_start();
ini_set( 'display_errors', '0' );
/** Idibia — Admin API Router */

require_once __DIR__ . '/../wp-auth-config.php';
require_once __DIR__ . '/../wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

// Load API modules
require_once __DIR__ . '/api/utils.php';
require_once __DIR__ . '/api/dashboard.php';
require_once __DIR__ . '/api/drivers.php';
require_once __DIR__ . '/api/customers.php';
require_once __DIR__ . '/api/trips.php';
require_once __DIR__ . '/api/ops.php';
require_once __DIR__ . '/api/disputes.php';
require_once __DIR__ . '/api/payouts.php';
require_once __DIR__ . '/api/revenue.php';
require_once __DIR__ . '/api/settings.php';
require_once __DIR__ . '/api/admin-users.php';
require_once __DIR__ . '/api/support.php';
require_once __DIR__ . '/api/ratings.php';
require_once __DIR__ . '/api/refunds.php';
require_once __DIR__ . '/api/notifications.php';
require_once __DIR__ . '/api/bulk.php';
require_once __DIR__ . '/api/system-health.php';

// Check auth first
$admin_id = 0;
if ( isset($_COOKIE['idibia_admin_auth']) ) {
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

// They must have an admin_id from the secure cookie
if ( ! $admin_id ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Unauthorized access.' ] );
}

global $wpdb;
$action = sanitize_key( $_POST['action'] ?? $_GET['action'] ?? '' );

try {
    switch ( $action ) {
        case 'list_admin_users':
            idibia_require_method( 'GET' );
            idibia_admin_list_users();
            break;
        case 'get_my_permissions':
            idibia_require_method( 'GET' );
            idibia_admin_get_my_permissions();
            break;

        case 'create_admin_user':
            idibia_require_method( 'POST' );
            idibia_admin_create_user();
            break;

        case 'update_admin_user':
            idibia_require_method( 'POST' );
            idibia_admin_update_user();
            break;

        case 'suspend_admin_user':
            idibia_require_method( 'POST' );
            idibia_admin_suspend_user();
            break;

        case 'get_roles':
            idibia_require_method( 'GET' );
            idibia_admin_get_roles();
            break;

        case 'get_dashboard_stats':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_live_map')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_dashboard_stats();
            break;

        case 'get_drivers':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_drivers')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_paginated_drivers();
            break;

        case 'get_driver':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_drivers')){ wp_send_json_error(['message'=>'Denied.'],403); }
            $driver_id = absint( $_GET['driver_id'] ?? 0 );
            $driver = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $driver_id ), ARRAY_A );
            $driver ? wp_send_json_success( [ 'driver' => idibia_admin_prepare_driver( $driver ) ] ) : wp_send_json_error( [ 'message' => 'Driver not found.' ] );
            break;

        case 'kyc_action':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'approve_reject_kyc' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_kyc_action();
            break;

        case 'suspend_driver':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'suspend_reinstate_driver' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_suspend_driver();
            break;

        case 'reset_driver_password':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'suspend_reinstate_driver' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_reset_driver_password();
            break;

        case 'get_customers':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_customers')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_paginated_customers();
            break;

        case 'get_customer':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_customers')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_get_customer_detail();
            break;

        case 'suspend_customer':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('suspend_customer')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_suspend_customer();
            break;

        case 'reinstate_customer':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('suspend_customer')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_reinstate_customer();
            break;

        case 'get_trips':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_trips')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_paginated_trips();
            break;

        case 'get_live_ops':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_live_map' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_live_ops();
            break;

        case 'get_disputes':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_disputes')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_paginated_disputes();
            break;

        case 'get_payouts':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('execute_payouts') && !idibia_admin_has_permission('view_export_revenue')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_paginated_payouts();
            break;

        case 'process_payout':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('execute_payouts')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_process_payout();
            break;

        case 'resolve_dispute':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('assign_resolve_disputes')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_resolve_dispute();
            break;

        case 'get_support_tickets':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_disputes')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_get_support_tickets();
            break;

        case 'get_ticket_messages':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_disputes')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_get_ticket_messages();
            break;

        case 'admin_reply_ticket':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('view_disputes')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_reply_ticket();
            break;

        case 'assign_ticket':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('assign_resolve_disputes')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_assign_ticket();
            break;

        case 'update_ticket_status':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('assign_resolve_disputes')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_update_ticket_status();
            break;

        case 'set_ticket_priority':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('assign_resolve_disputes')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_set_ticket_priority();
            break;

        case 'get_trip_pod':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_trips')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_get_trip_pod();
            break;

        case 'issue_refund':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'issue_refunds' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_issue_refund();
            break;

        case 'issue_driver_adjustment':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'execute_payouts' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_issue_driver_adjustment();
            break;

        case 'admin_credit_customer_wallet':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'issue_refunds' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_credit_customer_wallet();
            break;

        case 'get_audit_log':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_admin_users' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_get_audit_log();
            break;

        case 'get_settings_history':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_settings' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_get_settings_history();
            break;

        case 'get_settings':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_settings')){ wp_send_json_error(['message'=>'Denied.'],403); }
            $rows = $wpdb->get_results( "SELECT setting_key, setting_value FROM `{$wpdb->prefix}sd_settings`", ARRAY_A );
            $settings = [];
            foreach ( $rows ?: [] as $row ) {
                if ( in_array( $row['setting_key'], ['paystack_secret_key', 'flutterwave_secret_key', 'pusher_secret'] ) ) {
                    $settings[ $row['setting_key'] ] = !empty($row['setting_value']) ? '********' : '';
                } else {
                    $settings[ $row['setting_key'] ] = $row['setting_value'];
                }
            }
            wp_send_json_success( [ 'settings' => $settings, 'payment' => idibia_payment_settings() ] );
            break;

        case 'export_tax_summary':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_export_revenue' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_export_tax_summary();
            break;

        case 'export_driver_wht':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_export_revenue' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_export_driver_wht();
            break;

        case 'export_vat_schedule':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_export_revenue' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_export_vat_schedule();
            break;

        case 'get_manual_payments':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_payments')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_manual_payments();
            break;

        case 'get_reconciliation_data':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_export_revenue' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_reconciliation_data();
            break;

        case 'get_revenue_analytics':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_export_revenue' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_revenue_analytics();
            break;

        case 'get_pnl_summary':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_export_revenue' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_get_pnl_summary();
            break;

        case 'get_payment':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_payments' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            $payment_id = absint( $_GET['payment_id'] ?? 0 );
            $payment = $wpdb->get_row( $wpdb->prepare( "SELECT p.*, t.trip_ref, t.status AS trip_status, c.full_name AS customer_name FROM `{$wpdb->prefix}sd_payments` p LEFT JOIN `{$wpdb->prefix}sd_trips` t ON t.id = p.trip_id LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = p.customer_id WHERE p.id = %d LIMIT 1", $payment_id ), ARRAY_A );
            if ( $payment ) {
                if ( in_array( $payment['status'], [ 'approved', 'captured' ], true ) ) {
                    $token = hash_hmac( 'sha256', $payment['trip_id'], wp_salt( 'auth' ) );
                    $payment['receipt_url'] = '/receipt-handler.php?trip_id=' . $payment['trip_id'] . '&token=' . $token;
                } else {
                    $payment['receipt_url'] = null;
                }
                wp_send_json_success( [ 'payment' => $payment ] );
            } else {
                wp_send_json_error( [ 'message' => 'Payment not found.' ] );
            }
            break;

        case 'review_manual_payment':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('approve_reject_payment')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_review_manual_payment();
            break;

        case 'admin_reassign_trip':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'force_redispatch' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_reassign_trip();
            break;

        case 'admin_force_cancel_trip':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'force_cancel_trip' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_force_cancel_trip();
            break;


        case 'save_settings':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('edit_pricing_commission')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_save_settings();
            break;

        case 'get_kyc_policy':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'config_kyc_policy' ) && ! idibia_admin_has_permission( 'view_settings' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_get_kyc_policy();
            break;

        case 'save_kyc_policy':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'config_kyc_policy' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_save_kyc_policy();
            break;

        case 'get_blacklist':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'suspend_reinstate_driver' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_get_blacklist();
            break;

        case 'add_to_blacklist':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'suspend_reinstate_driver' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_add_to_blacklist();
            break;

        case 'remove_from_blacklist':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'suspend_reinstate_driver' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_remove_from_blacklist();
            break;

        case 'get_zones':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_settings')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_get_zones();
            break;

        case 'create_zone':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('edit_pricing_commission')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_create_zone();
            break;

        case 'update_zone':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('edit_pricing_commission')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_update_zone();
            break;

        case 'delete_zone':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('edit_pricing_commission')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_delete_zone();
            break;

        case 'get_ratings':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_drivers' ) && ! idibia_admin_has_permission( 'view_customers' ) ) {
                wp_send_json_error( [ 'message' => 'Denied.' ], 403 );
            }
            idibia_admin_get_ratings();
            break;

        case 'delete_rating':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'suspend_reinstate_driver' ) ) {
                wp_send_json_error( [ 'message' => 'Denied.' ], 403 );
            }
            idibia_admin_delete_rating();
            break;

        case 'flag_rating':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'view_drivers' ) && ! idibia_admin_has_permission( 'view_customers' ) ) {
                wp_send_json_error( [ 'message' => 'Denied.' ], 403 );
            }
            idibia_admin_flag_rating();
            break;

        case 'get_subject_ratings':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_drivers' ) && ! idibia_admin_has_permission( 'view_customers' ) ) {
                wp_send_json_error( [ 'message' => 'Denied.' ], 403 );
            }
            idibia_admin_get_subject_ratings();
            break;

        case 'get_campaigns':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_settings' ) ) {
                wp_send_json_error( [ 'message' => 'Denied.' ], 403 );
            }
            idibia_admin_get_campaigns();
            break;

        case 'get_campaign':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_settings' ) ) {
                wp_send_json_error( [ 'message' => 'Denied.' ], 403 );
            }
            idibia_admin_get_campaign();
            break;

        case 'create_campaign':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'edit_pricing_commission' ) ) {
                wp_send_json_error( [ 'message' => 'Denied.' ], 403 );
            }
            idibia_admin_create_campaign();
            break;

        case 'update_campaign':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'edit_pricing_commission' ) ) {
                wp_send_json_error( [ 'message' => 'Denied.' ], 403 );
            }
            idibia_admin_update_campaign();
            break;

        case 'deactivate_campaign':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'edit_pricing_commission' ) ) {
                wp_send_json_error( [ 'message' => 'Denied.' ], 403 );
            }
            idibia_admin_deactivate_campaign();
            break;

        case 'get_campaign_leaderboard':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_settings' ) ) {
                wp_send_json_error( [ 'message' => 'Denied.' ], 403 );
            }
            idibia_admin_get_campaign_leaderboard();
            break;

        case 'send_broadcast':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'manage_settings' ) ) {
                wp_send_json_error( [ 'message' => 'Denied.' ], 403 );
            }
            idibia_admin_send_broadcast();
            break;

        case 'get_broadcasts':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_settings' ) ) {
                wp_send_json_error( [ 'message' => 'Denied.' ], 403 );
            }
            idibia_admin_get_broadcasts();
            break;

        case 'test_smtp_email':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'manage_settings' ) ) {
                wp_send_json_error( [ 'message' => 'Denied.' ], 403 );
            }
            idibia_admin_test_smtp_email();
            break;

        case 'correct_trip_status':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'correct_trip_status' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_correct_trip_status();
            break;

        case 'get_live_alerts':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_live_map' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_get_live_alerts();
            break;

        case 'bulk_action':
            idibia_require_method( 'POST' );
            idibia_admin_bulk_action();
            break;

        case 'get_demand_supply_heatmap':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_live_map' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_get_demand_supply_heatmap();
            break;

        case 'get_system_health':
            idibia_require_method( 'GET' );
            if ( ! idibia_admin_has_permission( 'view_settings' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_get_system_health();
            break;

        case 'force_drivers_offline':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'view_settings' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_force_drivers_offline();
            break;

        case 'create_driver_account':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'create_driver_accounts' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_create_driver_account();
            break;

        case 'edit_driver_details':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'edit_driver_details' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_edit_driver_details();
            break;

        case 'create_customer_account':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'create_customer_accounts' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_create_customer_account();
            break;

        case 'edit_customer_details':
            idibia_require_method( 'POST' );
            if ( ! idibia_admin_has_permission( 'edit_customer_details' ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
            idibia_admin_edit_customer_details();
            break;

        default:
            wp_send_json_error( [ 'message' => 'Unknown action.' ] );
    }
} catch ( Throwable $e ) {
    http_response_code( 500 );
    wp_send_json_error( [ 'message' => 'Server error.' ] );
}
