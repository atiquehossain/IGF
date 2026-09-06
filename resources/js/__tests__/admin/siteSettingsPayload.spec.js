import {
  collectCustomizerSettings,
  installCustomizerPayloadSubmission,
  payloadByteLength,
} from '../../admin/siteSettingsPayload';

function renderForm(maxBytes = 4194304) {
  document.body.innerHTML = `
    <form id="customizer-form" data-settings-payload-max="${maxBytes}">
      <input id="customizer-settings-payload" name="settings_payload" disabled>
      <div id="customizer-payload-error" hidden tabindex="-1"></div>
      <div data-setting-field data-setting-group="branding" data-setting-key="site_name" data-setting-type="text">
        <input name="settings[branding][site_name]" value="Ignite Foundation" data-setting-control>
      </div>
      <div data-setting-field data-setting-group="header" data-setting-key="show_search" data-setting-type="boolean">
        <input type="hidden" name="settings[header][show_search]" value="0">
        <input type="checkbox" name="settings[header][show_search]" value="1" data-setting-control checked>
      </div>
      <div data-setting-field data-setting-group="design" data-setting-key="card_columns" data-setting-type="select">
        <input type="radio" name="settings[design][card_columns]" value="2" data-setting-control>
        <input type="radio" name="settings[design][card_columns]" value="3" data-setting-control checked>
      </div>
      <section data-setting-field data-setting-group="contact_page" data-setting-key="faqs" data-setting-type="faq_list">
        <article data-faq-item>
          <input data-faq-field="question" name="settings[contact_page][faqs][0][question]" value="How can I help?">
          <textarea data-faq-field="answer" name="settings[contact_page][faqs][0][answer]">Volunteer locally.</textarea>
          <input type="hidden" data-faq-field="is_active" name="settings[contact_page][faqs][0][is_active]" value="0">
          <input type="checkbox" data-faq-field="is_active" name="settings[contact_page][faqs][0][is_active]" value="1">
        </article>
      </section>
    </form>
    <form id="reset-form">
      <input name="settings[branding][site_name]" value="reset form stays enabled">
    </form>`;

  return document.getElementById('customizer-form');
}

describe('Website Customizer bounded payload', () => {
  it('serializes scalar, checkbox, radio, and FAQ controls without duplicate values', () => {
    const form = renderForm();

    expect(collectCustomizerSettings(form)).toEqual({
      branding: { site_name: 'Ignite Foundation' },
      header: { show_search: '1' },
      design: { card_columns: '3' },
      contact_page: {
        faqs: [{
          question: 'How can I help?',
          answer: 'Volunteer locally.',
          is_active: '0',
        }],
      },
    });
  });

  it('submits one JSON field and disables every nested settings control', () => {
    const form = renderForm();
    const payload = form.querySelector('#customizer-settings-payload');
    installCustomizerPayloadSubmission(form);

    const event = new Event('submit', { bubbles: true, cancelable: true });
    form.dispatchEvent(event);

    expect(event.defaultPrevented).toBe(false);
    expect(payload.disabled).toBe(false);
    expect(JSON.parse(payload.value).branding.site_name).toBe('Ignite Foundation');
    expect([...form.querySelectorAll('[name^="settings["]')].every((control) => control.disabled)).toBe(true);
    expect(document.querySelector('#reset-form input').disabled).toBe(false);
  });

  it('keeps editable controls enabled and shows a graceful error when the payload is too large', () => {
    const form = renderForm(20);
    const payload = form.querySelector('#customizer-settings-payload');
    const error = form.querySelector('#customizer-payload-error');
    installCustomizerPayloadSubmission(form);

    const event = new Event('submit', { bubbles: true, cancelable: true });
    form.dispatchEvent(event);

    expect(event.defaultPrevented).toBe(true);
    expect(payload.disabled).toBe(true);
    expect(error.hidden).toBe(false);
    expect(error.textContent).toContain('unusually large');
    expect(form.querySelector('[name="settings[branding][site_name]"]').disabled).toBe(false);
  });

  it('measures UTF-8 bytes rather than JavaScript character count', () => {
    expect(payloadByteLength('ই')).toBe(3);
  });
});
