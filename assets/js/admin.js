/* Login Script */
const loginForm = document.getElementById('adminLoginForm');
if (loginForm) {

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

const panels={overview:'Platform Overview',kyc:'KYC Review Queue',ops:'Live Operations',trips:'Deliveries',revenue:'Revenue Analytics',payouts:'Driver Payouts',drivers:'Drivers',users:'Customers',disputes:'Disputes',settings:'Settings'};
const subs={overview:'Live · '+new Date().toLocaleDateString(undefined,{weekday:'short',month:'short',day:'numeric',year:'numeric'}),kyc:'Applications awaiting review',ops:'Port Harcourt metro',trips:'All trips and tracking',revenue:'Finance analytics endpoint pending',payouts:'Earnings management',drivers:'Driver records from database',users:'Customer accounts from database',disputes:'Complaints & escalations',settings:'Platform configuration'};

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
  if(name === 'drivers') loadDrivers();
  if(name === 'users') loadCustomers();
  if(name === 'disputes') loadDisputes();
  if(name === 'settings') { loadPaymentSettings(); loadManualPayments(); }
  if(name === 'reconciliation') loadReconciliation();

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
const pageState = { trips:{page:1, per_page:10, search:'', status:'', category:''}, payouts:{page:1, per_page:10, search:'', status:'pending'}, disputes:{page:1, per_page:10, search:'', status:'all'}, drivers:{page:1, per_page:10, search:''}, customers:{page:1, per_page:10, search:''}, reconciliation:{page:1, per_page:10, search:'', status:'all', start_date:'', end_date:''} };
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
  if(method !== 'GET') {
    body.append('action', action);
    body.append('_nonce', window.ADMIN_API_NONCE || '');
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
  payload['_nonce'] = window.ADMIN_API_NONCE || '';
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
if (document.getElementById('app')) {
  loadKycQueue('under_review');
  loadDashboard();
  loadTrips();
  loadPayouts();
  loadDisputes();
  loadPaymentSettings();
  loadManualPayments();
}


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
    document.getElementById('kyc-badge').textContent=Number(data.kyc_pending||0).toLocaleString();
    document.getElementById('overviewCompletionRate').textContent=Number(data.completion_rate||0).toFixed(1)+'%';
    document.getElementById('overviewAvgPickup').textContent=Number(data.avg_pickup_time||0).toFixed(1)+'m';
    document.getElementById('overviewOpenDisputes').textContent=Number(data.open_disputes||0).toLocaleString();
    document.getElementById('dispute-badge').textContent=Number(data.open_disputes||0).toLocaleString();
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
function renderPayoutItem(p){ const failed=p.status==='failed'; return `<div class="list-item"><div class="avatar" style="background:${failed?'rgba(232,72,74,0.1)':'rgba(34,196,122,0.1)'};color:${failed?'var(--danger)':'var(--success)'}">${escapeHtml(initials(p.driver_name))}</div><div class="item-info"><div class="item-name">${escapeHtml(p.driver_name||('Driver #'+p.driver_id))}</div><div class="item-meta">${escapeHtml(p.bank_name||'Bank not set')} ${escapeHtml(maskAccount(p.account_number))} · ${Number(p.total_trips||0).toLocaleString()} trips · ${escapeHtml(formatStatusLabel(p.status))}</div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><div style="font-weight:700;font-size:14px;color:${failed?'var(--danger)':'inherit'}">${formatMoney(p.amount)}</div>${p.status==='paid'?'<span class="badge badge-success">Paid</span>':`<button class="btn-sm disabled-action" aria-disabled="true" onclick="showUnavailableFeature('Payout release', 'Payout release is disabled until a real transfer provider or manual transfer reference flow is connected.')">Release</button>`}</div></div>`; }
async function processPayout(id,status){ try{ const data=await adminApi('process_payout',{payout_id:id,status},'POST'); toast(data.message||'Payout updated'); loadPayouts(); }catch(e){ toast(e.message||'Could not update payout'); } }
function releaseVisiblePayouts(){ showUnavailableFeature('Bulk payout release', 'Payout release is disabled until a real transfer provider or manual transfer reference flow is connected.'); }

async function loadDrivers(page=pageState.drivers.page){
  const st=pageState.drivers; st.page=page;
  const list=document.getElementById('driverDirectory'); if(!list) return;
  list.innerHTML='<div class="loading-state">Loading drivers…</div>';
  try{
    const data=await adminApi('get_drivers', st);
    const drivers=data.drivers||[];
    document.getElementById('driversTotalCount').textContent=Number(data.total||0).toLocaleString();
    document.getElementById('driversOnlineCount').textContent=drivers.filter(d=>Number(d.is_online)===1).length.toLocaleString();
    document.getElementById('driversSuspendedCount').textContent=drivers.filter(d=>d.status==='suspended').length.toLocaleString();
    const ratings=drivers.map(d=>Number(d.rating||0)).filter(Boolean);
    document.getElementById('driversAvgRating').textContent=ratings.length?(ratings.reduce((a,b)=>a+b,0)/ratings.length).toFixed(1)+'★':'--';
    list.innerHTML=drivers.length?drivers.map(renderDriverDirectoryItem).join(''):'<div class="empty-state">No drivers match your filters.</div>';
    renderPagination('driverPagination', st, data.total, 'loadDrivers');
  }catch(e){ list.innerHTML='<div class="empty-state">Could not load drivers: '+escapeHtml(e.message)+'</div>'; }
}
function renderDriverDirectoryItem(driver){
  const status = driver.status || 'active';
  const meta = [vehicleIcon(driver.vehicle_type)+' '+vehicleLabel(driver.vehicle_type), driver.kyc_status ? 'KYC '+formatStatusLabel(driver.kyc_status) : null, Number(driver.is_online)===1?'Online':'Offline', (driver.rating?Number(driver.rating).toFixed(1)+'★':null), Number(driver.total_trips||0).toLocaleString()+' trips'].filter(Boolean).map(escapeHtml).join(' · ');
  return `<div class="list-item"><div class="avatar" style="background:rgba(74,158,255,0.1);color:var(--info)">${escapeHtml(initials(driver.full_name))}</div><div class="item-info"><div class="item-name">${escapeHtml(driver.full_name||'Unnamed driver')} · ${escapeHtml(status)}</div><div class="item-meta">${meta}</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="loadDriverDetail(${driver.id})">Profile</button>${status==='suspended'?'<span class="badge badge-danger">Suspended</span>':`<button class="btn-sm btn-suspend" onclick="suspendDriverFromDirectory(${Number(driver.id)}, '${escapeHtml(driver.full_name||'Driver').replace(/'/g,'&#39;')}')">Suspend</button>`}</div></div>`;
}
async function suspendDriverFromDirectory(driverId, name){
  if(!confirm('Suspend '+name+'? They will be notified and go offline immediately.')) return;
  try{ const data=await adminApi('suspend_driver',{driver_id:driverId},'POST'); toast(data.message||'Driver suspended.'); loadDrivers(); loadDashboard(); }
  catch(e){ toast(e.message||'Could not suspend driver.'); }
}
function queueDriverSearch(v){ clearTimeout(searchTimers.drivers); searchTimers.drivers=setTimeout(()=>{pageState.drivers.search=v; loadDrivers(1);},300); }
function setDriverStatusFilter(value){
  if(value !== 'all') showUnavailableFeature('Driver status filter', 'The current drivers endpoint does not support status filtering yet. Use search or open the KYC queue for approval states.');
  document.getElementById('driverStatusFilter').value='all';
}

async function loadCustomers(page=pageState.customers.page){
  const st=pageState.customers; st.page=page;
  const list=document.getElementById('customerDirectory'); if(!list) return;
  list.innerHTML='<div class="loading-state">Loading customers…</div>';
  try{
    const data=await adminApi('get_customers', st);
    const customers=data.customers||[];
    document.getElementById('customersTotalCount').textContent=Number(data.total||0).toLocaleString();
    document.getElementById('customersVerifiedCount').textContent=customers.filter(c=>Number(c.email_verified)===1).length.toLocaleString();
    document.getElementById('customersSuspendedCount').textContent=customers.filter(c=>c.status==='suspended').length.toLocaleString();
    list.innerHTML=customers.length?customers.map(renderCustomerDirectoryItem).join(''):'<div class="empty-state">No customers match your filters.</div>';
    renderPagination('customerPagination', st, data.total, 'loadCustomers');
  }catch(e){ list.innerHTML='<div class="empty-state">Could not load customers: '+escapeHtml(e.message)+'</div>'; }
}
function renderCustomerDirectoryItem(customer){
  const status = customer.status || 'active';
  const verified = Number(customer.email_verified)===1 ? 'Email verified' : 'Email pending';
  const meta = [customer.email||'No email', customer.phone||'No phone', verified, dateLabel(customer.created_at)].map(escapeHtml).join(' · ');
  return `<div class="list-item"><div class="avatar" style="background:rgba(245,200,66,0.12);color:var(--gold-dark)">${escapeHtml(initials(customer.full_name))}</div><div class="item-info"><div class="item-name">${escapeHtml(customer.full_name||'Unnamed customer')} · ${escapeHtml(status)}</div><div class="item-meta">${meta}</div></div><div class="item-actions"><button class="btn-sm btn-view" onclick="showUnavailableFeature('Customer profile', 'Customer detail screens need a dedicated customer-detail endpoint before opening full profiles.')">Profile</button></div></div>`;
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
  list.innerHTML = '<div class="loading-state">Loading reconciliation data…</div>';
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
    `).join('') : '<div class="empty-state">No payments match your filters.</div>';
    renderPagination('reconciliationPagination', st, data.total, 'loadReconciliation');
  } catch (e) {
    list.innerHTML = '<div class="empty-state">Could not load reconciliation data: ' + escapeHtml(e.message) + '</div>';
  }
}

async function loadDisputes(page=pageState.disputes.page){
  const st=pageState.disputes; st.page=page; const list=document.getElementById('disputeList'); if(!list) return; list.innerHTML='<div class="loading-state">Loading disputes…</div>';
  try{ const data=await adminApi('get_disputes', st); const rows=data.disputes||[]; document.getElementById('disputeTotalCount').textContent=Number(data.total||0).toLocaleString(); document.getElementById('disputeOpenCount').textContent=rows.filter(d=>d.status==='open'||d.status==='escalated').length.toLocaleString(); document.getElementById('disputeEscalatedCount').textContent=rows.filter(d=>d.status==='escalated').length.toLocaleString()+' escalated'; document.getElementById('disputeRefundAmount').textContent=formatMoney(rows.reduce((sum,d)=>sum+Number(d.refund_amount||0),0)); list.innerHTML=rows.length?rows.map(renderDisputeItem).join(''):'<div class="empty-state">No disputes match your filters.</div>'; renderPagination('disputePagination', st, data.total, 'loadDisputes'); }
  catch(e){ list.innerHTML='<div class="empty-state">Could not load disputes: '+escapeHtml(e.message)+'</div>'; }
}
function renderDisputeItem(d){ const status=d.status||'open'; const title='#D-'+String(d.id).padStart(4,'0')+' · '+(d.category||'Dispute'); const meta=status==='resolved'?`Resolved · ${formatMoney(d.refund_amount)} refunded · ${d.resolution||''}`:`Customer: ${d.customer_name||'Unknown'} · Driver: ${d.driver_name||'Unassigned'} · ${dateLabel(d.created_at)}`; return `<div class="list-item" data-dispute="${escapeHtml(status)}"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger);font-size:11px">${status==='resolved'?'✓':(status==='escalated'?'🔴':'!')}</div><div class="item-info"><div class="item-name">${escapeHtml(title)}</div><div class="item-meta">${escapeHtml(meta)}</div></div><div class="item-actions"><button class="btn-sm ${status==='escalated'?'btn-reject':'btn-view'}" onclick="openDisputeModal('${escapeHtml(title).replace(/'/g,'&#39;')}', ${Number(d.id)})">${status==='resolved'?'View':'Handle'}</button></div></div>`; }
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

async function loadDriverDetail(driverId) {
  try {
    const data = await adminApi('get_driver', { driver_id: driverId });
    const d = data.driver;
    if (!d) throw new Error('Driver not found');

    document.getElementById('driverModalTitle').textContent = `Driver: ${d.full_name || 'Unnamed'}`;
    document.getElementById('driverModalBody').innerHTML = `
      <div class="metrics-grid" style="margin-bottom:16px">
        <div style="background:var(--surface);border-radius:10px;padding:12px">
          <div style="font-size:10px;color:var(--text-muted)">STATUS</div>
          <div style="font-size:13px;font-weight:600">${escapeHtml(d.status || 'Active')}</div>
        </div>
        <div style="background:var(--surface);border-radius:10px;padding:12px">
          <div style="font-size:10px;color:var(--text-muted)">KYC</div>
          <div style="font-size:13px;font-weight:600">${escapeHtml(d.kyc_status || 'Pending')}</div>
        </div>
        <div style="background:var(--surface);border-radius:10px;padding:12px">
          <div style="font-size:10px;color:var(--text-muted)">RATING</div>
          <div style="font-size:13px;font-weight:600">${d.rating ? Number(d.rating).toFixed(1) + '★' : '--'}</div>
        </div>
        <div style="background:var(--surface);border-radius:10px;padding:12px">
          <div style="font-size:10px;color:var(--text-muted)">TRIPS</div>
          <div style="font-size:13px;font-weight:600">${Number(d.total_trips || 0).toLocaleString()}</div>
        </div>
      </div>
      <div style="background:var(--surface);border-radius:10px;padding:12px;margin-bottom:16px">
        <div style="font-size:10px;color:var(--text-muted);margin-bottom:6px">CONTACT INFO</div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Email</span><span>${escapeHtml(d.email || '--')}</span></div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Phone</span><span>${escapeHtml(d.phone || '--')}</span></div>
      </div>
      <div style="background:var(--surface);border-radius:10px;padding:12px;margin-bottom:16px">
        <div style="font-size:10px;color:var(--text-muted);margin-bottom:6px">VEHICLE INFO</div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Type</span><span>${escapeHtml(vehicleLabel(d.vehicle_type || 'bike'))}</span></div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Plate</span><span>${escapeHtml(d.plate_number || '--')}</span></div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Make/Model</span><span>${escapeHtml(d.vehicle_make || '--')} ${escapeHtml(d.vehicle_model || '')}</span></div>
      </div>
      <div style="background:var(--surface);border-radius:10px;padding:12px">
        <div style="font-size:10px;color:var(--text-muted);margin-bottom:6px">FINANCIALS</div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Wallet Balance</span><span style="color:var(--success)">${formatMoney(d.wallet_balance || 0)}</span></div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Bank Name</span><span>${escapeHtml(d.bank_name || 'Not set')}</span></div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Account No</span><span>${escapeHtml(maskAccount(d.account_number || '')) || '--'}</span></div>
      </div>
    `;
    document.getElementById('driverModal').classList.add('open');
  } catch (e) {
    toast('Could not load driver details: ' + escapeHtml(e.message));
  }
}

function closeDriverModal() {
  document.getElementById('driverModal').classList.remove('open');
}

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
  showUnavailableFeature('Notification read state', 'Notification inbox APIs are not connected yet, so read/unread state cannot be changed.');
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
    body.append('_nonce', window.ADMIN_API_NONCE || '');
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
