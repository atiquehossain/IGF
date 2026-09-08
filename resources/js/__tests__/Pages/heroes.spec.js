import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import HeroesPage from '@/Pages/heroes.vue';
import HeroesRegionMap from '@/Pages/Heroes/HeroesRegionMap.vue';
import heroesPageSource from '@/Pages/heroes.vue?raw';
import carouselSource from '@/Pages/Heroes/HeroesProfileCarousel.vue?raw';
import regionMapSource from '@/Pages/Heroes/HeroesRegionMap.vue?raw';
import districtMap from '../../../data/bangladesh-districts.json';
import administrativeDivisions from '../../../../database/data/bangladesh-administrative-divisions.json';

const layoutStub = { name: 'Layout', template: '<div><slot /></div>' };
const members = [
  {
    id: 1,
    name: 'Amina Rahman',
    designation: 'Community organiser',
    biography: 'Amina listens first and builds with her neighbours.\n\nShe leads volunteer teams across Dhaka.',
    image: '/storage/amina.jpg',
    social_links: [{ platform: 'linkedin', label: 'LinkedIn', url: 'https://linkedin.example/amina' }],
  },
  {
    id: 2,
    name: 'Bashir Hossain',
    designation: 'Youth mentor',
    biography: 'Bashir helps young people turn ideas into action.',
    image: '/storage/bashir.jpg',
  },
  { id: 3, name: 'Chaya Das', designation: 'Volunteer lead', biography: 'Chaya connects local volunteers.' },
];

function mountPage(props) {
  usePage().props = { locale: 'en', ...props };
  return mount(HeroesPage, { global: { stubs: { Layout: layoutStub } } });
}

beforeEach(() => {
  window.matchMedia = vi.fn().mockReturnValue({
    matches: false,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  });
});
afterEach(() => {
  vi.useRealTimers();
});

