import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import PageBlocks from '@/Shared/PageBlocks.vue';
import pageBlocksSource from '@/Shared/PageBlocks.vue?raw';
import bangladeshDivisionMap from '../../../data/bangladesh-divisions.json';

const sharedTeamSettings = {
  team_biography_label: 'Biography',
  team_external_link_suffix: 'opens in a new tab',
  team_qualification_label: 'Qualification',
  team_social_links_label: 'Social links',
  team_member_label: 'Team member',
  team_item_link_label: 'View profile',
  team_profile_accessible_label: 'View {name} profile',
  team_linkedin_label: 'Connect',
  team_website_label: 'Website',
};

const members = [
  {
    id: 11,
    heading: 'Amina Rahman',
    designation: 'Chairperson',
    biography: 'Amina guides the foundation strategy and governance.',
    qualification: 'MSc in Development Studies',
    image: '/image/amina.jpg',
    image_alt: 'Portrait of Amina Rahman',
    division_id: 6,
    division_slug: 'dhaka',
    division_name: 'Dhaka',
    url: '/people/amina-rahman',
    social_links: [{ platform: 'linkedin', url: 'https://www.linkedin.com/in/amina' }],
  },
  {
    id: 12,
    heading: 'Bashir Hossain',
    designation: 'Regional lead',
    biography: 'Bashir supports partnerships in northern communities.',
    image: '',
    division_id: 1,
    division_name: 'Rangpur Division',
    url: '/people/bashir-hossain',
    social_links: [],
  },
  {
    id: 13,
    heading: 'Chaya Das',
    designation: 'Programme lead',
    biography: 'Chaya coordinates community programmes.',
    image: '',
    division_slug: 'chittagong',
    division_name: 'Chittagong',
    url: '/people/chaya-das',
    social_links: [],
  },
  {
    id: 14,
    heading: 'Dina Akter',
    designation: 'Volunteer coordinator',
    biography: 'Dina supports volunteers across Bangladesh.',
    image: '',
    division_id: null,
    division_slug: '',
    division_name: '',
    url: '',
    social_links: [],
  },
];

function teamBlock(content = {}) {
  return {
    uuid: 'directory-team',
    type: 'team',
    is_enabled: true,
    show_on_desktop: true,
    show_on_mobile: true,
    content: {
      eyebrow: 'Our people',
      heading: 'Meet the team',
      intro: 'Explore our people by division.',
      team_presentation: 'directory_map',
      show_map: true,
      map_position: 'right',
      profile_behavior: 'panel',
      animation_enabled: true,
      items: members,
      ...content,
    },
  };
}

function setPageSettings(locale = 'en') {
  usePage().props = {
    locale,
    siteSettings: {
      shared_blocks: sharedTeamSettings,
      regional: {},
      donation_page: {},
      content_archives: {},
    },
  };
}

