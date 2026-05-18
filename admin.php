<?php ob_start();
require_once __DIR__ . '/wp-auth-config.php';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) ) {
    idibia_clean_json_buffer();
    $action = sanitize_key( wp_unslash( $_POST['action'] ) );

    if ( $action === 'admin_login' ) {
        $identifier = sanitize_text_field( wp_unslash( $_POST['login'] ?? '' ) );
        $password   = (string) ( $_POST['password'] ?? '' );

        if ( ! $identifier || ! $password ) {
            wp_send_json_error( [ 'message' => 'Enter your admin login and password.' ] );
        }

        $user = wp_signon( [
            'user_login'    => idibia_find_user_login_by_identifier( $identifier ),
            'user_password' => $password,
            'remember'      => true,
        ], is_ssl() );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error( [ 'message' => 'Invalid admin login details.' ] );
        }

        idibia_finish_wordpress_login( $user );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_logout();
            wp_send_json_error( [ 'message' => 'This account does not have admin access.' ] );
        }

        wp_send_json_success( [ 'redirect' => '/admin.php' ] );
    }

    if ( $action === 'admin_logout' ) {
        wp_logout();
        wp_send_json_success( [ 'redirect' => '/admin.php' ] );
    }

    wp_send_json_error( [ 'message' => 'Unknown action.' ] );
}

if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
    if ( ob_get_level() > 0 ) ob_end_flush();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Idibia — Admin Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-login-body">
  <?php require_once __DIR__ . '/components/admin/login.php'; ?>
<script src="assets/js/admin.js"></script>
</body>
</html>
    <?php    exit;
}

if ( ob_get_level() > 0 ) ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Idibia — Admin Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div id="app">

<?php require_once __DIR__ . '/components/admin/sidebar.php'; ?>

<!-- MAIN -->
<div class="main">
  <?php require_once __DIR__ . '/components/admin/topbar.php'; ?>
  <?php require_once __DIR__ . '/components/admin/notif-panel.php'; ?>

  <?php require_once __DIR__ . '/components/admin/panel-overview.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-kyc.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-ops.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-trips.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-revenue.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-payouts.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-drivers.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-users.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-disputes.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-settings.php'; ?>

</div><!-- /main -->

<?php require_once __DIR__ . '/components/admin/modals.php'; ?>

<script>
let ADMIN_API_URL = "/admin/api.php";
</script>
<script src="assets/js/admin.js"></script>
</body>
</html>