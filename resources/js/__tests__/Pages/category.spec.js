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
        ...(options.contentArchives || {}),
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

  test('uses the bundled programs hero, a balanced four-card grid, and managed closing CTA', () => {
    const items = ['Education', 'Youth Development', 'Disaster Response', 'Livelihoods'].map((name, index) => ({
      id: index + 1,
      name,
      sub_title: `${name} program summary.`,
      thumbnail: `/storage/program-${index + 1}.jpg`,
      public_url: `/page/program-${index + 1}`,
    }));
    const wrapper = mountCategory({
      category: { name: 'Our programs', slug: 'our-causes', description: '<p>Community-led programs.</p>' },
      banner: null,
      archive_design: {
        hero_layout: 'split',
        show_banner: true,
        card_columns: 'auto',
        card_eyebrow: '',
        show_card_eyebrow: false,
        card_link_label: 'Explore program',
        show_card_link: true,
        bottom_cta: {
          enabled: true,
          title: 'Help create lasting change',
          body: 'Support community-led programs.',
          label: 'Donate now',
          url: '/donate',
        },
      },
      items,
    }, {
      stubCards: false,
      contentArchives: { category_hero_layout: 'split', category_show_banner: true },
    });

    expect(wrapper.get('.igf-program-hero__visual img').attributes()).toMatchObject({
      src: '/image/our-cause/our-causes-bg.webp',
      alt: '',
      loading: 'eager',
      fetchpriority: 'high',
    });
    expect(wrapper.get('.igf-card-grid').classes()).toContain('igf-card-grid--four');
    expect(wrapper.findAll('a.igf-content-card')).toHaveLength(4);
    expect(wrapper.find('.igf-content-card__eyebrow').exists()).toBe(false);
    expect(wrapper.get('a.igf-content-card').attributes('aria-label')).toBe('Explore program: Education');
    expect(wrapper.get('.igf-listing__cta a').attributes('href')).toBe('/donate');
  });

  test('honours the normalized admin controls when the programs banner is disabled', () => {
    const wrapper = mountCategory({
      category: { name: 'Our programs', slug: 'our-causes', description: '<p>Community-led programs.</p>' },
      banner: null,
      archive_design: {
        hero_layout: 'compact',
        show_banner: false,
        card_columns: 'auto',
        card_link_label: 'Explore program',
        show_card_link: true,
        bottom_cta: null,
      },
      items: [{ id: 1, name: 'Education', public_url: '/page/education' }],
    }, {
      contentArchives: { category_hero_layout: 'split', category_show_banner: true },
    });

    expect(wrapper.find('.igf-program-hero--split').exists()).toBe(false);
    expect(wrapper.find('.igf-program-hero__visual').exists()).toBe(false);
    expect(wrapper.find('.igf-program-hero__jump').exists()).toBe(true);
  });

  test('does not leak the programs fallback, jump link, or closing CTA to generic archives', () => {
    const wrapper = mountCategory({
      category: { name: 'Projects', slug: 'projects', description: '' },
      banner: null,
      archive_design: {
        hero_layout: 'compact',
        show_banner: false,
        card_columns: 'auto',
        card_link_label: 'Explore program',
        show_card_link: true,
        bottom_cta: { enabled: true, title: 'Donate', body: '', label: 'Donate now', url: '/donate' },
      },
      items: [{ id: 1, name: 'A project', public_url: '/page/a-project' }],
    }, {
      contentArchives: { category_hero_layout: 'split', category_show_banner: true },
    });

    expect(wrapper.find('.igf-program-hero__visual').exists()).toBe(false);
    expect(wrapper.find('.igf-program-hero__jump').exists()).toBe(false);
    expect(wrapper.find('.igf-listing__cta').exists()).toBe(false);
  });

  test('lets an explicit managed card-column choice override automatic four-card balancing', () => {
    const wrapper = mountCategory({
      category: { name: 'Our programs', slug: 'our-causes', description: '' },
      banner: null,
      archive_design: {
        hero_layout: 'compact',
        show_banner: false,
        card_columns: '3',
        card_link_label: 'Explore program',
        show_card_link: true,
        bottom_cta: null,
      },
      items: [1, 2, 3, 4].map(id => ({ id, name: `Program ${id}`, public_url: `/page/program-${id}` })),
    });

    expect(wrapper.get('.igf-card-grid').classes()).toContain('igf-card-grid--columns-3');
    expect(wrapper.get('.igf-card-grid').classes()).not.toContain('igf-card-grid--four');
  });

  test('renders an allow-listed category banner and its optional primary action', async () => {
    const wrapper = mountCategory({
      category: { name: 'Projects', slug: 'projects', description: '' },
      banner: {
        image_url: '/storage/banner/projects.webp',
        image_alt: 'Volunteers working together',
        cta_label: 'Meet the team',
        cta_url: '/about-us',
      },
      archive_design: {
        hero_layout: 'split',
        show_banner: true,
        card_columns: 'auto',
        card_link_label: 'Explore',
        show_card_link: true,
        bottom_cta: null,
      },
      items: [],
    }, {
      contentArchives: { category_hero_layout: 'split', category_show_banner: true },
    });

    const heroImage = wrapper.get('.igf-program-hero__visual img');
    expect(heroImage.attributes()).toMatchObject({
      src: '/storage/banner/projects.webp',
      alt: 'Volunteers working together',
    });
    expect(wrapper.get('.igf-program-hero__primary').attributes('href')).toBe('/about-us');
    expect(wrapper.get('.igf-program-hero__primary').text()).toContain('Meet the team');

    await heroImage.trigger('error');
    expect(wrapper.find('.igf-program-hero__visual').exists()).toBe(false);
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
