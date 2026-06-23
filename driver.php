<?php ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';




$pusher_config = idibia_pusher_public_config();

$driver_initial_context = [
    'logged_in'   => false,
    'driver_id'   => 0,
    'first_name'  => '',
    'full_name'   => '',
    'kyc_status'  => 'guest',
    'status'      => 'guest',
    'is_approved' => false,
    'is_online'   => false,
];

if ( is_user_logged_in() && get_user_meta( get_current_user_id(), 'idibia_account_type', true ) === 'driver' ) {
    $current_user = wp_get_current_user();
    $driver_id    = idibia_find_or_create_profile_row( $current_user->ID, 'driver' );
    global $wpdb;
    $driver_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $driver_id ), ARRAY_A );

    // Admin-triggered password reset: block app access until the driver sets a new password.
    if ( ! empty( $driver_row['force_password_change'] ) ) {
        header( 'Location: /driver-change-password.php' );
        exit;
    }

    $kyc_status = $driver_row['kyc_status'] ?? ( get_user_meta( $current_user->ID, 'idibia_kyc_status', true ) ?: 'pending' );

    $upload_dir = wp_upload_dir();
    $upload_baseurl = rtrim( $upload_dir['baseurl'], '/' );
    $status     = $driver_row['status'] ?? ( get_user_meta( $current_user->ID, 'idibia_account_status', true ) ?: 'pending' );
    $email_verified = ! empty( $driver_row['email_verified'] );
    // Dashboard Statistics
    $today_start = gmdate('Y-m-d 00:00:00');
    $today_end = gmdate('Y-m-d 23:59:59');
    $today_stats = $wpdb->get_row($wpdb->prepare("SELECT SUM(fare) as today_earnings, COUNT(id) as today_trips FROM `{$wpdb->prefix}sd_trips` WHERE driver_id = %d AND status = 'completed' AND completed_at >= %s AND completed_at <= %s", $driver_id, $today_start, $today_end), ARRAY_A);
    $today_earnings = (float) ($today_stats['today_earnings'] ?? 0);
    $today_trips = (int) ($today_stats['today_trips'] ?? 0);

    $week_start = gmdate('Y-m-d 00:00:00', strtotime('monday this week'));
    $week_end = gmdate('Y-m-d 23:59:59', strtotime('sunday this week'));
    $weekly_stats = $wpdb->get_row($wpdb->prepare("SELECT SUM(fare) as week_earnings, COUNT(id) as week_trips FROM `{$wpdb->prefix}sd_trips` WHERE driver_id = %d AND status = 'completed' AND completed_at >= %s AND completed_at <= %s", $driver_id, $week_start, $week_end), ARRAY_A);
    $week_earnings = (float) ($weekly_stats['week_earnings'] ?? 0);
    $week_trips = (int) ($weekly_stats['week_trips'] ?? 0);

    $daily_breakdown_raw = $wpdb->get_results($wpdb->prepare("SELECT DATE(completed_at) as date, SUM(fare) as daily_earnings FROM `{$wpdb->prefix}sd_trips` WHERE driver_id = %d AND status = 'completed' AND completed_at >= %s AND completed_at <= %s GROUP BY DATE(completed_at)", $driver_id, $week_start, $week_end), ARRAY_A);
    $daily_breakdown = [];
    foreach ($daily_breakdown_raw as $row) {
        $daily_breakdown[$row['date']] = (float) $row['daily_earnings'];
    }

    $avg_rating = (float) $wpdb->get_var($wpdb->prepare("SELECT AVG(rating) FROM `{$wpdb->prefix}sd_ratings` WHERE subject_id = %d AND reviewer_type = 'customer'", $driver_id));
    $avg_rating = $avg_rating > 0 ? round($avg_rating, 1) : 0.0;

    $trips_history = $wpdb->get_results($wpdb->prepare("SELECT id, trip_ref, pickup, dropoff, fare, status, created_at, completed_at FROM `{$wpdb->prefix}sd_trips` WHERE driver_id = %d ORDER BY created_at DESC LIMIT 100", $driver_id), ARRAY_A);

    // Active Campaigns
    $now = gmdate('Y-m-d H:i:s');
    $active_campaigns_raw = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$wpdb->prefix}sd_campaigns` WHERE status = 'active' AND start_time <= %s AND end_time >= %s ORDER BY end_time ASC", $now, $now), ARRAY_A);

    $active_campaigns = [];
    if (!empty($active_campaigns_raw)) {
        // ⚡ Bolt: Fix N+1 query problem by batching trip lookups
        // Fetch all relevant trips in a single query based on the bounding time window
        // of all active campaigns, then count in memory to avoid multiple DB calls.
        $min_time = $active_campaigns_raw[0]['start_time'];
        $max_time = $active_campaigns_raw[0]['end_time'];
        foreach ($active_campaigns_raw as $c) {
            if ($c['start_time'] < $min_time) $min_time = $c['start_time'];
            if ($c['end_time'] > $max_time) $max_time = $c['end_time'];
        }

        $trip_dates = $wpdb->get_col($wpdb->prepare("SELECT completed_at FROM `{$wpdb->prefix}sd_trips` WHERE driver_id = %d AND status = 'completed' AND completed_at >= %s AND completed_at <= %s", $driver_id, $min_time, $max_time));

        foreach ($active_campaigns_raw as $campaign) {
            $completed_trips = 0;
            foreach ($trip_dates as $date) {
                if ($date >= $campaign['start_time'] && $date <= $campaign['end_time']) {
                    $completed_trips++;
                }
            }
            $campaign['progress'] = $completed_trips;
            $active_campaigns[] = $campaign;
        }
    }

    $driver_initial_context = [
        'logged_in'   => true,
        'driver_id'   => $driver_id,
        'dashboard_stats' => [
            'today_earnings'  => $today_earnings,
            'today_trips'     => $today_trips,
            'week_earnings'   => $week_earnings,
            'week_trips'      => $week_trips,
            'daily_breakdown' => $daily_breakdown,
            'avg_rating'      => $avg_rating,
        ],
        'trips_history' => $trips_history,
        'active_campaigns' => $active_campaigns,
        'first_name'  => idibia_first_name_from_user( $current_user ),
        'full_name'   => $driver_row['full_name'] ?? $current_user->display_name,
        'phone'       => $driver_row['phone'] ?? '',
        'kyc_status'  => $kyc_status,
        'status'      => $status,
        'is_approved' => $kyc_status === 'approved' && $status === 'active',
        'is_online'   => ! empty( $driver_row['is_online'] ),
        'email_verified' => $email_verified,
        'vehicle_type' => $driver_row['vehicle_type'] ?? '',
        'rating'      => $driver_row['rating'] ?? '0.00',
        'total_trips' => $driver_row['total_trips'] ?? 0,
        'bank_name'   => $driver_row['bank_name'] ?? '',
        'account_holder_name' => $driver_row['account_holder_name'] ?? '',
        'account_number' => $driver_row['account_number'] ?? '',
        'secondary_bank_details' => $driver_row['secondary_bank_details'] ?? '',
        'emergency_name' => $driver_row['emergency_name'] ?? '',
        'emergency_phone' => $driver_row['emergency_phone'] ?? '',
        'selfie_path' => $driver_row['selfie_path'] ?? '',
        'avatar_path' => $driver_row['avatar_path'] ?? '',
        'kyc_notes'   => $driver_row['kyc_notes'] ?? '',
        'kyc_rejection_history' => $driver_row['kyc_rejection_history'] ?? '',
        'upload_baseurl' => $upload_baseurl ?? '',
    ];
}


if ( ob_get_level() > 0 ) ob_end_flush();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0B1628">
<title>Idibia – Driver App</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<link rel="stylesheet" href="assets/css/driver.css?v=<?php echo time(); ?>">
</head>
<body>
<div id="app">

  <!-- ===== DRIVER WELCOME ONBOARDING ===== -->
  <?php require_once __DIR__ . '/components/driver/onboarding.php'; ?>
  <!-- ===== DRIVER REGISTRATION ===== -->
  <?php require_once __DIR__ . '/components/driver/auth.php'; ?>
  <?php require_once __DIR__ . '/components/driver/main-app.php'; ?>
  <?php require_once __DIR__ . '/components/customer/modal-sos.php'; ?>
  <?php require_once __DIR__ . '/components/driver/modal-faq.php'; ?>
  <?php require_once __DIR__ . '/components/driver/modal-pin.php'; ?>
  <?php require_once __DIR__ . '/components/driver/modal-rating.php'; ?>

  <input class="kyc-file-input" type="file" id="driverKycFileInput" accept="image/jpeg,image/png,application/pdf">

  <!-- TOAST -->
  <div class="toast" id="toast"></div>
</div>

<?php if ( ! empty( $pusher_config['enabled'] ) ) : ?>
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
<?php endif; ?>

<script>
window.idibiaPusherConfig = <?php echo wp_json_encode( $pusher_config ?? null ); ?>;
window.PUSHER_CONFIG = window.idibiaPusherConfig;
window.CURRENT_DRIVER_ID = <?php echo (int) ( $driver_initial_context['driver_id'] ?? 0 ); ?>;
window.driverInitialContext = <?php echo wp_json_encode( $driver_initial_context ?? [] ); ?>;
window.idibiaLogoutUrl = '/';
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="assets/js/driver.js?v=<?php echo time(); ?>"></script>

</body>
</html>