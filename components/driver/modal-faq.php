<div class="modal" id="modal-faq">
  <div class="modal-content">
    <div class="modal-header">
      <h3>FAQs</h3>
      <button class="icon-btn" onclick="closeModal('modal-faq')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body" style="padding: 20px;">
      <div class="faq-accordion">
        <div class="faq-item" style="border-bottom: 1px solid rgba(255,255,255,0.05); padding: 15px 0;">
          <h4 style="margin: 0 0 10px 0; font-size: 16px; cursor: pointer;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">How do I accept trips?</h4>
          <p style="margin: 0; font-size: 14px; color: var(--text-muted); display: none;">When you are online, trip offers will appear on your screen. Tap 'Accept' within the time limit to take the trip.</p>
        </div>

        <div class="faq-item" style="border-bottom: 1px solid rgba(255,255,255,0.05); padding: 15px 0;">
          <h4 style="margin: 0 0 10px 0; font-size: 16px; cursor: pointer;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">How do payments work?</h4>
          <p style="margin: 0; font-size: 14px; color: var(--text-muted); display: none;">Earnings are added to your balance upon trip completion. Payouts to your bank account are processed weekly.</p>
        </div>

        <div class="faq-item" style="border-bottom: 1px solid rgba(255,255,255,0.05); padding: 15px 0;">
          <h4 style="margin: 0 0 10px 0; font-size: 16px; cursor: pointer;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">How do I contact support?</h4>
          <p style="margin: 0; font-size: 14px; color: var(--text-muted); display: none;">Go to the Help tab and select a specific trip or general topic to submit a ticket. We'll respond as soon as possible.</p>
        </div>

        <div class="faq-item" style="border-bottom: 1px solid rgba(255,255,255,0.05); padding: 15px 0;">
          <h4 style="margin: 0 0 10px 0; font-size: 16px; cursor: pointer;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">How do I update vehicle documents?</h4>
          <p style="margin: 0; font-size: 14px; color: var(--text-muted); display: none;">Currently, vehicle documents can only be updated by contacting support. Navigate to Help > Account & Documents.</p>
        </div>

        <div class="faq-item" style="padding: 15px 0;">
          <h4 style="margin: 0 0 10px 0; font-size: 16px; cursor: pointer;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">How do I update my account details?</h4>
          <p style="margin: 0; font-size: 14px; color: var(--text-muted); display: none;">Navigate to the Profile tab and tap 'Personal Info' or 'Bank Details' to make changes.</p>
        </div>
      </div>
    </div>
  </div>
</div>
