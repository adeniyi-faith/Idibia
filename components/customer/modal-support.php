<div class="modal" id="modal-support">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Customer Support</h3>
      <button class="icon-btn" aria-label="Close modal" onclick="closeModal(null,'support')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body" style="padding: 20px;">
      <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 20px;">How can we help you today? Please provide as much detail as possible so we can assist you better.</p>

      <form id="supportForm" onsubmit="event.preventDefault(); submitSupportTicket();">
        <div class="fi-input">
          <label>Category</label>
          <select id="support_category" required>
            <option value="general">General Inquiry</option>
            <option value="trip_issue">Trip Issue</option>
            <option value="billing">Billing & Payments</option>
            <option value="account">Account Management</option>
          </select>
        </div>

        <div class="fi-input">
          <label>Subject</label>
          <input type="text" id="support_subject" required placeholder="Brief summary of your issue">
        </div>

        <div class="fi-input">
          <label>Message</label>
          <textarea id="support_message" rows="5" required placeholder="Describe your issue here..."></textarea>
        </div>

        <button type="submit" class="btn-primary" id="btnSubmitSupport">Submit Ticket</button>
      </form>
    </div>
  </div>
</div>
