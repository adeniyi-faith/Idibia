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

        global $wpdb;

        // Check new sd_admin_users table first
        $admin = $wpdb->get_row( $wpdb->prepare( "
            SELECT * FROM `{$wpdb->prefix}sd_admin_users`
            WHERE email = %s
        ", $identifier ) );

        $is_new_admin = false;

        if ( $admin ) {
            if ( $admin->status === 'suspended' ) {
                wp_send_json_error( [ 'message' => 'Account suspended.' ] );
            }
            if ( ! wp_check_password( $password, $admin->password_hash ) ) {
                wp_send_json_error( [ 'message' => 'Invalid admin login details.' ] );
            }
            $is_new_admin = true;

            // We set a secure custom session cookie for sd_admin_users
            $session_token = bin2hex(random_bytes(32));
            // Assuming we don't have an sd_admin_sessions table, we'll store it as a secure transient.
            // Better yet, just use a signed cookie containing the ID, since wp_salt('auth') is available.

            $remember_me     = ! empty( $_POST['remember_me'] );
            $cookie_lifetime = $remember_me ? ( 30 * DAY_IN_SECONDS ) : ( 12 * HOUR_IN_SECONDS );

            $payload = json_encode(['id' => $admin->id, 'time' => time(), 'remember' => $remember_me]);
            $hash = hash_hmac('sha256', $payload, wp_salt('auth'));
            $cookie_val = base64_encode($payload . '|' . $hash);

            setcookie('idibia_admin_auth', $cookie_val, time() + $cookie_lifetime, '/', '', is_ssl(), true);

            // Update last login
            $wpdb->update(
                "{$wpdb->prefix}sd_admin_users",
                [
                    'last_login' => gmdate('Y-m-d H:i:s'),
                    'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ],
                [ 'id' => $admin->id ]
            );
        } else {
            // Fallback to legacy WP admin
            $remember_me = ! empty( $_POST['remember_me'] );
            $user = wp_signon( [
                'user_login'    => idibia_find_user_login_by_identifier( $identifier ),
                'user_password' => $password,
                'remember'      => $remember_me,
            ], is_ssl() );

            if ( is_wp_error( $user ) ) {
                wp_send_json_error( [ 'message' => 'Invalid admin login details.' ] );
            }

            idibia_finish_wordpress_login( $user );

            if ( ! current_user_can( 'manage_options' ) ) {
                wp_logout();
                wp_send_json_error( [ 'message' => 'This account does not have admin access.' ] );
            }

            // Clear any custom admin cookie
            setcookie('idibia_admin_auth', '', time() - 3600, '/', '', is_ssl(), true);
        }

        wp_send_json_success( [ 'redirect' => '/admin.php' ] );
    }

    if ( $action === 'admin_logout' ) {
        // Simple nonce check. We might not have WP nonce if logged in via custom cookie
        // For simplicity we omit nonce check on logout or fallback to it
        wp_logout();
        setcookie('idibia_admin_auth', '', time() - 3600, '/', '', is_ssl(), true);
        wp_send_json_success( [ 'redirect' => '/admin.php' ] );
    }

    wp_send_json_error( [ 'message' => 'Unknown action.' ] );
}

$custom_admin_id = 0;
if ( isset($_COOKIE['idibia_admin_auth']) ) {
    $decoded = base64_decode($_COOKIE['idibia_admin_auth']);
    $parts = explode('|', $decoded);
    if ( count($parts) === 2 ) {
        $hash = hash_hmac('sha256', $parts[0], wp_salt('auth'));
        if ( hash_equals($hash, $parts[1]) ) {
            $payload = json_decode($parts[0], true);
            if ( $payload && isset($payload['id']) ) {
                $custom_admin_id = (int) $payload['id'];
            }
        }
    }
}

if ( ! $custom_admin_id ) {
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
<link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime( __DIR__ . '/assets/css/admin.css' ); ?>">
</head>
<body class="admin-login-body">
  <?php require_once __DIR__ . '/components/admin/login.php'; ?>
<script src="assets/js/admin.js?v=<?php echo filemtime( __DIR__ . '/assets/js/admin.js' ); ?>"></script>
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
<link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime( __DIR__ . '/assets/css/admin.css' ); ?>">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
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
  <?php require_once __DIR__ . '/components/admin/panel-live-ops.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-trips.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-revenue.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-reconciliation.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-payouts.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-drivers.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-customers.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-disputes.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-settings.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-admin-users.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-ratings.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-campaigns.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-notifications.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-system.php'; ?>

</div><!-- /main -->

<?php require_once __DIR__ . '/components/admin/modals.php'; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let ADMIN_API_URL = "/admin/api.php";
</script>
<script src="assets/js/admin.js?v=<?php echo filemtime( __DIR__ . '/assets/js/admin.js' ); ?>"></script>
</body>
</html>