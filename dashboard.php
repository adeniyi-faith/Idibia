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
$customer_phone = $customer_row['phone'] ?? '';
$customer_referral_code = $customer_row['referral_code'] ?? '';
$customer_wallet_balance = number_format( (float) ( $customer_row['wallet_balance'] ?? 0 ), 2 );
$customer_avatar_path = $customer_row['avatar_path'] ?? '';
$customer_rating = '5.0'; // Default placeholder, no rating system visible yet for customers
$customer_initials = '';
$name_parts = explode(' ', trim($customer_full_name));
if (count($name_parts) >= 2) {
    $customer_initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $customer_initials = strtoupper(substr($customer_full_name, 0, 2));
}
if (!$customer_initials) $customer_initials = 'CU';

$upload_dir = wp_upload_dir();
$upload_baseurl = rtrim( $upload_dir['baseurl'], '/' );

$saved_addresses_count = 0;
if (!empty($customer_row['saved_addresses'])) {
    $decoded = json_decode($customer_row['saved_addresses'], true);
    if (is_array($decoded)) $saved_addresses_count = count($decoded);
}

$pusher_config  = idibia_pusher_public_config();

$company_bank_name = idibia_get_setting('company_bank_name', 'Not Available');
$company_account_name = idibia_get_setting('company_account_name', 'Not Available');
$company_account_number = idibia_get_setting('company_account_number', 'Not Available');

$legal_terms = idibia_get_setting('legal_terms', 'Terms and conditions will be provided here.');
$legal_privacy = idibia_get_setting('legal_privacy', 'Privacy policy will be provided here.');
$legal_location = idibia_get_setting('legal_location', 'Location data policy will be provided here.');
$legal_license = idibia_get_setting('legal_license', 'Software license will be provided here.');
$legal_copyright = idibia_get_setting('legal_copyright', 'Copyright notice will be provided here.');

global $wpdb;
$customer_id = idibia_find_or_create_profile_row( get_current_user_id(), 'customer' );
$customer    = $wpdb->get_row( $wpdb->prepare( "SELECT rating FROM `{$wpdb->prefix}sd_customers` WHERE id = %d LIMIT 1", $customer_id ), ARRAY_A );
$customer_rating = !empty($customer['rating']) && (float)$customer['rating'] > 0 ? number_format((float)$customer['rating'], 1) : '5.0';

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
<link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo @filemtime( __DIR__ . '/assets/css/dashboard.css' ) ?: '1'; ?>">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
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
<?php require_once __DIR__ . '/components/customer/modal-preferences.php'; ?>
<?php require_once __DIR__ . '/components/customer/modal-support.php'; ?>
<?php require_once __DIR__ . '/components/customer/modal-faq.php'; ?>
<?php require_once __DIR__ . '/components/customer/modal-payment.php'; ?>
<?php require_once __DIR__ . '/components/customer/modal-legal.php'; ?>
<?php require_once __DIR__ . '/components/customer/modal-logout.php'; ?>
<?php require_once __DIR__ . '/components/customer/modal-sos.php'; ?>
<?php require_once __DIR__ . '/components/customer/modal-profile.php'; ?>

</div>

<div class="toast" id="toast"></div>

<?php if ( ! empty( $pusher_config['enabled'] ) ) : ?>
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
<?php endif; ?>

<script>
window.idibiaPusherConfig = <?php echo wp_json_encode( $pusher_config ); ?>;
window.idibiaLogoutUrl = '/';
window.idibiaCustomerRating = '<?php echo esc_js( $customer_rating ); ?>';
window.idibiaCustomerAvatar = '<?php echo esc_js( $customer_avatar_path ); ?>';
window.idibiaUploadBaseUrl = '<?php echo esc_url( $upload_baseurl ); ?>';
window.idibiaLegalContents = {
  legal_terms: <?php echo wp_json_encode( $legal_terms ); ?>,
  legal_privacy: <?php echo wp_json_encode( $legal_privacy ); ?>,
  legal_location: <?php echo wp_json_encode( $legal_location ); ?>,
  legal_license: <?php echo wp_json_encode( $legal_license ); ?>,
  legal_copyright: <?php echo wp_json_encode( $legal_copyright ); ?>
};
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="assets/js/dashboard.js?v=<?php echo @filemtime( __DIR__ . '/assets/js/dashboard.js' ) ?: '1'; ?>"></script>
</body>
</html>