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
          <h4 style="margin: 0 0 10px 0; font-size: 16px; cursor: pointer;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">How do I book a delivery?</h4>
          <p style="margin: 0; font-size: 14px; color: var(--text-muted); display: none;">To book a delivery, select a category on the Home tab, enter your pickup and dropoff locations, review the estimated fare, and tap "Request Driver".</p>
        </div>

        <div class="faq-item" style="border-bottom: 1px solid rgba(255,255,255,0.05); padding: 15px 0;">
          <h4 style="margin: 0 0 10px 0; font-size: 16px; cursor: pointer;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">How do I track my package?</h4>
          <p style="margin: 0; font-size: 14px; color: var(--text-muted); display: none;">Once a driver accepts your request, you can track their real-time location and see their ETA on the active trip screen.</p>
        </div>

        <div class="faq-item" style="border-bottom: 1px solid rgba(255,255,255,0.05); padding: 15px 0;">
          <h4 style="margin: 0 0 10px 0; font-size: 16px; cursor: pointer;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">What payment methods are supported?</h4>
          <p style="margin: 0; font-size: 14px; color: var(--text-muted); display: none;">We currently support bank transfers. You can find our company bank details in the Payment Methods section.</p>
        </div>

        <div class="faq-item" style="border-bottom: 1px solid rgba(255,255,255,0.05); padding: 15px 0;">
          <h4 style="margin: 0 0 10px 0; font-size: 16px; cursor: pointer;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">How can I contact my driver?</h4>
          <p style="margin: 0; font-size: 14px; color: var(--text-muted); display: none;">After a driver is assigned to your trip, you will see a call button on the tracking screen that lets you contact them directly.</p>
        </div>

        <div class="faq-item" style="padding: 15px 0;">
          <h4 style="margin: 0 0 10px 0; font-size: 16px; cursor: pointer;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">How do I update my account details?</h4>
          <p style="margin: 0; font-size: 14px; color: var(--text-muted); display: none;">Navigate to the Account tab and tap 'Profile Details' to edit your personal information.</p>
        </div>
      </div>
    </div>
  </div>
</div>
