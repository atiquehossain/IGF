import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import SponsorChild from '@/Pages/sponsor_child.vue';

vi.mock('axios', () => ({
  default: { post: vi.fn() },
}));

const layoutStub = { template: '<main><slot /></main>' };
let formValidationResult = { valid: true };
const formStub = {
  name: 'VForm',
  props: ['modelValue'],
  emits: ['update:modelValue'],
  template: '<form><slot /></form>',
  mounted() {
    this.$emit('update:modelValue', true);
  },
  methods: {
    validate: () => Promise.resolve(formValidationResult),
    resetValidation: () => Promise.resolve(),
  },
};
const textFieldStub = {
  name: 'VTextField',
  inheritAttrs: false,
  props: ['modelValue', 'rules', 'label', 'hideDetails', 'variant'],
  emits: ['update:modelValue'],
  template: '<input v-bind="$attrs" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
  methods: {
    focus() {
      this.$el.focus();
    },
  },
};
const dialogStub = {
  name: 'VDialog',
  props: ['modelValue'],
  template: '<div v-if="modelValue"><slot /></div>',
};
const slotStub = { template: '<div><slot /></div>' };

function sponsorSettings() {
  return {
    eyebrow: 'Sponsor a child',
    title: 'Help a child build a future full of possibility.',
    introduction: 'Help a learner stay in school.',
    hero_image: '/image/banner/slider-2-1588.webp',
    hero_image_alt: 'Children learning together',
    hero_cta_label: 'Start a sponsorship',
    monthly_amount: 1500,
    monthly_period_label: 'per child, each month',
    impact_eyebrow: 'Your impact',
    impact_title: 'One contribution, whole-child support.',
    impact_body: 'Support education and care.',
    benefit_1: 'Quality education',
    form_eyebrow: 'Take the next step',
    form_title: 'Start your sponsorship',
    form_body: 'Choose your support, then tell us how to contact you.',
    selection_step_label: 'Step 1 of 2',
    details_step_label: 'Step 2 of 2',
    edit_selection_label: 'Change sponsorship choice',
    children_field_label: 'Number of children',
    decrease_children_label: 'Sponsor one fewer child',
    increase_children_label: 'Sponsor one more child',
    interval_field_label: 'Contribution interval',
    name_field_label: 'Full name',
    email_field_label: 'Email address',
    phone_field_label: 'Phone number (optional)',
    address_field_label: 'Address (optional)',
    monthly_interval_label: 'Monthly',
    quarterly_interval_label: 'Quarterly',
    semi_annual_interval_label: 'Every six months',
    annual_interval_label: 'Annually',
    child_singular: 'child',
    child_plural: 'children',
    month_singular: 'month',
    month_plural: 'months',
    contribution_summary_label: 'Your {interval} contribution',
    assurances_label: 'Sponsorship assurances',
    assurance_1: 'Your request is private',
    privacy_note: 'Your details are handled with care.',
    submit_label: 'Send sponsorship request',
    sending_label: 'Sending request...',
    confirmation_title: 'Confirm your sponsorship',
    confirmation_body: 'You are requesting to sponsor {count} {children} with a {interval} contribution.',
    confirmation_total_label: 'Total contribution',
    confirmation_note: 'No payment is taken now.',
    confirmation_back_label: 'Go back',
    confirmation_submit_label: 'Confirm request',
    confirmation_sending_label: 'Sending...',
    required_message: '{field} is required',
    minimum_children_message: 'Choose at least one child',
    maximum_children_message: 'Choose 100 children or fewer',
    invalid_email_message: 'Enter a valid email address',
    success_message: 'Sponsorship request submitted.',
    error_message: 'We could not send your request.',
  };
}

function mountSponsor() {
  usePage().props = {
    siteSettings: {
      sponsor_page: sponsorSettings(),
      regional: {
        currency_code: 'BDT',
        currency_symbol: '৳',
        currency_position: 'before',
        number_locale: 'en-BD',
      },
    },
  };

  return mount(SponsorChild, {
    global: {
      plugins: [{
        install(app) {
          app.config.globalProperties.$toast = { success: vi.fn(), error: vi.fn() };
        },
      }],
      stubs: {
        Layout: layoutStub,
        'v-form': formStub,
        'v-text-field': textFieldStub,
        'v-dialog': dialogStub,
        'v-card': slotStub,
        'v-card-title': slotStub,
        'v-card-text': slotStub,
        'v-card-actions': slotStub,
      },
    },
  });
}

