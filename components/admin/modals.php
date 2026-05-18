<!-- DISPUTE MODAL -->
<div class="modal-overlay" id="disputeModal" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-header"><h3 style="font-size:15px" id="modalTitle">Handle Dispute</h3><button onclick="closeModal()" style="background:none;border:none;font-size:18px;color:var(--text-muted)">×</button></div>
    <div class="modal-body">
      <div id="modalDesc" style="font-size:13px;color:var(--text-secondary);margin-bottom:16px"></div>
      <div class="form-group"><label class="form-label">Resolution action</label><select class="form-input" id="resolutionType"><option>Issue full refund to customer</option><option>Issue partial refund</option><option>Warn driver (first offence)</option><option>Suspend driver temporarily</option><option>Suspend driver permanently</option><option>No action — complaint invalid</option></select></div>
      <div class="form-group"><label class="form-label">Refund amount (₦)</label><input class="form-input" type="number" placeholder="0" id="refundAmt"></div>
      <div class="form-group"><label class="form-label">Admin notes</label><textarea class="form-input" id="resolutionNotes" rows="3" placeholder="Add resolution notes…" style="resize:none"></textarea></div>
    </div>
    <div class="modal-footer">
      <button onclick="closeModal()" style="padding:9px 18px;border-radius:9px;border:1.5px solid var(--surface-2);background:none;font-size:13px">Cancel</button>
      <button class="btn-primary" onclick="resolveDispute()">Resolve Dispute</button>
    </div>
  </div>
</div>

<!-- TRIP DETAIL MODAL -->
<div class="modal-overlay" id="tripModal" onclick="if(event.target===this)closeTripModal()">
  <div class="modal">
    <div class="modal-header"><h3 style="font-size:15px" id="tripModalTitle">Trip Details</h3><button onclick="closeTripModal()" style="background:none;border:none;font-size:18px;color:var(--text-muted)">×</button></div>
    <div class="modal-body" id="tripModalBody"></div>
    <div class="modal-footer">
      <button onclick="closeTripModal()" style="padding:9px 18px;border-radius:9px;border:1.5px solid var(--surface-2);background:none;font-size:13px">Close</button>
      <button class="btn-primary disabled-action" aria-disabled="true" onclick="showUnavailableFeature('Receipt email', 'Receipt email delivery needs a receipt generation endpoint before messages can be sent.')">Email Receipt</button>
    </div>
  </div>
</div>

<div class="toast" id="toastEl"></div>
