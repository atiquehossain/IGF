import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import PageBlocks from '@/Shared/PageBlocks.vue';
import pageBlocksSource from '@/Shared/PageBlocks.vue?raw';

const heroBlock = {
  uuid: 'hero-carousel-test',
  type: 'hero',
  is_enabled: true,
  show_on_desktop: true,
  show_on_mobile: true,
  content: {
    autoplay: true,
    interval: 3000,
    pause_on_hover: true,
    slides: [
      {
        eyebrow: 'First story', heading: 'First carousel heading', body: 'First description',
        primary_label: 'Donate', primary_url: '/donate', secondary_label: '', secondary_url: '',
        report_label: '', report_url: '', image: '/image/first.jpg', overlay_opacity: 64,
      },
      {
        eyebrow: 'Second story', heading: 'Second carousel heading', body: 'Second description',
        primary_label: 'Learn more', primary_url: '/about-us', secondary_label: '', secondary_url: '',
        report_label: '', report_url: '', image: '/image/second.jpg', overlay_opacity: 58,
      },
    ],
  },
};

const sharedInterfaceSettings = {
  hero_carousel_label: 'Featured highlights',
  hero_carousel_roledescription: 'carousel',
  hero_controls_label: 'Hero slides',
  hero_previous_label: 'Previous slide',
  hero_next_label: 'Next slide',
  hero_show_slide_label: 'Show slide {current} of {total}',
  hero_pause_label: 'Pause automatic slides',
  hero_resume_label: 'Resume automatic slides',
  hero_status_label: 'Slide {current} of {total}: {heading}',
  team_biography_label: 'Biography',
  team_hide_details_label: 'Hide details',
  team_hide_details_for_label: 'Hide details for {name}',
  team_keep_details_open_label: 'Keep details open',
  team_keep_details_open_for_label: 'Keep details open for {name}',
  team_external_link_suffix: 'opens in a new tab',
  team_qualification_label: 'Qualification',
  team_social_links_label: 'Social links',
  team_member_label: 'Team member',
  team_view_details_label: 'View details',
  team_view_details_for_label: 'View details for {name}',
  team_item_link_label: 'View profile',
  team_profile_accessible_label: 'View {name} profile',
  team_linkedin_label: 'Connect',
  team_website_label: 'Website',
  partner_external_link_label: '{name}, opens in a new tab',
  video_embed_title: 'Embedded video',
  video_unsupported_message: 'Your browser does not support this video.',
  gallery_carousel_label: 'Gallery',
  gallery_carousel_roledescription: 'carousel',
  gallery_controls_label: 'Gallery slides',
  gallery_previous_slide_label: 'Previous slide',
  gallery_next_slide_label: 'Next slide',
  gallery_show_slide_label: 'Show gallery slide {current} of {total}',
  gallery_open_image_label: 'Open image: {name}',
  gallery_image_label: 'Gallery image',
  gallery_close_image_label: 'Close image',
  gallery_previous_image_label: 'Previous image',
  gallery_next_image_label: 'Next image',
  gallery_view_all_label: 'View all photos',
  gallery_view_all_url: '/gallery',
  campaign_form_title: 'Make a donation',
  campaign_custom_amount_label: 'Custom donation amount',
  campaign_custom_amount_placeholder: 'Enter a custom amount',
  campaign_submit_label: 'Donate now',
};

function setPageSettings({ locale = 'en', shared = {}, donation = {}, regional = {} } = {}) {
  usePage().props = {
    locale,
    siteSettings: {
      shared_blocks: { ...sharedInterfaceSettings, ...shared },
      regional: {
        number_locale: 'en-BD',
        date_locale: 'en-BD',
        timezone: 'Asia/Dhaka',
        ...regional,
      },
      donation_page: {
        show_custom_amount: true,
        amount_button_count: '2',
        amount_1: 500,
        amount_2: 1000,
        amount_1_impact: 'Learning materials',
        amount_2_impact: 'Family support',
        featured_amount_index: '2',
        gift_subtitle: 'Every contribution strengthens the cause you choose.',
        frequency_accessible_label: 'Donation frequency',
        frequency_label: 'One-time',
        frequency_daily_label: 'Daily',
        frequency_weekly_label: 'Weekly',
        frequency_monthly_label: 'Monthly',
        frequency_coming_soon_label: 'Coming soon',
        frequency_help: 'One-time gifts are charged once.',
        accountability_label: 'Transparent, accountable giving',
        gateway_heading: 'Secure payment options',
        ...donation,
      },
    },
  };
}

describe('PageBlocks hero carousel', () => {
  beforeEach(() => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: false });
    setPageSettings();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  test('supports arrows, dots, accessible controls, and automatic rotation', async () => {
    vi.useFakeTimers();
    const wrapper = mount(PageBlocks, { props: { blocks: [heroBlock] } });

    expect(wrapper.get('section[aria-roledescription="carousel"]').attributes('aria-label')).toBe('Featured highlights');
    expect(wrapper.get('h1').text()).toBe('First carousel heading');
    expect(wrapper.findAll('.igf-hero-carousel__dots button')).toHaveLength(2);

    await wrapper.get('button[aria-label="Next slide"]').trigger('click');
    expect(wrapper.get('h1').text()).toBe('Second carousel heading');
    expect(wrapper.get('a[aria-label="Learn more"]').text()).toContain('Learn more');
    expect(wrapper.find('.igf-hero-carousel__dots button[aria-current="true"]').attributes('aria-label')).toContain('slide 2');

    await wrapper.findAll('.igf-hero-carousel__dots button')[0].trigger('click');
    expect(wrapper.get('h1').text()).toBe('First carousel heading');

    await vi.advanceTimersByTimeAsync(3500);
    expect(wrapper.get('h1').text()).toBe('Second carousel heading');

    await wrapper.get('button[aria-label="Pause automatic slides"]').trigger('click');
    await vi.advanceTimersByTimeAsync(3500);
    expect(wrapper.get('h1').text()).toBe('Second carousel heading');

    wrapper.unmount();
  });

  test('takes every carousel interface label from public site settings', () => {
    setPageSettings({
      shared: {
        hero_carousel_label: 'Impact highlights',
        hero_carousel_roledescription: 'story rotator',
        hero_controls_label: 'Impact story controls',
        hero_previous_label: 'Earlier impact story',
        hero_next_label: 'Later impact story',
        hero_show_slide_label: 'Open impact {current} of {total}',
        hero_pause_label: 'Stop rotating impact stories',
        hero_resume_label: 'Resume rotating impact stories',
        hero_status_label: 'Impact {current} of {total}: {heading}',
      },
    });
    const wrapper = mount(PageBlocks, { props: { blocks: [heroBlock] } });
    const section = wrapper.get('.igf-page-block--hero');
    const arrows = wrapper.findAll('.igf-hero-carousel__arrow');

    expect(section.attributes('aria-label')).toBe('Impact highlights');
    expect(section.attributes('aria-roledescription')).toBe('story rotator');
    expect(wrapper.get('.igf-hero-carousel__controls').attributes('aria-label')).toBe('Impact story controls');
    expect(arrows[0].attributes('aria-label')).toBe('Earlier impact story');
    expect(arrows[1].attributes('aria-label')).toBe('Later impact story');
    expect(wrapper.findAll('.igf-hero-carousel__dots button')[0].attributes('aria-label')).toBe('Open impact 1 of 2');
    expect(wrapper.get('.igf-hero-carousel__pause').attributes('aria-label')).toBe('Stop rotating impact stories');
    expect(wrapper.get('[role="status"]').text()).toContain('Impact 1 of 2: First carousel heading');

    wrapper.unmount();
  });

});

