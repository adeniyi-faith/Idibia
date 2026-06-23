<!-- NOTIFICATIONS / BROADCAST PANEL -->
<div class="panel" id="panel-notifications">
  <div class="page-header">
    <h2 class="page-title">Notifications</h2>
    <div class="page-sub">Send broadcast messages to customers or drivers</div>
  </div>

  <!-- Broadcast Composer -->
  <div class="settings-section">
    <h4>New Broadcast</h4>
    <p style="font-size:13px;color:var(--text-muted);margin:0 0 16px">
      Write a message and choose who should receive it. Recipients get an in-app notification instantly, and optionally an email too.
    </p>

    <div class="form-group" style="margin-bottom:12px">
      <label class="form-label">Title</label>
      <input class="form-input" id="bc-title" type="text" placeholder="e.g. New rate update" maxlength="200">
    </div>

    <div class="form-group" style="margin-bottom:12px">
      <label class="form-label">Message</label>
      <textarea class="form-input" id="bc-body" rows="4" placeholder="Write your message here…" maxlength="1000" style="resize:vertical"></textarea>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Audience</label>
        <select class="form-input" id="bc-target-type" onchange="bcAudienceChanged()">
          <option value="all_both">Everyone (Customers &amp; Drivers)</option>
          <option value="all_customers">All Customers</option>
          <option value="all_drivers">All Drivers</option>
          <option value="online_drivers">Online Drivers Only</option>
          <option value="vehicle_type">Drivers by Vehicle Type</option>
          <option value="specific_ids">Specific Customer IDs</option>
        </select>
      </div>
      <div class="form-group" id="bc-value-wrap" style="display:none">
        <label class="form-label" id="bc-value-label">Value</label>
        <input class="form-input" id="bc-target-value" type="text" placeholder="e.g. bike">
      </div>
      <div class="form-group">
        <label class="form-label">Schedule (optional)</label>
        <input class="form-input" id="bc-scheduled-at" type="datetime-local">
      </div>
    </div>

    <div class="toggle-row" style="margin-bottom:16px">
      <div>
        <div class="toggle-label">Also send email</div>
        <div class="toggle-sub">Sends the same message via email in addition to in-app notification</div>
      </div>
      <button class="toggle" id="bc-send-email-toggle" onclick="this.classList.toggle('on')"></button>
    </div>

    <button class="btn-primary" style="width:auto;padding:10px 20px" onclick="sendBroadcast()">
      Send Broadcast
    </button>
    <span id="bc-status" style="margin-left:12px;font-size:13px;color:var(--text-muted)"></span>
  </div>

  <!-- Broadcast History -->
  <div class="settings-section">
    <div class="scard-header">
      <h4 style="border:0;margin:0;padding:0">Broadcast History</h4>
      <button class="scard-action" onclick="loadBroadcasts()">Refresh</button>
    </div>
    <div id="broadcasts-list" style="margin-top:12px">
      <div class="list-item"><div class="item-info"><div class="item-name" style="color:var(--text-muted)">Loading…</div></div></div>
    </div>
  </div>
</div>

