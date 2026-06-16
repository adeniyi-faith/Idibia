<div class="modal" id="modal-legal">
  <div class="modal-content" style="height: 80vh; max-height: 800px; display: flex; flex-direction: column;">
    <div class="modal-header" style="flex-shrink: 0;">
      <h3 id="legal_modal_title">Legal Content</h3>
      <button class="icon-btn" aria-label="Close modal" onclick="closeModal(null,'legal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body" style="padding: 20px; overflow-y: auto; flex-grow: 1;">
      <div id="legal_modal_content" style="font-size: 14px; line-height: 1.6; color: var(--text);">
        <!-- Content injected dynamically -->
      </div>
    </div>
  </div>
</div>