describe('Meet the Heroes public journey', () => {
  test('renders the managed national showcase and native division gateways', async () => {
    const wrapper = mountPage({
      scope: 'national',
      title: 'SEO title that should not replace presentation copy',
      presentation: {
        eyebrow: 'Our people',
        title: 'Meet Our Community Champions',
        introduction: 'People creating durable change across Bangladesh.',
        map_heading: 'Choose a division',
        map_help: 'Open a division page from the map or list.',
      },
      data: {
        members,
        divisions: [{ slug: 'dhaka', label: 'Dhaka', url: '/meet-the-heroes/division/dhaka' }],
        districts: [],
        activities: [],
      },
    });

    expect(wrapper.get('#heroes-page-title').text()).toBe('Meet Our Community Champions');
    expect(wrapper.get('.igf-heroes-page__eyebrow').text()).toContain('Our people');
    expect(wrapper.get('.igf-heroes-page__header > p:last-child').text()).toBe('People creating durable change across Bangladesh.');
    expect(wrapper.get('.igf-heroes-map__heading h2').text()).toBe('Choose a division');
    expect(wrapper.findAll('.igf-heroes-map.is-division a.igf-heroes-map__link')).toHaveLength(1);
    expect(wrapper.findAll('.igf-heroes-map.is-division .igf-heroes-map__link.is-unavailable')).toHaveLength(7);
    expect(wrapper.get('[data-region-key="dhaka"]').attributes('href')).toBe('/meet-the-heroes/division/dhaka');
    expect(wrapper.get('[data-region-key="chattogram"]').element.tagName.toLowerCase()).toBe('g');
    expect(wrapper.get('[data-region-key="chattogram"]').attributes('href')).toBeUndefined();
    expect(wrapper.findAll('.igf-heroes-map__list a')).toHaveLength(1);

    const people = wrapper.findAll('.igf-heroes-carousel__person');
    expect(people).toHaveLength(3);
    expect(people[0].attributes('aria-pressed')).toBe('true');
    expect(wrapper.get('.igf-heroes-carousel__profile').text()).toContain('Amina listens first');
    expect(wrapper.findAll('.igf-heroes-carousel__biography p')).toHaveLength(2);

    await people[0].trigger('keydown', { key: 'ArrowRight' });
    expect(wrapper.findAll('.igf-heroes-carousel__person')[1].attributes('aria-pressed')).toBe('true');
    expect(wrapper.get('.igf-heroes-carousel__profile h2').text()).toBe('Bashir Hossain');
    wrapper.unmount();
  });

  test('defaults the dedicated carousel to five-second autoplay while honoring an explicit opt-out', async () => {
    vi.useFakeTimers();
    const automatic = mountPage({ scope: 'national', presentation: { title: 'Meet the Heroes' }, data: { members } });
    expect(automatic.get('.igf-heroes-carousel__navigation button[aria-pressed]').attributes('aria-label')).toContain('Pause');
    expect(automatic.get('.igf-heroes-carousel > .sr-only[role="status"]').attributes('aria-live')).toBe('off');
    vi.advanceTimersByTime(5100);
    await automatic.vm.$nextTick();
    expect(automatic.findAll('.igf-heroes-carousel__person')[1].attributes('aria-pressed')).toBe('true');
    expect(automatic.get('.igf-heroes-carousel > .sr-only[role="status"]').attributes('aria-live')).toBe('off');
    await automatic.findAll('.igf-heroes-carousel__person')[2].trigger('click');
    expect(automatic.get('.igf-heroes-carousel > .sr-only[role="status"]').attributes('aria-live')).toBe('polite');
    automatic.unmount();

    const manual = mountPage({
      scope: 'national',
      presentation: { title: 'Meet the Heroes' },
      data: { members, settings: { autoplay: false } },
    });
    expect(manual.find('.igf-heroes-carousel__navigation button[aria-pressed]').exists()).toBe(false);
    vi.advanceTimersByTime(5100);
    await manual.vm.$nextTick();
    expect(manual.findAll('.igf-heroes-carousel__person')[0].attributes('aria-pressed')).toBe('true');
    manual.unmount();
    vi.useRealTimers();
  });

  test('changes the autoplay profile without focusing, revealing, or scrolling the page', async () => {
    vi.useFakeTimers();
    const originalScrollIntoView = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'scrollIntoView');
    const originalScrollY = Object.getOwnPropertyDescriptor(window, 'scrollY');
    const scrollIntoView = vi.fn();
    Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', { configurable: true, value: scrollIntoView });
    Object.defineProperty(window, 'scrollY', { configurable: true, value: 640 });
    const focus = vi.spyOn(HTMLElement.prototype, 'focus');
    const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
    const automatic = mountPage({ scope: 'national', presentation: { title: 'Meet the Heroes' }, data: { members } });

    scrollIntoView.mockClear();
    focus.mockClear();
    scrollTo.mockClear();
    const scrollPosition = window.scrollY;

    vi.advanceTimersByTime(5100);
    await automatic.vm.$nextTick();

    expect(automatic.findAll('.igf-heroes-carousel__person')[1].attributes('aria-pressed')).toBe('true');
    expect(scrollIntoView).not.toHaveBeenCalled();
    expect(focus).not.toHaveBeenCalled();
    expect(scrollTo).not.toHaveBeenCalled();
    expect(window.scrollY).toBe(scrollPosition);

    automatic.unmount();
    focus.mockRestore();
    scrollTo.mockRestore();
    if (originalScrollIntoView) Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', originalScrollIntoView);
    else delete HTMLElement.prototype.scrollIntoView;
    if (originalScrollY) Object.defineProperty(window, 'scrollY', originalScrollY);
  });

  test('renders only the selected division ADM2 geometry, narrative, and compact activity cards', () => {
    const wrapper = mountPage({
      scope: 'division',
      presentation: {
        eyebrow: 'Regional heroes',
        title: 'Meet the Heroes from Dhaka Division',
        introduction: '',
        map_heading: 'Explore districts in Dhaka',
        map_help: 'Select a district to open its page.',
        about_heading: 'About Dhaka',
        about_body: 'This managed narrative wins over the location fallback.',
        activities_heading: 'Activities at Dhaka',
        activities_empty: 'No Dhaka activities yet.',
      },
      data: {
        members,
        current_division: { slug: 'dhaka', name: 'Dhaka', description: 'Fallback division description.' },
        districts: [{ slug: 'gazipur', label: 'Gazipur', url: '/meet-the-heroes/district/gazipur' }],
        activities: [{
          id: 91,
          title: 'Neighbourhood learning circle',
          excerpt: 'This should not appear in the compact reference card.',
          published_at: '2026-08-20',
          image_url: '/storage/activity.jpg',
          image_alt: 'Learners sitting together',
          url: 'https://www.igf.test/event/learning-circle?lang=bn',
        }],
      },
    });

    const districtLinks = wrapper.findAll('.igf-heroes-map.is-district a.igf-heroes-map__link');
    expect(districtLinks).toHaveLength(1);
    expect(districtLinks.every(link => link.attributes('href').startsWith('/meet-the-heroes/district/'))).toBe(true);
    expect(wrapper.findAll('.igf-heroes-map.is-district .igf-heroes-map__link.is-unavailable')).toHaveLength(12);
    expect(wrapper.findAll('.igf-heroes-map.is-district .igf-heroes-map__list a')).toHaveLength(1);
    expect(wrapper.get('.igf-heroes-map.is-district svg').attributes('viewBox')).toBe(districtMap.divisionViewBoxes.dhaka);
    expect(wrapper.find('[data-region-key="sylhet"]').exists()).toBe(false);
    expect(wrapper.get('#heroes-page-title strong').text()).toBe('Dhaka');
    expect(wrapper.get('#heroes-about-title').text()).toBe('About Dhaka');
    expect(wrapper.get('#heroes-about-title strong').text()).toBe('Dhaka');
    expect(wrapper.get('.igf-heroes-page__narrative').text()).toContain('managed narrative wins');

    const activity = wrapper.get('.igf-heroes-activities__card');
    expect(wrapper.find('.igf-heroes-page__header > p:last-child').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('Meet the people creating change across Dhaka.');
    expect(activity.attributes('href')).toBe('/event/learning-circle?lang=bn');
    expect(activity.get('time').text()).toBe('Aug 20th 2026');
    expect(activity.get('time').attributes('datetime')).toBe('2026-08-20');
    expect(activity.text()).toContain('Neighbourhood learning circle');
    expect(activity.text()).not.toContain('This should not appear');
    expect(activity.find('.igf-heroes-activities__link').exists()).toBe(false);
    wrapper.unmount();
  });

  test('matches the district reference with a managed group image and no member carousel', () => {
    const wrapper = mountPage({
      scope: 'district',
      presentation: {
        eyebrow: 'Team',
        title: 'Meet the Heroes from Gazipur District',
        introduction: 'Discover community-led work in Gazipur.',
        about_heading: 'About Gazipur',
        activities_heading: 'Activities at Gazipur',
        activities_empty: 'No activities yet.',
      },
      data: {
        members,
        current_division: { slug: 'dhaka', name: 'Dhaka' },
        current_district: {
          slug: 'gazipur',
          name: 'Gazipur',
          description: 'Gazipur volunteers lead practical change with their communities.',
          hero_image: '/storage/districts/gazipur-team.jpg',
          hero_image_alt: 'Gazipur community heroes together',
        },
        activities: [],
      },
    });

    expect(wrapper.get('#heroes-page-title').text()).toBe('Meet the Heroes from Gazipur District');
    expect(wrapper.get('#heroes-page-title strong').text()).toBe('Gazipur');
    expect(wrapper.get('.igf-heroes-page__eyebrow').text()).toContain('Team');
    expect(wrapper.find('.igf-heroes-carousel').exists()).toBe(false);
    expect(wrapper.get('#heroes-about-title').text()).toBe('About Gazipur');
    expect(wrapper.get('#heroes-about-title strong').text()).toBe('Gazipur');
    expect(wrapper.get('.igf-heroes-page__about--district .igf-heroes-page__narrative').text()).toContain('Gazipur volunteers');
    expect(wrapper.get('.igf-heroes-page__group-figure img').attributes()).toMatchObject({
      src: '/storage/districts/gazipur-team.jpg',
      alt: 'Gazipur community heroes together',
      width: '1280',
      height: '720',
    });
    expect(wrapper.find('.igf-heroes-page__back-link').exists()).toBe(false);
    expect(wrapper.get('.igf-heroes-activities__empty').text()).toBe('No activities yet.');
    wrapper.unmount();
  });

  test('uses an accessible group-image fallback and rejects executable media URLs', () => {
    const wrapper = mountPage({
      scope: 'district',
      presentation: { title: 'Meet the Heroes from Bogura District' },
      data: {
        current_division: { slug: 'rajshahi', name: 'Rajshahi' },
        current_district: { slug: 'bogura', name: 'Bogura', hero_image: 'javascript:alert(1)' },
        activities: [],
      },
    });

    expect(wrapper.find('.igf-heroes-page__group-figure img').exists()).toBe(false);
    expect(wrapper.get('.igf-heroes-page__group-fallback').attributes('role')).toBe('img');
    expect(wrapper.get('.igf-heroes-page__group-fallback').text()).toContain('Bogura');
    wrapper.unmount();
  });

  test('keeps localized presentation copy, labels, and language query links in Bangla', () => {
    const wrapper = mountPage({
      locale: 'bn',
      scope: 'national',
      presentation: {
        eyebrow: 'আমাদের মানুষ',
        title: 'হিরোদের সঙ্গে পরিচিত হোন',
        map_heading: 'বিভাগ অনুযায়ী দেখুন',
        map_help: 'একটি বিভাগ নির্বাচন করুন।',
      },
      data: {
        members: [],
        divisions: [{
          slug: 'dhaka',
          label: 'ঢাকা',
          url: 'https://www.igf.test/meet-the-heroes/division/dhaka?lang=bn',
        }],
      },
    });

    expect(wrapper.get('#heroes-page-title').text()).toBe('হিরোদের সঙ্গে পরিচিত হোন');
    expect(wrapper.get('[data-region-key="dhaka"]').attributes('aria-label')).toContain('ঢাকা');
    expect(wrapper.get('[data-region-key="dhaka"]').attributes('href')).toBe('/meet-the-heroes/division/dhaka?lang=bn');
    expect(wrapper.get('.igf-heroes-carousel__empty').text()).toContain('প্রকাশিত নায়ক');
    wrapper.unmount();
  });

  test('ships attributed real ADM2 geometry and responsive, reduced-motion presentation rules', () => {
    expect(districtMap.metadata.source).toBe('geoBoundaries gbOpen');
    expect(districtMap.metadata.boundaryId).toBe('BGD-ADM2-16705992');
    expect(districtMap.metadata.license).toContain('CC BY 3.0 IGO');
    expect(districtMap.districts).toHaveLength(64);
    expect(new Set(districtMap.districts.map(district => district.division)).size).toBe(8);
    expect(districtMap.districts.every(district => district.path.length > 20)).toBe(true);
    expect(heroesPageSource).toContain('grid-template-columns:repeat(4,minmax(0,1fr))');
    expect(heroesPageSource).toContain('@media(max-width:980px)');
    expect(heroesPageSource).toContain('grid-template-columns:repeat(2,minmax(0,1fr))');
    expect(heroesPageSource).toContain('@media(max-width:600px)');
    expect(heroesPageSource).toContain('grid-template-columns:1fr');
    expect(heroesPageSource).toContain('@media(prefers-reduced-motion:reduce)');
    expect(heroesPageSource).toContain('font-size:clamp(40px,4vw,48px);line-height:1');
    expect(heroesPageSource).toContain(".igf-heroes-page{font-family:'Jost'");
    expect(heroesPageSource).toContain("font-family:'Jost',Arial,sans-serif;font-weight:400");
    expect(heroesPageSource).toContain('font-weight:700');
    expect(heroesPageSource).toContain('flex:0 0 46px');
    expect(heroesPageSource).toContain('width:min(73%,896px)');
    expect(heroesPageSource).toContain('.igf-heroes-page__group-figure img{height:auto}');
    expect(heroesPageSource).toContain('aspect-ratio:16 / 9');
    expect(heroesPageSource).toContain('.igf-heroes-page__district-hero{padding:80px 0 40px}');
    expect(heroesPageSource).toContain('.igf-heroes-page__district-heading{margin-bottom:20px}');
    expect(heroesPageSource).toContain('width:min(calc(100% - 32px),1360px)');
    expect(heroesPageSource).toContain('.igf-heroes-page__showcase{padding:80px 0 40px}');
    expect(heroesPageSource).toContain('.igf-heroes-page__split{gap:16px}');
    expect(heroesPageSource).toContain('font-size:20px;font-weight:400;line-height:28px');
    expect(heroesPageSource).toContain('width:min(100%,600px);height:600px;aspect-ratio:1');
    expect(heroesPageSource).toContain('.igf-heroes-page__showcase--division{padding-bottom:0}');
    expect(heroesPageSource).toContain('.igf-heroes-page__header h1 .is-before-place{display:block}');
    expect(heroesPageSource).toContain('.igf-heroes-page__about{padding:40px 0 72px}');
    expect(heroesPageSource).toContain('align-items:center;gap:24px');
    expect(heroesPageSource).toContain('aspect-ratio:3 / 2');
    expect(heroesPageSource).toContain("font:700 24px/32px 'Hanken Grotesk'");
    expect(heroesPageSource).toContain('.igf-heroes-activities>.igf-heroes-page__shell');
    expect(carouselSource).toContain('.igf-heroes-carousel__navigation:focus-within');
    expect(carouselSource).toContain('scrollbar-width:none');
    expect(carouselSource).toContain('.igf-heroes-carousel__rail::-webkit-scrollbar{display:none}');
    expect(carouselSource).toContain('.igf-heroes-carousel:hover .igf-heroes-carousel__autoplay');
    expect(carouselSource).toContain('opacity:0');
    expect(carouselSource).toContain('min-width:196px');
    expect(carouselSource).toContain('min-height:266px;padding:24px 0');
    expect(carouselSource).toContain('.igf-heroes-carousel__rail{padding:40px 0}');
    expect(carouselSource).toContain('.igf-heroes-carousel__profile{margin:0}');
    expect(carouselSource).toContain('@media(hover:none),(pointer:coarse)');
    expect(carouselSource).toContain("announceChanges ? 'polite' : 'off'");
    expect(carouselSource).toContain("font:400 16px/24px 'Jost'");
    expect(carouselSource).toContain("font:400 14px/20px 'Jost'");
    expect(carouselSource).toContain("font:700 24px/32px 'Jost'");
    expect(carouselSource).toContain('min-height:48px;align-items:center');
    expect(carouselSource).toContain('height:266px;min-height:266px');
    expect(carouselSource).toContain('.igf-heroes-carousel__person strong,.igf-heroes-carousel__person small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}');
    expect(carouselSource).toContain('@media(max-width:600px){.igf-heroes-carousel__person{height:auto;min-height:0}');
    expect(carouselSource).toContain('padding-top:24px');
    expect(carouselSource).toContain('object-position:50% 50%');
    expect(carouselSource).toContain('border:0;box-shadow:none');
    expect(carouselSource).toContain('.igf-heroes-carousel__profile{padding-top:0}');
    expect(carouselSource).toContain('display:none!important;content:none!important');
    expect(regionMapSource).toContain('.igf-heroes-map__attribution{display:flex;justify-content:flex-end');
    expect(regionMapSource).toContain('@media(max-width:600px)');
    expect(regionMapSource).toContain('.igf-heroes-map__attribution{justify-content:flex-start}');
    expect(heroesPageSource).toContain("max-width:none;font:400 16px/24px 'Jost'");
    expect(carouselSource).toContain('0 10px 15px -3px rgba(74,45,24,.1),0 4px 6px -4px rgba(74,45,24,.1)');
  });

  test('keeps map attribution compact while exposing the complete source and licence', () => {
    const wrapper = mount(HeroesRegionMap, {
      props: {
        level: 'division',
        locale: 'en',
        regions: [{ slug: 'dhaka', name: 'Dhaka' }],
        heading: 'Explore heroes by division',
        attributionPrefix: 'Boundary data:',
        minimal: true,
      },
    });

    const attribution = wrapper.get('.igf-heroes-map__attribution');
    const summary = attribution.get('summary');
    expect(summary.text()).toBe('ⓘ');
    expect(summary.get('span').attributes('aria-hidden')).toBe('true');
    expect(summary.attributes('aria-label')).toContain('geoBoundaries gbOpen');
    expect(summary.attributes('aria-label')).toContain('CC BY 4.0');
    expect(attribution.findAll('a')).toHaveLength(2);
    expect(attribution.findAll('a')[0].attributes('href')).toContain('/geoBoundaries/');
    expect(attribution.findAll('a')[1].attributes('href')).toBe('https://creativecommons.org/licenses/by/4.0/');
    expect(attribution.get('.igf-heroes-map__attribution-details').text()).toContain('Boundary data: geoBoundaries gbOpen · CC BY 4.0.');

    wrapper.unmount();
  });

  test('maps every bundled ADM2 shape to the canonical managed district slug and URL', () => {
    const slug = value => String(value).toLowerCase().replace(/['’]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    const suppliedByDivision = Object.fromEntries(administrativeDivisions.divisions.map(division => [
      slug(division.name),
      division.districts.map(district => ({
        slug: slug(district.name),
        name: district.name,
        url: `/meet-the-heroes/district/${slug(district.name)}`,
      })),
    ]));
    const suppliedKeys = Object.values(suppliedByDivision).flat().map(district => district.slug).sort();

    expect(districtMap.districts.map(district => district.key).sort()).toEqual(suppliedKeys);
    for (const [division, districts] of Object.entries(suppliedByDivision)) {
      const wrapper = mount(HeroesRegionMap, {
        props: {
          level: 'district',
          parentSlug: division,
          regions: districts,
          heading: `Districts in ${division}`,
        },
      });
      const links = wrapper.findAll('.igf-heroes-map__link');
      expect(links).toHaveLength(districts.length);
      expect(links.map(link => link.attributes('href')).sort()).toEqual(districts.map(district => district.url).sort());
      wrapper.unmount();
    }
  });
});
