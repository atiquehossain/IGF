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
});
