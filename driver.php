<?php ob_start();
require_once __DIR__ . '/wp-auth-config.php';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) ) {
    idibia_clean_json_buffer();

    $action = sanitize_key( wp_unslash( $_POST['action'] ) );

    if ( $action === 'login' ) {
        $identifier = sanitize_text_field( wp_unslash( $_POST['phone'] ?? $_POST['email'] ?? '' ) );
        $password   = (string) ( $_POST['password'] ?? '' );

        if ( ! $identifier || ! $password ) {
            wp_send_json_error( [ 'message' => 'Enter your phone/email and password.' ] );
        }

        $user = wp_signon( [
            'user_login'    => idibia_find_user_login_by_identifier( $identifier ),
            'user_password' => $password,
            'remember'      => true,
        ], is_ssl() );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error( [ 'message' => 'Invalid login details.' ] );
        }

        if ( get_user_meta( $user->ID, 'idibia_account_type', true ) !== 'driver' ) {
            wp_logout();
            wp_send_json_error( [ 'message' => 'Use a driver account to sign in here.' ] );
        }

        if ( get_user_meta( $user->ID, 'idibia_account_status', true ) === 'suspended' ) {
            wp_logout();
            wp_send_json_error( [ 'message' => 'Your driver account is suspended. Contact support.' ] );
        }

        idibia_finish_wordpress_login( $user );
        $driver_id = idibia_find_or_create_profile_row( $user->ID, 'driver' );
        global $wpdb;
        $driver_row = $wpdb->get_row( $wpdb->prepare( "SELECT kyc_status, status, is_online FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $driver_id ), ARRAY_A );
        $kyc_status = $driver_row['kyc_status'] ?? ( get_user_meta( $user->ID, 'idibia_kyc_status', true ) ?: 'pending' );
        $status     = $driver_row['status'] ?? ( get_user_meta( $user->ID, 'idibia_account_status', true ) ?: 'pending' );

        wp_send_json_success( [
            'redirect'    => '/driver.php',
            'first_name'  => idibia_first_name_from_user( $user ),
            'driver_id'   => $driver_id,
            'kyc_status'  => $kyc_status,
            'status'      => $status,
            'is_approved' => $kyc_status === 'approved' && $status === 'active',
            'is_online'   => ! empty( $driver_row['is_online'] ),
        ] );
    }

    if ( $action === 'signup' ) {
        $first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
        $middle_name = sanitize_text_field( wp_unslash( $_POST['middle_name'] ?? '' ) );
        $last_name  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
        $full_name  = trim( $first_name . ' ' . ( $middle_name ? $middle_name . ' ' : '' ) . $last_name );
        $phone      = preg_replace( '/[\s\-()]/', '', sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
        $email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $password   = (string) ( $_POST['password'] ?? '' );
        $language   = sanitize_text_field( wp_unslash( $_POST['language'] ?? 'English' ) );
        $dob        = sanitize_text_field( wp_unslash( $_POST['date_of_birth'] ?? '' ) );
        $gender     = sanitize_text_field( wp_unslash( $_POST['gender'] ?? '' ) );
        $state      = sanitize_text_field( wp_unslash( $_POST['state_of_origin'] ?? '' ) );
        $vehicle_type = sanitize_text_field( wp_unslash( $_POST['vehicle_type'] ?? 'bike' ) );
        $errors     = [];

        if ( strlen( $first_name ) < 2 || strlen( $last_name ) < 2 ) $errors[] = 'Please enter your first and last name.';
        if ( ! is_email( $email ) ) $errors[] = 'Enter a valid email address.';
        if ( ! preg_match( '/^(\+?234|0)[789][01]\d{8}$/', $phone ) ) $errors[] = 'Enter a valid Nigerian phone number.';
        if ( strlen( $password ) < 6 ) $errors[] = 'Password must be at least 6 characters.';
        if ( $errors ) wp_send_json_error( [ 'message' => implode( ' ', $errors ) ] );

        if ( username_exists( $phone ) ) wp_send_json_error( [ 'message' => 'Phone already registered. Try logging in.' ] );
        if ( email_exists( $email ) ) wp_send_json_error( [ 'message' => 'Email already in use. Try logging in.' ] );

        $user_id = wp_insert_user( [
            'user_login'   => $phone,
            'user_pass'    => $password,
            'user_email'   => $email,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $full_name,
            'role'         => 'subscriber',
        ] );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( [ 'message' => $user_id->get_error_message() ?: 'Something went wrong. Please try again.' ] );
        }

        update_user_meta( $user_id, 'idibia_account_type', 'driver' );
        update_user_meta( $user_id, 'idibia_account_status', 'pending' );
        update_user_meta( $user_id, 'idibia_kyc_status', 'pending' );
        update_user_meta( $user_id, 'idibia_phone', $phone );
        update_user_meta( $user_id, 'idibia_driver_language', $language );
        update_user_meta( $user_id, 'idibia_driver_middle_name', $middle_name );
        update_user_meta( $user_id, 'idibia_driver_date_of_birth', $dob );
        update_user_meta( $user_id, 'idibia_driver_gender', $gender );
        update_user_meta( $user_id, 'idibia_driver_state_of_origin', $state );

        $driver_id = idibia_find_or_create_profile_row( $user_id, 'driver', [
            'full_name' => $full_name,
            'email'     => $email,
            'phone'     => $phone,
        ] );
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'sd_drivers', [ 'vehicle_type' => in_array( $vehicle_type, [ 'bike', 'car', 'van', 'keke' ], true ) ? $vehicle_type : 'bike' ], [ 'id' => $driver_id ], [ '%s' ], [ '%d' ] );

        $user = get_user_by( 'id', $user_id );
        idibia_finish_wordpress_login( $user );

        wp_send_json_success( [
            'redirect'   => '/driver.php',
            'first_name' => $first_name,
            'driver_id'  => $driver_id,
            'kyc_status'  => 'pending',
            'status'      => 'pending',
            'is_approved' => false,
            'message'     => 'Driver account created. Continue your KYC application.',
        ] );
    }

    wp_send_json_error( [ 'message' => 'Unknown auth action.' ] );
}


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

    $driver_initial_context = [
        'logged_in'   => true,
        'driver_id'   => $driver_id,
        'first_name'  => idibia_first_name_from_user( $current_user ),
        'full_name'   => $driver_row['full_name'] ?? $current_user->display_name,
        'kyc_status'  => $kyc_status,
        'status'      => $status,
        'is_approved' => $kyc_status === 'approved' && $status === 'active',
        'is_online'   => ! empty( $driver_row['is_online'] ),
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
<style>
:root {
  --navy: #0B1628;
  --navy-light: #1E2D47;
  --navy-mid: #152238;
  --gold: #F5C842;
  --gold-dark: #C9A224;
  --gold-pale: rgba(245,200,66,0.08);
  --slate: #8A9AB8;
  --slate-light: #B8C5D8;
  --white: #FFFFFF;
  --surface: #F4F6FA;
  --surface-2: #E8ECF3;
  --danger: #E8484A;
  --success: #22C47A;
  --success-pale: rgba(34,196,122,0.1);
  --info: #4A9EFF;
  --info-pale: rgba(74,158,255,0.08);
  --text-primary: #0B1628;
  --text-secondary: #5A6B85;
  --text-muted: #8A9AB8;
  --radius: 16px;
  --radius-sm: 10px;
  --radius-lg: 24px;
  --shadow-sm: 0 2px 12px rgba(11,22,40,0.08);
  --shadow-md: 0 6px 28px rgba(11,22,40,0.13);
  --shadow-lg: 0 16px 48px rgba(11,22,40,0.2);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; overflow: hidden; }
body {
  font-family: 'DM Sans', sans-serif;
  background: var(--navy);
  height: 100%;
  min-height: 100dvh;
  overflow: hidden;
  color: var(--white);
  -webkit-font-smoothing: antialiased;
}
h1, h2, h3, h4 { font-family: 'Syne', sans-serif; font-weight: 700; }

/* ===== APP WRAPPER ===== */
#app {
  width: 100%;
  height: 100%;
  height: var(--app-height, 100dvh);
  position: relative;
  overflow: hidden;
}

/* ===== SCREENS ===== */
.screen {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  opacity: 0;
  pointer-events: none;
  transform: translateX(30px);
  transition: opacity 0.35s cubic-bezier(0.4,0,0.2,1), transform 0.35s cubic-bezier(0.4,0,0.2,1);
}
.screen.active { opacity: 1; pointer-events: all; transform: translateX(0); }
.screen.slide-out { opacity: 0; transform: translateX(-30px); }

/* DESKTOP: side-by-side layout for screens with a sidebar */
@media (min-width: 768px) {
  #screen-driver-dash {
    flex-direction: row;
  }
}

/* ===== FORMS ===== */
.form-group { margin-bottom: 18px; }
.form-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 7px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}
.form-input {
  width: 100%;
  height: 52px;
  background: var(--white);
  border: 1.5px solid var(--surface-2);
  border-radius: var(--radius-sm);
  padding: 0 16px;
  font-size: 15px;
  font-family: 'DM Sans', sans-serif;
  color: var(--text-primary);
  transition: border-color 0.2s, box-shadow 0.2s;
  outline: none;
  -webkit-appearance: none;
  appearance: none;
}
.form-input:focus {
  border-color: var(--gold);
  box-shadow: 0 0 0 3px rgba(245,200,66,0.15);
}
.form-input::placeholder { color: var(--text-muted); }
.section-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1.2px;
  color: var(--text-muted);
  text-transform: uppercase;
  margin-bottom: 12px;
  display: block;
}

/* ===== BUTTONS ===== */
.btn-primary {
  width: 100%;
  height: 52px;
  background: var(--gold);
  color: var(--navy);
  border: none;
  border-radius: var(--radius-sm);
  font-family: 'Syne', sans-serif;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
  letter-spacing: 0.3px;
}
.btn-primary:hover { background: var(--gold-dark); box-shadow: 0 4px 16px rgba(245,200,66,0.35); }
.btn-primary:active { transform: scale(0.98); }

.global-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 24px;
  background: var(--gold);
  color: var(--navy);
  border: 1.5px solid transparent;
  border-radius: var(--radius-sm);
  font-family: 'Syne', sans-serif;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}
.global-btn:hover { background: var(--gold-dark); box-shadow: 0 4px 16px rgba(245,200,66,0.3); }
.global-btn:active { transform: scale(0.97); }
.global-btn.ghost {
  background: transparent;
  border-color: var(--surface-2);
  color: var(--text-secondary);
}
.global-btn.ghost:hover { background: var(--surface); box-shadow: none; }

