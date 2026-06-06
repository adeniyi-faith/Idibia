<!-- DISPUTE MODAL -->
<div class="modal-overlay" id="disputeModal" onclick="if(event.target===this)closeModal()">
  <div class="modal" style="max-width: 600px;">
    <div class="modal-header"><h3 style="font-size:15px" id="modalTitle">Handle Dispute</h3><button aria-label="Close modal" onclick="closeModal()" style="background:none;border:none;font-size:18px;color:var(--text-muted)">×</button></div>
    <div class="modal-body">
      <div id="modalDesc" style="font-size:13px;color:var(--text-secondary);margin-bottom:16px"></div>
      <div id="disputeEvidence" style="margin-bottom:16px;font-size:13px;color:var(--text-secondary);white-space:pre-wrap"></div>
      <div id="disputeTimeline" style="margin-bottom:16px"></div>

      <div id="disputeActionArea">
        <div class="form-group"><label class="form-label">Resolution action</label><select class="form-input" id="resolutionType"><option>Issue full refund to customer</option><option>Issue partial refund</option><option>Warn driver (first offence)</option><option>Suspend driver temporarily</option><option>Suspend driver permanently</option><option>No action — complaint invalid</option></select></div>
        <div class="form-group"><label class="form-label">Refund amount (₦)</label><input class="form-input" type="number" placeholder="0" id="refundAmt"></div>
        <div class="form-group"><label class="form-label">Admin notes</label><textarea class="form-input" id="resolutionNotes" rows="3" placeholder="Add resolution notes…" style="resize:none"></textarea></div>
      </div>
    </div>
    <div class="modal-footer" id="disputeFooter">
      <button onclick="closeModal()" style="padding:9px 18px;border-radius:9px;border:1.5px solid var(--surface-2);background:none;font-size:13px">Cancel</button>
      <button class="btn-primary" onclick="resolveDispute()" id="resolveDisputeBtn">Resolve Dispute</button>
    </div>
  </div>
</div>

<!-- TRIP DETAIL MODAL -->
<div class="modal-overlay" id="tripModal" onclick="if(event.target===this)closeTripModal()">
  <div class="modal" style="max-width: 600px;">
    <div class="modal-header"><h3 style="font-size:15px" id="tripModalTitle">Trip Details</h3><button aria-label="Close modal" onclick="closeTripModal()" style="background:none;border:none;font-size:18px;color:var(--text-muted)">×</button></div>
    <div class="modal-body">
        <div id="tripModalBody"></div>
        <div id="tripTimeline" style="margin-top:16px"></div>
        <div id="tripPayments" style="margin-top:16px"></div>
    </div>
    <div class="modal-footer">
      <button onclick="closeTripModal()" style="padding:9px 18px;border-radius:9px;border:1.5px solid var(--surface-2);background:none;font-size:13px">Close</button>
      <button class="btn-primary disabled-action" aria-disabled="true" onclick="showUnavailableFeature('Receipt email', 'Receipt email delivery needs a receipt generation endpoint before messages can be sent.')">Email Receipt</button>
    </div>
  </div>
</div>

<!-- DRIVER DETAIL MODAL -->
<div class="modal-overlay" id="driverModal" onclick="if(event.target===this)closeDriverModal()">
  <div class="modal" style="max-width: 600px;">
    <div class="modal-header"><h3 style="font-size:15px" id="driverModalTitle">Driver Details</h3><button aria-label="Close modal" onclick="closeDriverModal()" style="background:none;border:none;font-size:18px;color:var(--text-muted)">×</button></div>
    <div class="modal-body">
        <div id="driverModalBody"></div>
        <div id="driverTrips" style="margin-top:16px"></div>
    </div>
    <div class="modal-footer">
      <button onclick="closeDriverModal()" style="padding:9px 18px;border-radius:9px;border:1.5px solid var(--surface-2);background:none;font-size:13px">Close</button>
    </div>
  </div>
</div>

<!-- CUSTOMER DETAIL MODAL -->
<div class="modal-overlay" id="customerModal" onclick="if(event.target===this)closeCustomerModal()">
  <div class="modal" style="max-width: 600px;">
    <div class="modal-header"><h3 style="font-size:15px" id="customerModalTitle">Customer Details</h3><button aria-label="Close modal" onclick="closeCustomerModal()" style="background:none;border:none;font-size:18px;color:var(--text-muted)">×</button></div>
    <div class="modal-body">
        <div id="customerModalBody"></div>
        <div id="customerTrips" style="margin-top:16px"></div>
    </div>
    <div class="modal-footer">
      <button onclick="closeCustomerModal()" style="padding:9px 18px;border-radius:9px;border:1.5px solid var(--surface-2);background:none;font-size:13px">Close</button>
    </div>
  </div>
</div>

