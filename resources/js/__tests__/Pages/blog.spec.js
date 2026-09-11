import { mount } from '@vue/test-utils';
import { router, usePage } from '@inertiajs/vue3';

import Blog from '@/Pages/blog.vue';
import BlogPost from '@/Pages/blog-post.vue';

const layoutStub = { template: '<main><slot /></main>' };
const paginationStub = {
  name: 'PaginationStub',
  props: [
    'modelValue',
    'length',
    'ariaLabel',
    'pageAriaLabel',
    'currentPageAriaLabel',
    'previousAriaLabel',
    'nextAriaLabel',
  ],
  emits: ['update:modelValue'],
  template: '<button type="button" data-test="pagination" @click="$emit(\'update:modelValue\', 2)">{{ ariaLabel }}</button>',
};
const pageBlocksStub = {
  name: 'PageBlocksStub',
  props: ['blocks'],
  template: '<section data-test="blog-blocks">{{ blocks.length }}</section>',
};

const regional = {
  date_locale: 'en-GB',
  number_locale: 'en-GB',
  timezone: 'Asia/Dhaka',
};
const archivePresentation = {
  eyebrow: 'Ideas in action',
  title: 'Ignite journal',
  introduction: 'Stories and practical learning from our work.',
  listing_label: 'Latest stories',
  empty_title: 'No stories yet',
  empty_body: 'Please check back soon.',
  featured_label: 'Featured story',
  read_label: 'Read story',
};

function mountBlog({
  items = [],
  locale = 'en',
  properties = {},
  presentation = {},
  seoLocale = { query_parameter: 'lang' },
  url = '/blog',
} = {}) {
  usePage().props = {
    title: 'Blog',
    locale,
    archive_route: 'frontend.blog',
    archive_presentation: { ...archivePresentation, ...presentation },
    properties: {
      page: 1,
      total_page: 1,
      total_count: items.length,
      ...properties,
    },
    data: { items },
    seoLocale,
    siteSettings: { regional },
  };
  usePage().url = url;

  return mount(Blog, {
    global: {
      stubs: {
        App: layoutStub,
        Layout: layoutStub,
        'v-pagination': paginationStub,
      },
    },
  });
}

function mountPost({ page, archiveUrl = '/blog', presentation = {}, locale = 'en' }) {
  usePage().props = {
    locale,
    archive_presentation: { ...archivePresentation, ...presentation },
    data: {
      page,
      archive_url: archiveUrl,
    },
    siteSettings: { regional },
  };
  usePage().url = page?.public_url || '/blog/a-story';

  return mount(BlogPost, {
    global: {
      stubs: {
        App: layoutStub,
        Layout: layoutStub,
        PageBlocks: pageBlocksStub,
      },
    },
  });
}

