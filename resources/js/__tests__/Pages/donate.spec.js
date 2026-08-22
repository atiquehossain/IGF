import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Donate from '@/Pages/donate.vue';
import donateSource from '@/Pages/donate.vue?raw';
import paymentResultSource from '@/Pages/payment_success.vue?raw';

vi.mock('axios', () => ({
  default: { get: vi.fn(), post: vi.fn() },
}));

const layoutStub = { template: '<main><slot /></main>' };
const formStub = {
  name: 'VForm',
  props: ['modelValue'],
  emits: ['update:modelValue'],
  template: '<form><slot /></form>',
  methods: { validate: () => Promise.resolve({ valid: true }) },
};
const textFieldStub = {
  name: 'VTextField',
  inheritAttrs: false,
  props: ['modelValue', 'prefix', 'suffix', 'rules', 'label', 'hideDetails', 'variant'],
  emits: ['update:modelValue'],
  template: '<input v-bind="$attrs" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
};
const buttonStub = {
  name: 'VBtn',
  inheritAttrs: false,
  props: ['disabled'],
  template: '<button v-bind="$attrs" :disabled="disabled"><slot /></button>',
};

function contrastRatio(foreground, background) {
  const luminance = (hex) => {
    const channels = hex.match(/[a-f\d]{2}/gi).map(value => parseInt(value, 16) / 255);
    const [red, green, blue] = channels.map(value => value <= 0.04045
      ? value / 12.92
      : ((value + 0.055) / 1.055) ** 2.4);

    return (0.2126 * red) + (0.7152 * green) + (0.0722 * blue);
  };
  const foregroundLuminance = luminance(foreground);
  const backgroundLuminance = luminance(background);

  return (Math.max(foregroundLuminance, backgroundLuminance) + 0.05)
    / (Math.min(foregroundLuminance, backgroundLuminance) + 0.05);
}

function settings() {
  return {
    checkout_layout: 'centered',
    card_style: 'soft',
    show_hero: false,
    show_intro_panel: false,
    show_help_card: false,
    show_form_badge: false,
    show_required_hint: false,
    show_custom_amount: true,
    show_gateway_note: false,
    show_legal_links: false,
    form_badge: 'Secure donation',
    form_title: 'Make a donation',
    checkout_steps_accessible_label: 'Donation steps',
    gift_step_label: 'Gift choices',
    checkout_step_label: 'Details & payment',
    continue_gift_label: 'Continue to details & payment',
    edit_gift_label: 'Edit gift choices',
    gift_subtitle: 'Every contribution strengthens the cause you choose.',
    frequency_heading: 'Choose how often',
    frequency_label: 'One-time',
    frequency_daily_label: 'Daily',
    frequency_weekly_label: 'Weekly',
    frequency_monthly_label: 'Monthly',
    frequency_coming_soon_label: 'Coming soon',
    frequency_help: 'One-time gifts are charged once.',
    frequency_accessible_label: 'Donation frequency',
    amount_legend: 'Donation amount',
    suggested_amounts_label: 'Suggested amounts',
    other_amount_label: 'Other amount (BDT) *',
    other_amount_option_label: 'Other amount',
    other_amount_option_help: 'Enter your own amount',
    amount_button_count: '5',
    amount_1: 500,
    amount_2: 1000,
    amount_3: 2500,
    amount_4: 5000,
    amount_5: 10000,
    amount_1_impact: 'Learning materials',
    amount_2_impact: 'Family support',
    amount_3_impact: 'Community programs',
    amount_4_impact: 'An active project',
    amount_5_impact: 'Long-term impact',
    featured_amount_index: '4',
    cause_legend: 'Where should your gift go?',
    cause_field_label: 'Select a cause *',
    cause_placeholder: 'Choose a cause',
    cause_help: 'Choose an active cause.',
    causes_unavailable_message: 'No causes are available.',
    project_field_label: 'Choose a project',
    project_placeholder: 'Support the broader program',
    project_help: 'Choose a project or support the whole program.',
    destination_label: 'Your gift will support',
    destination_unrestricted_explanation: 'The foundation directs this gift within the managed purpose.',
    destination_category_explanation: 'Support the whole program or one project.',
    destination_page_explanation: 'This cause supports the exact project shown.',
    payment_method_legend: 'Choose a payment method',
    payment_method_help: 'You will complete payment on the provider website.',
    payment_method_required_message: 'Choose an available payment method to continue.',
    payment_method_unavailable_label: 'Currently unavailable',
    payment_methods_unavailable_message: 'No payment methods are available.',
    details_legend: 'Your details',
    name_field_label: 'Full name *',
    email_field_label: 'Email *',
    phone_field_label: 'Phone *',
    address_field_label: 'Address *',
    name_required_message: 'Name is required',
    email_required_message: 'Email is required',
    invalid_email_message: 'Enter a valid email',
    phone_required_message: 'Phone is required',
    address_required_message: 'Address is required',
    amount_required_message: 'Amount is required',
    minimum_amount_message: 'Amount must be at least {currency} 10',
    maximum_amount_message: 'Amount must be {currency} 500,000 or less',
    amount_precision_message: 'Enter no more than two decimal places.',
    privacy_note: 'Payment details stay with the provider.',
    submit_label: 'Continue to secure payment',
    submit_with_amount_label: 'Continue securely with {amount}',
    summary_heading: 'Your donation',
    summary_frequency_label: 'Frequency',
    summary_amount_label: 'Amount',
    summary_destination_label: 'Destination',
    summary_payment_label: 'Payment',
    summary_pending_label: 'Not selected',
    summary_help: 'Review your choices before continuing.',
  };
}

