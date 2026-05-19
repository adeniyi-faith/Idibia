<?php ob_start();
require_once __DIR__ . '/wp-auth-config.php';

if ( isset($_GET['track']) ) {
    $token = sanitize_text_field($_GET['track']);
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT trip_id FROM `{$wpdb->prefix}sd_tracking_tokens` WHERE token = %s AND expires_at > NOW() LIMIT 1", $token ) );
    if ( $row ) {
        // Render public tracking view
        require_once __DIR__ . '/public-tracking.php';
        die();
    } else {
        die('Tracking link expired or invalid.');
    }
}
if ( is_user_logged_in() && get_user_meta( get_current_user_id(), 'idibia_account_type', true ) === 'customer' ) {
    header( 'Location: dashboard.php' );
    exit;
}
$register_nonce = wp_create_nonce( 'idibia_register' );
$verify_nonce   = wp_create_nonce( 'idibia_verify' );
if ( ob_get_level() > 0 ) ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=0">
<meta name="theme-color" content="#0B1628">
<title>Idibia — Customer App</title>
<link rel="icon" href="data:,">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
<style>
:root {
  --navy: #0B1628;
  --navy-mid: #162035;
  --navy-light: #1E2D47;
  --navy-card: #243352;
  --gold: #F5C842;
  --gold-dark: #C9A224;
  --gold-light: #FDE68A;
  --slate: #8A9AB8;
  --slate-light: #B8C5D8;
  --white: #FFFFFF;
  --surface: #F4F6FA;
  --surface-2: #E8ECF3;
  --surface-3: #D8DFE8;
  --danger: #E8484A;
  --danger-soft: rgba(232,72,74,0.1);
  --success: #22C47A;
  --success-soft: rgba(34,196,122,0.1);
  --info: #4A9EFF;
  --info-soft: rgba(74,158,255,0.1);
  --orange: #FF7B3A;
  --purple: #9B59FF;
  --text-primary: #0B1628;
  --text-secondary: #5A6B85;
  --text-muted: #8A9AB8;
  --radius: 16px;
  --radius-sm: 10px;
  --radius-xs: 8px;
  --radius-lg: 24px;
  --radius-xl: 32px;
  --sidebar: 72px;
  --shadow-sm: 0 2px 8px rgba(11,22,40,0.08);
  --shadow-md: 0 4px 20px rgba(11,22,40,0.12);
  --shadow-lg: 0 8px 40px rgba(11,22,40,0.16);
  --shadow-gold: 0 4px 24px rgba(245,200,66,0.3);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
html { height: 100%; overflow: hidden; font-size: 16px; }
body { font-family: 'DM Sans', sans-serif; background: var(--navy); height: 100%; overflow: hidden; color: var(--white); -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
h1,h2,h3,h4,h5,.display { font-family: 'Syne', sans-serif; font-weight: 700; }
button { font-family: 'DM Sans', sans-serif; user-select: none; }
input, textarea, select { font-family: 'DM Sans', sans-serif; }
svg { display: block; flex-shrink: 0; }
a { color: inherit; }
#app { width: 100%; height: 100%; height: 100dvh; position: relative; overflow: hidden; }

/* ═══════════════════════ SCREEN ROUTING ═══════════════════════ */
.screen {
  position: absolute; inset: 0; display: flex; flex-direction: column;
  opacity: 0; pointer-events: none; transform: translateX(32px);
  transition: opacity 0.38s cubic-bezier(0.4,0,0.2,1), transform 0.38s cubic-bezier(0.4,0,0.2,1);
  z-index: 1; will-change: opacity, transform;
}
.screen.active { opacity: 1; pointer-events: all; transform: translateX(0); z-index: 5; }
.screen.slide-out { opacity: 0; transform: translateX(-32px); }

/* ═══════════════════════ ONBOARDING ═══════════════════════ */
#screen-onboarding { background: var(--surface); justify-content: flex-end; }
.onb-bg { position: absolute; inset: 0; overflow: hidden; }
.onb-blob { position: absolute; border-radius: 50%; filter: blur(90px); }
.onb-blob-1 { width: 380px; height: 380px; background: var(--gold); opacity: 0.12; top: -80px; right: -80px; animation: blobFloat1 8s ease-in-out infinite; }
.onb-blob-2 { width: 280px; height: 280px; background: var(--info); opacity: 0.1; bottom: 18%; left: -60px; animation: blobFloat2 10s ease-in-out infinite; }
.onb-blob-3 { width: 200px; height: 200px; background: var(--success); opacity: 0.07; top: 40%; right: 10%; animation: blobFloat3 12s ease-in-out infinite; }
@keyframes blobFloat1 { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(-20px,20px) scale(1.05)} }
@keyframes blobFloat2 { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(20px,-15px) scale(1.08)} }
@keyframes blobFloat3 { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(-10px,25px) scale(0.95)} }
.onb-slides-wrap { position: absolute; top: 0; left: 0; right: 0; bottom: 260px; overflow: hidden; }
.onb-slides { display: flex; height: 100%; transition: transform 0.55s cubic-bezier(0.4,0,0.2,1); }
.onb-slide { min-width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 64px 36px 24px; text-align: center; }
.onb-icon-ring { position: relative; width: 156px; height: 156px; margin-bottom: 40px; flex-shrink: 0; }
.onb-icon-ring-inner { position: absolute; inset: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--white); box-shadow: var(--shadow-md); }
.onb-icon-ring-inner svg { width: 60px; height: 60px; }
.ring-anim { position: absolute; inset: -10px; border-radius: 50%; border: 1.5px solid currentColor; opacity: 0.18; animation: ringPulse 3.5s ease-in-out infinite; }
.ring-anim-2 { position: absolute; inset: -22px; border-radius: 50%; border: 1px solid currentColor; opacity: 0.08; animation: ringPulse 3.5s ease-in-out infinite 0.8s; }
@keyframes ringPulse { 0%,100%{transform:scale(1);opacity:0.18} 50%{transform:scale(1.06);opacity:0.06} }
.c-gold { color: var(--gold-dark); }
.c-info { color: var(--info); }
.c-success { color: var(--success); }
.onb-slide h2 { font-size: clamp(26px, 7vw, 36px); line-height: 1.15; margin-bottom: 16px; color: var(--text-primary); }
.onb-slide p { font-size: 15.5px; color: var(--text-secondary); line-height: 1.75; max-width: 310px; }
.onb-slide p strong { color: var(--navy); }
.onb-bottom { position: absolute; bottom: 0; left: 0; right: 0; padding: 20px 28px; padding-bottom: max(24px, env(safe-area-inset-bottom)); background: linear-gradient(to top, var(--surface) 75%, transparent); }
.onb-dots { display: flex; gap: 8px; justify-content: center; margin-bottom: 22px; }
.onb-dot { width: 8px; height: 8px; border-radius: 4px; background: var(--surface-3); transition: all 0.35s cubic-bezier(0.4,0,0.2,1); }
.onb-dot.active { width: 32px; background: var(--gold); }
.onb-skip { position: absolute; top: max(16px, env(safe-area-inset-top)); right: 16px; color: var(--text-muted); font-size: 13.5px; font-weight: 500; background: rgba(255,255,255,0.8); border: none; cursor: pointer; padding: 7px 14px; border-radius: 20px; transition: all 0.2s; z-index: 10; backdrop-filter: blur(8px); }
.onb-skip:hover { color: var(--text-primary); background: var(--white); }

/* ═══════════════════════ BUTTONS ═══════════════════════ */
.btn-primary { width: 100%; height: 54px; background: var(--gold); color: var(--navy); border: none; border-radius: var(--radius-sm); font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s, transform 0.15s, box-shadow 0.2s; letter-spacing: 0.3px; box-shadow: var(--shadow-gold); user-select: none; }
.btn-primary:hover { background: var(--gold-dark); box-shadow: 0 6px 28px rgba(245,200,66,0.4); }
.btn-primary:active { transform: scale(0.97); }
.btn-outline { width: 100%; height: 52px; background: transparent; color: var(--text-primary); border: 1.5px solid var(--surface-3); border-radius: var(--radius-sm); font-size: 15px; font-weight: 500; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: background 0.2s, border-color 0.2s; margin-top: 10px; user-select: none; }
.btn-outline:hover { background: var(--surface-2); border-color: var(--surface-2); }
.btn-outline:active { transform: scale(0.98); }
.find-btn { width: 100%; height: 58px; background: var(--gold); color: var(--navy); border: none; border-radius: var(--radius); font-family: 'Syne', sans-serif; font-size: 17px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; letter-spacing: 0.2px; transition: all 0.2s; box-shadow: var(--shadow-gold); position: relative; overflow: hidden; user-select: none; }
.find-btn::after { content:''; position:absolute; inset:0; background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, transparent 60%); pointer-events:none; }
.find-btn:hover { background: var(--gold-dark); box-shadow: 0 6px 32px rgba(245,200,66,0.45); }
.find-btn:active { transform: scale(0.97); }

