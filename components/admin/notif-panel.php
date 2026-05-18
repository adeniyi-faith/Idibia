<!-- NOTIFICATION PANEL -->
  <div class="notif-panel" id="notifPanel">
    <div class="notif-header">
      <h4>Notifications</h4>
      <button class="disabled-action" aria-disabled="true" onclick="showUnavailableFeature('Notification read state', 'Notification inbox APIs are not connected yet, so read/unread state cannot be changed.')" style="font-size:11px;color:var(--info);background:none;border:none;">Mark all read</button>
    </div>
    <div class="notif-item">
      <div class="notif-icon" style="background:rgba(74,158,255,0.1)">ℹ️</div>
      <div><div style="font-size:12px;font-weight:600">Notification inbox pending backend connection</div><div style="font-size:11px;color:var(--text-muted)">Operational alerts will appear here after notification APIs are wired.</div></div>
    </div>
  </div>