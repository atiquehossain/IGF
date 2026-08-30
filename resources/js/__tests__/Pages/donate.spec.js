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

const fullDonationCatalog = [
  ['where-it-is-needed-most', 'Where it is needed most', 'hands-heart', 'unrestricted'],
  ['education', 'Education', 'graduation-cap', 'restricted_fund'],
  ['zakat', 'Donate Your Zakat', 'moon', 'restricted_fund'],
  ['sadaqah', 'Donate Your Sadaqah', 'hand-heart', 'restricted_fund'],
  ['food-support', 'Food Support', 'food', 'page'],
  ['emergency-relief', 'Emergency Relief', 'emergency', 'restricted_fund'],
  ['orphan-support', 'Orphan Shelter & Support', 'children', 'restricted_fund'],
  ['school-stationery', 'School Stationery', 'stationery', 'page'],
  ['school-uniforms', 'School Uniforms', 'uniform', 'page'],
  ['school-meals', 'School Meals', 'meals', 'page'],
  ['adopt-a-school', 'Adopt a School', 'school', 'restricted_fund'],
  ['ramadan-iftar', 'Ramadan Iftar', 'moon', 'page'],
  ['qurbani', 'Qurbani', 'qurbani', 'restricted_fund'],
  ['pure-water-and-sanitation', 'Pure Water & Sanitation', 'water', 'page'],
  ['women-empowerment', 'Women Empowerment', 'women', 'restricted_fund'],
  ['youth-development', 'Youth Development', 'youth', 'page'],
  ['street-children-education', 'Street Children Education', 'street-education', 'page'],
].map(([slug, name, iconKey, destinationType]) => {
  const fixedProject = destinationType === 'page'
    ? [{ uuid: `${slug}-project`, name: `${name} Project` }]
    : [];

  return {
    uuid: slug,
    slug,
    url: `/donate/${slug}`,
    name,
    description: `Support ${name}.`,
    image: '',
    icon_key: iconKey,
    destination_type: destinationType,
    destination_name: destinationType === 'unrestricted' ? name : `${name} Fund`,
    project_selection: destinationType === 'page' ? 'fixed' : 'none',
    projects: fixedProject,
  };
});

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
    show_help_card: false,
    show_form_badge: false,
    show_required_hint: false,
    show_custom_amount: true,
    show_gateway_note: false,
    show_legal_links: false,
    cause_gallery_eyebrow: 'Choose your impact',
    cause_gallery_title: 'Support a cause',
    cause_gallery_introduction: 'Select the work you want your donation to support.',
    cause_tabs_accessible_label: 'Donation cause categories',
    cause_tabs_all_label: 'All causes',
    cause_card_cta_label: 'Donate now',
    cause_catalog_back_label: 'All donation causes',
    selected_cause_eyebrow: 'Your selected cause',
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