/* ═══════════════════════ AUTH ═══════════════════════ */
#screen-auth { background: var(--surface); flex-direction: column; }
@media(min-width: 768px) { #screen-auth { flex-direction: row; } }
.auth-brand { display: none; width: 44%; background: var(--navy); flex-direction: column; justify-content: space-between; padding: 52px; position: relative; overflow: hidden; }
.auth-brand::before { content:''; position:absolute; width:700px; height:700px; border-radius:50%; background: conic-gradient(from 180deg, rgba(245,200,66,0.08), transparent 60%); top:-200px; right:-200px; }
.auth-brand::after { content:''; position:absolute; width:300px; height:300px; border-radius:50%; background:var(--info); opacity:0.04; bottom:-100px; left:-80px; }
@media(min-width:768px){.auth-brand{display:flex;}}
.brand-logo { display: flex; align-items: center; gap: 12px; font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; color: var(--white); }
.brand-icon { width: 44px; height: 44px; background: var(--gold); border-radius: 13px; display: flex; align-items: center; justify-content: center; color: var(--navy); box-shadow: 0 4px 16px rgba(245,200,66,0.3); flex-shrink: 0; }
.brand-headline { font-family: 'Syne', sans-serif; font-size: 48px; font-weight: 800; line-height: 1.05; margin: auto 0; }
.brand-headline span { color: var(--gold); }
.brand-features { display: flex; flex-direction: column; gap: 16px; }
.brand-feat { display: flex; align-items: center; gap: 14px; }
.brand-feat-icon { width: 38px; height: 38px; border-radius: 11px; background: rgba(245,200,66,0.1); display: flex; align-items: center; justify-content: center; color: var(--gold); flex-shrink: 0; border: 1px solid rgba(245,200,66,0.15); }
.brand-feat span { font-size: 14px; color: var(--slate-light); font-weight: 400; }
.brand-copy { font-size: 12px; color: var(--slate); margin-top: 28px; }
.auth-form-panel { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: max(32px, env(safe-area-inset-top)) 24px; overflow-y: auto; background: var(--surface); -webkit-overflow-scrolling: touch; }
.auth-mobile-logo { display: flex; align-items: center; gap: 10px; font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; color: var(--navy); margin-bottom: 36px; }
@media(min-width:768px){.auth-mobile-logo{display:none;}}
.auth-inner { max-width: 420px; width: 100%; margin: 0 auto; }
.auth-title { font-size: 28px; color: var(--text-primary); margin-bottom: 6px; }
.auth-sub { font-size: 15px; color: var(--text-secondary); margin-bottom: 32px; }
.form-group { margin-bottom: 18px; }
.form-label { display: block; font-size: 12.5px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px; letter-spacing: 0.4px; text-transform: uppercase; }
.form-input-wrap { position: relative; }
.form-input-wrap .fi-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; z-index: 1;}
.form-input { width: 100%; height: 52px; background: var(--white); border: 1.5px solid var(--surface-3); border-radius: var(--radius-sm); padding: 0 16px 0 46px; font-size: 16px; /* MOBILE FIX: 16px prevents iOS Auto-zoom */ color: var(--text-primary); transition: border-color 0.2s, box-shadow 0.2s; outline: none; -webkit-appearance: none; appearance: none; }
.form-input.has-pw-toggle { padding-right: 48px; } /* Prevent text overlapping eye toggle */
.form-input:focus { border-color: var(--gold); box-shadow: 0 0 0 4px rgba(245,200,66,0.12); }
.form-input::placeholder { color: var(--text-muted); }
.form-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.form-forgot { font-size: 13px; font-weight: 600; color: var(--navy); background: none; border: none; cursor: pointer; padding: 4px; }
.auth-divider { display: flex; align-items: center; gap: 12px; margin: 22px 0; }
.auth-divider::before,.auth-divider::after { content:''; flex:1; height:1px; background:var(--surface-3); }
.auth-divider span { font-size: 13px; color: var(--text-muted); white-space: nowrap; }
.auth-toggle { text-align: center; margin-top: 22px; font-size: 14px; color: var(--text-secondary); }
.auth-toggle button { background: none; border: none; color: var(--navy); font-weight: 700; cursor: pointer; font-size: 14px; text-decoration: underline; text-underline-offset: 3px; padding: 4px; }
.form-check { display: flex; align-items: flex-start; gap: 10px; margin: 14px 0 4px; }
.form-check input { width: 18px; height: 18px; cursor: pointer; margin-top: 2px; accent-color: var(--gold); flex-shrink: 0; }
.form-check label { font-size: 13px; color: var(--text-secondary); cursor: pointer; line-height: 1.6; }
.form-check label a { color: var(--navy); font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
.error-box { background: #FFF0F0; border-left: 3px solid var(--danger); border-radius: var(--radius-sm); padding: 12px 16px; font-size: 13px; color: var(--danger); margin-bottom: 16px; display: none; }
.error-box.show { display: flex; align-items: center; gap: 8px; }
.otp-inputs { display: flex; gap: 10px; justify-content: center; margin: 20px 0 28px; }
.otp-input { width: 52px; height: 60px; border: 2px solid var(--surface-3); border-radius: var(--radius-sm); text-align: center; font-size: 24px; font-weight: 700; font-family: 'Syne', sans-serif; color: var(--text-primary); background: var(--white); outline: none; transition: border-color 0.2s, box-shadow 0.2s; -webkit-appearance: none; appearance: none;}
.otp-input:focus { border-color: var(--gold); box-shadow: 0 0 0 4px rgba(245,200,66,0.12); }

/* Password Toggle Icon Button */
.pw-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 6px; display: flex; align-items: center; justify-content: center; z-index: 2; border-radius: 8px; transition: color 0.2s, background 0.2s; }
.pw-toggle:hover { color: var(--navy); background: var(--surface); }
.pw-toggle:active { transform: translateY(-50%) scale(0.95); }

/* ═══════════════════════ MAIN APP ═══════════════════════ */
#screen-main { background: var(--surface); flex-direction: column; }
@media(min-width: 768px) { #screen-main { flex-direction: row; } }

.sidebar { display: none; width: var(--sidebar); background: var(--navy); flex-direction: column; align-items: center; padding: 20px 0 24px; justify-content: space-between; z-index: 10; flex-shrink: 0; }
@media(min-width:768px){.sidebar{display:flex;}}
.sidebar-logo { width: 44px; height: 44px; background: var(--gold); border-radius: 13px; display: flex; align-items: center; justify-content: center; color: var(--navy); margin-bottom: 28px; box-shadow: var(--shadow-gold); }
.sidebar-nav { display: flex; flex-direction: column; gap: 2px; width: 100%; padding: 0 10px; flex: 1; }
.sidebar-btn { width: 100%; aspect-ratio: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; background: none; border: none; cursor: pointer; border-radius: 12px; transition: all 0.2s; color: var(--slate); font-size: 9px; font-weight: 500; padding: 6px 4px; user-select: none; }
.sidebar-btn:hover { background: var(--navy-light); color: var(--slate-light); }
.sidebar-btn.active { background: var(--navy-mid); color: var(--gold); }
.sidebar-btn svg { width: 20px; height: 20px; }
.sidebar-exit { color: var(--slate); width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: none; border: none; cursor: pointer; transition: all 0.2s; }
.sidebar-exit:hover { background: rgba(232,72,74,0.12); color: var(--danger); }
.main-content { flex: 1; overflow: hidden; display: flex; flex-direction: column; min-width: 0; }
.tab-view { display: none; flex: 1; overflow: hidden; flex-direction: column; }
.tab-view.active { display: flex; }

/* ═══════════════════════ HOME TAB ═══════════════════════ */
.home-split { display: flex; flex-direction: column; flex: 1; overflow: hidden; }
@media(min-width:768px){.home-split{flex-direction:row-reverse;}}
.map-area { flex: 1; position: relative; min-height: 38dvh; overflow: hidden; background: #C8D8EC; }
@media(min-width:768px){.map-area{min-height:unset;}}
.map-bg { position:absolute; inset:0; background-image: linear-gradient(rgba(11,22,40,0.05) 1px,transparent 1px), linear-gradient(90deg,rgba(11,22,40,0.05) 1px,transparent 1px); background-size:36px 36px; background-color:#C8D8EC; }
.map-roads { position:absolute; inset:0; }
.map-buildings { position: absolute; inset: 0; pointer-events: none; }
.map-float { position:absolute; top:max(16px, env(safe-area-inset-top)); right:16px; display:flex; flex-direction:column; gap:10px; }
.map-btn { width:46px; height:46px; background:var(--white); border-radius:13px; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--text-secondary); box-shadow:var(--shadow-md); transition:all 0.2s; user-select: none; }
.map-btn:hover { transform:scale(1.05); box-shadow:var(--shadow-lg); }
.map-btn:active { transform:scale(0.95); }
.map-btn svg { width:19px; height:19px; }
.map-pin { position:absolute; top:50%; left:50%; transform:translate(-50%,-60%); display:flex; flex-direction:column; align-items:center; animation:floatPin 2.8s ease-in-out infinite; }
@keyframes floatPin { 0%,100%{transform:translate(-50%,-60%)} 50%{transform:translate(-50%,-72%)} }
.map-pin-head { width:50px; height:50px; background:var(--navy); border-radius:50% 50% 50% 0; transform:rotate(-45deg); display:flex; align-items:center; justify-content:center; box-shadow:0 6px 24px rgba(11,22,40,0.35); }
.map-pin-head::after { content:''; width:20px; height:20px; background:var(--gold); border-radius:50%; }
.map-pin-shadow { width:20px; height:7px; background:rgba(11,22,40,0.18); border-radius:50%; margin-top:3px; filter:blur(3px); animation:shadowPulse 2.8s ease-in-out infinite; }
@keyframes shadowPulse { 0%,100%{transform:scaleX(1);opacity:0.18} 50%{transform:scaleX(0.7);opacity:0.1} }
.rider-marker { position:absolute; display:flex; flex-direction:column; align-items:center; animation:riderFloat 4s ease-in-out infinite; }
.rider-marker:nth-child(2) { top:22%; left:18%; animation-delay:0s; }
.rider-marker:nth-child(3) { top:55%; right:20%; animation-delay:1.5s; }
.rider-marker:nth-child(4) { bottom:28%; left:35%; animation-delay:0.8s; }
@keyframes riderFloat { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-5px)} }
.rider-blip { width:38px; height:38px; background:var(--white); border-radius:50%; border:3px solid var(--success); display:flex; align-items:center; justify-content:center; box-shadow:var(--shadow-md); position:relative; }
.rider-blip::after { content:''; position:absolute; inset:-5px; border-radius:50%; border:2px solid var(--success); opacity:0.3; animation:blipPulse 2s ease-in-out infinite; }
@keyframes blipPulse { 0%,100%{transform:scale(1);opacity:0.3} 50%{transform:scale(1.2);opacity:0} }
.rider-label { background:var(--white); border-radius:6px; padding:2px 7px; font-size:10px; font-weight:600; color:var(--text-primary); margin-top:4px; box-shadow:var(--shadow-sm); white-space:nowrap; }
.map-eta-chip { position:absolute; bottom:16px; left:16px; background:var(--white); border-radius:12px; padding:10px 14px; display:flex; align-items:center; gap:10px; box-shadow:var(--shadow-md); }
.eta-chip-dot { width:10px; height:10px; border-radius:50%; background:var(--success); flex-shrink:0; box-shadow:0 0 0 4px rgba(34,196,122,0.2); }
.eta-chip-text { font-size:13px; font-weight:600; color:var(--text-primary); }
.eta-chip-sub { font-size:11px; color:var(--text-muted); }

.booking-panel { background:var(--white); width:100%; display:flex; flex-direction:column; box-shadow:0 -12px 48px rgba(11,22,40,0.1); border-radius:var(--radius-lg) var(--radius-lg) 0 0; max-height:64dvh; overflow:hidden; z-index: 10; position: relative; }
@media(min-width:768px){.booking-panel{width:420px; max-height:unset; border-radius:0; box-shadow:8px 0 40px rgba(11,22,40,0.08);}}
.drag-handle { width:36px; height:4px; background:var(--surface-3); border-radius:2px; margin:12px auto 2px; display:block; transition: background 0.2s; }
.booking-panel:active .drag-handle { background: var(--slate-light); }
@media(min-width:768px){.drag-handle{display:none;}}
.panel-scroll { flex:1; overflow-y:auto; padding:14px 22px; -webkit-overflow-scrolling:touch; overscroll-behavior:contain; }
.panel-scroll::-webkit-scrollbar { width:3px; }
.panel-scroll::-webkit-scrollbar-thumb { background:var(--surface-3); border-radius:2px; }
.panel-title { font-size:21px; color:var(--text-primary); margin-bottom:18px; letter-spacing:-0.3px; }

/* Location box */
.location-box { background:var(--surface); border-radius:var(--radius); padding:6px; margin-bottom:18px; position:relative; border:1px solid var(--surface-2); }
.loc-row { display:flex; align-items:center; gap:10px; padding:8px 10px; }
.loc-dot { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.loc-dot.pickup { background:var(--info-soft); }
.loc-dot.dropoff { background:rgba(245,200,66,0.12); }
.loc-input { flex:1; background:var(--white); border:1.5px solid var(--surface-2); border-radius:var(--radius-sm); padding:9px 14px; font-size:16px; /* MOBILE FIX */ color:var(--text-primary); outline:none; transition:border-color 0.2s, box-shadow 0.2s; min-width:0; -webkit-appearance:none; appearance:none; }
.loc-input:focus { border-color:var(--gold); box-shadow:0 0 0 3px rgba(245,200,66,0.12); }
.loc-input::placeholder { color:var(--text-muted); font-size:14px; }
.loc-divider { height:1px; background:var(--surface-2); margin:0 0 0 52px; }
.loc-swap { position:absolute; right:18px; top:50%; transform:translateY(-50%); width:32px; height:32px; background:var(--white); border:1.5px solid var(--surface-2); border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--text-secondary); transition:all 0.3s; box-shadow:var(--shadow-sm); z-index:2; }
.loc-swap:hover { border-color:var(--gold); color:var(--gold); transform:translateY(-50%) rotate(180deg); }

/* Schedule row */
.schedule-row { display:flex; gap:8px; margin-bottom:18px; }
.sched-btn { flex:1; height:42px; background:var(--surface); border:2px solid transparent; border-radius:var(--radius-sm); font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.2s; color:var(--text-secondary); letter-spacing:0.1px; user-select: none; }
.sched-btn:hover { background:var(--surface-2); }
.sched-btn.active { background:rgba(245,200,66,0.08); border-color:var(--gold); color:var(--text-primary); }
.sched-btn svg { width:15px; height:15px; }

/* Section label */
.section-label { font-size:10.5px; font-weight:700; letter-spacing:1.2px; color:var(--text-muted); text-transform:uppercase; margin-bottom:12px; }

/* Category cards with fade hint */
.categories-wrapper { position: relative; margin-bottom: 18px; }
.categories-wrapper::after { content: ''; position: absolute; top: 0; right: 0; bottom: 8px; width: 44px; background: linear-gradient(to right, rgba(255,255,255,0), var(--white) 85%); pointer-events: none; }
.categories-scroll { display:flex; gap:9px; overflow-x:auto; padding-bottom:8px; padding-right: 32px; scrollbar-width:none; -webkit-overflow-scrolling:touch; }
.categories-scroll::-webkit-scrollbar { display:none; }
.cat-card { flex-shrink:0; width:84px; background:var(--surface); border-radius:var(--radius); padding:13px 8px; display:flex; flex-direction:column; align-items:center; gap:7px; cursor:pointer; border:2px solid transparent; transition:all 0.22s; user-select:none; }
.cat-card:hover { background:var(--surface-2); }
.cat-card:active { transform: scale(0.97); }
.cat-card.active { background:rgba(245,200,66,0.08); border-color:var(--gold); }
.cat-icon { width:38px; height:38px; border-radius:10px; background:var(--white); display:flex; align-items:center; justify-content:center; transition:background 0.2s; }
.cat-card.active .cat-icon { background:rgba(245,200,66,0.12); }
.cat-card svg { width:20px; height:20px; color:var(--text-muted); transition:color 0.2s; }
.cat-card.active svg { color:var(--gold-dark); }
.cat-card span { font-size:10.5px; font-weight:500; color:var(--text-secondary); text-align:center; line-height:1.2; transition:color 0.2s; }
.cat-card.active span { color:var(--text-primary); font-weight:600; }

/* Service flags */
.service-flags { display:flex; flex-direction:column; gap:10px; margin-bottom:22px; }
.service-flag { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; background:var(--surface); border-radius:var(--radius); cursor:pointer; border:2px solid transparent; transition:all 0.22s; user-select: none; }
.service-flag:hover { background:var(--surface-2); }
.service-flag:active { transform: scale(0.98); }
.service-flag.active { background:rgba(245,200,66,0.06); border-color:rgba(245,200,66,0.35); }
.sf-left { display:flex; align-items:center; gap:13px; }
.sf-icon { width:40px; height:40px; border-radius:11px; background:var(--navy-light); display:flex; align-items:center; justify-content:center; color:var(--gold); flex-shrink:0; transition:background 0.2s; }
.service-flag.active .sf-icon { background:rgba(245,200,66,0.12); }
.sf-title { font-size:14px; font-weight:600; color:var(--text-primary); }
.sf-desc { font-size:12px; color:var(--text-muted); margin-top:2px; }
.toggle-wrap { width:46px; height:26px; background:var(--surface-3); border-radius:13px; position:relative; transition:background 0.25s; flex-shrink:0; cursor:pointer; }
.toggle-wrap.on { background:var(--gold); }
.toggle-knob { position:absolute; width:20px; height:20px; background:var(--white); border-radius:50%; top:3px; left:3px; transition:transform 0.25s cubic-bezier(0.4,0,0.2,1); box-shadow:0 2px 6px rgba(0,0,0,0.18); }
.toggle-wrap.on .toggle-knob { transform:translateX(20px); }

/* Panel footer */
.panel-footer { padding:14px 22px; padding-bottom:max(14px, env(safe-area-inset-bottom)); background:var(--white); border-top:1px solid var(--surface-2); flex-shrink:0; }

/* ═══════════════════════ ACTIVITY TAB ═══════════════════════ */
.activity-tab { background:var(--surface); flex:1; overflow-y:auto; padding:max(24px, env(safe-area-inset-top)) 24px 24px; -webkit-overflow-scrolling:touch; }
.activity-tab::-webkit-scrollbar { width:3px; }
.tab-header { font-size:24px; color:var(--text-primary); margin-bottom:8px; }
.tab-sub { font-size:14px; color:var(--text-muted); margin-bottom:22px; }
.filter-row { display:flex; gap:8px; margin-bottom:20px; overflow-x:auto; scrollbar-width:none; -webkit-overflow-scrolling:touch; padding-bottom:4px; }
.filter-row::-webkit-scrollbar { display:none; }
.filter-pill { flex-shrink:0; height:34px; padding:0 14px; border-radius:20px; background:var(--white); border:1.5px solid var(--surface-2); font-size:12.5px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; color:var(--text-secondary); transition:all 0.2s; white-space:nowrap; user-select: none; }
.filter-pill.active { background:var(--navy); border-color:var(--navy); color:var(--white); }
.filter-pill svg { width:13px; height:13px; }
.trip-card { background:var(--white); border-radius:var(--radius); padding:18px; margin-bottom:12px; cursor:pointer; transition:all 0.2s; border:1.5px solid var(--surface-2); box-shadow:var(--shadow-sm); user-select: none; }
.trip-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-md); border-color:var(--surface-3); }
.trip-card:active { transform:translateY(0) scale(0.98); }
.trip-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; }
.trip-id { font-family:'Syne',sans-serif; font-size:13px; font-weight:700; color:var(--text-primary); }
.trip-date { font-size:11.5px; color:var(--text-muted); margin-top:2px; }
.trip-status { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border-radius:7px; font-size:11px; font-weight:700; letter-spacing:0.3px; }
.trip-status::before { content:''; width:6px; height:6px; border-radius:50%; flex-shrink:0; }
.trip-status.delivered { background:var(--success-soft); color:var(--success); }
.trip-status.delivered::before { background:var(--success); }
.trip-status.in-transit { background:var(--info-soft); color:var(--info); }
.trip-status.in-transit::before { background:var(--info); animation:blipPulse 1.5s ease-in-out infinite; }
.trip-status.cancelled { background:var(--danger-soft); color:var(--danger); }
.trip-status.cancelled::before { background:var(--danger); }
.trip-status.scheduled { background:rgba(155,89,255,0.1); color:var(--purple); }
.trip-status.scheduled::before { background:var(--purple); }
.trip-route { display:flex; flex-direction:column; gap:8px; }
.trip-point { display:flex; align-items:center; gap:10px; font-size:13.5px; color:var(--text-primary); }
.trip-point-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
.trip-point-dot.from { background:var(--info); }
.trip-point-dot.to { background:var(--gold-dark); }
.trip-line { width:1px; height:12px; background:var(--surface-3); margin-left:4px; }
.trip-meta { display:flex; align-items:center; justify-content:space-between; margin-top:14px; padding-top:14px; border-top:1px solid var(--surface); }
.trip-price { font-family:'Syne',sans-serif; font-size:18px; font-weight:800; color:var(--text-primary); }
.trip-actions { display:flex; gap:8px; }
.trip-action-btn { height:30px; padding:0 12px; border-radius:7px; border:1.5px solid var(--surface-2); background:var(--surface); font-size:12px; font-weight:600; color:var(--text-secondary); cursor:pointer; transition:all 0.2s; user-select: none; }
.trip-action-btn.primary { background:var(--navy); border-color:var(--navy); color:var(--white); }
.trip-action-btn:hover { border-color:var(--navy); color:var(--navy); }
.trip-action-btn.primary:hover { background:var(--navy-light); }
.trip-cat { display:flex; align-items:center; gap:5px; font-size:11.5px; color:var(--text-muted); }
.trip-cat svg { width:13px; height:13px; }