/* ===== DRIVER ONBOARDING ===== */
#screen-driver { background: var(--surface); }

.driver-header {
  background: var(--navy);
  flex-shrink: 0;
  padding: 56px 24px 24px;
  padding-top: max(56px, env(safe-area-inset-top, 20px));
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: relative;
  overflow: hidden;
}
.driver-header::before {
  content: '';
  position: absolute;
  width: 200px; height: 200px;
  background: var(--gold);
  opacity: 0.04;
  border-radius: 50%;
  top: -60px; right: -40px;
}
.driver-header h2 { font-size: 20px; position: relative; z-index: 1; }
.step-indicator {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  color: var(--slate);
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.08);
  padding: 6px 12px;
  border-radius: 20px;
  position: relative;
  z-index: 1;
}
.step-indicator strong { color: var(--gold); font-family: 'Syne', sans-serif; }

.progress-bar-wrap { background: var(--navy); padding: 0 24px 20px; flex-shrink: 0; }
.progress-bar-track {
  height: 4px;
  background: rgba(255,255,255,0.08);
  border-radius: 2px;
  overflow: hidden;
}
.progress-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--gold-dark), var(--gold));
  border-radius: 2px;
  transition: width 0.5s cubic-bezier(0.4,0,0.2,1);
  position: relative;
}
.progress-bar-fill::after {
  content: '';
  position: absolute;
  right: 0; top: 0; bottom: 0;
  width: 20px;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.35));
  border-radius: 2px;
  animation: progress-shimmer 1.5s ease-in-out infinite;
}
@keyframes progress-shimmer { 0%,100% { opacity: 0.4; } 50% { opacity: 1; } }

.driver-content {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  padding: 24px;
  padding-bottom: calc(100px + env(safe-area-inset-bottom, 0px));
  color: var(--text-primary);
}
.driver-content::-webkit-scrollbar { width: 3px; }
.driver-content::-webkit-scrollbar-thumb { background: var(--surface-2); border-radius: 2px; }

.driver-step { display: none; }
.driver-step.active { display: block; animation: stepIn 0.3s ease; }
@keyframes stepIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

.step-title { font-size: 26px; color: var(--text-primary); margin-bottom: 6px; line-height: 1.2; }
.step-sub { font-size: 15px; color: var(--text-secondary); margin-bottom: 26px; line-height: 1.5; }

/* Vehicle grid */
.vehicle-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
.vehicle-card {
  background: var(--white);
  border-radius: var(--radius);
  padding: 20px 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  border: 2px solid var(--surface-2);
  transition: all 0.2s;
  text-align: center;
  box-shadow: var(--shadow-sm);
}
.vehicle-card:hover { border-color: var(--slate-light); transform: translateY(-1px); }
.vehicle-card.active {
  border-color: var(--gold);
  background: rgba(245,200,66,0.05);
  box-shadow: 0 0 0 1px var(--gold), var(--shadow-sm);
}
.vehicle-card svg { width: 36px; height: 36px; color: var(--text-muted); transition: color 0.2s; }
.vehicle-card.active svg { color: var(--gold-dark); }
.vehicle-card span { font-size: 13px; font-weight: 600; color: var(--text-secondary); transition: color 0.2s; }
.vehicle-card.active span { color: var(--text-primary); }

/* Upload boxes */
.upload-box {
  background: var(--white);
  border: 2px dashed var(--surface-2);
  border-radius: var(--radius);
  padding: 28px 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.25s;
  margin-bottom: 14px;
  box-shadow: var(--shadow-sm);
}
.upload-box:hover { border-color: var(--gold); background: rgba(245,200,66,0.02); transform: translateY(-1px); }
.upload-box svg { width: 32px; height: 32px; color: var(--text-muted); margin-bottom: 10px; }
.upload-box p { font-size: 14px; color: var(--text-secondary); font-weight: 500; }
.upload-box small { font-size: 12px; color: var(--text-muted); margin-top: 4px; display: block; }
.upload-box.done {
  border-style: solid;
  border-color: var(--success);
  background: rgba(34,196,122,0.04);
}
.upload-box.done svg { color: var(--success); }

/* Info notes */
.info-note {
  background: var(--info-pale);
  border-left: 3px solid var(--info);
  border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
  padding: 12px 16px;
  font-size: 13px;
  color: var(--info);
  margin-bottom: 20px;
  line-height: 1.6;
}
.info-note.gold {
  background: rgba(245,200,66,0.08);
  border-color: var(--gold-dark);
  color: #9a7810;
}

/* Driver footer */
.driver-footer {
  position: absolute;
  bottom: 0;
  left: 0; right: 0;
  padding: 14px 24px;
  padding-bottom: max(14px, env(safe-area-inset-bottom));
  background: var(--surface);
  border-top: 1px solid var(--surface-2);
  display: flex;
  gap: 10px;
  box-shadow: 0 -4px 20px rgba(11,22,40,0.08);
  z-index: 5;
}

.driver-auth-card {
  background: var(--white);
  border: 1.5px solid var(--surface-2);
  border-radius: var(--radius-lg);
  padding: 16px;
  margin-bottom: 20px;
  box-shadow: var(--shadow-sm);
}
.driver-auth-tabs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  background: var(--surface);
  border-radius: var(--radius-sm);
  padding: 5px;
  margin-bottom: 16px;
}
.driver-auth-tab {
  border: 0;
  border-radius: 9px;
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
  font-family: 'Syne', sans-serif;
  font-size: 14px;
  font-weight: 700;
  padding: 11px 10px;
}
.driver-auth-tab.active {
  background: var(--gold);
  color: var(--navy);
  box-shadow: var(--shadow-sm);
}
.driver-auth-panel { display: none; }
.driver-auth-panel.active { display: block; }
.driver-auth-help { color: var(--text-secondary); font-size: 13px; line-height: 1.5; margin: -4px 0 14px; }
.driver-login-mode .driver-register-only { display: none !important; }
.driver-register-only[hidden] { display: none !important; }
.kyc-file-input {
  position: fixed;
  left: -100vw;
  top: 0;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}
@media (max-width: 480px) {
  .driver-header { padding-left: 18px; padding-right: 18px; }
  .driver-content { padding: 22px 18px calc(96px + env(safe-area-inset-bottom, 0px)); }
  .driver-footer { padding-left: 18px; padding-right: 18px; }
  .step-title { font-size: 24px; }
  .step-sub { font-size: 14px; }
}
.btn-back {
  width: 52px;
  height: 52px;
  background: var(--white);
  border: 1.5px solid var(--surface-2);
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  color: var(--text-secondary);
  transition: all 0.2s;
  box-shadow: var(--shadow-sm);
}
.btn-back:hover { border-color: var(--text-secondary); color: var(--text-primary); }

/* Pending screen */
.pending-screen { text-align: center; padding: 48px 24px; }
.pending-icon {
  width: 110px;
  height: 110px;
  border-radius: 50%;
  background: rgba(245,200,66,0.08);
  border: 2px solid rgba(245,200,66,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 28px;
  animation: pending-pulse 2.5s ease-in-out infinite;
}
@keyframes pending-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(245,200,66,0.3); }
  50% { box-shadow: 0 0 0 24px rgba(245,200,66,0); }
}
.pending-icon svg { width: 48px; height: 48px; color: var(--gold-dark); }
.pending-screen h3 { font-size: 24px; color: var(--text-primary); margin-bottom: 14px; }
.pending-screen p { font-size: 15px; color: var(--text-secondary); line-height: 1.75; }
.pending-app-id {
  margin-top: 28px;
  padding: 20px 24px;
  background: var(--white);
  border-radius: var(--radius);
  border: 1.5px solid var(--surface-2);
  text-align: left;
  box-shadow: var(--shadow-sm);
}
.pending-app-id .label { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 6px; }
.pending-app-id .id { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; color: var(--text-primary); }
.pending-steps { margin-top: 20px; display: flex; flex-direction: column; gap: 10px; text-align: left; }
.pending-step-item {
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--white);
  border-radius: var(--radius-sm);
  padding: 12px 16px;
  border: 1.5px solid var(--surface-2);
}
.pending-step-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.pending-step-dot.done { background: var(--success); }
.pending-step-dot.pending { background: var(--gold); animation: blink 1.2s ease-in-out infinite; }
.pending-step-dot.waiting { background: var(--surface-2); }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
.pending-step-text { font-size: 13px; color: var(--text-secondary); }
.pending-step-text strong { color: var(--text-primary); display: block; font-size: 14px; }

/* ===== DRIVER DASHBOARD ===== */
#screen-driver-dash { background: var(--surface); }

/* Dashboard sidebar (desktop) */
.dash-sidebar {
  display: none;
  width: 72px;
  background: var(--navy);
  flex-direction: column;
  align-items: center;
  padding: 24px 0;
  gap: 8px;
  border-right: 1px solid rgba(255,255,255,0.06);
  position: relative;
  z-index: 2;
}
@media (min-width: 768px) {
  .dash-sidebar { display: flex; }
}
.dash-sidebar-icon {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--slate);
  transition: all 0.2s;
}
.dash-sidebar-icon:hover { background: rgba(255,255,255,0.06); color: var(--white); }
.dash-sidebar-icon.active { background: rgba(245,200,66,0.12); color: var(--gold); }
.dash-sidebar-logo {
  font-family: 'Syne', sans-serif;
  font-size: 11px;
  font-weight: 800;
  color: var(--gold);
  letter-spacing: 1px;
  text-transform: uppercase;
  writing-mode: vertical-lr;
  margin-bottom: 16px;
}

/* Main dash area */
.dash-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

/* Dashboard scroll container */
.dash-content-scroll {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}
.dash-content-scroll::-webkit-scrollbar { display: none; }

/* Clean, minimal Top Bar specifically inside Home Tab */
.home-top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}
.dash-top-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.dash-avatar-img {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  object-fit: cover;
  box-shadow: 0 4px 14px rgba(11,22,40,0.08);
  border: 2px solid var(--white);
}
.dash-greeting { font-size: 13px; color: var(--text-muted); margin-bottom: 2px; }
.dash-name { font-size: 18px; font-family: 'Syne', sans-serif; font-weight: 700; color: var(--text-primary); }