describe('PageBlocks Ways to Give', () => {
  beforeEach(() => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: false });
    setPageSettings();
  });

  const block = layout => ({
    uuid: `ways-${layout}`,
    type: 'ways_to_give',
    is_enabled: true,
    show_on_desktop: true,
    show_on_mobile: true,
    content: {
      layout,
      eyebrow: 'Ways to give',
      heading: 'Choose your impact',
      body: '<p>Every option is managed securely.</p>',
      empty_state: 'No giving options are available.',
      items: [
        {
          key: 'cause:education', heading: 'Education Fund', body: '<p>Help learners thrive.</p>',
          destination: 'Education program', image: '/storage/media/education.webp',
          url: '/donate?cause=education&project=project-one', link_label: 'Give now',
        },
        {
          key: 'zakat', heading: 'Estimate your Zakat', body: 'Use the calculator.',
          destination: 'Plan your giving', image: '', url: '/zakat', link_label: 'Give your Zakat',
        },
      ],
    },
  });

  test('renders managed images, a consistent fallback, safe destinations, and accessible card labels', () => {
    const wrapper = mount(PageBlocks, { props: { blocks: [block('card_grid')] } });
    const section = wrapper.get('.igf-page-block--ways_to_give');
    const cards = section.findAll('.igf-giving-card');

    expect(section.classes()).toContain('igf-page-block--ways_to_give');
    expect(section.get('.igf-giving').classes()).toContain('igf-giving--card_grid');
    expect(cards).toHaveLength(2);
    expect(cards[0].attributes('href')).toBe('/donate?cause=education&project=project-one');
    expect(cards[0].attributes('aria-label')).toBe('Education Fund. Education program');
    expect(cards[0].get('img').attributes('alt')).toBe('');
    expect(cards[1].get('.fa-hand-holding-heart').attributes('aria-hidden')).toBeUndefined();
    expect(cards[1].text()).toContain('Give your Zakat');
    expect(section.html()).toContain('<p>Help learners thrive.</p>');
  });

  test('supports single CTA, banner, and an intentional empty state without inventing options', () => {
    const single = mount(PageBlocks, { props: { blocks: [block('single_cta')] } });
    expect(single.findAll('.igf-giving-card')).toHaveLength(1);
    expect(single.get('.igf-giving').classes()).toContain('igf-giving--single_cta');

    const banner = mount(PageBlocks, { props: { blocks: [block('banner')] } });
    expect(banner.findAll('.igf-giving-card')).toHaveLength(2);
    expect(banner.get('.igf-giving').classes()).toContain('igf-giving--banner');

    const empty = block('card_grid');
    empty.content.items = [];
    const emptyWrapper = mount(PageBlocks, { props: { blocks: [empty] } });
    expect(emptyWrapper.findAll('.igf-giving-card')).toHaveLength(0);
    expect(emptyWrapper.get('.igf-dynamic-empty').attributes('role')).toBe('status');
    expect(emptyWrapper.get('.igf-dynamic-empty').text()).toBe('No giving options are available.');
  });

  test('keeps responsive actions keyboard-visible and at least 44px tall in source CSS', () => {
    expect(pageBlocksSource).toContain('.igf-giving-card:focus-visible');
    expect(pageBlocksSource).toContain('min-height:44px');
    expect(pageBlocksSource).toContain('.igf-giving__options { grid-template-columns:1fr;');
  });
});

describe('PageBlocks partner logo wall', () => {
  beforeEach(() => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: false });
    setPageSettings();
  });

  test('renders accessible linked and unlinked partner cards in the reference grid structure', async () => {
    const block = {
      uuid: 'partners-reference-grid',
      type: 'partners',
      is_enabled: true,
      show_on_desktop: true,
      show_on_mobile: true,
      content: {
        heading: 'Partner Organizations',
        items: [
          {
            heading: 'Bangladesh Brand Forum',
            image: '/image/partners/bangladesh-brand-forum.png',
            image_alt: 'Bangladesh Brand Forum',
            url: 'https://bbf.digital/',
          },
          {
            heading: 'Daraz Bangladesh',
            image: '/image/partners/daraz.png',
            image_alt: 'Daraz Bangladesh',
            url: '',
          },
        ],
      },
    };

    const wrapper = mount(PageBlocks, { props: { blocks: [block] } });
    const section = wrapper.get('.igf-page-block--partners');
    const cards = section.findAll('.igf-partner-card');
    const linkedCard = cards[0];

    expect(section.get('h2').text()).toBe('Partner Organizations');
    expect(section.get('.igf-partner-underline').attributes('aria-hidden')).toBe('true');
    expect(section.find('.igf-page-block__eyebrow').exists()).toBe(false);
    expect(section.find('.igf-section-lead').exists()).toBe(false);
    expect(cards).toHaveLength(2);
    expect(linkedCard.element.tagName).toBe('A');
    expect(linkedCard.attributes('href')).toBe('https://bbf.digital/');
    expect(linkedCard.attributes('target')).toBe('_blank');
    expect(linkedCard.attributes('rel')).toBe('noopener noreferrer');
    expect(linkedCard.attributes('aria-label')).toBe('Bangladesh Brand Forum, opens in a new tab');
    expect(linkedCard.get('img').attributes('alt')).toBe('');
    expect(cards[1].element.tagName).toBe('DIV');
    expect(cards[1].get('img').attributes('alt')).toBe('Daraz Bangladesh');
    expect(section.find('figcaption').exists()).toBe(false);

    await cards[1].get('img').trigger('error');
    expect(cards[1].get('img').attributes()).toHaveProperty('hidden');
    expect(cards[1].get('.igf-partner-card__fallback').text()).toBe('Daraz Bangladesh');

    wrapper.unmount();
  });

  test('keeps the legacy partner-card variant safe and visually aligned', () => {
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [{
          uuid: 'partner-card-variant',
          type: 'cards',
          is_enabled: true,
          show_on_desktop: true,
          show_on_mobile: true,
          content: {
            variant: 'partners',
            heading: 'Partner Organizations',
            items: [
              { heading: 'First partner', image: '/first.png', url: 'javascript:alert(1)' },
              { heading: 'Second partner', image: '', url: '' },
            ],
          },
        }],
      },
    });

    const section = wrapper.get('.igf-page-block--partners');
    const cards = section.findAll('.igf-partner-card');

    expect(section.classes()).toContain('igf-page-block--cards');
    expect(cards).toHaveLength(2);
    expect(cards[0].element.tagName).toBe('DIV');
    expect(cards[0].get('img').attributes('alt')).toBe('First partner');
    expect(cards[1].element.tagName).toBe('DIV');
    expect(cards[1].get('strong').text()).toBe('Second partner');
    expect(section.find('a[href^="javascript:"]').exists()).toBe(false);

    wrapper.unmount();
  });
});

