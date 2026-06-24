<?php
ini_set( 'display_errors', '0' );
ob_start();
require_once __DIR__ . '/wp-auth-config.php';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) ) {
    idibia_clean_json_buffer();
    $action = sanitize_key( wp_unslash( $_POST['action'] ) );

    if ( $action === 'admin_first_setup' ) {
        global $wpdb;
        // Only allowed when NO admin users exist yet
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_admin_users`" );
        if ( $count > 0 ) {
            wp_send_json_error( [ 'message' => 'Setup already complete. Please log in.' ] );
        }

        $full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
        $email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $password  = (string) ( $_POST['password'] ?? '' );
        $confirm   = (string) ( $_POST['confirm_password'] ?? '' );

        if ( ! $full_name || ! $email || ! $password ) {
            wp_send_json_error( [ 'message' => 'All fields are required.' ] );
        }
        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => 'Enter a valid email address.' ] );
        }
        if ( strlen( $password ) < 8 ) {
            wp_send_json_error( [ 'message' => 'Password must be at least 8 characters.' ] );
        }
        if ( $password !== $confirm ) {
            wp_send_json_error( [ 'message' => 'Passwords do not match.' ] );
        }

        // Get Super Admin role id (id = 1 per migration seed)
        $role_id = (int) $wpdb->get_var( "SELECT id FROM `{$wpdb->prefix}sd_roles` WHERE name = 'Super Admin' LIMIT 1" );
        if ( ! $role_id ) {
            wp_send_json_error( [ 'message' => 'Roles not found. Run database migrations first.' ] );
        }

        $wpdb->insert(
            "{$wpdb->prefix}sd_admin_users",
            [
                'full_name'     => $full_name,
                'email'         => $email,
                'password_hash' => wp_hash_password( $password ),
                'role_id'       => $role_id,
                'status'        => 'active',
                'created_at'    => gmdate( 'Y-m-d H:i:s' ),
            ],
            [ '%s', '%s', '%s', '%d', '%s', '%s' ]
        );

        $new_id = (int) $wpdb->insert_id;
        if ( ! $new_id ) {
            wp_send_json_error( [ 'message' => 'Failed to create account. Please try again.' ] );
        }

        $remember_me     = true;
        $cookie_lifetime = 12 * HOUR_IN_SECONDS;
        $payload         = json_encode( [ 'id' => $new_id, 'time' => time(), 'remember' => false ] );
        $hash            = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
        $cookie_val      = base64_encode( $payload . '|' . $hash );

        setcookie( 'idibia_admin_auth', $cookie_val, [
            'expires'  => time() + $cookie_lifetime,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ] );

        wp_send_json_success( [ 'redirect' => '/admin.php' ] );
    }

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

            setcookie( 'idibia_admin_auth', $cookie_val, [
                'expires'  => time() + $cookie_lifetime,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ] );

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
            wp_send_json_error( [ 'message' => 'Invalid admin login details.' ] );
        }

        wp_send_json_success( [ 'redirect' => '/admin.php' ] );
    }

    if ( $action === 'admin_logout' ) {
        // Simple nonce check. We might not have WP nonce if logged in via custom cookie
        // For simplicity we omit nonce check on logout or fallback to it
        wp_logout();
        setcookie( 'idibia_admin_auth', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ] );
        wp_send_json_success( [ 'redirect' => '/admin.php' ] );
    }

    if ( $action === 'admin_change_password' ) {
        if ( ! isset( $_COOKIE['idibia_admin_auth'] ) ) {
            wp_send_json_error( [ 'message' => 'Not authenticated.' ] );
        }
        $decoded = base64_decode( $_COOKIE['idibia_admin_auth'] );
        $parts   = explode( '|', $decoded );
        if ( count( $parts ) !== 2 ) {
            wp_send_json_error( [ 'message' => 'Invalid session.' ] );
        }
        $hash = hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) );
        if ( ! hash_equals( $hash, $parts[1] ) ) {
            wp_send_json_error( [ 'message' => 'Invalid session.' ] );
        }
        $payload = json_decode( $parts[0], true );
        if ( ! $payload || ! isset( $payload['id'] ) ) {
            wp_send_json_error( [ 'message' => 'Invalid session.' ] );
        }
        $uid          = (int) $payload['id'];
        $new_password = (string) ( $_POST['new_password'] ?? '' );
        $confirm      = (string) ( $_POST['confirm_password'] ?? '' );

        if ( strlen( $new_password ) < 8 ) {
            wp_send_json_error( [ 'message' => 'Password must be at least 8 characters.' ] );
        }
        if ( $new_password !== $confirm ) {
            wp_send_json_error( [ 'message' => 'Passwords do not match.' ] );
        }

        global $wpdb;
        $updated = $wpdb->update(
            "{$wpdb->prefix}sd_admin_users",
            [
                'password_hash'        => wp_hash_password( $new_password ),
                'force_password_change' => 0,
                'updated_at'           => gmdate( 'Y-m-d H:i:s' ),
            ],
            [ 'id' => $uid ],
            [ '%s', '%d', '%s' ],
            [ '%d' ]
        );

        if ( $updated === false ) {
            wp_send_json_error( [ 'message' => 'Failed to update password. Please try again.' ] );
        }

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

// Validate the admin row and enforce forced password change
if ( $custom_admin_id ) {
    global $wpdb;
    $admin_row = $wpdb->get_row( $wpdb->prepare(
        "SELECT status, force_password_change FROM `{$wpdb->prefix}sd_admin_users` WHERE id = %d LIMIT 1",
        $custom_admin_id
    ) );

    if ( ! $admin_row || $admin_row->status !== 'active' ) {
        // Stale or suspended session — clear the cookie and redirect to login.
        setcookie( 'idibia_admin_auth', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ] );
        if ( ob_get_level() > 0 ) ob_end_flush();
        header( 'Location: /admin.php' );
        exit;
    }

    if ( ! empty( $admin_row->force_password_change ) ) {
        if ( ob_get_level() > 0 ) ob_end_flush();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Idibia — Set Your Password</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime( __DIR__ . '/assets/css/admin.css' ); ?>">
</head>
<body class="admin-login-body">
<form class="login-card" id="changePasswordForm" novalidate>
  <div class="login-brand">
    <div class="brand-badge">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
  </div>
  <h1 class="login-title">Set Your Password</h1>
  <p class="login-sub">Your account requires a new password before you can continue.</p>
  <div class="err" id="changePwError"></div>
  <div class="login-field">
    <label for="newPassword">New Password</label>
    <div class="pw-wrap">
      <input id="newPassword" name="new_password" type="password" autocomplete="new-password" placeholder="At least 8 characters" required>
      <button type="button" class="eye-btn" id="toggleNewPw" aria-label="Show password">
        <svg class="eye-icon eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg class="eye-icon eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
      </button>
    </div>
  </div>
  <div class="login-field">
    <label for="confirmPassword">Confirm Password</label>
    <input id="confirmPassword" name="confirm_password" type="password" autocomplete="new-password" placeholder="Repeat your new password" required>
  </div>
  <button class="login-btn" id="changePwBtn">Update Password &amp; Continue</button>
</form>
<script>
(function () {
  document.getElementById('toggleNewPw').addEventListener('click', function () {
    const input = document.getElementById('newPassword');
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    this.querySelector('.eye-open').style.display  = show ? 'none' : '';
    this.querySelector('.eye-closed').style.display = show ? '' : 'none';
  });

  document.getElementById('changePasswordForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('changePwBtn');
    const err = document.getElementById('changePwError');
    err.classList.remove('show');
    btn.disabled = true;
    btn.textContent = 'Updating…';
    try {
      const body = new FormData(this);
      body.append('action', 'admin_change_password');
      const res = await fetch(window.location.href, { method: 'POST', body, credentials: 'same-origin' });
      const raw = await res.text();
      let data;
      try { data = JSON.parse(raw); } catch (ex) { throw new Error('Invalid server response'); }
      if (data.success) {
        window.location.href = data.data.redirect || '/admin.php';
      } else {
        err.textContent = data.data?.message || 'Failed to update password.';
        err.classList.add('show');
      }
    } catch (ex) {
      err.textContent = 'Could not reach the server. Please try again.';
      err.classList.add('show');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Update Password & Continue';
    }
  });
})();
</script>
</body>
</html>
        <?php exit;
    }
}

if ( ! $custom_admin_id ) {
    if ( ob_get_level() > 0 ) ob_end_flush();

    global $wpdb;
    $admin_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_admin_users`" );
    $is_first_run = ( $admin_count === 0 );
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Idibia — <?php echo $is_first_run ? 'Setup Admin Account' : 'Admin Login'; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime( __DIR__ . '/assets/css/admin.css' ); ?>">
</head>
<body class="admin-login-body">
<?php if ( $is_first_run ) : ?>
<div class="admin-login-body">
<form class="login-card" id="adminSetupForm" method="post" novalidate>
  <div class="login-brand">
    <div class="brand-badge">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    </div>
  </div>
  <h1 class="login-title">Create Admin Account</h1>
  <p class="login-sub">No admin users exist yet. Set up your Super Admin account to get started.</p>
  <div class="err" id="adminSetupError"></div>
  <div class="login-field">
    <label for="setupName">Full Name</label>
    <input id="setupName" name="full_name" type="text" autocomplete="name" placeholder="Your full name" required>
  </div>
  <div class="login-field">
    <label for="setupEmail">Email Address</label>
    <input id="setupEmail" name="email" type="email" autocomplete="email" placeholder="admin@example.com" required>
  </div>
  <div class="login-field">
    <label for="setupPassword">Password</label>
    <div class="pw-wrap">
      <input id="setupPassword" name="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" required>
      <button type="button" class="eye-btn" id="toggleSetupPassword" aria-label="Show password">
        <svg class="eye-icon eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg class="eye-icon eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
      </button>
    </div>
  </div>
  <div class="login-field">
    <label for="setupConfirm">Confirm Password</label>
    <input id="setupConfirm" name="confirm_password" type="password" autocomplete="new-password" placeholder="Repeat your password" required>
  </div>
  <button class="login-btn" id="adminSetupBtn">Create Account &amp; Sign In</button>
</form>
</div>
<script>
(function () {
  const form = document.getElementById('adminSetupForm');
  const btn  = document.getElementById('adminSetupBtn');
  const err  = document.getElementById('adminSetupError');

  document.getElementById('toggleSetupPassword').addEventListener('click', function () {
    const input = document.getElementById('setupPassword');
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    this.querySelector('.eye-open').style.display  = show ? 'none' : '';
    this.querySelector('.eye-closed').style.display = show ? '' : 'none';
  });

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    err.classList.remove('show');
    btn.disabled = true;
    btn.textContent = 'Creating account…';
    try {
      const body = new FormData(form);
      body.append('action', 'admin_first_setup');
      const res  = await fetch(window.location.href, { method: 'POST', body, credentials: 'same-origin' });
      const raw  = await res.text();
      let data;
      try { data = JSON.parse(raw); } catch (ex) { throw new Error('Invalid server response'); }
      if (data.success) {
        window.location.href = data.data.redirect || '/admin.php';
      } else {
        err.textContent = data.data?.message || 'Setup failed.';
        err.classList.add('show');
      }
    } catch (ex) {
      err.textContent = 'Could not reach the server. Please try again.';
      err.classList.add('show');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Create Account & Sign In';
    }
  });
})();
</script>
<?php else : ?>
  <?php require_once __DIR__ . '/components/admin/login.php'; ?>
  <script src="assets/js/admin.js?v=<?php echo filemtime( __DIR__ . '/assets/js/admin.js' ); ?>"></script>
<?php endif; ?>
</body>
</html>
    <?php exit;
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
  <?php require_once __DIR__ . '/components/admin/panel-wallet-topups.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-drivers.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-customers.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-disputes.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-settings.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-admin-users.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-ratings.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-campaigns.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-notifications.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-system.php'; ?>
  <?php require_once __DIR__ . '/components/admin/panel-audit-log.php'; ?>

</div><!-- /main -->

<?php require_once __DIR__ . '/components/admin/modals.php'; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let ADMIN_API_URL = "/admin/api.php";
</script>
<script src="assets/js/admin.js?v=<?php echo filemtime( __DIR__ . '/assets/js/admin.js' ); ?>" defer></script>
</body>
</html>