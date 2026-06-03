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
function renderDisputeItem(d){ const status=d.status||'open'; const title='#D-'+String(d.id).padStart(4,'0')+' · '+(d.category||'Dispute'); const meta=status==='resolved'?`Resolved · ${formatMoney(d.refund_amount)} refunded · ${d.resolution||''}`:`Customer: ${d.customer_name||'Unknown'} · Driver: ${d.driver_name||'Unassigned'} · ${dateLabel(d.created_at)}`; return `<div class="list-item" data-dispute="${escapeHtml(status)}"><div class="avatar" style="background:rgba(232,72,74,0.1);color:var(--danger);font-size:11px">${status==='resolved'?'✓':(status==='escalated'?'🔴':'!')}</div><div class="item-info"><div class="item-name">${escapeHtml(title)}</div><div class="item-meta">${escapeHtml(meta)}</div></div><div class="item-actions"><button class="btn-sm ${status==='escalated'?'btn-reject':'btn-view'}" onclick="openDisputeModal('${escapeHtml(title).replace(/'/g,'&#39;')}', ${Number(d.id)}, '${escapeHtml(status)}')">${status==='resolved'?'View':'Handle'}</button></div></div>`; }
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

    document.getElementById('driverModalBody').innerHTML = html;
    document.getElementById('driverModal').classList.add('open');
  } catch(e) {
    toast('Could not load driver: ' + e.message);
  }
}
function closeDriverModal() {
  document.getElementById('driverModal').classList.remove('open');
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

    document.getElementById('paymentModalBody').innerHTML = html;
    document.getElementById('paymentModal').classList.add('open');
  } catch(e) {
    toast('Could not load payment: ' + e.message);
  }
}

function closePaymentModal() {
  document.getElementById('paymentModal').classList.remove('open');
}