describe('PageBlocks statistic animations', () => {
  const statsBlock = (content = {}) => ({
    uuid: 'stats-animation-test',
    type: 'stats',
    is_enabled: true,
    show_on_desktop: true,
    show_on_mobile: true,
    content: {
      heading: 'Our impact',
      animation_enabled: true,
      animation_type: 'count_up',
      animation_duration: 300,
      animation_delay: 0,
      items: [{ value: '23,000+', label: 'Volunteers', icon: 'people' }],
      ...content,
    },
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  beforeEach(() => {
    setPageSettings();
  });

  test('counts formatted values when the section enters the viewport', async () => {
    vi.useFakeTimers();
    window.matchMedia = vi.fn().mockReturnValue({ matches: false });
    let intersectionCallback;
    const observe = vi.fn();
    const unobserve = vi.fn();
    vi.stubGlobal('IntersectionObserver', vi.fn(function IntersectionObserverMock(callback) {
      intersectionCallback = callback;
      this.observe = observe;
      this.unobserve = unobserve;
      this.disconnect = vi.fn();
    }));
    vi.stubGlobal('requestAnimationFrame', vi.fn(callback => window.setTimeout(() => callback(performance.now() + 1000), 10)));
    vi.stubGlobal('cancelAnimationFrame', vi.fn(id => window.clearTimeout(id)));

    const wrapper = mount(PageBlocks, { props: { blocks: [statsBlock()] } });
    const statistic = wrapper.get('.igf-stat');
    expect(statistic.get('strong').text()).toBe('23,000+');
    expect(observe).toHaveBeenCalledWith(statistic.element);

    intersectionCallback([{ isIntersecting: true, target: statistic.element }]);
    await wrapper.vm.$nextTick();
    expect(statistic.get('strong').text()).toBe('0+');
    await vi.advanceTimersByTimeAsync(20);
    await wrapper.vm.$nextTick();

    expect(statistic.get('strong').text()).toBe('23,000+');
    expect(statistic.classes()).toContain('is-visible');
    expect(unobserve).toHaveBeenCalledWith(statistic.element);
    wrapper.unmount();
  });

  test('supports non-counting styles and honors reduced-motion preferences', () => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: true });
    const observer = vi.fn();
    vi.stubGlobal('IntersectionObserver', observer);
    const wrapper = mount(PageBlocks, { props: { blocks: [statsBlock({ animation_type: 'pop' })] } });
    const statistic = wrapper.get('.igf-stat');

    expect(statistic.classes()).toContain('igf-stat--pop');
    expect(statistic.classes()).toContain('is-visible');
    expect(statistic.get('strong').text()).toBe('23,000+');
    expect(observer).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  test('can disable statistic animation completely', () => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: false });
    const wrapper = mount(PageBlocks, { props: { blocks: [statsBlock({ animation_enabled: false })] } });
    const statistic = wrapper.get('.igf-stat');

    expect(statistic.classes()).not.toContain('igf-stat--animated');
    expect(statistic.get('strong').text()).toBe('23,000+');
    wrapper.unmount();
  });

  test('uses the admin-selected number locale for static and reduced-motion values', () => {
    setPageSettings({ regional: { number_locale: 'bn-BD' } });
    window.matchMedia = vi.fn().mockReturnValue({ matches: true });
    const wrapper = mount(PageBlocks, { props: { blocks: [statsBlock()] } });

    expect(wrapper.get('.igf-stat strong').text()).toBe(`${new Intl.NumberFormat('bn-BD').format(23000)}+`);
    wrapper.unmount();
  });

});

describe('PageBlocks regional event dates', () => {
  test('formats raw managed event dates with the configured locale and timezone', () => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: true });
    setPageSettings({ regional: { date_locale: 'bn-BD', timezone: 'Asia/Dhaka' } });
    const publishedAt = '2026-08-19';
    const block = {
      uuid: 'regional-event-date',
      type: 'events',
      is_enabled: true,
      show_on_desktop: true,
      show_on_mobile: true,
      content: {
        heading: 'Events',
        items: [{ heading: 'Community day', published_at: publishedAt, url: '/event/community-day' }],
      },
    };
    const wrapper = mount(PageBlocks, { props: { blocks: [block] } });
    const date = new Date(publishedAt);

    expect(wrapper.get('.igf-event-cards__date strong').text())
      .toBe(new Intl.DateTimeFormat('bn-BD', { day: '2-digit', timeZone: 'Asia/Dhaka' }).format(date));
    expect(wrapper.get('.igf-event-cards__date small').text())
      .toBe(new Intl.DateTimeFormat('bn-BD', { month: 'short', timeZone: 'Asia/Dhaka' }).format(date));
    wrapper.unmount();
  });
});

