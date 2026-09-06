export const DEFAULT_MAX_PAYLOAD_BYTES = 4 * 1024 * 1024;

function fail(message) {
  throw new Error(message);
}

function setValue(settings, group, key, value) {
  if (!group || !key) {
    fail('A website setting is missing its form identity. Reload the customizer and try again.');
  }

  settings[group] ??= {};
  if (Object.prototype.hasOwnProperty.call(settings[group], key)) {
    fail('A website setting appears more than once. Reload the customizer and try again.');
  }

  settings[group][key] = value;
}

function faqValue(field) {
  return [...field.querySelectorAll('[data-faq-item]')].map((item) => {
    const question = item.querySelector('input[data-faq-field="question"]');
    const answer = item.querySelector('textarea[data-faq-field="answer"]');
    const visibility = item.querySelector('input[type="checkbox"][data-faq-field="is_active"]');

    if (!question || !answer || !visibility) {
      fail('An FAQ item is incomplete. Reload the customizer and try again.');
    }

    return {
      question: question.value,
      answer: answer.value,
      is_active: visibility.checked ? '1' : '0',
    };
  });
}

function scalarValue(field) {
  const type = field.dataset.settingType;
  const controls = [...field.querySelectorAll('[data-setting-control]')];

  if (type === 'boolean') {
    const checkbox = controls.find((control) => control.matches('input[type="checkbox"]'));
    if (!checkbox) fail('A yes/no setting is incomplete. Reload the customizer and try again.');
    return checkbox.checked ? '1' : '0';
  }

  if (type === 'select') {
    const selected = controls.find((control) => control.matches('input[type="radio"]:checked'));
    return selected?.value ?? null;
  }

  if (controls.length !== 1) {
    fail('A website setting is incomplete. Reload the customizer and try again.');
  }

  return controls[0].value;
}

export function collectCustomizerSettings(form) {
  const settings = {};
  const fields = [...form.querySelectorAll('[data-setting-field]')];

  if (fields.length === 0) {
    fail('No website settings were found. Reload the customizer and try again.');
  }

  fields.forEach((field) => {
    const value = field.dataset.settingType === 'faq_list'
      ? faqValue(field)
      : scalarValue(field);
    setValue(settings, field.dataset.settingGroup, field.dataset.settingKey, value);
  });

  return settings;
}

export function payloadByteLength(payload) {
  return new TextEncoder().encode(payload).byteLength;
}

function showPayloadError(errorElement, message) {
  if (!errorElement) return;
  errorElement.textContent = message;
  errorElement.hidden = false;
  errorElement.focus();
}

export function installCustomizerPayloadSubmission(form) {
  if (!form || form.dataset.settingsPayloadInstalled === 'true') return;

  const payloadInput = form.querySelector('#customizer-settings-payload');
  const errorElement = form.querySelector('#customizer-payload-error');
  if (!payloadInput) return;

  form.dataset.settingsPayloadInstalled = 'true';
  form.addEventListener('submit', (event) => {
    try {
      const payload = JSON.stringify(collectCustomizerSettings(form));
      const configuredLimit = Number.parseInt(form.dataset.settingsPayloadMax, 10);
      const maxBytes = Number.isFinite(configuredLimit) && configuredLimit > 0
        ? configuredLimit
        : DEFAULT_MAX_PAYLOAD_BYTES;

      if (payloadByteLength(payload) > maxBytes) {
        fail('These website changes are unusually large. Shorten the longest text or FAQ answers, then try again.');
      }

      payloadInput.value = payload;
      payloadInput.disabled = false;
      form.querySelectorAll('[name^="settings["]').forEach((control) => {
        control.disabled = true;
      });
      if (errorElement) errorElement.hidden = true;
    } catch (error) {
      event.preventDefault();
      payloadInput.value = '';
      payloadInput.disabled = true;
      form.dispatchEvent(new CustomEvent('customizer:payload-error'));
      showPayloadError(
        errorElement,
        error instanceof Error ? error.message : 'The website changes could not be prepared. Reload and try again.',
      );
    }
  });
}

function installOnPage() {
  installCustomizerPayloadSubmission(document.getElementById('customizer-form'));
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', installOnPage, { once: true });
  } else {
    installOnPage();
  }
}
