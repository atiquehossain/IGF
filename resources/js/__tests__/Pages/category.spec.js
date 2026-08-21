import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import Category from '@/Pages/category.vue';

const layoutStub = { template: '<main><slot /></main>' };
const pageBlocksStub = {
  props: ['blocks'],
  template: '<div data-test="landing-blocks">{{ blocks.length }}</div>',
};

function mountCategory(data) {
  usePage().props = {
    data,
    properties: { page: 1, total_page: 1 },
    siteSettings: {
      content_archives: {
        category_eyebrow: 'Explore',
        category_listing_label: 'Category items',
        category_empty_title: 'Nothing here yet',
        category_empty_body: 'Please check again later.',
      },
    },
  };
  usePage().url = '/category/visit-ignite-school';

  return mount(Category, {
    global: {
      stubs: {
        App: layoutStub,
        Layout: layoutStub,
        PageBlocks: pageBlocksStub,
        CategoryItemCard: true,
        'v-pagination': true,
      },
    },
  });
}

describe('category landing pages', () => {
  test('renders the selected page-builder blocks instead of the archive grid', () => {
    const wrapper = mountCategory({
      category: { name: 'Visit Ignite School', slug: 'visit-ignite-school' },
      landing_page: {
        slug: 'ignite-school-bawnia-campus',
        visible_blocks: [
          { uuid: 'school-hero', type: 'hero', content: { heading: 'Ignite School' } },
          { uuid: 'school-stats', type: 'stats', content: { items: [] } },
        ],
      },
      items: [{ id: 30, name: 'Ignite School, Bawnia Campus' }],
    });

    expect(wrapper.get('[data-test="landing-blocks"]').text()).toBe('2');
    expect(wrapper.find('.igf-listing').exists()).toBe(false);
  });

  test('keeps the normal archive fallback when no landing blocks are available', () => {
    const wrapper = mountCategory({
      category: { name: 'Projects', slug: 'projects', description: '' },
      landing_page: null,
      items: [],
    });

    expect(wrapper.find('[data-test="landing-blocks"]').exists()).toBe(false);
    expect(wrapper.get('.igf-listing h1').text()).toBe('Projects');
    expect(wrapper.get('.igf-empty').text()).toContain('Nothing here yet');
  });
});
