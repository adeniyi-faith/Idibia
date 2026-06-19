<!-- ══════════ MODAL: POST-TRIP / RECEIPT ══════════ -->
<div class="modal-overlay" id="modal-post-trip" onclick="closeModal(event,'post-trip')">
  <div class="modal-content" onclick="event.stopPropagation()">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <div class="modal-icon success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="24" height="24"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h2>Trip Completed! 🎉</h2>
      <p>Package safely delivered to your destination.</p>
    </div>
    <!-- Receipt -->
    <div class="receipt-box">
      <div class="receipt-label">Payment Breakdown</div>
      <div class="receipt-row"><span>Base Fare</span><span>₦1,800</span></div>
      <div class="receipt-row"><span>Distance (4.2 km)</span><span>₦700</span></div>
      <div class="receipt-row"><span>Taxes & Fees</span><span>₦300</span></div>
      <div class="receipt-row total"><span>Total Paid</span><span>₦2,800</span></div>
      <div class="receipt-payment">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        Paid by bank transfer — payment verified
        <button type="button" id="receiptLinkBtn" onclick="viewReceipt()" style="margin-left:auto;background:none;border:none;font-size:12px;font-weight:700;color:var(--navy);cursor:pointer;text-decoration:underline;text-underline-offset:2px;padding:4px;display:none;">View Receipt</button>
      </div>
    </div>
    <!-- Star Rating -->
    <div style="text-align:center;margin-bottom:12px;flex-shrink:0;">
      <p style="font-size:15px;font-weight:600;color:var(--text-primary);margin-bottom:14px">How was your rider, Amina? ⭐</p>
      <div class="star-rating" id="starRating">
        <button class="star-btn" aria-label="Rate 1 star" onclick="rateStar(1)"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></button>
        <button class="star-btn" aria-label="Rate 2 stars" onclick="rateStar(2)"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></button>
        <button class="star-btn" aria-label="Rate 3 stars" onclick="rateStar(3)"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></button>
        <button class="star-btn" aria-label="Rate 4 stars" onclick="rateStar(4)"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></button>
        <button class="star-btn active" aria-label="Rate 5 stars" onclick="rateStar(5)"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></button>
      </div>
      <div class="rating-label" id="ratingLabel">Amazing! 🌟</div>
    </div>
    <!-- Feedback chips -->
    <div class="feedback-chips" id="feedbackChips">
      <button class="feedback-chip active" onclick="toggleChip(this)">On time</button>
      <button class="feedback-chip active" onclick="toggleChip(this)">Safe handling</button>
      <button class="feedback-chip" onclick="toggleChip(this)">Friendly</button>
      <button class="feedback-chip" onclick="toggleChip(this)">Fast</button>
      <button class="feedback-chip" onclick="toggleChip(this)">Careful</button>
      <button class="feedback-chip" onclick="toggleChip(this)">Professional</button>
    </div>
    <!-- Actions reordered -->
    <button type="button" class="btn-primary" onclick="submitCustomerRatingAndClose()" style="flex-shrink:0;">Done — Back to Home</button>
    <button type="button" class="report-issue" onclick="openSupportTicket('trip_issue')">Report an issue with this trip</button>
  </div>
</div>
