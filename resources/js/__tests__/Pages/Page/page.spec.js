import { shallowMountComponent } from '../../../vue-inertial.helper';
import Page from '@/pages/page.vue';
import { globalTestData } from '../../../test.global-data';

describe('Testing Page.vue component', () => {
  let wrapper;

  beforeEach(() => {
    wrapper = shallowMountComponent(Page, {
      global: {
        mocks: {
          $page: {
            props: { ...globalTestData, 'data.page': '<h2>Test page</h2>' },
          },
          $inertia: vi.fn(),
        },
      },
    });
  });

  test('does a wrapper exist', () => {
    expect(wrapper.exists()).toBe(true);
  });
});
