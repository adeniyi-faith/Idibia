<!-- AUDIT LOG PANEL -->
<div class="panel" id="panel-audit-log">
  <div class="page-header">
    <h2 class="page-title">Audit Log</h2>
    <div class="page-sub">Security log of admin actions — who did what and when</div>
  </div>

  <!-- Filters -->
  <div class="settings-section" style="padding-bottom:16px">
    <div class="form-row" style="gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="min-width:160px;margin-bottom:0">
        <label class="form-label">Action</label>
        <input class="form-input" id="al-filter-action" type="text" placeholder="e.g. delete, update…">
      </div>
      <div class="form-group" style="min-width:160px;margin-bottom:0">
        <label class="form-label">Entity Type</label>
        <input class="form-input" id="al-filter-entity" type="text" placeholder="e.g. driver, customer…">
      </div>
      <div class="form-group" style="min-width:150px;margin-bottom:0">
        <label class="form-label">From</label>
        <input class="form-input" id="al-filter-from" type="date">
      </div>
      <div class="form-group" style="min-width:150px;margin-bottom:0">
        <label class="form-label">To</label>
        <input class="form-input" id="al-filter-to" type="date">
      </div>
      <div style="padding-bottom:1px">
        <button class="btn-primary" style="width:auto;padding:9px 18px" onclick="loadAuditLog(1)">Filter</button>
        <button class="scard-action" style="margin-left:8px" onclick="auditLogReset()">Reset</button>
      </div>
    </div>
  </div>

  <!-- Log List -->
  <div class="scard">
    <div class="scard-header">
      <h3>Activity Log</h3>
      <button class="scard-action" onclick="loadAuditLog(_alPage)">Refresh</button>
    </div>
    <div id="audit-log-list">
      <div class="list-item"><div class="item-info"><div class="item-name" style="color:var(--text-muted)">Loading…</div></div></div>
    </div>
    <div id="audit-log-pagination" style="padding:12px 16px;display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-muted);border-top:1px solid var(--surface-2)"></div>
  </div>
</div>