describe('PageBlocks editorial links', () => {
  beforeEach(() => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: false });
    setPageSettings();
  });

  const cardsBlock = viewAllUrl => ({
    uuid: `cards-${viewAllUrl}`,
    type: 'cards',
    is_enabled: true,
    show_on_desktop: true,
    show_on_mobile: true,
    content: {
      heading: 'Projects',
      view_all_label: 'View all projects',
      view_all_url: viewAllUrl,
      items: [],
    },
  });

  test('does not render a javascript view-all link and preserves a safe local link', () => {
    const wrapper = mount(PageBlocks, {
      props: { blocks: [cardsBlock('javascript:alert(1)'), cardsBlock('/projects')] },
    });
    const links = wrapper.findAll('.igf-section-heading > .igf-text-link');

    expect(links).toHaveLength(1);
    expect(links[0].attributes('href')).toBe('/projects');
    expect(wrapper.html()).not.toContain('javascript:');

    wrapper.unmount();
  });

  test('keeps URL-less initiatives non-interactive and renders safe contribution actions', () => {
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [
          {
            uuid: 'school-initiatives',
            type: 'cards',
            content: {
              variant: 'initiatives',
              heading: 'Our initiatives',
              items: [
                { heading: 'Free education', body: 'Inclusive learning for every child.', icon: 'school', url: '' },
                { heading: 'Unsafe action', body: 'This must not become a link.', url: 'javascript:alert(1)' },
              ],
            },
          },
          {
            uuid: 'school-contributions',
            type: 'cards',
            content: {
              variant: 'contributions',
              heading: 'Ways to contribute',
              items: [
                { heading: 'Sponsor a child', body: 'Support one learner.', url: '/sponsor-child', link_label: 'View sponsorship' },
              ],
            },
          },
        ],
      },
    });

    const initiatives = wrapper.get('.igf-page-block--initiatives');
    expect(initiatives.findAll('.igf-campus-initiative-card')).toHaveLength(2);
    expect(initiatives.findAll('article.igf-campus-initiative-card')).toHaveLength(2);
    expect(initiatives.findAll('a.igf-campus-initiative-card')).toHaveLength(0);

    const contributions = wrapper.get('.igf-page-block--contributions');
    expect(contributions.findAll('article.igf-campus-contribution-card')).toHaveLength(1);
    const action = contributions.get('.igf-campus-contribution-card__link');
    expect(action.attributes('href')).toBe('/sponsor-child');
    expect(action.text()).toContain('View sponsorship');
    expect(wrapper.html()).not.toContain('javascript:');

    wrapper.unmount();
  });

  test('renders the campus gallery as a finite mosaic carousel with an accessible lightbox', async () => {
    const items = Array.from({ length: 6 }, (_, index) => ({
      heading: `School photo ${index + 1}`,
      image: `/images/school-${index + 1}.jpg`,
      image_alt: `Learners in school photo ${index + 1}`,
    }));
    const wrapper = mount(PageBlocks, {
      attachTo: document.body,
      props: {
        blocks: [{
          uuid: 'school-gallery',
          type: 'gallery',
          content: { variant: 'campus-gallery', heading: 'Gallery', items },
        }],
      },
    });

    const slides = wrapper.findAll('.igf-campus-gallery__slide');
    expect(slides).toHaveLength(2);
    expect(slides[1].attributes('aria-hidden')).toBe('true');
    expect(slides[1].attributes('inert')).toBeDefined();
    expect(wrapper.findAll('.igf-campus-gallery__lightbox-trigger')[5].attributes('tabindex')).toBe('-1');
    expect(wrapper.findAll('.igf-campus-gallery__dots button')).toHaveLength(2);
    expect(wrapper.get('.igf-campus-gallery__dots').attributes('aria-label')).toBe('Gallery slides');
    expect(wrapper.findAll('.igf-campus-gallery__dots button')[1].attributes('aria-label')).toBe('Show gallery slide 2 of 2');
    const arrows = wrapper.findAll('.igf-campus-gallery__arrow');
    expect(arrows[0].attributes('disabled')).toBeDefined();
    await arrows[1].trigger('click');
    expect(wrapper.get('.igf-campus-gallery__track').attributes('style')).toContain('translateX(-100%)');
    expect(arrows[1].attributes('disabled')).toBeDefined();
    expect(slides[1].attributes('aria-hidden')).toBeUndefined();
    expect(slides[1].attributes('inert')).toBeUndefined();

    const trigger = wrapper.findAll('.igf-campus-gallery__lightbox-trigger')[5];
    await trigger.trigger('click');
    expect(wrapper.get('[role="dialog"]').attributes('aria-modal')).toBe('true');
    expect(wrapper.get('.igf-campus-lightbox figure img').attributes('src')).toBe('/images/school-6.jpg');
    const close = wrapper.get('.igf-campus-lightbox__close');
    expect(close.attributes('aria-label')).toBe('Close image');
    expect(document.activeElement).toBe(close.element);

    await close.trigger('keydown', { key: 'Tab', shiftKey: true });
    expect(document.activeElement).toBe(wrapper.get('.igf-campus-lightbox__nav--previous').element);

    await close.trigger('click');
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    expect(document.activeElement).toBe(trigger.element);

    wrapper.unmount();
  });

  test('uses Customizer wording for campus gallery controls and lightbox actions', async () => {
    setPageSettings({ shared: {
      gallery_carousel_roledescription: 'photo carousel',
      gallery_controls_label: 'Choose a photo group',
      gallery_previous_slide_label: 'Earlier photos',
      gallery_next_slide_label: 'Later photos',
      gallery_show_slide_label: 'Open group {current} of {total}',
      gallery_open_image_label: 'Enlarge {name}',
      gallery_close_image_label: 'Close photo viewer',
      gallery_previous_image_label: 'Earlier photo',
      gallery_next_image_label: 'Later photo',
    } });
    const items = Array.from({ length: 6 }, (_, index) => ({
      heading: `Photo ${index + 1}`,
      image: `/images/photo-${index + 1}.jpg`,
    }));
    const wrapper = mount(PageBlocks, {
      attachTo: document.body,
      props: { blocks: [{ uuid: 'localized-gallery', type: 'gallery', content: { variant: 'campus-gallery', heading: '', items } }] },
    });

    expect(wrapper.get('.igf-campus-gallery__carousel').attributes('aria-roledescription')).toBe('photo carousel');
    expect(wrapper.get('.igf-campus-gallery__dots').attributes('aria-label')).toBe('Choose a photo group');
    expect(wrapper.findAll('.igf-campus-gallery__arrow')[0].attributes('aria-label')).toBe('Earlier photos');
    expect(wrapper.findAll('.igf-campus-gallery__arrow')[1].attributes('aria-label')).toBe('Later photos');
    expect(wrapper.findAll('.igf-campus-gallery__dots button')[1].attributes('aria-label')).toBe('Open group 2 of 2');
    expect(wrapper.findAll('.igf-campus-gallery__lightbox-trigger')[0].attributes('aria-label')).toBe('Enlarge Photo 1');

    await wrapper.findAll('.igf-campus-gallery__lightbox-trigger')[0].trigger('click');
    expect(wrapper.get('.igf-campus-lightbox__close').attributes('aria-label')).toBe('Close photo viewer');
    expect(wrapper.get('.igf-campus-lightbox__nav--previous').attributes('aria-label')).toBe('Earlier photo');
    expect(wrapper.get('.igf-campus-lightbox__nav--next').attributes('aria-label')).toBe('Later photo');
    wrapper.unmount();
  });

  test('restores focus to the active image trigger after lightbox navigation changes slides', async () => {
    const items = Array.from({ length: 6 }, (_, index) => ({
      heading: `School photo ${index + 1}`,
      image: `/images/school-${index + 1}.jpg`,
    }));
    const wrapper = mount(PageBlocks, {
      attachTo: document.body,
      props: {
        blocks: [{
          uuid: 'school-gallery-focus-return',
          type: 'gallery',
          content: { variant: 'campus-gallery', heading: 'Gallery', items },
        }],
      },
    });
    const triggers = wrapper.findAll('.igf-campus-gallery__lightbox-trigger');
    const originalTrigger = triggers[4];
    const activeTrigger = triggers[5];

    await originalTrigger.trigger('click');
    await wrapper.get('.igf-campus-lightbox__nav--next').trigger('click');

    const slides = wrapper.findAll('.igf-campus-gallery__slide');
    expect(slides[0].attributes('inert')).toBeDefined();
    expect(slides[1].attributes('inert')).toBeUndefined();
    expect(wrapper.get('.igf-campus-lightbox figure img').attributes('src')).toBe('/images/school-6.jpg');

    await wrapper.get('.igf-campus-lightbox__close').trigger('click');
    expect(document.activeElement).toBe(activeTrigger.element);
    expect(document.activeElement).not.toBe(originalTrigger.element);

    wrapper.unmount();
  });

  test.each([
    [6, 1],
    [7, 2],
    [8, 3],
  ])('balances a final gallery slide containing %i item(s)', (itemCount, finalCount) => {
    const items = Array.from({ length: itemCount }, (_, index) => ({
      heading: `School photo ${index + 1}`,
      image: `/images/school-${index + 1}.jpg`,
    }));
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [{
          uuid: `school-gallery-${itemCount}`,
          type: 'gallery',
          content: { variant: 'campus-gallery', heading: 'Gallery', items },
        }],
      },
    });
    const slides = wrapper.findAll('.igf-campus-gallery__slide');
    const finalSlide = slides.at(-1);

    expect(slides).toHaveLength(2);
    expect(finalSlide.findAll('figure')).toHaveLength(finalCount);
    expect(finalSlide.classes()).toContain(`igf-campus-gallery__slide--count-${finalCount}`);

    wrapper.unmount();
  });

  test('overrides generic gallery placement for short slides at the tablet breakpoint', () => {
    const tabletStart = pageBlocksSource.indexOf('@media (max-width:767px)');
    const tabletEnd = pageBlocksSource.indexOf('@media (max-width:560px)', tabletStart);
    const tabletCss = pageBlocksSource.slice(tabletStart, tabletEnd);
    const genericPlacement = tabletCss.indexOf(
      '.igf-campus-gallery__slide figure:first-child { grid-row:span 2; }',
    );
    const countOnePlacement = tabletCss.indexOf(
      '.igf-campus-gallery__slide--count-1 figure:first-child { grid-column:1; grid-row:1; }',
    );
    const countTwoPlacement = tabletCss.indexOf(
      '.igf-campus-gallery__slide--count-2 figure,.igf-campus-gallery__slide--count-2 figure:first-child { grid-row:1; }',
    );
    const countThreePlacement = tabletCss.indexOf(
      '.igf-campus-gallery__slide--count-3 figure:first-child { grid-column:1; grid-row:1/-1; }',
    );

    expect(tabletStart).toBeGreaterThanOrEqual(0);
    expect(tabletEnd).toBeGreaterThan(tabletStart);
    expect(tabletCss).toContain(
      '.igf-campus-gallery__slide--count-1 { grid-template-columns:1fr; grid-template-rows:minmax(0,1fr); }',
    );
    expect(tabletCss).toContain(
      '.igf-campus-gallery__slide--count-2 { grid-template-columns:repeat(2,minmax(0,1fr)); grid-template-rows:minmax(0,1fr); }',
    );
    expect(tabletCss).toContain(
      '.igf-campus-gallery__slide--count-3 { grid-template-columns:repeat(2,minmax(0,1fr)); grid-template-rows:repeat(2,minmax(0,1fr)); }',
    );
    expect(countOnePlacement).toBeGreaterThan(genericPlacement);
    expect(countTwoPlacement).toBeGreaterThan(genericPlacement);
    expect(countThreePlacement).toBeGreaterThan(genericPlacement);
  });
});

