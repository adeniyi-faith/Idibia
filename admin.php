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
<style>
*{box-sizing:border-box;margin:0;padding:0}body{min-height:100vh;display:grid;place-items:center;background:#0B1628;color:#fff;font-family:'DM Sans',sans-serif;padding:20px}.card{width:min(100%,420px);background:#fff;color:#0B1628;border-radius:24px;padding:28px;box-shadow:0 24px 80px rgba(0,0,0,.28)}h1{font-family:'Syne',sans-serif;font-size:28px;margin-bottom:8px}.sub{color:#5A6B85;margin-bottom:24px;line-height:1.5}.field{margin-bottom:14px}.field label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#5A6B85;margin-bottom:7px}.field input{width:100%;height:48px;border:1.5px solid #E8ECF3;border-radius:12px;padding:0 14px;font:inherit;outline:none}.field input:focus{border-color:#F5C842}.btn{width:100%;height:50px;border:0;border-radius:14px;background:#F5C842;color:#0B1628;font-weight:800;font-size:15px;cursor:pointer}.err{display:none;margin:0 0 14px;padding:10px 12px;border-radius:10px;background:rgba(232,72,74,.1);color:#E8484A;font-size:13px}.err.show{display:block}.brand{width:44px;height:44px;border-radius:14px;background:#F5C842;display:grid;place-items:center;color:#0B1628;font-weight:900;margin-bottom:18px}</style>
</head>
<body>
  <form class="card" id="adminLoginForm">
    <div class="brand">ID</div>
    <h1>Admin Login</h1>
    <p class="sub">Sign in with a WordPress administrator account to manage Idibia operations.</p>
    <div class="err" id="adminLoginError"></div>
    <div class="field"><label for="adminLogin">Email, username, or phone</label><input id="adminLogin" name="login" autocomplete="username" required></div>
    <div class="field"><label for="adminPassword">Password</label><input id="adminPassword" name="password" type="password" autocomplete="current-password" required></div>
    <button class="btn" id="adminLoginBtn">Sign In</button>
  </form>
<script>
document.getElementById('adminLoginForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  const btn = document.getElementById('adminLoginBtn');
  const err = document.getElementById('adminLoginError');
  err.classList.remove('show');
  btn.disabled = true;
  btn.textContent = 'Signing in…';
  try {
    const body = new FormData(event.currentTarget);
    body.append('action', 'admin_login');
    const response = await fetch(window.location.href, { method: 'POST', body, credentials: 'same-origin' });
    const rawText = await response.text();
    let data;
    try { data = JSON.parse(rawText); } catch (e) { console.error('Raw response:', rawText); throw new Error('Invalid server response'); }
    if (data.success) window.location.href = data.data.redirect || '/admin.php';
    else { err.textContent = data.data?.message || 'Login failed.'; err.classList.add('show'); }
  } catch (e) { err.textContent = 'Could not reach Idibia right now. Please try again.'; err.classList.add('show'); }
  finally { btn.disabled = false; btn.textContent = 'Sign In'; }
});
</script>
</body>
</html>
    <?php
    exit;
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
<style>
:root {
  --navy:#0B1628;--navy-mid:#162035;--navy-light:#1E2D47;
  --gold:#F5C842;--gold-dark:#C9A224;
  --slate:#8A9AB8;--slate-light:#B8C5D8;
  --white:#FFFFFF;--surface:#F4F6FA;--surface-2:#E8ECF3;
  --danger:#E8484A;--success:#22C47A;--info:#4A9EFF;--warn:#F5A623;
  --text-primary:#0B1628;--text-secondary:#5A6B85;--text-muted:#8A9AB8;
  --radius:16px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;overflow:hidden;-webkit-font-smoothing:antialiased;}
body{font-family:'DM Sans',sans-serif;background:var(--surface);color:var(--text-primary);}
h1,h2,h3,h4{font-family:'Syne',sans-serif;font-weight:700;}
svg{display:block;}
input,select,textarea{font-family:'DM Sans',sans-serif;}
button{cursor:pointer;font-family:'DM Sans',sans-serif;}

#app{width:100%;height:100%;display:flex;flex-direction:column;overflow:hidden;position:relative;}
@media(min-width:900px){#app{flex-direction:row;}}

/* SIDEBAR OVERLAY (MOBILE) */
.sidebar-overlay {
  position: fixed; inset: 0; background: rgba(11,22,40,0.4); z-index: 999;
  opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
}
.sidebar-overlay.open { opacity: 1; pointer-events: auto; }

/* SIDEBAR */
.sidebar {
  position: fixed; top: 0; left: -280px; bottom: 0; width: 260px;
  background: var(--navy); display: flex; flex-direction: column;
  padding: 20px 14px; overflow-y: auto; overflow-x: hidden;
  z-index: 1000; transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  gap: 2px;
}
.sidebar.open { left: 0; box-shadow: 4px 0 24px rgba(0,0,0,0.15); }
.sidebar::-webkit-scrollbar{display:none;}
.sidebar { scrollbar-width: none; }

@media(min-width:900px) {
  .sidebar { position: relative; left: 0; width: 230px; z-index: 1; transition: none; flex-shrink: 0; }
  .sidebar-overlay { display: none !important; }
}

.sidebar-logo {
  display: flex; align-items: center; gap: 10px; font-family: 'Syne', sans-serif;
  font-size: 18px; font-weight: 800; color: var(--white); margin-bottom: 28px;
  padding: 0 8px; flex-shrink: 0;
}
.brand-icon {
  width: 34px; height: 34px; background: var(--gold); border-radius: 10px;
  display: flex; align-items: center; justify-content: center; color: var(--navy); flex-shrink: 0;
}
.nav-section-label {
  display: block; font-size: 10px; letter-spacing: 1px; color: var(--slate);
  text-transform: uppercase; padding: 10px 12px 4px; font-weight: 600;
}
.nav-btn {
  display: flex; align-items: center; gap: 9px; padding: 9px 10px; background: none;
  border: none; border-radius: 10px; font-size: 13px; color: var(--slate);
  transition: all 0.18s; white-space: nowrap; flex-shrink: 0; width: 100%; text-align: left;
}
.nav-btn:hover { background: var(--navy-light); color: var(--slate-light); }
.nav-btn.active { background: var(--navy-mid); color: var(--white); }
.nav-btn svg { width: 17px; height: 17px; flex-shrink: 0; }
.nav-badge { font-size: 10px; background: var(--danger); color: #fff; padding: 2px 7px; border-radius: 10px; margin-left: auto; font-weight: 700; }
.nav-warn-badge { font-size: 10px; background: var(--warn); color: #fff; padding: 2px 7px; border-radius: 10px; margin-left: auto; font-weight: 700; }
.sidebar-bottom {
  display: flex; flex-direction: column; gap: 2px; margin-top: auto;
  padding-top: 12px; border-top: 1px solid var(--navy-light);
}

/* MAIN */
.main{flex:1;overflow-y:auto;overflow-x:hidden;display:flex;flex-direction:column;}
.main::-webkit-scrollbar{width:4px;}
.main::-webkit-scrollbar-thumb{background:var(--surface-2);border-radius:2px;}
.panel{display:none;padding:16px;flex:1;}
.panel.active{display:block;}
@media(min-width:600px){.panel{padding:28px;}}

/* TOPBAR */
.topbar{background:var(--white);border-bottom:1.5px solid var(--surface-2);padding:12px 16px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;position:sticky;top:0;z-index:10;gap:10px;}
@media(min-width:600px){.topbar{padding:12px 20px;}}
.topbar-left{display:flex;align-items:center;gap:12px;}
.mobile-menu-btn {
  display: flex; align-items: center; justify-content: center; background: none;
  border: none; color: var(--text-primary); padding: 4px; margin-left: -4px;
}
@media(min-width:900px){.mobile-menu-btn{display:none;}}

.topbar-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
@media(min-width:400px){.topbar-title{font-size:15px;}}
.topbar-sub{font-size:11px;color:var(--text-muted);display:none;}
@media(min-width:500px){.topbar-sub{display:block;font-size:12px;}}

.topbar-actions{display:flex;gap:6px;align-items:center;}
@media(min-width:600px){.topbar-actions{gap:8px;}}
.topbar-search{background:var(--surface);border:1.5px solid var(--surface-2);border-radius:10px;padding:7px 12px;font-size:13px;color:var(--text-primary);outline:none;width:100px;transition:width 0.2s;}
.topbar-search:focus{border-color:var(--info);background:#fff;width:140px;}
@media(min-width:600px){.topbar-search{width:160px;}.topbar-search:focus{width:200px;}}

.topbar-btn{width:34px;height:34px;border-radius:9px;background:var(--surface);border:1.5px solid var(--surface-2);display:flex;align-items:center;justify-content:center;color:var(--text-secondary);transition:all 0.15s;position:relative;flex-shrink:0;}
.topbar-btn:hover{background:var(--navy);color:var(--white);}
.notif-dot{position:absolute;top:6px;right:6px;width:7px;height:7px;background:var(--danger);border-radius:50%;border:1.5px solid var(--white);}

/* PAGE HEADER */
.page-header{margin-bottom:20px;}
.page-title{font-size:20px;color:var(--text-primary);margin-bottom:3px;}
@media(min-width:600px){.page-title{font-size:22px;}}
.page-sub{font-size:12px;color:var(--text-secondary);}
@media(min-width:600px){.page-sub{font-size:13px;}}

/* METRICS */
.metrics-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;}
@media(max-width:380px){.metrics-grid{grid-template-columns:1fr;}}
@media(min-width:700px){.metrics-grid.four{grid-template-columns:repeat(4,1fr);}}
@media(min-width:700px){.metrics-grid.three{grid-template-columns:repeat(3,1fr);}}
.metric-card{background:var(--white);border-radius:14px;padding:16px;border:1.5px solid var(--surface-2);}
.metric-label{font-size:11px;color:var(--text-muted);margin-bottom:6px;font-weight:500;letter-spacing:0.3px;}
.metric-value{font-family:'Syne',sans-serif;font-size:22px;font-weight:700;color:var(--text-primary);}
@media(min-width:600px){.metric-value{font-size:24px;}}
.metric-delta{font-size:11px;margin-top:3px;}
.metric-delta.up{color:var(--success);}
.metric-delta.down{color:var(--danger);}
.metric-delta.neutral{color:var(--text-muted);}

/* SECTION CARD */
.scard{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--surface-2);overflow:hidden;margin-bottom:18px;}
.scard-header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--surface);flex-wrap:wrap;gap:8px;}
@media(min-width:600px){.scard-header{padding:15px 18px;}}
.scard-header h3{font-size:14px;color:var(--text-primary);}
.scard-action{font-size:12px;color:var(--info);background:none;border:none;padding:0;}
.scard-action:hover{text-decoration:underline;}

