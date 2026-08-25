import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import Category from '@/Pages/category.vue';

const layoutStub = { template: '<main><slot /></main>' };
const pageBlocksStub = {
  props: ['blocks'],
  template: '<div data-test="landing-blocks">{{ blocks.length }}</div>',
};

function mountCategory(data, options = {}) {
  usePage().props = {
    data,
    properties: {
      page: 1,
      total_page: 1,
      total_count: data.items?.length || 0,
      ...(options.properties || {}),
    },
    siteSettings: {
      content_archives: {
        category_eyebrow: 'Explore',
        category_listing_label: 'Category items',
        category_empty_title: 'Nothing here yet',
        category_empty_body: 'Please check again later.',
        category_card_eyebrow: 'Community impact',
        category_card_link_label: 'Read the story',
        awards_eyebrow: 'Honours & recognition',
        awards_listing_eyebrow: 'Recognition archive',
        awards_listing_title: 'Milestones shaped by collective action.',
        awards_listing_body: 'Explore the recognitions earned through community-led work.',
        awards_card_eyebrow: 'Recognition',
        awards_card_link_label: 'View recognition',
        awards_count_singular: 'recognition',
        awards_count_plural: 'recognitions',
      },
    },
  };
  usePage().url = `/category/${data.category?.slug || ''}`;

  return mount(Category, {
    global: {
      stubs: {
        App: layoutStub,
        Layout: layoutStub,
        PageBlocks: pageBlocksStub,
        CategoryItemCard: options.stubCards ?? true,
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

  test('renders five managed awards in the branded responsive archive', () => {
    const names = [
      'The Diana Award',
      'UN Best Volunteer Award',
      'ILA Global 30 Under 30',
      'VSO National Volunteer Award',
      'The Hero Award',
    ];
    const items = names.map((name, index) => ({
      id: index + 1,
      name,
      sub_title: `Full managed summary for ${name} remains available in the document.`,
      thumbnail: `/storage/award-${index + 1}.jpg`,
      public_url: `/page/award-${index + 1}`,
    }));

    const wrapper = mountCategory({
      category: {
        name: 'Awards & Recognition',
        slug: 'puraskar-o-swikriti',
        description: '<p>Recognition of community-led impact.</p>',
      },
      is_awards_category: true,
      landing_page: null,
      items,
    }, { stubCards: false });

    expect(wrapper.get('.igf-listing').classes()).toContain('igf-listing--awards');
    expect(wrapper.get('.igf-card-grid').classes()).toEqual(expect.arrayContaining([
      'igf-card-grid--awards',
      'igf-card-grid--five',
    ]));
    expect(wrapper.get('.igf-awards-count').text()).toContain('5');
    expect(wrapper.get('.igf-awards-count').text()).toContain('recognitions');

    const cards = wrapper.findAll('a.igf-content-card--award');
    expect(cards).toHaveLength(5);
    expect(cards.map(card => card.get('h2').text())).toEqual(names);
    expect(cards.map(card => card.attributes('href'))).toEqual(items.map(item => item.public_url));
    expect(cards[0].get('img').attributes()).toMatchObject({
      loading: 'lazy',
      decoding: 'async',
    });
    expect(cards[0].text()).toContain(items[0].sub_title);
  });
});