/* ═══════════════════════ ACCOUNT TAB ═══════════════════════ */
.account-tab { background:var(--surface); flex:1; overflow-y:auto; -webkit-overflow-scrolling:touch; }
.account-tab::-webkit-scrollbar { width:3px; }
.account-hero { background:var(--navy); padding:max(52px, env(safe-area-inset-top)) 24px 64px; position:relative; overflow:hidden; }
.account-hero-bg { position:absolute; inset:0; overflow:hidden; }
.account-hero-bg::before { content:''; position:absolute; width:280px; height:280px; background:var(--gold); opacity:0.05; border-radius:50%; bottom:-100px; right:-60px; }
.account-hero-bg::after { content:''; position:absolute; width:180px; height:180px; background:var(--info); opacity:0.05; border-radius:50%; top:-40px; left:40%; }
.avatar-wrap { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
.avatar { width:74px; height:74px; background:linear-gradient(135deg,var(--gold),var(--gold-dark)); border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:'Syne',sans-serif; font-size:26px; font-weight:800; color:var(--navy); flex-shrink:0; box-shadow:0 4px 20px rgba(245,200,66,0.35); }
.avatar-name { font-family:'Syne',sans-serif; font-size:22px; font-weight:700; color: var(--white); }
.avatar-email { font-size:14px; color:var(--slate-light); margin-top:4px; }
.avatar-badge { display:inline-flex; align-items:center; gap:5px; margin-top:8px; background:rgba(34,196,122,0.15); border:1px solid rgba(34,196,122,0.3); border-radius:20px; padding:3px 10px; font-size:11px; font-weight:600; color:var(--success); }

/* Updated Stats for better mobile view */
.account-stats { display:flex; gap:8px; margin:0 16px; margin-top:-28px; position:relative; z-index:2; margin-bottom:20px; }
.stat-card { flex:1; min-width:0; background:var(--white); border-radius:var(--radius); padding:12px 6px; text-align:center; box-shadow:var(--shadow-md); border:1.5px solid var(--surface-2); }
.stat-value { font-family:'Syne',sans-serif; font-size:18px; font-weight:800; color:var(--text-primary); }
.stat-label { font-size:10px; color:var(--text-muted); margin-top:3px; }
@media(min-width: 768px) {
  .account-stats { gap:12px; margin: 0 24px; margin-top:-28px; }
  .stat-card { padding: 16px; }
  .stat-value { font-size:22px; }
  .stat-label { font-size:11px; }
}

.account-body { padding:0 24px 32px; }
.referral-banner { background:var(--navy); border-radius:var(--radius); padding:20px; display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; gap:14px; position:relative; overflow:hidden; border:1px solid rgba(245,200,66,0.15); }
.referral-banner::after { content:''; position:absolute; width:160px; height:160px; background:var(--gold); opacity:0.04; border-radius:50%; bottom:-60px; right:-40px; pointer-events:none; }
.ref-text h4 { font-family:'Syne',sans-serif; font-size:16px; color:var(--gold); }
.ref-text p { font-size:13px; color:var(--slate-light); margin-top:3px; }
.ref-code { background:rgba(245,200,66,0.1); border:1.5px dashed rgba(245,200,66,0.4); border-radius:var(--radius-sm); padding:8px 16px; font-family:'Syne',sans-serif; font-size:15px; font-weight:800; color:var(--gold); white-space:nowrap; cursor:pointer; transition:background 0.2s; flex-shrink:0; z-index:1; user-select: none; }
.ref-code:hover { background:rgba(245,200,66,0.18); }
.ref-code:active { transform: scale(0.97); }
.account-card { background:var(--white); border-radius:var(--radius); overflow:hidden; margin-bottom:14px; border:1.5px solid var(--surface-2); box-shadow:var(--shadow-sm); }
.account-card-title { font-size:10.5px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; color:var(--text-muted); padding:14px 18px 8px; }
.account-row { display:flex; align-items:center; gap:13px; padding:14px 18px; cursor:pointer; transition:background 0.15s; border-top:1px solid var(--surface); user-select: none; }
.account-row:first-of-type { border-top:none; }
.account-row:hover { background:var(--surface); }
.account-row:active { background:var(--surface-2); }
.account-row-icon { width:38px; height:38px; border-radius:10px; background:var(--surface); display:flex; align-items:center; justify-content:center; color:var(--text-secondary); flex-shrink:0; transition:background 0.2s; }
.account-row:hover .account-row-icon { background:var(--surface-2); }
.account-row-icon svg { width:18px; height:18px; }
.account-row-label { flex:1; font-size:15px; color:var(--text-primary); font-weight:500; }
.account-row-meta { font-size:12px; color:var(--text-muted); margin-right:6px; }
.account-row-arrow svg { width:15px; height:15px; color:var(--text-muted); }

/* ═══════════════════════ BOTTOM NAV ═══════════════════════ */
.bottom-nav { display:flex; background:var(--white); border-top:1px solid var(--surface-2); padding:6px 0; padding-bottom:max(8px, env(safe-area-inset-bottom)); flex-shrink:0; z-index:20; box-shadow:0 -4px 20px rgba(11,22,40,0.06); }
@media(min-width:768px){.bottom-nav{display:none;}}
.bnav-btn { flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; background:none; border:none; cursor:pointer; padding:6px 4px; font-size:10px; font-weight:600; color:var(--text-muted); transition:color 0.2s; position:relative; user-select: none; }
.bnav-btn svg { width:22px; height:22px; transition:transform 0.2s; }
.bnav-btn.active { color:var(--navy); }
.bnav-btn.active svg { transform:scale(1.1); }
.bnav-indicator { position:absolute; top:4px; width:20px; height:3px; background:var(--gold); border-radius:2px; opacity:0; transition:opacity 0.2s; }
.bnav-btn.active .bnav-indicator { opacity:1; }

/* ═══════════════════════ TRACKING SCREEN ═══════════════════════ */
#screen-tracking { background:var(--surface); }
.tracking-header { background:var(--white); padding:max(14px, env(safe-area-inset-top)) 18px 14px; display:flex; align-items:center; gap:13px; border-bottom:1px solid var(--surface-2); flex-shrink:0; }
.back-btn { width:40px; height:40px; border-radius:11px; background:var(--surface); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--text-secondary); transition:all 0.2s; flex-shrink:0; user-select: none;}
.back-btn:hover { background:var(--surface-2); }
.back-btn:active { transform:scale(0.95); }
.tracking-header h3 { font-size:18px; color:var(--text-primary); flex:1; }
.header-badge { background:var(--info-soft); border:1px solid rgba(74,158,255,0.2); border-radius:8px; padding:4px 10px; font-size:11px; font-weight:700; color:var(--info); display:flex; align-items:center; gap:5px; }
.header-badge::before { content:''; width:6px; height:6px; border-radius:50%; background:var(--info); animation:blipPulse 1.5s ease-in-out infinite; flex-shrink:0; }
.tracking-map { height:260px; position:relative; background:#C8D8EC; overflow:hidden; flex-shrink:0; }
@media(min-width:480px){.tracking-map{height:32dvh;}}
.tracking-map-bg { position:absolute; inset:0; background-image:linear-gradient(rgba(11,22,40,0.05) 1px,transparent 1px), linear-gradient(90deg,rgba(11,22,40,0.05) 1px,transparent 1px); background-size:30px 30px; }
.moving-rider { position:absolute; top:38%; left:20%; display:flex; flex-direction:column; align-items:center; animation:riderMove 6s linear infinite; }
@keyframes riderMove { 0%{left:15%; top:38%} 25%{left:35%; top:32%} 50%{left:50%; top:42%} 75%{left:62%; top:36%} 100%{left:15%; top:38%} }
.tracking-scroll { flex:1; overflow-y:auto; padding-bottom:env(safe-area-inset-bottom); -webkit-overflow-scrolling:touch; }
.tracking-scroll::-webkit-scrollbar { width:3px; }
.tracking-card { background:var(--white); margin:16px; border-radius:var(--radius-lg); padding:22px; box-shadow:var(--shadow-md); }

/* Improved Rider Info & Plate Layout */
.rider-status { display:flex; align-items:center; gap:15px; margin-bottom:12px; }
.rider-avatar { width:56px; height:56px; border-radius:50%; background:linear-gradient(135deg,var(--gold),var(--gold-dark)); display:flex; align-items:center; justify-content:center; font-family:'Syne',sans-serif; font-size:18px; font-weight:800; color:var(--navy); flex-shrink:0; position:relative; }
.rider-online { position:absolute; bottom:2px; right:2px; width:13px; height:13px; background:var(--success); border-radius:50%; border:2.5px solid var(--white); }
.rider-info-col { flex:1; display:flex; flex-direction:column; justify-content:center; min-width:0; }
.rider-profile-top { display:flex; justify-content:space-between; align-items:flex-start; width:100%; gap:8px; }
.rider-name { font-size:16px; font-weight:600; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.rider-plate { background:var(--surface); border-radius:7px; padding:5px 11px; font-size:12px; font-weight:700; color:var(--text-secondary); border:1px solid var(--surface-2); letter-spacing:0.5px; white-space:nowrap; flex-shrink:0; }
.rider-rating { display:flex; align-items:center; gap:4px; font-size:12.5px; color:var(--text-muted); margin-top:4px; }
.rider-rating svg { width:13px; height:13px; color:var(--gold); fill:var(--gold); }
.rider-contact { display:flex; gap:8px; margin-bottom:18px; padding-bottom:18px; border-bottom:1px solid var(--surface-2); }
.contact-btn { height:36px; border-radius:9px; border:1.5px solid var(--surface-2); background:var(--surface); font-size:12px; font-weight:600; color:var(--text-secondary); cursor:pointer; display:flex; align-items:center; gap:6px; padding:0 12px; transition:all 0.2s; flex:1; justify-content:center; user-select: none; }
.contact-btn:hover { border-color:var(--navy); color:var(--navy); }
.contact-btn.call { background:var(--navy); border-color:var(--navy); color:var(--white); }
.contact-btn svg { width:14px; height:14px; }

.eta-bar { background:var(--surface); border-radius:var(--radius); padding:16px; display:flex; align-items:center; gap:0; margin-bottom:18px; overflow:hidden; }
.eta-item { flex:1; text-align:center; min-width: 0;}
.eta-label { font-size:10px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.8px; margin-bottom:4px; }
.eta-value { font-family:'Syne',sans-serif; font-size:26px; font-weight:800; color:var(--text-primary); line-height:1; white-space: nowrap; }
.eta-unit { font-size:11px; color:var(--text-muted); margin-top:3px; white-space: nowrap; }
.eta-divider { width:1px; height:50px; background:var(--surface-2); flex-shrink:0; }
.tracking-steps { display:flex; flex-direction:column; }
.t-step { display:flex; align-items:flex-start; gap:12px; padding:12px 0; position:relative; }
.t-step::after { content:''; position:absolute; left:9px; top:34px; width:2px; height:calc(100% - 22px); background:var(--surface-2); border-radius:1px; }
.t-step:last-child::after { display:none; }
.t-step-dot { width:20px; height:20px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:3px; }
.t-step-dot.done { background:var(--success); }
.t-step-dot.active { background:var(--info); animation:stepPulse 1.5s ease-in-out infinite; }
.t-step-dot.pending { background:var(--surface-2); }
@keyframes stepPulse { 0%,100%{box-shadow:0 0 0 0 rgba(74,158,255,0.4)} 50%{box-shadow:0 0 0 8px rgba(74,158,255,0)} }
.t-step-dot svg { width:11px; height:11px; color:var(--white); }
.t-step-info h4 { font-size:14px; font-weight:600; color:var(--text-primary); }
.t-step-info p { font-size:12px; color:var(--text-muted); margin-top:2px; }

/* Safety panel */
.safety-panel { background:var(--navy); border-radius:var(--radius); padding:16px; margin:0 16px 16px; display:flex; align-items:center; gap:14px; }
.safety-icon { width:44px; height:44px; border-radius:11px; background:rgba(245,200,66,0.1); display:flex; align-items:center; justify-content:center; color:var(--gold); flex-shrink:0; border:1px solid rgba(245,200,66,0.15); }
.safety-text h4 { font-size:14px; font-weight:600; color:var(--white); }
.safety-text p { font-size:12px; color:var(--slate-light); margin-top:2px; }
.safety-btn { margin-left:auto; background:var(--danger); border:none; border-radius:9px; color:var(--white); font-size:12px; font-weight:700; padding:8px 14px; cursor:pointer; white-space:nowrap; flex-shrink:0; font-family:'Syne',sans-serif; transition: transform 0.2s; user-select: none;}
.safety-btn:active { transform: scale(0.95); }

/* ═══════════════════════ MODALS ═══════════════════════ */
.modal-overlay { position:fixed; inset:0; background:rgba(11,22,40,0.65); z-index:100; display:flex; align-items:flex-end; justify-content:center; opacity:0; pointer-events:none; transition:opacity 0.3s; backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); }
@media(min-width:768px){.modal-overlay{align-items:center;}}
.modal-overlay.show { opacity:1; pointer-events:all; }
.modal-content { background:var(--white); width:100%; max-width:500px; border-radius:var(--radius-xl) var(--radius-xl) 0 0; padding:28px 24px calc(28px + env(safe-area-inset-bottom)); transform:translateY(100%); transition:transform 0.4s cubic-bezier(0.2,0.8,0.2,1); max-height:92dvh; overflow-y:auto; -webkit-overflow-scrolling:touch; display: flex; flex-direction: column; }
@media(min-width:768px){.modal-content{border-radius:var(--radius-xl); transform:translateY(24px) scale(0.95); max-height:90dvh; padding-bottom: 28px;}}
.modal-overlay.show .modal-content { transform:translateY(0) scale(1); }
.modal-handle { width:36px; height:4px; background:var(--surface-3); border-radius:2px; margin:0 auto 16px; display:block; flex-shrink: 0; }
@media(min-width:768px){.modal-handle{display:none;}}
.modal-header { text-align:center; margin-bottom:16px; flex-shrink: 0; }
.modal-icon { width:52px; height:52px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 12px; flex-shrink: 0; }
.modal-icon.success { background:var(--success-soft); color:var(--success); border:2px solid rgba(34,196,122,0.2); }
.modal-icon.info { background:var(--info-soft); color:var(--info); border:2px solid rgba(74,158,255,0.2); }
.modal-header h2 { font-size:22px; color:var(--text-primary); }
.modal-header p { font-size:13px; color:var(--text-muted); margin-top:4px; }
.receipt-box { background:var(--surface); border-radius:var(--radius); padding:14px; margin-bottom:16px; border:1.5px dashed var(--surface-3); flex-shrink: 0; }
.receipt-label { font-size:10.5px; font-weight:700; letter-spacing:1.1px; color:var(--text-muted); text-transform:uppercase; margin-bottom:10px; }
.receipt-row { display:flex; justify-content:space-between; align-items:center; font-size:13.5px; color:var(--text-secondary); margin-bottom:8px; }
.receipt-row:last-child { margin-bottom:0; }
.receipt-row.total { border-top:1px solid var(--surface-3); padding-top:12px; margin-top:4px; color:var(--text-primary); font-weight:800; font-size:18px; font-family:'Syne',sans-serif; }
.receipt-payment { display:flex; align-items:center; gap:9px; margin-top:12px; padding-top:12px; border-top:1px solid var(--surface-2); font-size:12px; color:var(--text-secondary); }
.receipt-payment svg { color:var(--text-muted); }
.star-rating { display:flex; justify-content:center; gap:8px; margin-bottom:16px; flex-shrink: 0; }
.star-btn { background:none; border:none; color:var(--surface-3); cursor:pointer; transition:all 0.2s; user-select: none; }
.star-btn:hover,.star-btn.active { color:var(--gold); }
.star-btn:active { transform:scale(0.88); }
.star-btn svg { width:32px; height:32px; fill:currentColor; filter:drop-shadow(0 2px 4px rgba(245,200,66,0)); transition:filter 0.2s; }
.star-btn.active svg { filter:drop-shadow(0 2px 8px rgba(245,200,66,0.4)); }
.rating-label { text-align:center; font-size:13.5px; font-weight:600; color:var(--text-primary); margin-bottom:16px; min-height:18px; }
.feedback-chips { display:flex; flex-wrap:wrap; gap:6px; justify-content:center; margin-bottom:16px; flex-shrink: 0; }
.feedback-chip { padding:5px 12px; border-radius:20px; border:1.5px solid var(--surface-2); background:var(--surface); font-size:12px; font-weight:500; cursor:pointer; color:var(--text-secondary); transition:all 0.2s; user-select: none; }
.feedback-chip.active { border-color:var(--navy); background:var(--navy); color:var(--white); }
.report-issue { display:block; width:100%; text-align:center; color:var(--danger); font-size:13px; font-weight:700; cursor:pointer; border:none; background:none; margin-top:12px; margin-bottom: 4px; text-decoration:underline; text-underline-offset:3px; padding: 4px; user-select: none; }

/* Swipe Hint Animation */
@keyframes swipeHintAnim { 0%,100%{transform:translateX(0)} 50%{transform:translateX(4px)} }
.swipe-hint { font-size:10px; font-weight:600; color:var(--text-muted); display:inline-flex; align-items:center; gap:4px; animation:swipeHintAnim 1.5s infinite; text-transform:uppercase; letter-spacing:0.5px; }

/* Schedule modal */
.time-picker { display:flex; gap:10px; margin-bottom:20px; flex-shrink: 0; }
.time-select { flex:1; height:52px; border:1.5px solid var(--surface-2); border-radius:var(--radius-sm); padding:0 14px; font-size:16px; /* MOBILE FIX */ color:var(--text-primary); background:var(--surface); outline:none; -webkit-appearance:none; appearance:none; cursor:pointer; min-width: 0; }
.time-select:focus { border-color:var(--gold); }
.date-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; margin-bottom:20px; flex-shrink: 0; }
.date-day { display:flex; flex-direction:column; align-items:center; padding:8px 4px; border-radius:var(--radius-sm); cursor:pointer; transition:all 0.2s; border:1.5px solid transparent; user-select: none; }
.date-day:hover { background:var(--surface-2); }
.date-day.active { background:var(--navy); border-color:var(--navy); }
.date-day.today { border-color:var(--gold); }
.date-day-name { font-size:10px; color:var(--text-muted); margin-bottom:4px; font-weight:600; }
.date-day-num { font-family:'Syne',sans-serif; font-size:16px; font-weight:700; color:var(--text-primary); }
.date-day.active .date-day-name,.date-day.active .date-day-num { color:var(--white); }

