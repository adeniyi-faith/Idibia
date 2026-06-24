<!-- AUDIT LOG PANEL -->
<div class="panel" id="panel-audit-log">
  <div class="page-header">
    <h2 class="page-title">Audit Log</h2>
    <div class="page-sub">Security log of all admin actions — who did what and when</div>
  </div>

  <!-- Filters -->
  <div class="settings-section" style="padding-bottom:12px">
    <div class="form-row" style="flex-wrap:wrap;gap:10px;align-items:flex-end">
      <div class="form-group" style="min-width:140px;margin-bottom:0">
        <label class="form-label">Action</label>
        <select class="form-input" id="al-action-filter" style="font-size:13px">
          <option value="">All actions</option>
          <option value="send_broadcast">send_broadcast</option>
          <option value="create">create</option>
          <option value="update">update</option>
          <option value="delete">delete</option>
          <option value="suspend">suspend</option>
          <option value="activate">activate</option>
          <option value="save_settings">save_settings</option>
          <option value="process_payout">process_payout</option>
          <option value="issue_refund">issue_refund</option>
          <option value="kyc_action">kyc_action</option>
        </select>
      </div>
      <div class="form-group" style="min-width:140px;margin-bottom:0">
        <label class="form-label">Entity type</label>
        <select class="form-input" id="al-entity-filter" style="font-size:13px">
          <option value="">All types</option>
          <option value="driver">driver</option>
          <option value="customer">customer</option>
          <option value="trip">trip</option>
          <option value="payout">payout</option>
          <option value="dispute">dispute</option>
          <option value="admin_user">admin_user</option>
          <option value="settings">settings</option>
          <option value="broadcast">broadcast</option>
        </select>
      </div>
      <div class="form-group" style="min-width:130px;margin-bottom:0">
        <label class="form-label">From date</label>
        <input class="form-input" id="al-date-from" type="date" style="font-size:13px">
      </div>
      <div class="form-group" style="min-width:130px;margin-bottom:0">
        <label class="form-label">To date</label>
        <input class="form-input" id="al-date-to" type="date" style="font-size:13px">
      </div>
      <div style="display:flex;gap:8px;margin-bottom:0;align-items:flex-end">
        <button class="btn-primary" style="padding:9px 16px;font-size:13px" onclick="loadAuditLog(1)">Filter</button>
        <button class="scard-action" style="padding:9px 16px;font-size:13px" onclick="resetAuditFilters()">Reset</button>
      </div>
    </div>
  </div>

  <!-- Log table -->
  <div class="settings-section" style="padding:0;overflow:hidden">
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="border-bottom:1px solid var(--surface-2)">
            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--text-muted);white-space:nowrap">When</th>
            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--text-muted)">Admin</th>
            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--text-muted)">Action</th>
            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--text-muted)">Entity</th>
            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--text-muted)">Detail</th>
            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--text-muted)">IP</th>
          </tr>
        </thead>
        <tbody id="al-tbody">
          <tr><td colspan="6" style="padding:20px 14px;color:var(--text-muted);text-align:center">Loading…</td></tr>
        </tbody>
      </table>
    </div>
    <div id="al-pagination" style="display:flex;justify-content:flex-end;align-items:center;gap:8px;padding:10px 14px;border-top:1px solid var(--surface-2);font-size:12px;color:var(--text-muted)"></div>
  </div>
</div>