.online-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 30px;
  font-family: 'Syne', sans-serif;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s;
  background: var(--white);
  box-shadow: var(--shadow-sm);
}
.online-pill.online {
  color: var(--success);
  border: 1.5px solid rgba(34,196,122,0.3);
}
.online-pill.offline {
  color: var(--text-muted);
  border: 1.5px solid var(--surface-2);
}
.online-status-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  display: block;
}
.online-pill.online .online-status-dot {
  background: var(--success);
  box-shadow: 0 0 0 3px rgba(34,196,122,0.2);
  animation: pulse-dot 2s ease-in-out infinite;
}
.online-pill.offline .online-status-dot {
  background: var(--slate-light);
}

/* Dashboard body */
.driver-dash-body {
  flex: 1;
  padding: 24px;
  padding-top: max(24px, env(safe-area-inset-top, 20px));
  color: var(--text-primary);
  padding-bottom: max(80px, env(safe-area-inset-bottom, 70px));
}
.driver-dash-body::-webkit-scrollbar { display: none; }

/* ===== BOTTOM NAV ===== */
.bottom-nav {
  position: sticky;
  bottom: 0;
  background: var(--white);
  border-top: 1px solid var(--surface-2);
  padding: 10px 8px;
  padding-bottom: max(10px, env(safe-area-inset-bottom));
  display: flex;
  align-items: center;
  justify-content: space-around;
  box-shadow: 0 -4px 20px rgba(11,22,40,0.1);
  flex-shrink: 0;
  z-index: 10;
}
@media (min-width: 768px) {
  .bottom-nav { display: none; }
}
.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  cursor: pointer;
  padding: 6px 16px;
  border-radius: 12px;
  transition: background 0.2s;
  min-width: 56px;
}
.nav-item:hover { background: var(--surface); }
.nav-item.active .nav-icon { color: var(--navy); }
.nav-item.active .nav-label { color: var(--navy); font-weight: 700; }
.nav-icon {
  width: 22px; height: 22px;
  color: var(--text-muted);
  transition: color 0.2s, transform 0.2s;
}
.nav-item.active .nav-icon { transform: translateY(-1px); }
.nav-label { font-size: 10px; font-weight: 500; color: var(--text-muted); transition: color 0.2s; letter-spacing: 0.2px; }
.nav-badge {
  position: absolute;
  top: -4px; right: -4px;
  background: var(--danger);
  color: white;
  font-size: 9px;
  font-weight: 700;
  width: 16px; height: 16px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--white);
}
.nav-icon-wrap { position: relative; }

/* ===== DASHBOARD TAB PANELS ===== */
.dash-panel { display: none; }
.dash-panel.active { display: block; }

/* ===== CARDS ===== */
.card {
  background: var(--white);
  border-radius: var(--radius-lg);
  padding: 22px;
  margin-bottom: 16px;
  border: 1.5px solid var(--surface-2);
  box-shadow: var(--shadow-sm);
}
.card-title {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: var(--text-muted);
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.card-title-action {
  font-size: 12px;
  font-weight: 500;
  color: var(--gold-dark);
  text-transform: none;
  letter-spacing: 0;
  cursor: pointer;
}

/* Trip request card */
.trip-request-card {
  background: linear-gradient(145deg, var(--navy) 0%, var(--navy-mid) 100%);
  border-radius: var(--radius-lg);
  padding: 22px;
  margin-bottom: 16px;
  color: var(--white);
  border: 1px solid rgba(255,255,255,0.07);
  box-shadow: var(--shadow-md);
  position: relative;
  overflow: hidden;
}
.trip-request-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--gold-dark), var(--gold));
}
.trq-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.trq-tag {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--gold);
  background: rgba(245,200,66,0.1);
  border: 1px solid rgba(245,200,66,0.2);
  padding: 5px 12px;
  border-radius: 20px;
}
.trq-timer-wrap { display: flex; align-items: center; gap: 6px; }
.trq-timer {
  font-family: 'Syne', sans-serif;
  font-size: 22px;
  font-weight: 800;
  color: var(--white);
  min-width: 28px;
  text-align: right;
}
.trq-timer.urgent { color: var(--danger); animation: blink 0.8s ease-in-out infinite; }
.trq-timer-label { font-size: 11px; color: var(--slate-light); }
.trq-fee {
  font-family: 'Syne', sans-serif;
  font-size: 34px;
  font-weight: 800;
  color: var(--gold);
  margin-bottom: 18px;
  line-height: 1;
}
.trq-fee span { font-size: 15px; font-weight: 400; color: var(--slate-light); margin-left: 6px; }
.trq-meta {
  display: flex;
  gap: 10px;
  margin-bottom: 16px;
}
.trq-meta-chip {
  display: flex;
  align-items: center;
  gap: 5px;
  background: rgba(255,255,255,0.06);
  border-radius: 20px;
  padding: 5px 12px;
  font-size: 12px;
  color: var(--slate-light);
}
.trq-meta-chip svg { width: 12px; height: 12px; }
.trq-route {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.07);
  border-radius: var(--radius);
  padding: 16px;
  margin-bottom: 18px;
  position: relative;
}
.trq-route-line {
  position: absolute;
  left: 26px; top: 34px; bottom: 34px;
  width: 1px;
  background: linear-gradient(to bottom, var(--info), var(--gold));
  opacity: 0.4;
}
.trq-point { display: flex; align-items: center; gap: 12px; font-size: 14px; color: var(--slate-light); }
.trq-point + .trq-point { margin-top: 14px; }
.trq-dot {
  width: 10px; height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
  border: 2px solid transparent;
}
.trq-dot.from { background: var(--info); border-color: rgba(74,158,255,0.3); }
.trq-dot.to { background: var(--gold); border-color: rgba(245,200,66,0.3); }
.trq-point-addr { flex: 1; font-size: 14px; color: var(--white); }
.trq-actions { display: flex; gap: 10px; }
.trq-decline {
  flex: 0 0 52px;
  height: 52px;
  background: rgba(232,72,74,0.1);
  border: 1px solid rgba(232,72,74,0.2);
  border-radius: var(--radius);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--danger);
  transition: all 0.2s;
}
.trq-decline:hover { background: rgba(232,72,74,0.2); }
.trq-accept {
  flex: 1;
  height: 52px;
  background: linear-gradient(135deg, var(--gold), var(--gold-dark));
  border: none;
  border-radius: var(--radius);
  cursor: pointer;
  font-family: 'Syne', sans-serif;
  font-size: 16px;
  font-weight: 700;
  color: var(--navy);
  transition: all 0.2s;
  box-shadow: 0 4px 16px rgba(245,200,66,0.25);
}
.trq-accept:hover { filter: brightness(1.05); box-shadow: 0 6px 20px rgba(245,200,66,0.35); }

/* Earnings */
.earnings-card {
  background: var(--white);
  border-radius: var(--radius-lg);
  padding: 24px;
  margin-bottom: 16px;
  border: 1.5px solid var(--surface-2);
  box-shadow: var(--shadow-sm);
}
.earnings-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
.earnings-label { font-size: 12px; font-weight: 700; letter-spacing: 0.7px; text-transform: uppercase; color: var(--text-muted); }
.earnings-period {
  font-size: 11px;
  color: var(--text-muted);
  background: var(--surface);
  padding: 4px 10px;
  border-radius: 20px;
  border: 1px solid var(--surface-2);
}
.earnings-amount {
  font-family: 'Syne', sans-serif;
  font-size: 44px;
  font-weight: 800;
  color: var(--text-primary);
  line-height: 1.1;
  margin: 8px 0 18px;
}
.earnings-amount span { font-size: 22px; color: var(--text-muted); }
.earnings-row { display: flex; gap: 10px; }
.earnings-sub {
  flex: 1;
  background: var(--surface);
  border-radius: var(--radius-sm);
  padding: 12px 14px;
  border: 1px solid var(--surface-2);
}
.earnings-sub-label { font-size: 11px; color: var(--text-muted); font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 5px; }
.earnings-sub-val {
  font-family: 'Syne', sans-serif;
  font-size: 18px;
  font-weight: 700;
  color: var(--text-primary);
}
.earnings-sub-val.up { color: var(--success); }

/* Campaign card */
.campaign-card {
  background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
  border-radius: var(--radius);
  padding: 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  border: 1px solid rgba(255,255,255,0.07);
  box-shadow: var(--shadow-md);
  cursor: pointer;
  transition: transform 0.2s;
}
.campaign-card:hover { transform: translateY(-1px); }
.campaign-badge {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 5px;
}
.campaign-text { font-size: 14px; color: var(--white); line-height: 1.4; max-width: 200px; }
.campaign-progress-wrap {
  background: rgba(245,200,66,0.1);
  border: 1px solid rgba(245,200,66,0.2);
  border-radius: var(--radius-sm);
  padding: 12px 18px;
  text-align: center;
  flex-shrink: 0;
}
.campaign-progress-num {
  font-family: 'Syne', sans-serif;
  font-size: 22px;
  font-weight: 800;
  color: var(--gold);
}
.campaign-progress-label { font-size: 10px; color: var(--slate); }