describe('Page-backed Blog presentation', () => {
  beforeEach(() => {
    vi.stubGlobal('route', vi.fn(() => '/blog'));
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  test('features the first post and renders every remaining post as a card', () => {
    const items = [
      {
        id: 1,
        uuid: 'featured-post',
        name: 'A school built with its community',
        excerpt: 'How families and volunteers shaped a shared learning space.',
        thumbnail: '/storage/blog/school.webp',
        thumbnail_alt: 'Children learning together at Ignite School',
        published_at: '2026-09-01',
        public_url: '/blog/community-school',
      },
      {
        id: 2,
        uuid: 'second-post',
        name: 'Young volunteers lead locally',
        sub_title: 'A new generation puts ideas into action.',
        published_at: '2026-08-20',
        public_url: '/blog/young-volunteers',
      },
      {
        id: 3,
        uuid: 'third-post',
        name: 'Preparing before the storm',
        excerpt: 'Community-led disaster readiness in coastal Bangladesh.',
        published_at: '2026-08-12',
        public_url: '/blog/storm-readiness',
      },
    ];
    const wrapper = mountBlog({ items });

    const featured = wrapper.get('.igf-blog-featured');
    expect(featured.get('h2').text()).toBe(items[0].name);
    expect(featured.get('a').attributes()).toMatchObject({
      href: items[0].public_url,
      'aria-label': `Read story: ${items[0].name}`,
    });
    expect(featured.get('img').attributes()).toMatchObject({
      src: items[0].thumbnail,
      alt: items[0].thumbnail_alt,
      fetchpriority: 'high',
    });

    const cards = wrapper.findAll('.igf-blog-grid .igf-content-card');
    expect(cards).toHaveLength(2);
    expect(cards.map(card => card.get('h2').text())).toEqual(items.slice(1).map(item => item.name));
    expect(cards.map(card => card.attributes('href'))).toEqual(items.slice(1).map(item => item.public_url));
    expect(cards[0].text()).toContain(items[1].sub_title);
    expect(cards[1].text()).toContain(items[2].excerpt);

    wrapper.unmount();
  });

  test('announces the managed empty state without rendering posts', () => {
    const wrapper = mountBlog();
    const empty = wrapper.get('.igf-blog__empty');

    expect(wrapper.find('.igf-blog-featured').exists()).toBe(false);
    expect(wrapper.find('.igf-blog-grid').exists()).toBe(false);
    expect(empty.attributes()).toMatchObject({ role: 'status', 'aria-live': 'polite' });
    expect(empty.get('h2').text()).toBe(archivePresentation.empty_title);
    expect(empty.get('p').text()).toBe(archivePresentation.empty_body);

    wrapper.unmount();
  });

  test('uses localized pagination labels and preserves the active language query', async () => {
    const get = vi.spyOn(router, 'get').mockImplementation(() => {});
    const wrapper = mountBlog({
      items: [{ id: 1, name: 'একটি গল্প', public_url: '/blog/a-story?lang=bn' }],
      locale: 'bn',
      presentation: {
        pagination_label: '',
        pagination_page_label: '',
        pagination_current_label: '',
        pagination_previous_label: '',
        pagination_next_label: '',
      },
      properties: { page: 1, total_page: 3 },
      url: '/blog?lang=bn&utm_source=ignored',
    });

    const pagination = wrapper.getComponent(paginationStub);
    expect(pagination.props()).toMatchObject({
      modelValue: 1,
      length: 3,
      ariaLabel: 'ব্লগের পাতাসমূহ',
      pageAriaLabel: 'পৃষ্ঠা {0}-এ যান',
      currentPageAriaLabel: 'বর্তমান পৃষ্ঠা, পৃষ্ঠা {0}',
      previousAriaLabel: 'আগের পৃষ্ঠা',
      nextAriaLabel: 'পরের পৃষ্ঠা',
    });

    await wrapper.get('[data-test="pagination"]').trigger('click');

    expect(globalThis.route).toHaveBeenCalledWith('frontend.blog');
    expect(get).toHaveBeenCalledWith('/blog', {
      page: 2,
      lang: 'bn',
    }, {
      preserveState: true,
      preserveScroll: true,
    });

    wrapper.unmount();
  });

  test('prefers visible PageBlocks over the legacy description', () => {
    const blocks = [
      { uuid: 'story-intro', type: 'media-text', content: { heading: 'A managed introduction' } },
      { uuid: 'story-quote', type: 'testimonial', content: { quote: 'Community comes first.' } },
    ];
    const wrapper = mountPost({
      page: {
        name: 'Inside an Ignite classroom',
        description: '<p data-test="legacy-copy">Legacy article copy</p>',
        visible_blocks: blocks,
        public_url: '/blog/ignite-classroom',
      },
    });

    const renderedBlocks = wrapper.getComponent({ name: 'PageBlocksStub' });
    expect(renderedBlocks.props('blocks')).toEqual(blocks);
    expect(wrapper.find('.igf-blog-post__body').exists()).toBe(false);
    expect(wrapper.find('[data-test="legacy-copy"]').exists()).toBe(false);

    wrapper.unmount();
  });

  test('renders sanitized fallback HTML and managed archive labels when no blocks exist', () => {
    const wrapper = mountPost({
      page: {
        name: 'Learning through service',
        sub_title: 'Reflections from a volunteer cohort.',
        description: '<h2>Start where you are</h2><p>Small actions build lasting change.</p>',
        visible_blocks: [],
        public_url: '/blog/learning-through-service?lang=bn',
      },
      archiveUrl: '/blog?lang=bn',
      presentation: {
        back_label: 'সব লেখা',
        footer_label: 'ব্লগে ফিরে যান',
        detail_eyebrow: 'মাঠপর্যায়ের কথা',
      },
      locale: 'bn',
    });

    expect(wrapper.find('[data-test="blog-blocks"]').exists()).toBe(false);
    expect(wrapper.get('.igf-blog-post__prose h2').text()).toBe('Start where you are');
    expect(wrapper.get('.igf-blog-post__prose p').text()).toBe('Small actions build lasting change.');

    const topBackLink = wrapper.get('.igf-blog-post__back');
    const footerBackLink = wrapper.get('.igf-blog-post__footer a');
    expect(topBackLink.attributes('href')).toBe('/blog?lang=bn');
    expect(topBackLink.text()).toContain('সব লেখা');
    expect(footerBackLink.attributes('href')).toBe('/blog?lang=bn');
    expect(footerBackLink.text()).toContain('ব্লগে ফিরে যান');

    wrapper.unmount();
  });
});
