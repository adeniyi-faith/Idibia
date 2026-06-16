<div class="modal" id="modal-sos">
  <div class="modal-content" style="max-width: 400px; padding: 24px;">
    <h3 style="margin-top:0; margin-bottom:16px; color:var(--danger); display:flex; align-items:center; gap:8px;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Safety Emergency (SOS)
    </h3>
    <p style="font-size:13px; color:var(--text-secondary); margin-bottom: 20px;">Please report any immediate safety concerns. High severity reports will alert our team immediately.</p>

    <div style="margin-bottom: 16px;">
        <label class="form-label">Category</label>
        <select class="form-input" id="sosCategory">
            <option value="accident">Accident / Collision</option>
            <option value="harassment">Harassment / Threat</option>
            <option value="theft">Theft / Package Loss</option>
            <option value="other">Other Emergency</option>
        </select>
    </div>

    <div style="margin-bottom: 16px;">
        <label class="form-label">Severity</label>
        <select class="form-input" id="sosSeverity">
            <option value="high">High - Immediate Assistance Required</option>
            <option value="medium">Medium - Safety Concern</option>
        </select>
    </div>

    <div style="margin-bottom: 24px;">
        <label class="form-label">Describe the situation</label>
        <textarea class="form-input" id="sosDescription" rows="4" placeholder="Tell us exactly what happened..."></textarea>
    </div>

    <div style="display:flex; gap: 12px;">
        <button class="btn-primary" style="flex:1; background:var(--surface-2); color:var(--text-primary); border:none;" onclick="closeModal(null,'sos')">Cancel</button>
        <button class="btn-primary" style="flex:1; background:var(--danger); border:none;" onclick="submitSosReport()">Send SOS</button>
    </div>
  </div>
</div>