<script>
(function(){
  var _alPage = 1;
  var _alTotal = 0;
  var _alPerPage = 20;

  function esc(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

  function fmtTs(s){
    if(!s) return '—';
    return new Date(s.replace(' ','T')+'Z').toLocaleString();
  }

  function actionBadgeColor(action){
    if(!action) return 'var(--text-muted)';
    if(action==='delete') return '#f44336';
    if(action==='suspend') return '#ff9800';
    if(action==='create') return '#4caf50';
    if(action==='activate') return '#2196f3';
    return 'var(--text-muted)';
  }

  function buildDetail(row){
    var parts=[];
    if(row.metadata && typeof row.metadata==='object'){
      Object.entries(row.metadata).slice(0,3).forEach(function(kv){
        parts.push(esc(kv[0])+': <strong>'+esc(String(kv[1]))+'</strong>');
      });
    }
    return parts.join(' · ') || '—';
  }

  window.loadAuditLog = function(page){
    _alPage = page || 1;
    var tbody = document.getElementById('al-tbody');
    tbody.innerHTML = '<tr><td colspan="6" style="padding:20px 14px;color:var(--text-muted);text-align:center">Loading…</td></tr>';

    var params = {
      page: _alPage,
      per_page: _alPerPage
    };
    var action = document.getElementById('al-action-filter').value;
    var entity = document.getElementById('al-entity-filter').value;
    var from   = document.getElementById('al-date-from').value;
    var to     = document.getElementById('al-date-to').value;
    if(action) params.action_filter = action;
    if(entity) params.entity_type = entity;
    if(from)   params.date_from = from;
    if(to)     params.date_to = to;

    adminApi('get_audit_log', params).then(function(data){
      var rows = (data.logs || data.data && data.data.logs) || [];
      var total = (data.total || data.data && data.data.total) || 0;
      _alTotal = total;

      if(!rows.length){
        tbody.innerHTML = '<tr><td colspan="6" style="padding:20px 14px;color:var(--text-muted);text-align:center">No audit log entries found.</td></tr>';
        document.getElementById('al-pagination').innerHTML = '';
        return;
      }

      tbody.innerHTML = rows.map(function(r){
        return '<tr style="border-bottom:1px solid var(--surface-2)">'+
          '<td style="padding:9px 14px;white-space:nowrap;color:var(--text-muted);font-size:12px">'+fmtTs(r.created_at)+'</td>'+
          '<td style="padding:9px 14px;white-space:nowrap">'+esc(r.admin_name||'#'+r.admin_id)+'</td>'+
          '<td style="padding:9px 14px;white-space:nowrap"><span style="color:'+actionBadgeColor(r.action)+';font-weight:600">'+esc(r.action)+'</span></td>'+
          '<td style="padding:9px 14px;white-space:nowrap">'+esc(r.entity_type)+(r.entity_id?' <span style="color:var(--text-muted)">#'+r.entity_id+'</span>':'')+'</td>'+
          '<td style="padding:9px 14px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(JSON.stringify(r.metadata||{}))+'">'+buildDetail(r)+'</td>'+
          '<td style="padding:9px 14px;color:var(--text-muted);font-size:12px;white-space:nowrap">'+esc(r.ip||'—')+'</td>'+
        '</tr>';
      }).join('');

      var pages = Math.ceil(total / _alPerPage);
      var pag = document.getElementById('al-pagination');
      if(pages <= 1){ pag.innerHTML=''; return; }
      pag.innerHTML =
        '<span>Page '+_alPage+' of '+pages+' &nbsp;('+total+' total)</span>'+
        '<button style="padding:4px 10px;border-radius:6px;border:1px solid var(--surface-2);background:none;font-size:12px;cursor:pointer" '+(_alPage<=1?'disabled':'')+' onclick="loadAuditLog('+(_alPage-1)+')">Prev</button>'+
        '<button style="padding:4px 10px;border-radius:6px;border:1px solid var(--surface-2);background:none;font-size:12px;cursor:pointer" '+(_alPage>=pages?'disabled':'')+' onclick="loadAuditLog('+(_alPage+1)+')">Next</button>';
    }).catch(function(){
      tbody.innerHTML = '<tr><td colspan="6" style="padding:20px 14px;color:#f44;text-align:center">Failed to load audit log.</td></tr>';
    });
  };

  window.resetAuditFilters = function(){
    document.getElementById('al-action-filter').value = '';
    document.getElementById('al-entity-filter').value = '';
    document.getElementById('al-date-from').value = '';
    document.getElementById('al-date-to').value = '';
    loadAuditLog(1);
  };

  // Auto-load when panel becomes visible
  var panel = document.getElementById('panel-audit-log');
  if(panel){
    new MutationObserver(function(muts){
      muts.forEach(function(m){
        if(m.target.classList.contains('active')) loadAuditLog(1);
      });
    }).observe(panel, { attributes:true, attributeFilter:['class'] });
  }
})();
</script>