function visibleValue(wrapper) {
  return wrapper.element.value ?? wrapper.text();
}

async function continueToDetails(wrapper) {
  await wrapper.get('[data-test="continue-details"]').trigger('click');
  await nextTick();
  await flushPromises();
}

describe('sponsor-child request journey', () => {
  let scrollIntoView;
  let focus;
  let originalScrollIntoView;

  beforeAll(() => {
    originalScrollIntoView = HTMLElement.prototype.scrollIntoView;
    if (!originalScrollIntoView) {
      Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', {
        configurable: true,
        value() {},
        writable: true,
      });
    }
  });

  beforeEach(() => {
    formValidationResult = { valid: true };
    axios.post.mockReset();
    globalThis.axios = axios;
    globalThis.route = vi.fn(name => name);
    scrollIntoView = vi.spyOn(HTMLElement.prototype, 'scrollIntoView').mockImplementation(() => {});
    focus = vi.spyOn(HTMLElement.prototype, 'focus').mockImplementation(() => {});
  });

  afterEach(() => {
    scrollIntoView?.mockRestore();
    focus?.mockRestore();
  });

  afterAll(() => {
    if (originalScrollIntoView) {
      HTMLElement.prototype.scrollIntoView = originalScrollIntoView;
    } else {
      delete HTMLElement.prototype.scrollIntoView;
    }
  });

  test('updates the contribution when interval and child count change', async () => {
    const wrapper = mountSponsor();
    const monthly = wrapper.get('[data-test="interval-monthly"]');
    const quarterly = wrapper.get('[data-test="interval-quarterly"]');
    const semiAnnually = wrapper.get('[data-test="interval-semi_annually"]');
    const annually = wrapper.get('[data-test="interval-annually"]');
    const intervalGroup = wrapper.get('[role="radiogroup"]');

    expect(wrapper.get('[data-test="sponsor-progress"]').text()).toContain('Step 1 of 2');
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuetext')).toBe('Step 1 of 2');
    expect(intervalGroup.attributes('aria-label')).toBe('Contribution interval');
    expect(monthly.attributes('role')).toBe('radio');
    expect(quarterly.attributes('role')).toBe('radio');
    expect(semiAnnually.attributes('role')).toBe('radio');
    expect(annually.attributes('role')).toBe('radio');
    expect(monthly.attributes('aria-checked')).toBe('true');
    expect(quarterly.attributes('aria-checked')).toBe('false');
    expect(semiAnnually.attributes('aria-checked')).toBe('false');
    expect(annually.attributes('aria-checked')).toBe('false');
    expect(monthly.attributes('aria-label')).toBeTruthy();
    expect(semiAnnually.attributes('aria-label')).toBeTruthy();
    expect(annually.attributes('aria-label')).toBeTruthy();
    expect(wrapper.get('[data-test="child-decrease"]').attributes('aria-label')).toBeTruthy();
    expect(wrapper.get('[data-test="child-increase"]').attributes('aria-label')).toBeTruthy();
    expect(visibleValue(wrapper.get('[data-test="child-count"]'))).toContain('1');
    expect(wrapper.get('[data-test="contribution-total"]').text()).toContain('1,500');

    await quarterly.trigger('click');
    expect(monthly.attributes('aria-checked')).toBe('false');
    expect(quarterly.attributes('aria-checked')).toBe('true');
    expect(wrapper.get('[data-test="contribution-total"]').text()).toContain('4,500');

    await wrapper.get('[data-test="child-increase"]').trigger('click');
    expect(visibleValue(wrapper.get('[data-test="child-count"]'))).toContain('2');
    expect(wrapper.get('[data-test="contribution-total"]').text()).toContain('9,000');

    await wrapper.get('[data-test="child-decrease"]').trigger('click');
    expect(visibleValue(wrapper.get('[data-test="child-count"]'))).toContain('1');

    wrapper.unmount();
  });

  test('moves between the mutually exclusive choice and contact-detail steps', async () => {
    const wrapper = mountSponsor();

    expect(wrapper.get('[data-test="sponsor-progress"]').text()).toContain('Step 1 of 2');
    expect(wrapper.find('[data-test="sponsorship-details"]').exists()).toBe(false);
    expect(wrapper.find('[data-test="continue-details"]').exists()).toBe(true);
    await continueToDetails(wrapper);

    expect(wrapper.find('[data-test="continue-details"]').exists()).toBe(false);
    expect(wrapper.get('[data-test="sponsorship-details"]').exists()).toBe(true);
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuetext')).toBe('Step 2 of 2');
    expect(scrollIntoView).toHaveBeenCalled();
    expect(focus).toHaveBeenCalled();

    const changeChoice = wrapper.findAll('button')
      .find(button => button.text().includes('Change sponsorship choice'));
    expect(changeChoice).toBeTruthy();
    await changeChoice.trigger('click');
    await nextTick();

    expect(wrapper.find('[data-test="sponsorship-details"]').exists()).toBe(false);
    expect(wrapper.get('[data-test="continue-details"]').exists()).toBe(true);
    expect(wrapper.get('[data-test="sponsor-progress"]').text()).toContain('Step 1 of 2');
    expect(scrollIntoView).toHaveBeenCalledTimes(2);

    wrapper.unmount();
  });

  test('rejects a whitespace-only name before opening confirmation or posting', async () => {
    const wrapper = mountSponsor();
    await continueToDetails(wrapper);

    const nameField = wrapper.findAllComponents(textFieldStub)
      .find(field => field.attributes('autocomplete') === 'name');
    expect(nameField).toBeTruthy();
    expect(nameField.props('rules')[0]('   ')).not.toBe(true);

    formValidationResult = { valid: false };
    await nameField.setValue('   ');
    await wrapper.get('input[autocomplete="email"]').setValue('sponsor@example.test');
    await wrapper.get('[data-test="sponsorship-details"]').trigger('submit');
    await flushPromises();

    expect(wrapper.find('[data-test="confirm-sponsorship"]').exists()).toBe(false);
    expect(axios.post).not.toHaveBeenCalled();

    wrapper.unmount();
  });

  test('submits the existing sponsorship API contract after confirmation', async () => {
    axios.post.mockResolvedValue({ data: { status: true, message: 'Request received.' } });
    const wrapper = mountSponsor();

    await wrapper.get('[data-test="interval-annually"]').trigger('click');
    await wrapper.get('[data-test="child-increase"]').trigger('click');
    await continueToDetails(wrapper);

    await wrapper.get('input[autocomplete="name"]').setValue('Community Sponsor');
    await wrapper.get('input[autocomplete="email"]').setValue('sponsor@example.test');
    await wrapper.get('input[autocomplete="tel"]').setValue('+8801700000000');
    await wrapper.get('input[autocomplete="street-address"]').setValue('Dhaka');
    expect(wrapper.get('[data-test="submit-sponsorship"]').attributes('type')).toBe('submit');
    await wrapper.get('[data-test="sponsorship-details"]').trigger('submit');
    await flushPromises();

    expect(axios.post).not.toHaveBeenCalled();
    const confirm = wrapper.get('[data-test="confirm-sponsorship"]');
    await confirm.trigger('click');
    await flushPromises();

    expect(axios.post).toHaveBeenCalledTimes(1);
    expect(axios.post).toHaveBeenCalledWith('frontend.sponsorship.store', {
      name: 'Community Sponsor',
      email: 'sponsor@example.test',
      phone: '+8801700000000',
      address: 'Dhaka',
      number_of_children: 2,
      contribution_interval: 'annually',
      sponsorshipAmount: 36000,
    });
    expect(wrapper.get('[data-test="sponsorship-success"]').text()).toContain('Request received.');
    expect(wrapper.find('[data-test="sponsorship-details"]').exists()).toBe(false);
    expect(wrapper.find('[data-test="continue-details"]').exists()).toBe(false);
    await nextTick();
    expect(wrapper.get('[data-test="sponsorship-success"]').exists()).toBe(true);

    wrapper.unmount();
  });
});
