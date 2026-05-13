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
@media(min-width:600px){
  .panel-search{flex-wrap:nowrap;}
  .panel-search input {flex:1;}
  .panel-search select, .panel-search button{flex:none;}
}

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
      <div class="metric-card"><div class="metric-label">ACTIVE RIDERS</div><div class="metric-value">284</div><div class="metric-delta up">↑ +12 since morning</div></div>
      <div class="metric-card"><div class="metric-label">TRIPS TODAY</div><div class="metric-value">1,423</div><div class="metric-delta up">↑ +8.2% vs yesterday</div></div>
      <div class="metric-card"><div class="metric-label">REVENUE TODAY</div><div class="metric-value">₦2.1M</div><div class="metric-delta up">↑ +14.5%</div></div>
      <div class="metric-card"><div class="metric-label">KYC PENDING</div><div class="metric-value" style="color:var(--danger)">7</div><div class="metric-delta down">⚠ Needs attention</div></div>
    </div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">COMPLETION RATE</div><div class="metric-value">96.4%</div><div class="metric-delta neutral">Last 24h</div></div>
      <div class="metric-card"><div class="metric-label">AVG PICKUP TIME</div><div class="metric-value">4.2m</div><div class="metric-delta up">↑ vs 5.1m avg</div></div>
      <div class="metric-card"><div class="metric-label">OPEN DISPUTES</div><div class="metric-value" style="color:var(--warn)">5</div><div class="metric-delta down">2 escalated</div></div>
      <div class="metric-card"><div class="metric-label">SUSPENDED</div><div class="metric-value" style="color:var(--danger)">14</div><div class="metric-delta neutral">Accounts</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Recent Deliveries</h3><button class="scard-action" onclick="nav('trips',document.querySelectorAll('.nav-btn')[3])">View all →</button></div>
      <div>
        <div class="list-item"><div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info)">JO</div><div class="item-info"><div class="item-name">#SD-00928 · Package</div><div class="item-meta">Agip Jctn → D-Line · ₦2,800</div></div><span class="badge badge-info">In Transit</span></div>
        <div class="list-item"><div class="avatar" style="background:rgba(34,196,122,0.1);color:var(--success)">AM</div><div class="item-info"><div class="item-name">#SD-00927 · Gift</div><div class="item-meta">GRA Phase 2 → Woji · ₦4,200</div></div><span class="badge badge-success">Delivered</span></div>
        <div class="list-item"><div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info)">TN</div><div class="item-info"><div class="item-name">#SD-00926 · Documents</div><div class="item-meta">Trans Amadi → Rumuola · ₦1,500</div></div><span class="badge badge-success">Delivered</span></div>
        <div class="list-item"><div class="avatar" style="background:rgba(245,166,35,0.1);color:var(--warn)">KA</div><div class="item-info"><div class="item-name">#SD-00925 · Groceries</div><div class="item-meta">Elelenwo → Rumuigbo · ₦3,100</div></div><span class="badge badge-warn">Delayed</span></div>
        <div class="list-item"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger)">OB</div><div class="item-info"><div class="item-name">#SD-00924 · Laundry</div><div class="item-meta">Rumuola → Stadium Rd · ₦1,800</div></div><span class="badge badge-danger">Cancelled</span></div>
      </div>
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
      <button class="tab active" onclick="kycTab('pending',this)">Pending <span id="kyc-pending-count">(7)</span></button>
      <button class="tab" onclick="kycTab('approved',this)">Approved</button>
      <button class="tab" onclick="kycTab('rejected',this)">Rejected</button>
    </div>
    <div class="filter-row">
      <button class="filter-btn active" onclick="filterKyc('all',this)">All Vehicles</button>
      <button class="filter-btn" onclick="filterKyc('motorbike',this)">Motorbike</button>
      <button class="filter-btn" onclick="filterKyc('car',this)">Car</button>
      <button class="filter-btn" onclick="filterKyc('van',this)">Van</button>
      <button class="filter-btn" onclick="filterKyc('tricycle',this)">Tricycle</button>
    </div>
    <div class="scard" id="kycQueue">
      <div class="kyc-item-wrap" data-type="motorbike"><div class="list-item"><div class="avatar" style="background:rgba(245,200,66,0.12);color:var(--gold-dark)">CN</div><div class="item-info"><div class="item-name">Chidi Nwosu</div><div class="item-meta">🏍 Motorbike · Rivers · Applied 2h ago</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openKycDetail('Chidi Nwosu','Motorbike','Rivers','2h ago','ID: NIN-774920, License: DL-082947')">View</button><button class="btn-sm btn-reject" onclick="kycAction(this,'rejected')">Reject</button><button class="btn-sm btn-approve" onclick="kycAction(this,'approved')">Approve</button></div></div></div>
      <div class="kyc-item-wrap" data-type="car"><div class="list-item"><div class="avatar" style="background:rgba(74,158,255,0.12);color:var(--info)">FA</div><div class="item-info"><div class="item-name">Fatima Abdullahi</div><div class="item-meta">🚗 Car · Kano · Applied 4h ago</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openKycDetail('Fatima Abdullahi','Car','Kano','4h ago','ID: NIN-223841, License: DL-039182')">View</button><button class="btn-sm btn-reject" onclick="kycAction(this,'rejected')">Reject</button><button class="btn-sm btn-approve" onclick="kycAction(this,'approved')">Approve</button></div></div></div>
      <div class="kyc-item-wrap" data-type="van"><div class="list-item"><div class="avatar" style="background:rgba(34,196,122,0.12);color:var(--success)">EM</div><div class="item-info"><div class="item-name">Emeka Mba</div><div class="item-meta">🚐 Van · Lagos · Applied 5h ago</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openKycDetail('Emeka Mba','Van','Lagos','5h ago','ID: NIN-558120, License: DL-112034')">View</button><button class="btn-sm btn-reject" onclick="kycAction(this,'rejected')">Reject</button><button class="btn-sm btn-approve" onclick="kycAction(this,'approved')">Approve</button></div></div></div>
      <div class="kyc-item-wrap" data-type="motorbike"><div class="list-item"><div class="avatar" style="background:rgba(245,200,66,0.12);color:var(--gold-dark)">YS</div><div class="item-info"><div class="item-name">Yewande Sola</div><div class="item-meta">🏍 Motorbike · Ogun · Applied 6h ago</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openKycDetail('Yewande Sola','Motorbike','Ogun','6h ago','ID: NIN-981023, License: DL-567234')">View</button><button class="btn-sm btn-reject" onclick="kycAction(this,'rejected')">Reject</button><button class="btn-sm btn-approve" onclick="kycAction(this,'approved')">Approve</button></div></div></div>
      <div class="kyc-item-wrap" data-type="tricycle"><div class="list-item"><div class="avatar" style="background:rgba(74,158,255,0.12);color:var(--info)">BU</div><div class="item-info"><div class="item-name">Babatunde Usman</div><div class="item-meta">🛺 Tricycle · Abuja · Applied 7h ago</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openKycDetail('Babatunde Usman','Tricycle','Abuja','7h ago','ID: NIN-340291, License: DL-009123')">View</button><button class="btn-sm btn-reject" onclick="kycAction(this,'rejected')">Reject</button><button class="btn-sm btn-approve" onclick="kycAction(this,'approved')">Approve</button></div></div></div>
      <div class="kyc-item-wrap" data-type="car"><div class="list-item"><div class="avatar" style="background:rgba(34,196,122,0.12);color:var(--success)">NA</div><div class="item-info"><div class="item-name">Ngozi Ama</div><div class="item-meta">🚗 Car · Enugu · Applied 9h ago</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openKycDetail('Ngozi Ama','Car','Enugu','9h ago','ID: NIN-672841, License: DL-234567')">View</button><button class="btn-sm btn-reject" onclick="kycAction(this,'rejected')">Reject</button><button class="btn-sm btn-approve" onclick="kycAction(this,'approved')">Approve</button></div></div></div>
      <div class="kyc-item-wrap" data-type="motorbike"><div class="list-item"><div class="avatar" style="background:rgba(245,166,35,0.12);color:var(--warn)">TK</div><div class="item-info"><div class="item-name">Taiwo Kehinde</div><div class="item-meta">🏍 Motorbike · Rivers · Applied 11h ago</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openKycDetail('Taiwo Kehinde','Motorbike','Rivers','11h ago','ID: NIN-112398, License: DL-998234')">View</button><button class="btn-sm btn-reject" onclick="kycAction(this,'rejected')">Reject</button><button class="btn-sm btn-approve" onclick="kycAction(this,'approved')">Approve</button></div></div></div>
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
            <div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">VEHICLE DOCS</div><div style="font-size:12px;font-weight:600">License & Inspection ✓</div></div>
            <div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">PHOTO REVIEW</div><div style="font-size:12px;font-weight:600">Clear portrait ✓</div></div>
            <div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">BANK DETAILS</div><div style="font-size:12px;font-weight:600">UBA · ****4821 ✓</div></div>
          </div>
          <div class="form-group">
            <label class="form-label">Rejection reason (if rejecting)</label>
            <select class="form-input" id="reject-reason">
              <option value="">Select reason…</option>
              <option>Blurry/invalid ID photo</option><option>License expired</option>
              <option>Vehicle inspection failed</option><option>Incomplete documents</option>
              <option>Profile photo invalid (cap/glasses)</option><option>Other</option>
            </select>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
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
      <div class="map-legend"><span style="color:var(--success)">●</span> 284 active &nbsp;<span style="color:var(--info)">●</span> 47 in trip &nbsp;<span style="color:var(--warn)">●</span> 12 delayed</div>
    </div>
    <div class="filter-row">
      <button class="filter-btn active" onclick="filterOps('all',this)">All</button>
      <button class="filter-btn" onclick="filterOps('motorbike',this)">🛵 Motorbike</button>
      <button class="filter-btn" onclick="filterOps('car',this)">🚗 Car</button>
      <button class="filter-btn" onclick="filterOps('van',this)">🚐 Van</button>
      <button class="filter-btn" onclick="filterOps('tricycle',this)">🛺 Tricycle</button>
    </div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">AVG PICKUP TIME</div><div class="metric-value">4.2m</div></div>
      <div class="metric-card"><div class="metric-label">COMPLETION RATE</div><div class="metric-value">96.4%</div></div>
      <div class="metric-card"><div class="metric-label">ON-TIME RATE</div><div class="metric-value">91.8%</div></div>
      <div class="metric-card"><div class="metric-label">AVG FARE</div><div class="metric-value">₦2,840</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Active Riders</h3><span style="font-size:11px;color:var(--text-muted)">Live · 284 online</span></div>
      <div>
        <div class="list-item"><div class="avatar" style="background:rgba(34,196,122,0.1);color:var(--success)">AK</div><div class="item-info"><div class="item-name">Amina Kalu · 🛵</div><div class="item-meta">In transit → GRA Phase 2 · ETA 8min</div></div><span class="badge badge-info">In Trip</span></div>
        <div class="list-item"><div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info)">BE</div><div class="item-info"><div class="item-name">Bayo Eze · 🚗</div><div class="item-meta">Online · D-Line area</div></div><span class="badge badge-success">Available</span></div>
        <div class="list-item"><div class="avatar" style="background:rgba(245,166,35,0.1);color:var(--warn)">CI</div><div class="item-info"><div class="item-name">Chuks Ikenna · 🛺</div><div class="item-meta">Delayed · Trans Amadi</div></div><span class="badge badge-warn">Delayed</span></div>
        <div class="list-item"><div class="avatar" style="background:rgba(34,196,122,0.1);color:var(--success)">DO</div><div class="item-info"><div class="item-name">Dayo Okon · 🛵</div><div class="item-meta">Online · Rumuola area</div></div><span class="badge badge-success">Available</span></div>
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
      <div id="tripList">
        <div class="list-item" data-status="in-transit"><div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info)">JO</div><div class="item-info"><div class="item-name">#SD-00928 · Package</div><div class="item-meta">Agip Jctn → D-Line · ₦2,800 · Driver: Amina K.</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><span class="badge badge-info">In Transit</span><button class="btn-sm btn-view" style="width:100%" onclick="openTripDetail('#SD-00928','Package','Agip Jctn','D-Line','₦2,800','Amina Kalu','In Transit')">Details</button></div></div>
        <div class="list-item" data-status="delivered"><div class="avatar" style="background:rgba(34,196,122,0.1);color:var(--success)">AM</div><div class="item-info"><div class="item-name">#SD-00927 · Gift</div><div class="item-meta">GRA Phase 2 → Woji · ₦4,200 · Driver: Bayo E.</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><span class="badge badge-success">Delivered</span><button class="btn-sm btn-view" style="width:100%" onclick="openTripDetail('#SD-00927','Gift','GRA Phase 2','Woji','₦4,200','Bayo Eze','Delivered')">Details</button></div></div>
        <div class="list-item" data-status="delivered"><div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info)">TN</div><div class="item-info"><div class="item-name">#SD-00926 · Documents</div><div class="item-meta">Trans Amadi → Rumuola · ₦1,500 · Driver: Chuks I.</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><span class="badge badge-success">Delivered</span><button class="btn-sm btn-view" style="width:100%" onclick="openTripDetail('#SD-00926','Documents','Trans Amadi','Rumuola','₦1,500','Chuks Ikenna','Delivered')">Details</button></div></div>
        <div class="list-item" data-status="delayed"><div class="avatar" style="background:rgba(245,166,35,0.1);color:var(--warn)">KA</div><div class="item-info"><div class="item-name">#SD-00925 · Groceries</div><div class="item-meta">Elelenwo → Rumuigbo · ₦3,100 · Driver: Dayo O.</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><span class="badge badge-warn">Delayed</span><button class="btn-sm btn-view" style="width:100%" onclick="openTripDetail('#SD-00925','Groceries','Elelenwo','Rumuigbo','₦3,100','Dayo Okon','Delayed')">Details</button></div></div>
        <div class="list-item" data-status="cancelled"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger)">OB</div><div class="item-info"><div class="item-name">#SD-00924 · Laundry</div><div class="item-meta">Rumuola → Stadium Rd · ₦1,800 · Driver: Emeka M.</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><span class="badge badge-danger">Cancelled</span><button class="btn-sm btn-view" style="width:100%" onclick="openTripDetail('#SD-00924','Laundry','Rumuola','Stadium Rd','₦1,800','Emeka Mba','Cancelled')">Details</button></div></div>
      </div>
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
      <div class="metric-card"><div class="metric-label">TOTAL PENDING</div><div class="metric-value">₦4.2M</div><div class="metric-delta down">42 drivers</div></div>
      <div class="metric-card"><div class="metric-label">PROCESSED TODAY</div><div class="metric-value">₦2.8M</div><div class="metric-delta up">28 processed</div></div>
      <div class="metric-card"><div class="metric-label">FAILED PAYOUTS</div><div class="metric-value" style="color:var(--danger)">3</div><div class="metric-delta down">Review needed</div></div>
      <div class="metric-card"><div class="metric-label">AVG PAYOUT</div><div class="metric-value">₦102K</div><div class="metric-delta neutral">Per driver/wk</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Pending Payouts</h3><button class="btn-primary" style="font-size:11px;padding:6px 12px;width:auto;" onclick="toast('Batch payout initiated for 42 drivers')">Release All (₦4.2M)</button></div>
      <div>
        <div class="list-item"><div class="avatar" style="background:rgba(34,196,122,0.1);color:var(--success)">AK</div><div class="item-info"><div class="item-name">Amina Kalu</div><div class="item-meta">UBA ****4821 · 24 trips this week</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><div style="font-weight:700;font-size:14px">₦138,400</div><button class="btn-sm btn-approve" onclick="toast('₦138,400 released to Amina Kalu')">Release</button></div></div>
        <div class="list-item"><div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info)">BE</div><div class="item-info"><div class="item-name">Bayo Eze</div><div class="item-meta">GTB ****2034 · 19 trips this week</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><div style="font-weight:700;font-size:14px">₦94,700</div><button class="btn-sm btn-approve" onclick="toast('₦94,700 released to Bayo Eze')">Release</button></div></div>
        <div class="list-item"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger)">CI</div><div class="item-info"><div class="item-name">Chuks Ikenna</div><div class="item-meta">Access ****9012 · FAILED · Invalid account</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><div style="font-weight:700;font-size:14px;color:var(--danger)">₦67,200</div><button class="btn-sm btn-reject" onclick="openDisputeModal('Failed payout for Chuks Ikenna')">Fix</button></div></div>
        <div class="list-item"><div class="avatar" style="background:rgba(245,200,66,0.1);color:var(--gold-dark)">DO</div><div class="item-info"><div class="item-name">Dayo Okon</div><div class="item-meta">Zenith ****6622 · 31 trips this week</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><div style="font-weight:700;font-size:14px">₦182,900</div><button class="btn-sm btn-approve" onclick="toast('₦182,900 released to Dayo Okon')">Release</button></div></div>
      </div>
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
      <div class="metric-card"><div class="metric-label">OPEN DISPUTES</div><div class="metric-value" style="color:var(--warn)">5</div><div class="metric-delta down">2 escalated</div></div>
      <div class="metric-card"><div class="metric-label">RESOLVED THIS WEEK</div><div class="metric-value">18</div><div class="metric-delta up">↑ Avg 1.2 days</div></div>
      <div class="metric-card"><div class="metric-label">REFUNDS ISSUED</div><div class="metric-value">₦84K</div><div class="metric-delta neutral">This week</div></div>
    </div>
    <div class="filter-row">
      <button class="filter-btn active" onclick="filterDisputes('all',this)">All</button>
      <button class="filter-btn" onclick="filterDisputes('open',this)">Open</button>
      <button class="filter-btn" onclick="filterDisputes('escalated',this)">Escalated</button>
      <button class="filter-btn" onclick="filterDisputes('resolved',this)">Resolved</button>
    </div>
    <div class="scard">
      <div>
        <div class="list-item" data-dispute="open"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger);font-size:11px">!</div><div class="item-info"><div class="item-name">#D-0052 · Late delivery</div><div class="item-meta">Customer: Ifeanyi O. · Driver: Chuks I. · 1 day old</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openDisputeModal('#D-0052 — Late delivery')">Handle</button></div></div>
        <div class="list-item" data-dispute="escalated"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger);font-size:11px">🔴</div><div class="item-info"><div class="item-name">#D-0051 · Package damaged</div><div class="item-meta">Customer: Ada B. · Driver: Mike N. · 3 days old · ESCALATED</div></div><div class="item-actions"><button class="btn-sm btn-reject" onclick="openDisputeModal('#D-0051 — Package damaged, ESCALATED')">Escalated</button></div></div>
        <div class="list-item" data-dispute="open"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger);font-size:11px">!</div><div class="item-info"><div class="item-name">#D-0050 · Wrong drop-off</div><div class="item-meta">Customer: Tunde L. · Driver: Bayo E. · 2 days old</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openDisputeModal('#D-0050 — Wrong drop-off')">Handle</button></div></div>
        <div class="list-item" data-dispute="resolved"><div class="avatar" style="background:rgba(34,196,122,0.1);color:var(--success);font-size:11px">✓</div><div class="item-info"><div class="item-name">#D-0049 · Rider rude behaviour</div><div class="item-meta">Resolved · ₦2,800 refunded · Driver warned</div></div><span class="badge badge-success">Resolved</span></div>
        <div class="list-item" data-dispute="escalated"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger);font-size:11px">🔴</div><div class="item-info"><div class="item-name">#D-0048 · Theft allegation</div><div class="item-meta">Customer: Chioma A. · Driver suspended · 2 days old · ESCALATED</div></div><div class="item-actions"><button class="btn-sm btn-reject" onclick="openDisputeModal('#D-0048 — Theft allegation, ESCALATED')">Critical</button></div></div>
      </div>
    </div>
  </div>

  <!-- SETTINGS -->
  <div class="panel" id="panel-settings">
    <div class="page-header"><h2 class="page-title">Settings</h2><div class="page-sub">Platform configuration and policies</div></div>
    <div class="settings-section">
      <h4>Commission & Pricing</h4>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Platform commission (%)</label><input class="form-input" type="number" value="20" min="1" max="50"></div>
        <div class="form-group"><label class="form-label">Surge multiplier cap (×)</label><input class="form-input" type="number" value="2.5" min="1" step="0.1"></div>
        <div class="form-group"><label class="form-label">Min. fare (₦)</label><input class="form-input" type="number" value="800"></div>
        <div class="form-group"><label class="form-label">Max. delivery radius (km)</label><input class="form-input" type="number" value="50"></div>
      </div>
    </div>
    <div class="settings-section">
      <h4>KYC Policy</h4>
      <div class="toggle-row"><div><div class="toggle-label">Auto-flag blurry ID photos</div><div class="toggle-sub">AI-assisted photo quality check</div></div><button class="toggle on" onclick="this.classList.toggle('on')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Require vehicle inspection report</div><div class="toggle-sub">Mandatory for vans and tricycles</div></div><button class="toggle on" onclick="this.classList.toggle('on')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">72-hour KYC review SLA alert</div><div class="toggle-sub">Email admin if review exceeds 72h</div></div><button class="toggle on" onclick="this.classList.toggle('on')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Background check integration</div><div class="toggle-sub">Third-party criminal record API</div></div><button class="toggle" onclick="this.classList.toggle('on')"></button></div>
    </div>
    <div class="settings-section">
      <h4>Notifications</h4>
      <div class="toggle-row"><div><div class="toggle-label">KYC queue alerts</div><div class="toggle-sub">Email when queue exceeds 5</div></div><button class="toggle on" onclick="this.classList.toggle('on')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Dispute escalation alerts</div><div class="toggle-sub">Push alert when dispute >48h unresolved</div></div><button class="toggle on" onclick="this.classList.toggle('on')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Daily revenue digest</div><div class="toggle-sub">Email summary at 8pm daily</div></div><button class="toggle on" onclick="this.classList.toggle('on')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Failed payout alerts</div><div class="toggle-sub">Instant alert on payout failures</div></div><button class="toggle on" onclick="this.classList.toggle('on')"></button></div>
    </div>
    <div class="settings-section">
      <h4>Legal & Compliance</h4>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 14px;background:var(--navy-light)" onclick="toast('Opening Terms & Conditions…')">Terms & Conditions</button>
        <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 14px;background:var(--navy-light)" onclick="toast('Opening Privacy Policy…')">Privacy Policy</button>
        <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 14px;background:var(--navy-light)" onclick="toast('Opening Location Data Policy…')">Location Data Policy</button>
        <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 14px;background:var(--navy-light)" onclick="toast('Opening Software License…')">Software License</button>
      </div>
    </div>
    <button class="btn-primary" onclick="toast('Settings saved successfully ✓')">Save Changes</button>
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
      <div class="form-group"><label class="form-label">Admin notes</label><textarea class="form-input" rows="3" placeholder="Add resolution notes…" style="resize:none"></textarea></div>
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
  if(window.innerWidth < 900) {
    document.getElementById('sidebar').classList.remove('open');
    document.querySelector('.sidebar-overlay').classList.remove('open');
  }
}

