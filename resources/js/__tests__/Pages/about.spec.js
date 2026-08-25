import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import About from '@/Pages/about.vue';

const layoutStub = { template: '<main><slot /></main>' };
const pageBlocksStub = {
  name: 'PageBlocksStub',
  inheritAttrs: true,
  props: ['blocks'],
  template: '<section data-test="about-blocks">{{ blocks.length }}</section>',
};

function mountAbout(blocks = []) {
  usePage().props = {
    data: {
      about_us: {
        name: 'About Ignite Global Foundation',
        sub_title: 'A volunteer-led movement built around opportunity.',
        description: '<p>Ignite works alongside communities across Bangladesh.</p>',
        visible_blocks: blocks,
      },
      founders_letter: null,
    },
    siteSettings: {
      shared_blocks: {
        about_eyebrow: 'About Ignite',
        about_fallback_title: 'About us',
        founder_eyebrow: 'From our founder',
      },
    },
  };

  return mount(About, {
    global: {
      stubs: {
        Layout: layoutStub,
        PageBlocks: pageBlocksStub,
      },
    },
  });
}

describe('About Us presentation', () => {
  test('renders the dynamic About hero and passes managed blocks through an isolated design class', () => {
    const blocks = [
      {
        uuid: 'mission-vision',
        type: 'cards',
        content: {
          variant: 'about-pillars',
          items: [
            { eyebrow: 'Our mission', heading: 'Mission heading' },
            { eyebrow: 'Our vision', heading: 'Vision heading' },
          ],
        },
      },
      { uuid: 'stats', type: 'stats', content: { items: [] } },
    ];

    const wrapper = mountAbout(blocks);

    expect(wrapper.get('.igf-about-hero h1').text()).toBe('About Ignite Global Foundation');
    expect(wrapper.get('.igf-about-hero__eyebrow').text()).toContain('About Ignite');
    expect(wrapper.get('.igf-about-hero__lead').text()).toContain('volunteer-led movement');
    expect(wrapper.get('.igf-about-hero__statement').text()).toContain('works alongside communities');

    const renderedBlocks = wrapper.getComponent({ name: 'PageBlocksStub' });
    expect(renderedBlocks.classes()).toContain('igf-page-blocks--about');
    expect(renderedBlocks.props('blocks')).toEqual(blocks);
  });

  test('does not render a second page hero when administrators add a managed hero block', () => {
    const blocks = [
      {
        uuid: 'managed-hero',
        type: 'hero',
        content: { heading: 'Managed About hero' },
      },
      { uuid: 'story', type: 'timeline', content: { items: [] } },
    ];

    const wrapper = mountAbout(blocks);

    expect(wrapper.find('.igf-about-hero').exists()).toBe(false);
    expect(wrapper.get('[data-test="about-blocks"]').text()).toBe('2');
  });

  test('keeps the legacy fallback available when no managed blocks exist', () => {
    const wrapper = mountAbout([]);

    expect(wrapper.get('.igf-about-hero h1').text()).toBe('About Ignite Global Foundation');
    expect(wrapper.get('.igf-about__legacy h2').text()).toBe('About Ignite Global Foundation');
    expect(wrapper.get('.igf-about__legacy article').text()).toContain('works alongside communities');
  });
});
