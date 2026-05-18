with open('admin.php', 'r') as f:
    content = f.read()

old_legal = """      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 14px;background:var(--navy-light)" onclick="toast('Opening Terms & Conditions…')">Terms & Conditions</button>
        <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 14px;background:var(--navy-light)" onclick="toast('Opening Privacy Policy…')">Privacy Policy</button>
        <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 14px;background:var(--navy-light)" onclick="toast('Opening Location Data Policy…')">Location Data Policy</button>
        <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 14px;background:var(--navy-light)" onclick="toast('Opening Software License…')">Software License</button>
      </div>"""

new_legal = """      <div class="form-row">
        <div class="form-group"><label class="form-label">Terms & Conditions URL</label><input class="form-input" data-setting="legal_terms_url" placeholder="https://..."></div>
        <div class="form-group"><label class="form-label">Privacy Policy URL</label><input class="form-input" data-setting="legal_privacy_url" placeholder="https://..."></div>
        <div class="form-group"><label class="form-label">Location Data Policy URL</label><input class="form-input" data-setting="legal_location_url" placeholder="https://..."></div>
        <div class="form-group"><label class="form-label">Software License URL</label><input class="form-input" data-setting="legal_license_url" placeholder="https://..."></div>
      </div>"""

content = content.replace(old_legal, new_legal)

with open('admin.php', 'w') as f:
    f.write(content)
