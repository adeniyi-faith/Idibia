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

    $kyc_status = $driver_row['kyc_status'] ?? ( get_user_meta( $current_user->ID, 'idibia_kyc_status', true ) ?: 'pending' );
    $status     = $driver_row['status'] ?? ( get_user_meta( $current_user->ID, 'idibia_account_status', true ) ?: 'pending' );
    $email_verified = ! empty( $driver_row['email_verified'] );
    $driver_nonces = [
        'toggle_online' => wp_create_nonce( 'idibia_toggle_online' ),
        'driver_action' => wp_create_nonce( 'idibia_driver_action' ),
        'support_action' => wp_create_nonce( 'idibia_support_action' ),
    ];
    if ( $email_verified ) {
        $driver_nonces['driver_kyc'] = wp_create_nonce( 'idibia_driver_kyc' );
    }

    $driver_initial_context = [
        'logged_in'   => true,
        'driver_id'   => $driver_id,
        'first_name'  => idibia_first_name_from_user( $current_user ),
        'full_name'   => $driver_row['full_name'] ?? $current_user->display_name,
        'kyc_status'  => $kyc_status,
        'status'      => $status,
        'is_approved' => $kyc_status === 'approved' && $status === 'active',
        'is_online'   => ! empty( $driver_row['is_online'] ),
        'email_verified' => $email_verified,
        'nonces'      => $driver_nonces,
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
<link rel="stylesheet" href="assets/css/driver.css">
</head>
<body>
<div id="app">

  <!-- ===== DRIVER ONBOARDING ===== -->
  <?php require_once __DIR__ . '/components/driver/auth.php'; ?>
  <?php require_once __DIR__ . '/components/driver/main-app.php'; ?>

  <input class="kyc-file-input" type="file" id="driverKycFileInput" accept="image/jpeg,image/png,application/pdf">

  <!-- TOAST -->
  <div class="toast" id="toast"></div>
</div>

<?php if ( ! empty( $pusher_config['enabled'] ) ) : ?>
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
<?php endif; ?>

<script>
window.idibiaPusherConfig = <?php echo wp_json_encode( $pusher_config ?? null ); ?>;
window.driverInitialContext = <?php echo wp_json_encode( $driver_initial_context ?? [] ); ?>;
window.idibiaLogoutUrl = '<?php echo esc_url( wp_logout_url( home_url() ) ); ?>';
</script>
<script src="assets/js/driver.js"></script>

</body>
</html>