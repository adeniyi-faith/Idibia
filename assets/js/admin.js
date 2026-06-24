/* Login Script */
const loginForm = document.getElementById('adminLoginForm');
if (loginForm) {

  // Show session-expired or kicked message if redirected here
  const _urlParams = new URLSearchParams(window.location.search);
  if (_urlParams.get('session') === 'expired') {
    const _err = document.getElementById('adminLoginError');
    _err.textContent = 'Your session expired. Please sign in again.';
    _err.classList.add('show');
  }

  // Password eye toggle
  const toggleBtn = document.getElementById('togglePassword');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      const input = document.getElementById('adminPassword');
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      toggleBtn.querySelector('.eye-open').style.display  = show ? 'none'  : '';
      toggleBtn.querySelector('.eye-closed').style.display = show ? '' : 'none';
      toggleBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  }

  loginForm.addEventListener('submit', async (event) => {
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

}


/* Admin App Script */

// Intercept every fetch call to the admin API.
// Only redirect to login on genuine authentication failures (no valid session).
// Permission-denied 403s are returned to the caller to handle gracefully.
(function () {
  const _origFetch = window.fetch.bind(window);
  window.fetch = async function (url, opts) {
    const res = await _origFetch(url, opts);
    if (res.status === 403 && String(url).includes('/admin/api')) {
      try {
        const clone = res.clone();
        const json = await clone.json();
        const msg = (json && json.data && json.data.message) ? json.data.message : '';
        if (msg === 'Unauthorized access.' || msg === 'Not authenticated as admin') {
          window.location.replace('/admin.php?session=expired');
          throw new Error('Session expired — redirecting to login.');
        }
        // Permission denied — return the original response so callers can handle it.
        return res;
      } catch (e) {
        if (e.message === 'Session expired — redirecting to login.') throw e;
        // JSON parse failed or unexpected error — treat as authentication failure.
        window.location.replace('/admin.php?session=expired');
        throw new Error('Session expired — redirecting to login.');
      }
    }
    return res;
  };
})();

const panels={overview:'Platform Overview',kyc:'KYC Review Queue',ops:'Live Operations',trips:'Deliveries',revenue:'Revenue Analytics',reconciliation:'Reconciliation',payouts:'Driver Payouts','wallet-topups':'Wallet Top-Ups',drivers:'Drivers',customers:'Customers',disputes:'Disputes',ratings:'Ratings',settings:'Settings','admin-users':'Admin Users',campaigns:'Driver Campaigns',notifications:'Notifications',system:'System Health'};
const subs={overview:'Live · '+new Date().toLocaleDateString(undefined,{weekday:'short',month:'short',day:'numeric',year:'numeric'}),kyc:'Applications awaiting review',ops:'Real-time trip tracking',trips:'All trips and tracking',revenue:'Platform commission · monthly totals',reconciliation:'Finance payment verification',payouts:'Earnings management','wallet-topups':'Customer bank transfer funding requests — approve to credit wallets',drivers:'Driver records from database',customers:'Customer accounts from database',disputes:'Complaints & escalations',ratings:'All platform ratings — filter, flag, and remove abusive reviews',settings:'Platform configuration','admin-users':'Manage internal staff accounts, roles, and granular permissions',campaigns:'Create and monitor driver incentive challenges',notifications:'Compose broadcasts and view delivery history',system:'Live platform status · gateways · cron · dispatch pipeline'};

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
  closeAdminUserPanel();
  closeSubjectRatingsPanel();

  // Close sidebar on mobile after navigation
  if(name === 'system') { if(typeof systemHealthInit==='function') systemHealthInit(); } else { if(typeof systemHealthStop==='function') systemHealthStop(); }
  if(name === 'overview') loadDashboard();
  if(name === 'ops') loadLiveOps();
  if(name === 'trips') loadTrips();
  if(name === 'payouts') { loadPayouts(); loadManualPaymentsPayouts(); }
  if(name === 'wallet-topups') loadWalletTopups(1);
  if(name === 'drivers') { driverPanelTab('drivers', document.getElementById('driverTabDrivers')); loadDrivers(); }
  if(name === 'customers') loadCustomers();
  if(name === 'disputes') loadDisputes();
  if(name === 'settings') { loadPaymentSettings(); loadManualPayments(); loadKycPolicy(); }
  if(name === 'reconciliation') loadReconciliation();
  if(name === 'revenue') loadRevenue();
  if(name === 'admin-users') loadAdminUsers();
  if(name === 'ratings') loadRatings();
  if(name === 'campaigns') loadCampaigns(1);

  if(window.innerWidth < 900) {
    document.getElementById('sidebar').classList.remove('open');
    document.querySelector('.sidebar-overlay').classList.remove('open');
  }
}

let kycCount = 0;
let currentKycName = '';
let currentKycId = 0;
let currentKycTab = 'under_review';
let currentKycFilter = 'all';
let kycDrivers = [];
let kycUploadBaseUrl = '';
const pageState = { trips:{page:1, per_page:10, search:'', status:'', category:''}, payouts:{page:1, per_page:10, search:'', status:'pending'}, disputes:{page:1, per_page:10, search:'', status:'all'}, drivers:{page:1, per_page:10, search:'', status:''}, customers:{page:1, per_page:10, search:''}, reconciliation:{page:1, per_page:10, search:'', status:'all', start_date:'', end_date:''}, walletTopups:{page:1, per_page:20} };
let searchTimers = {};
let currentDisputeId = 0;

function escapeHtml(value){
  return String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
}
function emptyState(icon, title, subtitle){
  return `<div style="text-align:center;padding:40px 20px;color:var(--text-muted,#888)"><div style="font-size:2rem;margin-bottom:10px">${icon}</div><div style="font-weight:600;font-size:15px;margin-bottom:4px;color:var(--text,#222)">${escapeHtml(title)}</div><div style="font-size:13px">${escapeHtml(subtitle)}</div></div>`;
}
function skeletonRows(count=5){
  const row='<div class="list-item"><div class="sk-circle"></div><div class="item-info"><div class="sk-line" style="width:55%;margin-bottom:6px"></div><div class="sk-line" style="width:38%"></div></div><div style="margin-left:auto;display:flex;gap:6px"><div class="sk-line" style="width:52px;height:28px;border-radius:8px"></div></div></div>';
  return Array(count).fill(row).join('');
}
function skeletonTableRows(cols=5,count=5){
  const cell='<td style="padding:10px 12px"><div class="sk-line" style="width:70%"></div></td>';
  return Array(count).fill('<tr>'+cell.repeat(cols)+'</tr>').join('');
}
function vehicleLabel(type){
  return {bike:'Motorbike',car:'Car',van:'Van',keke:'Tricycle'}[type] || (type ? type : 'Vehicle');
}
function vehicleIcon(type){
  const s='viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="vertical-align:middle"';
  const icons={
    bike:`<svg ${s}><circle cx="5" cy="16" r="2"/><circle cx="19" cy="16" r="2"/><path d="M5 16L9 8h5l3 4h3"/></svg>`,
    car:`<svg ${s}><rect x="2" y="9" width="20" height="8" rx="2"/><path d="M5 9l2-4h10l2 4"/><circle cx="7" cy="17" r="1.5"/><circle cx="17" cy="17" r="1.5"/></svg>`,
    van:`<svg ${s}><rect x="1" y="6" width="15" height="12" rx="1"/><path d="M16 9l5 3v6h-5"/><circle cx="5" cy="18" r="1.5"/><circle cx="12" cy="18" r="1.5"/></svg>`,
    keke:`<svg ${s}><circle cx="5" cy="17" r="2"/><circle cx="12" cy="17" r="2"/><path d="M5 17V9h7v8"/><path d="M12 12h5v5h-2"/></svg>`
  };
  return icons[type] || `<svg ${s}><rect x="1" y="6" width="13" height="12" rx="1"/><path d="M14 9l6 2v7h-6"/><circle cx="5" cy="18" r="1.5"/><circle cx="11" cy="18" r="1.5"/><circle cx="18" cy="18" r="1.5"/></svg>`;
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
  if(method !== 'GET') {
    body.append('action', action);
  }
  const response = await fetch(url.toString(), {
    method,
    body: method === 'GET' ? undefined : body,
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' }
  });
  const rawText = await response.text();
  let data;
  try { data = JSON.parse(rawText); } catch (e) { console.error('Raw admin API response:', rawText); throw new Error('Invalid server response'); }
  if(!data.success){ console.error('Admin API failure [' + action + ']:', rawText); throw new Error(data.data?.message || 'Admin request failed.'); }
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
    queue.innerHTML = '<div id="kyc-empty">'+emptyState('🪪', currentKycTab === 'under_review' ? 'No KYC applications pending' : 'No '+escapeHtml(currentKycTab.replace('_',' '))+' applications', currentKycTab === 'under_review' ? 'All submissions have been reviewed.' : 'New driver KYC submissions will appear here.')+'</div>';
    return;
  }
  queue.innerHTML = visible.map(driver => {
    const canReview = driver.kyc_status === 'under_review';
    const isResubmit = canReview && driver.kyc_rejection_history && driver.kyc_rejection_history !== '';
    const applied = formatApplied(driver.created_at);
    const state = driver.emergency_address || driver.vehicle_plate || 'Submitted KYC';
    const resubBadge = isResubmit ? `<span class="badge" style="background:rgba(245,160,0,0.15);color:var(--gold-dark);font-size:10px;margin-left:4px">Resubmission</span>` : '';
    return `<div class="kyc-item-wrap" data-driver-id="${Number(driver.id)}" data-type="${escapeHtml(driver.vehicle_type)}"><div class="list-item"><div class="avatar" style="background:rgba(245,200,66,0.12);color:var(--gold-dark)">${escapeHtml(initials(driver.full_name))}</div><div class="item-info"><div class="item-name">${escapeHtml(driver.full_name || 'Unnamed driver')}${resubBadge}</div><div class="item-meta">${vehicleIcon(driver.vehicle_type)} ${escapeHtml(vehicleLabel(driver.vehicle_type))} · ${escapeHtml(state)} · Applied ${escapeHtml(applied)}</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="openKycDetailById(${Number(driver.id)})">View</button>${canReview ? `<button class="btn-sm btn-reject" onclick="kycAction(this,'rejected')">Reject</button><button class="btn-sm btn-approve" onclick="kycAction(this,'approved')">Approve</button>` : `<span class="badge ${driver.kyc_status === 'approved' ? 'badge-success' : 'badge-danger'}">${escapeHtml(driver.kyc_status)}</span>`}</div></div></div>`;
  }).join('');
}
async function loadKycQueue(status = currentKycTab){
  currentKycTab = status;
  const queue = document.getElementById('kycQueue');
  queue.innerHTML = skeletonRows(4);
  try {
    const isResubmit = (status === 'resubmission');
    const params = isResubmit ? { kyc_status: 'under_review', is_resubmission: '1' } : { kyc_status: status };
    const data = await adminApiAllPages('get_drivers', params);
    kycDrivers = data.drivers || [];
    kycUploadBaseUrl = (data.upload_base_url || '').replace(/\/$/, '');
    if(status === 'under_review') {
      kycCount = Number(data.total || kycDrivers.length || 0);
      updateKycBadge();
    }
    if(isResubmit) {
      const countEl = document.getElementById('kyc-resubmit-count');
      if(countEl) countEl.textContent = kycDrivers.length ? '('+kycDrivers.length+')' : '';
    }
    renderKycQueue();
  } catch (e) {
    queue.innerHTML = emptyState('⚠️', 'Could not load KYC applications', escapeHtml(e.message));
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
  let notes;
  if(action === 'rejected'){
    const reason = await showConfirmDialog({
      title: 'Reject KYC Application',
      desc: 'Reject the KYC application for ' + (driver.full_name || 'this driver') + '? They will be notified with the reason provided.',
      reasonLabel: 'Rejection reason',
      reasonRequired: true,
      useSelect: true,
      confirmLabel: 'Reject Application',
    });
    if(reason === null) return;
    notes = reason || 'Rejected from admin KYC review.';
  } else {
    notes = 'Approved from admin KYC review.';
  }
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
    console.error('loadPaymentSettings error:', e);
    toast('Could not load settings: ' + (e.message || 'Unknown error'));
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
    const data = await adminApi('get_manual_payments', {status:'proof_submitted', per_page:20});
    const payments = data.payments || [];
    if(!payments.length){
      list.innerHTML = emptyState('🧾','No manual transfers pending review','Customer receipt uploads will appear here.');
      return;
    }
    list.innerHTML = payments.map(p => `
      <div class="list-item" data-payment-id="${p.id}">
        <div class="avatar" style="background:rgba(245,200,66,0.1);color:var(--gold-dark)">₦</div>
        <div class="item-info">
          <div class="item-name">${escapeHtml(p.trip_ref || ('Trip #' + p.trip_id))} · ₦${Number(p.amount || 0).toLocaleString()}</div>
          <div class="item-meta">${escapeHtml(p.customer_name || 'Customer')} · ${escapeHtml(p.provider_ref || 'No reference')} · ${escapeHtml(p.status)}</div>
          ${p.proof_url ? `<button onclick="viewProofImage('${escapeHtml(p.proof_url)}','Receipt – ${escapeHtml(p.trip_ref||'Trip #'+p.trip_id)}')" style="background:none;border:none;padding:0;font-size:12px;color:var(--info);font-weight:700;cursor:pointer;text-decoration:underline">View Receipt</button>` : '<span style="font-size:12px;color:var(--danger)">No proof uploaded</span>'}
        </div>
        <div class="item-actions">
          <button class="btn-sm btn-approve" onclick="reviewManualPayment(${p.id}, 'approve')">Approve</button>
          <button class="btn-sm btn-reject" onclick="reviewManualPayment(${p.id}, 'reject')">Reject</button>
        </div>
      </div>`).join('');
  } catch (e) {
    list.innerHTML = emptyState('⚠️','Could not load manual payments',escapeHtml(e.message));
  }
}

async function reviewManualPayment(paymentId, decision, source){
  let notes = '';
  if(decision === 'reject'){
    const reason = await showConfirmDialog({
      title: 'Reject Payment Proof',
      desc: 'Reject this payment proof? The customer will be notified and the trip will remain unpaid.',
      reasonLabel: 'Reason for rejection',
      reasonRequired: true,
      confirmLabel: 'Reject Payment',
    });
    if(reason === null) return;
    notes = reason;
  }
  try {
    const data = await adminApi('review_manual_payment', {payment_id: paymentId, decision, admin_notes: notes}, 'POST');
    toast(data.message || (decision === 'approve' ? 'Payment approved' : 'Payment rejected'));
    if(source === 'payouts') loadManualPaymentsPayouts(); else loadManualPayments();
  } catch (e) {
    toast(e.message || 'Could not review payment');
  }
}

async function loadManualPaymentsPayouts(){
  const list = document.getElementById('manualPaymentsListPayouts');
  if(!list) return;
  const status = document.getElementById('manualPaymentsStatus')?.value || 'proof_submitted';
  list.innerHTML = skeletonRows(3);
  try {
    const data = await adminApi('get_manual_payments', {status, per_page: 20});
    const payments = data.payments || [];
    if(!payments.length){
      list.innerHTML = emptyState('🧾','No manual transfers for this status','Customer uploads will appear here once submitted.');
      return;
    }
    const isPending = status === 'proof_submitted';
    list.innerHTML = payments.map(p => {
      const statusBadge = `<span style="font-size:11px;font-weight:700;padding:2px 7px;border-radius:20px;background:${p.status==='captured'?'rgba(34,197,94,0.12)':p.status==='rejected'?'rgba(239,68,68,0.12)':'rgba(245,200,66,0.12)'};color:${p.status==='captured'?'var(--success)':p.status==='rejected'?'var(--danger)':'var(--gold-dark)'}">${p.status==='captured'?'Approved':p.status.replace('_',' ')}</span>`;
      return `<div class="list-item" data-payment-id="${p.id}">
        <div class="avatar" style="background:rgba(245,200,66,0.1);color:var(--gold-dark)">₦</div>
        <div class="item-info">
          <div class="item-name">${escapeHtml(p.trip_ref || ('Trip #'+p.trip_id))} · ₦${Number(p.amount||0).toLocaleString()} ${statusBadge}</div>
          <div class="item-meta">${escapeHtml(p.customer_name||'Customer')} · Ref: ${escapeHtml(p.provider_ref||'—')} · ${escapeHtml(p.customer_phone||'')}</div>
          ${p.proof_url
            ? `<button onclick="viewProofImage('${escapeHtml(p.proof_url)}','Receipt – ${escapeHtml(p.trip_ref||'Trip #'+p.trip_id)}')" style="background:none;border:none;padding:0;font-size:12px;color:var(--info);font-weight:700;cursor:pointer;text-decoration:underline">View Receipt</button>`
            : '<span style="font-size:12px;color:var(--danger)">No proof uploaded</span>'}
          ${p.admin_notes ? `<div style="font-size:11px;color:var(--text-secondary);margin-top:2px">Note: ${escapeHtml(p.admin_notes)}</div>` : ''}
        </div>
        <div class="item-actions">
          ${isPending ? `<button class="btn-sm btn-approve" onclick="reviewManualPayment(${p.id},'approve','payouts')">Approve</button><button class="btn-sm btn-reject" onclick="reviewManualPayment(${p.id},'reject','payouts')">Reject</button>` : ''}
        </div>
      </div>`;
    }).join('');
  } catch(e) {
    list.innerHTML = '<div class="list-item"><div class="item-info"><div class="item-name">Could not load manual transfers</div><div class="item-meta">'+escapeHtml(e.message)+'</div></div></div>';
  }
}