describe('PageBlocks video and campaign settings', () => {
  beforeEach(() => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: false });
    setPageSettings();
  });

  const block = (uuid, type, content) => ({
    uuid,
    type,
    is_enabled: true,
    show_on_desktop: true,
    show_on_mobile: true,
    content,
  });

  test('uses settings-managed video labels for embeds and native fallback copy', () => {
    setPageSettings({
      shared: {
        video_embed_title: 'Community program film',
        video_unsupported_message: 'This browser cannot play the community film.',
      },
    });
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [
          block('video-embed', 'video', { heading: '', video_url: 'https://youtu.be/abcdefghijk' }),
          block('video-native', 'video', { heading: '', video_url: '/media/community-film.mp4' }),
        ],
      },
    });

    expect(wrapper.get('iframe').attributes('title')).toBe('Community program film');
    expect(wrapper.get('video').attributes('aria-label')).toBe('Community program film');
    expect(wrapper.get('video').text()).toBe('This browser cannot play the community film.');

    wrapper.unmount();
  });

  test('hides the campaign custom amount when the donation setting disables it', () => {
    const campaign = block('campaign', 'cta', {
      variant: 'campaign',
      heading: 'Support community learning',
      primary_url: '/donate',
    });
    setPageSettings({ donation: { show_custom_amount: false } });
    const hidden = mount(PageBlocks, { props: { blocks: [campaign] } });
    expect(hidden.find('.igf-custom-amount').exists()).toBe(false);
    hidden.unmount();

    setPageSettings({ donation: { show_custom_amount: true } });
    const visible = mount(PageBlocks, { props: { blocks: [campaign] } });
    const input = visible.get('.igf-custom-amount input');
    expect(input.attributes('name')).toBe('custom_amount');
    expect(input.attributes('min')).toBe('10');
    expect(input.attributes('max')).toBe('500000');
    expect(input.attributes('step')).toBe('0.01');
    expect(visible.get('input[name="frequency"]').attributes('value')).toBe('one_time');
    expect(visible.findAll('.igf-campaign-frequency__tabs button[disabled]')).toHaveLength(3);
    expect(visible.findAll('.igf-amounts label')).toHaveLength(2);
    expect(visible.get('.igf-amounts .is-featured small').text()).toBe('Family support');
    expect(visible.get('.igf-campaign__assurance').text()).toBe('Transparent, accountable giving');
    visible.unmount();
  });
});