/* ═══════════════════════ TOAST ═══════════════════════ */
.toast { position:fixed; bottom:90px; left:50%; transform:translateX(-50%) translateY(16px); background:var(--navy); color:var(--white); padding:12px 22px; border-radius:30px; font-size:13.5px; font-weight:500; white-space:nowrap; opacity:0; pointer-events:none; transition:all 0.3s cubic-bezier(0.4,0,0.2,1); z-index:9999; box-shadow:var(--shadow-lg); display:flex; align-items:center; gap:8px; max-width:calc(100vw - 48px); }
.toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
@media(min-width:768px){.toast{bottom:28px;}}

/* ═══════════════════════ MISC ═══════════════════════ */
.hidden { display:none !important; }
.chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:700; }
.chip-success { background:var(--success-soft); color:var(--success); }
.chip-info { background:var(--info-soft); color:var(--info); }
.chip-gold { background:rgba(245,200,66,0.12); color:var(--gold-dark); }
.section-divider { height:1px; background:var(--surface-2); margin:20px 0; }
/* Rider dot on map */
.rider-dot { width:38px; height:38px; background:var(--white); border-radius:50%; border:3px solid var(--success); display:flex; align-items:center; justify-content:center; box-shadow:var(--shadow-md); }
</style>
</head>
<body>
<div id="app">