/* Trips panel */
.trip-history-item {
  background: var(--white);
  border-radius: var(--radius);
  padding: 16px;
  margin-bottom: 10px;
  border: 1.5px solid var(--surface-2);
  display: flex;
  align-items: center;
  gap: 14px;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: var(--shadow-sm);
}
.trip-history-item:hover { transform: translateX(2px); border-color: var(--slate-light); }
.trip-icon {
  width: 44px; height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.trip-icon.completed { background: var(--success-pale); color: var(--success); }
.trip-icon.cancelled { background: rgba(232,72,74,0.08); color: var(--danger); }
.trip-details { flex: 1; min-width: 0; }
.trip-route { font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.trip-meta { font-size: 12px; color: var(--text-muted); }
.trip-amount { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 700; color: var(--text-primary); text-align: right; }
.trip-status { font-size: 11px; text-align: right; margin-top: 3px; }
.trip-status.completed { color: var(--success); }
.trip-status.cancelled { color: var(--danger); }

/* Earnings full panel */
.tax-portal-card {
  background: var(--white);
  border-radius: var(--radius);
  padding: 16px;
  margin-bottom: 10px;
  border: 1.5px solid var(--surface-2);
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: var(--shadow-sm);
  cursor: pointer;
  transition: all 0.2s;
}
.tax-portal-card:hover { transform: translateX(2px); }
.tax-card-icon {
  width: 40px; height: 40px;
  border-radius: 10px;
  background: var(--gold-pale);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--gold-dark);
  flex-shrink: 0;
}
.tax-card-info { flex: 1; padding: 0 12px; }
.tax-card-title { font-size: 14px; font-weight: 600; color: var(--text-primary); }
.tax-card-sub { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.btn-download {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: var(--info);
  background: var(--info-pale);
  padding: 7px 12px;
  border-radius: 8px;
  cursor: pointer;
  border: none;
  font-family: 'DM Sans', sans-serif;
  transition: all 0.2s;
  flex-shrink: 0;
}
.btn-download:hover { background: rgba(74,158,255,0.15); }

/* Weekly chart */
.week-chart { display: flex; align-items: flex-end; gap: 8px; height: 80px; margin-bottom: 8px; }
.week-bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; }
.week-bar {
  width: 100%;
  border-radius: 6px 6px 0 0;
  background: var(--surface-2);
  transition: height 0.5s cubic-bezier(0.4,0,0.2,1);
  min-height: 6px;
}
.week-bar.active { background: linear-gradient(to top, var(--gold-dark), var(--gold)); }
.week-day { font-size: 10px; color: var(--text-muted); font-weight: 600; }

/* Help panel */
.help-category {
  background: var(--white);
  border-radius: var(--radius);
  padding: 16px;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 14px;
  border: 1.5px solid var(--surface-2);
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: var(--shadow-sm);
}
.help-category:hover { transform: translateX(2px); border-color: var(--slate-light); }
.help-cat-icon {
  width: 42px; height: 42px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.help-cat-text { flex: 1; }
.help-cat-title { font-size: 14px; font-weight: 600; color: var(--text-primary); }
.help-cat-sub { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

/* Profile panel */
.profile-header {
  background: linear-gradient(145deg, var(--navy), var(--navy-mid));
  border-radius: var(--radius-lg);
  padding: 28px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  margin-bottom: 16px;
  border: 1px solid rgba(255,255,255,0.07);
  box-shadow: var(--shadow-md);
}
.profile-avatar-lg {
  width: 72px; height: 72px;
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 14px;
  box-shadow: 0 6px 20px rgba(11,22,40,0.35);
  overflow: hidden;
  border: 2px solid var(--gold);
}
.profile-avatar-lg img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.profile-name { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 700; color: var(--white); margin-bottom: 4px; }
.profile-rating { display: flex; align-items: center; gap: 6px; font-size: 14px; color: var(--slate-light); }
.profile-star { color: var(--gold); }
.profile-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--white);
  border-radius: var(--radius-sm);
  padding: 14px 16px;
  margin-bottom: 8px;
  border: 1.5px solid var(--surface-2);
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: var(--shadow-sm);
}
.profile-row:hover { transform: translateX(2px); }
.profile-row-left { display: flex; align-items: center; gap: 12px; }
.profile-row-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.profile-row-label { font-size: 14px; font-weight: 500; color: var(--text-primary); }
.profile-row-sub { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.profile-row svg.chevron { width: 16px; height: 16px; color: var(--text-muted); }

/* ===== TOAST ===== */
.toast {
  position: fixed;
  bottom: 90px;
  left: 50%;
  transform: translateX(-50%) translateY(20px);
  background: var(--navy);
  color: var(--white);
  padding: 12px 24px;
  border-radius: 30px;
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
  z-index: 9999;
  box-shadow: 0 4px 24px rgba(11,22,40,0.35);
  border: 1px solid rgba(255,255,255,0.07);
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

.hidden { display: none !important; }
svg { display: block; }

/* Stat row */
.stat-row { display: flex; gap: 10px; margin-bottom: 16px; }
.stat-chip {
  flex: 1;
  background: var(--white);
  border-radius: var(--radius);
  padding: 14px;
  border: 1.5px solid var(--surface-2);
  text-align: center;
  box-shadow: var(--shadow-sm);
}
.stat-chip-val { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; color: var(--text-primary); }
.stat-chip-label { font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; }

/* Section header */
.section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.section-head-title { font-size: 16px; font-weight: 700; color: var(--text-primary); font-family: 'Syne', sans-serif; }
.section-head-link { font-size: 13px; color: var(--gold-dark); font-weight: 600; cursor: pointer; }
</style>
</head>
<body>
<div id="app">

  <!-- ===== DRIVER ONBOARDING ===== -->
  <div class="screen <?php echo $driver_initial_context['is_approved'] ? '' : 'active'; ?>" id="screen-driver">
    <div class="driver-header" id="driverHeaderWrap">
      <div>
        <div style="font-size:11px;color:var(--slate);letter-spacing:1.2px;text-transform:uppercase;margin-bottom:4px;position:relative;z-index:1">Driver Registration</div>
        <h2 id="driverStepTitle">Account Setup</h2>
      </div>
      <div class="step-indicator">Step <strong id="driverStepNum">1</strong>&nbsp;/ 5</div>
    </div>
    <div class="progress-bar-wrap" id="driverProgressWrap">
      <div class="progress-bar-track">
        <div class="progress-bar-fill" id="driverProgress" style="width:20%"></div>
      </div>
    </div>

    <div class="driver-content" id="driverContent">

      <!-- Step 1: Account Initialization -->
      <div class="driver-step active" id="dstep-1">
        <h3 class="step-title">Let's get you started</h3>
        <p class="step-sub">Select your language and fill in your basic personal information</p>
        <div class="form-group driver-register-only">
          <label class="form-label">Preferred Language</label>
          <select class="form-input" id="driverLanguage">
            <option>English</option>
            <option>Hausa</option>
            <option>Yoruba</option>
            <option>Igbo</option>
            <option>Pidgin</option>
          </select>
        </div>

        <div class="driver-auth-card">
          <div class="driver-auth-tabs" role="tablist" aria-label="Driver account access">
            <button class="driver-auth-tab active" id="driverSignupTab" type="button" role="tab" aria-selected="true" aria-controls="driverSignupPanel" onclick="setDriverAuthMode('signup')">Register</button>
            <button class="driver-auth-tab" id="driverLoginTab" type="button" role="tab" aria-selected="false" aria-controls="driverLoginPanel" onclick="setDriverAuthMode('login')">Sign in</button>
          </div>

          <div class="driver-auth-panel active" id="driverSignupPanel" role="tabpanel" aria-labelledby="driverSignupTab">
            <p class="driver-auth-help">Create your driver account first. The Continue button will save it before moving to verification.</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div class="form-group">
                <label class="form-label">First Name</label>
                <input class="form-input" type="text" id="driverFirstName" placeholder="First" autocomplete="given-name">
              </div>
              <div class="form-group">
                <label class="form-label">Last Name</label>
                <input class="form-input" type="text" id="driverLastName" placeholder="Last" autocomplete="family-name">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input class="form-input" type="email" id="driverEmail" placeholder="you@example.com" autocomplete="email">
            </div>
            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input class="form-input" type="tel" id="driverPhone" placeholder="08012345678" autocomplete="tel">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Password</label>
              <input class="form-input" type="password" id="driverPassword" placeholder="Min. 6 characters" autocomplete="new-password">
            </div>
          </div>

          <div class="driver-auth-panel" id="driverLoginPanel" role="tabpanel" aria-labelledby="driverLoginTab">
            <p class="driver-auth-help">Already registered? Sign in with your phone/email and password to open your driver dashboard.</p>
            <div class="form-group"><label class="form-label">Phone or Email</label><input class="form-input" type="text" id="driverLoginPhone" placeholder="Phone or email" autocomplete="username"></div>
            <div class="form-group"><label class="form-label">Password</label><input class="form-input" type="password" id="driverLoginPassword" placeholder="Password" autocomplete="current-password"></div>
            <button class="global-btn ghost" type="button" style="width:100%;justify-content:center" onclick="driverLogin()">Sign in as Driver</button>
          </div>
        </div>

        <div class="driver-register-only" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Middle Name</label>
            <input class="form-input" type="text" id="driverMiddleName" placeholder="Middle">
          </div>
          <div class="form-group">
            <label class="form-label">Date of Birth</label>
            <input class="form-input" type="date" id="driverDob">
          </div>
        </div>
        <div class="form-group driver-register-only">
          <label class="form-label">Gender</label>
          <select class="form-input" id="driverGender">
            <option>Male</option>
            <option>Female</option>
            <option>Prefer not to say</option>
          </select>
        </div>
        <div class="form-group driver-register-only">
          <label class="form-label">State of Origin</label>
          <select class="form-input" id="driverStateOrigin">
            <option>Abia</option><option>Adamawa</option><option>Akwa Ibom</option><option>Anambra</option>
            <option>Bauchi</option><option>Bayelsa</option><option>Benue</option><option>Borno</option>
            <option>Cross River</option><option>Delta</option><option>Ebonyi</option><option>Edo</option>
            <option>Ekiti</option><option>Enugu</option><option>Gombe</option><option>Imo</option>
            <option>Jigawa</option><option>Kaduna</option><option>Kano</option><option>Katsina</option>
            <option>Kebbi</option><option>Kogi</option><option>Kwara</option><option>Lagos</option>
            <option>Nasarawa</option><option>Niger</option><option>Ogun</option><option>Ondo</option>
            <option>Osun</option><option>Oyo</option><option>Plateau</option><option>Rivers</option>
            <option>Sokoto</option><option>Taraba</option><option>Yobe</option><option>Zamfara</option>
            <option>Abuja FCT</option>
          </select>
        </div>
        <span class="section-label driver-register-only" style="margin-top:8px">Select your vehicle class</span>
        <div class="vehicle-grid driver-register-only" id="vg1">
          <div class="vehicle-card active" data-value="bike" onclick="selVehicle(this, 'vg1')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="5.5" cy="17.5" r="2.5"/><circle cx="17" cy="17.5" r="2.5"/><path d="M5.5 17.5h11.5M8 9l-2.5 8.5M12 5l4 12.5M12 5H8l-2.5 4h9l-2.5-4z"/></svg>
            <span>Motorbike</span>
          </div>
          <div class="vehicle-card" data-value="car" onclick="selVehicle(this, 'vg1')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="6" width="15" height="10" rx="2"/><polygon points="16 9 20 9 23 13 23 16 16 16 16 9"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="18.5" cy="18.5" r="2"/></svg>
            <span>Car</span>
          </div>
          <div class="vehicle-card" data-value="keke" onclick="selVehicle(this, 'vg1')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3m0 0h4l2 5v4h-6"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
            <span>Tricycle</span>
          </div>
          <div class="vehicle-card" data-value="van" onclick="selVehicle(this, 'vg1')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="20" height="14" rx="2"/><circle cx="5.5" cy="20" r="2"/><circle cx="18.5" cy="20" r="2"/><line x1="1" y1="12" x2="21" y2="12"/></svg>
            <span>Van</span>
          </div>
        </div>
      </div>

      <!-- Step 2: Identity Verification -->
      <div class="driver-step" id="dstep-2">
        <h3 class="step-title">Identity verification</h3>
        <p class="step-sub">Your documents are encrypted and stored securely. This step is confidential.</p>
        <div class="info-note">
          <strong>🔒 Strictly confidential</strong><br>
          This information is used only for background verification and will never be shared with customers.
        </div>
        <div class="upload-box" data-field="id_front" onclick="chooseKycFile(this)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
          <p><strong>Driver's License</strong></p>
          <small>Tap to upload · JPG, PNG or PDF</small>
        </div>
        <div class="upload-box" data-field="id_back" onclick="chooseKycFile(this)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-6-4L3 20"/></svg>
          <p><strong>National ID Card / NIN Slip</strong></p>
          <small>Tap to upload · JPG or PNG</small>
        </div>
        <div class="upload-box" data-field="selfie" onclick="chooseKycFile(this)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <p><strong>Profile Photo</strong></p>
          <small>Clear portrait · Full face · No caps or glasses</small>
        </div>
        <div class="info-note gold">
          📷 Photo requirements: Clear face, neutral background, eyes open, no headwear or sunglasses.
        </div>
      </div>

      <!-- Step 3: Vehicle Information -->
      <div class="driver-step" id="dstep-3">
        <h3 class="step-title">Vehicle information</h3>
        <p class="step-sub">Tell us about the vehicle you'll be using for deliveries</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Year</label>
            <input class="form-input" type="number" id="driverVehicleYear" placeholder="2020">
          </div>
          <div class="form-group">
            <label class="form-label">Manufacturer</label>
            <input class="form-input" type="text" id="driverVehicleManufacturer" placeholder="Toyota">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">License Plate Number</label>
          <input class="form-input" type="text" id="driverVehiclePlate" placeholder="e.g. ABC 123 DE" style="text-transform:uppercase">
        </div>
        <div class="form-group">
          <label class="form-label">Vehicle Color</label>
          <input class="form-input" type="text" id="driverVehicleColor" placeholder="e.g. Red">
        </div>
        <span class="section-label" style="margin-top:4px">Vehicle photos (high quality, all angles)</span>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
          <div class="upload-box" data-field="vehicle_photo" style="padding:22px 12px;margin-bottom:0" onclick="chooseKycFile(this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-6-6L3 21"/></svg>
            <p style="font-size:12px;margin-top:6px">Exterior</p>
          </div>
          <div class="upload-box" data-field="vehicle_interior_photo" style="padding:22px 12px;margin-bottom:0" onclick="chooseKycFile(this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-6-6L3 21"/></svg>
            <p style="font-size:12px;margin-top:6px">Interior</p>
          </div>
          <div class="upload-box" data-field="vehicle_front_photo" style="padding:22px 12px;margin-bottom:0" onclick="chooseKycFile(this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-6-6L3 21"/></svg>
            <p style="font-size:12px;margin-top:6px">Front angle</p>
          </div>
          <div class="upload-box" data-field="vehicle_rear_photo" style="padding:22px 12px;margin-bottom:0" onclick="chooseKycFile(this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-6-6L3 21"/></svg>
            <p style="font-size:12px;margin-top:6px">Rear angle</p>
          </div>
        </div>
        <span class="section-label">Vehicle documents</span>
        <div class="upload-box" data-field="vehicle_license_doc" onclick="chooseKycFile(this)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <p><strong>Vehicle License Certificate</strong></p>
          <small>Upload valid document</small>
        </div>
        <div class="upload-box" data-field="insurance_doc" onclick="chooseKycFile(this)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <p><strong>Vehicle Inspection Report</strong></p>
          <small>Recent inspection certificate</small>
        </div>
      </div>

      <!-- Step 4: Financial & Emergency -->
      <div class="driver-step" id="dstep-4">
        <h3 class="step-title">Financial & emergency info</h3>
        <p class="step-sub">For payouts and your safety record</p>
        <span class="section-label">Billing type</span>
        <div style="display:flex;gap:10px;margin-bottom:22px" id="billingGroup">
          <div class="vehicle-card active" onclick="selVehicle(this, 'billingGroup')" style="flex:1;flex-direction:row;justify-content:flex-start;padding:14px 16px;gap:10px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>Personal</span>
          </div>
          <div class="vehicle-card" onclick="selVehicle(this, 'billingGroup')" style="flex:1;flex-direction:row;justify-content:flex-start;padding:14px 16px;gap:10px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            <span>Company</span>
          </div>
        </div>
        <span class="section-label">Bank details</span>
        <div class="form-group">
          <label class="form-label">Account Holder Name</label>
          <input class="form-input" type="text" id="driverAccountHolder" placeholder="As on bank records">
        </div>
        <div class="form-group">
          <label class="form-label">Account Number</label>
          <input class="form-input" type="number" id="driverAccountNumber" placeholder="0123456789" maxlength="10">
        </div>
        <div class="form-group">
          <label class="form-label">Bank Name</label>
          <select class="form-input" id="driverBankName">
            <option>Access Bank</option>
            <option>GTBank</option>
            <option>UBA</option>
            <option>Zenith Bank</option>
            <option>First Bank</option>
            <option>Kuda Bank</option>
            <option>OPay</option>
            <option>Palmpay</option>
            <option>Wema Bank</option>
            <option>Polaris Bank</option>
          </select>
        </div>
        <span class="section-label" style="margin-top:4px">Emergency contact</span>
        <div class="form-group">
          <label class="form-label">Next of Kin Full Name</label>
          <input class="form-input" type="text" id="driverEmergencyName" placeholder="Full name">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Relationship</label>
            <select class="form-input" id="driverEmergencyRelationship">
              <option>Parent</option>
              <option>Spouse</option>
              <option>Sibling</option>
              <option>Child</option>
              <option>Friend</option>
              <option>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input class="form-input" type="tel" id="driverEmergencyPhone" placeholder="+234...">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <input class="form-input" type="text" id="driverEmergencyAddress" placeholder="Next of kin address">
        </div>
      </div>

      <!-- Step 5: Pending -->
      <div class="driver-step" id="dstep-5">
        <div class="pending-screen">
          <div class="pending-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <h3>Application Under Review</h3>
          <p>We're verifying your documents and details. This usually takes <strong>24–48 hours.</strong><br><br>You'll receive a push notification once your account is approved.</p>
          <div class="pending-app-id">
            <div class="label">Application Reference</div>
            <div class="id">#DRV-00412</div>
          </div>
          <div class="pending-steps">
            <div class="pending-step-item">
              <div class="pending-step-dot done"></div>
              <div class="pending-step-text"><strong>Documents Submitted</strong>All files uploaded successfully</div>
            </div>
            <div class="pending-step-item">
              <div class="pending-step-dot pending"></div>
              <div class="pending-step-text"><strong>Identity Verification</strong>Checking NIN & Driver's License</div>
            </div>
            <div class="pending-step-item">
              <div class="pending-step-dot waiting"></div>
              <div class="pending-step-text"><strong>Vehicle Inspection</strong>Awaiting document review</div>
            </div>
            <div class="pending-step-item">
              <div class="pending-step-dot waiting"></div>
              <div class="pending-step-text"><strong>Account Activation</strong>Final approval & onboarding</div>
            </div>
          </div>
          <button class="global-btn" style="margin-top:28px;width:100%;justify-content:center" onclick="goToDashboard()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Open Driver Dashboard
          </button>
        </div>
      </div>
    </div>

    <div class="driver-footer" id="driverFooterWrap">
      <button class="btn-back" id="driverBack" onclick="driverPrev()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      </button>
      <button class="btn-primary" id="driverNext" onclick="driverNext()" style="flex:1">
        Continue
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </button>
    </div>
  </div>

  <!-- ===== DRIVER DASHBOARD ===== -->
  <div class="screen <?php echo $driver_initial_context['is_approved'] ? 'active' : ''; ?>" id="screen-driver-dash">

    <!-- Sidebar (desktop only) -->
    <div class="dash-sidebar">
      <div style="font-family:'Syne',sans-serif;font-size:13px;font-weight:800;color:var(--gold);margin-bottom:20px;letter-spacing:0.5px">SD</div>
      <div class="dash-sidebar-icon active" onclick="switchTab('home')" title="Home">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </div>
      <div class="dash-sidebar-icon" onclick="switchTab('earnings')" title="Earnings">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      </div>
      <div class="dash-sidebar-icon" onclick="switchTab('trips')" title="Trips">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M9 20l-5.447-2.724A1 1 0 0 1 3 16.382V5.618a1 1 0 0 1 1.447-.894L9 7M9 20l6-3M9 20V7m6 13l4.553 2.276A1 1 0 0 0 21 21.382V10.618a1 1 0 0 0-.553-.894L15 7m0 13V7M9 7l6-3"/></svg>
      </div>
      <div class="dash-sidebar-icon" onclick="switchTab('help')" title="Help">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div style="flex:1"></div>
      <div class="dash-sidebar-icon" onclick="switchTab('profile')" title="Profile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
    </div>

    <!-- Main content -->
    <div class="dash-main">
      <div class="dash-content-scroll" id="dashBody">

        <div class="driver-dash-body">

          <!-- HOME TAB -->
          <div class="dash-panel active" id="panel-home">

            <!-- Clean, minimal Top Bar restricted to Home Tab -->
            <div class="home-top-bar">
              <div class="dash-top-left">
                <img class="dash-avatar-img" src="https://app.oaglobalstandardservice.com/wp/wp-content/uploads/2026/04/1725853367655.jpg" alt="Profile">
                <div>
                  <div class="dash-greeting" id="dashGreeting">Good afternoon,</div>
                  <div class="dash-name">Chidi Nwosu 👋</div>
                </div>
              </div>
              <div class="online-pill online" id="onlineToggle" onclick="toggleOnline()">
                <span class="online-status-dot"></span>
                <span id="onlineLabel">Online</span>
              </div>
            </div>

            <!-- Incoming request -->
            <div class="trip-request-card">
              <div class="trq-header">
                <div class="trq-tag">New Request</div>
                <div class="trq-timer-wrap">
                  <div class="trq-timer" id="trqTimer">14</div>
                  <div class="trq-timer-label">sec</div>
                </div>
              </div>
              <div class="trq-fee">₦3,400 <span>· 5.2 km away</span></div>
              <div class="trq-meta">
                <div class="trq-meta-chip">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  ~12 mins
                </div>
                <div class="trq-meta-chip">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                  Package
                </div>
                <div class="trq-meta-chip">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                  Safe Send
                </div>
              </div>
              <div class="trq-route">
                <div class="trq-route-line"></div>
                <div class="trq-point">
                  <div class="trq-dot from"></div>
                  <div>
                    <div style="font-size:11px;color:var(--slate-light);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:2px">Pickup</div>
                    <div class="trq-point-addr">Eagle Island, Port Harcourt</div>
                  </div>
                </div>
                <div class="trq-point">
                  <div class="trq-dot to"></div>
                  <div>
                    <div style="font-size:11px;color:var(--slate-light);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:2px">Drop-off</div>
                    <div class="trq-point-addr">Rumuola Road Junction</div>
                  </div>
                </div>
              </div>
              <div class="trq-actions">
                <button class="trq-decline" onclick="showToast('Request declined')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                <button class="trq-accept" onclick="showToast('✓ Delivery accepted! Navigating to pickup...')">Accept Delivery</button>
              </div>
            </div>

            <!-- Quick stats -->
            <div class="stat-row">
              <div class="stat-chip">
                <div class="stat-chip-val">₦8,200</div>
                <div class="stat-chip-label">Today</div>
              </div>
              <div class="stat-chip">
                <div class="stat-chip-val">6</div>
                <div class="stat-chip-label">Trips</div>
              </div>
              <div class="stat-chip">
                <div class="stat-chip-val">4.8★</div>
                <div class="stat-chip-label">Rating</div>
              </div>
            </div>

            <!-- Campaign -->
            <div class="campaign-card">
              <div>
                <div class="campaign-badge">⚡ Peak Hour Bonus</div>
                <div class="campaign-text">Complete 3 more trips to unlock a ₦5,000 bonus</div>
              </div>
              <div class="campaign-progress-wrap">
                <div class="campaign-progress-num">2/5</div>
                <div class="campaign-progress-label">done</div>
              </div>
            </div>

            <!-- Map placeholder -->
            <div style="background:linear-gradient(145deg,var(--navy-light),var(--navy));border-radius:var(--radius-lg);padding:28px;text-align:center;border:1px solid rgba(255,255,255,0.07);margin-bottom:16px;box-shadow:var(--shadow-md)">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.5" width="36" height="36" style="margin:0 auto 12px"><path d="M9 20l-5.447-2.724A1 1 0 0 1 3 16.382V5.618a1 1 0 0 1 1.447-.894L9 7M9 20l6-3M9 20V7m6 13l4.553 2.276A1 1 0 0 0 21 21.382V10.618a1 1 0 0 0-.553-.894L15 7m0 13V7M9 7l6-3"/></svg>
              <div style="font-family:'Syne',sans-serif;font-size:15px;color:var(--white);margin-bottom:4px">Live Map Active</div>
              <div style="font-size:13px;color:var(--slate-light)">Port Harcourt, Rivers State</div>
            </div>
          </div>

          <!-- EARNINGS TAB -->
          <div class="dash-panel" id="panel-earnings">
            <div class="earnings-card">
              <div class="earnings-top">
                <div class="earnings-label">This week's earnings</div>
                <div class="earnings-period">Mon – Fri</div>
              </div>
              <div class="earnings-amount"><span>₦</span>47,850</div>
              <div class="earnings-row">
                <div class="earnings-sub">
                  <div class="earnings-sub-label">Today</div>
                  <div class="earnings-sub-val up">₦8,200</div>
                </div>
                <div class="earnings-sub">
                  <div class="earnings-sub-label">Trips</div>
                  <div class="earnings-sub-val">23</div>
                </div>
                <div class="earnings-sub">
                  <div class="earnings-sub-label">Rating</div>
                  <div class="earnings-sub-val">4.8 ★</div>
                </div>
              </div>
            </div>

            <!-- Weekly bar chart -->
            <div class="card">
              <div class="card-title">Weekly breakdown</div>
              <div class="week-chart">
                <div class="week-bar-wrap">
                  <div class="week-bar" style="height:45%"></div>
                  <div class="week-day">Mon</div>
                </div>
                <div class="week-bar-wrap">
                  <div class="week-bar" style="height:65%"></div>
                  <div class="week-day">Tue</div>
                </div>
                <div class="week-bar-wrap">
                  <div class="week-bar" style="height:50%"></div>
                  <div class="week-day">Wed</div>
                </div>
                <div class="week-bar-wrap">
                  <div class="week-bar" style="height:80%"></div>
                  <div class="week-day">Thu</div>
                </div>
                <div class="week-bar-wrap">
                  <div class="week-bar active" style="height:55%"></div>
                  <div class="week-day" style="color:var(--gold-dark);font-weight:700">Fri</div>
                </div>
                <div class="week-bar-wrap">
                  <div class="week-bar" style="height:30%"></div>
                  <div class="week-day">Sat</div>
                </div>
                <div class="week-bar-wrap">
                  <div class="week-bar" style="height:20%"></div>
                  <div class="week-day">Sun</div>
                </div>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted)">
                <span>₦0</span><span>₦15k</span>
              </div>
            </div>

            <!-- Active campaign -->
            <div class="card">
              <div class="card-title">Active Campaigns</div>
              <div class="campaign-card" style="margin-bottom:10px">
                <div>
                  <div class="campaign-badge">⚡ Peak Hour</div>
                  <div class="campaign-text">3 more trips = ₦5,000 bonus</div>
                </div>
                <div class="campaign-progress-wrap">
                  <div class="campaign-progress-num">2/5</div>
                  <div class="campaign-progress-label">done</div>
                </div>
              </div>
              <div class="campaign-card">
                <div>
                  <div class="campaign-badge">🌟 Weekend Star</div>
                  <div class="campaign-text">10 trips this weekend = ₦8,000</div>
                </div>
                <div class="campaign-progress-wrap">
                  <div class="campaign-progress-num">0/10</div>
                  <div class="campaign-progress-label">done</div>
                </div>
              </div>
            </div>

            <!-- Tax portal -->
            <div class="card">
              <div class="card-title">
                Tax Portal
                <span class="card-title-action">Learn more</span>
              </div>
              <div class="tax-portal-card" onclick="showToast('Generating Q1 2025 tax summary...')">
                <div class="tax-card-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <div class="tax-card-info">
                  <div class="tax-card-title">Q1 2025 Summary</div>
                  <div class="tax-card-sub">Jan – Mar 2025 · Ready to download</div>
                </div>
                <button class="btn-download">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  PDF
                </button>
              </div>
              <div class="tax-portal-card" onclick="showToast('Generating Q4 2024 tax summary...')">
                <div class="tax-card-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                </div>
                <div class="tax-card-info">
                  <div class="tax-card-title">Q4 2024 Summary</div>
                  <div class="tax-card-sub">Oct – Dec 2024 · Available</div>
                </div>
                <button class="btn-download">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  PDF
                </button>
              </div>
            </div>
          </div>

          <!-- TRIPS TAB -->
          <div class="dash-panel" id="panel-trips">
            <div class="section-head">
              <div class="section-head-title">Trip History</div>
              <div class="section-head-link">Filter</div>
            </div>

            <!-- Trip items -->
            <div class="trip-history-item" onclick="showToast('Receipt for trip #TRP-00142 opened')">
              <div class="trip-icon completed">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <div class="trip-details">
                <div class="trip-route">Eagle Island → Rumuola Rd</div>
                <div class="trip-meta">Today · 2:14 PM · #TRP-00142</div>
              </div>
              <div>
                <div class="trip-amount">₦3,400</div>
                <div class="trip-status completed">Completed</div>
              </div>
            </div>

            <div class="trip-history-item" onclick="showToast('Receipt for trip #TRP-00141 opened')">
              <div class="trip-icon completed">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <div class="trip-details">
                <div class="trip-route">GRA Phase 2 → Trans Amadi</div>
                <div class="trip-meta">Today · 12:55 PM · #TRP-00141</div>
              </div>
              <div>
                <div class="trip-amount">₦2,100</div>
                <div class="trip-status completed">Completed</div>
              </div>
            </div>

            <div class="trip-history-item" onclick="showToast('Receipt for trip #TRP-00140 opened')">
              <div class="trip-icon cancelled">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </div>
              <div class="trip-details">
                <div class="trip-route">Woji Road → D-Line</div>
                <div class="trip-meta">Today · 11:02 AM · #TRP-00140</div>
              </div>
              <div>
                <div class="trip-amount">₦0</div>
                <div class="trip-status cancelled">Cancelled</div>
              </div>
            </div>

            <div class="trip-history-item" onclick="showToast('Receipt for trip #TRP-00139 opened')">
              <div class="trip-icon completed">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <div class="trip-details">
                <div class="trip-route">Peter Odili Rd → Old GRA</div>
                <div class="trip-meta">Yesterday · 6:45 PM · #TRP-00139</div>
              </div>
              <div>
                <div class="trip-amount">₦4,750</div>
                <div class="trip-status completed">Completed</div>
              </div>
            </div>

            <div class="trip-history-item" onclick="showToast('Receipt for trip #TRP-00138 opened')">
              <div class="trip-icon completed">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <div class="trip-details">
                <div class="trip-route">Rumuokwurushi → Stadium Rd</div>
                <div class="trip-meta">Yesterday · 4:20 PM · #TRP-00138</div>
              </div>
              <div>
                <div class="trip-amount">₦3,200</div>
                <div class="trip-status completed">Completed</div>
              </div>
            </div>
          </div>

          <!-- HELP TAB -->
          <div class="dash-panel" id="panel-help">
            <div class="section-head" style="margin-bottom:16px">
              <div class="section-head-title">Driver Help</div>
            </div>
            <div class="info-note" style="margin-bottom:20px">Select a recent trip below to get targeted support, or browse help topics.</div>

            <div class="card">
              <div class="card-title">Get help for a specific trip</div>
              <div class="trip-history-item" style="margin-bottom:8px" onclick="showToast('Support ticket opened for TRP-00142')">
                <div class="trip-icon completed">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="trip-details">
                  <div class="trip-route">#TRP-00142 · Eagle Island → Rumuola Rd</div>
                  <div class="trip-meta">Today · ₦3,400</div>
                </div>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
              </div>
              <div class="trip-history-item" style="margin-bottom:0" onclick="showToast('Support ticket opened for TRP-00141')">
                <div class="trip-icon completed">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="trip-details">
                  <div class="trip-route">#TRP-00141 · GRA Phase 2 → Trans Amadi</div>
                  <div class="trip-meta">Today · ₦2,100</div>
                </div>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
              </div>
            </div>

            <div class="section-head">
              <div class="section-head-title">Help topics</div>
            </div>
            <div class="help-category" onclick="showToast('Opening: Payments & Earnings help')">
              <div class="help-cat-icon" style="background:rgba(34,196,122,0.1);color:var(--success)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              </div>
              <div class="help-cat-text">
                <div class="help-cat-title">Payments & Earnings</div>
                <div class="help-cat-sub">Payout issues, missing earnings, bank details</div>
              </div>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
            </div>
            <div class="help-category" onclick="showToast('Opening: Trip issues help')">
              <div class="help-cat-icon" style="background:var(--info-pale);color:var(--info)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M9 20l-5.447-2.724A1 1 0 0 1 3 16.382V5.618a1 1 0 0 1 1.447-.894L9 7M9 20l6-3M9 20V7m6 13l4.553 2.276A1 1 0 0 0 21 21.382V10.618a1 1 0 0 0-.553-.894L15 7m0 13V7M9 7l6-3"/></svg>
              </div>
              <div class="help-cat-text">
                <div class="help-cat-title">Trip & Delivery issues</div>
                <div class="help-cat-sub">Wrong route, item damaged, dispute with customer</div>
              </div>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
            </div>
            <div class="help-category" onclick="showToast('Opening: Account & documents help')">
              <div class="help-cat-icon" style="background:var(--gold-pale);color:var(--gold-dark)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </div>
              <div class="help-cat-text">
                <div class="help-cat-title">Account & Documents</div>
                <div class="help-cat-sub">KYC update, vehicle change, profile issues</div>
              </div>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
            </div>
            <div class="help-category" onclick="showToast('Opening: Safety & emergency help')">
              <div class="help-cat-icon" style="background:rgba(232,72,74,0.08);color:var(--danger)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              </div>
              <div class="help-cat-text">
                <div class="help-cat-title">Safety & Emergency</div>
                <div class="help-cat-sub">Report an incident, emergency support</div>
              </div>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
            </div>
          </div>

          <!-- PROFILE TAB -->
          <div class="dash-panel" id="panel-profile">
            <div class="profile-header">
              <div class="profile-avatar-lg">
                <img src="https://app.oaglobalstandardservice.com/wp/wp-content/uploads/2026/04/1725853367655.jpg" alt="Profile Image">
              </div>
              <div class="profile-name">Chidi Nwosu</div>
              <div class="profile-rating">
                <span class="profile-star">★★★★★</span>
                4.8 · 23 trips this week
              </div>
              <div style="margin-top:16px;display:flex;gap:10px">
                <div style="background:rgba(34,196,122,0.15);border:1px solid rgba(34,196,122,0.25);color:var(--success);font-size:12px;font-weight:600;padding:5px 14px;border-radius:20px">✓ Verified</div>
                <div style="background:rgba(245,200,66,0.1);border:1px solid rgba(245,200,66,0.2);color:var(--gold);font-size:12px;font-weight:600;padding:5px 14px;border-radius:20px">Motorbike</div>
              </div>
            </div>

            <div class="profile-row" onclick="showToast('Edit personal info')">
              <div class="profile-row-left">
                <div class="profile-row-icon" style="background:var(--info-pale);color:var(--info)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div>
                  <div class="profile-row-label">Personal Info</div>
                  <div class="profile-row-sub">Name, DOB, State of Origin</div>
                </div>
              </div>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </div>

            <div class="profile-row" onclick="showToast('Edit bank details')">
              <div class="profile-row-left">
                <div class="profile-row-icon" style="background:rgba(34,196,122,0.1);color:var(--success)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                </div>
                <div>
                  <div class="profile-row-label">Bank Details</div>
                  <div class="profile-row-sub">GTBank · ****7890</div>
                </div>
              </div>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </div>

            <div class="profile-row" onclick="showToast('View vehicle documents')">
              <div class="profile-row-left">
                <div class="profile-row-icon" style="background:var(--gold-pale);color:var(--gold-dark)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="1" y="6" width="15" height="10" rx="2"/><polygon points="16 9 20 9 23 13 23 16 16 16 16 9"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="18.5" cy="18.5" r="2"/></svg>
                </div>
                <div>
                  <div class="profile-row-label">Vehicle Documents</div>
                  <div class="profile-row-sub">License, Inspection Report</div>
                </div>
              </div>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </div>

            <div class="profile-row" onclick="showToast('Edit emergency contact')">
              <div class="profile-row-left">
                <div class="profile-row-icon" style="background:rgba(232,72,74,0.08);color:var(--danger)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.37 2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72c.127.96.36 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.16 6.16l.97-.86a2 2 0 0 1 2.11-.45c.907.34 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </div>
                <div>
                  <div class="profile-row-label">Emergency Contact</div>
                  <div class="profile-row-sub">Mama Nwosu · Parent</div>
                </div>
              </div>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </div>

            <div style="margin-top:8px">
              <button class="global-btn ghost" style="width:100%;justify-content:center" onclick="location.reload()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Log Out
              </button>
            </div>
          </div>

        </div><!-- end driver-dash-body -->
      </div><!-- end dashBody scroll wrapper -->

      <!-- BOTTOM NAV (mobile only) -->
      <nav class="bottom-nav">
        <div class="nav-item active" id="nav-home" onclick="switchTab('home')">
          <div class="nav-icon-wrap">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          </div>
          <span class="nav-label">Home</span>
        </div>
        <div class="nav-item" id="nav-earnings" onclick="switchTab('earnings')">
          <div class="nav-icon-wrap">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
          <span class="nav-label">Earnings</span>
        </div>
        <div class="nav-item" id="nav-trips" onclick="switchTab('trips')">
          <div class="nav-icon-wrap">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 20l-5.447-2.724A1 1 0 0 1 3 16.382V5.618a1 1 0 0 1 1.447-.894L9 7M9 20l6-3M9 20V7m6 13l4.553 2.276A1 1 0 0 0 21 21.382V10.618a1 1 0 0 0-.553-.894L15 7m0 13V7M9 7l6-3"/></svg>
          </div>
          <span class="nav-label">Trips</span>
        </div>
        <div class="nav-item" id="nav-help" onclick="switchTab('help')">
          <div class="nav-icon-wrap">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div class="nav-badge">1</div>
          </div>
          <span class="nav-label">Help</span>
        </div>
        <div class="nav-item" id="nav-profile" onclick="switchTab('profile')">
          <div class="nav-icon-wrap">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <span class="nav-label">Profile</span>
        </div>
      </nav>
    </div><!-- end dash-main -->
  </div><!-- end screen-driver-dash -->

  <input class="kyc-file-input" type="file" id="driverKycFileInput" accept="image/jpeg,image/png,application/pdf">

  <!-- TOAST -->
  <div class="toast" id="toast"></div>
</div>

<script>
// ===== ONBOARDING =====
function setAppHeight() {
  document.documentElement.style.setProperty('--app-height', `${window.innerHeight}px`);
}
setAppHeight();
window.addEventListener('resize', setAppHeight);
window.addEventListener('orientationchange', setAppHeight);

const driverInitialContext = <?php echo wp_json_encode( $driver_initial_context ); ?>;
let driverStep = driverInitialContext.logged_in && driverInitialContext.kyc_status === 'under_review' ? 5 : 1;
let driverAuthenticated = !!driverInitialContext.logged_in;
let driverAuthMode = 'signup';
const driverTitles = ['Account Setup','Identity Verification','Vehicle Information','Financial & Emergency','Application Submitted'];
const driverFiles = {};

async function parseDriverJson(response) {
  const rawText = await response.text();
  try {
    return JSON.parse(rawText);
  } catch (err) {
    console.error('Raw driver auth response:', rawText);
    throw new Error('Invalid server response');
  }
}

async function driverAuthPost(body) {
  const response = await fetch(window.location.href, {
    method: 'POST',
    body,
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' }
  });

  return parseDriverJson(response);
}

function syncDriverRegistrationFields() {
  const isLogin = driverAuthMode === 'login';
  document.querySelectorAll('.driver-register-only').forEach(el => {
    if (el.dataset.originalDisplay === undefined) {
      el.dataset.originalDisplay = el.style.display || '';
    }
    el.hidden = isLogin;
    el.style.display = isLogin ? 'none' : el.dataset.originalDisplay;
  });
}

function setDriverAuthMode(mode) {
  driverAuthMode = mode === 'login' ? 'login' : 'signup';
  document.getElementById('driverSignupTab').classList.toggle('active', driverAuthMode === 'signup');
  document.getElementById('driverLoginTab').classList.toggle('active', driverAuthMode === 'login');
  document.getElementById('driverSignupPanel').classList.toggle('active', driverAuthMode === 'signup');
  document.getElementById('driverLoginPanel').classList.toggle('active', driverAuthMode === 'login');
  document.getElementById('driverSignupTab').setAttribute('aria-selected', driverAuthMode === 'signup');
  document.getElementById('driverLoginTab').setAttribute('aria-selected', driverAuthMode === 'login');
  document.getElementById('screen-driver').classList.toggle('driver-login-mode', driverAuthMode === 'login');
  syncDriverRegistrationFields();
  updateDriver();
}

async function driverLogin() {
  const identifier = document.getElementById('driverLoginPhone').value.trim();
  const password = document.getElementById('driverLoginPassword').value;

  if (!identifier || !password) {
    showToast('Enter your phone/email and password.');
    return false;
  }

  const body = new FormData();
  body.append('action', 'login');
  body.append('phone', identifier);
  body.append('password', password);
  body.append('language', document.getElementById('driverLanguage').value);
  body.append('middle_name', document.getElementById('driverMiddleName').value.trim());
  body.append('date_of_birth', document.getElementById('driverDob').value);
  body.append('gender', document.getElementById('driverGender').value);
  body.append('state_of_origin', document.getElementById('driverStateOrigin').value);
  body.append('vehicle_type', getSelectedValue('vg1', 'bike'));

  try {
    const json = await driverAuthPost(body);
    if (json.success) {
      driverAuthenticated = true;
      Object.assign(driverInitialContext, json.data || {}, { logged_in: true });
      showToast(json.data?.first_name ? `Welcome back, ${json.data.first_name} 👋` : 'Welcome back 👋');
      goToDashboard();
      return true;
    }

    showToast(json.data?.message || 'Driver login failed.');
  } catch (err) {
    showToast('Could not reach Idibia right now. Please try again.');
  }

  return false;
}

async function submitDriverSignup() {
  const firstName = document.getElementById('driverFirstName').value.trim();
  const lastName = document.getElementById('driverLastName').value.trim();
  const email = document.getElementById('driverEmail').value.trim();
  const phone = document.getElementById('driverPhone').value.trim();
  const password = document.getElementById('driverPassword').value;

  if (!firstName || !lastName || !email || !phone || !password) {
    showToast('Complete your name, email, phone, and password first.');
    return false;
  }

  const body = new FormData();
  body.append('action', 'signup');
  body.append('first_name', firstName);
  body.append('last_name', lastName);
  body.append('email', email);
  body.append('phone', phone);
  body.append('password', password);

  try {
    const json = await driverAuthPost(body);
    if (json.success) {
      driverAuthenticated = true;
      Object.assign(driverInitialContext, json.data || {}, { logged_in: true });
      showToast(json.data?.message || 'Driver account created. Continue your application.');
      return true;
    }

    showToast(json.data?.message || 'Driver registration failed.');
  } catch (err) {
    showToast('Could not reach Idibia right now. Please try again.');
  }

  return false;
}


function getSelectedValue(groupId, fallback = '') {
  const active = document.querySelector(`#${groupId} .vehicle-card.active`);
  return active?.dataset?.value || active?.textContent?.trim() || fallback;
}

function chooseKycFile(box) {
  const field = box.dataset.field;
  if (!field) return;

  const input = document.getElementById('driverKycFileInput');
  if (!input) {
    showToast('File picker is not ready yet. Please reload and try again.');
    return;
  }

  input.value = '';
  input.onchange = () => {
    const file = input.files && input.files[0];
    if (!file) return;
    driverFiles[field] = file;
    markDone(box, file.name);
  };
  input.click();
}

function validateRequiredFiles(fields, message) {
  const missing = fields.filter(field => !driverFiles[field]);
  if (missing.length) {
    showToast(message);
    return false;
  }
  return true;
}

function requireValue(id, message) {
  const el = document.getElementById(id);
  if (!el || !el.value.trim()) {
    showToast(message);
    if (el) el.focus();
    return false;
  }
  return true;
}

async function submitDriverKyc() {
  if (!requireValue('driverAccountHolder', 'Enter the bank account holder name.')) return false;
  if (!requireValue('driverAccountNumber', 'Enter the bank account number.')) return false;
  if (!requireValue('driverEmergencyName', 'Enter your next of kin name.')) return false;
  if (!requireValue('driverEmergencyPhone', 'Enter your next of kin phone number.')) return false;

  const body = new FormData();
  body.append('vehicle_type', getSelectedValue('vg1', 'bike'));
  body.append('vehicle_year', document.getElementById('driverVehicleYear').value.trim());
  body.append('vehicle_model', `${document.getElementById('driverVehicleYear').value.trim()} ${document.getElementById('driverVehicleManufacturer').value.trim()}`.trim());
  body.append('vehicle_plate', document.getElementById('driverVehiclePlate').value.trim().toUpperCase());
  body.append('vehicle_color', document.getElementById('driverVehicleColor').value.trim());
  body.append('bank_name', document.getElementById('driverBankName').value);
  body.append('account_holder_name', document.getElementById('driverAccountHolder').value.trim());
  body.append('account_number', document.getElementById('driverAccountNumber').value.trim());
  body.append('emergency_name', document.getElementById('driverEmergencyName').value.trim());
  body.append('emergency_relationship', document.getElementById('driverEmergencyRelationship').value);
  body.append('emergency_phone', document.getElementById('driverEmergencyPhone').value.trim());
  body.append('emergency_address', document.getElementById('driverEmergencyAddress').value.trim());

  Object.entries(driverFiles).forEach(([field, file]) => body.append(field, file));

  try {
    const response = await fetch('/driver-kyc-handler.php', {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    const json = await parseDriverJson(response);
    if (json.success) {
      driverInitialContext.kyc_status = 'under_review';
      showToast(json.data?.message || 'Application submitted for review.');
      return true;
    }
    showToast(json.data?.message || 'Could not submit application.');
  } catch (err) {
    showToast('Could not submit application. Please try again.');
  }
  return false;
}

function updateDriver() {
  syncDriverRegistrationFields();
  for (let i = 1; i <= 5; i++) {
    document.getElementById('dstep-' + i).classList.toggle('active', i === driverStep);
  }
  document.getElementById('driverStepNum').textContent = driverStep;
  document.getElementById('driverStepTitle').textContent = driverTitles[driverStep - 1];
  document.getElementById('driverProgress').style.width = (driverStep / 5 * 100) + '%';

  const backBtn = document.getElementById('driverBack');
  const nextBtn = document.getElementById('driverNext');
  const headerWrap = document.getElementById('driverHeaderWrap');
  const progressWrap = document.getElementById('driverProgressWrap');
  const footerWrap = document.getElementById('driverFooterWrap');

  backBtn.style.visibility = driverStep === 1 ? 'hidden' : 'visible';

  if (driverStep === 1) {
    nextBtn.innerHTML = driverAuthMode === 'login'
      ? 'Sign in <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>'
      : 'Continue <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
  } else {
    nextBtn.innerHTML = 'Continue <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
  }

  if (driverStep === 5) {
    headerWrap.style.display = 'none';
    progressWrap.style.display = 'none';
    footerWrap.style.display = 'none';
  } else {
    headerWrap.style.display = 'flex';
    progressWrap.style.display = 'block';
    footerWrap.style.display = 'flex';
  }
}

async function driverNext() {
  if (driverStep === 1 && !driverAuthenticated) {
    if (driverAuthMode === 'login') {
      await driverLogin();
      return;
    }

    const created = await submitDriverSignup();
    if (!created) return;
  }

  if (driverStep === 2 && !validateRequiredFiles(['id_front', 'id_back', 'selfie'], 'Upload your license, ID/NIN, and profile photo first.')) return;

  if (driverStep === 3) {
    if (!requireValue('driverVehicleYear', 'Enter your vehicle year.')) return;
    if (!requireValue('driverVehicleManufacturer', 'Enter your vehicle manufacturer.')) return;
    if (!requireValue('driverVehiclePlate', 'Enter your license plate number.')) return;
    if (!validateRequiredFiles(['vehicle_photo', 'vehicle_license_doc'], 'Upload at least one vehicle photo and your vehicle license certificate.')) return;
  }

  if (driverStep === 4) {
    const submitted = await submitDriverKyc();
    if (!submitted) return;
  }

  if (driverStep < 5) {
    driverStep++;
    updateDriver();
    document.getElementById('driverContent').scrollTop = 0;
  }
}
function driverPrev() {
  if (driverStep > 1) {
    driverStep--;
    updateDriver();
    document.getElementById('driverContent').scrollTop = 0;
  }
}

function selVehicle(el, groupId) {
  const parent = el.closest('[id=' + groupId + ']') || el.parentElement;
  parent.querySelectorAll('.vehicle-card').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
}

function markDone(el, filename = '') {
  el.classList.add('done');
  el.querySelector('p').innerHTML = '<strong style="color:var(--success)">✓ File selected</strong>';
  const small = el.querySelector('small');
  if (small) small.textContent = filename || 'Tap to replace';
}

function goToDashboard() {
  if (!driverInitialContext.is_approved) {
    driverStep = driverInitialContext.kyc_status === 'under_review' ? 5 : 1;
    document.getElementById('screen-driver-dash').classList.remove('active');
    document.getElementById('screen-driver').classList.add('active');
    updateDriver();
    return;
  }
  document.getElementById('screen-driver').classList.remove('active');
  document.getElementById('screen-driver-dash').classList.add('active');
}

// ===== DASHBOARD =====
let isOnline = true;
let currentTab = 'home';

async function toggleOnline() {
  if (!driverInitialContext.is_approved) {
    showToast('Your account must be approved before going online.');
    return;
  }

  const requestedState = !isOnline;
  const body = new FormData();
  body.append('online', requestedState ? '1' : '0');

  try {
    const response = await fetch('/driver-toggle-online.php', {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    const json = await parseDriverJson(response);
    if (!json.success) {
      showToast(json.data?.message || 'Could not update online status.');
      return;
    }

    isOnline = !!json.data?.is_online;
    driverInitialContext.is_online = isOnline;
    const toggle = document.getElementById('onlineToggle');
    toggle.classList.toggle('online', isOnline);
    toggle.classList.toggle('offline', !isOnline);
    document.getElementById('onlineLabel').textContent = isOnline ? "Online" : "Offline";
    showToast(isOnline ? '✓ You are now online' : 'You are now offline');
  } catch (err) {
    showToast('Could not update online status. Please try again.');
  }
}

function switchTab(tab) {
  currentTab = tab;
  // panels
  document.querySelectorAll('.dash-panel').forEach(p => p.classList.remove('active'));
  const panel = document.getElementById('panel-' + tab);
  if (panel) panel.classList.add('active');
  // bottom nav
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const navItem = document.getElementById('nav-' + tab);
  if (navItem) navItem.classList.add('active');
  // sidebar icons
  document.querySelectorAll('.dash-sidebar-icon').forEach(i => i.classList.remove('active'));
  const sidebarItems = document.querySelectorAll('.dash-sidebar-icon');
  const tabOrder = ['home','earnings','trips','help','profile'];
  const idx = tabOrder.indexOf(tab);
  if (sidebarItems[idx]) sidebarItems[idx].classList.add('active');

  document.getElementById('dashBody').scrollTop = 0;
}

// Timer countdown
let trqCount = 14;
setInterval(() => {
  trqCount--;
  if (trqCount <= 0) trqCount = 14;
  const el = document.getElementById('trqTimer');
  if (el) {
    el.textContent = trqCount;
    el.classList.toggle('urgent', trqCount <= 5);
  }
}, 1000);

// Dynamic greeting
function setGreeting() {
  const h = new Date().getHours();
  const greet = h < 12 ? 'Good morning,' : h < 17 ? 'Good afternoon,' : 'Good evening,';
  const el = document.getElementById('dashGreeting');
  if (el) el.textContent = greet;
}
setGreeting();
setDriverAuthMode(driverAuthMode);
isOnline = !!driverInitialContext.is_online;
if (document.getElementById('onlineToggle')) {
  document.getElementById('onlineToggle').classList.toggle('online', isOnline);
  document.getElementById('onlineToggle').classList.toggle('offline', !isOnline);
  document.getElementById('onlineLabel').textContent = isOnline ? 'Online' : 'Offline';
}

// Toast
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 3000);
}

updateDriver();
</script>
</body>
</html>