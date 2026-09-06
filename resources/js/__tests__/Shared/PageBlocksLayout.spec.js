import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import PageBlocks from '@/Shared/PageBlocks.vue';
import pageBlocksSource from '@/Shared/PageBlocks.vue?raw';

function layoutBlock(rows) {
  return {
    uuid: 'layout-test',
    type: 'layout',
    is_enabled: true,
    show_on_desktop: true,
    show_on_mobile: true,
    content: { rows },
  };
}

function setPageSettings() {
  usePage().props = {
    locale: 'en',
    siteSettings: {
      shared_blocks: {
        video_embed_title: 'Embedded video',
        video_unsupported_message: 'Your browser does not support this video.',
      },
      regional: {
        number_locale: 'en-BD',
        date_locale: 'en-BD',
        timezone: 'Asia/Dhaka',
      },
      donation_page: {},
    },
  };
}

describe('PageBlocks visual layout renderer', () => {
  beforeEach(() => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: false });
    setPageSettings();
  });

  test('renders the supported row, column, and element contract safely', () => {
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [layoutBlock([{
          layout: 'third_two_thirds',
          width: 'wide',
          background: 'dark',
          spacing: 'generous',
          columns: [
            {
              elements: [
                { type: 'heading', level: 'h3', text: 'A flexible heading' },
                { type: 'rich_text', body: '<p>Formatted <strong>copy</strong>.</p>' },
                { type: 'button', label: 'Primary action', url: '/donate', style: 'primary' },
                { type: 'button', label: 'Text action', url: 'javascript:alert(1)', style: 'text' },
                { type: 'divider' },
                { type: 'spacer', size: 'large' },
              ],
            },
            {
              elements: [
                { type: 'image', path: '/image/community.jpg', alt: 'Community members', caption: 'Working together' },
                { type: 'video', source_type: 'upload', source: '/storage/videos/story.mp4', title: 'Community story' },
                { type: 'video', source_type: 'youtube', source: 'https://youtu.be/dQw4w9WgXcQ', title: 'YouTube story' },
                { type: 'button', label: 'Secondary action', url: 'https://example.com', style: 'secondary' },
              ],
            },
          ],
        }])],
      },
    });

    const row = wrapper.get('.igf-layout-row');
    expect(row.classes()).toEqual(expect.arrayContaining([
      'igf-layout-row--third_two_thirds',
      'igf-layout-row--width-wide',
      'igf-layout-row--background-dark',
      'igf-layout-row--spacing-generous',
    ]));
    expect(wrapper.findAll('.igf-layout-column')).toHaveLength(2);
    expect(wrapper.get('h3.igf-layout-heading').text()).toBe('A flexible heading');
    expect(wrapper.get('.igf-layout-copy strong').text()).toBe('copy');
    expect(wrapper.get('.igf-layout-media img').attributes()).toEqual(expect.objectContaining({
      src: '/image/community.jpg',
      alt: 'Community members',
    }));
    expect(wrapper.get('.igf-layout-media figcaption').text()).toBe('Working together');
    expect(wrapper.get('video').attributes()).toEqual(expect.objectContaining({
      src: '/storage/videos/story.mp4',
      'aria-label': 'Community story',
    }));
    expect(wrapper.get('iframe').attributes()).toEqual(expect.objectContaining({
      src: 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
      title: 'YouTube story',
    }));

    const buttons = wrapper.findAll('a.igf-button');
    expect(buttons).toHaveLength(3);
    expect(buttons[0].classes()).toContain('igf-button--primary');
    expect(buttons[1].attributes('href')).toBe('#');
    expect(buttons[1].classes()).toContain('igf-layout-button--text');
    expect(buttons[2].classes()).toContain('igf-button--outline');
    expect(wrapper.find('.igf-layout-divider').exists()).toBe(true);
    expect(wrapper.get('.igf-layout-spacer').classes()).toContain('igf-layout-spacer--large');

    wrapper.unmount();
  });

  test('normalizes unsafe choices and ignores malformed columns and elements', () => {
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [layoutBlock([
          {
            layout: 'not-a-layout',
            width: 'wide extra-class',
            background: 'script',
            spacing: 'huge',
            columns: [{ elements: [
              { type: 'heading', level: 'h1', text: 'Safe fallback heading' },
              { type: 'image', path: 'data:text/html,bad', alt: 'Unsafe image' },
              { type: 'video', source_type: 'youtube', source: 'https://example.com/not-youtube' },
              { type: 'custom_html', body: '<script>alert(1)</script>' },
            ] }],
          },
          {
            layout: 'halves',
            columns: [{ elements: [{ type: 'heading', text: 'Incomplete row' }] }],
          },
        ])],
      },
    });

    const row = wrapper.get('.igf-layout-row');
    expect(wrapper.findAll('.igf-layout-row')).toHaveLength(1);
    expect(row.classes()).toEqual(expect.arrayContaining([
      'igf-layout-row--full',
      'igf-layout-row--width-standard',
      'igf-layout-row--background-default',
      'igf-layout-row--spacing-standard',
    ]));
    expect(wrapper.get('h2.igf-layout-heading').text()).toBe('Safe fallback heading');
    expect(wrapper.find('.igf-layout-media').exists()).toBe(false);
    expect(wrapper.find('.igf-layout-video').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('alert(1)');

    wrapper.unmount();
  });

  test.each([
    'youtube.com/watch?v=AbCdEfGhI_1',
    'www.youtube.com/shorts/AbCdEfGhI_1',
    'http://youtu.be/AbCdEfGhI_1',
  ])('does not render a layout YouTube video without an explicit secure URL: %s', source => {
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [layoutBlock([{
          layout: 'full',
          columns: [{ elements: [{ type: 'video', source_type: 'youtube', source, title: 'Rejected video' }] }],
        }])],
      },
    });

    expect(wrapper.find('.igf-layout-video').exists()).toBe(false);
    expect(wrapper.find('iframe').exists()).toBe(false);
    wrapper.unmount();
  });

  test('canonicalizes an explicit HTTPS layout YouTube URL through the privacy-enhanced host', () => {
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [layoutBlock([{
          layout: 'full',
          columns: [{ elements: [{
            type: 'video',
            source_type: 'youtube',
            source: 'https://www.youtube.com/shorts/AbCdEfGhI_1',
            title: 'Secure video',
          }] }],
        }])],
      },
    });

    expect(wrapper.get('.igf-layout-video iframe').attributes('src'))
      .toBe('https://www.youtube-nocookie.com/embed/AbCdEfGhI_1');
    wrapper.unmount();
  });

  test('includes responsive grid rules for every preset', () => {
    expect(pageBlocksSource).toContain('.igf-layout-row--halves .igf-layout-row__columns');
    expect(pageBlocksSource).toContain('.igf-layout-row--thirds .igf-layout-row__columns');
    expect(pageBlocksSource).toContain('.igf-layout-row--quarter .igf-layout-row__columns');
    expect(pageBlocksSource).toContain('.igf-layout-row--third_two_thirds .igf-layout-row__columns');
    expect(pageBlocksSource).toContain('.igf-layout-row--two_thirds_third .igf-layout-row__columns');
    expect(pageBlocksSource).toContain('grid-template-columns:minmax(0,1fr)!important');
  });

  test('uses persisted layout identities as Vue keys with positional fallbacks', () => {
    expect(pageBlocksSource).toContain(':key="row.id || `layout-row-${rowIndex}`"');
    expect(pageBlocksSource).toContain(':key="`layout-column-${row.id || rowIndex}-${columnIndex}`"');
    expect(pageBlocksSource).toContain(':key="element.id || `layout-element-${rowIndex}-${columnIndex}-${elementIndex}`"');
  });
});