function mountDonate({ methods, data = {}, url = '/donate?amount=500' } = {}) {
  usePage().props = {
    data: {
      donationTypes: [{ uuid: 'education', name: 'Education' }],
      selectedUUID: 'education',
      checkout_key: 'checkout-key-initial',
      paymentMethods: methods || [
        { key: 'bkash', label: 'bKash', description: 'Pay from bKash', logos: [{ src: '/image/payment-methods/bkash-reference.svg' }], enabled: true, available: true },
        { key: 'nagad', label: 'Nagad', description: 'Pay from Nagad', logos: [{ src: '/image/payment-methods/nagad.png' }], enabled: true, available: false, unavailable_reason: 'Nagad is awaiting provider activation.' },
        { key: 'card', label: 'Card', description: 'Pay by card', networks: ['Visa', 'American Express'], logos: [{ src: '/image/payment-methods/visa-reference.svg' }, { src: '/image/payment-methods/amex.png' }], enabled: true, available: true },
        { key: 'hidden', label: 'Admin disabled', enabled: false, available: true },
      ],
      ...data,
    },
    siteSettings: {
      donation_page: settings(),
      contact: {},
      regional: {},
    },
  };
  usePage().url = url;

  return mount(Donate, {
    global: {
      plugins: [{
        install(app) {
          app.config.globalProperties.$toast = { error: vi.fn() };
        },
      }],
      stubs: {
        App: layoutStub,
        Layout: layoutStub,
        'v-form': formStub,
        'v-text-field': textFieldStub,
        'v-btn': buttonStub,
      },
    },
  });
}