describe('PageBlocks interactive team directory and Bangladesh map', () => {
  beforeEach(() => {
    window.matchMedia = vi.fn().mockReturnValue({
      matches: false,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    });
    setPageSettings();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
  });

  test('renders an eight-division map, equivalent text filters, and one selected inline profile', () => {
    const wrapper = mount(PageBlocks, { props: { blocks: [teamBlock()] } });
    const directory = wrapper.get('.igf-team-directory');
    const mapDivisions = directory.findAll('.igf-team-directory__map-division');
    const filters = directory.findAll('.igf-team-directory__division-controls button');
    const people = directory.findAll('.igf-team-directory__person');

    expect(directory.classes()).toContain('is-map-right');
    expect(directory.classes()).toContain('is-profile-panel');
    expect(directory.classes()).toContain('is-motion-enabled');
    expect(wrapper.get('.igf-section-lead').text()).toBe('Explore our people by division.');
    expect(mapDivisions).toHaveLength(8);
    expect(directory.get('.igf-team-directory__map-svg').attributes('aria-hidden')).toBe('true');
    expect(mapDivisions.every(division => division.attributes('role') === undefined)).toBe(true);
    expect(mapDivisions.every(division => division.attributes('tabindex') === undefined)).toBe(true);
    expect(mapDivisions.every(division => division.get('path').attributes('d').length > 100)).toBe(true);
    expect(directory.find('.igf-team-directory__map-svg text').exists()).toBe(false);
    expect(filters).toHaveLength(9);
    expect(filters[0].text()).toContain('All divisions');
    expect(filters.every(filter => filter.attributes('aria-controls') === directory.get('.igf-team-directory__people').attributes('id'))).toBe(true);
    expect(people).toHaveLength(4);
    expect(people[0].attributes('aria-pressed')).toBe('true');
    expect(people[0].attributes('aria-controls')).toBe(wrapper.get('.igf-team-directory__detail').attributes('id'));
    expect(wrapper.findAll('.igf-team-directory__detail')).toHaveLength(1);
    expect(wrapper.get('.igf-team-directory__detail').text()).toContain('Amina guides the foundation strategy');
    expect(wrapper.find('.igf-team-card').exists()).toBe(false);

    wrapper.unmount();
  });

  test('keeps pointer map and text controls synchronized while showing full-name feedback', async () => {
    const wrapper = mount(PageBlocks, { props: { blocks: [teamBlock()] } });
    const mapDivisions = wrapper.findAll('.igf-team-directory__map-division');
    const filters = wrapper.findAll('.igf-team-directory__division-controls button');

    await mapDivisions[0].trigger('mouseenter');
    expect(wrapper.get('.igf-team-directory__map-feedback').text()).toContain('Rangpur');
    await mapDivisions[0].trigger('click');
    expect(mapDivisions[0].classes()).toContain('is-active');
    expect(mapDivisions[0].get('title').text()).toBe('Rangpur, 1 person');
    expect(wrapper.get('.igf-team-directory__map-feedback').text()).toContain('1 person in this division');
    expect(filters[1].attributes('aria-pressed')).toBe('true');
    expect(filters[1].find('.fa-check').exists()).toBe(true);
    expect(wrapper.findAll('.igf-team-directory__person')).toHaveLength(1);
    expect(wrapper.get('.igf-team-directory__person').text()).toContain('Bashir Hossain');
    expect(wrapper.get('.igf-team-directory__detail').text()).toContain('northern communities');
    expect(wrapper.get('[role="status"]').text()).toContain('Showing 1 person in Rangpur.');

    await filters[4].trigger('focus');
    expect(wrapper.get('.igf-team-directory__map-feedback').text()).toContain('Sylhet');
    await filters[4].trigger('click');
    expect(wrapper.find('.igf-team-directory__people-list').exists()).toBe(false);
    expect(wrapper.get('.igf-team-directory__empty').text()).toContain('No published people');
    expect(wrapper.find('.igf-team-directory__detail').exists()).toBe(false);
    expect(mapDivisions[3].classes()).toContain('is-active');

    await filters[0].trigger('click');
    expect(wrapper.findAll('.igf-team-directory__person')).toHaveLength(4);
    expect(filters[0].attributes('aria-pressed')).toBe('true');

    wrapper.unmount();
  });

  test('supports arrow-key person selection and restores focus to the selected control', async () => {
    const wrapper = mount(PageBlocks, { attachTo: document.body, props: { blocks: [teamBlock()] } });
    let people = wrapper.findAll('.igf-team-directory__person');
    people[0].element.focus();

    await people[0].trigger('keydown', { key: 'ArrowDown' });
    await wrapper.vm.$nextTick();
    people = wrapper.findAll('.igf-team-directory__person');

    expect(people[1].attributes('aria-pressed')).toBe('true');
    expect(document.activeElement).toBe(people[1].element);
    expect(wrapper.get('.igf-team-directory__detail').text()).toContain('Bashir supports partnerships');

    wrapper.unmount();
  });

  test('provides an accessible modal profile with Escape close and focus restoration', async () => {
    const wrapper = mount(PageBlocks, {
      attachTo: document.body,
      props: { blocks: [teamBlock({ profile_behavior: 'modal' })] },
    });
    const trigger = wrapper.findAll('.igf-team-directory__person')[0];

    expect(wrapper.find('.igf-team-directory__detail').exists()).toBe(false);
    expect(trigger.attributes('aria-haspopup')).toBe('dialog');
    expect(trigger.attributes('aria-expanded')).toBe('false');

    trigger.element.focus();
    await trigger.trigger('click');
    await wrapper.vm.$nextTick();

    const dialog = document.querySelector('[role="dialog"]');
    const close = dialog.querySelector('.igf-team-directory-dialog__close');
    expect(dialog).not.toBeNull();
    expect(dialog.getAttribute('aria-modal')).toBe('true');
    expect(dialog.textContent).toContain('Amina guides the foundation strategy');
    expect(document.activeElement).toBe(close);
    expect(trigger.attributes('aria-expanded')).toBe('true');

    dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
    await wrapper.vm.$nextTick();
    expect(document.querySelector('[role="dialog"]')).toBeNull();
    expect(document.activeElement).toBe(trigger.element);

    wrapper.unmount();
  });

  test('uses profile links when available and safely falls back to the inline panel without a URL', async () => {
    const wrapper = mount(PageBlocks, { props: { blocks: [teamBlock({ profile_behavior: 'link' })] } });
    const controls = wrapper.findAll('.igf-team-directory__person');

    expect(controls[0].element.tagName).toBe('A');
    expect(controls[0].attributes('href')).toBe('/people/amina-rahman');
    expect(controls[0].attributes('aria-pressed')).toBeUndefined();
    expect(controls[3].element.tagName).toBe('BUTTON');
    expect(controls[3].attributes('aria-controls')).toBeTruthy();
    expect(wrapper.find('.igf-team-directory__detail').exists()).toBe(false);

    await controls[3].trigger('click');
    expect(controls[3].attributes('aria-pressed')).toBe('true');
    expect(wrapper.get('.igf-team-directory__detail').text()).toContain('Dina supports volunteers');

    wrapper.unmount();
  });

  test('can hide the visual map without removing the complete text-based division control', () => {
    const wrapper = mount(PageBlocks, { props: { blocks: [teamBlock({ show_map: false, animation_enabled: false })] } });

    expect(wrapper.find('.igf-team-directory__map-svg').exists()).toBe(false);
    expect(wrapper.findAll('.igf-team-directory__division-controls button')).toHaveLength(9);
    expect(wrapper.get('.igf-team-directory').classes()).not.toContain('has-visual-map');
    expect(wrapper.get('.igf-team-directory').classes()).not.toContain('is-motion-enabled');

    wrapper.unmount();
  });

  test('keeps subtle directory motion on by default while allowing an explicit opt-out', () => {
    const defaultBlock = teamBlock();
    delete defaultBlock.content.animation_enabled;
    const defaultWrapper = mount(PageBlocks, { props: { blocks: [defaultBlock] } });
    const disabledWrapper = mount(PageBlocks, {
      props: { blocks: [teamBlock({ animation_enabled: 'false' })] },
    });

    expect(defaultWrapper.get('.igf-team-directory').classes()).toContain('is-motion-enabled');
    expect(disabledWrapper.get('.igf-team-directory').classes()).not.toContain('is-motion-enabled');

    defaultWrapper.unmount();
    disabledWrapper.unmount();
  });

  test('supports the legacy presentation alias and localizes all division controls in Bangla', () => {
    setPageSettings('bn');
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [teamBlock({
          team_presentation: undefined,
          presentation: 'map_directory',
        })],
      },
    });
    const filters = wrapper.findAll('.igf-team-directory__division-controls button');

    expect(filters[0].text()).toContain('সব বিভাগ');
    expect(filters[1].text()).toContain('রংপুর');
    expect(filters[8].text()).toContain('চট্টগ্রাম');
    expect(wrapper.get('.igf-team-directory__map-heading').text()).toContain('বাংলাদেশের বিভাগসমূহ');
    expect(wrapper.findAll('.igf-team-directory__map-division title').map(title => title.text())).toEqual(expect.arrayContaining([
      expect.stringContaining('রংপুর'),
      expect.stringContaining('রাজশাহী'),
      expect.stringContaining('ময়মনসিংহ'),
      expect.stringContaining('সিলেট'),
      expect.stringContaining('খুলনা'),
      expect.stringContaining('ঢাকা'),
      expect.stringContaining('বরিশাল'),
      expect.stringContaining('চট্টগ্রাম'),
    ]));

    wrapper.unmount();
  });

  test('renders the preserved heroes showcase with a portrait rail, synchronized profile, and semantic map controls', async () => {
    const wrapper = mount(PageBlocks, {
      props: { blocks: [teamBlock({ team_presentation: 'heroes_showcase' })] },
    });
    const showcase = wrapper.get('.igf-team-showcase');
    const people = showcase.findAll('.igf-team-showcase__person');
    const mapControls = showcase.findAll('.igf-team-showcase__map-control');

    expect(showcase.classes()).toContain('is-map-right');
    expect(showcase.classes()).toContain('has-visual-map');
    expect(wrapper.findAll('h2').map(heading => heading.text())).toEqual(['Meet the team']);
    expect(showcase.findAll('.igf-team-showcase__eyebrow-marks span')).toHaveLength(2);
    expect(showcase.get('.igf-team-showcase__heading h2 strong').text()).toBe('team');
    expect(people).toHaveLength(4);
    expect(people[0].attributes('aria-pressed')).toBe('true');
    expect(people[0].get('img').attributes('width')).toBe('150');
    expect(showcase.get('.igf-team-showcase__profile').text()).toContain('Amina guides the foundation strategy');
    expect(showcase.findAll('.igf-team-showcase__map-division')).toHaveLength(8);
    expect(showcase.get('.igf-team-showcase__map-svg').attributes('aria-hidden')).toBe('true');
    expect(mapControls).toHaveLength(8);
    expect(mapControls.every(control => control.element.tagName === 'BUTTON')).toBe(true);
    expect(mapControls[0].attributes('aria-label')).toContain('Rangpur');
    expect(wrapper.find('.igf-team-directory').exists()).toBe(false);
    expect(wrapper.find('.igf-team-card').exists()).toBe(false);
    expect(wrapper.find('.igf-team-tabs').exists()).toBe(false);

    await mapControls[0].trigger('focus');
    expect(showcase.get('.igf-team-showcase__map-tooltip').text()).toBe('Rangpur');
    await mapControls[0].trigger('click');
    expect(mapControls[0].attributes('aria-pressed')).toBe('true');
    expect(showcase.findAll('.igf-team-showcase__person')).toHaveLength(1);
    expect(showcase.get('.igf-team-showcase__person').text()).toContain('Bashir Hossain');
    expect(showcase.get('.igf-team-showcase__profile').text()).toContain('Bashir supports partnerships');

    await showcase.get('.igf-team-showcase__map-heading > button').trigger('click');
    expect(showcase.findAll('.igf-team-showcase__person')).toHaveLength(4);

    wrapper.unmount();
  });

  test('uses native division page links when configured and keeps in-place filtering as the fallback', async () => {
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [teamBlock({
          team_presentation: 'heroes_showcase',
          division_links: {
            dhaka: 'https://www.igf.test/meet-the-heroes/division/dhaka?lang=bn',
            chattogram: 'javascript:alert(1)',
          },
        })],
      },
    });
    const controls = wrapper.findAll('.igf-team-showcase__map-control');
    const dhaka = controls.find(control => control.attributes('aria-label').includes('Dhaka'));
    const chattogram = controls.find(control => control.attributes('aria-label').includes('Chattogram'));

    expect(dhaka.element.tagName).toBe('A');
    expect(dhaka.attributes('href')).toBe('/meet-the-heroes/division/dhaka?lang=bn');
    expect(dhaka.attributes('aria-pressed')).toBeUndefined();
    expect(chattogram.element.tagName).toBe('BUTTON');
    expect(chattogram.attributes('href')).toBeUndefined();

    await chattogram.trigger('click');
    expect(wrapper.findAll('.igf-team-showcase__person')).toHaveLength(1);
    expect(wrapper.get('.igf-team-showcase__person').text()).toContain('Chaya Das');
    wrapper.unmount();
  });

  test('operates the heroes showcase rail with arrow keys, arrows, dots, and focus restoration', async () => {
    const wrapper = mount(PageBlocks, {
      attachTo: document.body,
      props: { blocks: [teamBlock({ team_presentation: 'heroes_showcase' })] },
    });
    let people = wrapper.findAll('.igf-team-showcase__person');
    people[0].element.focus();

    await people[0].trigger('keydown', { key: 'ArrowRight' });
    await wrapper.vm.$nextTick();
    people = wrapper.findAll('.igf-team-showcase__person');
    expect(people[1].attributes('aria-pressed')).toBe('true');
    expect(document.activeElement).toBe(people[1].element);
    expect(wrapper.get('.igf-team-showcase__profile').text()).toContain('Bashir supports partnerships');

    const arrows = wrapper.findAll('.igf-team-showcase__arrow');
    await arrows[1].trigger('click');
    expect(wrapper.findAll('.igf-team-showcase__person')[2].attributes('aria-pressed')).toBe('true');
    expect(wrapper.get('.igf-team-showcase__profile').text()).toContain('Chaya coordinates community programmes');

    const dots = wrapper.findAll('.igf-team-showcase__dots button');
    expect(dots).toHaveLength(4);
    await dots[3].trigger('click');
    expect(dots[3].attributes('aria-current')).toBe('true');
    expect(wrapper.get('.igf-team-showcase__profile').text()).toContain('Dina supports volunteers');

    wrapper.unmount();
  });

  test('keeps showcase autoplay opt-in, supports pause, and disables it for reduced motion', async () => {
    vi.useFakeTimers();
    const defaultWrapper = mount(PageBlocks, {
      props: { blocks: [teamBlock({ team_presentation: 'heroes_showcase' })] },
    });
    expect(defaultWrapper.get('.igf-team-showcase__autoplay').attributes('aria-label')).toContain('Play');
    vi.advanceTimersByTime(6000);
    await defaultWrapper.vm.$nextTick();
    expect(defaultWrapper.findAll('.igf-team-showcase__person')[0].attributes('aria-pressed')).toBe('true');
    defaultWrapper.unmount();

    const autoplayWrapper = mount(PageBlocks, {
      props: { blocks: [teamBlock({ team_presentation: 'heroes_showcase', autoplay: true })] },
    });
    expect(autoplayWrapper.get('.igf-team-showcase__autoplay').attributes('aria-label')).toContain('Pause');
    expect(autoplayWrapper.get('.igf-team-showcase__content > .sr-only[role="status"]').attributes('aria-live')).toBe('off');
    vi.advanceTimersByTime(5600);
    await autoplayWrapper.vm.$nextTick();
    expect(autoplayWrapper.findAll('.igf-team-showcase__person')[1].attributes('aria-pressed')).toBe('true');
    expect(autoplayWrapper.get('.igf-team-showcase__content > .sr-only[role="status"]').attributes('aria-live')).toBe('off');

    await autoplayWrapper.findAll('.igf-team-showcase__person')[2].trigger('click');
    expect(autoplayWrapper.get('.igf-team-showcase__content > .sr-only[role="status"]').attributes('aria-live')).toBe('polite');

    await autoplayWrapper.get('.igf-team-showcase__autoplay').trigger('click');
    expect(autoplayWrapper.get('.igf-team-showcase__autoplay').attributes('aria-label')).toContain('Play');
    vi.advanceTimersByTime(6000);
    await autoplayWrapper.vm.$nextTick();
    expect(autoplayWrapper.findAll('.igf-team-showcase__person')[2].attributes('aria-pressed')).toBe('true');
    autoplayWrapper.unmount();

    window.matchMedia = vi.fn().mockImplementation(query => ({
      matches: query.includes('prefers-reduced-motion'),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    }));
    const reducedWrapper = mount(PageBlocks, {
      props: { blocks: [teamBlock({ team_presentation: 'heroes_showcase', autoplay_enabled: true })] },
    });
    expect(reducedWrapper.find('.igf-team-showcase__autoplay').exists()).toBe(false);
    vi.advanceTimersByTime(6000);
    await reducedWrapper.vm.$nextTick();
    expect(reducedWrapper.findAll('.igf-team-showcase__person')[0].attributes('aria-pressed')).toBe('true');
    reducedWrapper.unmount();
  });

  test('can hide the showcase map without removing the portrait rail and selected profile', () => {
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [teamBlock({
          team_presentation: 'heroes_showcase',
          show_map: false,
          map_position: 'left',
        })],
      },
    });

    expect(wrapper.get('.igf-team-showcase').classes()).toContain('is-map-left');
    expect(wrapper.get('.igf-team-showcase').classes()).not.toContain('has-visual-map');
    expect(wrapper.find('.igf-team-showcase__map').exists()).toBe(false);
    expect(wrapper.findAll('.igf-team-showcase__person')).toHaveLength(4);
    expect(wrapper.get('.igf-team-showcase__profile').text()).toContain('Amina Rahman');

    wrapper.unmount();
  });

  test.each(['cards', 'list', 'compact'])('preserves the existing %s team rendering path', presentation => {
    const wrapper = mount(PageBlocks, {
      props: { blocks: [teamBlock({ team_presentation: presentation })] },
    });

    expect(wrapper.find('.igf-team-directory').exists()).toBe(false);
    expect(wrapper.findAll('.igf-team-card')).toHaveLength(4);
    wrapper.unmount();
  });

  test('contains explicit desktop, tablet, mobile, and reduced-motion safeguards', () => {
    expect(pageBlocksSource).toContain("grid-template-areas:'map people detail'");
    expect(pageBlocksSource).toContain('.igf-team-directory.is-profile-modal,.igf-team-directory.is-profile-link:not(.has-inline-detail)');
    expect(pageBlocksSource).toContain('@media (max-width:1180px)');
    expect(pageBlocksSource).toContain("grid-template-areas:'map people' 'detail detail'");
    expect(pageBlocksSource).toContain("grid-template-areas:'map' 'people' 'detail'");
    expect(pageBlocksSource).toContain('@media (forced-colors:active)');
    expect(pageBlocksSource).toContain('@media (prefers-reduced-motion:reduce)');
    expect(pageBlocksSource).toContain('button.is-active { border-color:var(--orange); background:var(--orange); color:#231d19; }');
    expect(pageBlocksSource).toContain("import bangladeshDivisionMapData from '../../data/bangladesh-divisions.json'");
    expect(pageBlocksSource).not.toContain('motion_enabled');
    expect(pageBlocksSource).not.toContain('teamDivisionShortLabel');
    expect(pageBlocksSource).not.toContain('teamDirectoryAutoplay');
    expect(pageBlocksSource).toContain("teamHeroesShowcasePresentations = new Set(['heroes_showcase'])");
    expect(pageBlocksSource).toContain('@media (min-width:1024px)');
    expect(pageBlocksSource).toContain('@media (min-width:1280px)');
    expect(pageBlocksSource).toContain("grid-auto-columns:calc((100% - 10px)/2)");
    expect(pageBlocksSource).toContain("grid-auto-columns:calc((100% - 20px)/3)");
    expect(pageBlocksSource).toContain('.igf-page-block--team-heroes-showcase { padding:40px 16px;');
    expect(pageBlocksSource).toContain('width:150px; height:150px');
    expect(pageBlocksSource).toContain('animation:igf-team-showcase-profile-in .3s ease both');
    expect(pageBlocksSource).toContain('from { opacity:0; transform:translateX(14px); }');
    expect(pageBlocksSource).toContain('transition:opacity .2s ease,transform .2s ease');
    expect(pageBlocksSource).toContain('@media (hover:none),(pointer:coarse)');
    expect(pageBlocksSource).toContain(".igf-team-showcase { font-family:'Jost'");
    expect(pageBlocksSource).toContain("font:400 16px/24px 'Jost'");
    expect(pageBlocksSource).toContain("font:400 14px/20px 'Jost'");
    expect(pageBlocksSource).toContain('.igf-team-showcase__map-svg { overflow:hidden; filter:none; }');
    expect(pageBlocksSource).toContain('height:266px; min-height:266px; border:0;');
    expect(pageBlocksSource).toContain('object-position:50% 50%');
  });

  test('uses the pinned, attributed, eight-division geographic dataset', () => {
    const expectedKeys = ['rangpur', 'rajshahi', 'mymensingh', 'sylhet', 'khulna', 'dhaka', 'barishal', 'chattogram'];
    const [, , width, height] = bangladeshDivisionMap.viewBox.split(' ').map(Number);

    expect(bangladeshDivisionMap.metadata.boundaryId).toBe('BGD-ADM1-32408957');
    expect(bangladeshDivisionMap.metadata.sourceCommit).toBe('9469f09592ced973a3448cf66b6100b741b64c0d');
    expect(bangladeshDivisionMap.metadata.license).toBe('CC BY 4.0');
    expect(bangladeshDivisionMap.divisions.map(division => division.key)).toEqual(expectedKeys);
    expect(bangladeshDivisionMap.divisions.every(division => division.path.length > 100)).toBe(true);
    expect(bangladeshDivisionMap.divisions.every(division => division.labelX > 0 && division.labelX < width)).toBe(true);
    expect(bangladeshDivisionMap.divisions.every(division => division.labelY > 0 && division.labelY < height)).toBe(true);
    expect(JSON.stringify(bangladeshDivisionMap).length).toBeLessThan(15000);
  });
});
