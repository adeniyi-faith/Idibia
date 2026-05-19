<?php ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
if ( ! is_user_logged_in() || get_user_meta( get_current_user_id(), 'idibia_account_type', true ) !== 'customer' ) {
    header( 'Location: index.php' );
    die();
}

$user_id = get_current_user_id();
$current_user = wp_get_current_user();
$customer_id = idibia_find_or_create_profile_row( $user_id, 'customer' );

global $wpdb;
$customer_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_customers` WHERE id = %d LIMIT 1", $customer_id ), ARRAY_A );
$trips_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` WHERE customer_id = %d", $customer_id ) );

$customer_full_name = $customer_row['full_name'] ?? $current_user->display_name;
$customer_email = $customer_row['email'] ?? $current_user->user_email;
$customer_referral_code = $customer_row['referral_code'] ?? '';
$customer_rating = '5.0'; // Default placeholder, no rating system visible yet for customers
$customer_initials = '';
$name_parts = explode(' ', trim($customer_full_name));
if (count($name_parts) >= 2) {
    $customer_initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $customer_initials = strtoupper(substr($customer_full_name, 0, 2));
}
if (!$customer_initials) $customer_initials = 'CU';

$saved_addresses_count = 0;
if (!empty($customer_row['saved_addresses'])) {
    $decoded = json_decode($customer_row['saved_addresses'], true);
    if (is_array($decoded)) $saved_addresses_count = count($decoded);
}

$register_nonce = wp_create_nonce( 'idibia_register' );
$verify_nonce   = wp_create_nonce( 'idibia_verify' );
$profile_nonce  = wp_create_nonce( 'idibia_profile_update' );
$pusher_config  = idibia_pusher_public_config();
if ( ob_get_level() > 0 ) ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=0">
<meta name="theme-color" content="#0B1628">
<title>Idibia — Customer Dashboard</title>
<link rel="icon" href="data:,">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
<div id="app">

<?php require_once __DIR__ . '/components/customer/onboarding.php'; ?>
<?php require_once __DIR__ . '/components/customer/auth.php'; ?>
<?php require_once __DIR__ . '/components/customer/otp.php'; ?>
<?php require_once __DIR__ . '/components/customer/main-app.php'; ?>
<?php require_once __DIR__ . '/components/customer/tracking.php'; ?>
<?php require_once __DIR__ . '/components/customer/modal-receipt.php'; ?>
<?php require_once __DIR__ . '/components/customer/modal-schedule.php'; ?>
<?php require_once __DIR__ . '/components/customer/modal-logout.php'; ?>
<?php require_once __DIR__ . '/components/customer/modal-sos.php'; ?>
<?php require_once __DIR__ . '/components/customer/modal-profile.php'; ?>

</div>

<div class="toast" id="toast"></div>

<?php if ( ! empty( $pusher_config['enabled'] ) ) : ?>
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
<?php endif; ?>

<script>
window.idibiaVerifyNonce = '<?php echo esc_js( $verify_nonce ?? '' ); ?>';
window.idibiaProfileNonce = '<?php echo esc_js( $profile_nonce ?? '' ); ?>';
window.idibiaPusherConfig = <?php echo wp_json_encode( $pusher_config ); ?>;
window.idibiaLogoutUrl = '<?php echo esc_url( wp_logout_url( home_url() ) ); ?>';
</script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>