<!-- ══════════ ONBOARDING ══════════ -->
<div class="screen" id="screen-onboarding">
  <div class="onb-bg">
    <div class="onb-blob onb-blob-1"></div>
    <div class="onb-blob onb-blob-2"></div>
    <div class="onb-blob onb-blob-3"></div>
  </div>
  <button class="onb-skip" onclick="goTo('screen-auth')">Skip</button>
  <div class="onb-slides-wrap">
    <div class="onb-slides" id="onbSlides">
      <!-- Slide 1 -->
      <div class="onb-slide">
        <div class="onb-icon-ring c-gold">
          <div class="ring-anim"></div>
          <div class="ring-anim-2"></div>
          <div class="onb-icon-ring-inner">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
              <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
            </svg>
          </div>
        </div>
        <h2>Deliver <span style="color:var(--gold-dark)">Faster.</span><br/>Deliver <span style="color:var(--gold-dark)">Better.</span></h2>
        <p>The premier logistics network connecting your items with <strong>verified local riders</strong> instantly across the city.</p>
      </div>
      <!-- Slide 2 -->
      <div class="onb-slide">
        <div class="onb-icon-ring c-info">
          <div class="ring-anim"></div>
          <div class="ring-anim-2"></div>
          <div class="onb-icon-ring-inner">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
          </div>
        </div>
        <h2>Track Every <span style="color:var(--info)">Package</span> Live</h2>
        <p>End-to-end real-time GPS tracking with live driver location and <strong>instant push notifications</strong> at every step.</p>
      </div>
      <!-- Slide 3 -->
      <div class="onb-slide">
        <div class="onb-icon-ring c-success">
          <div class="ring-anim"></div>
          <div class="ring-anim-2"></div>
          <div class="onb-icon-ring-inner">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
              <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
          </div>
        </div>
        <h2>Thousands of <span style="color:var(--success)">Verified</span> Riders</h2>
        <p>Every rider is KYC-verified with full background checks, guaranteeing <strong>safe same-day delivery.</strong></p>
      </div>
    </div>
  </div>
  <div class="onb-bottom">
    <div class="onb-dots" id="onbDots">
      <div class="onb-dot active"></div>
      <div class="onb-dot"></div>
      <div class="onb-dot"></div>
    </div>
    <button class="find-btn" id="onbBtn" onclick="onbNext()">
      <span id="onbBtnText">Next</span>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>
  </div>