describe('donation payment methods', () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.post.mockReset();
    globalThis.route = vi.fn(name => name);
  });

  test('uses WCAG-safe dark-orange tokens behind small white interactive text', () => {
    expect(contrastRatio('#ffffff', '#9c4500')).toBeGreaterThanOrEqual(4.5);
    expect(contrastRatio('#ffffff', '#783300')).toBeGreaterThanOrEqual(4.5);
    expect(donateSource).toContain('--action-orange:#9c4500');
    expect(donateSource).toContain('background:var(--action-orange)!important');
    expect(donateSource).toContain('background:var(--action-orange-hover)!important');
    expect(paymentResultSource).toContain('--action-orange:#9c4500');
    expect(paymentResultSource).toContain('.igf-button--primary{border-color:var(--action-orange);background:var(--action-orange);color:#fff}');
  });

  test('renders enabled choices as native radios and explains provider-unavailable choices', async () => {
    axios.post.mockResolvedValue({ data: { status: false, message: 'Test response' } });
    const wrapper = mountDonate();
    const choices = wrapper.findAll('.igf-payment-method');
    const radios = wrapper.findAll('input[name="payment_method"]');

    expect(choices).toHaveLength(3);
    expect(radios).toHaveLength(3);
    expect(radios.every(radio => radio.attributes('required') !== undefined)).toBe(true);
    expect(wrapper.text()).not.toContain('Admin disabled');
    expect(wrapper.get('.igf-fieldset legend').text()).toContain('Donation amount');
    expect(wrapper.findAll('.igf-frequency-tabs button')).toHaveLength(4);
    expect(wrapper.findAll('.igf-frequency-tabs button[disabled]')).toHaveLength(3);
    expect(wrapper.get('.igf-frequency-tabs .is-selected').text()).toBe('One-time');
    expect(wrapper.findAll('[data-test="suggested-amount"]')).toHaveLength(5);
    expect(wrapper.findAll('[data-test="custom-amount-option"]')).toHaveLength(1);
    expect(wrapper.get('.igf-amount-options .is-featured small').text()).toBe('An active project');
    expect(wrapper.get('.igf-payment-methods legend').text()).toBe('Choose a payment method');
    expect(wrapper.get('#payment-method-nagad').attributes('disabled')).toBeDefined();
    expect(wrapper.get('#payment-method-nagad-unavailable').text()).toBe('Nagad is awaiting provider activation.');
    expect(wrapper.findAll('.igf-payment-method__status')).toHaveLength(0);
    expect(wrapper.get('.igf-payment-method__networks').text()).toBe('Visa · American Express');
    expect(wrapper.findAll('.igf-payment-method__logos img')).toHaveLength(4);
    expect(wrapper.get('label[for="payment-method-bkash"] img').attributes()).toMatchObject({
      src: '/image/payment-methods/bkash-reference.svg',
      alt: '',
      width: '122',
      height: '44',
    });
    expect(wrapper.get('label[for="payment-method-nagad"] .igf-payment-method__logos').attributes('aria-hidden')).toBe('true');
    expect(wrapper.get('label[for="payment-method-nagad"] img').attributes('src')).toBe('/image/payment-methods/nagad.png');
    expect(wrapper.findAll('label[for="payment-method-card"] img').map(image => image.attributes('src'))).toEqual([
      '/image/payment-methods/visa-reference.svg',
      '/image/payment-methods/amex.png',
    ]);

    const unavailableRule = donateSource.match(/\.igf-payment-method\.is-unavailable\s*\{([^}]*)\}/)?.[1] || '';
    expect(unavailableRule).not.toContain('opacity');
    expect(donateSource).not.toContain('filter:grayscale');

    wrapper.vm.isFormValid = true;
    await wrapper.get('#payment-method-card').setValue();
    await nextTick();

    expect(wrapper.vm.donation.payment_method).toBe('card');
    expect(wrapper.get('label[for="payment-method-card"]').classes()).toContain('is-selected');
    expect(wrapper.vm.canSubmit).toBe(true);

    await wrapper.vm.submitDonation();
    await flushPromises();
    expect(axios.post).toHaveBeenCalledWith('frontend.donate.store', expect.objectContaining({
      payment_method: 'card',
      frequency: 'one_time',
      checkout_key: 'checkout-key-initial',
    }));
  });

  test('preselects the editor-highlighted gift when the URL does not request an amount', async () => {
    const wrapper = mountDonate({ url: '/donate' });
    await nextTick();

    expect(wrapper.vm.donation.amount).toBe(5000);
    expect(wrapper.get('.igf-amount-options button.is-selected small').text()).toBe('An active project');
  });

  test('switches coherently between suggested and custom amounts', async () => {
    const wrapper = mountDonate();
    await nextTick();

    expect(wrapper.find('[data-test="custom-amount-field"]').exists()).toBe(false);
    expect(wrapper.get('[data-test="suggested-amount"].is-selected').text()).toContain('৳500');

    await wrapper.get('[data-test="custom-amount-option"]').trigger('click');
    await nextTick();
    expect(wrapper.vm.customAmountActive).toBe(true);
    expect(wrapper.vm.donation.amount).toBe('');

    await wrapper.get('[data-test="custom-amount-field"]').setValue('750');
    expect(wrapper.vm.donation.amount).toBe('750');
    expect(wrapper.get('[data-test="donation-review"]').text()).toContain('৳750');

    await wrapper.findAll('[data-test="suggested-amount"]')[1].trigger('click');
    await nextTick();
    expect(wrapper.vm.customAmountActive).toBe(false);
    expect(wrapper.vm.donation.amount).toBe(1000);
    expect(wrapper.find('[data-test="custom-amount-field"]').exists()).toBe(false);
  });

  test('opens custom mode for a deep-linked non-suggested amount', async () => {
    const wrapper = mountDonate({ url: '/donate?custom_amount=750' });
    await nextTick();

    expect(wrapper.vm.customAmountActive).toBe(true);
    expect(wrapper.vm.donation.amount).toBe(750);
    expect(wrapper.get('[data-test="custom-amount-field"]').attributes('value')).toBe('750');
    expect(wrapper.find('.igf-amount-options [data-test="suggested-amount"].is-selected').exists()).toBe(false);
  });

  test('reveals details only after the gift and destination are ready', async () => {
    const wrapper = mountDonate();
    await nextTick();

    expect(wrapper.vm.giftSelectionComplete).toBe(true);
    expect(wrapper.get('#donation-step-checkout').attributes('style')).toContain('display: none');
    expect(wrapper.get('.igf-step-continue').attributes('disabled')).toBeUndefined();
    const checkoutFocus = vi.spyOn(wrapper.get('#donation-checkout-heading').element, 'focus');
    const giftFocus = vi.spyOn(wrapper.get('#donation-gift-heading').element, 'focus');

    await wrapper.get('.igf-step-continue').trigger('click');
    await nextTick();
    expect(wrapper.vm.checkoutRevealed).toBe(true);
    expect(wrapper.get('#donation-step-checkout').attributes('style') || '').not.toContain('display: none');
    expect(checkoutFocus).toHaveBeenCalledOnce();

    await wrapper.get('.igf-checkout-section__heading button').trigger('click');
    await nextTick();
    expect(wrapper.vm.checkoutRevealed).toBe(false);
    expect(giftFocus).toHaveBeenCalledOnce();
  });

  test('updates the live review and amount-aware secure-payment action', async () => {
    const wrapper = mountDonate();
    await nextTick();
    const review = wrapper.get('[data-test="donation-review"]');

    expect(review.attributes('aria-live')).toBe('polite');
    expect(review.text()).toContain('৳500');
    expect(review.text()).toContain('Education');
    expect(review.text()).toContain('Not selected');
    expect(wrapper.get('.igf-submit').text()).toContain('Continue securely with ৳500');

    await wrapper.get('#payment-method-card').setValue();
    await nextTick();
    expect(review.text()).toContain('Card');
  });

  test('shows backend-confirmed destinations and keeps project selection compatible when the cause changes', async () => {
    const wrapper = mountDonate({
      data: {
        donationTypes: [
          {
            uuid: 'program-cause', slug: 'program-cause', name: 'Education program',
            destination_type: 'category', destination_name: 'Education', project_selection: 'optional',
            projects: [{ uuid: 'project-one', name: 'Project One' }, { uuid: 'project-two', name: 'Project Two' }],
          },
          {
            uuid: 'fixed-cause', slug: 'fixed-cause', name: 'School rebuild',
            destination_type: 'page', destination_name: 'School Rebuild Project', project_selection: 'fixed',
            projects: [{ uuid: 'fixed-project', name: 'School Rebuild Project' }],
          },
          {
            uuid: 'general-cause', slug: 'general-cause', name: 'General support',
            destination_type: 'unrestricted', destination_name: 'Where it is needed most', project_selection: 'none', projects: [],
          },
        ],
        selectedUUID: 'program-cause',
        selectedProjectUUID: 'project-two',
        selectedDestination: { type: 'category', name: 'Education', project_name: 'Project Two' },
        selection_warning: 'A linked choice needed review.',
      },
    });
    await nextTick();

    expect(wrapper.get('.igf-selection-warning').text()).toBe('A linked choice needed review.');
    expect(wrapper.get('#donation-project').attributes('disabled')).toBeUndefined();
    expect(wrapper.vm.donation.project_uuid).toBe('project-two');
    expect(wrapper.get('.igf-destination-summary strong').text()).toBe('Project Two');
    expect(wrapper.get('.igf-destination-summary p').text()).toBe('Support the whole program or one project.');

    await wrapper.get('#donation-cause').setValue('fixed-cause');
    expect(wrapper.find('.igf-selection-warning').exists()).toBe(false);
    expect(wrapper.vm.donation.project_uuid).toBe('fixed-project');
    expect(wrapper.find('#donation-project').exists()).toBe(false);
    expect(wrapper.get('.igf-fixed-project strong').text()).toBe('School Rebuild Project');
    expect(wrapper.get('.igf-fixed-project small').text()).toBe('This cause supports the exact project shown.');
    expect(wrapper.get('.igf-destination-summary strong').text()).toBe('School Rebuild Project');
    expect(wrapper.get('.igf-destination-summary p').text()).toBe('This cause supports the exact project shown.');

    await wrapper.get('#donation-cause').setValue('general-cause');
    expect(wrapper.find('#donation-project').exists()).toBe(false);
    expect(wrapper.vm.donation.project_uuid).toBe('');
    expect(wrapper.get('.igf-destination-summary strong').text()).toBe('General support');
  });

  test('posts the guided project UUID and treats a project change as a new payment attempt', async () => {
    axios.post.mockResolvedValue({ data: { status: false, message: 'Test response' } });
    axios.get.mockResolvedValue({ data: { status: true, checkout_key: 'project-key-refreshed' } });
    const wrapper = mountDonate({
      data: {
        donationTypes: [{
          uuid: 'program-cause', slug: 'program-cause', name: 'Education program',
          destination_type: 'category', destination_name: 'Education', project_selection: 'optional',
          projects: [{ uuid: 'project-one', name: 'Project One' }, { uuid: 'project-two', name: 'Project Two' }],
        }],
        selectedUUID: 'program-cause',
        selectedProjectUUID: 'project-one',
      },
    });
    wrapper.vm.isFormValid = true;
    await wrapper.get('#payment-method-card').setValue();
    await wrapper.vm.submitDonation();
    await flushPromises();

    expect(axios.post).toHaveBeenLastCalledWith('frontend.donate.store', expect.objectContaining({
      payment_cause: 'program-cause',
      project_uuid: 'project-one',
    }));

    await wrapper.get('#donation-project').setValue('project-two');
    expect(wrapper.vm.checkoutKeyNeedsRefresh).toBe(true);
    await wrapper.vm.submitDonation();
    await flushPromises();
    expect(axios.get).toHaveBeenCalledTimes(1);
    expect(axios.post).toHaveBeenLastCalledWith('frontend.donate.store', expect.objectContaining({
      project_uuid: 'project-two',
      checkout_key: 'project-key-refreshed',
    }));
  });

  test('keeps unavailable brands visible but announces one shared message when no method is operational', () => {
    const wrapper = mountDonate({
      methods: [
        { key: 'bkash', label: 'bKash', logos: [{ src: '/image/payment-methods/bkash-reference.svg' }], enabled: true, available: false },
        { key: 'nagad', label: 'Nagad', logos: [{ src: '/image/payment-methods/nagad.png' }], enabled: true, available: false },
      ],
    });

    expect(wrapper.findAll('.igf-payment-method__logos img')).toHaveLength(2);
    expect(wrapper.findAll('.igf-payment-method__unavailable')).toHaveLength(0);
    expect(wrapper.findAll('#payment-methods-unavailable')).toHaveLength(1);
    expect(wrapper.get('#payment-methods-unavailable').text()).toBe('No payment methods are available.');
    expect(wrapper.get('#payment-method-bkash').attributes('aria-describedby')).toBe('payment-methods-unavailable');
    expect(wrapper.get('#payment-method-nagad').attributes('aria-describedby')).toBe('payment-methods-unavailable');
  });

  test('allows only method-matched local brand marks and falls back for missing or unknown logos', () => {
    const wrapper = mountDonate({
      methods: [
        {
          key: 'bkash',
          label: 'bKash',
          logos: [
            { src: '/image/payment-methods/bkash-reference.svg' },
            { src: '/image/payment-methods/bkash-reference.svg' },
            { src: '/image/payment-methods/bkash.png' },
            { src: '/image/payment-methods/nagad.png' },
            { src: 'https://example.test/bkash.png' },
            { src: '/image/payment-methods/../logo.png' },
          ],
          enabled: true,
          available: true,
        },
        { key: 'card', label: 'Card', logos: [], enabled: true, available: true },
        { key: 'unknown', label: 'Unknown', logos: [{ src: '/image/payment-methods/bkash-reference.svg' }], enabled: true, available: true },
      ],
    });

    expect(wrapper.findAll('.igf-payment-method__logos img')).toHaveLength(1);
    expect(wrapper.get('label[for="payment-method-bkash"] img').attributes('src')).toBe('/image/payment-methods/bkash-reference.svg');
    expect(wrapper.get('label[for="payment-method-card"] .igf-payment-method__icon i').classes()).toEqual(expect.arrayContaining(['fa-regular', 'fa-credit-card']));
    expect(wrapper.get('label[for="payment-method-unknown"] .igf-payment-method__icon i').classes()).toEqual(expect.arrayContaining(['fa-solid', 'fa-mobile-screen-button']));
    expect(wrapper.html()).not.toContain('https://example.test/bkash.png');
    expect(wrapper.html()).not.toContain('../logo.png');
  });

  test('uses the provider amount limits and rejects values with more than two decimals', async () => {
    const wrapper = mountDonate();
    await wrapper.get('[data-test="custom-amount-option"]').trigger('click');
    await nextTick();
    const amountInput = wrapper.get('input[type="number"]');

    expect(amountInput.attributes('min')).toBe('10');
    expect(amountInput.attributes('max')).toBe('500000');
    expect(amountInput.attributes('step')).toBe('0.01');

    wrapper.vm.isFormValid = true;
    wrapper.vm.donation.amount = '10.001';
    await wrapper.get('#payment-method-bkash').setValue();
    await nextTick();

    expect(wrapper.vm.amountRules[3]('10.001')).toBe('Enter no more than two decimal places.');
    expect(wrapper.vm.canSubmit).toBe(false);

    wrapper.vm.donation.amount = '10.000';
    await nextTick();
    expect(wrapper.vm.canSubmit).toBe(false);

    wrapper.vm.donation.amount = '1e2';
    await nextTick();
    expect(wrapper.vm.canSubmit).toBe(false);

    wrapper.vm.donation.amount = '010.00';
    await nextTick();
    expect(wrapper.vm.canSubmit).toBe(false);

    wrapper.vm.donation.amount = '10.01';
    await nextTick();
    expect(wrapper.vm.canSubmit).toBe(true);
  });

  test('shows the localized empty state when no methods are enabled', () => {
    const wrapper = mountDonate({
      methods: [{ key: 'bkash', label: 'bKash', enabled: false, available: true }],
    });

    expect(wrapper.find('[data-test="payment-method-options"]').exists()).toBe(false);
    expect(wrapper.get('.igf-payment-methods [role="status"]').text()).toBe('No payment methods are available.');
    expect(wrapper.vm.canSubmit).toBe(false);
  });

  test('announces the localized required-method error after an attempted submission', async () => {
    const wrapper = mountDonate();
    wrapper.vm.isFormValid = true;
    await nextTick();

    expect(wrapper.vm.canAttemptSubmit).toBe(true);
    expect(wrapper.get('.igf-submit').attributes('disabled')).toBeUndefined();
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(wrapper.get('#payment-method-error').text()).toBe('Choose an available payment method to continue.');
    expect(wrapper.get('.igf-payment-methods').attributes('aria-invalid')).toBe('true');
    expect(axios.post).not.toHaveBeenCalled();
  });

  test('retries an unchanged canonical payload with the same server checkout key', async () => {
    axios.post.mockResolvedValue({ data: { status: false, message: 'Retry this attempt.' } });
    const wrapper = mountDonate();
    wrapper.vm.isFormValid = true;
    await wrapper.get('#payment-method-bkash').setValue();
    await nextTick();

    await wrapper.vm.submitDonation();
    await wrapper.vm.submitDonation();
    await flushPromises();

    expect(axios.get).not.toHaveBeenCalled();
    expect(axios.post).toHaveBeenCalledTimes(2);
    expect(axios.post.mock.calls.map(call => call[1].checkout_key)).toEqual([
      'checkout-key-initial',
      'checkout-key-initial',
    ]);
  });

  test('fetches a fresh server key before submitting a materially changed payload', async () => {
    axios.post.mockResolvedValue({ data: { status: false, message: 'Test response' } });
    axios.get.mockResolvedValue({ data: { status: true, checkout_key: 'checkout-key-refreshed' } });
    const wrapper = mountDonate();
    wrapper.vm.isFormValid = true;
    await wrapper.get('#payment-method-card').setValue();
    await nextTick();

    await wrapper.vm.submitDonation();
    wrapper.vm.donation.amount = '750.00';
    await nextTick();
    expect(wrapper.vm.checkoutKeyNeedsRefresh).toBe(true);

    await wrapper.vm.submitDonation();
    await flushPromises();

    expect(axios.get).toHaveBeenCalledTimes(1);
    expect(axios.get).toHaveBeenCalledWith('frontend.donate.checkout-key', {
      headers: { Accept: 'application/json' },
    });
    expect(axios.post).toHaveBeenCalledTimes(2);
    expect(axios.post.mock.calls[1][1]).toEqual(expect.objectContaining({
      amount: '750.00',
      checkout_key: 'checkout-key-refreshed',
    }));
  });

  test('adopts a server replacement key without automatically retrying the charge', async () => {
    axios.post.mockRejectedValue({
      response: {
        data: {
          status: false,
          code: 'IDEMPOTENCY_CONFLICT',
          message: 'Start a new payment attempt.',
          replacement_checkout_key: 'checkout-key-replacement',
        },
      },
    });
    const wrapper = mountDonate();
    wrapper.vm.isFormValid = true;
    await wrapper.get('#payment-method-bkash').setValue();
    await nextTick();

    await wrapper.vm.submitDonation();
    await flushPromises();

    expect(axios.post).toHaveBeenCalledTimes(1);
    expect(wrapper.vm.donation.checkout_key).toBe('checkout-key-replacement');
    expect(wrapper.vm.checkoutKeyNeedsRefresh).toBe(false);
  });
});
