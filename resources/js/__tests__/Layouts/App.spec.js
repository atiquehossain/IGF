import { shallowMountComponent } from "../../vue-inertial.helper";
import App from '@/layouts/App.vue';
import { globalTestData } from "../../test.global-data";
import { resolvePageCss } from '@/Shared/pageCss';

describe('Testing App.vue component', () => {
  let wrapper;

  beforeEach(() => {
    wrapper = shallowMountComponent(App, {
      global: {
        mocks: {
          $page: { props: { ...globalTestData } },
          $inertia: vi.fn(), // Mock Inertia globally
        },
        provide: {
          igfLocale: 'en', // Providing igfLocale directly
        },
      },
    });
  });

  test('does a wrapper exist', () => {
    expect(wrapper.exists()).toBe(true);
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
