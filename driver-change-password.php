<?php
/** Idibia — Driver Forced Password Change */

ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';

// Must be a logged-in driver
if ( ! is_user_logged_in() || get_user_meta( get_current_user_id(), 'idibia_account_type', true ) !== 'driver' ) {
    header( 'Location: /driver.php' );
    exit;
}

$current_user = wp_get_current_user();
$driver_id    = idibia_find_or_create_profile_row( $current_user->ID, 'driver' );

global $wpdb;
$driver = $wpdb->get_row( $wpdb->prepare(
    "SELECT id, full_name, email, force_password_change FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1",
    $driver_id
), ARRAY_A );

// If the flag is not set, redirect back to the app normally.
if ( ! $driver || empty( $driver['force_password_change'] ) ) {
    header( 'Location: /driver.php' );
    exit;
}

$error   = '';
$success = false;

// ── Handle POST ───────────────────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $new_password  = (string) ( $_POST['new_password']     ?? '' );
    $confirm_password = (string) ( $_POST['confirm_password'] ?? '' );

    if ( strlen( $new_password ) < 8 ) {
        $error = 'Password must be at least 8 characters.';
    } elseif ( $new_password !== $confirm_password ) {
        $error = 'Passwords do not match.';
    } else {
        // Update WordPress user password (which is the auth source for drivers)
        wp_set_password( $new_password, $current_user->ID );

        // Clear the force flag and remove the temp hash
        $wpdb->update(
            $wpdb->prefix . 'sd_drivers',
            [
                'force_password_change' => 0,
                'temp_password_hash'    => null,
            ],
            [ 'id' => $driver_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        // Re-authenticate the driver so the session remains valid
        wp_clear_auth_cookie();
        $signon = wp_signon( [
            'user_login'    => $current_user->user_login,
            'user_password' => $new_password,
            'remember'      => true,
        ], is_ssl() );

        $success = true;
    }
}

$first_name = explode( ' ', trim( $driver['full_name'] ) )[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set New Password — Idibia Driver</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    background: #f0f0f5;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
  }
  .card {
    background: #fff;
    border-radius: 16px;
    padding: 40px 32px;
    max-width: 420px;
    width: 100%;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
  }
  .logo {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: #1a1a2e;
    margin-bottom: 8px;
  }
  h1 {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 6px;
  }
  .subtitle {
    font-size: 14px;
    color: #666;
    margin-bottom: 28px;
    line-height: 1.5;
  }
  label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 6px;
  }
  input[type="password"] {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid #ddd;
    border-radius: 8px;
    font-size: 15px;
    outline: none;
    transition: border-color 0.15s;
    margin-bottom: 16px;
    font-family: inherit;
  }
  input[type="password"]:focus { border-color: #1a1a2e; }
  .error-box {
    background: #fff1f0;
    border: 1px solid #ffa39e;
    color: #cf1322;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13px;
    margin-bottom: 20px;
  }
  .success-box {
    background: #f0fff4;
    border: 1px solid #b7eb8f;
    color: #135200;
    border-radius: 8px;
    padding: 14px 16px;
    font-size: 14px;
    margin-bottom: 20px;
    line-height: 1.5;
  }
  .btn {
    width: 100%;
    padding: 14px;
    background: #1a1a2e;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    letter-spacing: 0.2px;
    transition: opacity 0.15s;
  }
  .btn:hover { opacity: 0.88; }
  .hint {
    font-size: 12px;
    color: #999;
    margin-top: 14px;
    text-align: center;
  }
</style>
</head>
<body>
<div class="card">
  <div class="logo">Idibia</div>
  <h1>Set a new password</h1>
  <p class="subtitle">
    Hi <?php echo esc_html( $first_name ); ?>, your account password was reset by an administrator.
    Please choose a new password to continue.
  </p>

  <?php if ( $error ) : ?>
    <div class="error-box"><?php echo esc_html( $error ); ?></div>
  <?php endif; ?>

  <?php if ( $success ) : ?>
    <div class="success-box">
      Your password has been updated successfully.<br>
      You'll be redirected to the driver app now.
    </div>
    <script>
      setTimeout(function () { window.location.href = '/driver.php'; }, 1800);
    </script>
  <?php else : ?>
    <form method="POST" action="/driver-change-password.php">
      <label for="new_password">New Password</label>
      <input type="password" id="new_password" name="new_password" required
             minlength="8" placeholder="At least 8 characters" autocomplete="new-password">

      <label for="confirm_password">Confirm Password</label>
      <input type="password" id="confirm_password" name="confirm_password" required
             minlength="8" placeholder="Repeat your new password" autocomplete="new-password">

      <button type="submit" class="btn">Set Password &amp; Continue</button>
    </form>
    <p class="hint">You must set a new password before you can access the driver app.</p>
  <?php endif; ?>
</div>
</body>
</html>
