import { shallowMountComponent } from "../../vue-inertial.helper";
import App from '@/layouts/App.vue';
import { usePage } from '@inertiajs/vue3';
import { globalTestData } from "../../test.global-data";
import { resolvePageCss } from '@/Shared/pageCss';

describe('Testing App.vue component', () => {
  let wrapper;

  beforeEach(() => {
    usePage().component = 'Home/home';
    usePage().props = {
      ...globalTestData,
      siteSettings: {
        theme: {},
        design: {
          font_pairing: 'classic',
          corner_radius: 'rounded',
          shadow_density: 'strong',
        },
        header: { presentation: 'soft', density: 'compact', sticky: false },
        footer: { presentation: 'light', layout: 'stacked' },
      },
    };
    wrapper = shallowMountComponent(App, {
      global: {
        mocks: {
          $page: { props: { ...globalTestData } },
          $inertia: vi.fn(), // Mock Inertia globally
        },
        provide: {
          igfLocale: 'en', // Providing igfLocale directly
        },
        stubs: {
          'v-app': { template: '<div class="v-app-stub"><slot /></div>' },
        },
      },
    });
  });

  test('does a wrapper exist', () => {
    expect(wrapper.exists()).toBe(true);
  });

  test('maps only curated design and shell settings to public CSS variables', () => {
    const style = wrapper.get('.igf-site-shell').element.style;

    expect(style.getPropertyValue('--igf-font-body')).toBe('Arial,Helvetica,sans-serif');
    expect(style.getPropertyValue('--igf-radius-lg')).toBe('28px');
    expect(style.getPropertyValue('--igf-shadow-header')).toBe('0 5px 18px rgba(25,28,29,.16)');
    expect(style.getPropertyValue('--igf-header-nav-bg')).toBe('color-mix(in srgb,var(--igf-primary) 3%,var(--igf-surface))');
    expect(style.getPropertyValue('--igf-header-nav-height')).toBe('70px');
    expect(style.getPropertyValue('--igf-header-position')).toBe('relative');
    expect(style.getPropertyValue('--igf-footer-bg')).toBe('#f4f1ed');
    expect(style.getPropertyValue('--igf-footer-body-columns')).toBe('1fr');
  });

  test('publishes computed foreground tokens for administrator-selected brand colors', async () => {
    usePage().props.siteSettings.theme = {
      primary_color: '#ffffff',
      accent_color: '#000000',
      ink_color: '#28313a',
      surface_color: '#f4f7fa',
    };
    await wrapper.vm.$nextTick();

    const style = wrapper.get('.igf-site-shell').element.style;
    expect(style.getPropertyValue('--igf-primary')).toBe('#ffffff');
    expect(style.getPropertyValue('--igf-on-primary')).toBe('#000000');
    expect(style.getPropertyValue('--igf-accent')).toBe('#000000');
    expect(style.getPropertyValue('--igf-on-accent')).toBe('#ffffff');
  });

  test('falls back to safe defaults when public preset values are unknown', async () => {
    usePage().props.siteSettings.design.font_pairing = 'url(https://unsafe.example/font)';
    usePage().props.siteSettings.header.presentation = 'transparent-script';
    usePage().props.siteSettings.footer.layout = 'position:fixed';
    await wrapper.vm.$nextTick();
    const style = wrapper.get('.igf-site-shell').element.style;

    expect(style.getPropertyValue('--igf-font-body')).toBe("'Hanken Grotesk',Arial,sans-serif");
    expect(style.getPropertyValue('--igf-header-nav-bg')).toBe('color-mix(in srgb,var(--igf-surface) 84%,#fff)');
    expect(style.getPropertyValue('--igf-footer-body-columns')).toBe('minmax(220px,.9fr) minmax(0,2.1fr)');
  });

  test.each([
    ['Home/home', 'homePage'],
    ['about', 'about_us'],
    ['zakat', 'zakat'],
    ['category', 'category'],
    ['event', 'event'],
    ['page', 'page'],
  ])('applies sanitized CSS only for the active %s content source', async (component, source) => {
    expect(resolvePageCss(component, { [source]: { inline_css: `.managed-${source}{color:#123456}` } }))
      .toBe(`.managed-${source}{color:#123456}`);
  });

  test('removes page CSS when navigating to a route without a managed CSS source', () => {
    expect(resolvePageCss('sponsor_child', {
      sponsor_child: { inline_css: '.must-not-render{display:none}' },
    })).toBe('');
  });

  test('combines category and selected landing-page CSS on a landing category', () => {
    expect(resolvePageCss('category', {
      category: { inline_css: '.category-shell{color:#123456}' },
      landing_page: { inline_css: '.school-hero{min-height:40rem}' },
    })).toBe('.category-shell{color:#123456}\n.school-hero{min-height:40rem}');
  });
});
