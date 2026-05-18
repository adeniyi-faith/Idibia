import re

with open('admin.php', 'r') as f:
    content = f.read()

# Replace loadPaymentSettings to handle buttons as well
old_load = """async function loadPaymentSettings(){
  try {
    const data = await adminApi('get_settings');
    const settings = data.settings || {};
    document.querySelectorAll('[data-setting]').forEach(input => {
      const key = input.getAttribute('data-setting');
      if(settings[key] !== undefined) input.value = settings[key];
    });
  } catch (e) {
    toast('Could not load payment settings');
  }
}"""

new_load = """async function loadPaymentSettings(){
  try {
    const data = await adminApi('get_settings');
    const settings = data.settings || {};
    document.querySelectorAll('[data-setting]').forEach(el => {
      const key = el.getAttribute('data-setting');
      if(settings[key] !== undefined) {
        if(el.tagName === 'BUTTON' && el.classList.contains('toggle')) {
          if (settings[key] == '1' || settings[key] === true || settings[key] === 'true') {
            el.classList.add('on');
          } else {
            el.classList.remove('on');
          }
        } else {
          el.value = settings[key];
        }
      }
    });
  } catch (e) {
    toast('Could not load settings');
  }
}"""

content = content.replace(old_load, new_load)

old_save = """async function savePaymentSettings(){
  const payload = {};
  document.querySelectorAll('[data-setting]').forEach(input => {
    payload[input.getAttribute('data-setting')] = input.value;
  });"""

new_save = """async function savePaymentSettings(){
  const payload = {};
  document.querySelectorAll('[data-setting]').forEach(el => {
    if(el.tagName === 'BUTTON' && el.classList.contains('toggle')) {
      payload[el.getAttribute('data-setting')] = el.classList.contains('on') ? '1' : '0';
    } else {
      payload[el.getAttribute('data-setting')] = el.value;
    }
  });"""

content = content.replace(old_save, new_save)

with open('admin.php', 'w') as f:
    f.write(content)
