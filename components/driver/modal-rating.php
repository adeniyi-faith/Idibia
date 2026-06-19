<div class="modal" id="modal-driver-rating">
  <div class="modal-content">
    <span class="modal-handle"></span>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
      <h3 style="font-size:20px;color:var(--text-primary);margin:0;">Rate Your Customer</h3>
      <button class="modal-close-btn" onclick="closeDriverRatingModal()" aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <p class="modal-sub">How was your experience with this customer?</p>

    <!-- Star rating -->
    <div class="rating-stars-wrap">
      <div class="rating-stars" id="driver-rating-stars">
        <button class="star-btn" data-value="1" onclick="setDriverRatingStar(1)" aria-label="1 star">
          <svg viewBox="0 0 24 24" width="44" height="44"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </button>
        <button class="star-btn" data-value="2" onclick="setDriverRatingStar(2)" aria-label="2 stars">
          <svg viewBox="0 0 24 24" width="44" height="44"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </button>
        <button class="star-btn" data-value="3" onclick="setDriverRatingStar(3)" aria-label="3 stars">
          <svg viewBox="0 0 24 24" width="44" height="44"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </button>
        <button class="star-btn" data-value="4" onclick="setDriverRatingStar(4)" aria-label="4 stars">
          <svg viewBox="0 0 24 24" width="44" height="44"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </button>
        <button class="star-btn" data-value="5" onclick="setDriverRatingStar(5)" aria-label="5 stars">
          <svg viewBox="0 0 24 24" width="44" height="44"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </button>
      </div>
      <div class="rating-label" id="driver-rating-label">&nbsp;</div>
    </div>

    <!-- Comment -->
    <div class="form-group" style="margin-top:20px;">
      <label style="font-size:13px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:8px;">Note (optional)</label>
      <textarea
        id="driver-rating-comment"
        class="form-input"
        placeholder="Any feedback about this customer…"
        rows="3"
        style="height:auto;padding:14px 16px;resize:none;"
      ></textarea>
    </div>

    <button class="btn-primary" id="driver-rating-submit-btn" onclick="submitDriverRatingFromModal()" style="width:100%;margin-top:4px;" disabled>
      Submit Rating
    </button>
    <button onclick="closeDriverRatingModal()" style="width:100%;margin-top:10px;background:none;border:none;color:var(--text-secondary);font-size:14px;cursor:pointer;padding:8px;">Skip for now</button>
  </div>
</div>