</div>

<!-- ══════════ AUTH SCREEN ══════════ -->
<div class="screen" id="screen-auth">
  <div class="auth-brand">
    <div>
      <div class="brand-logo">
        <div class="brand-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="22" height="22"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        </div>
        Idibia
      </div>
      <div class="brand-headline" style="margin-top:52px">Deliver<br/><span>Faster.</span><br/>Deliver <span>Better.</span></div>
    </div>
    <div>
      <div class="brand-features">
        <div class="brand-feat"><div class="brand-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div><span>End-to-end live package tracking</span></div>
        <div class="brand-feat"><div class="brand-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div><span>Thousands of KYC-verified riders</span></div>
        <div class="brand-feat"><div class="brand-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><span>Guaranteed same-day delivery</span></div>
        <div class="brand-feat"><div class="brand-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div><span>Secure payment & instant receipts</span></div>
      </div>
      <div class="brand-copy">© 2026 Idibia Logistics Inc. All rights reserved.</div>
    </div>
  </div>
  <div class="auth-form-panel">
    <div class="auth-inner">
      <div class="auth-mobile-logo">
        <div class="brand-icon" style="width:40px;height:40px;border-radius:11px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        </div>
        Idibia
      </div>
      <div class="error-box" id="authError">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="authErrorText"></span>
      </div>

      <!-- LOGIN VIEW -->
      <form id="loginView" method="post" action="login-handler.php" onsubmit="doLogin(event)">
        <h2 class="auth-title">Welcome back 👋</h2>
        <p class="auth-sub">Sign in to manage your deliveries</p>
        <div class="form-group">
          <label class="form-label">Email or Phone Number</label>
          <div class="form-input-wrap">
            <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input class="form-input" type="text" id="loginEmail" name="email" placeholder="Email or phone number" autocomplete="username" required>
          </div>
        </div>
        <div class="form-group">
          <div class="form-row">
            <label class="form-label" style="margin-bottom:0">Password / OTP</label>
            <button type="button" class="form-forgot" onclick="showToast('Reset link sent to your email')">Forgot password?</button>
          </div>
          <div class="form-input-wrap">
            <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input class="form-input has-pw-toggle" type="password" id="loginPass" name="password" placeholder="••••••••" autocomplete="current-password" required>
            <button type="button" class="pw-toggle" onclick="togglePassword(this)" title="Toggle password visibility">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <button type="submit" class="btn-primary" style="margin-top:4px">
          Sign In — Let's Go
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
        <div class="auth-divider"><span>or continue with</span></div>
        <button type="button" class="btn-outline" onclick="doLogin()">
          <svg viewBox="0 0 24 24" width="18" height="18"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
          Continue with Google
        </button>
        <button type="button" class="btn-outline" onclick="doLogin()" style="margin-top:8px">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.7 9.05 7.4c1.39.06 2.35.76 3.16.8 1.22-.27 2.38-1.01 3.68-.9 1.55.14 2.72.75 3.48 1.9-3.17 1.93-2.4 6.06.68 7.24-.63 1.73-1.43 3.43-2.97 4.84zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
          Continue with Apple
        </button>
        <div class="auth-toggle">Don't have an account? <button type="button" onclick="switchAuth(false)">Create account →</button></div>
      </form>

      <!-- REGISTER VIEW -->
      <form id="registerView" method="post" action="register-handler.php" onsubmit="doRegister(event)" style="display:none">
        <h2 class="auth-title">Join Idibia 🚀</h2>
        <p class="auth-sub">Create your account in 30 seconds</p>
        <div class="error-box" id="regError">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span id="regErrorText"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <div class="form-input-wrap">
            <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input class="form-input" type="text" id="regName" name="full_name" placeholder="John Okafor" autocomplete="name" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <div class="form-input-wrap">
            <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 9a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <input class="form-input" type="tel" id="regPhone" name="phone" placeholder="08012345678" autocomplete="tel" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="form-input-wrap">
            <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input class="form-input" type="email" id="regEmail" name="email" placeholder="you@example.com" autocomplete="email" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="form-input-wrap">
            <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input class="form-input has-pw-toggle" type="password" id="regPassword" name="password" placeholder="Min. 6 characters" autocomplete="new-password" minlength="6" required>
            <button type="button" class="pw-toggle" onclick="togglePassword(this)" title="Toggle password visibility">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <div class="form-check">
          <input type="checkbox" id="termsCheck" name="terms" value="1" checked required>
          <label for="termsCheck">I agree to Idibia's <a href="#">Terms of Service</a>, <a href="#">Privacy Policy</a>, and <a href="#">Location Data Policy</a></label>
        </div>
        <!-- Hidden nonce — PHP outputs this value -->
        <input type="hidden" id="regNonce" name="_nonce" value="<?php echo esc_attr( $register_nonce ?? '' ); ?>">
        <button type="submit" class="btn-primary" style="margin-top:18px" id="regBtn">
          Create Account &amp; Verify
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
        <div class="auth-toggle">Already have an account? <button type="button" onclick="switchAuth(true)">Sign in →</button></div>
      </form>
    </div>
  </div>
