import re

with open('admin.php', 'r') as f:
    content = f.read()

# Replace missing data-setting attributes for Commission & Pricing
content = re.sub(
    r'<input class="form-input" type="number" value="20" min="1" max="50">',
    r'<input class="form-input" type="number" data-setting="platform_commission_pct" value="20" min="1" max="50">',
    content
)

content = re.sub(
    r'<input class="form-input" type="number" value="2\.5" min="1" step="0\.1">',
    r'<input class="form-input" type="number" data-setting="surge_multiplier_cap" value="2.5" min="1" step="0.1">',
    content
)

content = re.sub(
    r'<input class="form-input" type="number" value="800">',
    r'<input class="form-input" type="number" data-setting="min_fare" value="800">',
    content
)

content = re.sub(
    r'<input class="form-input" type="number" value="50">',
    r'<input class="form-input" type="number" data-setting="max_delivery_radius_km" value="50">',
    content
)

# KYC Policy Toggles
content = re.sub(
    r'<div class="toggle-row"><div><div class="toggle-label">Auto-flag blurry ID photos</div><div class="toggle-sub">AI-assisted photo quality check</div></div><button class="toggle on" onclick="this.classList.toggle\(\'on\'\)"></button></div>',
    r'<div class="toggle-row"><div><div class="toggle-label">Auto-flag blurry ID photos</div><div class="toggle-sub">AI-assisted photo quality check</div></div><button class="toggle" data-setting="kyc_auto_flag_blurry" onclick="this.classList.toggle(\'on\')"></button></div>',
    content
)

content = re.sub(
    r'<div class="toggle-row"><div><div class="toggle-label">Require vehicle inspection report</div><div class="toggle-sub">Mandatory for vans and tricycles</div></div><button class="toggle on" onclick="this.classList.toggle\(\'on\'\)"></button></div>',
    r'<div class="toggle-row"><div><div class="toggle-label">Require vehicle inspection report</div><div class="toggle-sub">Mandatory for vans and tricycles</div></div><button class="toggle" data-setting="kyc_require_vehicle_inspection" onclick="this.classList.toggle(\'on\')"></button></div>',
    content
)

content = re.sub(
    r'<div class="toggle-row"><div><div class="toggle-label">72-hour KYC review SLA alert</div><div class="toggle-sub">Email admin if review exceeds 72h</div></div><button class="toggle on" onclick="this.classList.toggle\(\'on\'\)"></button></div>',
    r'<div class="toggle-row"><div><div class="toggle-label">72-hour KYC review SLA alert</div><div class="toggle-sub">Email admin if review exceeds 72h</div></div><button class="toggle" data-setting="kyc_72h_sla_alert" onclick="this.classList.toggle(\'on\')"></button></div>',
    content
)

content = re.sub(
    r'<div class="toggle-row"><div><div class="toggle-label">Background check integration</div><div class="toggle-sub">Third-party criminal record API</div></div><button class="toggle" onclick="this.classList.toggle\(\'on\'\)"></button></div>',
    r'<div class="toggle-row"><div><div class="toggle-label">Background check integration</div><div class="toggle-sub">Third-party criminal record API</div></div><button class="toggle" data-setting="kyc_background_check" onclick="this.classList.toggle(\'on\')"></button></div>',
    content
)


# Notification Toggles
content = re.sub(
    r'<div class="toggle-row"><div><div class="toggle-label">KYC queue alerts</div><div class="toggle-sub">Email when queue exceeds 5</div></div><button class="toggle on" onclick="this.classList.toggle\(\'on\'\)"></button></div>',
    r'<div class="toggle-row"><div><div class="toggle-label">KYC queue alerts</div><div class="toggle-sub">Email when queue exceeds 5</div></div><button class="toggle" data-setting="notif_kyc_queue" onclick="this.classList.toggle(\'on\')"></button></div>',
    content
)

content = re.sub(
    r'<div class="toggle-row"><div><div class="toggle-label">Dispute escalation alerts</div><div class="toggle-sub">Push alert when dispute >48h unresolved</div></div><button class="toggle on" onclick="this.classList.toggle\(\'on\'\)"></button></div>',
    r'<div class="toggle-row"><div><div class="toggle-label">Dispute escalation alerts</div><div class="toggle-sub">Push alert when dispute >48h unresolved</div></div><button class="toggle" data-setting="notif_dispute_escalation" onclick="this.classList.toggle(\'on\')"></button></div>',
    content
)

content = re.sub(
    r'<div class="toggle-row"><div><div class="toggle-label">Daily revenue digest</div><div class="toggle-sub">Email summary at 8pm daily</div></div><button class="toggle on" onclick="this.classList.toggle\(\'on\'\)"></button></div>',
    r'<div class="toggle-row"><div><div class="toggle-label">Daily revenue digest</div><div class="toggle-sub">Email summary at 8pm daily</div></div><button class="toggle" data-setting="notif_daily_revenue" onclick="this.classList.toggle(\'on\')"></button></div>',
    content
)

content = re.sub(
    r'<div class="toggle-row"><div><div class="toggle-label">Failed payout alerts</div><div class="toggle-sub">Instant alert on payout failures</div></div><button class="toggle on" onclick="this.classList.toggle\(\'on\'\)"></button></div>',
    r'<div class="toggle-row"><div><div class="toggle-label">Failed payout alerts</div><div class="toggle-sub">Instant alert on payout failures</div></div><button class="toggle" data-setting="notif_failed_payout" onclick="this.classList.toggle(\'on\')"></button></div>',
    content
)


# Save main settings button
content = re.sub(
    r'<button class="btn-primary" onclick="toast\(\'Settings saved successfully ✓\'\)">Save Changes</button>',
    r'<button class="btn-primary" onclick="savePaymentSettings()">Save Changes</button>',
    content
)

# And in loadPaymentSettings, we also need to toggle elements
# It looks like the inputs are updated fine if they have data-setting
# But toggles are buttons where class "on" signifies the setting.
# So we need to update loadPaymentSettings and savePaymentSettings to handle buttons as well.
# It's better to update JS. Let's see the JS.

# Let's write the patched content out for now and examine JS.
with open('admin.php', 'w') as f:
    f.write(content)