<script>
(function(){
  function bcAudienceChanged(){
    var t  = document.getElementById('bc-target-type').value;
    var w  = document.getElementById('bc-value-wrap');
    var lbl= document.getElementById('bc-value-label');
    var inp= document.getElementById('bc-target-value');
    if(t==='vehicle_type'){
      w.style.display='';
      lbl.textContent='Vehicle type (bike/car/van/keke)';
      inp.placeholder='bike';
    } else if(t==='specific_ids'){
      w.style.display='';
      lbl.textContent='Customer IDs (comma-separated)';
      inp.placeholder='12,45,78';
    } else {
      w.style.display='none';
    }
  }
  window.bcAudienceChanged = bcAudienceChanged;

  window.sendBroadcast = function(){
    var title  = document.getElementById('bc-title').value.trim();
    var body   = document.getElementById('bc-body').value.trim();
    var ttype  = document.getElementById('bc-target-type').value;
    var tvalue = document.getElementById('bc-target-value').value.trim();
    var sched  = document.getElementById('bc-scheduled-at').value;
    var email  = document.getElementById('bc-send-email-toggle').classList.contains('on');
    var status = document.getElementById('bc-status');

    if(!title || !body){ alert('Please fill in the title and message.'); return; }

    status.textContent = 'Sending…';

    var fd = new FormData();
    fd.append('action','send_broadcast');
    fd.append('title', title);
    fd.append('body',  body);
    fd.append('target_type',  ttype);
    fd.append('target_value', tvalue);
    fd.append('send_email',   email ? '1' : '0');
    if(sched) fd.append('scheduled_at', sched);

    fetch('/admin/api.php', { method:'POST', body:fd, credentials:'include' })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if(d.success){
          status.style.color='#4caf50';
          status.textContent = d.data.message + ' (' + d.data.recipient_count + ' recipients)';
          document.getElementById('bc-title').value='';
          document.getElementById('bc-body').value='';
          document.getElementById('bc-scheduled-at').value='';
          loadBroadcasts();
        } else {
          status.style.color='#f44';
          status.textContent = d.data.message || 'Something went wrong.';
        }
      })
      .catch(function(){ status.style.color='#f44'; status.textContent='Network error.'; });
  };

  function fmtDate(s){
    if(!s) return '—';
    var d = new Date(s.replace(' ','T')+'Z');
    return d.toLocaleString();
  }

  function audienceLabel(type, value){
    var map = {
      all_both:      'Everyone (Customers & Drivers)',
      all_customers: 'All Customers',
      all_drivers:   'All Drivers',
      online_drivers:'Online Drivers',
      vehicle_type:  'Drivers — ' + value,
      specific_ids:  'Specific IDs: ' + value,
    };
    return map[type] || type;
  }

  window.loadBroadcasts = function(){
    var el = document.getElementById('broadcasts-list');
    el.innerHTML = '<div class="list-item"><div class="item-info"><div class="item-name" style="color:var(--text-muted)">Loading…</div></div></div>';
    fetch('/admin/api.php?action=get_broadcasts', { credentials:'include' })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if(!d.success || !d.data.broadcasts.length){
          el.innerHTML='<div class="list-item"><div class="item-info"><div class="item-name" style="color:var(--text-muted)">No broadcasts sent yet.</div></div></div>';
          return;
        }
        el.innerHTML = d.data.broadcasts.map(function(b){
          var badge = b.sent_at
            ? '<span style="color:#4caf50;font-size:11px">Sent</span>'
            : '<span style="color:#f90;font-size:11px">Scheduled</span>';
          return '<div class="list-item" style="flex-direction:column;align-items:flex-start;gap:4px">'+
            '<div style="display:flex;align-items:center;justify-content:space-between;width:100%">'+
              '<div class="item-name">'+escHtml(b.title)+'</div>'+
              badge+
            '</div>'+
            '<div class="item-sub" style="font-size:12px;color:var(--text-muted)">'+escHtml(b.body.substring(0,120))+(b.body.length>120?'…':'')+'</div>'+
            '<div style="display:flex;gap:16px;font-size:11px;color:var(--text-muted);margin-top:2px">'+
              '<span>Audience: '+audienceLabel(b.target_type, b.target_value)+'</span>'+
              '<span>'+b.recipient_count+' recipients</span>'+
              '<span>'+(b.sent_at ? 'Sent '+fmtDate(b.sent_at) : 'Scheduled '+fmtDate(b.scheduled_at))+'</span>'+
            '</div>'+
          '</div>';
        }).join('');
      })
      .catch(function(){ el.innerHTML='<div class="list-item"><div class="item-name" style="color:#f44">Failed to load.</div></div>'; });
  };

  function escHtml(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

  // Auto-load when panel becomes visible
  var obs = new MutationObserver(function(muts){
    muts.forEach(function(m){
      if(m.target.classList.contains('active')) loadBroadcasts();
    });
  });
  var panel = document.getElementById('panel-notifications');
  if(panel) obs.observe(panel, { attributes:true, attributeFilter:['class'] });

  loadBroadcasts();
})();
</script>