</div>

<!-- ══════════ OTP VERIFICATION ══════════ -->
<div class="screen" id="screen-otp">
  <div class="auth-form-panel" style="background:var(--surface)">
    <div class="auth-inner">
      <button type="button" onclick="goBack()" style="display:flex;align-items:center;gap:8px;background:none;border:none;color:var(--text-secondary);font-size:14px;font-weight:600;cursor:pointer;margin-bottom:32px;padding:0">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back
      </button>
      <div style="text-align:center;margin-bottom:32px">
        <div style="width:72px;height:72px;background:var(--info-soft);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:2px solid rgba(74,158,255,0.2)">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="2" width="32" height="32"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <h2 class="auth-title" style="margin-bottom:8px">Check Your Email</h2>
        <p style="font-size:15px;color:var(--text-secondary);line-height:1.6">We emailed a 5-digit code to<br/><strong style="color:var(--text-primary)" id="otpEmailDisplay">your email address</strong></p>
      </div>
      <div class="otp-inputs">
        <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,0)">
        <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,1)">
        <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,2)">
        <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,3)">
        <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,4)">
      </div>
      <div class="error-box" id="verifyError" style="margin-bottom:12px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="verifyErrorText"></span>
      </div>
      <button type="button" class="btn-primary" id="verifyBtn" onclick="doVerify()">
        Verify &amp; Get Started
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><polyline points="20 6 9 17 4 12"/></svg>
      </button>
      <div style="text-align:center;margin-top:20px;font-size:14px;color:var(--text-muted)">
        Didn't receive it? <button type="button" onclick="resendCode()" style="background:none;border:none;color:var(--navy);font-weight:700;cursor:pointer;font-size:14px;padding:4px">Resend Email</button>
      </div>
    </div>
  </div>
</div>

<!-- Dashboard views live in dashboard.php after customer login. -->

<!-- ══════════ TOAST ══════════ -->
<div class="toast" id="toast"></div>

<script>
// ═══════════ STATE ═══════════
let currentScreen = 'screen-onboarding';
let screenHistory = ['screen-onboarding'];
let onbSlide = 0;
let selectedCategory = 'Package';
let currentRating = 5;
let etaInterval = null;
const IDIBIA_API_BASE = new URL('.', window.location.href).href.replace(/\/$/, '');
const IDIBIA_VERIFY_NONCE = '<?php echo esc_js( $verify_nonce ?? '' ); ?>';

async function idibiaPost(endpoint, body = null) {
  const res = await fetch(`${IDIBIA_API_BASE}/${endpoint}`, {
    method: 'POST',
    body,
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' }
  });

  const rawText = await res.text();
  try {
    return JSON.parse(rawText);
  } catch (err) {
    console.error('Raw response from ' + endpoint + ':', rawText);
    throw new Error('Invalid server response');
  }
}

// ═══════════ INIT ═══════════
document.addEventListener('DOMContentLoaded', () => {
  // Start at onboarding on mobile, auth on desktop
  const start = window.innerWidth >= 768 ? 'screen-auth' : 'screen-onboarding';
  currentScreen = start;
  screenHistory = [start];
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(start).classList.add('active');
  buildDateGrid();
  startEtaCountdown();
  initOnboardingSwipe();
});

// ═══════════ SWIPABLE ONBOARDING ═══════════
function initOnboardingSwipe() {
  const slidesWrap = document.getElementById('onbSlides');
  if (!slidesWrap) return;
  let touchStartX = 0;
  let touchEndX = 0;

  slidesWrap.addEventListener('touchstart', e => {
    touchStartX = e.changedTouches[0].screenX;
  }, {passive: true});

  slidesWrap.addEventListener('touchend', e => {
    touchEndX = e.changedTouches[0].screenX;
    if (touchStartX - touchEndX > 50) {
      onbNext(); // Swipe left (next)
    } else if (touchEndX - touchStartX > 50) {
      onbPrev(); // Swipe right (prev)
    }
  }, {passive: true});
}

function onbPrev() {
  const slides = document.getElementById('onbSlides');
  const dots = document.querySelectorAll('.onb-dot');
  const btnText = document.getElementById('onbBtnText');
  if (onbSlide > 0) {
    onbSlide--;
    slides.style.transform = `translateX(-${onbSlide * 100}%)`;
    dots.forEach((d,i) => d.classList.toggle('active', i === onbSlide));
    btnText.textContent = 'Next';
  }
}

// ═══════════ PASSWORD TOGGLE ═══════════
function togglePassword(btn) {
  const input = btn.previousElementSibling;
  if (input.type === 'password') {
    input.type = 'text';
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22"/></svg>`;
  } else {
    input.type = 'password';
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
  }
}

// ═══════════ ROUTING ═══════════
function goTo(id) {
  if (id === currentScreen) return;
  const curr = document.getElementById(currentScreen);
  const next = document.getElementById(id);
  if (!next) return;
  curr.classList.remove('active');
  curr.classList.add('slide-out');
  setTimeout(() => curr.classList.remove('slide-out'), 400);
  next.classList.add('active');
  screenHistory.push(id);
  currentScreen = id;
}

function goBack() {
  if (screenHistory.length <= 1) return;
  screenHistory.pop();
  const prev = screenHistory[screenHistory.length - 1];
  const curr = document.getElementById(currentScreen);
  const prevEl = document.getElementById(prev);
  curr.classList.remove('active');
  curr.classList.add('slide-out');
  setTimeout(() => curr.classList.remove('slide-out'), 400);
  prevEl.classList.add('active');
  currentScreen = prev;
}

// ═══════════ ONBOARDING ═══════════
function onbNext() {
  const slides = document.getElementById('onbSlides');
  const dots = document.querySelectorAll('.onb-dot');
  const btnText = document.getElementById('onbBtnText');
  if (onbSlide < 2) {
    onbSlide++;
    slides.style.transform = `translateX(-${onbSlide * 100}%)`;
    dots.forEach((d,i) => d.classList.toggle('active', i === onbSlide));
    btnText.textContent = onbSlide === 2 ? 'Get Started' : 'Next';
  } else {
    goTo('screen-auth');
  }
}

// ═══════════ OTP ═══════════
function otpNext(el, idx) {
  const inputs = document.querySelectorAll('.otp-input');
  if (el.value.length === 1 && idx < 4) inputs[idx + 1].focus();
  // Auto-verify if all filled
  const all = Array.from(inputs).every(i => i.value.length === 1);
  if (all) setTimeout(() => doVerify(), 400);
}

// ═══════════ AUTH ═══════════
function switchAuth(login) {
  document.getElementById('loginView').style.display = login ? '' : 'none';
  document.getElementById('registerView').style.display = login ? 'none' : '';
}

function enterCustomerApp(message = 'Welcome back 👋', redirect = 'dashboard.php') {
  closeAllModals();
  window.location.href = redirect || 'dashboard.php';
  return;
}

async function doLogin(event) {
  if (event) event.preventDefault();
  const errorBox  = document.getElementById('authError');
  const errorText = document.getElementById('authErrorText');
  const identifier = document.getElementById('loginEmail').value.trim();
  const password   = document.getElementById('loginPass').value;

  errorBox.classList.remove('show');

  if (!identifier || !password) {
    errorText.textContent = 'Enter your email/phone and password.';
    errorBox.classList.add('show');
    return;
  }

  try {
    const body = new FormData();
    body.append('email', identifier);
    body.append('password', password);

    const json = await idibiaPost('login-handler.php', body);
    if (json.success) {
      enterCustomerApp(json.data?.first_name ? `Welcome back, ${json.data.first_name} 👋` : 'Welcome back 👋', json.data?.redirect);
    } else {
      errorText.textContent = json.data?.message || 'Login failed. Please try again.';
      errorBox.classList.add('show');
    }
  } catch (err) {
    errorText.textContent = 'Could not reach Idibia right now. Please check your connection and try again.';
    errorBox.classList.add('show');
  }
}

