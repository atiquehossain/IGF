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

  test('renders every safe static extension with nontechnical choices and semantic markup', () => {
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [layoutBlock([{
          id: 'row-static-elements',
          layout: 'full',
          columns: [{ id: 'column-static-elements', elements: [
            {
              id: 'icon-element', type: 'icon', icon: 'heart', accessible_label: 'Care and support',
              decorative: false, size: 'large', style: 'circle',
            },
            {
              id: 'file-element', type: 'file', path: '/storage/media/annual-report.pdf',
              label: 'Download the annual report', description: 'PDF, 2 MB', open_in_new_tab: true,
            },
            {
              id: 'card-element', type: 'card', eyebrow: 'Our work', heading: 'Community learning',
              body: 'A safe plain-text summary.', image: '/storage/media/learning.jpg', image_alt: 'Learners together',
              link_label: 'Explore the program', url: '/programs/learning', style: 'soft',
            },
            {
              id: 'stat-element', type: 'stat', value: '12,500+', label: 'People reached',
              icon: 'people', emphasis: 'accent',
            },
            {
              id: 'quote-element', type: 'quote', quote: 'Dignity must guide every action.',
              attribution: 'Amina Rahman', role: 'Community volunteer', image: '/storage/media/amina.jpg',
              image_alt: 'Amina Rahman', style: 'featured',
            },
            {
              id: 'gallery-element', type: 'gallery', columns: '2', lightbox: false,
              items: [
                { id: 'photo-one', path: '/storage/media/photo-one.jpg', alt: 'Students reading', caption: 'Reading together' },
                { id: 'photo-two', path: '/storage/media/photo-two.jpg', alt: 'Volunteers meeting', caption: 'Planning together' },
              ],
            },
            {
              id: 'accordion-element', type: 'accordion', allow_one_open: true,
              items: [
                { id: 'answer-one', question: 'Who can volunteer?', answer: '<p>Anyone who supports our <strong>values</strong>.</p>' },
                { id: 'answer-two', question: 'Where do you work?', answer: '<p>Across Bangladesh.</p>' },
              ],
            },
            {
              id: 'timeline-element', type: 'timeline', style: 'steps',
              items: [
                { id: 'step-one', date_label: 'Step 1', heading: 'Choose a role', body: 'Find a suitable opportunity.', icon: 'people' },
                { id: 'step-two', date_label: 'Step 2', heading: 'Apply', body: 'Send your details.', icon: '' },
              ],
            },
            {
              id: 'callout-element', type: 'callout', eyebrow: 'Take action', heading: 'Join the community',
              body: '<p>Make a <em>meaningful</em> contribution.</p>', icon: 'heart', tone: 'success',
              link_label: 'Become a volunteer', url: '/volunteer',
            },
          ] }],
        }])],
      },
    });

    const icon = wrapper.get('.igf-layout-icon');
    expect(icon.classes()).toEqual(expect.arrayContaining(['igf-layout-icon--large', 'igf-layout-icon--circle']));
    expect(icon.attributes()).toEqual(expect.objectContaining({ role: 'img', 'aria-label': 'Care and support' }));

    const file = wrapper.get('a.igf-layout-file');
    expect(file.attributes()).toEqual(expect.objectContaining({
      href: '/storage/media/annual-report.pdf', target: '_blank', rel: 'noopener noreferrer',
    }));
    expect(file.text()).toContain('PDF, 2 MB');
    expect(file.get('.sr-only').text()).toBe(', opens in a new tab');

    const card = wrapper.get('a.igf-layout-card');
    expect(card.classes()).toContain('igf-layout-card--soft');
    expect(card.attributes('href')).toBe('/programs/learning');
    expect(card.get('img').attributes('alt')).toBe('Learners together');
    expect(card.get('.igf-layout-card__link').text()).toContain('Explore the program');

    expect(wrapper.get('.igf-layout-stat').classes()).toContain('igf-layout-stat--accent');
    expect(wrapper.get('.igf-layout-stat').text()).toContain('12,500+');
    expect(wrapper.get('.igf-layout-quote').classes()).toContain('igf-layout-quote--featured');
    expect(wrapper.get('.igf-layout-quote blockquote').text()).toBe('Dignity must guide every action.');
    expect(wrapper.get('.igf-layout-quote figcaption').text()).toContain('Community volunteer');

    const gallery = wrapper.get('.igf-layout-gallery');
    expect(gallery.classes()).toContain('igf-layout-gallery--columns-2');
    expect(gallery.findAll(':scope > figure')).toHaveLength(2);
    expect(gallery.findAll('button')).toHaveLength(0);
    expect(gallery.findAll('img')[0].attributes('alt')).toBe('Students reading');

    const answers = wrapper.findAll('.igf-layout-accordion details');
    expect(answers).toHaveLength(2);
    expect(answers[0].attributes('name')).toBe('layout-accordion-layout-test-accordion-element');
    expect(answers[0].get('summary').text()).toBe('Who can volunteer?');
    expect(answers[0].get('strong').text()).toBe('values');

    const timeline = wrapper.get('ol.igf-layout-timeline');
    expect(timeline.classes()).toContain('igf-layout-timeline--steps');
    expect(timeline.findAll('li')).toHaveLength(2);
    expect(timeline.findAll('h3').map(item => item.text())).toEqual(['Choose a role', 'Apply']);

    const callout = wrapper.get('aside.igf-layout-callout');
    expect(callout.classes()).toContain('igf-layout-callout--success');
    expect(callout.get('em').text()).toBe('meaningful');
    expect(callout.get('a').attributes('href')).toBe('/volunteer');

    wrapper.unmount();
  });

  test('rejects unsafe static media and links, normalizes style tokens, and ignores managed types', () => {
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [layoutBlock([{
          layout: 'full',
          columns: [{ elements: [
            { type: 'icon', icon: 'fa-solid fa-skull', decorative: true, size: 'huge', style: 'position:fixed' },
            { type: 'file', path: 'data:text/html,<script>alert(1)</script>', label: 'Unsafe file' },
            {
              type: 'card', heading: '<img src=x onerror=alert(1)>', body: '<script>alert(2)</script>',
              image: 'javascript:alert(3)', url: 'javascript:alert(4)', style: 'rogue-class',
            },
            {
              type: 'quote', quote: '<script>alert(5)</script>', image: 'data:image/svg+xml,<svg onload=alert(6)>',
              style: 'rogue-class',
            },
            {
              type: 'gallery', columns: '99', lightbox: false,
              items: [
                { path: 'javascript:alert(7)', alt: 'Unsafe' },
                { path: '/storage/media/safe.jpg', alt: '<img src=x onerror=alert(8)>', caption: '<script>alert(9)</script>' },
              ],
            },
            { type: 'timeline', style: 'rogue-class', items: [{ heading: '<img src=x>', body: '<script>x</script>' }] },
            { type: 'callout', heading: 'Safe heading', url: 'javascript:alert(10)', link_label: 'Unsafe link', tone: 'rogue-class' },
            { type: 'content_feed', heading: 'Managed content must wait' },
            { type: 'managed_form', heading: 'Managed form must wait' },
          ] }],
        }])],
      },
    });

    expect(wrapper.find('.igf-layout-file').exists()).toBe(false);
    expect(wrapper.find('a.igf-layout-card').exists()).toBe(false);
    expect(wrapper.get('article.igf-layout-card').classes()).toContain('igf-layout-card--standard');
    expect(wrapper.get('article.igf-layout-card').find('img').exists()).toBe(false);
    expect(wrapper.get('.igf-layout-card__heading').text()).toBe('<img src=x onerror=alert(1)>');
    expect(wrapper.get('.igf-layout-card__body').text()).toBe('<script>alert(2)</script>');
    expect(wrapper.get('.igf-layout-quote').classes()).toContain('igf-layout-quote--standard');
    expect(wrapper.get('.igf-layout-quote').find('script').exists()).toBe(false);
    expect(wrapper.get('.igf-layout-gallery').classes()).toContain('igf-layout-gallery--columns-3');
    expect(wrapper.findAll('.igf-layout-gallery img')).toHaveLength(1);
    expect(wrapper.get('.igf-layout-gallery figcaption').text()).toBe('<script>alert(9)</script>');
    expect(wrapper.get('.igf-layout-timeline').classes()).toContain('igf-layout-timeline--timeline');
    expect(wrapper.get('.igf-layout-timeline').find('script').exists()).toBe(false);
    expect(wrapper.get('.igf-layout-callout').classes()).toContain('igf-layout-callout--accent');
    expect(wrapper.get('.igf-layout-callout').find('a').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('Managed content must wait');
    expect(wrapper.text()).not.toContain('Managed form must wait');
    expect(wrapper.find('script').exists()).toBe(false);
    expect(wrapper.html()).not.toContain('javascript:');
    expect(wrapper.html()).not.toContain('position:fixed');
    expect(wrapper.get('.igf-layout-icon i').classes()).toContain('fa-circle-dot');

    wrapper.unmount();
  });

  test('opens the layout gallery lightbox accessibly, supports navigation, and restores focus', async () => {
    const wrapper = mount(PageBlocks, {
      attachTo: document.body,
      props: {
        blocks: [layoutBlock([{
          id: 'gallery-row',
          layout: 'full',
          columns: [{ id: 'gallery-column', elements: [{
            id: 'gallery-lightbox-element',
            type: 'gallery',
            columns: '2',
            lightbox: true,
            items: [
              { id: 'one', path: '/storage/media/one.jpg', alt: 'First photo', caption: 'First caption' },
              { id: 'two', path: '/storage/media/two.jpg', alt: 'Second photo', caption: 'Second caption' },
            ],
          }] }],
        }])],
      },
    });

    const triggers = wrapper.findAll('.igf-layout-gallery__trigger');
    expect(triggers).toHaveLength(2);
    expect(triggers[0].attributes('aria-label')).toBe('Open image: First caption');

    await triggers[0].trigger('click');
    const dialog = wrapper.get('.igf-layout-gallery-lightbox__dialog');
    expect(dialog.attributes('role')).toBe('dialog');
    expect(dialog.attributes('aria-modal')).toBe('true');
    expect(dialog.attributes('aria-label')).toBe('First caption');
    expect(dialog.get('img').attributes()).toEqual(expect.objectContaining({
      src: '/storage/media/one.jpg', alt: 'First photo',
    }));

    const close = wrapper.get('.igf-layout-gallery-lightbox__close');
    expect(document.activeElement).toBe(close.element);
    await close.trigger('keydown', { key: 'Tab', shiftKey: true });
    expect(document.activeElement).toBe(wrapper.get('.igf-layout-gallery-lightbox__nav--next').element);

    await wrapper.get('.igf-layout-gallery-lightbox__nav--next').trigger('click');
    expect(dialog.get('img').attributes('src')).toBe('/storage/media/two.jpg');
    expect(dialog.get('figcaption').text()).toBe('Second caption');
    expect(wrapper.get('.igf-layout-gallery-lightbox__nav--next').attributes('disabled')).toBeDefined();

    await dialog.trigger('keydown', { key: 'Escape' });
    expect(wrapper.find('.igf-layout-gallery-lightbox').exists()).toBe(false);
    expect(document.activeElement).toBe(triggers[0].element);

    wrapper.unmount();
  });

  test('includes responsive, focus-visible, and small-screen rules for the static extensions', () => {
    expect(pageBlocksSource).toContain('.igf-layout-gallery--columns-4 { grid-template-columns:repeat(4,minmax(0,1fr)); }');
    expect(pageBlocksSource).toContain('.igf-layout-gallery--columns-3,.igf-layout-gallery--columns-4 { grid-template-columns:repeat(2,minmax(0,1fr)); }');
    expect(pageBlocksSource).toContain('.igf-layout-gallery { grid-template-columns:1fr; }');
    expect(pageBlocksSource).toContain('.igf-layout-gallery__trigger:focus-visible');
    expect(pageBlocksSource).toContain('.igf-layout-accordion summary:focus-visible');
    expect(pageBlocksSource).toContain('.igf-layout-callout { grid-template-columns:1fr; }');
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