let kycCount=7;
function kycAction(btn,action){
  const item=btn.closest('.kyc-item-wrap');
  item.style.opacity='0';item.style.transform='translateX(20px)';item.style.transition='all 0.3s';
  setTimeout(()=>{item.remove();kycCount=Math.max(0,kycCount-1);updateKycBadge();},300);
  toast(action==='approved'?'✓ Driver approved & notified':'✗ Application rejected');
}
function updateKycBadge(){
  document.getElementById('kyc-badge').textContent=kycCount;
  document.getElementById('kyc-pending-count').textContent='('+kycCount+')';
  if(kycCount===0)document.getElementById('kyc-empty').style.display='block';
}
function kycTab(tab,btn){
  document.querySelectorAll('.tabs .tab').forEach(t=>t.classList.remove('active'));
  btn.classList.add('active');
  if(tab==='approved')toast('Showing 28 approved drivers');
  else if(tab==='rejected')toast('Showing 3 rejected applications');
}
function filterKyc(type,btn){
  document.querySelectorAll('.filter-row .filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.kyc-item-wrap').forEach(item=>{
    item.style.display=(type==='all'||item.dataset.type===type)?'':'none';
  });
}
let currentKycName='';
function openKycDetail(name,vehicle,state,time,docs){
  currentKycName=name;
  document.getElementById('detail-name').textContent=name;
  document.getElementById('detail-meta').textContent=vehicle+' · '+state+' · Applied '+time;
  document.getElementById('detail-avatar').textContent=name.split(' ').map(n=>n[0]).join('').slice(0,2);
  document.getElementById('detail-docs').textContent=docs;
  document.getElementById('kycDetail').classList.add('open');
}
function closeKycDetail(){document.getElementById('kycDetail').classList.remove('open');}
function kycDetailAction(action){
  closeKycDetail();
  toast(action==='approved'?'✓ '+currentKycName+' approved':'✗ '+currentKycName+' rejected');
  kycCount=Math.max(0,kycCount-1);updateKycBadge();
}
function filterOps(type,btn){
  document.querySelectorAll('.filter-row .filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');toast('Filtering: '+type);
}
function filterTripStatus(status,btn){
  document.querySelectorAll('.filter-row .filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#tripList .list-item').forEach(item=>{
    item.style.display=(status==='all'||item.dataset.status===status)?'':'none';
  });
}
function filterDisputes(type,btn){
  document.querySelectorAll('.filter-row .filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('[data-dispute]').forEach(item=>{
    item.style.display=(type==='all'||item.dataset.dispute===type)?'':'none';
  });
}
function openTripDetail(id,cat,from,to,fare,driver,status){
  document.getElementById('tripModalTitle').textContent='Trip '+id;
  document.getElementById('tripModalBody').innerHTML=`<div class="metrics-grid" style="margin-bottom:16px"><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">CATEGORY</div><div style="font-size:13px;font-weight:600">${cat}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">STATUS</div><div style="font-size:13px;font-weight:600">${status}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">PICKUP</div><div style="font-size:13px;font-weight:600">${from}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">DROP-OFF</div><div style="font-size:13px;font-weight:600">${to}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">FARE</div><div style="font-size:13px;font-weight:600">${fare}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">DRIVER</div><div style="font-size:13px;font-weight:600">${driver}</div></div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:6px">PAYMENT BREAKDOWN</div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Base fare</span><span>${fare}</span></div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;color:var(--success)"><span>Platform comm.</span><span>-20%</span></div><div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;margin-top:6px;padding-top:6px;border-top:1px solid var(--surface-2)"><span>Driver payout</span><span style="color:var(--success)">80%</span></div></div>`;
  document.getElementById('tripModal').classList.add('open');
}
function closeTripModal(){document.getElementById('tripModal').classList.remove('open');}
function openDisputeModal(desc){
  document.getElementById('modalDesc').textContent=desc;
  document.getElementById('disputeModal').classList.add('open');
}
function closeModal(){document.getElementById('disputeModal').classList.remove('open');}
function resolveDispute(){
  const action=document.getElementById('resolutionType').value;
  const refund=document.getElementById('refundAmt').value;
  closeModal();
  toast('✓ Dispute resolved: '+action+(refund?' · ₦'+Number(refund).toLocaleString()+' refunded':''));
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
function searchTrips(val){
  if(!val){document.querySelectorAll('#tripList .list-item').forEach(i=>i.style.display='');return;}
  document.querySelectorAll('#tripList .list-item').forEach(item=>{
    item.style.display=item.textContent.toLowerCase().includes(val.toLowerCase())?'':'none';
  });
}
function filterTrips(cat){
  if(!cat){document.querySelectorAll('#tripList .list-item').forEach(i=>i.style.display='');return;}
  document.querySelectorAll('#tripList .list-item').forEach(item=>{
    item.style.display=item.textContent.toLowerCase().includes(cat.toLowerCase())?'':'none';
  });
}
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