/* LIST ITEMS */
.list-item{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--surface);transition:background 0.12s;flex-wrap:wrap;}
@media(min-width:500px){.list-item{flex-wrap:nowrap;padding:13px 18px;}}
.list-item:last-child{border-bottom:none;}
.list-item:hover{background:var(--surface);}
.avatar{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;flex-shrink:0;}
.item-info{flex:1;min-width:140px;}
.item-name{font-size:13px;font-weight:500;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.item-meta{font-size:11px;color:var(--text-muted);margin-top:2px;line-height:1.4;}
.item-actions{display:flex;gap:6px;flex-shrink:0;width:100%;margin-top:6px;}
@media(min-width:500px){.item-actions{width:auto;margin-top:0;}}
.btn-sm{height:30px;padding:0 12px;border-radius:8px;border:none;font-size:11px;font-weight:600;transition:all 0.15s;flex:1;}
@media(min-width:500px){.btn-sm{flex:none;}}
.btn-approve{background:rgba(34,196,122,0.1);color:var(--success);}
.btn-approve:hover{background:var(--success);color:#fff;}
.btn-reject{background:rgba(232,72,74,0.1);color:var(--danger);}
.btn-reject:hover{background:var(--danger);color:#fff;}
.btn-view{background:rgba(74,158,255,0.1);color:var(--info);}
.btn-view:hover{background:var(--info);color:#fff;}
.btn-suspend{background:rgba(245,166,35,0.1);color:var(--warn);}
.btn-suspend:hover{background:var(--warn);color:#fff;}

/* STATUS BADGES */
.badge{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:6px;font-size:10px;font-weight:700;letter-spacing:0.3px;white-space:nowrap;}
.badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
.badge-success{background:rgba(34,196,122,0.1);color:var(--success);}
.badge-info{background:rgba(74,158,255,0.1);color:var(--info);}
.badge-danger{background:rgba(232,72,74,0.1);color:var(--danger);}
.badge-warn{background:rgba(245,166,35,0.1);color:var(--warn);}
.badge-muted{background:var(--surface-2);color:var(--text-muted);}

/* MAP */
.ops-map{height:200px;background:#C8DBE8;border-radius:var(--radius);position:relative;overflow:hidden;margin-bottom:18px;}
@media(min-width:600px){.ops-map{height:220px;}}
.ops-map-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(11,22,40,0.06) 1px,transparent 1px),linear-gradient(90deg,rgba(11,22,40,0.06) 1px,transparent 1px);background-size:24px 24px;}
.ops-rider{position:absolute;width:24px;height:24px;background:var(--navy);border:2px solid var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;color:var(--gold);box-shadow:0 2px 8px rgba(0,0,0,0.25);transition:all 2s;}
.map-legend{position:absolute;top:8px;left:8px;background:rgba(255,255,255,0.95);border-radius:8px;padding:6px 11px;font-size:10px;color:var(--text-primary);box-shadow:0 2px 6px rgba(0,0,0,0.1);}

/* REVENUE BARS */
.rev-bars{padding:16px;}
@media(min-width:600px){.rev-bars{padding:18px;}}
.rev-row{display:flex;align-items:center;gap:8px;margin-bottom:10px;}
.rev-label{width:28px;font-size:10px;color:var(--text-muted);text-align:right;flex-shrink:0;}
@media(min-width:600px){.rev-label{font-size:11px;}}
.rev-track{flex:1;height:24px;background:var(--surface);border-radius:6px;overflow:hidden;}
.rev-fill{height:100%;border-radius:6px;background:var(--navy);display:flex;align-items:center;padding:0 8px;transition:width 1s cubic-bezier(0.4,0,0.2,1);}
.rev-fill span{font-size:9px;font-weight:700;color:var(--gold);white-space:nowrap;}
@media(min-width:600px){.rev-fill span{font-size:10px;}}
.rev-fill.today{background:var(--navy);}

/* DRIVER DETAIL PANEL */
.driver-detail{display:none;position:absolute;inset:0;background:var(--surface);z-index:50;overflow-y:auto;padding:16px;}
@media(min-width:600px){.driver-detail{padding:20px;}}
.driver-detail.open{display:block;}
.detail-header{display:flex;align-items:center;gap:12px;margin-bottom:20px;}
.detail-back{background:none;border:none;color:var(--info);font-size:13px;display:flex;align-items:center;gap:4px;}
.detail-back:hover{text-decoration:underline;}

/* TABS */
.tabs{display:flex;flex-wrap:wrap;gap:4px;background:var(--surface);border-radius:10px;padding:4px;margin-bottom:16px;}
.tab{flex:1;min-width:80px;padding:7px 0;border-radius:7px;border:none;background:none;font-size:12px;font-weight:500;color:var(--text-muted);transition:all 0.15s;}
.tab.active{background:var(--white);color:var(--text-primary);box-shadow:0 1px 4px rgba(0,0,0,0.08);}

/* FILTERS */
.filter-row{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;}
.filter-btn{padding:6px 10px;border-radius:8px;border:1.5px solid var(--surface-2);background:var(--white);font-size:11px;color:var(--text-secondary);transition:all 0.15s;flex-grow:1;text-align:center;}
@media(min-width:400px){.filter-btn{flex-grow:0;font-size:12px;padding:6px 12px;}}
.filter-btn.active{border-color:var(--navy);background:var(--navy);color:var(--white);}
.filter-select{padding:6px 10px;border-radius:8px;border:1.5px solid var(--surface-2);background:var(--white);font-size:12px;color:var(--text-primary);outline:none;}

/* SEARCH BAR */
.panel-search{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;}
.panel-search input, .panel-search select {flex:1 1 100%; background:var(--white);border:1.5px solid var(--surface-2);border-radius:10px;padding:8px 12px;font-size:13px;color:var(--text-primary);outline:none;}
.panel-search input:focus{border-color:var(--info);}
.panel-search button{flex:1 1 100%; padding:9px 14px; border-radius:10px;border:none;background:var(--navy);color:var(--white);font-size:12px;font-weight:600;}
.loading-state,.empty-state{padding:28px 18px;text-align:center;color:var(--text-muted);font-size:13px;}
.pagination{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--surface);font-size:12px;color:var(--text-secondary);}
.pagination button{height:30px;padding:0 12px;border:1px solid var(--surface-2);border-radius:8px;background:var(--white);color:var(--text-primary);font-weight:700;}
.pagination button:disabled{opacity:.45;cursor:not-allowed;}
@media(min-width:600px){
  .panel-search{flex-wrap:nowrap;}
  .panel-search input {flex:1;}
  .panel-search select, .panel-search button{flex:none;}
}


.kyc-detail-section{background:var(--white);border:1.5px solid var(--surface-2);border-radius:14px;padding:14px;margin:14px 0;}
.kyc-detail-section h4{font-size:13px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--surface);}
.kyc-detail-grid{display:grid;grid-template-columns:1fr;gap:10px;}
@media(min-width:650px){.kyc-detail-grid{grid-template-columns:repeat(2,1fr);}}
@media(min-width:980px){.kyc-detail-grid.three{grid-template-columns:repeat(3,1fr);}}
.kyc-detail-field{background:var(--surface);border-radius:10px;padding:10px;}
.kyc-detail-label{font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px;}
.kyc-detail-value{font-size:12px;font-weight:600;color:var(--text-primary);line-height:1.35;word-break:break-word;}
.kyc-file-grid{display:grid;grid-template-columns:1fr;gap:10px;}
@media(min-width:600px){.kyc-file-grid{grid-template-columns:repeat(2,1fr);}}
@media(min-width:900px){.kyc-file-grid{grid-template-columns:repeat(3,1fr);}}
.kyc-file-card{border:1.5px solid var(--surface-2);border-radius:12px;background:var(--white);overflow:hidden;}
.kyc-file-preview{height:110px;background:var(--surface);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:12px;text-align:center;padding:10px;}
.kyc-file-preview img{width:100%;height:100%;object-fit:cover;}
.kyc-file-body{padding:10px;}
.kyc-file-title{font-size:12px;font-weight:700;margin-bottom:4px;}
.kyc-file-meta{font-size:11px;color:var(--text-muted);margin-bottom:8px;}
.kyc-decision-list{display:grid;grid-template-columns:1fr;gap:8px;}
@media(min-width:700px){.kyc-decision-list{grid-template-columns:repeat(2,1fr);}}
.kyc-decision-item{display:flex;align-items:flex-start;gap:8px;padding:10px;border-radius:10px;background:var(--surface);font-size:12px;line-height:1.35;}
.kyc-decision-item.ok{color:var(--success);}
.kyc-decision-item.warn{color:var(--warn);}
.kyc-decision-item.bad{color:var(--danger);}

/* KYC STEPPER */
.kyc-steps{display:flex;gap:4px;margin-bottom:16px;}
.kyc-step{flex:1;height:4px;border-radius:2px;background:var(--surface-2);}
.kyc-step.done{background:var(--success);}
.kyc-step.active{background:var(--info);}

/* NOTIFICATION PANEL */
.notif-panel{position:absolute;top:56px;right:10px;left:10px;background:var(--white);border-radius:14px;border:1.5px solid var(--surface-2);box-shadow:0 8px 32px rgba(11,22,40,0.12);z-index:100;display:none;}
@media(min-width:400px){.notif-panel{left:auto;right:16px;width:300px;}}
.notif-panel.open{display:block;}
.notif-header{padding:12px 16px;border-bottom:1px solid var(--surface);display:flex;justify-content:space-between;align-items:center;}
.notif-header h4{font-size:13px;}
.notif-item{padding:12px 16px;border-bottom:1px solid var(--surface);display:flex;gap:10px;}
.notif-item:last-child{border-bottom:none;}
.notif-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px;}

/* SETTINGS */
.form-group{margin-bottom:16px;}
.form-label{font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:6px;display:block;}
.form-input{width:100%;padding:9px 12px;border-radius:10px;border:1.5px solid var(--surface-2);font-size:13px;color:var(--text-primary);background:var(--white);outline:none;transition:border 0.15s;}
.form-input:focus{border-color:var(--info);}
.form-row{display:grid;grid-template-columns:1fr;gap:12px;}
@media(min-width:600px){.form-row{grid-template-columns:1fr 1fr;}}
.settings-section{background:var(--white);border-radius:14px;border:1.5px solid var(--surface-2);padding:16px;margin-bottom:16px;}
@media(min-width:600px){.settings-section{padding:18px;}}
.settings-section h4{font-size:13px;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--surface);}
.toggle-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--surface);gap:10px;}
.toggle-row:last-child{border-bottom:none;}
.toggle-label{font-size:13px;color:var(--text-primary);line-height:1.2;margin-bottom:2px;}
.toggle-sub{font-size:11px;color:var(--text-muted);line-height:1.3;}
.toggle{width:40px;height:22px;background:var(--surface-2);border-radius:11px;border:none;position:relative;transition:background 0.2s;flex-shrink:0;}
.toggle.on{background:var(--success);}
.toggle::after{content:'';position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;transition:transform 0.2s;box-shadow:0 1px 3px rgba(0,0,0,0.2);}
.toggle.on::after{transform:translateX(18px);}
.btn-primary{background:var(--navy);color:var(--white);padding:10px 22px;border-radius:10px;border:none;font-size:13px;font-weight:600;transition:all 0.15s;width:100%;}
@media(min-width:500px){.btn-primary{width:auto;}}
.btn-primary:hover{background:#0f2040;}

/* TOAST */
.toast{position:fixed;bottom:20px;left:50%;transform:translate(-50%, 20px);background:var(--navy);color:var(--white);padding:11px 22px;border-radius:30px;font-size:12px;opacity:0;pointer-events:none;transition:all 0.3s;z-index:9999;box-shadow:0 4px 24px rgba(11,22,40,0.3);white-space:nowrap;}
.toast.show{opacity:1;transform:translate(-50%, 0);}
@media(min-width:600px){.toast{left:auto;right:20px;transform:translateY(20px);}.toast.show{transform:translateY(-8px);}}

/* MODALS */
.modal-overlay{position:fixed;inset:0;background:rgba(11,22,40,0.6);z-index:2000;display:none;align-items:center;justify-content:center;padding:16px;}
.modal-overlay.open{display:flex;}
.modal{background:var(--white);border-radius:var(--radius);width:100%;max-width:440px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 12px 48px rgba(0,0,0,0.2);}
.modal-header{padding:16px 20px;border-bottom:1px solid var(--surface);display:flex;justify-content:space-between;align-items:center;flex-shrink:0;}
.modal-body{padding:20px;overflow-y:auto;}
.modal-footer{padding:14px 20px;border-top:1px solid var(--surface);display:flex;gap:8px;justify-content:flex-end;flex-shrink:0;}
</style>
</head>
<body>
<div id="app">

<!-- SIDEBAR OVERLAY FOR MOBILE -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="brand-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
    </div>
    Idibia
  </div>

  <div class="nav-section-label">Main</div>
  <button class="nav-btn active" onclick="nav('overview',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Overview
  </button>
  <button class="nav-btn" onclick="nav('kyc',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    KYC Queue <span class="nav-badge" id="kyc-badge">7</span>
  </button>
  <button class="nav-btn" onclick="nav('ops',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
    Live Ops
  </button>
  <button class="nav-btn" onclick="nav('trips',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
    Deliveries
  </button>

  <div class="nav-section-label" style="margin-top:8px">Finance</div>
  <button class="nav-btn" onclick="nav('revenue',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    Revenue
  </button>
  <button class="nav-btn" onclick="nav('payouts',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    Payouts
  </button>

  <div class="nav-section-label" style="margin-top:8px">People</div>
  <button class="nav-btn" onclick="nav('drivers',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
    Drivers
  </button>
  <button class="nav-btn" onclick="nav('users',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Customers
  </button>
  <button class="nav-btn" onclick="nav('disputes',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    Disputes <span class="nav-warn-badge">5</span>
  </button>

  <div class="sidebar-bottom">
    <div class="nav-section-label" style="margin:0 0 4px;">System</div>
    <button class="nav-btn" onclick="nav('settings',this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
      Settings
    </button>
    <button class="nav-btn" onclick="toast('Logged out')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </button>
  </div>
</div>

<!-- MAIN -->
<div class="main">
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-left">
      <button class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Open Menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-title" id="topbar-title">Platform Overview</div>
        <div class="topbar-sub" id="topbar-sub">Live · Sat Apr 11, 2026</div>
      </div>
    </div>
    <div class="topbar-actions">
      <input class="topbar-search" placeholder="Search…" oninput="handleSearch(this.value)">
      <button class="topbar-btn" onclick="toggleNotif()" title="Notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="notif-dot"></span>
      </button>
      <button class="topbar-btn" title="Export" onclick="toast('Exporting report…')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      </button>
    </div>
  </div>

  <!-- NOTIFICATION PANEL -->
  <div class="notif-panel" id="notifPanel">
    <div class="notif-header">
      <h4>Notifications</h4>
      <button onclick="markAllRead()" style="font-size:11px;color:var(--info);background:none;border:none;">Mark all read</button>
    </div>
    <div class="notif-item">
      <div class="notif-icon" style="background:rgba(232,72,74,0.1)">🚨</div>
      <div><div style="font-size:12px;font-weight:600">7 KYC applications pending</div><div style="font-size:11px;color:var(--text-muted)">Oldest: 6h ago</div></div>
    </div>
    <div class="notif-item">
      <div class="notif-icon" style="background:rgba(245,166,35,0.1)">⚠️</div>
      <div><div style="font-size:12px;font-weight:600">Driver Bayo A. has 3 complaints</div><div style="font-size:11px;color:var(--text-muted)">Review recommended</div></div>
    </div>
    <div class="notif-item">
      <div class="notif-icon" style="background:rgba(34,196,122,0.1)">✅</div>
      <div><div style="font-size:12px;font-weight:600">₦2.1M revenue today</div><div style="font-size:11px;color:var(--text-muted)">+14.5% vs yesterday</div></div>
    </div>
    <div class="notif-item">
      <div class="notif-icon" style="background:rgba(74,158,255,0.1)">📦</div>
      <div><div style="font-size:12px;font-weight:600">Dispute #D-0048 unresolved 2 days</div><div style="font-size:11px;color:var(--text-muted)">Escalation needed</div></div>
    </div>
  </div>

  <!-- OVERVIEW -->
  <div class="panel active" id="panel-overview">
    <div class="page-header">
      <h2 class="page-title">Platform Overview</h2>
      <div class="page-sub">Live snapshot · Port Harcourt metro</div>
    </div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">ACTIVE RIDERS</div><div class="metric-value" id="overviewActiveDrivers">--</div><div class="metric-delta neutral" id="overviewOnlineDrivers">Loading…</div></div>
      <div class="metric-card"><div class="metric-label">TRIPS TODAY</div><div class="metric-value" id="overviewTripsToday">--</div><div class="metric-delta neutral" id="overviewTripsDelta">Loading…</div></div>
      <div class="metric-card"><div class="metric-label">REVENUE TODAY</div><div class="metric-value" id="overviewRevenueToday">--</div><div class="metric-delta neutral">Platform commission</div></div>
      <div class="metric-card"><div class="metric-label">KYC PENDING</div><div class="metric-value" style="color:var(--danger)" id="overviewKycPending">--</div><div class="metric-delta down">Needs attention</div></div>
    </div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">COMPLETION RATE</div><div class="metric-value" id="overviewCompletionRate">--</div><div class="metric-delta neutral">Last 24h</div></div>
      <div class="metric-card"><div class="metric-label">AVG PICKUP TIME</div><div class="metric-value" id="overviewAvgPickup">--</div><div class="metric-delta neutral">Last 24h</div></div>
      <div class="metric-card"><div class="metric-label">OPEN DISPUTES</div><div class="metric-value" style="color:var(--warn)" id="overviewOpenDisputes">--</div><div class="metric-delta down" id="overviewEscalatedDisputes">-- escalated</div></div>
      <div class="metric-card"><div class="metric-label">SUSPENDED</div><div class="metric-value" style="color:var(--danger)" id="overviewSuspended">--</div><div class="metric-delta neutral">Driver accounts</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Recent Deliveries</h3><button class="scard-action" onclick="nav('trips',document.querySelectorAll('.nav-btn')[3])">View all →</button></div>
      <div id="overviewRecentTrips"><div class="loading-state">Loading recent deliveries…</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Activity Trend (7 days)</h3></div>
      <div style="padding:16px 18px">
        <div style="display:flex;align-items:flex-end;gap:6px;height:60px;margin-bottom:8px">
          <div style="flex:1;background:var(--navy-light);border-radius:3px 3px 0 0;height:40%"></div>
          <div style="flex:1;background:var(--navy-light);border-radius:3px 3px 0 0;height:55%"></div>
          <div style="flex:1;background:var(--navy-light);border-radius:3px 3px 0 0;height:60%"></div>
          <div style="flex:1;background:var(--navy-light);border-radius:3px 3px 0 0;height:70%"></div>
          <div style="flex:1;background:var(--navy-light);border-radius:3px 3px 0 0;height:80%"></div>
          <div style="flex:1;background:var(--navy-light);border-radius:3px 3px 0 0;height:90%"></div>
          <div style="flex:1;background:var(--gold);border-radius:3px 3px 0 0;height:100%"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text-muted)"><span>Sa</span><span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span style="color:var(--gold);font-weight:700">Fr</span></div>
        <div style="margin-top:8px;font-size:11px;color:var(--text-muted)">Peak: Friday · <span style="color:var(--success)">1,423 trips</span></div>
      </div>
    </div>
  </div>

  <!-- KYC QUEUE -->
  <div class="panel" id="panel-kyc">
    <div class="page-header">
      <h2 class="page-title">KYC Review Queue</h2>
      <div class="page-sub">Driver applications awaiting admin review</div>
    </div>
    <div class="tabs">
      <button class="tab active" onclick="kycTab('under_review',this)">Pending <span id="kyc-pending-count">(0)</span></button>
      <button class="tab" onclick="kycTab('approved',this)">Approved</button>
      <button class="tab" onclick="kycTab('rejected',this)">Rejected</button>
    </div>
    <div class="filter-row">
      <button class="filter-btn active" onclick="filterKyc('all',this)">All Vehicles</button>
      <button class="filter-btn" onclick="filterKyc('bike',this)">Motorbike</button>
      <button class="filter-btn" onclick="filterKyc('car',this)">Car</button>
      <button class="filter-btn" onclick="filterKyc('van',this)">Van</button>
      <button class="filter-btn" onclick="filterKyc('keke',this)">Tricycle</button>
    </div>
    <div class="scard" id="kycQueue">
      <div style="padding:32px;text-align:center;color:var(--text-muted);font-size:13px">Loading driver applications…</div>
      <div id="kyc-empty" style="display:none;padding:32px;text-align:center;color:var(--text-muted);font-size:13px">All applications reviewed ✓</div>
    </div>
    <!-- KYC DETAIL OVERLAY -->
    <div class="driver-detail" id="kycDetail">
      <div class="detail-header"><button class="detail-back" onclick="closeKycDetail()">← Back to queue</button></div>
      <div class="scard">
        <div style="padding:20px">
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
            <div class="avatar" style="width:56px;height:56px;font-size:18px;background:rgba(74,158,255,0.1);color:var(--info)" id="detail-avatar">CN</div>
            <div><div style="font-size:17px;font-weight:700" id="detail-name">Chidi Nwosu</div><div style="font-size:12px;color:var(--text-muted)" id="detail-meta">Motorbike · Rivers · Applied 2h ago</div></div>
          </div>
          <div class="kyc-steps"><div class="kyc-step done"></div><div class="kyc-step done"></div><div class="kyc-step done"></div><div class="kyc-step done"></div><div class="kyc-step active"></div></div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:16px">Step 5 of 5 · Pending admin review</div>
          <div class="metrics-grid">
            <div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">DOCUMENT STATUS</div><div style="font-size:12px;font-weight:600" id="detail-docs">ID verified ✓</div></div>
            <div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">VEHICLE DOCS</div><div style="font-size:12px;font-weight:600" id="detail-vehicle-docs">License & Inspection ✓</div></div>
            <div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">PHOTO REVIEW</div><div style="font-size:12px;font-weight:600" id="detail-photo">Clear portrait ✓</div></div>
            <div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">BANK DETAILS</div><div style="font-size:12px;font-weight:600" id="detail-bank">Bank details pending</div></div>
          </div>
          <div id="kycReviewDetails"></div>
          <div class="form-group" id="kycRejectReasonGroup">
            <label class="form-label">Rejection reason (if rejecting)</label>
            <select class="form-input" id="reject-reason">
              <option value="">Select reason…</option>
              <option>Blurry/invalid ID photo</option><option>License expired</option>
              <option>Vehicle inspection failed</option><option>Incomplete documents</option>
              <option>Profile photo invalid (cap/glasses)</option><option>Other</option>
            </select>
          </div>
          <div id="kycReviewActions" style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn-sm btn-reject" style="flex:1;min-width:140px;height:40px;font-size:13px" onclick="kycDetailAction('rejected')">Reject Application</button>
            <button class="btn-sm btn-approve" style="flex:1;min-width:140px;height:40px;font-size:13px" onclick="kycDetailAction('approved')">Approve Driver</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- LIVE OPS -->
  <div class="panel" id="panel-ops">
    <div class="page-header"><h2 class="page-title">Live Operations</h2><div class="page-sub">Port Harcourt metro · Real-time driver tracking</div></div>
    <div class="ops-map">
      <div class="ops-map-grid"></div>
      <svg viewBox="0 0 400 220" style="position:absolute;inset:0;width:100%;height:100%" preserveAspectRatio="none">
        <path d="M0 80 Q100 70 200 90 T400 80" stroke="white" stroke-width="4" fill="none" opacity="0.35"/>
        <path d="M0 150 Q120 140 200 160 T400 155" stroke="white" stroke-width="3" fill="none" opacity="0.25"/>
        <path d="M100 0 Q110 110 115 220" stroke="white" stroke-width="4" fill="none" opacity="0.35"/>
        <path d="M270 0 Q265 110 272 220" stroke="white" stroke-width="3" fill="none" opacity="0.25"/>
        <circle cx="200" cy="110" r="6" fill="#F5C842" opacity="0.8"/>
        <text x="207" y="114" fill="white" font-size="8" opacity="0.7">City Center</text>
      </svg>
      <div class="ops-rider" id="r1" style="top:28%;left:18%">🛵</div>
      <div class="ops-rider" id="r2" style="top:52%;left:42%">🚗</div>
      <div class="ops-rider" id="r3" style="top:18%;left:62%">🛵</div>
      <div class="ops-rider" id="r4" style="top:68%;left:70%">🚐</div>
      <div class="ops-rider" id="r5" style="top:38%;left:78%">🛵</div>
      <div class="ops-rider" id="r6" style="top:58%;left:12%">🛺</div>
      <div class="ops-rider" id="r7" style="top:75%;left:32%">🛵</div>
      <div class="ops-rider" id="r8" style="top:12%;left:85%">🚗</div>
      <div class="map-legend" id="opsMapLegend"><span style="color:var(--success)">●</span> Loading live operations…</div>
    </div>
    <div class="filter-row">
      <button class="filter-btn active" onclick="filterOps('all',this)">All</button>
      <button class="filter-btn" onclick="filterOps('motorbike',this)">🛵 Motorbike</button>
      <button class="filter-btn" onclick="filterOps('car',this)">🚗 Car</button>
      <button class="filter-btn" onclick="filterOps('van',this)">🚐 Van</button>
      <button class="filter-btn" onclick="filterOps('tricycle',this)">🛺 Tricycle</button>
    </div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">ONLINE DRIVERS</div><div class="metric-value" id="opsOnlineDrivers">--</div></div>
      <div class="metric-card"><div class="metric-label">ACTIVE TRIPS</div><div class="metric-value" id="opsActiveTrips">--</div></div>
      <div class="metric-card"><div class="metric-label">LAST LOCATION</div><div class="metric-value" id="opsLastLocation">--</div></div>
      <div class="metric-card"><div class="metric-label">REFRESH</div><div class="metric-value" id="opsRefreshAge">Live</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Active Riders</h3><span id="opsListMeta" style="font-size:11px;color:var(--text-muted)">Live feed</span></div>
      <div id="opsDriverList">
        <div class="list-item"><div class="item-info"><div class="item-name">Loading live driver locations…</div><div class="item-meta">Authenticated admins can view current trip state and last known driver location here.</div></div></div>
      </div>
    </div>
  </div>

  <!-- DELIVERIES -->
  <div class="panel" id="panel-trips">
    <div class="page-header"><h2 class="page-title">Deliveries</h2><div class="page-sub">All trips, tracking and receipts</div></div>
    <div class="panel-search">
      <input placeholder="Search order ID, driver…" id="tripSearch" oninput="searchTrips(this.value)">
      <select class="filter-select" onchange="filterTrips(this.value)">
        <option value="">All categories</option>
        <option>Package</option><option>Gift</option><option>Documents</option><option>Groceries</option><option>Flowers</option><option>Laundry</option>
      </select>
      <button onclick="toast('Exported CSV')">Export</button>
    </div>
    <div class="filter-row">
      <button class="filter-btn active" onclick="filterTripStatus('all',this)">All</button>
      <button class="filter-btn" onclick="filterTripStatus('in-transit',this)">In Transit</button>
      <button class="filter-btn" onclick="filterTripStatus('delivered',this)">Delivered</button>
      <button class="filter-btn" onclick="filterTripStatus('delayed',this)">Delayed</button>
      <button class="filter-btn" onclick="filterTripStatus('cancelled',this)">Cancelled</button>
    </div>
    <div class="scard">
      <div id="tripList"><div class="loading-state">Loading deliveries…</div></div><div class="pagination" id="tripPagination"></div>
    </div>
  </div>

  <!-- REVENUE -->
  <div class="panel" id="panel-revenue">
    <div class="page-header"><h2 class="page-title">Revenue Analytics</h2><div class="page-sub">April 2026 · Platform financial overview</div></div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">MONTHLY REVENUE</div><div class="metric-value">₦48.2M</div><div class="metric-delta up">↑ +22% MoM</div></div>
      <div class="metric-card"><div class="metric-label">NET COMMISSION</div><div class="metric-value">₦9.6M</div><div class="metric-delta neutral">20% take rate</div></div>
      <div class="metric-card"><div class="metric-label">DRIVER PAYOUTS</div><div class="metric-value">₦38.5M</div><div class="metric-delta neutral">80% to drivers</div></div>
      <div class="metric-card"><div class="metric-label">AVG DAILY</div><div class="metric-value">₦1.6M</div><div class="metric-delta up">↑ vs ₦1.3M</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Revenue by day (this week)</h3><button class="scard-action" onclick="toast('Downloading revenue report…')">Download CSV</button></div>
      <div class="rev-bars">
        <div class="rev-row"><span class="rev-label">Mon</span><div class="rev-track"><div class="rev-fill" style="width:58%"><span>₦1.4M</span></div></div></div>
        <div class="rev-row"><span class="rev-label">Tue</span><div class="rev-track"><div class="rev-fill" style="width:72%"><span>₦1.8M</span></div></div></div>
        <div class="rev-row"><span class="rev-label">Wed</span><div class="rev-track"><div class="rev-fill" style="width:82%"><span>₦2.0M</span></div></div></div>
        <div class="rev-row"><span class="rev-label">Thu</span><div class="rev-track"><div class="rev-fill" style="width:88%"><span>₦2.2M</span></div></div></div>
        <div class="rev-row"><span class="rev-label">Fri</span><div class="rev-track"><div class="rev-fill today" style="width:100%"><span>₦2.4M (proj.)</span></div></div></div>
      </div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Revenue by delivery category</h3></div>
      <div class="rev-bars">
        <div class="rev-row"><span class="rev-label" style="width:65px;text-align:left">Package</span><div class="rev-track"><div class="rev-fill" style="width:82%"><span>₦12.8M</span></div></div></div>
        <div class="rev-row"><span class="rev-label" style="width:65px;text-align:left">Groceries</span><div class="rev-track"><div class="rev-fill" style="width:60%"><span>₦9.4M</span></div></div></div>
        <div class="rev-row"><span class="rev-label" style="width:65px;text-align:left">Documents</span><div class="rev-track"><div class="rev-fill" style="width:45%"><span>₦7.2M</span></div></div></div>
        <div class="rev-row"><span class="rev-label" style="width:65px;text-align:left">Gifts</span><div class="rev-track"><div class="rev-fill" style="width:38%"><span>₦6.1M</span></div></div></div>
        <div class="rev-row"><span class="rev-label" style="width:65px;text-align:left">Other</span><div class="rev-track"><div class="rev-fill" style="width:22%"><span>₦3.7M</span></div></div></div>
      </div>
    </div>
    <div class="metrics-grid three">
      <div class="metric-card"><div class="metric-label">SAME-DAY DELIVERIES</div><div class="metric-value">38%</div><div class="metric-delta up">↑ Premium tier</div></div>
      <div class="metric-card"><div class="metric-label">REFERRAL REVENUE</div><div class="metric-value">₦2.1M</div><div class="metric-delta up">↑ 4.4% of total</div></div>
      <div class="metric-card"><div class="metric-label">GATEWAY SUCCESS</div><div class="metric-value">99.1%</div><div class="metric-delta neutral">Payment uptime</div></div>
    </div>
  </div>

  <!-- PAYOUTS -->
  <div class="panel" id="panel-payouts">
    <div class="page-header"><h2 class="page-title">Driver Payouts</h2><div class="page-sub">Manage and release driver earnings</div></div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">TOTAL PENDING</div><div class="metric-value" id="payoutPendingAmount">--</div><div class="metric-delta down" id="payoutPendingCount">Loading…</div></div>
      <div class="metric-card"><div class="metric-label">PROCESSED TODAY</div><div class="metric-value" id="payoutProcessedAmount">--</div><div class="metric-delta up" id="payoutProcessedCount">Loading…</div></div>
      <div class="metric-card"><div class="metric-label">FAILED PAYOUTS</div><div class="metric-value" style="color:var(--danger)" id="payoutFailedCount">--</div><div class="metric-delta down">Review needed</div></div>
      <div class="metric-card"><div class="metric-label">AVG PAYOUT</div><div class="metric-value" id="payoutAvgAmount">--</div><div class="metric-delta neutral">Per payout/wk</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Driver Payouts</h3><button class="btn-primary" id="releaseAllPayoutsBtn" style="font-size:11px;padding:6px 12px;width:auto;" onclick="releaseVisiblePayouts()">Release visible</button></div>
      <div class="panel-search" style="padding:14px 16px;margin-bottom:0"><input id="payoutSearch" placeholder="Search driver, bank, reference…" oninput="queuePayoutSearch(this.value)"><select class="filter-select" id="payoutStatus" onchange="setPayoutStatus(this.value)"><option value="pending">Pending</option><option value="processing">Processing</option><option value="failed">Failed</option><option value="paid">Paid</option><option value="all">All</option></select></div><div id="payoutList"><div class="loading-state">Loading payouts…</div></div><div class="pagination" id="payoutPagination"></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Tax portal</h3></div>
      <div style="padding:16px 18px">
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px">Generate tax summaries for drivers and platform income reports for accounting.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 16px" onclick="toast('Generating Q1 2026 tax summary…')">Q1 2026 Summary</button>
          <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 16px;background:var(--navy-light)" onclick="toast('Generating driver tax reports…')">Driver WHT Reports</button>
          <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 16px;background:var(--navy-light)" onclick="toast('Downloading VAT schedule…')">VAT Schedule</button>
        </div>
      </div>
    </div>
  </div>

  <!-- DRIVERS -->
  <div class="panel" id="panel-drivers">
    <div class="page-header"><h2 class="page-title">Drivers</h2><div class="page-sub">All approved and active delivery riders</div></div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">TOTAL DRIVERS</div><div class="metric-value">1,832</div><div class="metric-delta up">↑ +28 this week</div></div>
      <div class="metric-card"><div class="metric-label">ONLINE NOW</div><div class="metric-value">284</div><div class="metric-delta up">15.5% online</div></div>
      <div class="metric-card"><div class="metric-label">SUSPENDED</div><div class="metric-value" style="color:var(--danger)">14</div><div class="metric-delta down">Review needed</div></div>
      <div class="metric-card"><div class="metric-label">AVG RATING</div><div class="metric-value">4.7★</div><div class="metric-delta up">Platform avg</div></div>
    </div>
    <div class="panel-search">
      <input placeholder="Search name, state…">
      <select class="filter-select"><option>All states</option><option>Rivers</option><option>Lagos</option><option>Abuja</option><option>Kano</option></select>
      <button onclick="toast('Filtered')">Search</button>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Driver Directory</h3></div>
      <div>
        <div class="list-item"><div class="avatar" style="background:rgba(34,196,122,0.1);color:var(--success)">AK</div><div class="item-info"><div class="item-name">Amina Kalu · 🛵</div><div class="item-meta">Rivers · 4.9★ · 312 trips · Online</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="toast('Opening Amina Kalu profile…')">Profile</button><button class="btn-sm btn-suspend" onclick="confirmSuspend('Amina Kalu')">Suspend</button></div></div>
        <div class="list-item"><div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info)">BE</div><div class="item-info"><div class="item-name">Bayo Eze · 🚗</div><div class="item-meta">Lagos · 4.6★ · 198 trips · Online</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="toast('Opening Bayo Eze profile…')">Profile</button><button class="btn-sm btn-suspend" onclick="confirmSuspend('Bayo Eze')">Suspend</button></div></div>
        <div class="list-item"><div class="avatar" style="background:rgba(245,166,35,0.1);color:var(--warn)">CI</div><div class="item-info"><div class="item-name">Chuks Ikenna · 🛺</div><div class="item-meta">Rivers · 3.8★ · 89 trips · Offline · ⚠ 2 complaints</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="toast('Opening Chuks Ikenna profile…')">Profile</button><button class="btn-sm btn-suspend" onclick="confirmSuspend('Chuks Ikenna')">Suspend</button></div></div>
        <div class="list-item"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger)">MN</div><div class="item-info"><div class="item-name">Mike Ndu · 🚗</div><div class="item-meta">Lagos · SUSPENDED · 3.1★ · 12 trips</div></div><div class="item-actions"><button class="btn-sm btn-approve" onclick="toast('Mike Ndu reinstated')">Reinstate</button><button class="btn-sm btn-reject" onclick="toast('Account permanently banned')">Ban</button></div></div>
      </div>
    </div>
  </div>

  <!-- CUSTOMERS -->
  <div class="panel" id="panel-users">
    <div class="page-header"><h2 class="page-title">Customers</h2><div class="page-sub">User accounts, reports and referrals</div></div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">TOTAL CUSTOMERS</div><div class="metric-value">18,204</div><div class="metric-delta up">↑ +342 this week</div></div>
      <div class="metric-card"><div class="metric-label">ACTIVE THIS WEEK</div><div class="metric-value">6,481</div><div class="metric-delta up">35.6% active rate</div></div>
      <div class="metric-card"><div class="metric-label">REFERRALS USED</div><div class="metric-value">1,204</div><div class="metric-delta up">↑ +18% MoM</div></div>
      <div class="metric-card"><div class="metric-label">SUSPENDED</div><div class="metric-value" style="color:var(--danger)">8</div><div class="metric-delta down">Fraud flags</div></div>
    </div>
    <div class="panel-search"><input placeholder="Search customer name or phone…"><button onclick="toast('Searching…')">Search</button></div>
    <div class="scard">
      <div class="scard-header"><h3>Recent customer reports</h3></div>
      <div>
        <div class="list-item"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger);font-size:11px">!</div><div class="item-info"><div class="item-name">Report #R-0048</div><div class="item-meta">Late delivery · Driver: Amina K. · Today</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openDisputeModal('Late delivery — Report #R-0048')">Review</button></div></div>
        <div class="list-item"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger);font-size:11px">!</div><div class="item-info"><div class="item-name">Report #R-0047</div><div class="item-meta">Wrong delivery · Driver: Bayo E. · Yesterday</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openDisputeModal('Wrong delivery — Report #R-0047')">Review</button></div></div>
        <div class="list-item"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger);font-size:11px">!</div><div class="item-info"><div class="item-name">Report #R-0046</div><div class="item-meta">Rude driver · Driver: Chuks I. · 2 days ago</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openDisputeModal('Rude driver — Report #R-0046')">Review</button></div></div>
      </div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Referral program</h3></div>
      <div style="padding:16px 18px">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(80px,1fr));gap:10px;margin-bottom:14px">
          <div style="background:var(--surface);border-radius:10px;padding:12px;text-align:center"><div style="font-size:11px;color:var(--text-muted)">Total codes</div><div style="font-family:'Syne',sans-serif;font-size:20px;font-weight:700">4,201</div></div>
          <div style="background:var(--surface);border-radius:10px;padding:12px;text-align:center"><div style="font-size:11px;color:var(--text-muted)">Redeemed</div><div style="font-family:'Syne',sans-serif;font-size:20px;font-weight:700">1,204</div></div>
          <div style="background:var(--surface);border-radius:10px;padding:12px;text-align:center"><div style="font-size:11px;color:var(--text-muted)">Value paid</div><div style="font-family:'Syne',sans-serif;font-size:20px;font-weight:700">₦2.1M</div></div>
        </div>
        <button class="btn-primary" style="font-size:12px;padding:8px 16px" onclick="toast('Referral report downloading…')">Download Report</button>
      </div>
    </div>
  </div>

  <!-- DISPUTES -->
  <div class="panel" id="panel-disputes">
    <div class="page-header"><h2 class="page-title">Disputes</h2><div class="page-sub">Customer complaints and escalations</div></div>
    <div class="metrics-grid three">
      <div class="metric-card"><div class="metric-label">OPEN DISPUTES</div><div class="metric-value" style="color:var(--warn)" id="disputeOpenCount">--</div><div class="metric-delta down" id="disputeEscalatedCount">-- escalated</div></div>
      <div class="metric-card"><div class="metric-label">TOTAL MATCHES</div><div class="metric-value" id="disputeTotalCount">--</div><div class="metric-delta neutral">Current filter</div></div>
      <div class="metric-card"><div class="metric-label">REFUNDS ISSUED</div><div class="metric-value" id="disputeRefundAmount">--</div><div class="metric-delta neutral">Visible page</div></div>
    </div>
    <div class="filter-row">
      <button class="filter-btn active" onclick="filterDisputes('all',this)">All</button>
      <button class="filter-btn" onclick="filterDisputes('open',this)">Open</button>
      <button class="filter-btn" onclick="filterDisputes('escalated',this)">Escalated</button>
      <button class="filter-btn" onclick="filterDisputes('resolved',this)">Resolved</button>
    </div>
    <div class="panel-search">
      <input id="disputeSearch" placeholder="Search dispute, trip, customer, driver…" oninput="queueDisputeSearch(this.value)">
      <select class="filter-select" id="disputeStatus" onchange="setDisputeStatus(this.value)"><option value="all">All statuses</option><option value="open">Open</option><option value="escalated">Escalated</option><option value="resolved">Resolved</option></select>
    </div>
    <div class="scard"><div id="disputeList"><div class="loading-state">Loading disputes…</div></div><div class="pagination" id="disputePagination"></div></div>
  </div>

  <!-- SETTINGS -->
  <div class="panel" id="panel-settings">
    <div class="page-header"><h2 class="page-title">Settings</h2><div class="page-sub">Platform configuration and policies</div></div>
    <div class="settings-section">
      <h4>Commission & Pricing</h4>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Platform commission (%)</label><input class="form-input" type="number" data-setting="platform_commission_pct" value="20" min="1" max="50"></div>
        <div class="form-group"><label class="form-label">Surge multiplier cap (×)</label><input class="form-input" type="number" data-setting="surge_multiplier_cap" value="2.5" min="1" step="0.1"></div>
        <div class="form-group"><label class="form-label">Min. fare (₦)</label><input class="form-input" type="number" data-setting="min_fare" value="800"></div>
        <div class="form-group"><label class="form-label">Max. delivery radius (km)</label><input class="form-input" type="number" data-setting="max_delivery_radius_km" value="50"></div>
      </div>
    </div>

    <div class="settings-section">
      <h4>Manual Transfer Payments</h4>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Active provider</label><select class="form-input" data-setting="payment_active_provider"><option value="manual_transfer">Manual transfer</option><option value="paystack">Paystack (future)</option><option value="flutterwave">Flutterwave (future)</option></select></div>
        <div class="form-group"><label class="form-label">Bank name</label><input class="form-input" data-setting="manual_bank_name" placeholder="e.g. GTBank"></div>
        <div class="form-group"><label class="form-label">Account name</label><input class="form-input" data-setting="manual_account_name" placeholder="Idibia Logistics Ltd"></div>
        <div class="form-group"><label class="form-label">Account number</label><input class="form-input" data-setting="manual_account_number" placeholder="0123456789"></div>
      </div>
      <div class="form-group"><label class="form-label">Customer payment instructions</label><textarea class="form-input" data-setting="manual_payment_instructions" rows="3" placeholder="Transfer exact fare and upload receipt."></textarea></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Paystack public key</label><input class="form-input" data-setting="paystack_public_key" placeholder="Future use"></div>
        <div class="form-group"><label class="form-label">Paystack secret key</label><input class="form-input" data-setting="paystack_secret_key" placeholder="Future use"></div>
        <div class="form-group"><label class="form-label">Flutterwave public key</label><input class="form-input" data-setting="flutterwave_public_key" placeholder="Future use"></div>
        <div class="form-group"><label class="form-label">Flutterwave secret key</label><input class="form-input" data-setting="flutterwave_secret_key" placeholder="Future use"></div>
      </div>
      <button class="btn-primary" style="font-size:12px;padding:8px 14px;width:auto" onclick="savePaymentSettings()">Save payment settings</button>
    </div>
    <div class="settings-section">
      <div class="scard-header"><h4 style="border:0;margin:0;padding:0">Manual Payment Reviews</h4><button class="scard-action" onclick="loadManualPayments()">Refresh</button></div>
      <div id="manualPaymentsList"><div class="list-item"><div class="item-info"><div class="item-name">Loading payment proofs…</div></div></div></div>
    </div>
    <div class="settings-section">
      <h4>KYC Policy</h4>
      <div class="toggle-row"><div><div class="toggle-label">Auto-flag blurry ID photos</div><div class="toggle-sub">AI-assisted photo quality check</div></div><button class="toggle" data-setting="kyc_auto_flag_blurry" onclick="this.classList.toggle(\'on\')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Require vehicle inspection report</div><div class="toggle-sub">Mandatory for vans and tricycles</div></div><button class="toggle" data-setting="kyc_require_vehicle_inspection" onclick="this.classList.toggle(\'on\')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">72-hour KYC review SLA alert</div><div class="toggle-sub">Email admin if review exceeds 72h</div></div><button class="toggle" data-setting="kyc_72h_sla_alert" onclick="this.classList.toggle(\'on\')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Background check integration</div><div class="toggle-sub">Third-party criminal record API</div></div><button class="toggle" data-setting="kyc_background_check" onclick="this.classList.toggle(\'on\')"></button></div>
    </div>
    <div class="settings-section">
      <h4>Notifications</h4>
      <div class="toggle-row"><div><div class="toggle-label">KYC queue alerts</div><div class="toggle-sub">Email when queue exceeds 5</div></div><button class="toggle" data-setting="notif_kyc_queue" onclick="this.classList.toggle(\'on\')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Dispute escalation alerts</div><div class="toggle-sub">Push alert when dispute >48h unresolved</div></div><button class="toggle" data-setting="notif_dispute_escalation" onclick="this.classList.toggle(\'on\')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Daily revenue digest</div><div class="toggle-sub">Email summary at 8pm daily</div></div><button class="toggle" data-setting="notif_daily_revenue" onclick="this.classList.toggle(\'on\')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Failed payout alerts</div><div class="toggle-sub">Instant alert on payout failures</div></div><button class="toggle" data-setting="notif_failed_payout" onclick="this.classList.toggle(\'on\')"></button></div>
    </div>
    <div class="settings-section">
      <h4>Legal & Compliance</h4>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Terms & Conditions URL</label><input class="form-input" data-setting="legal_terms_url" placeholder="https://..."></div>
        <div class="form-group"><label class="form-label">Privacy Policy URL</label><input class="form-input" data-setting="legal_privacy_url" placeholder="https://..."></div>
        <div class="form-group"><label class="form-label">Location Data Policy URL</label><input class="form-input" data-setting="legal_location_url" placeholder="https://..."></div>
        <div class="form-group"><label class="form-label">Software License URL</label><input class="form-input" data-setting="legal_license_url" placeholder="https://..."></div>
      </div>
    </div>
    <button class="btn-primary" onclick="savePaymentSettings()">Save Changes</button>
  </div>

</div><!-- /main -->

<!-- DISPUTE MODAL -->
<div class="modal-overlay" id="disputeModal" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-header"><h3 style="font-size:15px" id="modalTitle">Handle Dispute</h3><button onclick="closeModal()" style="background:none;border:none;font-size:18px;color:var(--text-muted)">×</button></div>
    <div class="modal-body">
      <div id="modalDesc" style="font-size:13px;color:var(--text-secondary);margin-bottom:16px"></div>
      <div class="form-group"><label class="form-label">Resolution action</label><select class="form-input" id="resolutionType"><option>Issue full refund to customer</option><option>Issue partial refund</option><option>Warn driver (first offence)</option><option>Suspend driver temporarily</option><option>Suspend driver permanently</option><option>No action — complaint invalid</option></select></div>
      <div class="form-group"><label class="form-label">Refund amount (₦)</label><input class="form-input" type="number" placeholder="0" id="refundAmt"></div>
      <div class="form-group"><label class="form-label">Admin notes</label><textarea class="form-input" id="resolutionNotes" rows="3" placeholder="Add resolution notes…" style="resize:none"></textarea></div>
    </div>
    <div class="modal-footer">
      <button onclick="closeModal()" style="padding:9px 18px;border-radius:9px;border:1.5px solid var(--surface-2);background:none;font-size:13px">Cancel</button>
      <button class="btn-primary" onclick="resolveDispute()">Resolve Dispute</button>
    </div>
  </div>
</div>

<!-- TRIP DETAIL MODAL -->
<div class="modal-overlay" id="tripModal" onclick="if(event.target===this)closeTripModal()">
  <div class="modal">
    <div class="modal-header"><h3 style="font-size:15px" id="tripModalTitle">Trip Details</h3><button onclick="closeTripModal()" style="background:none;border:none;font-size:18px;color:var(--text-muted)">×</button></div>
    <div class="modal-body" id="tripModalBody"></div>
    <div class="modal-footer">
      <button onclick="closeTripModal()" style="padding:9px 18px;border-radius:9px;border:1.5px solid var(--surface-2);background:none;font-size:13px">Close</button>
      <button class="btn-primary" onclick="toast('Receipt emailed to customer');closeTripModal()">Email Receipt</button>
    </div>
  </div>
</div>

<div class="toast" id="toastEl"></div>

<script>
const panels={overview:'Platform Overview',kyc:'KYC Review Queue',ops:'Live Operations',trips:'Deliveries',revenue:'Revenue Analytics',payouts:'Driver Payouts',drivers:'Drivers',users:'Customers',disputes:'Disputes',settings:'Settings'};
const subs={overview:'Live · Sat Apr 11, 2026',kyc:'Applications awaiting review',ops:'Port Harcourt metro',trips:'All trips and tracking',revenue:'April 2026',payouts:'Earnings management',drivers:'All verified drivers',users:'Customer accounts',disputes:'Complaints & escalations',settings:'Platform configuration'};

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.querySelector('.sidebar-overlay').classList.toggle('open');
}

function nav(name,btn){
  document.querySelectorAll('.panel').forEach(p=>p.classList.remove('active'));
  document.getElementById('panel-'+name).classList.add('active');
  document.querySelectorAll('.nav-btn').forEach(b=>b.classList.remove('active'));
  if(btn)btn.classList.add('active');
  document.getElementById('topbar-title').textContent=panels[name]||name;
  document.getElementById('topbar-sub').textContent=subs[name]||'';
  document.getElementById('notifPanel').classList.remove('open');

  // Close sidebar on mobile after navigation
  if(name === 'overview') loadDashboard();
  if(name === 'ops') loadLiveOps();
  if(name === 'trips') loadTrips();
  if(name === 'payouts') loadPayouts();
  if(name === 'disputes') loadDisputes();
  if(name === 'settings') { loadPaymentSettings(); loadManualPayments(); }

  if(window.innerWidth < 900) {
    document.getElementById('sidebar').classList.remove('open');
    document.querySelector('.sidebar-overlay').classList.remove('open');
  }
}

const ADMIN_API_URL = '/admin/api.php';
let kycCount = 0;
let currentKycName = '';
let currentKycId = 0;
let currentKycTab = 'under_review';
let currentKycFilter = 'all';
let kycDrivers = [];
const pageState = { trips:{page:1, per_page:10, search:'', status:'', category:''}, payouts:{page:1, per_page:10, search:'', status:'pending'}, disputes:{page:1, per_page:10, search:'', status:'all'} };
let searchTimers = {};
let currentDisputeId = 0;

function escapeHtml(value){
  return String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
}
function vehicleLabel(type){
  return {bike:'Motorbike',car:'Car',van:'Van',keke:'Tricycle'}[type] || (type ? type : 'Vehicle');
}
function vehicleIcon(type){
  return {bike:'🏍',car:'🚗',van:'🚐',keke:'🛺'}[type] || '🚚';
}
function initials(name){
  return String(name || 'DR').split(' ').filter(Boolean).map(n=>n[0]).join('').slice(0,2).toUpperCase() || 'DR';
}
function maskAccount(number){
  const clean = String(number || '').replace(/\D/g, '');
  return clean ? '****' + clean.slice(-4) : 'Not provided';
}

function formatMoney(value){ return '₦' + Number(value || 0).toLocaleString(undefined, {maximumFractionDigits:0}); }
function formatStatusLabel(value){ return String(value || '').replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase()); }
function tripStatusClass(status){
  if(['completed','delivered','paid'].includes(status)) return 'badge-success';
  if(['cancelled','failed'].includes(status)) return 'badge-danger';
  if(['pending','searching','delayed','escalated'].includes(status)) return 'badge-warn';
  return 'badge-info';
}
function renderPagination(elId, state, total, loader){
  const el=document.getElementById(elId); if(!el) return;
  const pages=Math.max(1, Math.ceil(Number(total||0)/state.per_page));
  el.innerHTML=`<span>Page ${state.page} of ${pages} · ${Number(total||0).toLocaleString()} total</span><button ${state.page<=1?'disabled':''} onclick="${loader}(${state.page-1})">Prev</button><button ${state.page>=pages?'disabled':''} onclick="${loader}(${state.page+1})">Next</button>`;
}
function dateLabel(value){ return value ? formatApplied(value) : 'Recently'; }

