import { mount } from '@vue/test-utils';
import { router, usePage } from '@inertiajs/vue3';
import AppFooter from '@/layouts/AppFooter.vue';

const LEGAL_STATUS_SLOT_UUID = '7f030000-0000-4000-8000-000000000300';

function legalStatusSettings(overrides = {}) {
  return {
    enabled: true,
    heading: 'Legal Status',
    authority_1_label: '',
    authority_1_registration: '',
    authority_1_logo: '',
    authority_2_label: 'NGO Affairs Bureau',
    authority_2_registration: 'Registration no. 3461',
    authority_2_logo: '/storage/legal/ngo-affairs.png',
    authority_3_label: 'Joint Stock & Firms',
    authority_3_registration: 'Registration no. S-13907/2022',
    authority_3_logo: '/storage/legal/joint-stock.png',
    ...overrides,
  };
}

function footerMenu(uuid, name, children = []) {
  return { uuid, name, children };
}

describe('AppFooter managed branding and newsletter', () => {
  beforeEach(() => {
    globalThis.route = vi.fn(name => name);
    usePage().props = {
      appName: 'Fallback name',
      appFooterMenus: [],
      siteSettings: {
        branding: {
          site_name: 'Managed foundation',
          footer_logo: '/storage/managed-footer-logo.png',
          footer_logo_alt: 'Managed foundation logo',
          tagline: 'A tagline the admin can change',
        },
        footer: {
          about: 'Managed footer summary',
          newsletter_title: 'Managed newsletter title',
          newsletter_body: 'Managed newsletter description',
          copyright: 'Managed copyright',
          trust_badge: 'Managed trust statement',
        },
        contact: { email: 'hello@example.test', phone_primary: '+880 1000', phone_secondary: '', address: 'Dhaka' },
        social: {},
        shared_blocks: {
          newsletter_email_label: 'Your email',
          newsletter_email_placeholder: 'name@example.com',
          newsletter_subscribe_label: 'Join updates',
          newsletter_subscribing_label: 'Joining…',
          newsletter_consent_prefix: 'I agree to the',
          newsletter_privacy_label: 'Privacy policy',
          newsletter_privacy_url: '/page/privacy-policy',
          newsletter_success_message: 'Subscription saved.',
          newsletter_error_message: 'Try again.',
        },
      },
    };
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
      options.onSuccess();
      options.onFinish();
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  test('renders Customizer fields and submits the shared newsletter form', async () => {
    const wrapper = mount(AppFooter);

    expect(wrapper.get('.footer-brand__name img').attributes('src')).toBe('/storage/managed-footer-logo.png');
    expect(wrapper.get('.footer-brand__tagline').text()).toBe('A tagline the admin can change');
    expect(wrapper.get('.footer-newsletter h2').text()).toBe('Managed newsletter title');
    expect(wrapper.get('.footer-newsletter').text()).toContain('Managed newsletter description');

    await wrapper.get('#footer-newsletter-email').setValue('visitor@example.test');
    await wrapper.get('.footer-newsletter__consent input').setValue(true);
    await wrapper.get('.footer-newsletter form').trigger('submit');

    expect(router.post).toHaveBeenCalledWith(
      'frontend.subscribe',
      { email: 'visitor@example.test', consent: true },
      expect.objectContaining({ preserveScroll: true }),
    );
    expect(wrapper.get('.footer-newsletter__message').text()).toBe('Subscription saved.');
    expect(wrapper.get('.footer-newsletter__message').attributes('role')).toBe('status');
    expect(wrapper.get('.footer-newsletter__message').classes()).toContain('is-success');
    expect(wrapper.get('#footer-newsletter-email').attributes('aria-describedby')).toBe('footer-newsletter-message');
    expect(wrapper.get('#footer-newsletter-email').attributes('aria-invalid')).toBeUndefined();
    expect(wrapper.get('#footer-newsletter-email').element.value).toBe('');
  });

  test('announces subscription errors and associates them with the email field', async () => {
    router.post.mockImplementationOnce((_url, _data, options) => {
      options.onError({ email: 'Please enter a deliverable email address.' });
      options.onFinish();
    });
    const wrapper = mount(AppFooter);

    await wrapper.get('#footer-newsletter-email').setValue('invalid@example.test');
    await wrapper.get('.footer-newsletter__consent input').setValue(true);
    await wrapper.get('.footer-newsletter form').trigger('submit');

    const input = wrapper.get('#footer-newsletter-email');
    const message = wrapper.get('#footer-newsletter-message');
    expect(message.text()).toBe('Please enter a deliverable email address.');
    expect(message.attributes('role')).toBe('alert');
    expect(message.classes()).toContain('is-error');
    expect(input.attributes('aria-invalid')).toBe('true');
    expect(input.attributes('aria-describedby')).toBe('footer-newsletter-message');
  });

  test('replaces only the stable Donor Support slot with managed Legal Status content', () => {
    usePage().props.siteSettings.legal_status = legalStatusSettings();
    usePage().props.appFooterMenus = [
      footerMenu('explore-column', 'Explore', [
        { name: 'About us', link: 'custom', slug: '/page/about' },
      ]),
      footerMenu(LEGAL_STATUS_SLOT_UUID, 'Donor support', [
        { name: 'Ways to give', link: 'custom', slug: '/page/giving' },
        { name: 'Sponsor a child', link: 'custom', slug: '/sponsor-child' },
      ]),
    ];

    const wrapper = mount(AppFooter);
    const responsiveContent = wrapper.get('.footer-content.has-legal-status');
    const legalStatus = wrapper.get('.footer-legal-status');
    const items = legalStatus.findAll('.footer-legal-status__item');

    expect(responsiveContent.get('.footer-navigation').exists()).toBe(true);
    expect(responsiveContent.get('.footer-navigation').attributes('style')).toContain('--footer-nav-columns: 1');
    expect(legalStatus.element.parentElement).toBe(responsiveContent.element);
    expect(legalStatus.get('h2').text()).toBe('Legal Status');
    expect(legalStatus.get('.footer-legal-status__list').exists()).toBe(true);
    expect(items).toHaveLength(2);
    expect(items[0].text()).toContain('NGO Affairs Bureau');
    expect(items[0].text()).toContain('3461');
    expect(items[1].text()).toContain('Joint Stock & Firms');
    expect(items[1].text()).toContain('S-13907/2022');
    expect(legalStatus.text()).not.toContain('Microcredit Regulatory Authority');
    expect(legalStatus.findAll('.footer-legal-status__logo')).toHaveLength(2);
    expect(legalStatus.findAll('a')).toHaveLength(0);
    expect(legalStatus.text()).not.toContain('Ways to give');
    expect(wrapper.get('.footer-links').text()).toContain('Explore');
    expect(wrapper.get('.footer-links a').text()).toBe('About us');
    expect(wrapper.get('.footer-links a').attributes('href')).toBe('/page/about');
  });

  test('does not replace a different menu that happens to have the Donor Support title', () => {
    usePage().props.siteSettings.legal_status = legalStatusSettings();
    usePage().props.appFooterMenus = [
      footerMenu('different-menu-uuid', 'Donor support', [
        { name: 'Ways to give', link: 'custom', slug: '/page/giving' },
      ]),
    ];

    const wrapper = mount(AppFooter);

    expect(wrapper.find('.footer-legal-status').exists()).toBe(false);
    expect(wrapper.get('.footer-links').text()).toContain('Donor support');
    expect(wrapper.get('.footer-links a').text()).toBe('Ways to give');
    expect(wrapper.get('.footer-links a').attributes('href')).toBe('/page/giving');
  });

  test('does not inject Legal Status when the stable footer slot has been removed', () => {
    usePage().props.siteSettings.legal_status = legalStatusSettings();
    usePage().props.appFooterMenus = [
      footerMenu('explore-column', 'Explore', [
        { name: 'About us', link: 'custom', slug: '/page/about' },
      ]),
    ];

    const wrapper = mount(AppFooter);

    expect(wrapper.find('.footer-legal-status').exists()).toBe(false);
    expect(wrapper.get('.footer-links').text()).toContain('Explore');
    expect(wrapper.get('.footer-links a').text()).toBe('About us');
  });

  test('uses text badges instead of broken image elements when authority logos are blank', () => {
    usePage().props.siteSettings.legal_status = legalStatusSettings({
      authority_1_logo: '',
      authority_2_logo: '   ',
      authority_3_logo: null,
    });
    usePage().props.appFooterMenus = [
      footerMenu(LEGAL_STATUS_SLOT_UUID, 'Donor support'),
    ];

    const wrapper = mount(AppFooter);
    const legalStatus = wrapper.get('.footer-legal-status');

    expect(legalStatus.findAll('.footer-legal-status__logo')).toHaveLength(0);
    expect(legalStatus.findAll('.footer-legal-status__badge')).toHaveLength(2);
    expect(legalStatus.text()).not.toContain('Microcredit Regulatory Authority');
    expect(legalStatus.text()).toContain('NGO Affairs Bureau');
    expect(legalStatus.text()).toContain('3461');
    expect(legalStatus.text()).toContain('Joint Stock & Firms');
    expect(legalStatus.text()).toContain('S-13907/2022');
  });

  test('renders managed contact, social, footer copy, and any number of menu columns', () => {
    usePage().props.siteSettings.contact.phone_secondary = '+880 2000';
    Object.assign(usePage().props.siteSettings.contact, {
      footer_address_label: 'Office',
      footer_phone_label: 'Mobile',
      footer_secondary_phone_label: 'Backup',
      footer_email_label: 'Inbox',
    });
    usePage().props.siteSettings.social = {
      facebook: 'https://facebook.com/ignite',
      instagram: 'https://instagram.com/ignite',
      linkedin: 'https://linkedin.com/company/ignite',
      tiktok: 'https://social.example.test/stale-tiktok',
      youtube: 'https://youtube.com/@ignite',
    };
    usePage().props.appFooterMenus = Array.from({ length: 5 }, (_, index) => footerMenu(
      `managed-column-${index + 1}`,
      `Managed column ${index + 1}`,
      [{ name: `Managed link ${index + 1}`, link: 'custom', slug: `/managed-${index + 1}` }],
    ));

    const wrapper = mount(AppFooter);
    const groups = wrapper.findAll('.footer-link-group');
    const contactRows = wrapper.findAll('.footer-contact > *');
    const contactLinks = wrapper.findAll('.footer-contact a');
    const socialLinks = wrapper.findAll('.footer-social a');

    expect(wrapper.get('.footer-brand').text()).not.toContain('Managed footer summary');
    expect(wrapper.find('#footer-office-contact-heading').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('Office contact details');
    expect(contactRows.map(row => row.text())).toEqual(['Office: Dhaka', 'Mobile: +880 1000', 'Backup: +880 2000', 'Inbox: hello@example.test']);
    expect(contactLinks.map(link => link.attributes('href'))).toEqual(['tel:+8801000', 'tel:+8802000', 'mailto:hello@example.test']);
    expect(socialLinks.map(link => link.attributes('href'))).toEqual([
      'https://facebook.com/ignite',
      'https://instagram.com/ignite',
      'https://linkedin.com/company/ignite',
      'https://youtube.com/@ignite',
    ]);
    expect(groups).toHaveLength(5);
    expect(wrapper.get('.footer-navigation').attributes('style')).toContain('--footer-nav-columns: 3');
    expect(groups.map(group => group.get('.footer-link-group__heading').text())).toEqual([
      'Managed column 1',
      'Managed column 2',
      'Managed column 3',
      'Managed column 4',
      'Managed column 5',
    ]);
    expect(groups.map(group => group.attributes('data-footer-column'))).toEqual([
      'managed-column-1',
      'managed-column-2',
      'managed-column-3',
      'managed-column-4',
      'managed-column-5',
    ]);
    expect(wrapper.get('.footer-bottom').text()).toContain('Managed copyright');
    expect(wrapper.get('.footer-bottom').text()).not.toContain('Managed trust statement');
  });

  test('renders each managed tagline sentence on its own line', () => {
    usePage().props.siteSettings.branding.tagline = 'Empowering communities. Transforming lives.';

    const wrapper = mount(AppFooter);
    const lines = wrapper.findAll('.footer-brand__tagline span');

    expect(lines).toHaveLength(2);
    expect(lines.map(line => line.text())).toEqual([
      'Empowering communities.',
      'Transforming lives.',
    ]);
  });

  test('omits empty optional newsletter and navigation regions', () => {
    usePage().props.siteSettings.footer.newsletter_title = '';
    usePage().props.siteSettings.footer.newsletter_body = '';
    usePage().props.appFooterMenus = [];

    const wrapper = mount(AppFooter);

    expect(wrapper.find('.footer-newsletter').exists()).toBe(false);
    expect(wrapper.find('.footer-navigation').exists()).toBe(false);
    expect(wrapper.find('.footer-content').exists()).toBe(false);
    expect(wrapper.get('.footer-brand').exists()).toBe(true);
  });

  test('restores the managed menu when Legal Status is disabled', () => {
    usePage().props.siteSettings.legal_status = legalStatusSettings({ enabled: false });
    usePage().props.appFooterMenus = [
      footerMenu(LEGAL_STATUS_SLOT_UUID, 'Support options', [
        { name: 'Ways to give', link: 'custom', slug: '/page/giving' },
        { name: 'Sponsor a child', link: 'custom', slug: '/sponsor-child' },
      ]),
    ];

    const wrapper = mount(AppFooter);

    expect(wrapper.find('.footer-legal-status').exists()).toBe(false);
    expect(wrapper.get(`[data-footer-column="${LEGAL_STATUS_SLOT_UUID}"]`).text()).toContain('Support options');
    expect(wrapper.get(`[data-footer-column="${LEGAL_STATUS_SLOT_UUID}"]`).text()).toContain('Ways to give');
    expect(wrapper.get(`[data-footer-column="${LEGAL_STATUS_SLOT_UUID}"] a`).attributes('href')).toBe('/page/giving');
  });

  test('collapses only managed navigation groups on small screens', async () => {
    const mediaQuery = {
      matches: true,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    };
    vi.stubGlobal('matchMedia', vi.fn(() => mediaQuery));
    usePage().props.siteSettings.legal_status = legalStatusSettings();
    usePage().props.appFooterMenus = [
      footerMenu('explore-column', 'Explore', [{ name: 'About us', link: 'custom', slug: '/page/about' }]),
      footerMenu(LEGAL_STATUS_SLOT_UUID, 'Donor support'),
      footerMenu('programs-column', 'Programs', [{ name: 'Education', link: 'custom', slug: '/page/education' }]),
    ];

    const wrapper = mount(AppFooter);
    await wrapper.vm.$nextTick();
    const toggles = wrapper.findAll('.footer-link-group__toggle');
    const explorePanel = wrapper.get('#footer-column-explore-column');

    expect(toggles).toHaveLength(2);
    expect(toggles[0].attributes('aria-expanded')).toBe('false');
    expect(toggles[0].attributes('aria-controls')).toBe('footer-column-explore-column');
    expect(explorePanel.isVisible()).toBe(false);
    expect(wrapper.get('.footer-legal-status').isVisible()).toBe(true);
    expect(wrapper.find('.footer-legal-status .footer-link-group__toggle').exists()).toBe(false);

    await toggles[0].trigger('click');
    expect(toggles[0].attributes('aria-expanded')).toBe('true');
    expect(wrapper.get('#footer-column-explore-column').attributes('style')).not.toContain('display: none');

    await toggles[0].trigger('click');
    expect(toggles[0].attributes('aria-expanded')).toBe('false');
    expect(wrapper.get('#footer-column-explore-column').attributes('style')).toContain('display: none');
  });

  test('keeps explicitly blank Legal Status records blank instead of restoring defaults', () => {
    usePage().props.siteSettings.legal_status = legalStatusSettings({
      authority_2_label: '',
      authority_2_registration: '',
      authority_2_logo: '',
    });
    usePage().props.appFooterMenus = [footerMenu(LEGAL_STATUS_SLOT_UUID, 'Donor support')];

    const wrapper = mount(AppFooter);
    const legalStatus = wrapper.get('.footer-legal-status');

    expect(legalStatus.findAll('.footer-legal-status__item')).toHaveLength(1);
    expect(legalStatus.text()).not.toContain('Microcredit Regulatory Authority');
    expect(legalStatus.text()).not.toContain('NGO Affairs Bureau');
    expect(legalStatus.text()).toContain('Joint Stock & Firms');
  });
});
