import { mount } from '@vue/test-utils';
import { router, usePage } from '@inertiajs/vue3';
import AppFooter from '@/layouts/AppFooter.vue';

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

  afterEach(() => vi.restoreAllMocks());

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
      { email: 'visitor@example.test' },
      expect.objectContaining({ preserveScroll: true }),
    );
    expect(wrapper.get('.footer-newsletter__message').text()).toBe('Subscription saved.');
    expect(wrapper.get('#footer-newsletter-email').element.value).toBe('');
  });
});