<!-- PAYMENT DETAIL MODAL -->
<div class="modal-overlay" id="paymentModal" onclick="if(event.target===this)closePaymentModal()">
  <div class="modal" style="max-width: 600px;">
    <div class="modal-header"><h3 style="font-size:15px" id="paymentModalTitle">Payment Details</h3><button aria-label="Close modal" onclick="closePaymentModal()" style="background:none;border:none;font-size:18px;color:var(--text-muted)">×</button></div>
    <div class="modal-body">
        <div id="paymentModalBody"></div>
        <div id="paymentTrip" style="margin-top:16px"></div>
    </div>
    <div class="modal-footer">
      <button onclick="closePaymentModal()" style="padding:9px 18px;border-radius:9px;border:1.5px solid var(--surface-2);background:none;font-size:13px">Close</button>
    </div>
  </div>
</div>

<!-- REASSIGN TRIP MODAL -->
<div class="modal-overlay" id="reassignTripModal" onclick="if(event.target===this)closeReassignModal()">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h3 style="font-size:15px" id="reassignModalTitle">Reassign Trip</h3>
      <button aria-label="Close modal" onclick="closeReassignModal()" style="background:none;border:none;font-size:18px;color:var(--text-muted)">×</button>
    </div>
    <div class="modal-body">
      <p id="reassignModalDesc" style="font-size:13px;color:var(--text-secondary);margin-bottom:16px"></p>
      <div class="form-group">
        <label class="form-label">Assign to driver</label>
        <select class="form-input" id="reassignDriverSelect"><option value="">Loading drivers…</option></select>
      </div>
    </div>
    <div class="modal-footer">
      <button onclick="closeReassignModal()" style="padding:9px 18px;border-radius:9px;border:1.5px solid var(--surface-2);background:none;font-size:13px">Cancel</button>
      <button class="btn-primary" id="reassignSubmitBtn" onclick="submitReassignTrip()">Reassign</button>
    </div>
  </div>
</div>

<!-- PROOF IMAGE LIGHTBOX -->
<div class="modal-overlay" id="proofModal" onclick="if(event.target===this)closeProofModal()" style="z-index:9999;align-items:center;justify-content:center">
  <div style="position:relative;max-width:90vw;max-height:90vh;background:var(--surface-1);border-radius:14px;overflow:hidden;display:flex;flex-direction:column">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--surface-2)">
      <span style="font-size:13px;font-weight:700;color:var(--text-primary)" id="proofModalCaption">Payment Receipt</span>
      <div style="display:flex;gap:8px;align-items:center">
        <a id="proofModalDownload" href="#" download style="font-size:12px;color:var(--info);font-weight:700;text-decoration:none">Download</a>
        <button onclick="closeProofModal()" style="background:none;border:none;font-size:20px;color:var(--text-muted);cursor:pointer;line-height:1">×</button>
      </div>
    </div>
    <div style="overflow:auto;flex:1;display:flex;align-items:center;justify-content:center;padding:12px;background:var(--bg-dark,#0a0c12)">
      <img id="proofModalImg" src="" alt="Payment proof" style="max-width:100%;max-height:75vh;border-radius:6px;object-fit:contain" onerror="this.style.display='none';document.getElementById('proofModalFallback').style.display='block'">
      <div id="proofModalFallback" style="display:none;text-align:center;padding:32px;color:var(--text-secondary)">
        <div style="font-size:32px;margin-bottom:8px">📄</div>
        <div style="font-size:13px">Could not preview this file.<br><a id="proofModalFallbackLink" href="#" target="_blank" rel="noopener" style="color:var(--info)">Open in new tab</a></div>
      </div>
    </div>
  </div>
</div>

<!-- CONFIRM ACTION DIALOG -->
<div class="modal-overlay" id="confirmActionModal" onclick="if(event.target===this)_confirmReject()">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h3 style="font-size:15px" id="confirmActionTitle">Confirm Action</h3>
      <button aria-label="Close" onclick="_confirmReject()" style="background:none;border:none;font-size:18px;color:var(--text-muted)">×</button>
    </div>
    <div class="modal-body">
      <p id="confirmActionDesc" style="font-size:13px;color:var(--text-secondary);margin-bottom:16px"></p>
      <div class="form-group" id="confirmReasonGroup">
        <label class="form-label" id="confirmReasonLabel">Reason</label>
        <div id="confirmReasonSelectWrap" style="display:none">
          <select class="form-input" id="confirmReasonSelect">
            <option value="">Select reason…</option>
            <option>Blurry/invalid ID photo</option>
            <option>License expired</option>
            <option>Vehicle inspection failed</option>
            <option>Incomplete documents</option>
            <option>Profile photo invalid (cap/glasses)</option>
            <option>Other</option>
          </select>
        </div>
        <textarea class="form-input" id="confirmReasonText" rows="3" placeholder="Enter reason…" style="resize:none;margin-top:0" oninput="document.getElementById('confirmActionSubmit').disabled=this.required&&!this.value.trim()"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button onclick="_confirmReject()" style="padding:9px 18px;border-radius:9px;border:1.5px solid var(--surface-2);background:none;font-size:13px">Cancel</button>
      <button id="confirmActionSubmit" onclick="_confirmResolve()" style="padding:9px 18px;border-radius:9px;font-size:13px;font-weight:700;background:var(--danger);color:#fff;border:none;cursor:pointer">Confirm</button>
    </div>
  </div>
</div>

<div class="toast" id="toastEl"></div>