function mountDonate({ methods, data = {}, pageSettings = {}, url = '/donate/education?amount=500', attachTo } = {}) {
  usePage().props = {
    data: {
      pageMode: 'detail',
      catalogUrl: '/donate',
      donationTypes: [{
        uuid: 'education',
        slug: 'education',
        url: '/donate/education',
        name: 'Education',
        description: 'Support Education.',
        destination_type: 'restricted_fund',
        destination_name: 'Education Fund',
        project_selection: 'none',
        projects: [],
      }],
      selectedUUID: 'education',
      selectedCauseSlug: 'education',
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
      donation_page: { ...settings(), ...pageSettings },
      contact: {},
      regional: {},
    },
  };
  usePage().url = url;

  return mount(Donate, {
    attachTo,
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

  test('renders editor-managed catalog cards as accessible cause links', async () => {
    const wrapper = mountDonate({
      pageSettings: {
        show_cause_gallery: true,
        cause_gallery_eyebrow: 'Choose your impact',
        cause_gallery_title: 'Give where it matters',
        cause_gallery_introduction: 'Select a cause to begin your secure donation.',
        cause_card_cta_label: 'Support this cause',
      },
      data: {
        pageMode: 'catalog',
        donationTypes: [
          {
            uuid: 'education',
            slug: 'education',
            url: '/donate/education',
            name: 'Education',
            description: 'Help children stay in school.',
            image: '/storage/donation-types/education.jpg',
          },
          {
            uuid: 'relief',
            slug: 'relief',
            url: '/donate/relief',
            name: 'Emergency relief',
            description: 'Respond quickly when families face a crisis.',
            image: '',
          },
        ],
        selectedUUID: null,
        checkout_key: null,
        paymentMethods: [],
        donationFrequencies: [],
      },
      url: '/donate',
    });
    await nextTick();

    const gallery = wrapper.get('.igf-donate-causes');
    const cards = gallery.findAll('[data-test="donation-cause-card"]');
    const links = gallery.findAll('[data-test="donation-cause-link"]');

    expect(gallery.text()).toContain('Choose your impact');
    expect(gallery.text()).toContain('Give where it matters');
    expect(gallery.text()).toContain('Select a cause to begin your secure donation.');
    expect(cards).toHaveLength(2);
    expect(cards[0].text()).toContain('Education');
    expect(cards[0].text()).toContain('Help children stay in school.');
    expect(cards[0].find('img').attributes('src')).toBe('/storage/donation-types/education.jpg');
    expect(cards[0].find('.igf-donation-cause-card__placeholder').exists()).toBe(false);
    expect(cards[1].find('img').exists()).toBe(false);
    expect(cards[1].find('.igf-donation-cause-card__placeholder').exists()).toBe(true);
    expect(links.map(link => link.element.tagName)).toEqual(['A', 'A']);
    expect(links.map(link => link.attributes('href'))).toEqual(['/donate/education', '/donate/relief']);
    expect(links[0].attributes('aria-describedby')).toBe('donation-cause-education');
    expect(links[0].attributes('aria-label')).toBe('Support this cause: Education');
    expect(links[1].text()).toContain('Support this cause');
    expect(wrapper.find('.igf-donation-cause-card__selected').exists()).toBe(false);
  });

  test('filters every cause through dynamic accessible tabs with roving keyboard focus', async () => {
    const causes = [
      {
        uuid: 'school-books', slug: 'school-books', url: '/donate/school-books', name: 'School Books',
        description: 'Provide learning materials.', group_uuid: 'education-group', image: '',
      },
      {
        uuid: 'school-meals', slug: 'school-meals', url: '/donate/school-meals', name: 'School Meals',
        description: 'Provide school-day meals.', group_uuid: 'education-group', image: '',
      },
      {
        uuid: 'flood-relief', slug: 'flood-relief', url: '/donate/flood-relief', name: 'Flood Relief',
        description: 'Respond after floods.', group_uuid: 'relief-group', image: '',
      },
      {
        uuid: 'hidden-campaign', slug: 'hidden-campaign', url: '/donate/hidden-campaign', name: 'Hidden-group campaign',
        description: 'Its group is not public.', group_uuid: 'hidden-group', image: '',
      },
      {
        uuid: 'general-support', slug: 'general-support', url: '/donate/general-support', name: 'General Support',
        description: 'Support the current priority.', group_uuid: null, image: '',
      },
    ];
    const wrapper = mountDonate({
      attachTo: document.body,
      pageSettings: {
        cause_tabs_accessible_label: 'Choose a donation category',
        cause_tabs_all_label: 'Everything',
      },
      data: {
        pageMode: 'catalog',
        donationTypes: causes,
        donationGroups: [
          {
            uuid: 'education-group', slug: 'education', name: 'Education',
            description: 'Learning, meals, and school support.',
          },
          {
            uuid: 'relief-group', slug: 'relief', name: 'Emergency Relief',
            description: 'Rapid assistance for communities in crisis.',
          },
          {
            uuid: 'empty-group', slug: 'empty', name: 'Empty group',
            description: 'This group has no active cause.',
          },
        ],
        selectedUUID: null,
        checkout_key: null,
        paymentMethods: [],
        donationFrequencies: [],
      },
      url: '/donate',
    });
    await nextTick();

    const tablist = wrapper.get('[data-test="donation-cause-tablist"]');
    let tabs = tablist.findAll('[role="tab"]');
    let panels = wrapper.findAll('[data-test="donation-cause-panel"]');
    let panel = panels[0];

    expect(tablist.attributes('role')).toBe('tablist');
    expect(tablist.attributes('aria-orientation')).toBe('horizontal');
    expect(tablist.attributes('aria-label')).toBe('Choose a donation category');
    expect(tabs.map(tab => tab.text())).toEqual(['Everything', 'Education', 'Emergency Relief']);
    expect(wrapper.text()).not.toContain('Empty group');
    expect(tabs[0].attributes('aria-selected')).toBe('true');
    expect(tabs[0].attributes('tabindex')).toBe('0');
    expect(tabs.slice(1).map(tab => tab.attributes('tabindex'))).toEqual(['-1', '-1']);
    expect(panels).toHaveLength(tabs.length);
    tabs.forEach((tab, index) => {
      expect(tab.attributes('aria-controls')).toBe(panels[index].attributes('id'));
      expect(document.getElementById(tab.attributes('aria-controls'))).toBe(panels[index].element);
      expect(panels[index].attributes('aria-labelledby')).toBe(tab.attributes('id'));
      expect(document.getElementById(panels[index].attributes('aria-labelledby'))).toBe(tab.element);
    });
    expect(panel.attributes('role')).toBe('tabpanel');
    expect(panel.attributes('aria-labelledby')).toBe(tabs[0].attributes('id'));
    expect(document.getElementById(panel.attributes('aria-labelledby'))).toBe(tabs[0].element);
    expect(panel.attributes('hidden')).toBeUndefined();
    expect(panels.slice(1).every(candidate => candidate.attributes('hidden') === '')).toBe(true);
    expect(wrapper.findAll('[data-test="donation-cause-card"]')).toHaveLength(5);

    await tabs[1].trigger('click');
    await nextTick();
    tabs = tablist.findAll('[role="tab"]');
    panels = wrapper.findAll('[data-test="donation-cause-panel"]');
    panel = panels[1];
    expect(tabs[1].attributes('aria-selected')).toBe('true');
    expect(tabs[1].attributes('tabindex')).toBe('0');
    expect(document.activeElement).toBe(tabs[1].element);
    expect(panel.attributes('aria-labelledby')).toBe(tabs[1].attributes('id'));
    expect(panel.attributes('aria-describedby')).toBe('donation-cause-panel-group-education-group-description');
    expect(panel.get('.igf-donate-causes__tab-description').text()).toBe('Learning, meals, and school support.');
    expect(wrapper.findAll('[data-test="donation-cause-card"]').map(card => card.get('h3').text()))
      .toEqual(['School Books', 'School Meals']);
    expect(wrapper.findAll('[data-test="donation-cause-link"]').map(link => link.attributes('href')))
      .toEqual(['/donate/school-books', '/donate/school-meals']);

    await tablist.trigger('keydown', { key: 'ArrowRight' });
    await nextTick();
    tabs = tablist.findAll('[role="tab"]');
    panel = wrapper.findAll('[data-test="donation-cause-panel"]')[2];
    expect(tabs[2].attributes('aria-selected')).toBe('true');
    expect(document.activeElement).toBe(tabs[2].element);
    expect(wrapper.get('[data-test="donation-cause-card"] h3').text()).toBe('Flood Relief');
    expect(panel.get('.igf-donate-causes__tab-description').text()).toContain('Rapid assistance');

    await tablist.trigger('keydown', { key: 'End' });
    await nextTick();
    tabs = tablist.findAll('[role="tab"]');
    panel = wrapper.findAll('[data-test="donation-cause-panel"]')[2];
    expect(tabs[2].attributes('aria-selected')).toBe('true');
    expect(wrapper.findAll('[data-test="donation-cause-card"]').map(card => card.get('h3').text()))
      .toEqual(['Flood Relief']);
    expect(panel.attributes('aria-describedby')).toBe('donation-cause-panel-group-relief-group-description');

    await tablist.trigger('keydown', { key: 'Home' });
    await nextTick();
    tabs = tablist.findAll('[role="tab"]');
    expect(tabs[0].attributes('aria-selected')).toBe('true');
    expect(wrapper.findAll('[data-test="donation-cause-link"]').map(link => link.attributes('href')))
      .toEqual(causes.map(cause => cause.url));

    await tablist.trigger('keydown', { key: 'ArrowLeft' });
    await nextTick();
    tabs = tablist.findAll('[role="tab"]');
    expect(tabs[2].attributes('aria-selected')).toBe('true');
    expect(document.activeElement).toBe(tabs[2].element);
    expect(donateSource).toContain('.igf-donate-causes__tabs { display:flex; width:100%; align-items:center; gap:10px; overflow-x:auto;');
    expect(donateSource).toContain('.igf-donate-causes__tab { display:inline-flex; min-height:44px; flex:0 0 auto;');

    wrapper.unmount();
  });

  test('accepts the transitional donationCauseGroups prop and always shows the All tab', async () => {
    const wrapper = mountDonate({
      data: {
        pageMode: 'catalog',
        donationTypes: [{
          ...fullDonationCatalog[1],
          group_uuid: 'programs-group',
        }],
        donationCauseGroups: [{
          uuid: 'programs-group', slug: 'programs', name: 'Programs', description: 'Managed programs.',
        }],
        selectedUUID: null,
        checkout_key: null,
        paymentMethods: [],
        donationFrequencies: [],
      },
      url: '/donate',
    });
    await nextTick();

    expect(wrapper.findAll('[data-test="donation-cause-tab"]').map(tab => tab.text()))
      .toEqual(['All causes', 'Programs']);
    const allTab = wrapper.get('[data-test="donation-cause-tab"]');
    expect(wrapper.get(`#${allTab.attributes('aria-controls')}`).attributes('aria-labelledby'))
      .toBe(allTab.attributes('id'));
  });

  test('keeps the catalog cause list usable when the legacy gallery toggle is disabled', async () => {
    const wrapper = mountDonate({
      pageSettings: { show_cause_gallery: false },
      data: {
        pageMode: 'catalog',
        donationTypes: [fullDonationCatalog[0]],
        selectedUUID: null,
        checkout_key: null,
        paymentMethods: [],
        donationFrequencies: [],
      },
      url: '/donate',
    });
    await nextTick();

    expect(wrapper.get('.igf-donate-causes').exists()).toBe(true);
    expect(wrapper.findAll('[data-test="donation-cause-tab"]')).toHaveLength(1);
    expect(wrapper.findAll('[data-test="donation-cause-tab"]').map(tab => tab.text())).toEqual(['All causes']);
    expect(wrapper.findAll('[data-test="donation-cause-card"]')).toHaveLength(1);
    expect(wrapper.get('[data-test="donation-cause-link"]').attributes('href'))
      .toBe('/donate/where-it-is-needed-most');
    expect(wrapper.find('form').exists()).toBe(false);
  });

  test('announces an accessible catalog empty state when no active cause is available', async () => {
    const wrapper = mountDonate({
      pageSettings: {
        show_cause_gallery: false,
        causes_unavailable_message: 'Donation causes are temporarily unavailable.',
      },
      data: {
        pageMode: 'catalog',
        donationTypes: [],
        selectedUUID: null,
        checkout_key: null,
        paymentMethods: [],
        donationFrequencies: [],
      },
      url: '/donate',
    });
    await nextTick();

    const empty = wrapper.get('[data-test="donation-catalog-empty"]');
    expect(empty.attributes('role')).toBe('status');
    expect(empty.attributes('aria-live')).toBe('polite');
    expect(empty.text()).toBe('Donation causes are temporarily unavailable.');
    expect(wrapper.find('[data-test="donation-cause-card"]').exists()).toBe(false);
    expect(wrapper.find('form').exists()).toBe(false);
  });

  test.each([undefined, '', 'cause', 'unexpected'])(
    'fails safely to catalog mode for an absent or unknown page mode (%s)',
    async (unsafeMode) => {
      const wrapper = mountDonate({
        data: {
          pageMode: unsafeMode,
          donationTypes: [fullDonationCatalog[0]],
          selectedUUID: null,
          checkout_key: 'must-not-expose-checkout',
        },
        url: '/donate',
      });
      await nextTick();

      expect(wrapper.vm.pageMode).toBe('catalog');
      expect(wrapper.get('.igf-donate-causes').exists()).toBe(true);
      expect(wrapper.find('.igf-donate__section').exists()).toBe(false);
      expect(wrapper.find('form').exists()).toBe(false);
      wrapper.unmount();
    },
  );

  test('uses only allowlisted cause icons and falls back safely for unknown keys', async () => {
    const wrapper = mountDonate({
      pageSettings: { show_cause_gallery: true },
      data: {
        pageMode: 'catalog',
        donationTypes: [
          {
            uuid: 'managed-water', name: 'Managed water icon', image: '',
            icon_key: 'water', destination_type: 'unrestricted',
          },
          {
            uuid: 'untrusted-icon', name: 'Untrusted icon', image: '',
            icon_key: 'fa-solid fa-user-secret', destination_type: 'page',
          },
          {
            uuid: 'legacy-category', name: 'Legacy category icon', image: '',
            icon_key: '', destination_type: 'category',
          },
        ],
        selectedUUID: null,
        checkout_key: null,
        paymentMethods: [],
        donationFrequencies: [],
      },
      url: '/donate',
    });
    await nextTick();

    const icons = wrapper.findAll('.igf-donation-cause-card__placeholder > i');

    expect(icons).toHaveLength(3);
    expect(icons[0].classes()).toEqual(['fa-solid', 'fa-droplet']);
    expect(icons[1].classes()).toEqual(['fa-solid', 'fa-bullseye']);
    expect(icons[2].classes()).toEqual(['fa-solid', 'fa-layer-group']);
    expect(wrapper.html()).not.toContain('fa-user-secret');
  });

  test('renders the complete 17-card catalog as dedicated links without checkout controls', async () => {
    const wrapper = mountDonate({
      pageSettings: { show_cause_gallery: true },
      data: {
        pageMode: 'catalog',
        donationTypes: fullDonationCatalog,
        selectedUUID: null,
        checkout_key: null,
        paymentMethods: [],
        donationFrequencies: [],
      },
      url: '/donate',
    });
    await nextTick();

    const cards = wrapper.findAll('[data-test="donation-cause-card"]');
    const links = wrapper.findAll('[data-test="donation-cause-link"]');

    expect(cards).toHaveLength(17);
    expect(links).toHaveLength(17);
    expect(cards.map(card => card.get('h3').text())).toEqual(fullDonationCatalog.map(cause => cause.name));
    expect(links.map(link => link.element.tagName)).toEqual(Array(17).fill('A'));
    expect(links.map(link => link.attributes('href'))).toEqual(fullDonationCatalog.map(cause => cause.url));
    expect(new Set(links.map(link => link.attributes('href'))).size).toBe(17);
    expect(wrapper.find('.igf-donate__section').exists()).toBe(false);
    expect(wrapper.find('#donation-form-title').exists()).toBe(false);
    expect(wrapper.find('[data-test="locked-donation-cause"]').exists()).toBe(false);
    expect(wrapper.find('#donation-cause').exists()).toBe(false);
    expect(wrapper.find('form').exists()).toBe(false);
  });

  test('renders a dedicated cause checkout with an immutable locked cause', async () => {
    const wrapper = mountDonate({
      pageSettings: {
        show_cause_gallery: true,
        cause_catalog_back_label: 'Return to every cause',
        selected_cause_eyebrow: 'Chosen impact',
      },
      data: {
        pageMode: 'detail',
        catalogUrl: '/donate',
        donationTypes: [{
          uuid: 'fixed-cause', slug: 'fixed-cause', url: '/donate/fixed-cause', name: 'School rebuild',
          description: 'Help a school recover safely.',
          destination_type: 'page', destination_name: 'School Rebuild Project', project_selection: 'fixed',
          projects: [{ uuid: 'fixed-project', name: 'School Rebuild Project' }],
        }],
        selectedUUID: 'fixed-cause',
        selectedCauseSlug: 'fixed-cause',
        selectedProjectUUID: 'fixed-project',
        selectedDestination: {
          type: 'page', uuid: 'fixed-project', name: 'School Rebuild Project',
          project_uuid: 'fixed-project', project_name: 'School Rebuild Project',
        },
      },
      url: '/donate/fixed-cause?amount=1000',
    });
    await nextTick();

    expect(wrapper.get('.igf-donate').classes()).toContain('is-cause-page');
    expect(wrapper.get('.igf-donate').classes()).toContain('is-layout-centered');
    expect(donateSource).toContain('.is-layout-centered.is-cause-page .igf-checkout-grid { grid-template-columns:1fr; }');
    expect(donateSource).toContain('@media (max-width:420px) { .igf-amount-options { grid-template-columns:1fr; } }');
    expect(wrapper.find('.igf-donate-causes').exists()).toBe(false);
    expect(wrapper.find('[data-test="donation-cause-card"]').exists()).toBe(false);
    expect(wrapper.get('.igf-donate__hero--cause h1').text()).toBe('School rebuild');
    expect(wrapper.get('.igf-cause-back-link').attributes('href')).toBe('/donate');
    expect(wrapper.get('.igf-cause-back-link').text()).toContain('Return to every cause');
    expect(wrapper.get('.igf-cause-story__body .igf-eyebrow').text()).toBe('Chosen impact');
    expect(wrapper.get('[data-test="locked-donation-cause"]').text()).toContain('School rebuild');
    expect(wrapper.get('[data-test="locked-donation-cause"] .fa-lock').exists()).toBe(true);
    expect(wrapper.find('#donation-cause').exists()).toBe(false);
    expect(wrapper.get('#donation-form-title').text()).toBe('Make a donation');
    expect(wrapper.vm.donation.payment_cause).toBe('fixed-cause');
    expect(wrapper.vm.donation.project_uuid).toBe('fixed-project');
    expect(wrapper.vm.donation.amount).toBe(1000);
    expect(wrapper.vm.donation.checkout_key).toBe('checkout-key-initial');
    expect(wrapper.get('.igf-fixed-project strong').text()).toBe('School Rebuild Project');
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
    const wrapper = mountDonate({ url: '/donate/education' });
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
    const wrapper = mountDonate({ url: '/donate/education?custom_amount=750' });
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

  test('keeps the cause locked while an optional project updates the confirmed destination', async () => {
    const wrapper = mountDonate({
      data: {
        pageMode: 'detail',
        donationTypes: [{
          uuid: 'program-cause', slug: 'program-cause', name: 'Education program',
          destination_type: 'category', destination_name: 'Education', project_selection: 'optional',
          projects: [{ uuid: 'project-one', name: 'Project One' }, { uuid: 'project-two', name: 'Project Two' }],
        }],
        selectedUUID: 'program-cause',
        selectedProjectUUID: 'project-two',
        selectedDestination: { type: 'category', name: 'Education', project_name: 'Project Two' },
      },
      url: '/donate/program-cause',
    });
    await nextTick();

    expect(wrapper.get('[data-test="locked-donation-cause"]').text()).toContain('Education program');
    expect(wrapper.find('#donation-cause').exists()).toBe(false);
    expect(wrapper.get('#donation-project').attributes('disabled')).toBeUndefined();
    expect(wrapper.vm.donation.project_uuid).toBe('project-two');
    expect(wrapper.get('.igf-destination-summary strong').text()).toBe('Project Two');
    expect(wrapper.get('.igf-destination-summary p').text()).toBe('Support the whole program or one project.');

    await wrapper.get('#donation-project').setValue('project-one');
    expect(wrapper.vm.donation.payment_cause).toBe('program-cause');
    expect(wrapper.vm.donation.project_uuid).toBe('project-one');
    expect(wrapper.get('.igf-destination-summary strong').text()).toBe('Project One');
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
