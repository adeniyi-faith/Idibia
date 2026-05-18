<!-- ══════════ MODAL: SCHEDULE PICKUP ══════════ -->
<div class="modal-overlay" id="modal-schedule" onclick="closeModal(event,'schedule')">
  <div class="modal-content" onclick="event.stopPropagation()">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <div class="modal-icon info">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="30" height="30"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
      <h2>Schedule a Pickup</h2>
      <p>Choose your preferred date and time</p>
    </div>
    <!-- Date picker -->
    <p class="section-label" style="margin-bottom:12px;flex-shrink:0;">Select Date</p>
    <div class="date-grid" id="dateGrid">
      <!-- Dynamically filled -->
    </div>
    <p class="section-label" style="margin-bottom:12px;flex-shrink:0;">Select Time</p>
    <div class="time-picker">
      <select class="time-select">
        <option>7:00 AM</option><option>8:00 AM</option><option>9:00 AM</option>
        <option selected>10:00 AM</option><option>11:00 AM</option><option>12:00 PM</option>
        <option>1:00 PM</option><option>2:00 PM</option><option>3:00 PM</option>
        <option>4:00 PM</option><option>5:00 PM</option><option>6:00 PM</option>
        <option>7:00 PM</option>
      </select>
      <select class="time-select" style="max-width:120px">
        <option>Today</option><option>Tomorrow</option><option>In 2 days</option>
      </select>
    </div>
    <button type="button" class="btn-primary" onclick="confirmSchedule()" style="flex-shrink:0;">Confirm Schedule</button>
    <button type="button" onclick="closeModal(null,'schedule')" style="width:100%;height:48px;background:none;border:none;color:var(--text-muted);font-size:14px;font-weight:600;cursor:pointer;margin-top:10px;flex-shrink:0;user-select:none;">Cancel</button>
  </div>
</div>
