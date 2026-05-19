<?php ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
if ( ! is_user_logged_in() || get_user_meta( get_current_user_id(), 'idibia_account_type', true ) !== 'customer' ) {
    header( 'Location: index.php' );
    die();
}
$register_nonce = wp_create_nonce( 'idibia_register' );
$verify_nonce   = wp_create_nonce( 'idibia_verify' );
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

</div>

<div class="toast" id="toast"></div>

<?php if ( ! empty( $pusher_config['enabled'] ) ) : ?>
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
<?php endif; ?>

<script>
window.idibiaVerifyNonce = '<?php echo esc_js( $verify_nonce ?? '' ); ?>';
window.idibiaPusherConfig = <?php echo wp_json_encode( $pusher_config ); ?>;
window.idibiaLogoutUrl = '<?php echo esc_url( wp_logout_url( home_url() ) ); ?>';
</script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>