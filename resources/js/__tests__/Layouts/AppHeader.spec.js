import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import AppHeader from '@/layouts/AppHeader.vue';

const baseProps = () => ({
  publicLocaleSwitcherEnabled: true,
  locale: 'en',
  seoLocale: { current: 'en', default: 'en' },
  siteSettings: {
    contact: { phone_primary: '+880 1972 016221', email: 'info@ignite.org.bd' },
    header: {
      show_language_switcher: true,
      english_language_label: 'EN',
      bangla_language_label: 'বাংলা',
    },
  },
});

describe('AppHeader verified language switcher', () => {
  beforeEach(() => {
    usePage().url = '/page/english-story';
    usePage().props = baseProps();
  });

  test('uses the server-provided translated slug instead of query-toggling the current path', () => {
    usePage().props.seoAlternates = {
      links: [
        { locale: 'en', url: 'http://localhost/page/english-story' },
        { locale: 'bn', url: 'http://localhost/page/bangla-story?lang=bn' },
      ],
      x_default: 'http://localhost/page/english-story',
    };

    const wrapper = mount(AppHeader);
    const languageLinks = wrapper.findAll('.utility-bar__links a[hreflang]');

    expect(languageLinks.map((link) => link.attributes('hreflang'))).toEqual(['en', 'bn']);
    expect(languageLinks[0].attributes('href')).toBe('http://localhost/page/english-story');
    expect(languageLinks[1].attributes('href')).toBe('http://localhost/page/bangla-story?lang=bn');
    expect(wrapper.html()).not.toContain('/page/english-story?lang=bn');
  });

  test('does not advertise a language switch when no real translation exists', () => {
    usePage().props.seoAlternates = {
      links: [{ locale: 'en', url: 'http://localhost/page/english-only' }],
      x_default: 'http://localhost/page/english-only',
    };

    const wrapper = mount(AppHeader);

    expect(wrapper.findAll('.utility-bar__links a[hreflang]')).toHaveLength(0);
    expect(wrapper.text()).not.toContain('বাংলা');
  });

  test('renders every managed social profile URL from website settings', () => {
    usePage().props.siteSettings.social = {
      facebook: 'https://social.example.test/facebook',
      instagram: 'https://social.example.test/instagram',
      linkedin: 'https://social.example.test/linkedin',
      tiktok: 'https://social.example.test/stale-tiktok',
      youtube: 'https://social.example.test/youtube',
    };

    const wrapper = mount(AppHeader);
    const links = wrapper.findAll('.utility-social a');

    expect(links.map(link => link.attributes('href'))).toEqual([
      'https://social.example.test/facebook',
      'https://social.example.test/instagram',
      'https://social.example.test/linkedin',
      'https://social.example.test/youtube',
    ]);
    expect(links.every(link => link.attributes('rel') === 'noopener noreferrer')).toBe(true);
  });

  test('does not render the annual reports link in the utility bar', () => {
    usePage().props.siteSettings.header.annual_reports_label = 'Annual reports';
    usePage().props.siteSettings.header.annual_reports_url = '/annual-report';

    const wrapper = mount(AppHeader);

    expect(wrapper.find('.utility-bar__links a[href="/annual-report"]').exists()).toBe(false);
    expect(wrapper.get('.utility-bar__links').text()).not.toContain('Annual reports');
  });

  test('honors explicitly blank managed contact fields by hiding those links', () => {
    usePage().props.siteSettings.contact = {
      phone_primary: '',
      phone_secondary: '',
      email: '',
    };

    const wrapper = mount(AppHeader);

    expect(wrapper.find('a[href^="tel:"]').exists()).toBe(false);
    expect(wrapper.find('a[href^="mailto:"]').exists()).toBe(false);
  });

  test('uses safe contact defaults only when managed keys are absent', () => {
    usePage().props.siteSettings.contact = {};

    const wrapper = mount(AppHeader);

    expect(wrapper.get('a[href="tel:+8801972016221"]').text()).toContain('+8801972016221');
    expect(wrapper.get('a[href="mailto:info@ignite.org.bd"]').text()).toContain('info@ignite.org.bd');
  });
});