function viewProofImage(url, caption){
  const modal = document.getElementById('proofModal');
  if(!modal) { window.open(url,'_blank','noopener'); return; }
  document.getElementById('proofModalImg').src = url;
  document.getElementById('proofModalImg').style.display = '';
  document.getElementById('proofModalFallback').style.display = 'none';
  document.getElementById('proofModalFallbackLink').href = url;
  document.getElementById('proofModalCaption').textContent = caption || 'Payment Receipt';
  document.getElementById('proofModalDownload').href = url;
  modal.classList.add('open');
}
function closeProofModal(){
  const modal = document.getElementById('proofModal');
  if(modal){ modal.classList.remove('open'); document.getElementById('proofModalImg').src = ''; }
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

  // Rejection history
  const historyPanel = document.getElementById('kycRejectionHistoryPanel');
  if(historyPanel){
    let history = [];
    if(driver.kyc_rejection_history){ try{ history = JSON.parse(driver.kyc_rejection_history); }catch(_){} }
    if(history.length){
      historyPanel.innerHTML = `<div style="margin:12px 0;padding:12px 14px;background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.2);border-radius:10px">
        <div style="font-size:11px;font-weight:700;color:var(--danger);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px">Previous Rejection${history.length>1?'s':''}</div>
        ${history.map((h,i)=>`<div style="${i<history.length-1?'margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid rgba(239,68,68,0.15)':''}">
          <div style="font-size:11px;color:var(--text-muted)">${h.rejected_at ? formatApplied(h.rejected_at) : 'Earlier'}</div>
          <div style="font-size:13px;margin-top:2px">${escapeHtml(h.reason||'No reason recorded')}</div>
        </div>`).join('')}
      </div>`;
    } else {
      historyPanel.innerHTML = '';
    }
  }

  // Document links
  const docLinksPanel = document.getElementById('kycDocLinks');
  if(docLinksPanel && kycUploadBaseUrl){
    const docFields = [
      {field:'id_front_path', label:'ID Front'},
      {field:'id_back_path', label:'ID Back'},
      {field:'selfie_path', label:'Selfie'},
      {field:'vehicle_license_doc_path', label:'Vehicle License'},
      {field:'insurance_doc_path', label:'Inspection Doc'},
      {field:'vehicle_photo_path', label:'Exterior'},
      {field:'vehicle_interior_photo_path', label:'Interior'},
      {field:'vehicle_front_photo_path', label:'Front'},
      {field:'vehicle_rear_photo_path', label:'Rear'},
    ];
    const available = docFields.filter(d => driver[d.field]);
    if(available.length){
      docLinksPanel.innerHTML = `<div style="margin:12px 0"><div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Documents</div><div style="display:flex;flex-wrap:wrap;gap:6px">${available.map(d=>`<button onclick="viewProofImage('${escapeHtml(kycUploadBaseUrl+'/'+driver[d.field])}','${escapeHtml(d.label+' – '+currentKycName)}')" class="btn-sm btn-view" style="font-size:11px">${escapeHtml(d.label)}</button>`).join('')}</div></div>`;
    } else {
      docLinksPanel.innerHTML = '';
    }
  } else if(docLinksPanel){
    docLinksPanel.innerHTML = '';
  }

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
if (document.getElementById('app')) {
  loadMyPermissions().then(() => {
    loadDashboard();
    if (iHavePermission('view_drivers') || iHavePermission('view_kyc') || iHavePermission('approve_reject_kyc')) loadKycQueue('under_review');
    if (iHavePermission('view_trips')) loadTrips();
    if (iHavePermission('execute_payouts') || iHavePermission('view_export_revenue')) loadPayouts();
    if (iHavePermission('view_disputes')) loadDisputes();
  });
}


async function loadDashboard(){
  const list=document.getElementById('overviewRecentTrips');
  if(list) list.innerHTML=skeletonRows(4);
  try{
    const data=await adminApi('get_dashboard_stats');
    document.getElementById('overviewActiveDrivers').textContent=Number(data.active_drivers||0).toLocaleString();
    document.getElementById('overviewOnlineDrivers').textContent=Number(data.online_drivers||0).toLocaleString()+' online now';
    document.getElementById('overviewTripsToday').textContent=Number(data.trips_today||0).toLocaleString();
    const diff=Number(data.trips_today||0)-Number(data.trips_yesterday||0);
    document.getElementById('overviewTripsDelta').textContent=(diff>=0?'↑ +':'↓ ')+diff.toLocaleString()+' vs yesterday';
    document.getElementById('overviewRevenueToday').textContent=formatMoney(data.revenue_today);
    document.getElementById('overviewKycPending').textContent=Number(data.kyc_pending||0).toLocaleString();
    document.getElementById('kyc-badge').textContent=Number(data.kyc_pending||0).toLocaleString();
    document.getElementById('overviewCompletionRate').textContent=Number(data.completion_rate||0).toFixed(1)+'%';
    document.getElementById('overviewAvgPickup').textContent=Number(data.avg_pickup_time||0).toFixed(1)+'m';
    document.getElementById('overviewOpenDisputes').textContent=Number(data.open_disputes||0).toLocaleString();
    document.getElementById('dispute-badge').textContent=Number(data.open_disputes||0).toLocaleString();
    document.getElementById('overviewEscalatedDisputes').textContent=Number(data.escalated_disputes||0).toLocaleString()+' escalated';
    document.getElementById('overviewSuspended').textContent=Number(data.suspended_drivers||0).toLocaleString();
    const trips=data.recent_trips||[];
    list.innerHTML=trips.length?trips.map(renderTripListItem).join(''):emptyState('📦','No deliveries yet','Recent trips will appear here.');
    renderActivityChart(data.activity_trend||[]);
  }catch(e){ if(list) list.innerHTML=emptyState('⚠️','Could not load dashboard',escapeHtml(e.message)); }
}
function renderActivityChart(trend){
  const chartEl=document.getElementById('overviewActivityChart');
  const labelsEl=document.getElementById('overviewActivityLabels');
  const summaryEl=document.getElementById('overviewActivitySummary');
  if(!chartEl||!labelsEl||!summaryEl) return;
  const counts=trend.map(d=>d.trips||0);
  const max=Math.max(...counts,1);
  const todayIdx=trend.length-1;
  chartEl.innerHTML=trend.map((d,i)=>{
    const pct=Math.max(4,Math.round((d.trips/max)*100));
    const isToday=i===todayIdx;
    const bg=isToday?'var(--gold)':'var(--navy-light)';
    return `<div title="${escapeHtml(d.label+': '+Number(d.trips).toLocaleString()+' trips')}" style="flex:1;background:${bg};border-radius:3px 3px 0 0;height:${pct}%;cursor:default"></div>`;
  }).join('');
  labelsEl.innerHTML=trend.map((d,i)=>{
    const isToday=i===todayIdx;
    return `<span style="${isToday?'color:var(--gold);font-weight:700':''}">${escapeHtml(d.label.slice(0,2))}</span>`;
  }).join('');
  const peakDay=trend.reduce((a,b)=>b.trips>a.trips?b:a,trend[0]||{trips:0,label:'—'});
  summaryEl.innerHTML=`Peak: ${escapeHtml(peakDay.label)} · <span style="color:var(--success)">${Number(peakDay.trips).toLocaleString()} trips</span>`;
}
function renderTripListItem(trip){
  const status=trip.dispatch_status && trip.dispatch_status !== 'completed' ? trip.dispatch_status : trip.status;
  const fare=trip.fare_amount ?? trip.final_fare ?? trip.fare_estimate ?? trip.fare;
  const from=trip.pickup_address || trip.pickup || 'Pickup';
  const to=trip.dropoff_address || trip.dropoff || 'Drop-off';
  const category=trip.service_category || trip.category || 'Delivery';
  const detail=[from+' → '+to, formatMoney(fare), trip.driver_name?'Driver: '+trip.driver_name:null].filter(Boolean).map(escapeHtml).join(' · ');
  const isTerminal = ['completed','cancelled'].includes(trip.status) && ['completed','cancelled',''].includes(trip.dispatch_status || '');
  const reassignBtn = !isTerminal ? `<button class="btn-sm" style="background:rgba(74,158,255,0.12);color:var(--info)" onclick="openReassignModal(${Number(trip.id)},'${escapeHtml((trip.trip_ref||('Trip #'+trip.id)).replace(/'/g,"&#39;"))}')">Reassign</button>` : '';
  return `<div class="list-item" data-status="${escapeHtml(status)}"><div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info)">${escapeHtml(initials(trip.customer_name || trip.driver_name || trip.trip_ref))}</div><div class="item-info"><div class="item-name">${escapeHtml(trip.trip_ref || ('Trip #'+trip.id))} · ${escapeHtml(formatStatusLabel(category))}</div><div class="item-meta">${detail}</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><span class="badge ${tripStatusClass(status)}">${escapeHtml(formatStatusLabel(status))}</span><div style="display:flex;gap:4px"><button class="btn-sm btn-view" onclick='openTripDetailFromData(${JSON.stringify(trip).replace(/'/g,"&#39;")})'>Details</button>${reassignBtn}</div></div></div>`;
}
async function loadTrips(page=pageState.trips.page){
  const st=pageState.trips; st.page=page;
  const list=document.getElementById('tripList'); if(!list) return;
  list.innerHTML=skeletonRows();
  try{ const data=await adminApi('get_trips', st); list.innerHTML=(data.trips||[]).length?(data.trips||[]).map(renderTripListItem).join(''):'<div class="empty-state">No deliveries match your filters.</div>'; renderPagination('tripPagination', st, data.total, 'loadTrips'); }
  catch(e){ list.innerHTML='<div class="empty-state">Could not load deliveries: '+escapeHtml(e.message)+'</div>'; }
}
function openTripDetailFromData(trip){ openTripDetail(trip.trip_ref||('#'+trip.id), trip.service_category||trip.category||'Delivery', trip.pickup_address||trip.pickup||'', trip.dropoff_address||trip.dropoff||'', formatMoney(trip.fare_amount??trip.final_fare??trip.fare_estimate??trip.fare), trip.driver_name||'Unassigned', formatStatusLabel(trip.dispatch_status||trip.status), trip.id); }
let _reassignTripId = 0;
async function openReassignModal(tripId, tripRef){
  _reassignTripId = tripId;
  document.getElementById('reassignModalTitle').textContent = 'Reassign ' + tripRef;
  document.getElementById('reassignModalDesc').textContent = 'Select a driver to take over this trip. The current driver will be unassigned and all pending offers expired.';
  const sel = document.getElementById('reassignDriverSelect');
  sel.innerHTML = '<option value="">Loading drivers…</option>';
  document.getElementById('reassignSubmitBtn').disabled = true;
  document.getElementById('reassignTripModal').classList.add('open');
  try {
    const data = await adminApiAllPages('get_drivers', {});
    const drivers = (data.drivers || []).filter(d => d.status !== 'suspended');
    sel.innerHTML = '<option value="">— Select driver —</option>' + drivers.map(d => `<option value="${Number(d.id)}">${escapeHtml(d.full_name || ('Driver #'+d.id))}</option>`).join('');
    document.getElementById('reassignSubmitBtn').disabled = false;
  } catch(e) {
    sel.innerHTML = '<option value="">Could not load drivers</option>';
    toast('Could not load drivers: ' + e.message);
  }
}
function closeReassignModal(){ document.getElementById('reassignTripModal').classList.remove('open'); _reassignTripId = 0; }
async function submitReassignTrip(){
  const driverId = Number(document.getElementById('reassignDriverSelect').value);
  if(!driverId){ toast('Select a driver first.'); return; }
  if(!_reassignTripId){ toast('No trip selected.'); return; }
  const btn = document.getElementById('reassignSubmitBtn');
  btn.disabled = true; btn.textContent = 'Reassigning…';
  try {
    const data = await adminApi('admin_reassign_trip', { trip_id: _reassignTripId, driver_id: driverId }, 'POST');
    toast(data.message || 'Trip reassigned.');
    closeReassignModal();
    loadTrips();
  } catch(e) {
    toast(e.message || 'Could not reassign trip.');
  } finally {
    btn.disabled = false; btn.textContent = 'Reassign';
  }
}
async function loadPayouts(page=pageState.payouts.page){
  const st=pageState.payouts; st.page=page; const list=document.getElementById('payoutList'); if(!list) return;
  list.innerHTML=skeletonRows();
  try{ const data=await adminApi('get_payouts', st); const m=data.metrics||{}; document.getElementById('payoutPendingAmount').textContent=formatMoney(m.pending_amount); document.getElementById('payoutPendingCount').textContent=Number(m.pending_count||0).toLocaleString()+' pending'; document.getElementById('payoutProcessedAmount').textContent=formatMoney(m.processed_today_amount); document.getElementById('payoutProcessedCount').textContent=Number(m.processed_today_count||0).toLocaleString()+' processed'; document.getElementById('payoutFailedCount').textContent=Number(m.failed_count||0).toLocaleString(); document.getElementById('payoutAvgAmount').textContent=formatMoney(m.avg_payout); list.innerHTML=(data.payouts||[]).length?(data.payouts||[]).map(renderPayoutItem).join(''):'<div class="empty-state">No payouts match your filters.</div>'; renderPagination('payoutPagination', st, data.total, 'loadPayouts'); }
  catch(e){ list.innerHTML='<div class="empty-state">Could not load payouts: '+escapeHtml(e.message)+'</div>'; }
}
function renderPayoutItem(p){
  const failed=p.status==='failed';
  const walletBal=parseFloat(p.wallet_balance||0);
  const walletHtml=`<span style="color:var(--text-muted)">Wallet: ${formatMoney(walletBal)}</span>`;
  let actionHtml='';
  if(p.status==='pending'){
    actionHtml=`<button class="btn-sm btn-approve" onclick="processPayout(${p.id},'processing')">Process</button>`;
  } else if(p.status==='processing'){
    actionHtml=`<button class="btn-sm btn-approve" onclick="processPayout(${p.id},'paid')">Mark Paid</button>`;
  } else if(p.status==='paid'){
    actionHtml='<span class="badge badge-success">Paid</span>';
  } else if(p.status==='failed'){
    actionHtml=`<button class="btn-sm" style="background:var(--danger);color:#fff" onclick="processPayout(${p.id},'processing')">Retry</button>`;
  }
  return `<div class="list-item"><div class="avatar" style="background:${failed?'rgba(232,72,74,0.1)':'rgba(34,196,122,0.1)'};color:${failed?'var(--danger)':'var(--success)'}">${escapeHtml(initials(p.driver_name))}</div><div class="item-info"><div class="item-name">${escapeHtml(p.driver_name||('Driver #'+p.driver_id))}</div><div class="item-meta">${escapeHtml(p.bank_name||'Bank not set')} ${escapeHtml(maskAccount(p.account_number))} · ${Number(p.total_trips||0).toLocaleString()} trips · ${escapeHtml(formatStatusLabel(p.status))} · ${walletHtml}</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><div style="font-weight:700;font-size:14px;color:${failed?'var(--danger)':'inherit'}">${formatMoney(p.amount)}</div>${actionHtml}</div></div>`;
}
async function processPayout(id,status){ try{ const data=await adminApi('process_payout',{payout_id:id,status},'POST'); toast(data.message||'Payout updated'); loadPayouts(); }catch(e){ toast(e.message||'Could not update payout'); } }
function releaseVisiblePayouts(){ showUnavailableFeature('Bulk payout release', 'Payout release is disabled until a real transfer provider or manual transfer reference flow is connected.'); }

let _driverDataCache = {};

async function loadDrivers(page=pageState.drivers.page){
  const st=pageState.drivers; st.page=page;
  const list=document.getElementById('driverDirectory'); if(!list) return;
  list.innerHTML=skeletonRows();
  try{
    const data=await adminApi('get_drivers', st);
    const drivers=data.drivers||[];
    _driverDataCache = {};
    drivers.forEach(d => { _driverDataCache[Number(d.id)] = d; });
    document.getElementById('driversTotalCount').textContent=Number(data.total||0).toLocaleString();
    document.getElementById('driversOnlineCount').textContent=drivers.filter(d=>Number(d.is_online)===1).length.toLocaleString();
    document.getElementById('driversSuspendedCount').textContent=drivers.filter(d=>d.status==='suspended').length.toLocaleString();
    const ratings=drivers.map(d=>Number(d.rating||0)).filter(Boolean);
    document.getElementById('driversAvgRating').textContent=ratings.length?(ratings.reduce((a,b)=>a+b,0)/ratings.length).toFixed(1)+'★':'--';
    list.innerHTML=drivers.length?drivers.map(renderDriverDirectoryItem).join(''):emptyState('🚗','No drivers match your filters','Try a different search term or status.');
    renderPagination('driverPagination', st, data.total, 'loadDrivers');
  }catch(e){ list.innerHTML=emptyState('⚠️','Could not load drivers',escapeHtml(e.message)); }
}
function renderDriverDirectoryItem(driver){
  const status = driver.status || 'active';
  const id = Number(driver.id);
  const checked = _selectedDriverIds.has(id) ? 'checked' : '';
  const textMeta = [vehicleLabel(driver.vehicle_type), driver.kyc_status ? 'KYC '+formatStatusLabel(driver.kyc_status) : null, Number(driver.is_online)===1?'Online':'Offline', (driver.rating?Number(driver.rating).toFixed(1)+'★':null), Number(driver.total_trips||0).toLocaleString()+' trips'].filter(Boolean).map(escapeHtml).join(' · ');
  const meta = vehicleIcon(driver.vehicle_type) + ' ' + textMeta;
  return `<div class="list-item"><label style="display:flex;align-items:center;padding:0 8px 0 12px;cursor:pointer;flex-shrink:0"><input type="checkbox" class="driver-check" data-id="${id}" ${checked} onchange="toggleDriverSelect(${id},this.checked)" style="width:15px;height:15px;cursor:pointer"></label><div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info)">${escapeHtml(initials(driver.full_name))}</div><div class="item-info"><div class="item-name">${escapeHtml(driver.full_name||'Unnamed driver')} · ${escapeHtml(status)}</div><div class="item-meta">${meta}</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="loadDriverDetail(${id})">Profile</button><button class="btn-sm btn-edit" onclick="editDriver(${id})">Edit</button>${status==='suspended'?'<span class="badge badge-danger">Suspended</span>':`<button class="btn-sm btn-suspend" onclick="suspendDriverFromDirectory(${id}, '${escapeHtml(driver.full_name||'Driver').replace(/'/g,'&#39;')}')">Suspend</button>`}</div></div>`;
}
async function suspendDriverFromDirectory(driverId, name){
  const reason = await showConfirmDialog({
    title: 'Suspend Driver',
    desc: 'Suspend ' + name + '? They will be notified and go offline immediately.',
    reasonLabel: 'Reason for suspension (optional)',
    reasonRequired: false,
    confirmLabel: 'Suspend Driver',
  });
  if(reason === null) return;
  try{ const data=await adminApi('suspend_driver',{driver_id:driverId, reason},'POST'); toast(data.message||'Driver suspended.'); loadDrivers(); loadDashboard(); }
  catch(e){ toast(e.message||'Could not suspend driver.'); }
}

async function resetDriverPassword(driverId, name) {
  const confirmed = await showConfirmDialog({
    title: 'Reset Driver Password',
    desc: 'This will generate a secure temporary password for ' + name + ' and send it to their registered email address. They will be required to set a new password on next login.',
    confirmLabel: 'Reset Password',
    confirmClass: 'btn-danger',
  });
  if (confirmed === null) return;
  try {
    const data = await adminApi('reset_driver_password', { driver_id: driverId }, 'POST');
    toast(data.message || 'Password reset email sent.');
    closeDriverModal();
  } catch(e) {
    toast(e.message || 'Could not reset password.');
  }
}
function queueDriverSearch(v){ clearTimeout(searchTimers.drivers); searchTimers.drivers=setTimeout(()=>{pageState.drivers.search=v; loadDrivers(1);},300); }
function setDriverStatusFilter(value){
  pageState.drivers.status = (value === 'all') ? '' : value;
  loadDrivers(1);
}

let _customerDataCache = {};

async function loadCustomers(page=pageState.customers.page){
  const st=pageState.customers; st.page=page;
  const list=document.getElementById('customerDirectory'); if(!list) return;
  list.innerHTML=skeletonRows();
  try{
    const data=await adminApi('get_customers', st);
    const customers=data.customers||[];
    _customerDataCache = {};
    customers.forEach(c => { _customerDataCache[Number(c.id)] = c; });
    document.getElementById('customersTotalCount').textContent=Number(data.total||0).toLocaleString();
    document.getElementById('customersVerifiedCount').textContent=customers.filter(c=>Number(c.email_verified)===1).length.toLocaleString();
    document.getElementById('customersSuspendedCount').textContent=customers.filter(c=>c.status==='suspended').length.toLocaleString();
    list.innerHTML=customers.length?customers.map(renderCustomerDirectoryItem).join(''):emptyState('👤','No customers match your filters','Try a different search term or status.');
    renderPagination('customerPagination', st, data.total, 'loadCustomers');
  }catch(e){ list.innerHTML=emptyState('⚠️','Could not load customers',escapeHtml(e.message)); }
}
function renderCustomerDirectoryItem(customer){
  const status = customer.status || 'active';
  const id = Number(customer.id);
  const checked = _selectedCustomerIds.has(id) ? 'checked' : '';
  const verified = Number(customer.email_verified)===1 ? 'Email verified' : 'Email pending';
  const meta = [customer.email||'No email', customer.phone||'No phone', verified, dateLabel(customer.created_at)].map(escapeHtml).join(' · ');
  const safeCustomerName = escapeHtml(customer.full_name || 'Customer').replace(/'/g,'&#39;');
  return `<div class="list-item"><label style="display:flex;align-items:center;padding:0 8px 0 12px;cursor:pointer;flex-shrink:0"><input type="checkbox" class="customer-check" data-id="${id}" ${checked} onchange="toggleCustomerSelect(${id},this.checked)" style="width:15px;height:15px;cursor:pointer"></label><div class="avatar" style="background:rgba(245,200,66,0.12);color:var(--gold-dark)">${escapeHtml(initials(customer.full_name))}</div><div class="item-info"><div class="item-name">${escapeHtml(customer.full_name||'Unnamed customer')} · ${escapeHtml(status)}</div><div class="item-meta">${meta}</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="loadCustomerDetail(${id})">Profile</button><button class="btn-sm btn-edit" onclick="editCustomer(${id})">Edit</button><button class="btn-sm" onclick="openSubjectRatings('customer',${id},'${safeCustomerName}')">Ratings</button></div></div>`;
}
function queueCustomerSearch(v){ clearTimeout(searchTimers.customers); searchTimers.customers=setTimeout(()=>{pageState.customers.search=v; loadCustomers(1);},300); }

function queueReconciliationSearch(v){ clearTimeout(searchTimers.reconciliation); searchTimers.reconciliation=setTimeout(()=>{pageState.reconciliation.search=v; loadReconciliation(1);},300); }
function setReconciliationStatus(v){ pageState.reconciliation.status=v; loadReconciliation(1); }
function setReconciliationDateStart(v){ pageState.reconciliation.start_date=v; loadReconciliation(1); }
function setReconciliationDateEnd(v){ pageState.reconciliation.end_date=v; loadReconciliation(1); }

async function loadReconciliation(page=pageState.reconciliation.page) {
  const st = pageState.reconciliation;
  st.page = page;
  const list = document.getElementById('reconciliationList');
  if (!list) return;
  list.innerHTML = skeletonRows();
  try {
    const data = await adminApi('get_reconciliation_data', st);
    const rows = data.reconciliation || [];
    list.innerHTML = rows.length ? rows.map(r => `
      <div class="list-item">
        <div class="item-info">
          <div class="item-name">${escapeHtml(r.trip_ref || ('Trip #' + r.trip_id))} · ₦${Number(r.amount || 0).toLocaleString()}</div>
          <div class="item-meta">${escapeHtml(r.customer_name || 'Customer')} · ${escapeHtml(r.provider || 'Provider')} · ${dateLabel(r.reviewed_at || r.created_at)}</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
          <span class="badge ${tripStatusClass(r.status)}">${escapeHtml(formatStatusLabel(r.status))}</span>
          <div style="display:flex; gap: 4px;">
            ${r.receipt_url ? `<a href="${escapeHtml(r.receipt_url)}" target="_blank" class="btn-sm btn-view" style="text-decoration:none;">Receipt</a>` : ''}
            <button class="btn-sm btn-view" onclick="loadPaymentDetail(${r.id})">Details</button>
          </div>
        </div>
      </div>
    `).join('') : emptyState('🧾','No payments match your filters','Adjust the date range or status filter.');
    renderPagination('reconciliationPagination', st, data.total, 'loadReconciliation');
  } catch (e) {
    list.innerHTML = emptyState('⚠️','Could not load reconciliation data',escapeHtml(e.message));
  }
}

function getRevDateRange(){
  const fromEl = document.getElementById('revDateFrom');
  const toEl   = document.getElementById('revDateTo');
  const params = {};
  if(fromEl && fromEl.value) params.date_from = fromEl.value;
  if(toEl   && toEl.value)   params.date_to   = toEl.value;
  return params;
}
async function loadRevenue(){
  const catChart=document.getElementById('revCategoryChart');
  if(catChart) catChart.innerHTML='<div class="loading-state" style="padding:20px 0">'+skeletonRows(3)+'</div>';
  const dateRange = getRevDateRange();
  try {
    const [data, pnl] = await Promise.all([
      adminApi('get_revenue_analytics', dateRange),
      adminApi('get_pnl_summary', dateRange).catch(()=>null)
    ]);
    const el = id => document.getElementById(id);
    if(el('revMonthly')) el('revMonthly').textContent = formatMoney(data.monthly_revenue);
    if(el('revCommission')) el('revCommission').textContent = formatMoney(data.net_commission);
    if(el('revPayouts')) el('revPayouts').textContent = formatMoney(data.driver_payouts);
    if(el('revAvgDaily')) el('revAvgDaily').textContent = formatMoney(data.avg_daily);
    if(el('revSameDay')) el('revSameDay').textContent = Number(data.same_day_trips||0).toLocaleString();
    if(el('revGateway')) el('revGateway').textContent = Number(data.gateway_success_rate||0).toFixed(1)+'%';
    // Use the backend-computed payout_rate (driver payouts as % of driver earnings).
    const ratio = pnl ? Number(pnl.payout_rate).toFixed(1) : '0.0';
    if(el('revPayoutRatio')) el('revPayoutRatio').textContent = ratio+'%';
    if(el('revMonthlyDelta')) el('revMonthlyDelta').textContent = 'Avg ₦'+Number(data.avg_daily||0).toLocaleString(undefined,{maximumFractionDigits:0})+'/day';
    if(el('revPeriodLabel')) el('revPeriodLabel').textContent = (data.date_from && data.date_to) ? data.date_from + ' → ' + data.date_to : '';
    renderRevenueWeekChart(data.weekly_chart||[]);
    renderRevenueCategoryChart(data.category_chart||[]);
    // P&L cards
    if(pnl){
      if(el('plGrossRevenue')) el('plGrossRevenue').textContent = formatMoney(pnl.gross_revenue);
      if(el('plTotalPayouts')) el('plTotalPayouts').textContent = formatMoney(pnl.total_payouts_made);
      if(el('plTotalRefunds')) el('plTotalRefunds').textContent = formatMoney(pnl.total_refunds_issued);
      if(el('plTotalBonuses')) el('plTotalBonuses').textContent = formatMoney(pnl.total_bonuses_paid);
      const np = pnl.net_profit;
      if(el('plNetProfit')){ el('plNetProfit').textContent = formatMoney(np); el('plNetProfit').style.color = np >= 0 ? 'var(--success)' : 'var(--danger)'; }
      if(el('plCashPosition')) el('plCashPosition').textContent = formatMoney(pnl.running_balance);
      if(el('plOutstandingPayouts')) el('plOutstandingPayouts').textContent = formatMoney(pnl.outstanding_payouts);
      if(el('plPayoutRate')) el('plPayoutRate').textContent = pnl.payout_rate+'% payout rate';
      if(pnl.revenue_change_pct !== null && el('plRevenueDelta')){
        const chg = pnl.revenue_change_pct;
        el('plRevenueDelta').textContent = (chg >= 0 ? '+' : '') + chg + '% vs prior period';
        el('plRevenueDelta').className = 'metric-delta ' + (chg >= 0 ? 'up' : 'down');
      }
      if(el('plPeriodBadge') && pnl.date_from) el('plPeriodBadge').textContent = pnl.date_from + ' → ' + pnl.date_to;
    }
  } catch(e) {
    ['revMonthly','revCommission','revPayouts','revAvgDaily'].forEach(id => { const el = document.getElementById(id); if(el) el.textContent = 'Error'; });
  }
}
function renderRevenueWeekChart(trend){
  const chart = document.getElementById('revWeekChart');
  const labels = document.getElementById('revWeekLabels');
  const summary = document.getElementById('revWeekSummary');
  if(!chart) return;
  const amounts = trend.map(d=>d.revenue||0);
  const max = Math.max(...amounts, 1);
  chart.innerHTML = trend.map((d,i) => {
    const pct = Math.max(4, Math.round((d.revenue/max)*100));
    const isToday = i === trend.length-1;
    const bg = isToday ? 'var(--gold)' : 'var(--navy-light)';
    return `<div title="${escapeHtml(d.label+': '+formatMoney(d.revenue))}" style="flex:1;background:${bg};border-radius:3px 3px 0 0;height:${pct}%;cursor:default"></div>`;
  }).join('');
  if(labels) labels.innerHTML = trend.map((d,i) => {
    const isToday = i === trend.length-1;
    return `<span style="${isToday?'color:var(--gold);font-weight:700':''}">${escapeHtml(d.label.slice(0,2))}</span>`;
  }).join('');
  if(summary) {
    const peak = trend.reduce((a,b)=>b.revenue>a.revenue?b:a, trend[0]||{revenue:0,label:'—'});
    summary.innerHTML = `Peak: ${escapeHtml(peak.label)} · <span style="color:var(--success)">${formatMoney(peak.revenue)}</span>`;
  }
}
function renderRevenueCategoryChart(cats){
  const el = document.getElementById('revCategoryChart');
  if(!el) return;
  if(!cats.length){ el.innerHTML = emptyState('📊','No revenue data yet','Completed trips will show here.'); return; }
  const max = Math.max(...cats.map(c=>c.revenue), 1);
  el.innerHTML = cats.map(c => {
    const pct = Math.round((c.revenue/max)*100);
    return `<div style="margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px">
        <span style="color:var(--text-primary)">${escapeHtml(formatStatusLabel(c.label))}</span>
        <span style="color:var(--text-secondary)">${formatMoney(c.revenue)}</span>
      </div>
      <div style="background:var(--border);border-radius:3px;height:6px">
        <div style="background:var(--gold);border-radius:3px;height:6px;width:${pct}%"></div>
      </div>
    </div>`;
  }).join('');
}
function exportTaxSummary(){
  const p = getRevDateRange();
  let url = ADMIN_API_URL + '?action=export_tax_summary';
  if(p.date_from) url += '&date_from=' + encodeURIComponent(p.date_from);
  if(p.date_to)   url += '&date_to='   + encodeURIComponent(p.date_to);
  window.location.href = url;
}
function exportDriverWht(){
  const p = getRevDateRange();
  let url = ADMIN_API_URL + '?action=export_driver_wht';
  if(p.date_from) url += '&date_from=' + encodeURIComponent(p.date_from);
  if(p.date_to)   url += '&date_to='   + encodeURIComponent(p.date_to);
  window.location.href = url;
}
function exportVatSchedule(){
  const p = getRevDateRange();
  let url = ADMIN_API_URL + '?action=export_vat_schedule';
  if(p.date_from) url += '&date_from=' + encodeURIComponent(p.date_from);
  if(p.date_to)   url += '&date_to='   + encodeURIComponent(p.date_to);
  window.location.href = url;
}
function exportRevenueCsv(){
  const rows = [];
  const chart = document.getElementById('revWeekChart');
  if(!chart) return;
  const bars = chart.querySelectorAll('div[title]');
  rows.push(['Day','Revenue']);
  bars.forEach(b => {
    const t = b.getAttribute('title')||'';
    const parts = t.split(': ');
    rows.push([parts[0]||'', parts[1]||'']);
  });
  const csv = rows.map(r => r.map(v => '"'+String(v).replace(/"/g,'""')+'"').join(',')).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'weekly_revenue.csv';
  a.click();
}

async function loadDisputes(page=pageState.disputes.page){
  const st=pageState.disputes; st.page=page; const list=document.getElementById('disputeList'); if(!list) return; list.innerHTML=skeletonRows();
  try{ const data=await adminApi('get_disputes', st); const rows=data.disputes||[]; document.getElementById('disputeTotalCount').textContent=Number(data.total||0).toLocaleString(); document.getElementById('disputeOpenCount').textContent=Number(data.open_count||0).toLocaleString(); document.getElementById('disputeEscalatedCount').textContent=Number(data.escalated_count||0).toLocaleString()+' escalated'; document.getElementById('disputeRefundAmount').textContent=formatMoney(rows.reduce((sum,d)=>sum+Number(d.refund_amount||0),0)); list.innerHTML=rows.length?rows.map(renderDisputeItem).join(''):'<div class="empty-state">No disputes match your filters.</div>'; renderPagination('disputePagination', st, data.total, 'loadDisputes'); }
  catch(e){ list.innerHTML='<div class="empty-state">Could not load disputes: '+escapeHtml(e.message)+'</div>'; }
}
function renderDisputeItem(d){ const status=d.status||'open'; const title='#D-'+String(d.id).padStart(4,'0')+' · '+(d.category||'Dispute'); const meta=status==='resolved'?`Resolved · ${formatMoney(d.refund_amount)} refunded · ${d.resolution||''}`:`Customer: ${d.customer_name||'Unknown'} · Driver: ${d.driver_name||'Unassigned'} · ${dateLabel(d.created_at)}`; const desc=encodeURIComponent(d.description||''); return `<div class="list-item" data-dispute="${escapeHtml(status)}"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger);font-size:11px">${status==='resolved'?'✓':(status==='escalated'?'🔴':'!')}</div><div class="item-info"><div class="item-name">${escapeHtml(title)}</div><div class="item-meta">${escapeHtml(meta)}</div></div><div class="item-actions"><button class="btn-sm ${status==='escalated'?'btn-reject':'btn-view'}" onclick="openDisputeModal('${escapeHtml(title).replace(/'/g,'&#39;')}', ${Number(d.id)}, '${escapeHtml(status)}', decodeURIComponent('${desc}'))">${status==='resolved'?'View':'Handle'}</button></div></div>`; }
function queuePayoutSearch(v){ clearTimeout(searchTimers.payouts); searchTimers.payouts=setTimeout(()=>{pageState.payouts.search=v; loadPayouts(1);},300); }
function setPayoutStatus(v){ pageState.payouts.status=v; loadPayouts(1); }
function queueDisputeSearch(v){ clearTimeout(searchTimers.disputes); searchTimers.disputes=setTimeout(()=>{pageState.disputes.search=v; loadDisputes(1);},300); }
function setDisputeStatus(v){ pageState.disputes.status=v; syncDisputeFilterBtns(v); loadDisputes(1); }
function syncDisputeFilterBtns(v){ document.querySelectorAll('#panel-disputes .filter-btn').forEach(b=>b.classList.toggle('active',b.dataset.status===v)); }
let opsMap = null;
let opsDriverMarkers = [];

function initOpsMap() {
  const el = document.getElementById('opsLeafletMap');
  if (!el || typeof L === 'undefined') return;
  if (opsMap) { setTimeout(() => opsMap.invalidateSize(), 120); return; }
  opsMap = L.map('opsLeafletMap', { zoomControl: true, scrollWheelZoom: false }).setView([4.8156, 7.0498], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
  }).addTo(opsMap);
}

function placeOpsMarkers(drivers) {
  if (!opsMap) return;
  opsDriverMarkers.forEach(m => m.remove());
  opsDriverMarkers = [];
  const pts = [];
  (drivers || []).forEach(d => {
    const lat = parseFloat(d.lat), lng = parseFloat(d.lng);
    if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;
    const inTrip = !!d.trip_id;
    const color = inTrip ? '#4A9EFF' : '#22C47A';
    const svgPaths = {
      bike: '<path d="M5 16L9 8h5l3 4h3"/><circle cx="5" cy="16" r="2"/><circle cx="19" cy="16" r="2"/>',
      car:  '<rect x="2" y="9" width="20" height="8" rx="2"/><path d="M5 9l2-4h10l2 4"/><circle cx="7" cy="17" r="1.5"/><circle cx="17" cy="17" r="1.5"/>',
      van:  '<rect x="1" y="6" width="15" height="12" rx="1"/><path d="M16 9l5 3v6h-5"/><circle cx="5" cy="18" r="1.5"/><circle cx="12" cy="18" r="1.5"/>',
      keke: '<circle cx="5" cy="17" r="2"/><circle cx="12" cy="17" r="2"/><path d="M5 17V9h7v8"/><path d="M12 12h5v5h-2"/>'
    }[d.vehicle_type] || '<path d="M5 16L9 8h5l3 4h3"/><circle cx="5" cy="16" r="2"/><circle cx="19" cy="16" r="2"/>';
    const icon = L.divIcon({
      className: '',
      html: `<div style="width:30px;height:30px;background:#0B1628;border:2.5px solid ${color};border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.35)"><svg viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="2" width="14" height="14">${svgPaths}</svg></div>`,
      iconSize: [30, 30], iconAnchor: [15, 15], popupAnchor: [0, -18]
    });
    const m = L.marker([lat, lng], { icon })
      .bindPopup(`<b>${escapeHtml(d.full_name || d.first_name || 'Driver')}</b><br>${inTrip ? 'In Trip' : 'Online'}<br><small>GPS ${lat.toFixed(5)}, ${lng.toFixed(5)}</small>`)
      .addTo(opsMap);
    opsDriverMarkers.push(m);
    pts.push([lat, lng]);
  });
  if (pts.length > 0) opsMap.fitBounds(L.latLngBounds(pts), { padding: [40, 40], maxZoom: 15 });
  setTimeout(() => opsMap.invalidateSize(), 200);
}

async function loadLiveOps(){
  initOpsMap();
  const tripList=document.getElementById('opsTripList');
  const driverList=document.getElementById('opsDriverList');
  if(tripList) tripList.innerHTML=skeletonRows(3);
  if(driverList) driverList.innerHTML=skeletonRows(3);
  try {
    const data = await adminApi('get_live_ops');
    renderLiveOps(data);
  } catch (err) {
    const list = document.getElementById('opsDriverList');
    if(list) list.innerHTML = `<div class="list-item"><div class="item-info"><div class="item-name">Could not load live operations</div><div class="item-meta">${escapeHtml(err.message)}</div></div></div>`;
  }
  // Load the alert feed and heatmap in parallel alongside the main ops data
  loadLiveAlerts();
  loadDemandHeatmap();
}
function renderLiveOps(data){
  const trips   = data.trips   || [];
  const drivers = data.drivers || [];
  const onlineDriverCount  = data.metrics?.online_drivers  ?? drivers.filter(d => Number(d.is_online) === 1).length;
  const activeTripCount    = data.metrics?.active_trips    ?? trips.filter(t => t.dispatch_status !== 'searching' && t.dispatch_status !== 'offered').length;
  const searchingTripCount = data.metrics?.searching_trips ?? trips.filter(t => t.dispatch_status === 'searching' || t.dispatch_status === 'offered').length;

  document.getElementById('opsOnlineDrivers').textContent  = onlineDriverCount;
  document.getElementById('opsActiveTrips').textContent    = activeTripCount;
  const searchingEl = document.getElementById('opsSearchingTrips');
  if(searchingEl) searchingEl.textContent = searchingTripCount;
  const newestTrip = trips.find(t => t.driver_lat != null)?.location_updated_at || null;
  const locationEl = document.getElementById('opsLastLocation');
  if(locationEl) locationEl.textContent = newestTrip ? 'GPS live' : 'No GPS';
  document.getElementById('opsRefreshAge').textContent = 'Now';
  document.getElementById('opsMapLegend').innerHTML = `<span style="color:var(--success)">●</span> ${onlineDriverCount} online &nbsp;<span style="color:var(--info)">●</span> ${activeTripCount} in trip &nbsp;<span style="color:var(--warn)">●</span> ${searchingTripCount} searching`;
  document.getElementById('opsListMeta').textContent = `Live · ${trips.length} trip${trips.length !== 1 ? 's' : ''}`;

  // Render trip list
  const tripList = document.getElementById('opsTripList');
  if(tripList){
    if(!trips.length){
      tripList.innerHTML = emptyState('🗺️','No active trips right now','Trips in searching or in-progress state appear here.');
    } else {
      tripList.innerHTML = trips.map(trip => {
        const ds = trip.dispatch_status || '';
        const isSearching = ds === 'searching' || ds === 'offered';
        let badgeClass = 'badge-info';
        if(isSearching) badgeClass = 'badge-warn';
        else if(ds === 'picked_up' || ds === 'arrived_dropoff') badgeClass = 'badge-success';
        const badge = `<span class="badge ${badgeClass}">${escapeHtml(formatStatusLabel(ds))}</span>`;
        const driverPart = trip.driver_name ? `${escapeHtml(trip.driver_name)} · ${vehicleIcon(trip.vehicle_type)}` : '<em>Searching…</em>';
        const gps = trip.driver_lat != null && trip.driver_lng != null
          ? `GPS ${Number(trip.driver_lat).toFixed(4)}, ${Number(trip.driver_lng).toFixed(4)}`
          : 'No GPS';
        const fare = trip.final_fare ?? trip.fare;
        const fareStr = fare ? `₦${Number(fare).toLocaleString()}` : '';
        const meta = `${escapeHtml(trip.customer_name || 'Customer')} · ${driverPart} · ${escapeHtml(trip.dropoff_address || 'Drop-off TBD')} · ${gps}${fareStr ? ' · ' + fareStr : ''}`;
        return `<div class="list-item"><div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info)">${escapeHtml(initials(trip.customer_name || 'T'))}</div><div class="item-info"><div class="item-name">${escapeHtml(trip.trip_ref || ('#' + trip.trip_id))}</div><div class="item-meta">${meta}</div></div>${badge}</div>`;
      }).join('');
    }
  }

  // Render driver marker list
  const list = document.getElementById('opsDriverList');
  if(list){
    if(!drivers.length){
      list.innerHTML = emptyState('🚗','No active drivers right now','Drivers appear here after heartbeat or active-trip assignment.');
    } else {
      list.innerHTML = drivers.map(driver => {
        const inTrip = !!driver.trip_id;
        const badge = inTrip ? '<span class="badge badge-info">In Trip</span>' : '<span class="badge badge-success">Available</span>';
        const location = driver.lat != null && driver.lng != null ? `${Number(driver.lat).toFixed(5)}, ${Number(driver.lng).toFixed(5)} · ${driver.updated_at || 'recent'}` : 'No last known location';
        const meta = inTrip ? `${escapeHtml(driver.trip_ref)} · ${escapeHtml(driver.dispatch_status)} → ${escapeHtml(driver.dropoff_address || 'drop-off')} · GPS ${escapeHtml(location)}` : `Online · GPS ${escapeHtml(location)}`;
        return `<div class="list-item"><div class="avatar" style="background:rgba(34,196,122,0.1);color:var(--success)">${escapeHtml(initials(driver.full_name || driver.first_name))}</div><div class="item-info"><div class="item-name">${escapeHtml(driver.full_name || driver.first_name || 'Driver')} · ${vehicleIcon(driver.vehicle_type)}</div><div class="item-meta">${meta}</div></div>${badge}</div>`;
      }).join('');
    }
  }
  placeOpsMarkers(drivers);
}
setInterval(() => {
  if(document.getElementById('panel-ops')?.classList.contains('active')) loadLiveOps();
}, 15000);
// Refresh alerts every 60 seconds independently of the main ops data refresh
setInterval(() => {
  if(document.getElementById('panel-ops')?.classList.contains('active')) loadLiveAlerts();
}, 60000);
setInterval(() => {
  if(document.getElementById('panel-overview')?.classList.contains('active')) loadDashboard();
}, 30000);
setInterval(() => {
  if(document.getElementById('panel-trips')?.classList.contains('active')) loadTrips();
}, 30000);
setInterval(() => {
  if(document.getElementById('panel-drivers')?.classList.contains('active')) loadDrivers();
}, 60000);
setInterval(() => {
  if(document.getElementById('panel-customers')?.classList.contains('active')) loadCustomers();
}, 60000);
setInterval(() => {
  if(document.getElementById('panel-disputes')?.classList.contains('active')) loadDisputes();
}, 60000);

function filterOps(type,btn){
  document.querySelectorAll('.filter-row .filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');toast('Filtering: '+type);
}
function filterTripStatus(status,btn){ document.querySelectorAll('#panel-trips .filter-row .filter-btn').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); pageState.trips.status=status==='all'?'':status; loadTrips(1); }
function filterDisputes(type,btn){ pageState.disputes.status=type; syncDisputeFilterBtns(type); const sel=document.getElementById('disputeStatus'); if(sel) sel.value=type; loadDisputes(1); }
async function loadDriverDetail(driverId) {
  try {
    const data = await adminApi('get_driver', { driver_id: driverId });
    const driver = data.driver;

    document.getElementById('driverModalTitle').textContent = driver.full_name || 'Driver Details';

    let html = `<div style="display:flex;gap:16px;margin-bottom:16px;">`;
    html += `<div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info);width:64px;height:64px;font-size:24px;">${escapeHtml(initials(driver.full_name))}</div>`;
    html += `<div>`;
    html += `<div style="font-weight:600;font-size:16px;">${escapeHtml(driver.full_name || 'Unnamed driver')}</div>`;
    html += `<div style="color:var(--text-secondary);font-size:13px;margin-top:4px;">${escapeHtml(driver.email || 'No email')} · ${escapeHtml(driver.phone || 'No phone')}</div>`;
    html += `<div style="margin-top:8px;"><span class="badge ${driver.status === 'suspended' ? 'badge-danger' : 'badge-success'}">${escapeHtml(formatStatusLabel(driver.status))}</span></div>`;
    html += `</div></div>`;

    html += `<div class="metrics-grid" style="margin-bottom:16px">`;
    html += `<div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">VEHICLE</div><div style="font-size:13px;font-weight:600">${vehicleIcon(driver.vehicle_type)} ${escapeHtml(vehicleLabel(driver.vehicle_type))}</div></div>`;
    html += `<div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">PLATE</div><div style="font-size:13px;font-weight:600">${escapeHtml(driver.vehicle_plate || 'Not set')}</div></div>`;
    html += `<div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">TRIPS</div><div style="font-size:13px;font-weight:600">${Number(driver.total_trips || 0).toLocaleString()}</div></div>`;
    html += `<div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">RATING</div><div style="font-size:13px;font-weight:600">${driver.rating ? Number(driver.rating).toFixed(1)+'★' : '--'}</div></div>`;
    html += `</div>`;

    if(driver.wallet_balance != null){
      html += `<div style="background:var(--surface);border-radius:10px;padding:12px;margin-bottom:16px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">WALLET BALANCE</div><div style="font-size:15px;font-weight:700">${formatMoney(driver.wallet_balance)}</div></div>`;
    }

    const safeName = escapeHtml(driver.full_name || 'Driver').replace(/'/g,'&#39;');
    html += `<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">`;
    html += `<button class="btn-sm btn-view" onclick="openSubjectRatings('driver',${Number(driver.id)},'${safeName}')">View Ratings</button>`;
    if(driver.status !== 'suspended'){
      html += `<button class="btn-sm btn-suspend" onclick="closeDriverModal();suspendDriverFromDirectory(${Number(driver.id)},'${safeName}')">Suspend Driver</button>`;
      html += `<button class="btn-sm" style="background:rgba(34,197,94,.12);color:#16a34a;border:none" onclick="openDriverAdjustmentModal(${Number(driver.id)},'${safeName}')">Penalty / Bonus</button>`;
    }
    html += `<button class="btn-sm" style="background:rgba(239,68,68,.1);color:#dc2626;border:none" onclick="resetDriverPassword(${Number(driver.id)},'${safeName}')">Reset Password</button>`;
    html += `</div>`;

    document.getElementById('driverModalBody').innerHTML = html;
    document.getElementById('driverModal').classList.add('open');
  } catch(e) {
    toast('Could not load driver: ' + e.message);
  }
}
function closeDriverModal() {
  document.getElementById('driverModal').classList.remove('open');
}

// ── DRIVER PENALTY / BONUS MODAL ─────────────────────────────────────────────

async function openDriverAdjustmentModal(driverId, driverName) {
  const type = await showConfirmDialog({
    title: 'Penalty / Bonus',
    desc: `Apply a penalty or bonus to ${driverName}'s wallet.`,
    confirmLabel: 'Continue',
    reasonLabel: 'Type (enter "penalty" or "bonus")',
    reasonRequired: true,
  });
  if (type === null) return;
  const adjType = type.trim().toLowerCase();
  if (!['penalty','bonus'].includes(adjType)) { toast('Please type exactly "penalty" or "bonus".'); return; }

  const amountStr = prompt(`Enter the amount (₦) for the ${adjType}:`);
  if (!amountStr) return;
  const amount = parseFloat(amountStr);
  if (!amount || amount <= 0) { toast('Invalid amount.'); return; }

  const reason = prompt('Reason (required):');
  if (!reason || !reason.trim()) { toast('A reason is required.'); return; }

  try {
    const data = await adminApi('issue_driver_adjustment', { driver_id: driverId, amount, adjustment_type: adjType, reason }, 'POST');
    toast(data.message || 'Adjustment applied.');
    loadDriverDetail(driverId);
  } catch(e) {
    toast(e.message || 'Could not apply adjustment.');
  }
}

// ── CUSTOMER DETAIL MODAL ────────────────────────────────────────────────────

async function loadCustomerDetail(customerId) {
  try {
    const data = await adminApi('get_customer', { customer_id: customerId });
    const c    = data.customer;
    const ledger = data.ledger || [];

    document.getElementById('customerModalTitle').textContent = c.full_name || 'Customer Details';

    const typeLabel = {topup:'Top-Up', refund:'Refund', credit:'Credit', debit:'Debit', referral_bonus:'Referral Bonus'};

    let html = `<div style="display:flex;gap:16px;margin-bottom:16px">`;
    html += `<div class="avatar" style="background:rgba(245,200,66,.12);color:var(--gold-dark);width:64px;height:64px;font-size:24px">${escapeHtml(initials(c.full_name))}</div>`;
    html += `<div><div style="font-weight:600;font-size:16px">${escapeHtml(c.full_name||'Unnamed')}</div>`;
    html += `<div style="color:var(--text-secondary);font-size:13px;margin-top:4px">${escapeHtml(c.email||'No email')} · ${escapeHtml(c.phone||'No phone')}</div>`;
    html += `<div style="margin-top:8px"><span class="badge ${c.status==='suspended'?'badge-danger':'badge-success'}">${escapeHtml(formatStatusLabel(c.status||'active'))}</span></div>`;
    html += `</div></div>`;

    html += `<div class="metrics-grid" style="margin-bottom:16px">`;
    html += `<div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">TRIPS</div><div style="font-size:13px;font-weight:600">${Number(c.total_trips||0).toLocaleString()}</div></div>`;
    html += `<div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">RATING</div><div style="font-size:13px;font-weight:600">${c.rating?Number(c.rating).toFixed(1)+'★':'--'}</div></div>`;
    html += `<div style="background:var(--surface);border-radius:10px;padding:12px;grid-column:span 2"><div style="font-size:10px;color:var(--text-muted)">WALLET BALANCE</div><div style="font-size:15px;font-weight:700">${formatMoney(c.wallet_balance)}</div></div>`;
    html += `</div>`;

    html += `<div style="margin-bottom:16px"><button class="btn-sm" style="background:rgba(34,197,94,.12);color:#16a34a;border:none" onclick="openCreditCustomerModal(${Number(c.id)})">Credit Wallet</button></div>`;

    if (ledger.length) {
      html += `<div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:8px">RECENT WALLET ACTIVITY</div>`;
      html += ledger.map(row => {
        const isIn  = ['topup','refund','credit','referral_bonus'].includes(row.entry_type);
        const color = isIn ? 'var(--success,#16a34a)' : 'var(--danger,#e53)';
        const sign  = isIn ? '+' : '-';
        return `<div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);font-size:12px">
          <div><span style="font-weight:600">${escapeHtml(typeLabel[row.entry_type]||row.entry_type)}</span><br><span style="color:var(--text-muted)">${escapeHtml(row.description||'')} · ${escapeHtml(row.created_at?.slice(0,10)||'')}</span></div>
          <div style="font-weight:700;color:${color}">${sign}${formatMoney(row.amount)}</div>
        </div>`;
      }).join('');
    }

    document.getElementById('customerModalBody').innerHTML = html;
    document.getElementById('customerModal').classList.add('open');
  } catch(e) {
    toast('Could not load customer: ' + e.message);
  }
}

function closeCustomerModal() {
  document.getElementById('customerModal').classList.remove('open');
}

async function openCreditCustomerModal(customerId) {
  const amountStr = prompt('Amount to credit to customer wallet (₦):');
  if (!amountStr) return;
  const amount = parseFloat(amountStr);
  if (!amount || amount <= 0) { toast('Invalid amount.'); return; }

  const reason = prompt('Reason for credit (required):');
  if (!reason || !reason.trim()) { toast('A reason is required.'); return; }

  try {
    const data = await adminApi('admin_credit_customer_wallet', { customer_id: customerId, amount, reason }, 'POST');
    toast(data.message || 'Wallet credited.');
    loadCustomerDetail(customerId);
  } catch(e) {
    toast(e.message || 'Could not credit wallet.');
  }
}

async function loadPaymentDetail(paymentId) {
  try {
    const data = await adminApi('get_payment', { payment_id: paymentId });
    const p = data.payment;

    document.getElementById('paymentModalTitle').textContent = `Payment for ${escapeHtml(p.trip_ref || ('Trip #' + p.trip_id))}`;

    let html = `<div class="metrics-grid" style="margin-bottom:16px">`;
    html += `<div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">AMOUNT</div><div style="font-size:13px;font-weight:600">₦${Number(p.amount || 0).toLocaleString()}</div></div>`;
    html += `<div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">STATUS</div><div style="font-size:13px;font-weight:600"><span class="badge ${tripStatusClass(p.status)}">${escapeHtml(formatStatusLabel(p.status))}</span></div></div>`;
    html += `<div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">PROVIDER</div><div style="font-size:13px;font-weight:600">${escapeHtml(p.provider || 'Unknown')}</div></div>`;
    html += `<div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">REFERENCE</div><div style="font-size:13px;font-weight:600" style="word-break: break-all;">${escapeHtml(p.provider_ref || 'No ref')}</div></div>`;
    html += `</div>`;

    html += `<div style="background:var(--surface);border-radius:10px;padding:12px;margin-bottom:16px;">`;
    html += `<div style="font-size:10px;color:var(--text-muted);margin-bottom:6px">CUSTOMER</div>`;
    html += `<div style="font-size:13px;font-weight:600">${escapeHtml(p.customer_name || 'Customer')}</div>`;
    html += `</div>`;

    if (p.receipt_url) {
      html += `<div style="margin-top: 16px;"><a href="${escapeHtml(p.receipt_url)}" target="_blank" class="btn-primary" style="display:inline-block;text-align:center;text-decoration:none;font-size:13px;">View Receipt</a></div>`;
    }

    if (!['refunded'].includes(p.status)) {
      html += `<div style="margin-top:12px"><button class="btn-sm" style="background:rgba(239,68,68,.12);color:#dc2626;border:none" onclick="openRefundModal(${Number(p.id)},${Number(p.amount)})">Issue Refund</button></div>`;
    }

    document.getElementById('paymentModalBody').innerHTML = html;
    document.getElementById('paymentModal').classList.add('open');
  } catch(e) {
    toast('Could not load payment: ' + e.message);
  }
}

function closePaymentModal() {
  document.getElementById('paymentModal').classList.remove('open');
}

async function openRefundModal(paymentId, originalAmount) {
  const amountStr = prompt(`Refund amount (₦) — original payment was ₦${Number(originalAmount).toLocaleString()}:`);
  if (!amountStr) return;
  const amount = parseFloat(amountStr);
  if (!amount || amount <= 0) { toast('Invalid amount.'); return; }

  const refundType = prompt('Refund type: type "wallet" to credit the customer wallet, or "bank" to reverse via the payment provider:');
  if (!refundType) return;
  const rt = refundType.trim().toLowerCase();
  const mappedType = rt === 'wallet' ? 'wallet_credit' : rt === 'bank' ? 'bank_reversal' : null;
  if (!mappedType) { toast('Please type "wallet" or "bank".'); return; }

  const reason = prompt('Reason for refund (required):');
  if (!reason || !reason.trim()) { toast('A reason is required.'); return; }

  try {
    const data = await adminApi('issue_refund', { payment_id: paymentId, refund_amount: amount, refund_type: mappedType, reason }, 'POST');
    toast(data.message || 'Refund processed.');
    closePaymentModal();
  } catch(e) {
    toast(e.message || 'Could not process refund.');
  }
}


let _currentAdminTripId = 0;
function openTripDetail(id,cat,from,to,fare,driver,status,tripId){
  _currentAdminTripId = tripId || 0;
  document.getElementById('tripModalTitle').textContent='Trip '+id;
  document.getElementById('tripModalBody').innerHTML=`<div class="metrics-grid" style="margin-bottom:16px"><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">CATEGORY</div><div style="font-size:13px;font-weight:600">${cat}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">STATUS</div><div style="font-size:13px;font-weight:600">${status}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">PICKUP</div><div style="font-size:13px;font-weight:600">${from}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">DROP-OFF</div><div style="font-size:13px;font-weight:600">${to}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">FARE</div><div style="font-size:13px;font-weight:600">${fare}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">DRIVER</div><div style="font-size:13px;font-weight:600">${driver}</div></div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:6px">PAYMENT BREAKDOWN</div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Base fare</span><span>${fare}</span></div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;color:var(--success)"><span>Platform comm.</span><span>-20%</span></div><div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;margin-top:6px;padding-top:6px;border-top:1px solid var(--surface-2)"><span>Driver payout</span><span style="color:var(--success)">80%</span></div></div>`;
  const podBtn = document.getElementById('tripPodBtn');
  if (podBtn) podBtn.style.display = (status === 'completed' || status === 'Completed' || status === 'Delivered') && tripId ? '' : 'none';
  document.getElementById('tripModal').classList.add('open');
}
function closeTripModal(){document.getElementById('tripModal').classList.remove('open');}
async function adminViewTripPod(){
  if (!_currentAdminTripId) return;
  try {
    const data = await adminApi('get_trip_pod', { trip_id: _currentAdminTripId });
    if (data.proof_url) {
      viewProofImage(data.proof_url, 'Proof of Delivery');
    } else {
      toast('No proof of delivery image found for this trip.');
    }
  } catch(e) {
    toast(e.message || 'Could not load proof of delivery.');
  }
}
function openDisputeModal(title, disputeId=0, status='open', description=''){
  currentDisputeId=Number(disputeId||0);
  document.getElementById('modalDesc').textContent=title;
  const evidenceEl=document.getElementById('disputeEvidence');
  if(evidenceEl) evidenceEl.textContent=description||'';

  const actionArea = document.getElementById('disputeActionArea');
  const resolveBtn = document.getElementById('resolveDisputeBtn');

  if (status === 'resolved') {
    if (actionArea) actionArea.style.display = 'none';
    if (resolveBtn) resolveBtn.style.display = 'none';
  } else {
    if (actionArea) actionArea.style.display = 'block';
    if (resolveBtn) resolveBtn.style.display = 'inline-block';
    document.getElementById('resolutionType').value='Issue full refund to customer';
    document.getElementById('refundAmt').value='';
    document.getElementById('resolutionNotes').value='';
  }

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
function toggleNotif(){
  const panel = document.getElementById('notifPanel');
  panel.classList.toggle('open');
  if (panel.classList.contains('open')) loadAdminNotifications();
}
async function loadAdminNotifications() {
  const panel = document.getElementById('notifPanel');
  const body  = document.getElementById('notifBody');
  if (!body) return;
  body.innerHTML = '<div style="padding:16px;font-size:12px;color:var(--text-muted)">Loading…</div>';
  try {
    const data = await adminApi('get_admin_notifications');
    const notifs = data.notifications || [];
    const badge = document.getElementById('notifBadge');
    if (badge) { badge.textContent = data.unread_count > 0 ? String(data.unread_count) : ''; badge.style.display = data.unread_count > 0 ? 'inline-block' : 'none'; }
    if (!notifs.length) {
      body.innerHTML = '<div style="padding:20px 16px;text-align:center;font-size:12px;color:var(--text-muted)">No notifications yet.</div>';
      return;
    }
    body.innerHTML = notifs.map(n => {
      const isUnread = !n.is_read;
      return `<div class="notif-item${isUnread ? ' notif-unread' : ''}" style="${isUnread ? 'background:rgba(74,158,255,0.06);' : ''}">
        <div class="notif-icon" style="background:rgba(74,158,255,0.1);flex-shrink:0"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--info)"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="8"/><line x1="12" y1="12" x2="12" y2="16"/></svg></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:12px;font-weight:600;color:var(--text)">${escapeHtml(n.title)}</div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:2px;line-height:1.4">${escapeHtml(n.body)}</div>
          <div style="font-size:10px;color:var(--text-muted);margin-top:4px">${dateLabel(n.created_at)}</div>
        </div>
      </div>`;
    }).join('');
  } catch(e) {
    body.innerHTML = '<div style="padding:16px;font-size:12px;color:var(--danger)">Could not load notifications.</div>';
  }
}
async function markAllRead(){
  try {
    await adminApi('mark_admin_notifications_read', {}, 'POST');
    document.querySelectorAll('.notif-unread').forEach(el => { el.classList.remove('notif-unread'); el.style.background = ''; });
    const badge = document.getElementById('notifBadge');
    if (badge) { badge.textContent = ''; badge.style.display = 'none'; }
  } catch(e) { toast('Could not mark notifications as read.'); }
}
function handleSearch(val){
  if(val.length>2) showUnavailableFeature('Global search', 'Cross-entity search needs a backend search endpoint before results can be opened.');
}
function showUnavailableFeature(title, detail){ toast(title + ': ' + detail); }
async function adminLogout(btn){
  const original = btn ? btn.innerHTML : '';
  if(btn) btn.innerHTML = 'Logging out…';
  try{
    const body = new FormData();
    body.append('action','admin_logout');
    const response = await fetch('/admin.php', { method:'POST', body, credentials:'same-origin', headers:{ 'Accept':'application/json' } });
    const data = await response.json();
    window.location.href = data.data?.redirect || '/admin.php';
  }catch(e){
    if(btn) btn.innerHTML = original;
    toast('Could not log out. Please refresh and try again.');
  }
}
function searchTrips(val){ clearTimeout(searchTimers.trips); searchTimers.trips=setTimeout(()=>{pageState.trips.search=val; loadTrips(1);},300); }
function filterTrips(cat){ pageState.trips.category=cat; loadTrips(1); }
let _confirmResolve = () => {};
let _confirmReject = () => {};
function showConfirmDialog({ title, desc, reasonLabel = 'Reason', reasonRequired = false, useSelect = false, confirmLabel = 'Confirm', confirmClass = 'danger' }) {
  return new Promise((resolve, reject) => {
    const modal = document.getElementById('confirmActionModal');
    document.getElementById('confirmActionTitle').textContent = title;
    document.getElementById('confirmActionDesc').textContent = desc;
    document.getElementById('confirmReasonLabel').textContent = reasonLabel;
    const selectWrap = document.getElementById('confirmReasonSelectWrap');
    const textarea = document.getElementById('confirmReasonText');
    const select = document.getElementById('confirmReasonSelect');
    const submitBtn = document.getElementById('confirmActionSubmit');
    selectWrap.style.display = useSelect ? 'block' : 'none';
    textarea.value = '';
    select.value = '';
    textarea.required = reasonRequired && !useSelect;
    submitBtn.disabled = reasonRequired && !useSelect;
    submitBtn.textContent = confirmLabel;
    submitBtn.style.background = confirmClass === 'danger' ? 'var(--danger)' : 'var(--primary)';
    if (useSelect) {
      select.onchange = () => { textarea.placeholder = select.value ? 'Additional notes (optional)…' : 'Enter reason…'; };
    }
    modal.classList.add('open');
    _confirmResolve = () => {
      modal.classList.remove('open');
      const reason = useSelect
        ? (select.value ? select.value + (textarea.value.trim() ? ': ' + textarea.value.trim() : '') : textarea.value.trim())
        : textarea.value.trim();
      if (reasonRequired && !reason) { resolve(null); return; }
      resolve(reason);
    };
    _confirmReject = () => { modal.classList.remove('open'); resolve(null); };
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

// ── Password visibility toggle (shared across slide panels) ────────────────
function togglePwVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  const isText = input.type === 'text';
  input.type = isText ? 'password' : 'text';
  btn.querySelector('.eye-open').style.display  = isText ? '' : 'none';
  btn.querySelector('.eye-closed').style.display = isText ? 'none' : '';
  btn.setAttribute('aria-label', isText ? 'Show password' : 'Hide password');
}

// ── Driver Create / Edit slide panel ───────────────────────────────────────
function _openDriverSlidePanelRaw() {
  const panel = document.getElementById('driverSlidePanel');
  const overlay = document.getElementById('driverSlidePanelOverlay');
  if (!panel || !overlay) return;
  panel.style.display = 'flex';
  overlay.style.display = 'block';
  requestAnimationFrame(() => { panel.classList.add('open'); overlay.classList.add('open'); });
}

function openDriverPanel() {
  document.getElementById('driverEditId').value = '';
  document.getElementById('driverFormFullName').value = '';
  document.getElementById('driverFormEmail').value = '';
  document.getElementById('driverFormPhone').value = '';
  document.getElementById('driverFormPassword').value = '';
  document.getElementById('driverFormVehicleType').value = 'bike';
  document.getElementById('driverFormMiddleName').value = '';
  document.getElementById('driverFormDob').value = '';
  document.getElementById('driverFormGender').value = '';
  document.getElementById('driverFormState').value = '';
  document.getElementById('driverFormLanguage').value = 'English';
  document.getElementById('driverFormEmailGroup').style.display = '';
  document.getElementById('driverFormPasswordGroup').style.display = '';
  document.getElementById('driverFormVehicleSection').style.display = 'none';
  document.getElementById('driverFormBankSection').style.display = 'none';
  document.getElementById('driverFormEmergencySection').style.display = 'none';
  document.getElementById('driverSlideTitle').textContent = 'Add Driver Account';
  document.getElementById('driverSlideSubtitle').textContent = 'Fill in the details below';
  document.getElementById('driverSaveBtn').textContent = 'Add Driver';
  _openDriverSlidePanelRaw();
}

function editDriver(driverId) {
  const d = _driverDataCache[Number(driverId)];
  if (!d) { toast('Refresh the driver list then try again.'); return; }
  document.getElementById('driverEditId').value = d.id;
  document.getElementById('driverFormFullName').value = d.full_name || '';
  document.getElementById('driverFormEmail').value = d.email || '';
  document.getElementById('driverFormPhone').value = d.phone || '';
  document.getElementById('driverFormVehicleType').value = d.vehicle_type || 'bike';
  document.getElementById('driverFormMiddleName').value = d.middle_name || '';
  document.getElementById('driverFormDob').value = d.date_of_birth || '';
  document.getElementById('driverFormGender').value = d.gender || '';
  document.getElementById('driverFormState').value = d.state_of_origin || '';
  document.getElementById('driverFormLanguage').value = d.language || 'English';
  document.getElementById('driverFormVehicleModel').value = d.vehicle_model || '';
  document.getElementById('driverFormVehicleYear').value = d.vehicle_year || '';
  document.getElementById('driverFormVehiclePlate').value = d.vehicle_plate || '';
  document.getElementById('driverFormVehicleColor').value = d.vehicle_color || '';
  document.getElementById('driverFormBankName').value = d.bank_name || '';
  document.getElementById('driverFormAccountHolder').value = d.account_holder_name || '';
  document.getElementById('driverFormAccountNumber').value = d.account_number || '';
  document.getElementById('driverFormEmergencyName').value = d.emergency_name || '';
  document.getElementById('driverFormEmergencyRelationship').value = d.emergency_relationship || '';
  document.getElementById('driverFormEmergencyPhone').value = d.emergency_phone || '';
  document.getElementById('driverFormEmergencyAddress').value = d.emergency_address || '';
  document.getElementById('driverFormEmailGroup').style.display = 'none';
  document.getElementById('driverFormPasswordGroup').style.display = 'none';
  document.getElementById('driverFormVehicleSection').style.display = '';
  document.getElementById('driverFormBankSection').style.display = '';
  document.getElementById('driverFormEmergencySection').style.display = '';
  document.getElementById('driverSlideTitle').textContent = 'Edit ' + escapeHtml(d.full_name || 'Driver');
  document.getElementById('driverSlideSubtitle').textContent = d.email || '';
  document.getElementById('driverSaveBtn').textContent = 'Save Changes';
  _openDriverSlidePanelRaw();
}

function closeDriverPanel() {
  const panel = document.getElementById('driverSlidePanel');
  const overlay = document.getElementById('driverSlidePanelOverlay');
  if (!panel || !overlay) return;
  panel.classList.remove('open'); overlay.classList.remove('open');
  setTimeout(() => { panel.style.display = 'none'; overlay.style.display = 'none'; }, 300);
}

async function saveDriver(e) {
  e.preventDefault();
  const btn = document.getElementById('driverSaveBtn');
  const origText = btn.textContent;
  btn.textContent = 'Saving…'; btn.disabled = true;

  const driverId = document.getElementById('driverEditId').value;
  const isEdit = !!driverId;
  let payload, action;

  if (isEdit) {
    action = 'edit_driver_details';
    payload = {
      driver_id: Number(driverId),
      full_name: document.getElementById('driverFormFullName').value.trim(),
      phone: document.getElementById('driverFormPhone').value.trim(),
      vehicle_type: document.getElementById('driverFormVehicleType').value,
      vehicle_model: document.getElementById('driverFormVehicleModel').value.trim(),
      vehicle_year: document.getElementById('driverFormVehicleYear').value.trim(),
      vehicle_plate: document.getElementById('driverFormVehiclePlate').value.trim().toUpperCase(),
      vehicle_color: document.getElementById('driverFormVehicleColor').value.trim(),
      bank_name: document.getElementById('driverFormBankName').value.trim(),
      account_holder_name: document.getElementById('driverFormAccountHolder').value.trim(),
      account_number: document.getElementById('driverFormAccountNumber').value.trim(),
      emergency_name: document.getElementById('driverFormEmergencyName').value.trim(),
      emergency_relationship: document.getElementById('driverFormEmergencyRelationship').value.trim(),
      emergency_phone: document.getElementById('driverFormEmergencyPhone').value.trim(),
      emergency_address: document.getElementById('driverFormEmergencyAddress').value.trim(),
    };
  } else {
    const fullName = document.getElementById('driverFormFullName').value.trim();
    const email = document.getElementById('driverFormEmail').value.trim();
    const phone = document.getElementById('driverFormPhone').value.trim();
    const password = document.getElementById('driverFormPassword').value;
    if (!fullName) { toast('Full name is required.'); btn.textContent = origText; btn.disabled = false; return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { toast('A valid email address is required.'); btn.textContent = origText; btn.disabled = false; return; }
    if (!phone) { toast('Phone number is required.'); btn.textContent = origText; btn.disabled = false; return; }
    if (password.length < 6) { toast('Password must be at least 6 characters.'); btn.textContent = origText; btn.disabled = false; return; }
    action = 'create_driver_account';
    payload = {
      full_name: fullName, email, phone, password,
      vehicle_type: document.getElementById('driverFormVehicleType').value,
      middle_name: document.getElementById('driverFormMiddleName').value.trim(),
      date_of_birth: document.getElementById('driverFormDob').value,
      gender: document.getElementById('driverFormGender').value,
      state_of_origin: document.getElementById('driverFormState').value,
      language: document.getElementById('driverFormLanguage').value,
    };
  }

  try {
    const res = await fetch(ADMIN_API_URL + '?action=' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.data?.message || 'Request failed.');
    toast(json.data?.message || (isEdit ? 'Driver updated.' : 'Driver account created.'));
    closeDriverPanel();
    loadDrivers();
  } catch (err) {
    toast('Error: ' + err.message);
  } finally {
    btn.textContent = origText; btn.disabled = false;
  }
}

// ── Customer Create / Edit slide panel ────────────────────────────────────
function _openCustomerSlidePanelRaw() {
  const panel = document.getElementById('customerSlidePanel');
  const overlay = document.getElementById('customerSlidePanelOverlay');
  if (!panel || !overlay) return;
  panel.style.display = 'flex';
  overlay.style.display = 'block';
  requestAnimationFrame(() => { panel.classList.add('open'); overlay.classList.add('open'); });
}

function openCustomerPanel() {
  document.getElementById('customerEditId').value = '';
  document.getElementById('customerFormFullName').value = '';
  document.getElementById('customerFormEmail').value = '';
  document.getElementById('customerFormPhone').value = '';
  document.getElementById('customerFormPassword').value = '';
  document.getElementById('customerFormEmailGroup').style.display = '';
  document.getElementById('customerFormPasswordGroup').style.display = '';
  document.getElementById('customerSlideTitle').textContent = 'Add Customer Account';
  document.getElementById('customerSlideSubtitle').textContent = 'Fill in the details below';
  document.getElementById('customerSaveBtn').textContent = 'Add Customer';
  _openCustomerSlidePanelRaw();
}

function editCustomer(customerId) {
  const c = _customerDataCache[Number(customerId)];
  if (!c) { toast('Refresh the customer list then try again.'); return; }
  document.getElementById('customerEditId').value = c.id;
  document.getElementById('customerFormFullName').value = c.full_name || '';
  document.getElementById('customerFormEmail').value = c.email || '';
  document.getElementById('customerFormPhone').value = c.phone || '';
  document.getElementById('customerFormEmailGroup').style.display = 'none';
  document.getElementById('customerFormPasswordGroup').style.display = 'none';
  document.getElementById('customerSlideTitle').textContent = 'Edit ' + escapeHtml(c.full_name || 'Customer');
  document.getElementById('customerSlideSubtitle').textContent = c.email || '';
  document.getElementById('customerSaveBtn').textContent = 'Save Changes';
  _openCustomerSlidePanelRaw();
}

function closeCustomerPanel() {
  const panel = document.getElementById('customerSlidePanel');
  const overlay = document.getElementById('customerSlidePanelOverlay');
  if (!panel || !overlay) return;
  panel.classList.remove('open'); overlay.classList.remove('open');
  setTimeout(() => { panel.style.display = 'none'; overlay.style.display = 'none'; }, 300);
}

async function saveCustomer(e) {
  e.preventDefault();
  const btn = document.getElementById('customerSaveBtn');
  const origText = btn.textContent;
  btn.textContent = 'Saving…'; btn.disabled = true;

  const customerId = document.getElementById('customerEditId').value;
  const isEdit = !!customerId;
  let payload, action;

  if (isEdit) {
    const fullName = document.getElementById('customerFormFullName').value.trim();
    const phone = document.getElementById('customerFormPhone').value.trim();
    if (!fullName) { toast('Full name is required.'); btn.textContent = origText; btn.disabled = false; return; }
    if (!phone) { toast('Phone number is required.'); btn.textContent = origText; btn.disabled = false; return; }
    action = 'edit_customer_details';
    payload = { customer_id: Number(customerId), full_name: fullName, phone };
  } else {
    const fullName = document.getElementById('customerFormFullName').value.trim();
    const email = document.getElementById('customerFormEmail').value.trim();
    const phone = document.getElementById('customerFormPhone').value.trim();
    const password = document.getElementById('customerFormPassword').value;
    if (!fullName) { toast('Full name is required.'); btn.textContent = origText; btn.disabled = false; return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { toast('A valid email address is required.'); btn.textContent = origText; btn.disabled = false; return; }
    if (!phone) { toast('Phone number is required.'); btn.textContent = origText; btn.disabled = false; return; }
    if (password.length < 6) { toast('Password must be at least 6 characters.'); btn.textContent = origText; btn.disabled = false; return; }
    action = 'create_customer_account';
    payload = { full_name: fullName, email, phone, password };
  }

  try {
    const res = await fetch(ADMIN_API_URL + '?action=' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.data?.message || 'Request failed.');
    toast(json.data?.message || (isEdit ? 'Customer updated.' : 'Customer account created.'));
    closeCustomerPanel();
    loadCustomers();
  } catch (err) {
    toast('Error: ' + err.message);
  } finally {
    btn.textContent = origText; btn.disabled = false;
  }
}

// --- ADMIN USERS (RBAC) ---

let currentRoles = [];
let rolePermissionsMap = {};
let currentUserOverrides = {};

async function loadAdminUsers() {
    const search = document.getElementById('adminUserSearch')?.value || '';
    const tbody = document.getElementById('adminUsersTbody');
    if (!tbody) return;
    tbody.innerHTML = skeletonTableRows(5,5);

    await loadMyPermissions();
    const canEditUsers         = iHavePermission('edit_admin_users');
    const canSuspendDeleteUsers = iHavePermission('suspend_delete_admin_users');

    try {
        const res = await fetch(ADMIN_API_URL + '?action=list_admin_users');
        const json = await res.json();

        if ( ! json.success ) {
            tbody.innerHTML = `<tr><td colspan="5">${emptyState('⚠️','Error loading users',json.data?.message||'Unknown error')}</td></tr>`;
            return;
        }

        const allUsers = json.data;

        // Update stat counters
        const active = allUsers.filter(u => u.status === 'active').length;
        const suspended = allUsers.filter(u => u.status !== 'active').length;
        const roles = [...new Set(allUsers.map(u => u.role_name))].length;
        const setEl = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        setEl('staffStatTotal', allUsers.length);
        setEl('staffStatActive', active);
        setEl('staffStatSuspended', suspended);
        setEl('staffStatRoles', roles);

        // Filter
        const filtered = search
            ? allUsers.filter(u => u.full_name.toLowerCase().includes(search.toLowerCase()) || u.email.toLowerCase().includes(search.toLowerCase()))
            : allUsers;

        const countEl = document.getElementById('staffUserCount');
        if (countEl) countEl.textContent = filtered.length + ' user' + (filtered.length !== 1 ? 's' : '');

        if ( allUsers.length === 0 ) {
            tbody.innerHTML = `<tr><td colspan="5">${emptyState('👥','No admin users yet','Create the first staff account using the button above.')}</td></tr>`;
            return;
        }

        if ( filtered.length === 0 ) {
            tbody.innerHTML = `<tr><td colspan="5">${emptyState('🔍','No matches','Try a different search term.')}</td></tr>`;
            return;
        }

        let html = '';
        filtered.forEach( u => {
            const initials = u.full_name.split(' ').map(n=>n[0]).join('').toUpperCase().slice(0,2);
            const avatar = u.avatar_path
                ? `<img src="${u.avatar_path}" class="avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">`
                : `<div class="avatar" style="width:36px;height:36px;border-radius:50%;background:var(--navy-light);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:12px;font-weight:700;color:var(--gold);flex-shrink:0;">${initials}</div>`;
            const statusClass = u.status === 'active' ? 'success' : (u.status === 'suspended' ? 'danger' : 'warn');
            const statusLabel = u.status.charAt(0).toUpperCase() + u.status.slice(1);
            const overridesCount = u.overrides ? Object.keys(u.overrides).length : 0;
            let lastLoginStr = '<span style="color:var(--text-muted);font-style:italic;font-size:11px;">Never</span>';
            if (u.last_login) {
                const d = new Date(u.last_login.replace(' ', 'T') + 'Z');
                lastLoginStr = d.toLocaleString('en-GB', {day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
            }
            const isSuperAdmin = u.role_name === 'Super Admin';

            html += `
            <tr class="staff-user-row">
                <td>
                    <div class="user-cell">
                        ${avatar}
                        <div>
                            <div class="strong">${escapeHtml(u.full_name)}</div>
                            <div class="sub-text">${escapeHtml(u.email)}</div>
                            <div class="staff-mobile-meta">
                                <span class="badge ${isSuperAdmin ? 'badge-warn' : 'neutral'}" style="font-size:10px;">${escapeHtml(u.role_name)}</span>
                                <span class="badge badge-${statusClass}" style="font-size:10px;text-transform:capitalize;">${statusLabel}</span>
                            </div>
                        </div>
                    </div>
                </td>
                <td class="hide-mobile">
                    <span class="badge ${isSuperAdmin ? 'badge-warn' : 'neutral'}">${escapeHtml(u.role_name)}</span>
                    ${overridesCount > 0 ? `<div style="font-size:10px;color:var(--info);margin-top:3px;">${overridesCount} override${overridesCount!==1?'s':''}</div>` : ''}
                </td>
                <td class="hide-mobile"><span class="badge badge-${statusClass}" style="text-transform:capitalize;">${statusLabel}</span></td>
                <td class="hide-mobile" style="font-size:12px;color:var(--text-secondary);">${lastLoginStr}</td>
                <td>
                    <div class="td-actions staff-actions">
                        ${canEditUsers ? `<button class="btn-secondary" style="padding:5px 12px;font-size:12px;" onclick='editAdminUser(${JSON.stringify(u).replace(/'/g, "&apos;")})'>Edit</button>` : ''}
                        ${!isSuperAdmin && canSuspendDeleteUsers ? `<button class="btn-secondary" style="padding:5px 12px;font-size:12px;color:var(--${u.status==='active'?'danger':'success'});border-color:var(--${u.status==='active'?'danger':'success'});" onclick="toggleAdminUserStatus(${u.id}, '${u.status}')">${u.status === 'active' ? 'Suspend' : 'Activate'}</button>` : ''}
                        ${!isSuperAdmin && canSuspendDeleteUsers ? `<button class="btn-secondary" style="padding:5px 12px;font-size:12px;color:var(--danger);border-color:var(--danger);" onclick="deleteAdminUser(${u.id}, '${escapeHtml(u.full_name).replace(/'/g,"\\'")}')">Delete</button>` : ''}
                    </div>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;

    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="5">${emptyState('⚠️','Network error',escapeHtml(e.message))}</td></tr>`;
    }
}

async function loadRolesForDropdown() {
    const select = document.getElementById('adminUserRole');
    if (!select) return;
    select.innerHTML = '<option value="">Loading roles…</option>';
    try {
        const res = await fetch(ADMIN_API_URL + '?action=get_roles');
        const json = await res.json();

        if ( json.success && json.data.length > 0 ) {
            currentRoles = json.data;
            rolePermissionsMap = {};
            select.innerHTML = '<option value="">Select a role…</option>';
            currentRoles.forEach(r => {
                rolePermissionsMap[r.id] = r.permissions || [];
                select.innerHTML += `<option value="${r.id}">${r.name}</option>`;
            });
        } else {
            select.innerHTML = '<option value="">No roles available</option>';
            console.warn("get_roles returned empty or error:", json);
        }
    } catch(e) {
        select.innerHTML = '<option value="">Failed to load roles</option>';
        console.error("Failed to load roles", e);
    }
}

const allPermissions = [
    { key: 'view_live_map', label: 'View Live Map' },
    { key: 'filter_intervene_trips', label: 'Filter & Intervene Trips' },
    { key: 'force_redispatch', label: 'Force Redispatch' },
    { key: 'force_cancel_trip', label: 'Force Cancel Trip' },
    { key: 'view_trips', label: 'View Trips' },
    { key: 'admin_cancel_trip', label: 'Admin Cancel Trip' },
    { key: 'correct_trip_status', label: 'Correct Trip Status' },
    { key: 'export_trips', label: 'Export Trips' },
    { key: 'view_kyc', label: 'View KYC' },
    { key: 'approve_reject_kyc', label: 'Approve/Reject KYC' },
    { key: 'config_kyc_policy', label: 'Configure KYC Policy' },
    { key: 'view_customers', label: 'View Customers' },
    { key: 'suspend_customer', label: 'Suspend Customer' },
    { key: 'view_customer_history', label: 'View Customer History' },
    { key: 'view_drivers', label: 'View Drivers' },
    { key: 'suspend_reinstate_driver', label: 'Suspend/Reinstate Driver' },
    { key: 'view_driver_wallet', label: 'View Driver Wallet' },
    { key: 'view_payments', label: 'View Payments' },
    { key: 'approve_reject_payment', label: 'Approve/Reject Payments' },
    { key: 'execute_payouts', label: 'Execute Payouts' },
    { key: 'view_export_revenue', label: 'View/Export Revenue' },
    { key: 'issue_refunds', label: 'Issue Refunds' },
    { key: 'view_disputes', label: 'View Disputes' },
    { key: 'assign_resolve_disputes', label: 'Assign/Resolve Disputes' },
    { key: 'view_download_evidence', label: 'View Evidence' },
    { key: 'view_notifications', label: 'View Notifications' },
    { key: 'send_manual_notifications', label: 'Send Manual Notifications' },
    { key: 'view_settings', label: 'View Settings' },
    { key: 'edit_pricing_commission', label: 'Edit Pricing & Commission' },
    { key: 'edit_payment_credentials', label: 'Edit Payment Credentials' },
    { key: 'edit_kyc_notif_policies', label: 'Edit KYC/Notif Policies' },
    { key: 'edit_legal_docs', label: 'Edit Legal Docs' },
    { key: 'view_admin_users', label: 'View Admin Users' },
    { key: 'create_admin_users', label: 'Create Admin Users' },
    { key: 'edit_admin_users', label: 'Edit Admin Users' },
    { key: 'suspend_delete_admin_users', label: 'Suspend/Delete Admin Users' }
];

let myPermissions = null;

async function loadMyPermissions() {
    if (myPermissions) return; // already loaded
    try {
        const res = await fetch(ADMIN_API_URL + '?action=get_my_permissions');
        const json = await res.json();
        if (json.success) {
            myPermissions = json.data;
        }
    } catch(e) {
        console.error(e);
    }
}

function iHavePermission(key) {
    if (!myPermissions) return false;
    if (myPermissions.is_super) return true;

    const isRoleDefault = myPermissions.role_perms && myPermissions.role_perms.includes(key);
    if (myPermissions.overrides && myPermissions.overrides[key] !== undefined) {
        return myPermissions.overrides[key];
    }
    return isRoleDefault;
}

const permissionCategories = [
    { label: 'Live Operations', keys: ['view_live_map','filter_intervene_trips','force_redispatch','force_cancel_trip'] },
    { label: 'Trip Management', keys: ['view_trips','admin_cancel_trip','correct_trip_status','export_trips'] },
    { label: 'KYC', keys: ['view_kyc','approve_reject_kyc','config_kyc_policy'] },
    { label: 'Customers', keys: ['view_customers','suspend_customer','view_customer_history'] },
    { label: 'Drivers', keys: ['view_drivers','suspend_reinstate_driver','view_driver_wallet'] },
    { label: 'Payments & Finance', keys: ['view_payments','approve_reject_payment','execute_payouts','view_export_revenue','issue_refunds'] },
    { label: 'Disputes', keys: ['view_disputes','assign_resolve_disputes','view_download_evidence'] },
    { label: 'Notifications', keys: ['view_notifications','send_manual_notifications'] },
    { label: 'Settings', keys: ['view_settings','edit_pricing_commission','edit_payment_credentials','edit_kyc_notif_policies','edit_legal_docs'] },
    { label: 'Admin Users', keys: ['view_admin_users','create_admin_users','edit_admin_users','suspend_delete_admin_users'] },
];

function renderPermissionToggles() {
    const roleId = document.getElementById('adminUserRole')?.value;
    const container = document.getElementById('adminUserPermissionsContainer');
    const descCard = document.getElementById('adminUserRoleDescCard');
    if (!container) return;

    if ( ! roleId ) {
        container.innerHTML = `<div style="text-align:center;padding:24px 16px;color:var(--text-muted);font-size:13px;">
            <div style="font-size:28px;margin-bottom:8px;">🔐</div>
            Select a role above to view and override permissions.
        </div>`;
        if (descCard) { descCard.textContent = ''; descCard.classList.remove('visible'); }
        return;
    }

    const role = currentRoles.find(r => r.id == roleId);
    if (role && descCard) {
        descCard.textContent = role.description || '';
        descCard.classList.toggle('visible', !!role.description);
    }

    const defaultPerms = rolePermissionsMap[roleId] || [];
    const permMap = {};
    allPermissions.forEach(p => { permMap[p.key] = p.label; });

    let html = '';
    permissionCategories.forEach(cat => {
        const permsInCat = cat.keys.filter(k => permMap[k]);
        if (!permsInCat.length) return;

        html += `<div class="perm-category">
            <div class="perm-category-title">${cat.label}</div>
            <div class="perm-grid">`;

        permsInCat.forEach(key => {
            const label = permMap[key];
            const isDefault = defaultPerms.includes(key);
            const isGranted = currentUserOverrides[key] !== undefined ? currentUserOverrides[key] : isDefault;
            const isOverridden = isGranted !== isDefault;
            const canEdit = iHavePermission(key);

            html += `
            <label class="perm-toggle-row${isOverridden ? ' is-override' : ''}${!canEdit ? ' is-disabled' : ''}" ${!canEdit ? 'title="You do not have this permission"' : ''}>
                <span class="perm-toggle-label">
                    ${escapeHtml(label)}
                    ${isOverridden ? '<span class="perm-override-dot"></span>' : ''}
                </span>
                <label class="perm-switch">
                    <input type="checkbox" id="perm_${key}" ${isGranted ? 'checked' : ''} ${!canEdit ? 'disabled' : ''} onchange="updateOverride('${key}', this.checked, ${isDefault})">
                    <span class="perm-switch-slider"></span>
                </label>
            </label>`;
        });

        html += '</div></div>';
    });

    container.innerHTML = html;
}

function updateOverride(permKey, checked, isDefault) {
    if ( checked === isDefault ) {
        delete currentUserOverrides[permKey]; // Reverted to default
    } else {
        currentUserOverrides[permKey] = checked;
    }
    renderPermissionToggles(); // Re-render to show/hide the override indicator
}

async function openAdminUserPanel() {
    if (!myPermissions) await loadMyPermissions();
    document.getElementById('adminUserId').value = '';
    document.getElementById('adminUserFullName').value = '';
    document.getElementById('adminUserEmail').value = '';
    document.getElementById('adminUserPassword').value = '';
    document.getElementById('adminUserRole').value = '';
    const _rdesc = document.getElementById('adminUserRoleDescCard'); if (_rdesc) { _rdesc.textContent=''; _rdesc.classList.remove('visible'); }
    document.getElementById('adminUserPasswordGroup').style.display = 'block';

    currentUserOverrides = {};
    document.getElementById('adminUserPermissionsContainer').innerHTML = '';

    document.getElementById('adminUserSlideTitle').innerText = 'Create Admin User';
    document.getElementById('adminUserSlideSubtitle').innerText = 'Fill in the details below';

    // Always re-fetch roles so the dropdown is always populated
    loadRolesForDropdown();

    const panel = document.getElementById('adminUserSlidePanel');
    const overlay = document.getElementById('adminUserSlidePanelOverlay');
    panel.style.display = 'flex';
    overlay.style.display = 'block';
    requestAnimationFrame(() => {
        panel.classList.add('open');
        overlay.classList.add('open');
    });
}

function editAdminUser(u) {
    openAdminUserPanel();
    // Wait for roles to load then populate form
    setTimeout(async () => {
        if (currentRoles.length === 0) await loadRolesForDropdown();
        document.getElementById('adminUserId').value = u.id;
        document.getElementById('adminUserFullName').value = u.full_name;
        document.getElementById('adminUserEmail').value = u.email;
        document.getElementById('adminUserPasswordGroup').style.display = 'none';
        document.getElementById('adminUserRole').value = u.role_id;
        currentUserOverrides = u.overrides || {};
        document.getElementById('adminUserSlideTitle').innerText = 'Edit ' + u.full_name;
        document.getElementById('adminUserSlideSubtitle').innerText = u.email;
        renderPermissionToggles();
    }, 200);
}

function closeAdminUserPanel() {
    const panel = document.getElementById('adminUserSlidePanel');
    const overlay = document.getElementById('adminUserSlidePanelOverlay');
    panel.classList.remove('open');
    overlay.classList.remove('open');
    setTimeout(() => {
        panel.style.display = 'none';
        overlay.style.display = 'none';
    }, 300);
}

async function saveAdminUser(e) {
    e.preventDefault();
    const btn = document.getElementById('adminUserSaveBtn');
    btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;"></span> Saving...';
    btn.disabled = true;

    const id = document.getElementById('adminUserId').value;
    const action = id ? 'update_admin_user' : 'create_admin_user';

    const payload = {
        action: action,
        id: id,
        full_name: document.getElementById('adminUserFullName').value,
        email: document.getElementById('adminUserEmail').value,
        role_id: document.getElementById('adminUserRole').value,
        overrides: currentUserOverrides
    };

    if ( !id ) {
        payload.password = document.getElementById('adminUserPassword').value;
    }

    try {
        const res = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();

        if ( json.success ) {
            closeAdminUserPanel();
            loadAdminUsers();
            toast('User saved successfully');
        } else {
            alert('Error: ' + (json.data?.message || 'Unknown error'));
        }
    } catch(err) {
        alert('Network error.');
    }

    btn.innerHTML = 'Save User';
    btn.disabled = false;
}

async function toggleAdminUserStatus(id, currentStatus) {
    if ( ! confirm(`Are you sure you want to ${currentStatus === 'active' ? 'suspend' : 'activate'} this user?`) ) return;

    try {
        const payload = {
            action: 'suspend_admin_user',
            id: id,
            action_type: currentStatus === 'active' ? 'suspend' : 'activate'
        };
        const res = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if ( json.success ) {
            loadAdminUsers();
        } else {
            alert('Error: ' + (json.data?.message || 'Unknown error'));
        }
    } catch(err) {
        alert('Network error.');
    }
}

async function deleteAdminUser(id, name) {
    if ( ! confirm(`Permanently delete ${name}? This cannot be undone.`) ) return;
    try {
        const res = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_admin_user', id })
        });
        const json = await res.json();
        if ( json.success ) {
            loadAdminUsers();
            toast('User deleted.');
        } else {
            alert('Error: ' + (json.data?.message || 'Unknown error'));
        }
    } catch(err) {
        alert('Network error.');
    }
}

// ══════════════════════════════════════════════════════════
// SUPPORT TICKETS — Admin Panel
// ══════════════════════════════════════════════════════════

let _currentAdminTicketId = 0;
const ticketPageState = { page: 1, per_page: 10, search: '', status: '', filter: 'all' };
const searchTimersTickets = {};

function switchDisputeTab(tab, btn) {
  document.querySelectorAll('#panel-disputes > .filter-row:first-of-type .filter-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  document.getElementById('sub-disputes').style.display = tab === 'disputes' ? '' : 'none';
  document.getElementById('sub-tickets').style.display = tab === 'tickets' ? '' : 'none';
  if (tab === 'tickets') loadAdminTickets(1);
}

async function loadAdminTickets(page) {
  if (page) ticketPageState.page = page;
  const list = document.getElementById('ticketList');
  if (!list) return;
  list.innerHTML = skeletonRows();
  try {
    const params = { ...ticketPageState };
    const data = await adminApi('get_support_tickets', params);
    const rows = data.tickets || [];
    document.getElementById('ticketTotalCount').textContent = Number(data.total || 0).toLocaleString();
    document.getElementById('ticketOpenCount').textContent = Number(data.open_count || 0).toLocaleString();
    const badge = document.getElementById('ticketOpenBadge');
    if (badge) { badge.textContent = data.open_count || 0; badge.style.display = data.open_count > 0 ? '' : 'none'; }
    list.innerHTML = rows.length ? rows.map(renderAdminTicketItem).join('') : '<div class="empty-state">No tickets match your filters.</div>';
    renderPagination('ticketPagination', ticketPageState, data.total, 'loadAdminTickets');
  } catch(e) {
    list.innerHTML = '<div class="empty-state">Could not load tickets: ' + escapeHtml(e.message) + '</div>';
  }
}

function renderAdminTicketItem(t) {
  const priorityColors = { low: 'var(--text-muted)', medium: 'var(--info)', high: 'var(--warn)', urgent: 'var(--danger)' };
  const priorityColor = priorityColors[t.priority || 'medium'] || 'var(--info)';
  const statusLabels = { open: 'Open', in_progress: 'In Progress', escalated: 'Escalated', resolved: 'Resolved', closed: 'Closed' };
  const catLabels = { general: 'General', trip_issue: 'Trip Issue', billing: 'Billing', account: 'Account', emergency_safety: 'Safety' };
  const title = '#T-' + String(t.id).padStart(4, '0') + ' · ' + (catLabels[t.category] || t.category || 'Support');
  const assignee = t.assignee_name ? `Assigned: ${t.assignee_name}` : 'Unassigned';
  const preview = t.last_message ? escapeHtml(t.last_message).substring(0, 80) + (t.last_message.length > 80 ? '…' : '') : 'No messages yet';
  const date = dateLabel(t.updated_at || t.created_at);
  return `<div class="list-item">
    <div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info);font-size:11px">${t.status === 'resolved' ? '✓' : (t.status === 'escalated' ? '🔴' : '?')}</div>
    <div class="item-info" style="flex:1">
      <div class="item-name">${escapeHtml(title)}</div>
      <div class="item-meta">${escapeHtml(t.creator_name || 'Unknown')} · ${escapeHtml(assignee)} · ${date}</div>
      <div style="font-size:11px;color:var(--text-muted);margin-top:2px">${preview}</div>
    </div>
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0">
      <span class="badge" style="background:${priorityColor};color:#fff;font-size:10px;padding:2px 7px;border-radius:6px;text-transform:uppercase">${t.priority || 'medium'}</span>
      <span class="badge ${t.status === 'resolved' ? 'badge-success' : (t.status === 'escalated' ? 'badge-danger' : 'badge-warn')}" style="font-size:10px">${statusLabels[t.status] || t.status}</span>
      <button class="btn-sm btn-view" onclick="openAdminTicketDetail(${Number(t.id)})">View</button>
    </div>
  </div>`;
}

function filterTickets(filter, btn) {
  ticketPageState.filter = filter;
  document.querySelectorAll('#sub-tickets .filter-row .filter-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  const label = { all: 'All', unassigned: 'Unassigned', mine: 'Mine', escalated: 'Escalated', resolved: 'Resolved' };
  const activeFilterEl = document.getElementById('ticketActiveFilter');
  if (activeFilterEl) activeFilterEl.textContent = label[filter] || filter;
  loadAdminTickets(1);
}

function setTicketStatusFilter(v) { ticketPageState.status = v; loadAdminTickets(1); }
function queueTicketSearch(v) {
  clearTimeout(searchTimersTickets.tickets);
  searchTimersTickets.tickets = setTimeout(() => { ticketPageState.search = v; loadAdminTickets(1); }, 300);
}

async function openAdminTicketDetail(ticketId) {
  _currentAdminTicketId = ticketId;
  const modal = document.getElementById('ticketDetailModal');
  if (!modal) return;
  document.getElementById('ticketDetailTitle').textContent = 'Ticket #' + String(ticketId).padStart(4, '0');
  document.getElementById('ticketDetailMeta').innerHTML = '';
  document.getElementById('ticketAdminThread').innerHTML = '<div style="text-align:center;padding:30px 0;color:var(--text-muted)">Loading…</div>';
  modal.classList.add('open');
  try {
    const data = await adminApi('get_ticket_messages', { ticket_id: ticketId });
    const { ticket, messages, admins } = data;

    // Fill assignee dropdown
    const sel = document.getElementById('ticketAssignSelect');
    sel.innerHTML = '<option value="0">Unassigned</option>' + (admins || []).map(a => `<option value="${a.id}" ${Number(a.id) === Number(ticket.assigned_to) ? 'selected' : ''}>${escapeHtml(a.full_name)}</option>`).join('');

    // Fill priority
    document.getElementById('ticketPrioritySelect').value = ticket.priority || 'medium';

    // Fill status
    document.getElementById('ticketStatusSelect').value = ticket.status || 'open';

    // Fill meta
    const catLabels = { general: 'General', trip_issue: 'Trip Issue', billing: 'Billing', account: 'Account', emergency_safety: 'Safety' };
    const statusColors = { open: 'var(--warn)', in_progress: 'var(--info)', escalated: 'var(--danger)', resolved: 'var(--success)', closed: 'var(--text-muted)' };
    document.getElementById('ticketDetailMeta').innerHTML =
      `<span style="font-size:11px;padding:2px 8px;border-radius:6px;background:${statusColors[ticket.status] || 'var(--info)'};color:#fff;font-weight:700">${ticket.status}</span>` +
      `<span style="font-size:11px;color:var(--text-muted)">${catLabels[ticket.category] || ticket.category}</span>` +
      `<span style="font-size:11px;color:var(--text-muted)">From: ${escapeHtml(ticket.customer_name || ticket.driver_name || 'Unknown')}</span>`;

    // Render messages
    if (!messages.length) {
      document.getElementById('ticketAdminThread').innerHTML = '<div style="text-align:center;padding:30px 0;color:var(--text-muted)">No messages yet.</div>';
    } else {
      document.getElementById('ticketAdminThread').innerHTML = messages.map(m => {
        const isAdmin = m.sender_type === 'admin';
        const senderLabel = isAdmin ? ('Admin: ' + escapeHtml(m.sender_name || 'Support')) : escapeHtml(m.sender_name || m.sender_type);
        const time = new Date(m.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
        const text = escapeHtml(m.message).replace(/\n/g, '<br>');
        return `<div style="display:flex;flex-direction:column;align-items:${isAdmin ? 'flex-end' : 'flex-start'}">
          <div style="max-width:80%;background:${isAdmin ? 'var(--info)' : 'var(--surface-2)'};color:${isAdmin ? '#fff' : 'var(--text-primary)'};border-radius:12px;padding:10px 14px;font-size:13px">${text}</div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:4px">${senderLabel} · ${time}</div>
        </div>`;
      }).join('');
      const thread = document.getElementById('ticketAdminThread');
      thread.scrollTop = thread.scrollHeight;
    }

    // Show/hide reply box
    const replyBox = document.getElementById('ticketAdminReplyBox');
    if (replyBox) replyBox.style.display = ['resolved', 'closed'].includes(ticket.status) ? 'none' : '';

  } catch(e) {
    document.getElementById('ticketAdminThread').innerHTML = '<div style="text-align:center;padding:30px 0;color:var(--text-muted)">Could not load ticket: ' + escapeHtml(e.message) + '</div>';
  }
}

function closeTicketDetailModal() {
  const modal = document.getElementById('ticketDetailModal');
  if (modal) modal.classList.remove('open');
  _currentAdminTicketId = 0;
  loadAdminTickets();
}

async function adminReplyToTicket() {
  if (!_currentAdminTicketId) return;
  const input = document.getElementById('ticketAdminReplyInput');
  const message = input?.value?.trim();
  if (!message) { toast('Write a message first.'); return; }
  const btn = document.getElementById('ticketAdminReplyBtn');
  const oldText = btn.textContent;
  btn.textContent = 'Sending…';
  btn.disabled = true;
  try {
    await adminApi('admin_reply_ticket', { ticket_id: _currentAdminTicketId, message }, 'POST');
    input.value = '';
    await openAdminTicketDetail(_currentAdminTicketId);
  } catch(e) {
    toast(e.message || 'Could not send reply.');
  } finally {
    btn.textContent = oldText;
    btn.disabled = false;
  }
}

async function adminAssignTicket(assigned_to) {
  if (!_currentAdminTicketId) return;
  try {
    await adminApi('assign_ticket', { ticket_id: _currentAdminTicketId, assigned_to }, 'POST');
    toast('Ticket assigned.');
  } catch(e) { toast(e.message || 'Could not assign ticket.'); }
}

async function adminUpdateTicketStatus(status) {
  if (!_currentAdminTicketId) return;
  try {
    await adminApi('update_ticket_status', { ticket_id: _currentAdminTicketId, status }, 'POST');
    toast('Status updated to: ' + status.replace('_', ' ') + '.');
    const replyBox = document.getElementById('ticketAdminReplyBox');
    if (replyBox) replyBox.style.display = ['resolved', 'closed'].includes(status) ? 'none' : '';
  } catch(e) { toast(e.message || 'Could not update status.'); }
}

async function adminSetTicketPriority(priority) {
  if (!_currentAdminTicketId) return;
  try {
    await adminApi('set_ticket_priority', { ticket_id: _currentAdminTicketId, priority }, 'POST');
    toast('Priority set to: ' + priority + '.');
  } catch(e) { toast(e.message || 'Could not set priority.'); }
}

// ─── KYC DOCUMENT POLICY ───────────────────────────────────────────────────

async function loadKycPolicy(){
  const container = document.getElementById('kycPolicySection');
  if(!container) return;
  container.innerHTML = skeletonRows(4);
  try {
    const data = await adminApi('get_kyc_policy');
    const policies = data.policies || [];
    const docTypes = [
      { key:'government_id',        label:'Government ID' },
      { key:'drivers_license',      label:"Driver's License" },
      { key:'vehicle_insurance',    label:'Vehicle Insurance' },
      { key:'vehicle_registration', label:'Vehicle Registration' },
      { key:'proof_of_ownership',   label:'Proof of Ownership' },
    ];
    const vtypes = ['bike','car','van','keke'];
    const vlabels = { bike:'Motorbike', car:'Car', van:'Van', keke:'Tricycle' };
    const byType = {};
    policies.forEach(p => { byType[p.vehicle_type] = p; });
    let html = '';
    vtypes.forEach(vt => {
      const p = byType[vt] || { required_documents: [], selfie_required: 1, min_age: 18 };
      let reqDocs = [];
      if (Array.isArray(p.required_documents)) {
        reqDocs = p.required_documents;
      } else {
        try { reqDocs = JSON.parse(p.required_documents || '[]'); } catch(_) {}
      }
      html += `<div style="margin-bottom:16px;background:var(--surface);border-radius:10px;padding:14px 16px">`;
      html += `<div style="font-weight:700;font-size:13px;margin-bottom:12px">${vehicleIcon(vt)} ${escapeHtml(vlabels[vt])}</div>`;
      html += `<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:8px;margin-bottom:12px">`;
      docTypes.forEach(dt => {
        html += `<label style="display:flex;align-items:center;gap:8px;font-size:12px;cursor:pointer"><input type="checkbox" data-kyc-vehicle="${escapeHtml(vt)}" data-kyc-doc="${escapeHtml(dt.key)}" ${reqDocs.includes(dt.key)?'checked':''} style="width:14px;height:14px;cursor:pointer"> ${escapeHtml(dt.label)}</label>`;
      });
      html += `</div><div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">`;
      html += `<label style="display:flex;align-items:center;gap:8px;font-size:12px;cursor:pointer"><input type="checkbox" data-kyc-vehicle="${escapeHtml(vt)}" data-kyc-selfie="1" ${p.selfie_required?'checked':''} style="width:14px;height:14px;cursor:pointer"> Selfie required</label>`;
      html += `<label style="display:flex;align-items:center;gap:8px;font-size:12px">Min. age: <input type="number" data-kyc-vehicle="${escapeHtml(vt)}" data-kyc-age="1" value="${Number(p.min_age||18)}" min="16" max="70" style="width:52px;padding:4px 8px;border-radius:6px;border:1px solid var(--surface-2);background:var(--bg);color:var(--text);font-size:12px"></label>`;
      html += `</div></div>`;
    });
    container.innerHTML = html || emptyState('📋','No policy data','KYC document policy will appear here.');
  } catch(e) {
    container.innerHTML = emptyState('⚠️','Could not load KYC policy',escapeHtml(e.message));
  }
}

async function saveKycPolicy(){
  const container = document.getElementById('kycPolicySection');
  if(!container){ toast('Policy panel not loaded.'); return; }
  const vtypes = ['bike','car','van','keke'];
  const docKeys = ['government_id','drivers_license','vehicle_insurance','vehicle_registration','proof_of_ownership'];
  const policies = vtypes.map(vt => ({
    vehicle_type: vt,
    required_documents: docKeys.filter(dk => container.querySelector(`[data-kyc-vehicle="${vt}"][data-kyc-doc="${dk}"]`)?.checked),
    selfie_required: container.querySelector(`[data-kyc-vehicle="${vt}"][data-kyc-selfie]`)?.checked ? 1 : 0,
    min_age: Number(container.querySelector(`[data-kyc-vehicle="${vt}"][data-kyc-age]`)?.value || 18),
  }));
  try {
    const data = await adminApi('save_kyc_policy', { policies: JSON.stringify(policies) }, 'POST');
    toast(data.message || 'KYC document policy saved');
  } catch(e) {
    toast(e.message || 'Could not save KYC policy');
  }
}

// ─── BLACKLIST ──────────────────────────────────────────────────────────────

function driverPanelTab(tab, btn){
  document.querySelectorAll('#panel-drivers .tabs .tab').forEach(t => t.classList.remove('active'));
  if(btn) btn.classList.add('active');
  const mainSection = document.getElementById('driverMainSection');
  const blSection = document.getElementById('blacklistSection');
  if(tab === 'blacklist'){
    if(mainSection) mainSection.style.display = 'none';
    if(blSection) blSection.style.display = 'block';
    loadBlacklist();
  } else {
    if(mainSection) mainSection.style.display = 'block';
    if(blSection) blSection.style.display = 'none';
  }
}

async function loadBlacklist(){
  const list = document.getElementById('blacklistDirectory');
  if(!list) return;
  list.innerHTML = skeletonRows(3);
  try {
    const data = await adminApi('get_blacklist');
    const entries = data.entries || [];
    if(!entries.length){
      list.innerHTML = emptyState('✅','No blacklisted identifiers','Add phone numbers, emails, or device IDs to permanently ban accounts.');
      return;
    }
    list.innerHTML = entries.map(e => `
      <div class="list-item">
        <div class="avatar" style="background:rgba(239,68,68,0.1);color:var(--danger);font-size:18px">⛔</div>
        <div class="item-info">
          <div class="item-name">${escapeHtml(e.identifier_value)} <span class="badge badge-danger" style="font-size:10px">${escapeHtml(e.identifier_type)}</span></div>
          <div class="item-meta">${escapeHtml(e.reason||'No reason provided')} · Banned by ${escapeHtml(e.admin_name||'Admin')} · ${dateLabel(e.created_at)}</div>
        </div>
        <div class="item-actions">
          <button class="btn-sm btn-reject" onclick="removeFromBlacklist(${Number(e.id)})">Remove</button>
        </div>
      </div>`).join('');
  } catch(e) {
    list.innerHTML = emptyState('⚠️','Could not load blacklist',escapeHtml(e.message));
  }
}

async function addToBlacklist(){
  const type  = document.getElementById('blacklistType')?.value;
  const value = (document.getElementById('blacklistValue')?.value || '').trim();
  const reason = (document.getElementById('blacklistReason')?.value || '').trim();
  if(!value || !reason){ toast('Enter the identifier value and reason.'); return; }
  try {
    const data = await adminApi('add_to_blacklist', { identifier_type: type, identifier_value: value, reason }, 'POST');
    toast(data.message || 'Added to blacklist');
    if(document.getElementById('blacklistValue'))  document.getElementById('blacklistValue').value  = '';
    if(document.getElementById('blacklistReason')) document.getElementById('blacklistReason').value = '';
    loadBlacklist();
  } catch(e) {
    toast(e.message || 'Could not add to blacklist');
  }
}

async function removeFromBlacklist(id){
  const confirmed = await showConfirmDialog({
    title: 'Remove from Blacklist',
    desc: 'Remove this identifier? The associated account will be able to register and log in again.',
    confirmLabel: 'Remove',
    confirmClass: 'danger',
    reasonRequired: false,
  });
  if(confirmed === null) return;
  try {
    const data = await adminApi('remove_from_blacklist', { blacklist_id: id }, 'POST');
    toast(data.message || 'Removed from blacklist');
    loadBlacklist();
  } catch(e) {
    toast(e.message || 'Could not remove from blacklist');
  }
}


// ── Ratings panel ─────────────────────────────────────────────────────────────

const pageStateRatings = { page: 1, per_page: 20 };

function starsHtml(rating) {
  const n = Number(rating) || 0;
  let s = '';
  for (let i = 1; i <= 5; i++) {
    s += `<span style="color:${i <= n ? 'var(--gold,#f5b842)' : 'var(--text-muted)'}">★</span>`;
  }
  return s;
}

async function loadRatings(page = pageStateRatings.page) {
  pageStateRatings.page = page;
  const list = document.getElementById('ratingsList');
  if (!list) return;
  list.innerHTML = skeletonRows();

  const params = { page };
  const reviewerType = document.getElementById('ratingsFilterType')?.value;
  const star         = document.getElementById('ratingsFilterStar')?.value;
  const dateFrom     = document.getElementById('ratingsDateFrom')?.value;
  const dateTo       = document.getElementById('ratingsDateTo')?.value;
  const flaggedOnly  = document.getElementById('ratingsFlaggedOnly')?.checked;

  if (reviewerType) params.reviewer_type = reviewerType;
  if (star)         params.rating         = star;
  if (dateFrom)     params.date_from      = dateFrom;
  if (dateTo)       params.date_to        = dateTo;
  if (flaggedOnly)  params.flagged_only   = '1';

  try {
    const data    = await adminApi('get_ratings', params);
    const ratings = data.ratings || [];
    const total   = Number(data.total || 0);

    document.getElementById('ratingsTotalCount').textContent  = total.toLocaleString();
    const avgScore = ratings.length
      ? (ratings.reduce((s, r) => s + Number(r.rating), 0) / ratings.length).toFixed(1)
      : '--';
    document.getElementById('ratingsAvgScore').textContent    = avgScore + (avgScore !== '--' ? '★' : '');
    document.getElementById('ratingsFlaggedCount').textContent = ratings.filter(r => Number(r.flagged)).length.toString();
    document.getElementById('ratingsOneStarCount').textContent = ratings.filter(r => Number(r.rating) === 1).length.toString();

    if (!ratings.length && total > 0) {
      list.innerHTML = emptyState('⚠️', 'Ratings found but could not be loaded', 'The server returned a count of ' + total + ' but no rows. Check server logs or try refreshing.');
    } else if (!ratings.length) {
      list.innerHTML = emptyState('⭐', 'No ratings match your filters', 'Try adjusting the filters above.');
    } else {
      list.innerHTML = `<div class="table-responsive"><table class="data-table"><thead><tr>
        <th>Reviewer</th><th>Subject</th><th>Stars</th><th>Comment</th><th>Trip</th><th>Date</th><th>Actions</th>
      </tr></thead><tbody>${ratings.map(renderRatingRow).join('')}</tbody></table></div>`;
    }

    renderPagination('ratingsPagination', pageStateRatings, total, 'loadRatings');
  } catch (e) {
    list.innerHTML = emptyState('⚠️', 'Could not load ratings', escapeHtml(e.message));
  }
}

function renderRatingRow(r) {
  const reviewerLabel = r.reviewer_type === 'customer' ? 'Customer' : 'Driver';
  const subjectType   = r.reviewer_type === 'customer' ? 'driver'   : 'customer';
  const flagClass     = Number(r.flagged) ? 'badge-warning' : '';
  const flagLabel     = Number(r.flagged) ? '🚩 Flagged' : '';
  return `<tr>
    <td>
      <span style="font-size:11px;color:var(--text-muted)">${escapeHtml(reviewerLabel)}</span><br>
      <strong>${escapeHtml(r.reviewer_name || 'Unknown')}</strong>
    </td>
    <td>
      <button class="btn-link" style="text-align:left;background:none;border:none;cursor:pointer;color:var(--info);font-size:13px;padding:0" onclick="openSubjectRatings('${escapeHtml(subjectType)}',${Number(r.subject_id)},'${escapeHtml(r.subject_name||'Unknown').replace(/'/g,"&#39;")}')">
        ${escapeHtml(r.subject_name || 'Unknown')}
      </button>
    </td>
    <td style="white-space:nowrap">${starsHtml(r.rating)} <span style="font-size:12px;color:var(--text-muted)">${r.rating}/5</span>${flagLabel ? `<br><span class="badge ${flagClass}" style="font-size:10px">${flagLabel}</span>` : ''}</td>
    <td style="max-width:200px;font-size:13px;color:var(--text-secondary)">${escapeHtml(r.comment || '—')}</td>
    <td style="font-size:12px">${escapeHtml(r.trip_ref || ('#' + r.trip_id))}</td>
    <td style="font-size:12px;white-space:nowrap">${dateLabel(r.created_at)}</td>
    <td style="white-space:nowrap">
      <button class="btn-sm" style="margin-right:4px" onclick="toggleFlagRating(${Number(r.id)}, this)">${Number(r.flagged) ? 'Unflag' : '🚩 Flag'}</button>
      <button class="btn-sm btn-reject" onclick="deleteRating(${Number(r.id)})">Delete</button>
    </td>
  </tr>`;
}

async function toggleFlagRating(ratingId, btn) {
  btn.disabled = true;
  try {
    const data = await adminApi('flag_rating', { rating_id: ratingId }, 'POST');
    toast(data.message || 'Done.');
    loadRatings();
  } catch(e) {
    toast(e.message || 'Could not flag rating.');
  } finally {
    btn.disabled = false;
  }
}

async function deleteRating(ratingId) {
  const confirmed = await showConfirmDialog({
    title: 'Delete Rating',
    desc: 'Permanently remove this rating? The subject\'s average score will be recalculated immediately.',
    confirmLabel: 'Delete',
    confirmClass: 'danger',
    reasonRequired: false,
  });
  if (confirmed === null) return;
  try {
    const data = await adminApi('delete_rating', { rating_id: ratingId }, 'POST');
    toast(data.message || 'Rating deleted.');
    loadRatings();
  } catch(e) {
    toast(e.message || 'Could not delete rating.');
  }
}

async function openSubjectRatings(subjectType, subjectId, subjectName) {
  const panel   = document.getElementById('subjectRatingsPanel');
  const overlay = document.getElementById('subjectRatingsPanelOverlay');
  const body    = document.getElementById('subjectRatingsPanelBody');
  const title   = document.getElementById('subjectRatingsPanelTitle');
  if (!panel) return;

  title.textContent = `Ratings for ${subjectName}`;
  body.innerHTML    = skeletonRows();
  panel.style.display   = '';
  overlay.style.display = '';
  panel.classList.add('open');

  try {
    const data     = await adminApi('get_subject_ratings', { subject_type: subjectType, subject_id: subjectId });
    const ratings  = data.ratings  || [];
    const avg      = Number(data.avg || 0);
    const breakdown = data.breakdown || {};

    let html = `<div style="background:var(--surface);border-radius:12px;padding:16px;margin-bottom:16px">`;
    html += `<div style="display:flex;align-items:center;gap:16px;margin-bottom:12px">`;
    html += `<div style="font-size:36px;font-weight:700;color:var(--gold,#f5b842)">${avg.toFixed(1)}</div>`;
    html += `<div>${starsHtml(Math.round(avg))}<div style="font-size:12px;color:var(--text-muted);margin-top:4px">${ratings.length} review${ratings.length !== 1 ? 's' : ''}</div></div>`;
    html += `</div>`;

    for (let star = 5; star >= 1; star--) {
      const count = Number(breakdown[star] || 0);
      const pct   = ratings.length ? Math.round((count / ratings.length) * 100) : 0;
      html += `<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;font-size:12px">
        <span style="width:40px;text-align:right;color:var(--text-muted)">${star}★</span>
        <div style="flex:1;height:8px;background:var(--surface-2);border-radius:4px;overflow:hidden">
          <div style="height:100%;width:${pct}%;background:var(--gold,#f5b842);border-radius:4px"></div>
        </div>
        <span style="width:28px;color:var(--text-muted)">${count}</span>
      </div>`;
    }
    html += `</div>`;

    if (ratings.length) {
      html += ratings.slice(0, 10).map(r => `
        <div style="border-bottom:1px solid var(--surface-2);padding:12px 0">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px">
            <div>
              <strong style="font-size:13px">${escapeHtml(r.reviewer_name || 'Anonymous')}</strong>
              <span style="font-size:11px;color:var(--text-muted);margin-left:8px">${escapeHtml(r.trip_ref || '#' + r.trip_id)}</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
              <span>${starsHtml(r.rating)}</span>
              <span style="font-size:11px;color:var(--text-muted)">${dateLabel(r.created_at)}</span>
              ${Number(r.flagged) ? '<span class="badge badge-warning" style="font-size:10px">Flagged</span>' : ''}
            </div>
          </div>
          ${r.comment ? `<div style="font-size:13px;color:var(--text-secondary)">${escapeHtml(r.comment)}</div>` : '<div style="font-size:12px;color:var(--text-muted);font-style:italic">No comment left</div>'}
        </div>`).join('');
      if (ratings.length > 10) {
        html += `<div style="text-align:center;padding:12px;font-size:12px;color:var(--text-muted)">Showing 10 most recent of ${ratings.length} total reviews</div>`;
      }
    } else {
      html += emptyState('⭐', 'No ratings yet', 'This person has not been rated yet.');
    }

    body.innerHTML = html;
  } catch(e) {
    body.innerHTML = emptyState('⚠️', 'Could not load ratings', escapeHtml(e.message));
  }
}

function closeSubjectRatingsPanel() {
  const panel   = document.getElementById('subjectRatingsPanel');
  const overlay = document.getElementById('subjectRatingsPanelOverlay');
  if (panel)   { panel.classList.remove('open'); panel.style.display = 'none'; }
  if (overlay) { overlay.style.display = 'none'; }
}

// ─── Campaign Management ──────────────────────────────────────────────────────

const campState = { page: 1, per_page: 20 };

async function loadCampaigns(page = campState.page) {
  campState.page = page;
  const status = document.getElementById('campStatusFilter')?.value || 'all';
  const list   = document.getElementById('campList');
  if (!list) return;
  list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted)">Loading…</div>';

  try {
    const data = await adminApi('get_campaigns', { status, page, per_page: campState.per_page });
    const campaigns = data.campaigns || [];

    // Update metric cards when loading all-statuses page 1
    if (status === 'all' && page === 1) {
      const active    = campaigns.filter(c => c.status === 'active');
      const totalPaid = campaigns.reduce((s, c) => s + Number(c.total_bonus_paid || 0), 0);
      const enrolled  = campaigns.reduce((s, c) => s + Number(c.enrolled_drivers || 0), 0);
      const completed = campaigns.reduce((s, c) => s + Number(c.completed_drivers || 0), 0);
      const totalEnrolled = campaigns.reduce((s, c) => s + Number(c.enrolled_drivers || 0), 0);
      const pct = totalEnrolled > 0 ? Math.round((completed / totalEnrolled) * 100) : 0;
      const _e = id => document.getElementById(id);
      if(_e('campMetricActive'))    _e('campMetricActive').textContent    = active.length;
      if(_e('campMetricBonusPaid')) _e('campMetricBonusPaid').textContent = formatMoney(totalPaid);
      if(_e('campMetricEnrolled'))  _e('campMetricEnrolled').textContent  = enrolled;
      if(_e('campMetricCompletion'))_e('campMetricCompletion').textContent = pct + '%';
    }

    if (!campaigns.length) {
      list.innerHTML = emptyState('🏁', 'No campaigns yet', 'Click "Create Campaign" to set up your first driver incentive.');
      document.getElementById('campPagination').innerHTML = '';
      return;
    }

    list.innerHTML = `
      <table class="data-table" style="width:100%">
        <thead><tr>
          <th>Title</th><th>Status</th><th>Target</th><th>Bonus</th>
          <th>Enrolled</th><th>Completed</th><th>Bonus Paid</th><th>Ends</th><th>Actions</th>
        </tr></thead>
        <tbody>${campaigns.map(c => {
          const statusClass = c.status === 'active' ? 'badge-success' : c.status === 'completed' ? 'badge-info' : 'badge-warning';
          const enrolled    = Number(c.enrolled_drivers || 0);
          const completed   = Number(c.completed_drivers || 0);
          const pct         = enrolled > 0 ? Math.round((completed / enrolled) * 100) : 0;
          return `<tr>
            <td style="font-weight:600">${escapeHtml(c.title)}</td>
            <td><span class="badge ${statusClass}">${escapeHtml(c.status)}</span></td>
            <td>${Number(c.target_trips).toLocaleString()} trips</td>
            <td>${formatMoney(c.bonus_amount)}</td>
            <td>${enrolled}</td>
            <td>${completed} <span style="color:var(--text-muted);font-size:11px">(${pct}%)</span></td>
            <td>${formatMoney(c.total_bonus_paid)}</td>
            <td style="font-size:12px;color:var(--text-secondary)">${dateLabel(c.end_time)}</td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                <button class="btn-sm btn-view" onclick="openCampaignLeaderboard(${Number(c.id)})">Leaderboard</button>
                ${c.status !== 'completed' ? `<button class="btn-sm" style="background:rgba(74,158,255,0.1);color:var(--info)" onclick="openEditCampaignModal(${Number(c.id)})">Edit</button>` : ''}
                ${c.status === 'active' ? `<button class="btn-sm btn-suspend" onclick="deactivateCampaign(${Number(c.id)}, '${escapeHtml(c.title).replace(/'/g,"\\'")}')">Deactivate</button>` : ''}
              </div>
            </td>
          </tr>`;
        }).join('')}</tbody>
      </table>`;

    // Pagination
    renderPagination('campPagination', campState, data.total, 'loadCampaigns');
  } catch(e) {
    list.innerHTML = emptyState('⚠️', 'Could not load campaigns', escapeHtml(e.message));
  }
}

async function openCampaignLeaderboard(campaignId) {
  const card    = document.getElementById('campLeaderboardCard');
  const title   = document.getElementById('campLeaderboardTitle');
  const content = document.getElementById('campLeaderboardContent');
  card.style.display = 'block';
  title.textContent  = 'Loading leaderboard…';
  content.innerHTML  = '<div style="text-align:center;color:var(--text-muted);padding:20px">Loading…</div>';
  card.scrollIntoView({ behavior: 'smooth', block: 'start' });

  try {
    const data       = await adminApi('get_campaign_leaderboard', { campaign_id: campaignId });
    const campaign   = data.campaign;
    const leaderboard = data.leaderboard || [];
    title.textContent = `Leaderboard: ${campaign.title}`;

    if (!leaderboard.length) {
      content.innerHTML = emptyState('🏁', 'No drivers yet', 'No driver has completed a trip during this campaign window yet.');
      return;
    }

    content.innerHTML = `
      <div style="margin-bottom:16px;font-size:13px;color:var(--text-secondary)">
        Target: <strong>${Number(campaign.target_trips).toLocaleString()} trips</strong> &nbsp;·&nbsp;
        Window: ${dateLabel(campaign.start_time)} → ${dateLabel(campaign.end_time)}
      </div>
      <table class="data-table" style="width:100%">
        <thead><tr><th>#</th><th>Driver</th><th>Vehicle</th><th>Trips Done</th><th>Progress</th><th>Bonus</th></tr></thead>
        <tbody>${leaderboard.map((d, i) => `<tr>
          <td style="font-weight:700;color:${i===0?'var(--warning)':i===1?'var(--text-secondary)':i===2?'#cd7f32':'var(--text-muted)'}">${i+1}</td>
          <td style="font-weight:600">${escapeHtml(d.full_name || 'Unknown')}</td>
          <td style="font-size:12px;color:var(--text-secondary)">${escapeHtml(vehicleLabel ? vehicleLabel(d.vehicle_type) : d.vehicle_type||'')}</td>
          <td>${Number(d.trips_completed).toLocaleString()} / ${Number(d.target_trips).toLocaleString()}</td>
          <td style="min-width:120px">
            <div style="background:var(--surface);border-radius:4px;height:8px;overflow:hidden">
              <div style="background:${d.bonus_earned?'var(--success)':'var(--info)'};height:100%;width:${d.progress_pct}%;border-radius:4px;transition:width 0.4s"></div>
            </div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px">${d.progress_pct}%</div>
          </td>
          <td>${d.bonus_earned ? '<span class="badge badge-success">Earned ✓</span>' : '<span style="color:var(--text-muted);font-size:12px">Pending</span>'}</td>
        </tr>`).join('')}</tbody>
      </table>`;
  } catch(e) {
    content.innerHTML = emptyState('⚠️', 'Could not load leaderboard', escapeHtml(e.message));
  }
}

function closeCampaignLeaderboard() {
  document.getElementById('campLeaderboardCard').style.display = 'none';
}

function openCreateCampaignModal() {
  document.getElementById('campEditId').value      = '';
  document.getElementById('campTitle').value       = '';
  document.getElementById('campDescription').value = '';
  document.getElementById('campTargetTrips').value = '';
  document.getElementById('campBonusAmount').value = '';
  document.getElementById('campStartTime').value   = '';
  document.getElementById('campEndTime').value     = '';
  document.getElementById('campVehicleTypes').value = 'all';
  document.getElementById('campaignModalTitle').textContent = 'Create Campaign';
  document.getElementById('campSaveBtn').textContent = 'Create Campaign';
  document.getElementById('campaignModalError').style.display = 'none';
  document.getElementById('campaignModalOverlay').style.display = 'flex';
}

async function openEditCampaignModal(campaignId) {
  try {
    const data     = await adminApi('get_campaign', { campaign_id: campaignId });
    const campaign = data.campaign;
    document.getElementById('campEditId').value       = campaign.id;
    document.getElementById('campTitle').value        = campaign.title || '';
    document.getElementById('campDescription').value  = campaign.description || '';
    document.getElementById('campTargetTrips').value  = campaign.target_trips || '';
    document.getElementById('campBonusAmount').value  = campaign.bonus_amount || '';
    document.getElementById('campStartTime').value    = (campaign.start_time || '').replace(' ', 'T').slice(0, 16);
    document.getElementById('campEndTime').value      = (campaign.end_time || '').replace(' ', 'T').slice(0, 16);
    document.getElementById('campVehicleTypes').value = campaign.eligible_vehicle_types || 'all';
    document.getElementById('campaignModalTitle').textContent = 'Edit Campaign';
    document.getElementById('campSaveBtn').textContent = 'Save Changes';
    document.getElementById('campaignModalError').style.display = 'none';
    document.getElementById('campaignModalOverlay').style.display = 'flex';
  } catch(e) {
    toast('Could not load campaign: ' + e.message);
  }
}

function closeCampaignModal() {
  document.getElementById('campaignModalOverlay').style.display = 'none';
}

async function saveCampaign() {
  const errEl  = document.getElementById('campaignModalError');
  const saveBtn = document.getElementById('campSaveBtn');
  errEl.style.display = 'none';

  const editId      = document.getElementById('campEditId').value;
  const title       = document.getElementById('campTitle').value.trim();
  const description = document.getElementById('campDescription').value.trim();
  const target      = document.getElementById('campTargetTrips').value;
  const bonus       = document.getElementById('campBonusAmount').value;
  const start       = document.getElementById('campStartTime').value;
  const end         = document.getElementById('campEndTime').value;
  const vehicles    = document.getElementById('campVehicleTypes').value.trim() || 'all';

  if (!title || !target || !bonus || !start || !end) {
    errEl.textContent   = 'Please fill in all required fields.';
    errEl.style.display = 'block';
    return;
  }

  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving…';

  try {
    const params = { title, description, target_trips: target, bonus_amount: bonus, start_time: start, end_time: end, eligible_vehicle_types: vehicles };
    if (editId) {
      params.campaign_id = editId;
      await adminApi('update_campaign', params, 'POST');
      toast('Campaign updated successfully.');
    } else {
      await adminApi('create_campaign', params, 'POST');
      toast('Campaign created successfully.');
    }
    closeCampaignModal();
    loadCampaigns(1);
  } catch(e) {
    errEl.textContent   = e.message || 'Could not save campaign.';
    errEl.style.display = 'block';
  } finally {
    saveBtn.disabled    = false;
    saveBtn.textContent = editId ? 'Save Changes' : 'Create Campaign';
  }
}

async function deactivateCampaign(campaignId, campaignTitle) {
  if (!confirm(`Deactivate campaign "${campaignTitle}"? Drivers already enrolled will keep their progress but no new bonuses will be awarded.`)) return;
  try {
    await adminApi('deactivate_campaign', { campaign_id: campaignId }, 'POST');
    toast('Campaign deactivated.');
    loadCampaigns();
  } catch(e) {
    toast('Error: ' + e.message);
  }
}

// ─── TRIP STATUS CORRECTION ──────────────────────────────────────────────────

let _correctStatusTripId = 0;

function openCorrectStatusModal(tripId) {
  if (!tripId) { toast('No trip selected.'); return; }
  _correctStatusTripId = tripId;
  document.getElementById('correctStatusDesc').textContent =
    'Manually override the status for Trip #' + tripId + '. Both the customer and driver will be notified. A reason is required and will be permanently logged.';
  document.getElementById('correctStatusNew').value = '';
  document.getElementById('correctDispatchStatusNew').value = '';
  document.getElementById('correctStatusReason').value = '';
  document.getElementById('correctStatusModal').classList.add('open');
}

function closeCorrectStatusModal() {
  document.getElementById('correctStatusModal').classList.remove('open');
}

async function submitCorrectTripStatus() {
  const newStatus   = document.getElementById('correctStatusNew').value;
  const newDispatch = document.getElementById('correctDispatchStatusNew').value;
  const reason      = document.getElementById('correctStatusReason').value.trim();

  if (!reason)                   { toast('A reason is required — it is permanently logged.'); return; }
  if (!newStatus && !newDispatch){ toast('Please select at least one status to change.'); return; }

  const btn = document.querySelector('#correctStatusModal .btn-primary');
  const orig = btn?.textContent;
  if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

  try {
    const params = { trip_id: _correctStatusTripId, reason };
    if (newStatus)   params.new_status          = newStatus;
    if (newDispatch) params.new_dispatch_status = newDispatch;

    const data = await adminApi('correct_trip_status', params, 'POST');
    toast(data.message || 'Trip status corrected.');
    closeCorrectStatusModal();
    closeTripModal();
    if (document.getElementById('panel-trips')?.classList.contains('active')) loadTrips();
    if (document.getElementById('panel-ops')?.classList.contains('active'))   loadLiveOps();
  } catch(e) {
    toast(e.message || 'Could not correct trip status.');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = orig; }
  }
}

// ─── LIVE ALERTS FEED ────────────────────────────────────────────────────────

async function loadLiveAlerts() {
  const feed = document.getElementById('opsAlertFeed');
  if (!feed) return;
  try {
    const data = await adminApi('get_live_alerts');
    renderLiveAlerts(data.alerts || []);
  } catch(e) {
    feed.innerHTML = `<div style="padding:12px;font-size:12px;color:var(--danger)">Could not load alerts: ${escapeHtml(e.message)}</div>`;
  }
}

function renderLiveAlerts(alerts) {
  const feed  = document.getElementById('opsAlertFeed');
  const badge = document.getElementById('opsAlertCount');

  if (badge) {
    badge.textContent    = alerts.length > 0 ? String(alerts.length) : '';
    badge.style.display  = alerts.length > 0 ? 'inline-block' : 'none';
  }
  if (!feed) return;

  if (!alerts.length) {
    feed.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:12px">No active alerts — all looks good!</div>';
    return;
  }

  const severityColor = { critical: 'var(--danger)', high: '#f97316', medium: 'var(--warn)', low: 'var(--text-muted)' };
  const severityIcon  = { critical: '🚨', high: '⚠️', medium: '⏰', low: 'ℹ️' };
  const typeLabel     = {
    trip_stuck_searching:    'Stuck: Searching',
    trip_stuck_in_progress:  'Stuck: In Progress',
    sos_filed:               'SOS Filed',
    payment_proof_pending_long: 'Payment Pending',
    payout_failed:           'Payout Failed',
    dispute_escalated:       'Dispute Escalated',
  };

  feed.innerHTML = alerts.map(alert => {
    const color = severityColor[alert.severity] || 'var(--text-muted)';
    const icon  = severityIcon[alert.severity]  || 'ℹ️';
    const label = typeLabel[alert.alert_type]   || alert.alert_type;

    let actionBtn = '';
    if (alert.trip_id && alert.alert_type === 'trip_stuck_searching') {
      actionBtn = `<button class="btn-sm" style="background:rgba(239,68,68,.12);color:#dc2626;border:none" onclick="forceCancelFromAlert(${alert.trip_id})">Force Cancel</button>
                   <button class="btn-sm btn-view" onclick="openCorrectStatusModal(${alert.trip_id})">Correct Status</button>`;
    } else if (alert.trip_id) {
      actionBtn = `<button class="btn-sm btn-view" onclick="openTripDetailFromOps(${alert.trip_id})">View Trip</button>`;
    } else if (alert.ticket_id) {
      actionBtn = `<button class="btn-sm btn-view" onclick="showPanel('support')">View Ticket</button>`;
    } else if (alert.dispute_id) {
      actionBtn = `<button class="btn-sm btn-view" onclick="showPanel('disputes')">View Dispute</button>`;
    } else if (alert.payout_id) {
      actionBtn = `<button class="btn-sm btn-view" onclick="showPanel('payouts')">View Payouts</button>`;
    }

    return `<div style="padding:10px 14px;border-bottom:1px solid var(--surface-2)">
      <div style="display:flex;gap:8px;align-items:flex-start">
        <span style="font-size:14px;flex-shrink:0;margin-top:1px">${icon}</span>
        <div style="flex:1;min-width:0">
          <div style="font-size:11px;font-weight:700;color:${color};margin-bottom:2px;text-transform:uppercase;letter-spacing:.4px">${escapeHtml(label)}</div>
          <div style="font-size:11px;color:var(--text-secondary);line-height:1.4;word-break:break-word">${escapeHtml(alert.description)}</div>
          ${actionBtn ? `<div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap">${actionBtn}</div>` : ''}
        </div>
      </div>
    </div>`;
  }).join('');
}

async function forceCancelFromAlert(tripId) {
  const reason = await showConfirmDialog({
    title:          'Force Cancel Trip',
    desc:           'Force cancel trip #' + tripId + '? The customer and driver will be notified immediately.',
    reasonLabel:    'Reason for cancellation',
    reasonRequired: true,
    confirmLabel:   'Force Cancel',
  });
  if (reason === null) return;
  try {
    const data = await adminApi('admin_force_cancel_trip', { trip_id: tripId, reason }, 'POST');
    toast(data.message || 'Trip cancelled.');
    loadLiveOps();
  } catch(e) {
    toast(e.message || 'Could not cancel trip.');
  }
}

function openTripDetailFromOps(tripId) {
  openTripDetail('#' + tripId, 'Trip', '', '', '', 'Unknown', 'Active', tripId);
}

// ─── BULK OPERATIONS ─────────────────────────────────────────────────────────

const _selectedDriverIds   = new Set();
const _selectedCustomerIds = new Set();

function toggleDriverSelect(id, checked) {
  if (checked) _selectedDriverIds.add(id); else _selectedDriverIds.delete(id);
  updateDriverBulkBar();
}

function toggleDriverSelectAll(checked) {
  document.querySelectorAll('.driver-check').forEach(cb => {
    cb.checked = checked;
    const id = Number(cb.dataset.id);
    if (checked) _selectedDriverIds.add(id); else _selectedDriverIds.delete(id);
  });
  updateDriverBulkBar();
}

function clearDriverSelection() {
  _selectedDriverIds.clear();
  document.querySelectorAll('.driver-check').forEach(cb => cb.checked = false);
  const sa = document.getElementById('driverSelectAll');
  if (sa) sa.checked = false;
  updateDriverBulkBar();
}

function updateDriverBulkBar() {
  const bar   = document.getElementById('driverBulkBar');
  const count = document.getElementById('driverSelectedCount');
  if (bar)   bar.style.display   = _selectedDriverIds.size > 0 ? 'flex' : 'none';
  if (count) count.textContent   = _selectedDriverIds.size + ' selected';
}

async function executeBulkDriverAction(action) {
  if (!action) { toast('Please choose a bulk action first.'); return; }
  if (!_selectedDriverIds.size) { toast('No drivers selected.'); return; }
  const ids    = Array.from(_selectedDriverIds);
  let actionParams = {};

  if (action === 'suspend') {
    const reason = await showConfirmDialog({ title: `Suspend ${ids.length} Driver(s)`, desc: `This will suspend ${ids.length} driver account(s). They will go offline immediately.`, reasonLabel: 'Reason (optional)', confirmLabel: 'Suspend All' });
    if (reason === null) return;
    actionParams.reason = reason;
  } else if (action === 'reinstate') {
    const reason = await showConfirmDialog({ title: `Reinstate ${ids.length} Driver(s)`, desc: `Restore ${ids.length} driver account(s) to active status.`, reasonLabel: 'Note (optional)', confirmLabel: 'Reinstate All' });
    if (reason === null) return;
    actionParams.reason = reason;
  } else if (action === 'send_notification') {
    const msg = prompt(`Message to send to ${ids.length} driver(s):`);
    if (!msg?.trim()) { toast('A message is required.'); return; }
    actionParams.message = msg.trim();
  }

  try {
    const data = await adminApi('bulk_action', {
      entity_type:   'driver',
      action,
      entity_ids:    JSON.stringify(ids),
      action_params: JSON.stringify(actionParams),
    }, 'POST');

    if (action === 'export' && data.export_data) {
      _downloadCsv(data.export_data, ['id','full_name','email','phone','status','kyc_status','vehicle_type','total_trips','created_at'], 'drivers_export.csv');
      toast(`Exported ${data.export_data.length} driver(s).`);
    } else {
      toast(data.message || 'Bulk action complete.');
      clearDriverSelection();
      loadDrivers();
    }
  } catch(e) {
    toast(e.message || 'Bulk action failed.');
  }
}

function toggleCustomerSelect(id, checked) {
  if (checked) _selectedCustomerIds.add(id); else _selectedCustomerIds.delete(id);
  updateCustomerBulkBar();
}

function toggleCustomerSelectAll(checked) {
  document.querySelectorAll('.customer-check').forEach(cb => {
    cb.checked = checked;
    const id = Number(cb.dataset.id);
    if (checked) _selectedCustomerIds.add(id); else _selectedCustomerIds.delete(id);
  });
  updateCustomerBulkBar();
}

function clearCustomerSelection() {
  _selectedCustomerIds.clear();
  document.querySelectorAll('.customer-check').forEach(cb => cb.checked = false);
  const sa = document.getElementById('customerSelectAll');
  if (sa) sa.checked = false;
  updateCustomerBulkBar();
}

function updateCustomerBulkBar() {
  const bar   = document.getElementById('customerBulkBar');
  const count = document.getElementById('customerSelectedCount');
  if (bar)   bar.style.display = _selectedCustomerIds.size > 0 ? 'flex' : 'none';
  if (count) count.textContent = _selectedCustomerIds.size + ' selected';
}

async function executeBulkCustomerAction(action) {
  if (!action) { toast('Please choose a bulk action first.'); return; }
  if (!_selectedCustomerIds.size) { toast('No customers selected.'); return; }
  const ids    = Array.from(_selectedCustomerIds);
  let actionParams = {};

  if (action === 'suspend') {
    const reason = await showConfirmDialog({ title: `Suspend ${ids.length} Customer(s)`, desc: `Suspend ${ids.length} customer account(s). They will be unable to book trips.`, reasonLabel: 'Reason (optional)', confirmLabel: 'Suspend All' });
    if (reason === null) return;
    actionParams.reason = reason;
  } else if (action === 'reinstate') {
    const reason = await showConfirmDialog({ title: `Reinstate ${ids.length} Customer(s)`, desc: `Restore ${ids.length} customer account(s) to active status.`, reasonLabel: 'Note (optional)', confirmLabel: 'Reinstate All' });
    if (reason === null) return;
    actionParams.reason = reason;
  } else if (action === 'send_notification') {
    const msg = prompt(`Message to send to ${ids.length} customer(s):`);
    if (!msg?.trim()) { toast('A message is required.'); return; }
    actionParams.message = msg.trim();
  }

  try {
    const data = await adminApi('bulk_action', {
      entity_type:   'customer',
      action,
      entity_ids:    JSON.stringify(ids),
      action_params: JSON.stringify(actionParams),
    }, 'POST');

    if (action === 'export' && data.export_data) {
      _downloadCsv(data.export_data, ['id','full_name','email','phone','status','email_verified','created_at'], 'customers_export.csv');
      toast(`Exported ${data.export_data.length} customer(s).`);
    } else {
      toast(data.message || 'Bulk action complete.');
      clearCustomerSelection();
      loadCustomers();
    }
  } catch(e) {
    toast(e.message || 'Bulk action failed.');
  }
}

function _downloadCsv(rows, fields, filename) {
  const header = fields.map(f => '"' + f + '"').join(',');
  const body   = rows.map(r => fields.map(f => '"' + String(r[f] ?? '').replace(/"/g, '""') + '"').join(',')).join('\n');
  const a      = document.createElement('a');
  a.href       = 'data:text/csv;charset=utf-8,' + encodeURIComponent(header + '\n' + body);
  a.download   = filename;
  a.click();
}

// ─── SUPPLY & DEMAND HEATMAP ─────────────────────────────────────────────────

async function loadDemandHeatmap() {
  const container = document.getElementById('opsHeatmapZones');
  if (!container) return;
  container.innerHTML = '<div style="padding:16px;color:var(--text-muted);font-size:12px">Loading zone data…</div>';
  try {
    const data = await adminApi('get_demand_supply_heatmap');
    renderDemandHeatmap(data.zones || []);
  } catch(e) {
    container.innerHTML = `<div style="padding:16px;font-size:12px;color:var(--danger)">Could not load heatmap: ${escapeHtml(e.message)}</div>`;
  }
}

function renderDemandHeatmap(zones) {
  const container = document.getElementById('opsHeatmapZones');
  if (!container) return;

  if (!zones.length) {
    container.innerHTML = '<div style="padding:16px;color:var(--text-muted);font-size:12px">No active zones configured. Add zones in Settings → Zones to see supply and demand data here.</div>';
    return;
  }

  const levelColor = { low: 'var(--success)', medium: 'var(--warn)', high: 'var(--danger)' };
  const levelLabel = { low: 'Supply > Demand', medium: 'Balanced', high: 'Demand > Supply' };

  container.innerHTML = zones.map(z => {
    const color = levelColor[z.demand_level] || 'var(--text-muted)';
    const label = levelLabel[z.demand_level] || z.demand_level;
    const drivers  = z.active_drivers_count;
    const requests = z.quote_requests_last_30min;
    return `<div class="list-item" style="align-items:center;gap:10px">
      <div style="width:12px;height:12px;border-radius:50%;background:${color};flex-shrink:0;box-shadow:0 0 8px ${color}66"></div>
      <div class="item-info">
        <div class="item-name" style="font-size:13px">${escapeHtml(z.name)}</div>
        <div class="item-meta">${drivers} driver${drivers !== 1 ? 's' : ''} online · ${requests} request${requests !== 1 ? 's' : ''} (last 30 min) · ${escapeHtml(label)}</div>
      </div>
      <span class="badge" style="background:${color}20;color:${color};border:1px solid ${color}40;white-space:nowrap">${escapeHtml(z.demand_level.charAt(0).toUpperCase() + z.demand_level.slice(1))}</span>
    </div>`;
  }).join('');
}

// ── WALLET TOP-UPS ────────────────────────────────────────────────────────────

let topupSearchTimer;
function queueTopupSearch(v){ clearTimeout(topupSearchTimer); topupSearchTimer = setTimeout(()=>loadWalletTopups(1), 350); }

async function loadWalletTopups(page=1){
  pageState.walletTopups.page = page;
  const list = document.getElementById('topupList');
  if(!list) return;
  list.innerHTML = skeletonRows(5);
  const status = document.getElementById('topupStatus')?.value || 'pending';
  const search = document.getElementById('topupSearch')?.value || '';
  try {
    const data = await adminApi('get_wallet_topups', { page, per_page:20, status, search });
    const m = data.metrics || {};
    const setEl = (id, val) => { const el=document.getElementById(id); if(el) el.textContent=val; };
    setEl('topupPendingAmount',       formatMoney(m.pending_amount || 0));
    setEl('topupPendingCount',        (m.pending_count||0) + ' request' + ((m.pending_count||0)===1?'':'s'));
    setEl('topupApprovedTodayAmount', formatMoney(m.approved_today_amount || 0));
    setEl('topupApprovedTodayCount',  (m.approved_today||0) + ' approved');
    setEl('topupWeekAmount',          formatMoney(m.week_amount || 0));
    setEl('topupRejectedCount',       m.rejected_count || 0);
    const badge = document.getElementById('topup-badge');
    if(badge) badge.textContent = m.pending_count || 0;
    const requests = data.requests || [];
    list.innerHTML = requests.length
      ? requests.map(renderWalletTopupItem).join('')
      : emptyState('🏦', 'No top-up requests', status==='pending' ? 'No pending transfer requests at the moment.' : 'No requests match the current filter.');
    renderPagination('topupPagination', pageState.walletTopups, data.total, 'loadWalletTopups');
  } catch(e) {
    list.innerHTML = emptyState('⚠️', 'Could not load', escapeHtml(e.message));
  }
}

function renderWalletTopupItem(r){
  const statusColors = { pending:'badge-warn', approved:'badge-success', rejected:'badge-danger' };
  const badge = `<span class="badge ${statusColors[r.status]||'badge-info'}" style="margin-left:4px">${escapeHtml(r.status)}</span>`;
  const isPending = r.status === 'pending';
  const proofBtn = r.proof_url
    ? `<button onclick="viewProofImage('${escapeHtml(r.proof_url)}','Transfer Receipt — ${escapeHtml(r.customer_name||'')} ₦${Number(r.amount).toLocaleString()}')" style="background:none;border:none;padding:0;font-size:12px;color:var(--info);font-weight:700;cursor:pointer;text-decoration:underline;margin-top:4px;display:block">View Receipt</button>`
    : '<span style="font-size:12px;color:var(--danger);margin-top:4px;display:block">No receipt uploaded</span>';
  const noteHtml = r.admin_notes
    ? `<div style="font-size:11px;color:var(--text-secondary);margin-top:4px;padding:4px 8px;background:var(--surface);border-radius:6px">Note: ${escapeHtml(r.admin_notes)}</div>`
    : '';
  return `<div class="list-item" style="align-items:flex-start">
    <div class="avatar" style="background:rgba(34,196,122,0.1);color:#22c47a;font-size:14px;flex-shrink:0">₦</div>
    <div class="item-info" style="flex:1;min-width:0">
      <div class="item-name">${escapeHtml(r.customer_name||'Customer')} · ${formatMoney(r.amount)} ${badge}</div>
      <div class="item-meta">${escapeHtml(r.customer_email||'')}${r.customer_phone?' · '+escapeHtml(r.customer_phone):''} · ${dateLabel(r.created_at)}</div>
      <div class="item-meta" style="margin-top:2px">Bank ref: <strong>${escapeHtml(r.bank_ref||'—')}</strong></div>
      ${proofBtn}${noteHtml}
    </div>
    <div class="item-actions" style="flex-shrink:0">
      ${isPending ? `<button class="btn-sm btn-approve" onclick="reviewWalletTopup(${r.id},'approved')">Approve</button><button class="btn-sm btn-reject" onclick="reviewWalletTopup(${r.id},'rejected')">Reject</button>` : ''}
    </div>
  </div>`;
}

async function reviewWalletTopup(id, action){
  if(action === 'rejected'){
    const reason = await showConfirmDialog({
      title: 'Reject Top-Up Request',
      desc: 'Reject this top-up request? The customer will not receive wallet credit.',
      reasonLabel: 'Reason for rejection (optional)',
      reasonRequired: false,
      confirmLabel: 'Reject Request',
      confirmClass: 'danger',
    });
    if(reason === null) return;
    try {
      const data = await adminApi('review_wallet_topup', { request_id:id, review_action:'rejected', admin_notes:reason }, 'POST');
      toast(data.message || 'Request rejected.');
      loadWalletTopups(pageState.walletTopups.page);
    } catch(e) { toast(e.message || 'Could not reject request.'); }
  } else {
    try {
      const data = await adminApi('review_wallet_topup', { request_id:id, review_action:'approved', admin_notes:'' }, 'POST');
      toast(data.message || 'Top-up approved!');
      loadWalletTopups(pageState.walletTopups.page);
    } catch(e) { toast(e.message || 'Could not approve top-up.'); }
  }
}