describe('PageBlocks team cards', () => {
  const teamBlock = {
    uuid: 'governance-team',
    type: 'team',
    is_enabled: true,
    show_on_desktop: true,
    show_on_mobile: true,
    content: {
      eyebrow: 'Governance',
      heading: 'Board of directors',
      body: 'The board provides mission stewardship and oversight.',
      items: [
        {
          id: 11,
          heading: 'Amina Rahman',
          designation: 'Chairperson',
          biography: 'Amina guides the foundation strategy and governance.',
          qualification: 'MSc in Development Studies',
          image: '/image/amina.jpg',
          image_alt: 'Amina Rahman',
          social_links: [
            { platform: 'linkedin', url: 'https://www.linkedin.com/in/amina' },
            { platform: 'x', url: 'javascript:alert(1)', label: 'Unsafe profile' },
          ],
        },
        {
          id: 12,
          heading: 'Bashir Hossain',
          body: 'Executive member',
          biography: 'Bashir supports partnerships and community programmes.',
          qualification: '',
          image: '',
          social_links: [
            { platform: 'facebook', url: 'https://www.facebook.com/bashir', label: 'Follow Bashir' },
          ],
        },
      ],
    },
  };

  beforeEach(() => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: false });
    setPageSettings();
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  test('renders dynamic details, safe social links, and a photo fallback', () => {
    const wrapper = mount(PageBlocks, { props: { blocks: [teamBlock] } });
    const cards = wrapper.findAll('.igf-team-card');

    expect(cards).toHaveLength(2);
    expect(cards[0].get('img').attributes('alt')).toBe('Amina Rahman');
    expect(cards[0].get('.igf-team-card__front').find('h3').exists()).toBe(false);
    expect(cards[0].get('.igf-team-card__back-heading p').text()).toBe('Chairperson');
    expect(cards[0].get('.igf-team-card__biography').text()).toContain('foundation strategy');
    expect(cards[0].get('.igf-team-card__qualification').text()).toContain('MSc in Development Studies');
    expect(cards[1].get('.igf-team-card__initials').text()).toBe('BH');
    expect(cards[1].get('.igf-team-card__fallback-copy').text()).toContain('Executive member');

    const firstToggle = cards[0].get('.igf-team-card__toggle');
    const firstBack = cards[0].get('.igf-team-card__back');
    const firstLinks = cards[0].findAll('.igf-team-card__social-link');
    expect(firstToggle.attributes('aria-expanded')).toBe('false');
    expect(firstToggle.attributes('aria-controls')).toBe(firstBack.attributes('id'));
    expect(firstBack.attributes('aria-hidden')).toBe('true');
    expect(firstLinks).toHaveLength(1);
    expect(firstLinks[0].attributes('tabindex')).toBe('-1');
    expect(firstLinks[0].attributes('href')).toBe('https://www.linkedin.com/in/amina');
    expect(firstLinks[0].attributes('target')).toBe('_blank');
    expect(firstLinks[0].attributes('rel')).toBe('noopener noreferrer');
    expect(firstLinks[0].text()).toBe('Connect');
    expect(firstLinks[0].get('i').classes()).toContain('fa-linkedin-in');
    expect(cards[0].get('.igf-team-card__socials').classes()).not.toContain('is-scrollable');
    expect(wrapper.html()).not.toContain('javascript:');

    wrapper.unmount();
  });

  test('replaces a photo that fails to load with the member fallback', async () => {
    const imageBlock = {
      ...teamBlock,
      content: {
        ...teamBlock.content,
        items: teamBlock.content.items.map((item, index) => ({
          ...item,
          image: index === 0 ? item.image : '/image/bashir.jpg',
        })),
      },
    };
    const wrapper = mount(PageBlocks, { props: { blocks: [imageBlock] } });
    const cards = wrapper.findAll('.igf-team-card');
    const firstCard = cards[0];
    const image = firstCard.get('.igf-team-card__media img');

    await image.trigger('error');
    await wrapper.vm.$nextTick();

    expect(firstCard.find('.igf-team-card__media img').exists()).toBe(false);
    expect(firstCard.get('.igf-team-card__initials').text()).toBe('AR');
    expect(firstCard.get('.igf-team-card__fallback-copy').text()).toContain('Amina Rahman');
    expect(firstCard.get('.igf-team-card__fallback-copy').text()).toContain('Chairperson');
    expect(cards[1].get('.igf-team-card__media img').attributes('src')).toBe('/image/bashir.jpg');

    wrapper.unmount();
  });

  test('matches the reference card proportions, flip timing, and responsive widths', () => {
    expect(pageBlocksSource).toContain('grid-template-columns:repeat(auto-fit,300px)');
    expect(pageBlocksSource).toContain('--igf-team-card-height:440px; position:relative; width:300px');
    expect(pageBlocksSource).toContain('height:var(--igf-team-card-height)');
    expect(pageBlocksSource).toContain('perspective:1000px');
    expect(pageBlocksSource).toContain('transition:transform 600ms ease-in-out');
    expect(pageBlocksSource).toContain('transform:rotateY(180deg); background:#f5f5ed');
    expect(pageBlocksSource).toContain('object-fit:cover; object-position:center 20%');
    expect(pageBlocksSource).toContain("font-family:'Poppins','Hanken Grotesk',Arial,sans-serif");
    expect(pageBlocksSource).toContain('@media (max-width:680px)');
    expect(pageBlocksSource).toContain('--igf-team-card-height:420px; width:280px');
    expect(pageBlocksSource).toContain('@media (max-width:360px)');
    expect(pageBlocksSource).toContain('.igf-team-card { width:260px; }');
  });

  test('keeps long biography content scrollable without moving the fixed social CTA', () => {
    const biography = Array.from({ length: 18 }, () => 'Amina guides long-term foundation strategy.').join(' ');
    const longProfileBlock = {
      ...teamBlock,
      content: {
        ...teamBlock.content,
        items: [{ ...teamBlock.content.items[0], biography }],
      },
    };
    const wrapper = mount(PageBlocks, { props: { blocks: [longProfileBlock] } });
    const back = wrapper.get('.igf-team-card__back');
    const backContent = back.get('.igf-team-card__back-content');
    const socials = back.get('.igf-team-card__socials');

    expect(backContent.text()).toContain('long-term foundation strategy');
    expect(backContent.find('.igf-team-card__socials').exists()).toBe(false);
    expect(backContent.element.nextElementSibling).toBe(socials.element);
    expect(back.element.lastElementChild).toBe(socials.element);
    expect(pageBlocksSource).toContain('.igf-team-card__back { padding:28px; transform:rotateY(180deg); background:#f5f5ed; overflow:hidden; }');
    expect(pageBlocksSource).toContain('.igf-team-card__back-content { min-height:0; flex:1; overflow-y:auto; overscroll-behavior:contain; }');

    wrapper.unmount();
  });

  test('keeps twelve dynamic social CTAs reachable in a bounded scrolling footer', async () => {
    const socialLinks = Array.from({ length: 12 }, (_, index) => ({
      platform: 'website',
      label: `Profile ${index + 1}`,
      url: `https://example.com/profile-${index + 1}`,
    }));
    const manyLinksBlock = {
      ...teamBlock,
      content: {
        ...teamBlock.content,
        items: [{ ...teamBlock.content.items[0], social_links: socialLinks }],
      },
    };
    const wrapper = mount(PageBlocks, { props: { blocks: [manyLinksBlock] } });
    const card = wrapper.get('.igf-team-card');
    const socials = card.get('.igf-team-card__socials');

    expect(socials.classes()).toContain('is-scrollable');
    expect(socials.findAll('.igf-team-card__social-link')).toHaveLength(12);
    expect(socials.findAll('.igf-team-card__social-link')[11].attributes('href')).toBe('https://example.com/profile-12');

    await card.get('.igf-team-card__toggle').trigger('click');
    expect(socials.findAll('.igf-team-card__social-link').every(link => link.attributes('tabindex') === undefined)).toBe(true);
    expect(pageBlocksSource).toContain('.igf-team-card__socials.is-scrollable { max-height:174px; overflow-y:auto; overscroll-behavior:contain; padding-right:4px; scrollbar-gutter:stable; }');

    wrapper.unmount();
  });

  test('uses the reference social CTA tokens and a safe stacked reduced-motion layout', () => {
    const reducedMotionStart = pageBlocksSource.indexOf('@media (prefers-reduced-motion:reduce)');
    const reducedMotionCss = pageBlocksSource.slice(reducedMotionStart);

    expect(pageBlocksSource).toContain('padding:13px 24px; border:1.5px solid #d4d6de; border-radius:14px; background:#f5f5ed; color:#2d3a4e');
    expect(pageBlocksSource).toContain('.igf-team-card__social-link:hover { border-color:#b0b4bf; background:#ebebdf; }');
    expect(reducedMotionStart).toBeGreaterThanOrEqual(0);
    expect(reducedMotionCss).toContain('.igf-team-card { height:auto; min-height:var(--igf-team-card-height); perspective:none; }');
    expect(reducedMotionCss).toContain('.igf-team-card__back-content { overflow:visible; }');
    expect(reducedMotionCss).toContain('.igf-team-card__toggle { top:0; right:0; bottom:auto; left:0; height:var(--igf-team-card-height); }');
    expect(reducedMotionCss).toContain('.igf-team-card.is-open .igf-team-card__back { display:flex; }');
  });

  test('auto-flips one card at 55% visibility on touch devices and unflips when it leaves', async () => {
    let intersectionCallback;
    let observerOptions;
    const observe = vi.fn();
    const disconnect = vi.fn();
    window.matchMedia = vi.fn(query => ({
      matches: query === '(hover: none), (pointer: coarse)',
    }));
    vi.stubGlobal('IntersectionObserver', vi.fn(function IntersectionObserverMock(callback, options) {
      intersectionCallback = callback;
      observerOptions = options;
      this.observe = observe;
      this.disconnect = disconnect;
    }));

    const wrapper = mount(PageBlocks, { props: { blocks: [teamBlock] } });
    const cards = wrapper.findAll('.igf-team-card');

    expect(observerOptions).toEqual({ threshold: [0, 0.55] });
    expect(observe).toHaveBeenCalledTimes(2);

    intersectionCallback([{ target: cards[0].element, isIntersecting: true, intersectionRatio: 0.55 }]);
    await wrapper.vm.$nextTick();
    expect(cards[0].classes()).toContain('is-open');
    expect(cards[1].classes()).not.toContain('is-open');

    intersectionCallback([{ target: cards[1].element, isIntersecting: true, intersectionRatio: 0.75 }]);
    await wrapper.vm.$nextTick();
    expect(cards[0].classes()).not.toContain('is-open');
    expect(cards[1].classes()).toContain('is-open');

    await cards[1].trigger('click');
    expect(cards[1].classes()).toContain('is-open');
    await cards[1].trigger('click');
    expect(cards[1].classes()).not.toContain('is-open');

    intersectionCallback([{ target: cards[0].element, isIntersecting: true, intersectionRatio: 0.8 }]);
    await wrapper.vm.$nextTick();
    await cards[0].trigger('click');
    intersectionCallback([{ target: cards[0].element, isIntersecting: true, intersectionRatio: 0.4 }]);
    await wrapper.vm.$nextTick();
    expect(cards[0].classes()).not.toContain('is-open');

    wrapper.unmount();
    expect(disconnect).toHaveBeenCalledOnce();
  });

  test('opens only one card and closes with Escape while restoring toggle focus', async () => {
    const wrapper = mount(PageBlocks, { attachTo: document.body, props: { blocks: [teamBlock] } });
    const cards = wrapper.findAll('.igf-team-card');
    const firstToggle = cards[0].get('.igf-team-card__toggle');
    const secondToggle = cards[1].get('.igf-team-card__toggle');

    await firstToggle.trigger('click');
    expect(firstToggle.attributes('aria-expanded')).toBe('true');
    expect(cards[0].classes()).toContain('is-open');
    expect(cards[0].get('.igf-team-card__social-link').attributes('tabindex')).toBeUndefined();

    await secondToggle.trigger('click');
    expect(firstToggle.attributes('aria-expanded')).toBe('false');
    expect(cards[0].classes()).not.toContain('is-open');
    expect(secondToggle.attributes('aria-expanded')).toBe('true');
    expect(cards[1].classes()).toContain('is-open');

    const secondLink = cards[1].get('.igf-team-card__social-link');
    secondLink.element.focus();
    await secondLink.trigger('keydown', { key: 'Escape' });
    await wrapper.vm.$nextTick();

    expect(secondToggle.attributes('aria-expanded')).toBe('false');
    expect(cards[1].classes()).not.toContain('is-open');
    expect(document.activeElement).toBe(secondToggle.element);

    wrapper.unmount();
  });

  test('makes hovered details and links interactive, then supports persistent click state', async () => {
    const wrapper = mount(PageBlocks, { props: { blocks: [teamBlock] } });
    const card = wrapper.findAll('.igf-team-card')[0];
    const toggle = card.get('.igf-team-card__toggle');
    const back = card.get('.igf-team-card__back');
    const link = card.get('.igf-team-card__social-link');

    await card.trigger('pointerenter');
    expect(card.classes()).toContain('is-open');
    expect(toggle.attributes('aria-expanded')).toBe('true');
    expect(toggle.text()).toContain('Keep details open');
    expect(back.attributes('aria-hidden')).toBeUndefined();
    expect(back.attributes('inert')).toBeUndefined();
    expect(link.attributes('tabindex')).toBeUndefined();

    await toggle.trigger('click');
    await card.trigger('pointerleave');
    expect(card.classes()).toContain('is-open');
    expect(toggle.text()).toContain('Hide details');

    await toggle.trigger('click');
    expect(card.classes()).not.toContain('is-open');
    expect(link.attributes('tabindex')).toBe('-1');

    wrapper.unmount();
  });

  test('animates a member with only a name and designation on mouse hover', async () => {
    const designationOnlyBlock = {
      ...teamBlock,
      content: {
        ...teamBlock.content,
        items: [{
          id: 13,
          heading: 'Designation Member',
          designation: 'Executive member',
          biography: '',
          qualification: '',
          image: '',
          social_links: [],
        }],
      },
    };
    const wrapper = mount(PageBlocks, { props: { blocks: [designationOnlyBlock] } });
    const card = wrapper.get('.igf-team-card');
    const toggle = card.get('.igf-team-card__toggle');
    const back = card.get('.igf-team-card__back');

    expect(card.classes()).toContain('has-details');
    expect(back.text()).toContain('Designation Member');
    expect(back.text()).toContain('Executive member');
    expect(toggle.attributes('aria-expanded')).toBe('false');

    await card.trigger('pointerenter', { pointerType: 'mouse' });

    expect(card.classes()).toContain('is-open');
    expect(toggle.attributes('aria-expanded')).toBe('true');
    expect(back.attributes('aria-hidden')).toBeUndefined();

    wrapper.unmount();
  });

  test('pins and closes a designation-only profile by clicking the card surface', async () => {
    const designationOnlyBlock = {
      ...teamBlock,
      content: {
        ...teamBlock.content,
        items: [{
          id: 13,
          heading: 'Designation Member',
          designation: 'Executive member',
          biography: '',
          qualification: '',
          image: '',
          social_links: [],
        }],
      },
    };
    const wrapper = mount(PageBlocks, { props: { blocks: [designationOnlyBlock] } });
    const card = wrapper.get('.igf-team-card');
    const toggle = card.get('.igf-team-card__toggle');

    await card.trigger('click');
    expect(card.classes()).toContain('is-open');
    expect(toggle.attributes('aria-expanded')).toBe('true');

    await card.trigger('click');
    expect(card.classes()).not.toContain('is-open');
    expect(toggle.attributes('aria-expanded')).toBe('false');

    wrapper.unmount();
  });

  test('does not double-toggle buttons or collapse when a social link is clicked', async () => {
    const wrapper = mount(PageBlocks, { props: { blocks: [teamBlock] } });
    const card = wrapper.findAll('.igf-team-card')[0];
    const toggle = card.get('.igf-team-card__toggle');
    const link = card.get('.igf-team-card__social-link');

    await toggle.trigger('click');
    expect(card.classes()).toContain('is-open');
    expect(toggle.attributes('aria-expanded')).toBe('true');

    await link.trigger('click');
    expect(card.classes()).toContain('is-open');
    expect(toggle.attributes('aria-expanded')).toBe('true');

    await toggle.trigger('click');
    expect(card.classes()).not.toContain('is-open');
    expect(toggle.attributes('aria-expanded')).toBe('false');

    wrapper.unmount();
  });

  test('clears another card hover preview when a different card is activated', async () => {
    const wrapper = mount(PageBlocks, { props: { blocks: [teamBlock] } });
    const cards = wrapper.findAll('.igf-team-card');
    const firstToggle = cards[0].get('.igf-team-card__toggle');
    const secondToggle = cards[1].get('.igf-team-card__toggle');

    await cards[0].trigger('pointerenter');
    expect(cards[0].classes()).toContain('is-open');
    expect(firstToggle.attributes('aria-expanded')).toBe('true');

    await secondToggle.trigger('click');
    expect(cards[0].classes()).not.toContain('is-open');
    expect(firstToggle.attributes('aria-expanded')).toBe('false');
    expect(cards[1].classes()).toContain('is-open');
    expect(secondToggle.attributes('aria-expanded')).toBe('true');
    expect(secondToggle.text()).toContain('Hide details');

    wrapper.unmount();
  });

  test('preserves a revealed card while focus remains inside its back face', async () => {
    const wrapper = mount(PageBlocks, { attachTo: document.body, props: { blocks: [teamBlock] } });
    const cards = wrapper.findAll('.igf-team-card');
    const firstToggle = cards[0].get('.igf-team-card__toggle');
    const firstBack = cards[0].get('.igf-team-card__back');
    const firstLink = cards[0].get('.igf-team-card__social-link');
    const secondToggle = cards[1].get('.igf-team-card__toggle');

    await cards[0].trigger('pointerenter');
    firstLink.element.focus();
    expect(document.activeElement).toBe(firstLink.element);

    await cards[0].trigger('pointerleave');
    expect(cards[0].classes()).toContain('is-open');
    expect(firstToggle.attributes('aria-expanded')).toBe('true');
    expect(firstBack.attributes('inert')).toBeUndefined();
    expect(firstLink.attributes('tabindex')).toBeUndefined();

    await cards[1].trigger('pointerenter');
    expect(cards[0].classes()).toContain('is-open');
    expect(cards[1].classes()).not.toContain('is-open');
    expect(secondToggle.attributes('aria-expanded')).toBe('false');
    expect(firstBack.attributes('inert')).toBeUndefined();
    expect(document.activeElement).toBe(firstLink.element);

    wrapper.unmount();
  });

  test('keeps members without usable details static and does not consume closed Escape', () => {
    const staticBlock = {
      ...teamBlock,
      content: {
        ...teamBlock.content,
        items: [{
          id: 14,
          heading: 'Static Member',
          body: '',
          designation: '',
          biography: '   ',
          qualification: '',
          image: '',
          url: 'javascript:alert(1)',
          social_links: [{ platform: 'linkedin', url: 'javascript:alert(1)' }],
        }],
      },
    };
    const wrapper = mount(PageBlocks, { attachTo: document.body, props: { blocks: [staticBlock] } });
    const card = wrapper.get('.igf-team-card');
    const bubbled = vi.fn();
    document.addEventListener('keydown', bubbled);
    const escape = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });

    expect(card.classes()).not.toContain('has-details');
    expect(card.find('.igf-team-card__toggle').exists()).toBe(false);
    expect(card.find('.igf-team-card__back').exists()).toBe(false);
    card.element.dispatchEvent(escape);
    expect(escape.defaultPrevented).toBe(false);
    expect(bubbled).toHaveBeenCalledOnce();

    document.removeEventListener('keydown', bubbled);
    wrapper.unmount();
  });

  test('localizes team controls and accessible labels for Bangla pages', async () => {
    setPageSettings({
      locale: 'bn',
      shared: {
        team_biography_label: 'জীবনী',
        team_hide_details_label: 'বিস্তারিত লুকান',
        team_hide_details_for_label: '{name}-এর বিস্তারিত লুকান',
        team_keep_details_open_label: 'বিস্তারিত খোলা রাখুন',
        team_keep_details_open_for_label: '{name}-এর বিস্তারিত খোলা রাখুন',
        team_external_link_suffix: 'নতুন ট্যাবে খুলবে',
        team_qualification_label: 'শিক্ষাগত যোগ্যতা',
        team_social_links_label: 'সামাজিক যোগাযোগের লিংক',
        team_member_label: 'দলের সদস্য',
        team_view_details_label: 'বিস্তারিত দেখুন',
        team_view_details_for_label: '{name}-এর বিস্তারিত দেখুন',
        team_item_link_label: 'প্রোফাইল দেখুন',
        team_website_label: 'ওয়েবসাইট',
      },
    });
    const localizedBlock = {
      ...teamBlock,
      content: {
        ...teamBlock.content,
        items: [{ ...teamBlock.content.items[0], heading: 'আমিনা রহমান' }],
      },
    };
    const wrapper = mount(PageBlocks, { props: { blocks: [localizedBlock] } });
    const card = wrapper.get('.igf-team-card');
    const toggle = card.get('.igf-team-card__toggle');

    expect(toggle.text()).toContain('বিস্তারিত দেখুন');
    expect(toggle.attributes('aria-label')).toContain('আমিনা রহমান');
    await toggle.trigger('click');
    expect(toggle.text()).toContain('বিস্তারিত লুকান');
    expect(card.get('.igf-team-card__socials').attributes('aria-label')).toBe('সামাজিক যোগাযোগের লিংক');
    expect(card.get('.igf-team-card__social-link').attributes('aria-label')).toContain('নতুন ট্যাবে খুলবে');
    expect(card.get('.igf-team-card__biography').text()).toContain('জীবনী');
    expect(card.get('.igf-team-card__qualification').text()).toContain('শিক্ষাগত যোগ্যতা');

    wrapper.unmount();
  });
});