<script>
(function(){
  var _alPage = 1;
  window._alPage = 1;

  var ACTION_COLORS = {
    delete: '#ef4444', suspend: '#f97316', ban: '#f97316',
    create: '#22c55e', approve: '#22c55e', activate: '#22c55e',
    update: '#3b82f6', save: '#3b82f6', change: '#3b82f6',
    login: '#a855f7', logout: '#a855f7',
    send: '#06b6d4',
  };

  function actionBadge(action){
    var color = '#6b7280';
    var a = (action||'').toLowerCase();
    for(var k in ACTION_COLORS){
      if(a.indexOf(k) !== -1){ color = ACTION_COLORS[k]; break; }
    }
    return '<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:600;letter-spacing:.5px;background:'+color+'22;color:'+color+';text-transform:uppercase">'+escHtml(action||'—')+'</span>';
  }

  function escHtml(s){
    var d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
  }

  function fmtDate(s){
    if(!s) return '—';
    return new Date(s.replace(' ','T')+'Z').toLocaleString();
  }

  window.loadAuditLog = function(page){
    _alPage = page || 1;
    window._alPage = _alPage;
    var el   = document.getElementById('audit-log-list');
    var pag  = document.getElementById('audit-log-pagination');
    el.innerHTML = '<div class="list-item"><div class="item-info"><div class="item-name" style="color:var(--text-muted)">Loading…</div></div></div>';
    pag.innerHTML = '';

    var params = { page: _alPage, per_page: 25 };
    var action = document.getElementById('al-filter-action').value.trim();
    var entity = document.getElementById('al-filter-entity').value.trim();
    var from   = document.getElementById('al-filter-from').value;
    var to     = document.getElementById('al-filter-to').value;
    if(action) params.action_filter = action;
    if(entity) params.entity_type = entity;
    if(from)   params.date_from   = from;
    if(to)     params.date_to     = to;

    var qs = Object.keys(params).map(function(k){ return encodeURIComponent(k)+'='+encodeURIComponent(params[k]); }).join('&');

    fetch('/admin/api.php?action=get_audit_log&'+qs, { credentials: 'include' })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if(!d.success){
          el.innerHTML = '<div class="list-item"><div class="item-name" style="color:#f44">'+(d.data&&d.data.message ? escHtml(d.data.message) : 'Failed to load.')+'</div></div>';
          return;
        }
        var entries = d.data.logs || [];
        if(!entries.length){
          el.innerHTML = '<div class="list-item"><div class="item-info"><div class="item-name" style="color:var(--text-muted)">No log entries found.</div></div></div>';
          return;
        }
        el.innerHTML = entries.map(function(e){
          var meta = '';
          if(e.metadata){
            try{
              var m = typeof e.metadata === 'string' ? JSON.parse(e.metadata) : e.metadata;
              var parts = Object.keys(m).slice(0,3).map(function(k){ return '<b>'+escHtml(k)+'</b>: '+escHtml(String(m[k]).slice(0,60)); });
              if(parts.length) meta = '<div style="font-size:11px;color:var(--text-muted);margin-top:3px">'+parts.join(' · ')+'</div>';
            }catch(x){}
          }
          return '<div class="list-item" style="flex-direction:column;align-items:flex-start;gap:2px">'+
            '<div style="display:flex;align-items:center;justify-content:space-between;width:100%;flex-wrap:wrap;gap:6px">'+
              '<div style="display:flex;align-items:center;gap:8px">'+
                actionBadge(e.action)+
                '<span class="item-name" style="font-size:13px">'+
                  (e.entity_type ? escHtml(e.entity_type)+(e.entity_id ? ' #'+escHtml(e.entity_id) : '') : '—')+
                '</span>'+
              '</div>'+
              '<div style="font-size:11px;color:var(--text-muted);text-align:right">'+
                '<span>'+(e.admin_name ? escHtml(e.admin_name) : 'Admin #'+escHtml(e.admin_id))+'</span>'+
                ' &middot; '+
                '<span>'+fmtDate(e.created_at)+'</span>'+
              '</div>'+
            '</div>'+
            meta+
          '</div>';
        }).join('');

        var total   = parseInt(d.data.total || 0, 10);
        var perPage = parseInt(d.data.per_page || 25, 10);
        var pages   = Math.max(1, Math.ceil(total / perPage));
        pag.innerHTML =
          '<span>Page '+_alPage+' of '+pages+' &middot; '+total+' total</span> '+
          '<button style="padding:4px 10px;border-radius:6px;border:1px solid var(--surface-2);background:none;font-size:12px;cursor:pointer" '+(_alPage<=1?'disabled':'')+' onclick="loadAuditLog('+(_alPage-1)+')">Prev</button> '+
          '<button style="padding:4px 10px;border-radius:6px;border:1px solid var(--surface-2);background:none;font-size:12px;cursor:pointer" '+(_alPage>=pages?'disabled':'')+' onclick="loadAuditLog('+(_alPage+1)+')">Next</button>';
      })
      .catch(function(){
        el.innerHTML = '<div class="list-item"><div class="item-name" style="color:#f44">Network error — could not load audit log.</div></div>';
      });
  };

  window.auditLogReset = function(){
    document.getElementById('al-filter-action').value = '';
    document.getElementById('al-filter-entity').value = '';
    document.getElementById('al-filter-from').value   = '';
    document.getElementById('al-filter-to').value     = '';
    loadAuditLog(1);
  };

  // Auto-load when panel becomes visible
  var obs = new MutationObserver(function(muts){
    muts.forEach(function(m){
      if(m.target.classList.contains('active')) loadAuditLog(1);
    });
  });
  var panel = document.getElementById('panel-audit-log');
  if(panel) obs.observe(panel, { attributes: true, attributeFilter: ['class'] });
})();
</script>
