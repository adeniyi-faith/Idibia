<?php
/** Idibia — Admin API: Dashboard Stats */

function idibia_admin_dashboard_stats(): void {
    global $wpdb;
    $today = gmdate( 'Y-m-d' );
    $yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
    $completed_expr = "COALESCE(NULLIF(final_fare, 0), NULLIF(fare_estimate, 0), fare, 0)";
    $trips_today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` WHERE DATE(created_at) = %s", $today ) );
    $trips_yesterday = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` WHERE DATE(created_at) = %s", $yesterday ) );
    $revenue_today = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM($completed_expr * platform_pct / 100), 0) FROM `{$wpdb->prefix}sd_trips` WHERE status = 'completed' AND DATE(COALESCE(completed_at, created_at)) = %s", $today ) );
    $completed_24h = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` WHERE status = 'completed' AND COALESCE(completed_at, created_at) >= UTC_TIMESTAMP() - INTERVAL 1 DAY" );
    $finished_24h = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` WHERE status IN ('completed','cancelled') AND COALESCE(completed_at, created_at) >= UTC_TIMESTAMP() - INTERVAL 1 DAY" );
    $avg_pickup = (float) $wpdb->get_var( "SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, created_at, accepted_at)), 0) FROM `{$wpdb->prefix}sd_trips` WHERE accepted_at IS NOT NULL AND created_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY" );
    $recent = $wpdb->get_results( "SELECT t.id, t.trip_ref, t.category, t.service_category, t.status, t.dispatch_status, t.pickup, t.dropoff, t.pickup_address, t.dropoff_address, $completed_expr AS fare_amount, t.created_at, t.completed_at, c.full_name AS customer_name, d.full_name AS driver_name FROM `{$wpdb->prefix}sd_trips` t LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.customer_id LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = t.driver_id ORDER BY t.created_at DESC LIMIT 5", ARRAY_A ) ?: [];
    $trend_rows = $wpdb->get_results( "SELECT DATE(created_at) AS day, COUNT(*) AS trips FROM `{$wpdb->prefix}sd_trips` WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY day ASC", ARRAY_A ) ?: [];
    $trend_map = [];
    foreach ( $trend_rows as $row ) { $trend_map[ $row['day'] ] = (int) $row['trips']; }
    $activity_trend = [];
    for ( $i = 6; $i >= 0; $i-- ) {
        $d = gmdate( 'Y-m-d', strtotime( "-{$i} day" ) );
        $activity_trend[] = [ 'date' => $d, 'label' => gmdate( 'D', strtotime( $d ) ), 'trips' => $trend_map[ $d ] ?? 0 ];
    }
    wp_send_json_success( [
        'total_customers' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_customers`" ),
        'active_drivers'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE status = 'active'" ),
        'online_drivers'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE is_online = 1 AND status = 'active'" ),
        'kyc_pending'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE kyc_status = 'under_review'" ),
        'trips_today'     => $trips_today,
        'trips_yesterday' => $trips_yesterday,
        'revenue_today'   => $revenue_today,
        'completion_rate' => $finished_24h > 0 ? round( ( $completed_24h / $finished_24h ) * 100, 1 ) : 0,
        'avg_pickup_time' => round( $avg_pickup, 1 ),
        'open_disputes'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_disputes` WHERE status IN ('open','escalated')" ),
        'escalated_disputes' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_disputes` WHERE status = 'escalated'" ),
        'suspended_drivers' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE status = 'suspended'" ),
        'recent_trips'    => $recent,
        'activity_trend'  => $activity_trend,
    ] );
}