function formatApplied(value){
  if(!value) return 'Recently';
  const created = new Date(String(value).replace(' ', 'T') + 'Z');
  if(Number.isNaN(created.getTime())) return value;
  const diffHours = Math.max(0, Math.round((Date.now() - created.getTime()) / 36e5));
  if(diffHours < 1) return 'Just now';
  if(diffHours < 24) return diffHours + 'h ago';
  const days = Math.round(diffHours / 24);
  return days + 'd ago';
}
async function adminApi(action, params = {}, method = 'GET'){
  const body = new FormData();
  const url = new URL(ADMIN_API_URL, window.location.origin);
  url.searchParams.set('action', action);
  Object.entries(params).forEach(([key,value]) => {
    if(method === 'GET') url.searchParams.set(key, value);
    else body.append(key, value);
  });
  if(method !== 'GET') body.append('action', action);
  const response = await fetch(url.toString(), {
    method,
    body: method === 'GET' ? undefined : body,
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' }
  });
  const rawText = await response.text();
  let data;
  try { data = JSON.parse(rawText); } catch (e) { console.error('Raw admin API response:', rawText); throw new Error('Invalid server response'); }
  if(!data.success) throw new Error(data.data?.message || 'Admin request failed.');
  return data.data;
}
async function adminApiAllPages(action, params = {}){
  const perPage = 100;
  let page = 1;
  let allDrivers = [];
  let firstPage = null;
  while(true){
    const data = await adminApi(action, { ...params, page, per_page: perPage });
    if(!firstPage) firstPage = data;
    const drivers = data.drivers || [];
    allDrivers = allDrivers.concat(drivers);
    const total = Number(data.total || allDrivers.length || 0);
    if(allDrivers.length >= total || drivers.length < perPage) {
      return { ...data, ...firstPage, drivers: allDrivers, total };
    }
    page += 1;
  }
}
function renderKycQueue(){
  const queue = document.getElementById('kycQueue');
  const visible = kycDrivers.filter(driver => currentKycFilter === 'all' || driver.vehicle_type === currentKycFilter);
  if(!visible.length){
    queue.innerHTML = '<div id="kyc-empty" style="padding:32px;text-align:center;color:var(--text-muted);font-size:13px">'+(currentKycTab === 'under_review' ? 'All applications reviewed ✓' : 'No '+escapeHtml(currentKycTab.replace('_',' '))+' applications found.')+'</div>';
    return;
  }
  queue.innerHTML = visible.map(driver => {
    const canReview = driver.kyc_status === 'under_review';
    const applied = formatApplied(driver.created_at);
    const state = driver.emergency_address || driver.vehicle_plate || 'Submitted KYC';
    return `<div class="kyc-item-wrap" data-driver-id="${Number(driver.id)}" data-type="${escapeHtml(driver.vehicle_type)}"><div class="list-item"><div class="avatar" style="background:rgba(245,200,66,0.12);color:var(--gold-dark)">${escapeHtml(initials(driver.full_name))}</div><div class="item-info"><div class="item-name">${escapeHtml(driver.full_name || 'Unnamed driver')}</div><div class="item-meta">${vehicleIcon(driver.vehicle_type)} ${escapeHtml(vehicleLabel(driver.vehicle_type))} · ${escapeHtml(state)} · Applied ${escapeHtml(applied)}</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openKycDetailById(${Number(driver.id)})">View</button>${canReview ? `<button class="btn-sm btn-reject" onclick="kycAction(this,'rejected')">Reject</button><button class="btn-sm btn-approve" onclick="kycAction(this,'approved')">Approve</button>` : `<span class="badge ${driver.kyc_status === 'approved' ? 'badge-success' : 'badge-danger'}">${escapeHtml(driver.kyc_status)}</span>`}</div></div></div>`;
  }).join('');
}
async function loadKycQueue(status = currentKycTab){
  currentKycTab = status;
  const queue = document.getElementById('kycQueue');
  queue.innerHTML = '<div style="padding:32px;text-align:center;color:var(--text-muted);font-size:13px">Loading driver applications…</div>';
  try {
    const data = await adminApiAllPages('get_drivers', { kyc_status: status });
    kycDrivers = data.drivers || [];
    if(status === 'under_review') {
      kycCount = Number(data.total || kycDrivers.length || 0);
      updateKycBadge();
    }
    renderKycQueue();
  } catch (e) {
    queue.innerHTML = '<div style="padding:32px;text-align:center;color:var(--danger);font-size:13px">Could not load KYC applications. '+escapeHtml(e.message)+'</div>';
  }
}
async function kycAction(btn, action){
  const item = btn.closest('.kyc-item-wrap');
  const driverId = Number(item?.dataset.driverId || currentKycId || 0);
  const driver = kycDrivers.find(row => Number(row.id) === driverId);
  if(!driver || driver.kyc_status !== 'under_review'){
    toast('This KYC record has already been resolved.');
    return;
  }
  const notes = action === 'rejected' ? (document.getElementById('reject-reason')?.value || 'Rejected from admin KYC review.') : 'Approved from admin KYC review.';
  btn.disabled = true;
  try {
    const data = await adminApi('kyc_action', { driver_id: driverId, decision: action, notes }, 'POST');
    if(item){
      item.style.opacity='0';item.style.transform='translateX(20px)';item.style.transition='all 0.3s';
      setTimeout(()=>{item.remove();renderKycQueue();},300);
    }
    kycDrivers = kycDrivers.filter(row => Number(row.id) !== driverId);
    if(currentKycTab === 'under_review') { kycCount = Math.max(0, kycCount - 1); updateKycBadge(); }
    toast(data.message || (action === 'approved' ? '✓ Driver approved & notified' : '✗ Application rejected'));
    if(driver && action === 'approved') toast('✓ '+driver.full_name+' can now open the driver dashboard');
  } catch (e) {
    btn.disabled = false;
    toast(e.message);
  }
}