function openTripDetail(id,cat,from,to,fare,driver,status){
  document.getElementById('tripModalTitle').textContent='Trip '+id;
  document.getElementById('tripModalBody').innerHTML=`<div class="metrics-grid" style="margin-bottom:16px"><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">CATEGORY</div><div style="font-size:13px;font-weight:600">${cat}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">STATUS</div><div style="font-size:13px;font-weight:600">${status}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">PICKUP</div><div style="font-size:13px;font-weight:600">${from}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">DROP-OFF</div><div style="font-size:13px;font-weight:600">${to}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">FARE</div><div style="font-size:13px;font-weight:600">${fare}</div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted)">DRIVER</div><div style="font-size:13px;font-weight:600">${driver}</div></div></div><div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:6px">PAYMENT BREAKDOWN</div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Base fare</span><span>${fare}</span></div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;color:var(--success)"><span>Platform comm.</span><span>-20%</span></div><div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;margin-top:6px;padding-top:6px;border-top:1px solid var(--surface-2)"><span>Driver payout</span><span style="color:var(--success)">80%</span></div></div>`;
  document.getElementById('tripModal').classList.add('open');
}
function closeTripModal(){document.getElementById('tripModal').classList.remove('open');}
function openDisputeModal(desc, disputeId=0, status='open'){
  currentDisputeId=Number(disputeId||0);
  document.getElementById('modalDesc').textContent=desc;

  const actionArea = document.getElementById('disputeActionArea');
  const resolveBtn = document.getElementById('resolveDisputeBtn');

  if (status === 'resolved') {
    if (actionArea) actionArea.style.display = 'none';
    if (resolveBtn) resolveBtn.style.display = 'none';
  } else {
    if (actionArea) actionArea.style.display = 'block';
    if (resolveBtn) resolveBtn.style.display = 'inline-block';
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

// --- ADMIN USERS (RBAC) ---

let currentRoles = [];
let rolePermissionsMap = {};
let currentUserOverrides = {};

async function loadAdminUsers() {
    const search = document.getElementById('adminUserSearch').value;
    const tbody = document.getElementById('adminUsersTbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="5" class="loading-state">Loading users...</td></tr>';

    try {
        const formData = new FormData();
        formData.append('action', 'list_admin_users');

        const res = await fetch(ADMIN_API_URL, { method: 'POST', body: formData });
        const json = await res.json();

        if ( ! json.success ) {
            tbody.innerHTML = `<tr><td colspan="5" class="empty-state">Error loading users: ${json.data?.message || 'Unknown error'}</td></tr>`;
            return;
        }

        const users = json.data;
        if ( users.length === 0 ) {
            tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No admin users found.</td></tr>';
            return;
        }

        let html = '';
        users.forEach( u => {
            if ( search && !u.full_name.toLowerCase().includes(search.toLowerCase()) && !u.email.toLowerCase().includes(search.toLowerCase()) ) {
                return;
            }
            const avatar = u.avatar_path ? `<img src="${u.avatar_path}" class="avatar">` : `<div class="avatar placeholder"></div>`;
            const statusClass = u.status === 'active' ? 'success' : 'danger';

            let overridesCount = 0;
            if (u.overrides) {
                 overridesCount = Object.keys(u.overrides).length;
            }

            html += `
            <tr>
                <td>
                    <div class="user-cell">
                        ${avatar}
                        <div>
                            <div class="strong">${u.full_name}</div>
                            <div class="sub-text">${u.email}</div>
                        </div>
                    </div>
                </td>
                <td><span class="badge neutral">${u.role_name}</span> ${overridesCount > 0 ? `<span style="font-size:10px;color:var(--text-light)">(+${overridesCount} overrides)</span>` : ''}</td>
                <td><span class="status-dot ${statusClass}"></span>${u.status}</td>
                <td>${u.last_login ? new Date(u.last_login).toLocaleString() : 'Never'}</td>
                <td>
                    <button class="btn-secondary" style="padding:4px 8px; font-size:12px" onclick='editAdminUser(${JSON.stringify(u).replace(/'/g, "&apos;")})'>Edit</button>
                    ${u.role_name !== 'Super Admin' ? `<button class="btn-secondary" style="padding:4px 8px; font-size:12px; color:var(--danger); border-color:var(--danger)" onclick="toggleAdminUserStatus(${u.id}, '${u.status}')">${u.status === 'active' ? 'Suspend' : 'Activate'}</button>` : ''}
                </td>
            </tr>`;
        });

        tbody.innerHTML = html || '<tr><td colspan="5" class="empty-state">No matches found.</td></tr>';

    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Network error loading users.</td></tr>';
    }
}

async function loadRolesForDropdown() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_roles');

        const res = await fetch(ADMIN_API_URL, { method: 'POST', body: formData });
        const json = await res.json();

        if ( json.success ) {
            currentRoles = json.data;
            rolePermissionsMap = {};
            const select = document.getElementById('adminUserRole');
            if (!select) return;
            select.innerHTML = '<option value="">Select a role...</option>';

            currentRoles.forEach(r => {
                rolePermissionsMap[r.id] = r.permissions || [];
                select.innerHTML += `<option value="${r.id}">${r.name}</option>`;
            });
        }
    } catch(e) {
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

function renderPermissionToggles() {
    const roleId = document.getElementById('adminUserRole')?.value;
    const container = document.getElementById('adminUserPermissionsContainer');
    if (!container) return;

    if ( ! roleId ) {
        container.innerHTML = '<div class="empty-state">Select a role to see permissions.</div>';
        return;
    }

    const role = currentRoles.find(r => r.id == roleId);
    if ( role ) {
        document.getElementById('adminUserRoleDesc').innerText = role.description;
    }

    const defaultPerms = rolePermissionsMap[roleId] || [];

    let html = '<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">';

    allPermissions.forEach(p => {
        const isDefault = defaultPerms.includes(p.key);
        const isGranted = currentUserOverrides[p.key] !== undefined ? currentUserOverrides[p.key] : isDefault;
        const isOverridden = isGranted !== isDefault;

        // Disable toggle if current user doesn't have the permission themselves
        const canEdit = iHavePermission(p.key);
        const disabledAttr = !canEdit ? 'disabled' : '';
        const opacityStyle = !canEdit ? 'opacity:0.5; cursor:not-allowed;' : '';

        html += `
        <label class="toggle-row" style="margin-bottom:8px; padding:8px; background:var(--bg-secondary); border-radius:8px; border:1px solid ${isOverridden ? 'var(--primary)' : 'transparent'}; ${opacityStyle}" ${!canEdit ? 'title="You lack this permission"' : ''}>
            <span style="display:flex;align-items:center;">
                ${p.label}
                ${isOverridden ? '<span style="display:inline-block;width:6px;height:6px;background:var(--primary);border-radius:50%;margin-left:8px;"></span>' : ''}
            </span>
            <input type="checkbox" id="perm_${p.key}" ${isGranted ? 'checked' : ''} ${disabledAttr} onchange="updateOverride('${p.key}', this.checked, ${isDefault})">
            <span class="toggle-slider"></span>
        </label>`;
    });

    html += '</div>';
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
    document.getElementById('adminUserRoleDesc').innerText = 'Select a role to see default permissions.';
    document.getElementById('adminUserPasswordGroup').style.display = 'block';

    currentUserOverrides = {};
    document.getElementById('adminUserPermissionsContainer').innerHTML = '';

    document.getElementById('adminUserSlideTitle').innerText = 'Create Admin User';

    if ( currentRoles.length === 0 ) {
        loadRolesForDropdown();
    }

    document.getElementById('adminUserSlidePanel').classList.add('show');
}

function editAdminUser(u) {
    openAdminUserPanel();
    setTimeout(() => {
        document.getElementById('adminUserId').value = u.id;
        document.getElementById('adminUserFullName').value = u.full_name;
        document.getElementById('adminUserEmail').value = u.email;
        document.getElementById('adminUserPasswordGroup').style.display = 'none'; // Don't show password field on edit
        document.getElementById('adminUserRole').value = u.role_id;

        currentUserOverrides = u.overrides || {};

        document.getElementById('adminUserSlideTitle').innerText = 'Edit ' + u.full_name;
        renderPermissionToggles();
    }, 100); // Small delay to ensure roles are loaded if it's the first time
}

function closeAdminUserPanel() {
    document.getElementById('adminUserSlidePanel').classList.remove('show');
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