// ═══════════ TAB SWITCHING ═══════════
function switchTab(name, sideBtn, bnavId) {
  document.querySelectorAll('.tab-view').forEach(t => t.classList.remove('active'));
  const target = document.getElementById('tab-' + name);
  if (target) target.classList.add('active');

  document.querySelectorAll('.sidebar-btn').forEach(b => b.classList.remove('active'));
  if (sideBtn) sideBtn.classList.add('active');
  else {
    // Find matching sidebar btn
    document.querySelectorAll('.sidebar-btn').forEach(b => {
      if (b.textContent.trim().toLowerCase().includes(name)) b.classList.add('active');
    });
  }

  document.querySelectorAll('.bnav-btn').forEach(b => b.classList.remove('active'));
  const id = bnavId || name;
  const bnav = document.getElementById('bnav-' + id);
  if (bnav) bnav.classList.add('active');
}

// ═══════════ HOME PANEL ═══════════
function swapLocations() {
  const pickup = document.getElementById('pickupInput');
  const dropoff = document.getElementById('dropoffInput');
  const temp = pickup.value;
  pickup.value = dropoff.value;
  dropoff.value = temp;
  showToast('Locations swapped');
}

function setSched(btn, mode) {
  document.querySelectorAll('.sched-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

function selCat(el, name) {
  document.querySelectorAll('.cat-card').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  selectedCategory = name;
  showToast(`"${name}" selected`);
}

function toggleFlag(el) {
  el.classList.toggle('active');
  const toggle = el.querySelector('.toggle-wrap');
  toggle.classList.toggle('on');
  const title = el.querySelector('.sf-title').textContent;
  const on = toggle.classList.contains('on');
  showToast(on ? `"${title}" enabled` : `"${title}" disabled`);
}

function findRider() {
  const pickup = document.getElementById('pickupInput').value;
  const dropoff = document.getElementById('dropoffInput').value;
  if (!dropoff.trim()) {
    showToast('Please enter a drop-off location');
    document.getElementById('dropoffInput').focus();
    return;
  }
  showToast('Searching for nearby riders...');
  setTimeout(() => {
    showToast('Amina K. is on the way! ETA: 4 min');
    setTimeout(() => goTo('screen-tracking'), 1500);
  }, 2000);
}

// ═══════════ ACTIVITY FILTERS ═══════════
function filterTrips(btn, status) {
  document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  const cards = document.querySelectorAll('.trip-card');
  cards.forEach(card => {
    if (status === 'all') {
      card.style.display = '';
    } else {
      card.style.display = card.dataset.status === status ? '' : 'none';
    }
  });
}

// ═══════════ MODALS ═══════════
function openModal(id) {
  document.getElementById('modal-' + id).classList.add('show');
}

function closeModal(e, id) {
  if (!e || e.target.classList.contains('modal-overlay')) {
    const modal = document.getElementById('modal-' + id);
    if (modal) modal.classList.remove('show');
  }
}

function closeAllModals() {
  document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('show'));
}

function closeModalAndGoHome() {
  closeAllModals();
  goBack();
  setTimeout(() => {
    switchTab('home', null, 'home');
    showToast('Thanks for your feedback, John!');
  }, 100);
}

function confirmLogout() { openModal('logout'); }

function doLogout() {
  closeAllModals();
  showToast('Signed out successfully');
  setTimeout(() => location.reload(), 1200);
}

// ═══════════ STAR RATING ═══════════
const ratingLabels = ['', 'Poor 😕', 'Fair 😐', 'Good 🙂', 'Great 😊', 'Amazing! 🌟'];

function rateStar(num) {
  currentRating = num;
  const stars = document.querySelectorAll('#starRating .star-btn');
  stars.forEach((btn, idx) => btn.classList.toggle('active', idx < num));
  const label = document.getElementById('ratingLabel');
  if (label) label.textContent = ratingLabels[num];
}

function toggleChip(el) {
  el.classList.toggle('active');
}

// ═══════════ SCHEDULE MODAL ═══════════
function buildDateGrid() {
  const grid = document.getElementById('dateGrid');
  if (!grid) return;
  const days = ['S','M','T','W','T','F','S'];
  const today = new Date();
  grid.innerHTML = '';
  for (let i = 0; i < 7; i++) {
    const d = new Date(today);
    d.setDate(today.getDate() + i);
    const el = document.createElement('div');
    el.className = 'date-day' + (i === 0 ? ' today active' : '');
    el.innerHTML = `<div class="date-day-name">${days[d.getDay()]}</div><div class="date-day-num">${d.getDate()}</div>`;
    el.onclick = () => {
      document.querySelectorAll('.date-day').forEach(d => d.classList.remove('active'));
      el.classList.add('active');
    };
    grid.appendChild(el);
  }
}

function confirmSchedule() {
  closeAllModals();
  const schedBtn = document.getElementById('schedLater');
  if (schedBtn) schedBtn.classList.add('active');
  const immBtn = document.getElementById('schedImmediate');
  if (immBtn) immBtn.classList.remove('active');
  showToast('Pickup scheduled successfully!');
}

// ═══════════ ETA COUNTDOWN (tracking screen) ═══════════
function startEtaCountdown() {
  let mins = 12;
  etaInterval = setInterval(() => {
    const el = document.getElementById('etaMinutes');
    if (el && mins > 0) {
      mins--;
      el.textContent = mins;
      if (mins === 0) {
        clearInterval(etaInterval);
        showToast('Package delivered!');
      }
    }
  }, 8000); // Every 8 seconds for demo
}

// ═══════════ TOAST ═══════════
let toastTimeout = null;
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  if (toastTimeout) clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => t.classList.remove('show'), 3000);
}

// ═══════════ KEYBOARD / ACCESSIBILITY ═══════════
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeAllModals();
});

// \u2500\u2500 REGISTRATION \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
async function doRegister(event) {
  if (event) event.preventDefault();
  const btn       = document.getElementById('regBtn');
  const errorBox  = document.getElementById('regError');
  const errorText = document.getElementById('regErrorText');

  const full_name = document.getElementById('regName').value.trim();
  const phone     = document.getElementById('regPhone').value.trim();
  const email     = document.getElementById('regEmail').value.trim();
  const password  = document.getElementById('regPassword').value;
  const terms     = document.getElementById('termsCheck').checked;
  const nonce     = document.getElementById('regNonce').value;

  errorBox.classList.remove('show');

  if ( ! full_name || ! phone || ! email || ! password ) {
    errorText.textContent = 'Please fill in all fields.';
    errorBox.classList.add('show');
    return;
  }

  btn.disabled    = true;
  btn.textContent = 'Creating account\u2026';

  try {
    const body = new FormData();
    body.append( '_nonce',    nonce );
    body.append( 'full_name', full_name );
    body.append( 'phone',     phone );
    body.append( 'email',     email );
    body.append( 'password',  password );
    body.append( 'terms',     terms ? '1' : '' );

    const json = await idibiaPost( 'register-handler.php', body );

    if ( json.success ) {
      enterCustomerApp( json.data?.first_name ? `Welcome, ${json.data.first_name}! 🎉` : 'Account created successfully.', json.data?.redirect );
    } else {
      errorText.textContent = json.data?.message || 'Registration failed. Please try again.';
      errorBox.classList.add('show');
    }
  } catch ( err ) {
    errorText.textContent = 'Could not reach Idibia right now. Please check your connection and try again.';
    errorBox.classList.add('show');
  } finally {
    btn.disabled  = false;
    btn.innerHTML = 'Create Account &amp; Verify <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
  }
}

// \u2500\u2500 RESEND VERIFICATION EMAIL \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
async function resendCode() {
  showToast( 'Sending a new code\u2026' );
  try {
    const body = new FormData();
    body.append( '_nonce', IDIBIA_VERIFY_NONCE );

    const json = await idibiaPost( 'resend-code.php', body );
    if ( json.success ) {
      showToast( 'New code sent! Check your inbox.' );
    } else {
      showToast( json.data?.message || 'Could not resend. Try again.' );
    }
  } catch {
    showToast( 'Could not reach Idibia right now. Try again.' );
  }
}

// \u2500\u2500 EMAIL VERIFICATION \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
async function doVerify() {
  const btn       = document.getElementById('verifyBtn');
  const errorBox  = document.getElementById('verifyError');
  const errorText = document.getElementById('verifyErrorText');
  const inputs    = document.querySelectorAll('.otp-input');

  const code = Array.from(inputs).map(i => i.value.trim()).join('');

  errorBox.classList.remove('show');

  if ( code.length < 5 ) {
    errorText.textContent = 'Enter all 5 digits of the code.';
    errorBox.classList.add('show');
    return;
  }

  btn.disabled    = true;
  btn.textContent = 'Verifying\u2026';

  try {
    const body = new FormData();
    body.append( '_nonce', IDIBIA_VERIFY_NONCE );
    body.append( 'code', code );

    const json = await idibiaPost( 'verify-handler.php', body );

    if ( json.success ) {
      const name = json.data?.first_name || '';
      inputs.forEach( i => i.value = '' );
      enterCustomerApp( name ? `Welcome, ${name}! 🎉` : 'Email verified! Welcome.' );
    } else {
      errorText.textContent = json.data?.message || 'Verification failed. Please try again.';
      errorBox.classList.add('show');
    }
  } catch ( err ) {
    errorText.textContent = 'Could not reach Idibia right now. Please check your connection and try again.';
    errorBox.classList.add('show');
  } finally {
    btn.disabled  = false;
    btn.innerHTML = 'Verify &amp; Get Started <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><polyline points="20 6 9 17 4 12"/></svg>';
  }
}
</script>
</body>
</html>