async function loadPaymentSettings(){
  try {
    const data = await adminApi('get_settings');
    const settings = data.settings || {};
    document.querySelectorAll('[data-setting]').forEach(el => {
      const key = el.getAttribute('data-setting');
      if(settings[key] !== undefined) {
        if(el.tagName === 'BUTTON' && el.classList.contains('toggle')) {
          if (settings[key] == '1' || settings[key] === true || settings[key] === 'true') {
            el.classList.add('on');
          } else {
            el.classList.remove('on');
          }
        } else {
          el.value = settings[key];
        }
      }
    });
  } catch (e) {
    toast('Could not load settings');
  }
}

async function savePaymentSettings(){
  const payload = {};
  document.querySelectorAll('[data-setting]').forEach(el => {
    if(el.tagName === 'BUTTON' && el.classList.contains('toggle')) {
      payload[el.getAttribute('data-setting')] = el.classList.contains('on') ? '1' : '0';
    } else {
      payload[el.getAttribute('data-setting')] = el.value;
    }
  });
  try {
    const response = await fetch(ADMIN_API_URL + '?action=save_settings', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const json = await response.json();
    toast(json.success ? 'Payment settings saved' : (json.data?.message || 'Could not save settings'));
  } catch (e) {
    toast('Could not save payment settings');
  }
}

async function loadManualPayments(){
  const list = document.getElementById('manualPaymentsList');
  if(!list) return;
  try {
    const data = await adminApi('get_manual_payments', {status:'pending', per_page:20});
    const payments = data.payments || [];
    if(!payments.length){
      list.innerHTML = '<div class="list-item"><div class="item-info"><div class="item-name">No manual transfers waiting for review</div><div class="item-meta">Customer uploads will appear here.</div></div></div>';
      return;
    }
    list.innerHTML = payments.map(p => `
      <div class="list-item" data-payment-id="${p.id}">
        <div class="avatar" style="background:rgba(245,200,66,0.1);color:var(--gold-dark)">₦</div>
        <div class="item-info">
          <div class="item-name">${escapeHtml(p.trip_ref || ('Trip #' + p.trip_id))} · ₦${Number(p.amount || 0).toLocaleString()}</div>
          <div class="item-meta">${escapeHtml(p.customer_name || 'Customer')} · ${escapeHtml(p.provider_ref || 'No reference')} · ${escapeHtml(p.status)}</div>
          ${p.proof_url ? `<a href="${escapeHtml(p.proof_url)}" target="_blank" rel="noopener" style="font-size:12px;color:var(--info);font-weight:700">View proof</a>` : '<span style="font-size:12px;color:var(--danger)">No proof uploaded</span>'}
        </div>
        <div class="item-actions">
          <button class="btn-sm btn-approve" onclick="reviewManualPayment(${p.id}, 'approve')">Approve</button>
          <button class="btn-sm btn-reject" onclick="reviewManualPayment(${p.id}, 'reject')">Reject</button>
        </div>
      </div>`).join('');
  } catch (e) {
    list.innerHTML = '<div class="list-item"><div class="item-info"><div class="item-name">Could not load manual payments</div><div class="item-meta">'+escapeHtml(e.message)+'</div></div></div>';
  }
}

async function reviewManualPayment(paymentId, decision){
  const notes = decision === 'reject' ? prompt('Why are you rejecting this proof?') : prompt('Approval note (optional)');
  if(decision === 'reject' && !notes) return;
  try {
    const data = await adminApi('review_manual_payment', {payment_id: paymentId, decision, admin_notes: notes || ''}, 'POST');
    toast(data.message || (decision === 'approve' ? 'Payment approved' : 'Payment rejected'));
    loadManualPayments();
  } catch (e) {
    toast(e.message || 'Could not review payment');
  }
}

function updateKycBadge(){
  document.getElementById('kyc-badge').textContent = kycCount;
  document.getElementById('kyc-pending-count').textContent = '(' + kycCount + ')';
}
function kycTab(tab,btn){
  document.querySelectorAll('.tabs .tab').forEach(t=>t.classList.remove('active'));
  btn.classList.add('active');
  loadKycQueue(tab);
}
function filterKyc(type,btn){
  currentKycFilter = type;
  document.querySelectorAll('.filter-row .filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  renderKycQueue();
}
function openKycDetailById(driverId){
  const driver = kycDrivers.find(row => Number(row.id) === Number(driverId));
  if(!driver){ toast('Driver record not found.'); return; }
  currentKycId = Number(driver.id);
  currentKycName = driver.full_name || 'Driver';
  document.getElementById('detail-name').textContent = currentKycName;
  document.getElementById('detail-meta').textContent = vehicleLabel(driver.vehicle_type) + ' · ' + (driver.vehicle_plate || 'No plate') + ' · Applied ' + formatApplied(driver.created_at);
  document.getElementById('detail-avatar').textContent = initials(currentKycName);
  document.getElementById('detail-docs').textContent = (driver.id_doc_type || 'Identity') + (driver.id_front_path || driver.id_back_path ? ' uploaded ✓' : ' pending');
  document.getElementById('detail-vehicle-docs').textContent = [driver.vehicle_license_doc_path ? 'License ✓' : 'License pending', driver.insurance_doc_path ? 'Inspection ✓' : 'Inspection optional/pending'].join(' · ');
  document.getElementById('detail-photo').textContent = driver.selfie_path ? 'Selfie uploaded ✓' : 'Selfie pending';
  document.getElementById('detail-bank').textContent = (driver.bank_name || 'Bank') + ' · ' + maskAccount(driver.account_number) + (driver.account_holder_name ? ' · ' + driver.account_holder_name : '');
  const canReview = driver.kyc_status === 'under_review';
  const reviewActions = document.getElementById('kycReviewActions');
  const rejectReason = document.getElementById('kycRejectReasonGroup');
  if(reviewActions) reviewActions.style.display = canReview ? 'flex' : 'none';
  if(rejectReason) rejectReason.style.display = canReview ? 'block' : 'none';
  document.getElementById('kycDetail').classList.add('open');
}
function openKycDetail(name,vehicle,state,time,docs){
  currentKycName=name;
  currentKycId=0;
  document.getElementById('detail-name').textContent=name;
  document.getElementById('detail-meta').textContent=vehicle+' · '+state+' · Applied '+time;
  document.getElementById('detail-avatar').textContent=initials(name);
  document.getElementById('detail-docs').textContent=docs;
  document.getElementById('kycReviewDetails').innerHTML = '';
  document.getElementById('kycDetail').classList.add('open');
}
function closeKycDetail(){document.getElementById('kycDetail').classList.remove('open');}
async function kycDetailAction(action){
  const driver = kycDrivers.find(row => Number(row.id) === Number(currentKycId));
  if(!driver || driver.kyc_status !== 'under_review'){
    toast('This KYC record has already been resolved.');
    return;
  }
  const fakeBtn = document.querySelector(`.kyc-item-wrap[data-driver-id="${currentKycId}"] .${action === 'approved' ? 'btn-approve' : 'btn-reject'}`) || document.createElement('button');
  await kycAction(fakeBtn, action);
  closeKycDetail();
}
loadKycQueue('under_review');
loadDashboard();
loadTrips();
loadPayouts();
loadDisputes();
loadPaymentSettings();
loadManualPayments();


async function loadDashboard(){
  const list=document.getElementById('overviewRecentTrips');
  if(list) list.innerHTML='<div class="loading-state">Loading recent deliveries…</div>';
  try{
    const data=await adminApi('get_dashboard_stats');
    document.getElementById('overviewActiveDrivers').textContent=Number(data.active_drivers||0).toLocaleString();
    document.getElementById('overviewOnlineDrivers').textContent=Number(data.online_drivers||0).toLocaleString()+' online now';
    document.getElementById('overviewTripsToday').textContent=Number(data.trips_today||0).toLocaleString();
    const diff=Number(data.trips_today||0)-Number(data.trips_yesterday||0);
    document.getElementById('overviewTripsDelta').textContent=(diff>=0?'↑ +':'↓ ')+diff.toLocaleString()+' vs yesterday';
    document.getElementById('overviewRevenueToday').textContent=formatMoney(data.revenue_today);
    document.getElementById('overviewKycPending').textContent=Number(data.kyc_pending||0).toLocaleString();
    document.getElementById('overviewCompletionRate').textContent=Number(data.completion_rate||0).toFixed(1)+'%';
    document.getElementById('overviewAvgPickup').textContent=Number(data.avg_pickup_time||0).toFixed(1)+'m';
    document.getElementById('overviewOpenDisputes').textContent=Number(data.open_disputes||0).toLocaleString();
    document.getElementById('overviewEscalatedDisputes').textContent=Number(data.escalated_disputes||0).toLocaleString()+' escalated';
    document.getElementById('overviewSuspended').textContent=Number(data.suspended_drivers||0).toLocaleString();
    const trips=data.recent_trips||[];
    list.innerHTML=trips.length?trips.map(renderTripListItem).join(''):'<div class="empty-state">No deliveries found yet.</div>';
  }catch(e){ if(list) list.innerHTML='<div class="empty-state">Could not load dashboard: '+escapeHtml(e.message)+'</div>'; }
}
function renderTripListItem(trip){
  const status=trip.dispatch_status && trip.dispatch_status !== 'completed' ? trip.dispatch_status : trip.status;
  const fare=trip.fare_amount ?? trip.final_fare ?? trip.fare_estimate ?? trip.fare;
  const from=trip.pickup_address || trip.pickup || 'Pickup';
  const to=trip.dropoff_address || trip.dropoff || 'Drop-off';
  const category=trip.service_category || trip.category || 'Delivery';
  const detail=[from+' → '+to, formatMoney(fare), trip.driver_name?'Driver: '+trip.driver_name:null].filter(Boolean).map(escapeHtml).join(' · ');
  return `<div class="list-item" data-status="${escapeHtml(status)}"><div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info)">${escapeHtml(initials(trip.customer_name || trip.driver_name || trip.trip_ref))}</div><div class="item-info"><div class="item-name">${escapeHtml(trip.trip_ref || ('Trip #'+trip.id))} · ${escapeHtml(formatStatusLabel(category))}</div><div class="item-meta">${detail}</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><span class="badge ${tripStatusClass(status)}">${escapeHtml(formatStatusLabel(status))}</span><button class="btn-sm btn-view" onclick='openTripDetailFromData(${JSON.stringify(trip).replace(/'/g,"&#39;")})'>Details</button></div></div>`;
}
async function loadTrips(page=pageState.trips.page){
  const st=pageState.trips; st.page=page;
  const list=document.getElementById('tripList'); if(!list) return;
  list.innerHTML='<div class="loading-state">Loading deliveries…</div>';
  try{ const data=await adminApi('get_trips', st); list.innerHTML=(data.trips||[]).length?(data.trips||[]).map(renderTripListItem).join(''):'<div class="empty-state">No deliveries match your filters.</div>'; renderPagination('tripPagination', st, data.total, 'loadTrips'); }
  catch(e){ list.innerHTML='<div class="empty-state">Could not load deliveries: '+escapeHtml(e.message)+'</div>'; }
}
function openTripDetailFromData(trip){ openTripDetail(trip.trip_ref||('#'+trip.id), trip.service_category||trip.category||'Delivery', trip.pickup_address||trip.pickup||'', trip.dropoff_address||trip.dropoff||'', formatMoney(trip.fare_amount??trip.final_fare??trip.fare_estimate??trip.fare), trip.driver_name||'Unassigned', formatStatusLabel(trip.dispatch_status||trip.status)); }
async function loadPayouts(page=pageState.payouts.page){
  const st=pageState.payouts; st.page=page; const list=document.getElementById('payoutList'); if(!list) return;
  list.innerHTML='<div class="loading-state">Loading payouts…</div>';
  try{ const data=await adminApi('get_payouts', st); const m=data.metrics||{}; document.getElementById('payoutPendingAmount').textContent=formatMoney(m.pending_amount); document.getElementById('payoutPendingCount').textContent=Number(m.pending_count||0).toLocaleString()+' pending'; document.getElementById('payoutProcessedAmount').textContent=formatMoney(m.processed_today_amount); document.getElementById('payoutProcessedCount').textContent=Number(m.processed_today_count||0).toLocaleString()+' processed'; document.getElementById('payoutFailedCount').textContent=Number(m.failed_count||0).toLocaleString(); document.getElementById('payoutAvgAmount').textContent=formatMoney(m.avg_payout); list.innerHTML=(data.payouts||[]).length?(data.payouts||[]).map(renderPayoutItem).join(''):'<div class="empty-state">No payouts match your filters.</div>'; renderPagination('payoutPagination', st, data.total, 'loadPayouts'); }
  catch(e){ list.innerHTML='<div class="empty-state">Could not load payouts: '+escapeHtml(e.message)+'</div>'; }
}
function renderPayoutItem(p){ const failed=p.status==='failed'; return `<div class="list-item"><div class="avatar" style="background:${failed?'rgba(232,72,74,0.1)':'rgba(34,196,122,0.1)'};color:${failed?'var(--danger)':'var(--success)'}">${escapeHtml(initials(p.driver_name))}</div><div class="item-info"><div class="item-name">${escapeHtml(p.driver_name||('Driver #'+p.driver_id))}</div><div class="item-meta">${escapeHtml(p.bank_name||'Bank not set')} ${escapeHtml(maskAccount(p.account_number))} · ${Number(p.total_trips||0).toLocaleString()} trips · ${escapeHtml(formatStatusLabel(p.status))}</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><div style="font-weight:700;font-size:14px;color:${failed?'var(--danger)':'inherit'}">${formatMoney(p.amount)}</div>${p.status==='paid'?'<span class="badge badge-success">Paid</span>':`<button class="btn-sm ${failed?'btn-reject':'btn-approve'}" onclick="processPayout(${Number(p.id)}, 'paid')">Release</button>`}</div></div>`; }
async function processPayout(id,status){ try{ const data=await adminApi('process_payout',{payout_id:id,status},'POST'); toast(data.message||'Payout updated'); loadPayouts(); }catch(e){ toast(e.message||'Could not update payout'); } }
function releaseVisiblePayouts(){ document.querySelectorAll('#payoutList button.btn-approve').forEach(btn=>btn.click()); }
async function loadDisputes(page=pageState.disputes.page){
  const st=pageState.disputes; st.page=page; const list=document.getElementById('disputeList'); if(!list) return; list.innerHTML='<div class="loading-state">Loading disputes…</div>';
  try{ const data=await adminApi('get_disputes', st); const rows=data.disputes||[]; document.getElementById('disputeTotalCount').textContent=Number(data.total||0).toLocaleString(); document.getElementById('disputeOpenCount').textContent=rows.filter(d=>d.status==='open'||d.status==='escalated').length.toLocaleString(); document.getElementById('disputeEscalatedCount').textContent=rows.filter(d=>d.status==='escalated').length.toLocaleString()+' escalated'; document.getElementById('disputeRefundAmount').textContent=formatMoney(rows.reduce((sum,d)=>sum+Number(d.refund_amount||0),0)); list.innerHTML=rows.length?rows.map(renderDisputeItem).join(''):'<div class="empty-state">No disputes match your filters.</div>'; renderPagination('disputePagination', st, data.total, 'loadDisputes'); }
  catch(e){ list.innerHTML='<div class="empty-state">Could not load disputes: '+escapeHtml(e.message)+'</div>'; }
}
function renderDisputeItem(d){ const status=d.status||'open'; const title='#D-'+String(d.id).padStart(4,'0')+' · '+(d.category||'Dispute'); const meta=status==='resolved'?`Resolved · ${formatMoney(d.refund_amount)} refunded · ${d.resolution||''}`:`Customer: ${d.customer_name||'Unknown'} · Driver: ${d.driver_name||'Unassigned'} · ${dateLabel(d.created_at)}`; return `<div class="list-item" data-dispute="${escapeHtml(status)}"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger);font-size:11px">${status==='resolved'?'✓':(status==='escalated'?'🔴':'!')}</div><div class="item-info"><div class="item-name">${escapeHtml(title)}</div><div class="item-meta">${escapeHtml(meta)}</div></div><div class="item-actions">${status==='resolved'?'<span class="badge badge-success">Resolved</span>':`<button class="btn-sm ${status==='escalated'?'btn-reject':'btn-view'}" onclick="openDisputeModal('${escapeHtml(title).replace(/'/g,'&#39;')}', ${Number(d.id)})">Handle</button>`}</div></div>`; }
function queuePayoutSearch(v){ clearTimeout(searchTimers.payouts); searchTimers.payouts=setTimeout(()=>{pageState.payouts.search=v; loadPayouts(1);},300); }
function setPayoutStatus(v){ pageState.payouts.status=v; loadPayouts(1); }
function queueDisputeSearch(v){ clearTimeout(searchTimers.disputes); searchTimers.disputes=setTimeout(()=>{pageState.disputes.search=v; loadDisputes(1);},300); }
function setDisputeStatus(v){ pageState.disputes.status=v; loadDisputes(1); }
async function loadLiveOps(){
  try {
    const data = await adminApi('get_live_ops');
    renderLiveOps(data);
  } catch (err) {
    const list = document.getElementById('opsDriverList');
    if(list) list.innerHTML = `<div class="list-item"><div class="item-info"><div class="item-name">Could not load live operations</div><div class="item-meta">${escapeHtml(err.message)}</div></div></div>`;
  }
}
function renderLiveOps(data){
  const drivers = data.drivers || [];
  const onlineDriverCount = data.metrics?.online_drivers ?? drivers.filter(d => Number(d.is_online) === 1).length;
  const activeTripCount = data.metrics?.active_trips ?? drivers.filter(d => d.trip_id).length;
  document.getElementById('opsOnlineDrivers').textContent = onlineDriverCount;
  document.getElementById('opsActiveTrips').textContent = activeTripCount;
  const newest = drivers.find(d => d.updated_at)?.updated_at || 'No GPS';
  document.getElementById('opsLastLocation').textContent = newest === 'No GPS' ? newest : 'Updated';
  document.getElementById('opsRefreshAge').textContent = 'Now';
  document.getElementById('opsMapLegend').innerHTML = `<span style="color:var(--success)">●</span> ${onlineDriverCount} online &nbsp;<span style="color:var(--info)">●</span> ${activeTripCount} in trip`;
  document.getElementById('opsListMeta').textContent = `Live · ${onlineDriverCount} online`;
  const list = document.getElementById('opsDriverList');
  if(!drivers.length){
    list.innerHTML = '<div class="list-item"><div class="item-info"><div class="item-name">No active drivers right now</div><div class="item-meta">Drivers appear here after heartbeat or active-trip assignment.</div></div></div>';
    return;
  }
  list.innerHTML = drivers.map(driver => {
    const inTrip = !!driver.trip_id;
    const badge = inTrip ? '<span class="badge badge-info">In Trip</span>' : '<span class="badge badge-success">Available</span>';
    const location = driver.lat != null && driver.lng != null ? `${Number(driver.lat).toFixed(5)}, ${Number(driver.lng).toFixed(5)} · ${driver.updated_at || 'recent'}` : 'No last known location';
    const meta = inTrip ? `${escapeHtml(driver.trip_ref)} · ${escapeHtml(driver.dispatch_status)} → ${escapeHtml(driver.dropoff_address || 'drop-off')} · GPS ${escapeHtml(location)}` : `Online · GPS ${escapeHtml(location)}`;
    return `<div class="list-item"><div class="avatar" style="background:rgba(34,196,122,0.1);color:var(--success)">${escapeHtml(initials(driver.full_name || driver.first_name))}</div><div class="item-info"><div class="item-name">${escapeHtml(driver.full_name || driver.first_name || 'Driver')} · ${vehicleIcon(driver.vehicle_type)}</div><div class="item-meta">${meta}</div></div>${badge}</div>`;
  }).join('');
}
setInterval(() => {
  if(document.getElementById('panel-ops')?.classList.contains('active')) loadLiveOps();
}, 15000);

function filterOps(type,btn){
  document.querySelectorAll('.filter-row .filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');toast('Filtering: '+type);
}
function filterTripStatus(status,btn){ document.querySelectorAll('#panel-trips .filter-row .filter-btn').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); pageState.trips.status=status==='all'?'':status; loadTrips(1); }
function filterDisputes(type,btn){ pageState.disputes.status=type; loadDisputes(1); }
function openTripDetail(id,cat,from,to,fare,driver,status){
  document.getElementById('tripModalTitle').textContent='Trip '+id;
  document.getElementById('tripModalBody').innerHTML=`<div class="metrics-grid" style="margin-bottom:16px"><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">CATEGORY</div><div style="font-size:13px;font-weight:600">${cat}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">STATUS</div><div style="font-size:13px;font-weight:600">${status}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">PICKUP</div><div style="font-size:13px;font-weight:600">${from}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">DROP-OFF</div><div style="font-size:13px;font-weight:600">${to}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">FARE</div><div style="font-size:13px;font-weight:600">${fare}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">DRIVER</div><div style="font-size:13px;font-weight:600">${driver}</div></div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:6px">PAYMENT BREAKDOWN</div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Base fare</span><span>${fare}</span></div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;color:var(--success)"><span>Platform comm.</span><span>-20%</span></div><div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;margin-top:6px;padding-top:6px;border-top:1px solid var(--surface-2)"><span>Driver payout</span><span style="color:var(--success)">80%</span></div></div>`;
  document.getElementById('tripModal').classList.add('open');
}
function closeTripModal(){document.getElementById('tripModal').classList.remove('open');}
function openDisputeModal(desc, disputeId=0){
  currentDisputeId=Number(disputeId||0);
  document.getElementById('modalDesc').textContent=desc;
  document.getElementById('disputeModal').classList.add('open');
}
function closeModal(){document.getElementById('disputeModal').classList.remove('open');}
async function resolveDispute(){
  if(!currentDisputeId){ toast('Select a real dispute first.'); return; }
  const action=document.getElementById('resolutionType').value;
  const refund=document.getElementById('refundAmt').value;
  const notes=document.getElementById('resolutionNotes').value;
  try{ const data=await adminApi('resolve_dispute',{dispute_id:currentDisputeId,resolution_action:action,refund_amount:refund||0,admin_notes:notes||''},'POST'); closeModal(); toast(data.message||'Dispute resolved'); loadDisputes(); loadDashboard(); }
  catch(e){ toast(e.message||'Could not resolve dispute'); }
}
function confirmSuspend(name){
  if(confirm('Suspend '+name+'? They will be notified and go offline immediately.')){toast('⚠ '+name+' suspended');}
}
function toggleNotif(){document.getElementById('notifPanel').classList.toggle('open');}
function markAllRead(){
  document.getElementById('notifPanel').classList.remove('open');
  document.querySelector('.notif-dot').style.display='none';
  toast('All notifications marked read');
}
function handleSearch(val){if(val.length>2)toast('Searching: "'+val+'"');}
function searchTrips(val){ clearTimeout(searchTimers.trips); searchTimers.trips=setTimeout(()=>{pageState.trips.search=val; loadTrips(1);},300); }
function filterTrips(cat){ pageState.trips.category=cat; loadTrips(1); }
function toast(msg){
  const t=document.getElementById('toastEl');
  t.textContent=msg;t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),3000);
}
function animateRiders(){
  document.querySelectorAll('.ops-rider').forEach(r=>{
    r.style.top=(10+Math.random()*75)+'%';
    r.style.left=(5+Math.random()*85)+'%';
  });
}
setInterval(animateRiders,4000);
</script>
</body>
</html>