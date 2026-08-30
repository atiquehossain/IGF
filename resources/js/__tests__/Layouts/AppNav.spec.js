import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import { compileStyle, parse } from '@vue/compiler-sfc';
import AppNav from '@/layouts/AppNav.vue';
import appNavSource from '@/layouts/AppNav.vue?raw';

describe('AppNav recursive disclosure navigation', () => {
  const mountedWrappers = [];
  const mediaQueries = new Map();

  const managedNavigation = () => [
    { id: 1, uuid: 'home-menu', name: 'Home', href: '/' },
    {
      id: 2,
      uuid: 'our-work-menu',
      name: 'Our Work',
      link: 'custom',
      slug: '#',
      children: [
        { name: 'Program Overview', uuid: 'program-overview-menu', href: '/category/our-causes' },
        {
          name: 'Youth Development',
          description: 'Programs led with young people.',
          uuid: 'youth-development-menu',
          href: '/page/youth-development',
          children: [
            {
              name: 'Workshop',
              description: 'Practical learning sessions.',
              uuid: 'workshop-menu',
              link: 'frontend.workshops.index',
              slug: null,
              children: [
                { name: 'Must not render at depth four', uuid: 'depth-four-menu', href: '/workshops/private' },
              ],
            },
          ],
        },
      ],
    },
    {
      id: 3,
      uuid: 'get-involved-menu',
      name: 'Get Involved',
      link: 'custom',
      slug: '#',
      children: [
        { name: 'Volunteer', uuid: 'volunteer-menu', href: '/volunteer/register' },
        { name: 'Careers', uuid: 'careers-menu', link: 'frontend.category', slug: 'career' },
      ],
    },
  ];

  function installMatchMedia() {
    mediaQueries.clear();
    window.matchMedia = vi.fn((query) => {
      const listeners = new Set();
      const mediaQuery = {
        media: query,
        matches: query.includes('(hover: hover)'),
        addEventListener: (event, listener) => event === 'change' && listeners.add(listener),
        removeEventListener: (event, listener) => event === 'change' && listeners.delete(listener),
        emit(matches) {
          this.matches = matches;
          listeners.forEach(listener => listener({ matches, media: query }));
        },
      };
      mediaQueries.set(query, mediaQuery);
      return mediaQuery;
    });
  }

  function mountNav(options = {}) {
    const wrapper = mount(AppNav, {
      global: { mocks: { route: window.route } },
      ...options,
    });
    mountedWrappers.push(wrapper);
    return wrapper;
  }

  function itemForLink(scope, linkSelector, itemSelector) {
    const element = scope.get(linkSelector).element.closest(itemSelector);
    return scope.findAll(itemSelector).find(item => item.element === element);
  }

  function expectDisclosureIdRefs(toggle, panel, label) {
    expect(toggle.attributes('id')).toBeTruthy();
    expect(panel.attributes('id')).toBe(toggle.attributes('aria-controls'));
    expect(label.attributes('id')).toBeTruthy();
    expect(panel.attributes('aria-labelledby')).toBe(label.attributes('id'));
    expect(document.getElementById(panel.attributes('aria-labelledby'))).toBe(label.element);
  }

  function contrastRatio(foreground, background) {
    const luminance = (hex) => {
      const channels = hex.match(/[\da-f]{2}/gi).map(value => parseInt(value, 16) / 255);
      const linear = channels.map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
      return (0.2126 * linear[0]) + (0.7152 * linear[1]) + (0.0722 * linear[2]);
    };
    const lighter = Math.max(luminance(foreground), luminance(background));
    const darker = Math.min(luminance(foreground), luminance(background));
    return (lighter + 0.05) / (darker + 0.05);
  }

  beforeEach(() => {
    usePage().props = {
      appName: 'Ignite Global Foundation',
      appMenus: managedNavigation(),
      siteSettings: {
        branding: { site_name: 'Ignite Global Foundation' },
        header: {
          donate_label: 'Give today',
          donate_url: '/give-today',
          sponsor_label: 'Fund a learner',
          sponsor_url: '/fund-a-learner',
        },
      },
    };
    window.history.replaceState({}, '', '/workshops/free-leadership');
    window.route = name => name ? '/' : ({ has: () => false });
    installMatchMedia();
  });

  afterEach(() => {
    mountedWrappers.splice(0).forEach(wrapper => wrapper.unmount());
    document.body.innerHTML = '';
  });

  test('renders only the CMS tree through depth three and keeps active ancestors without marking a section as the current page', () => {
    const wrapper = mountNav();
    const desktop = wrapper.get('.desktop-nav');
    const ourWork = itemForLink(desktop, 'a[href="/workshops"]', '.desktop-nav__item');
    const youth = itemForLink(ourWork, 'a[href="/page/youth-development"]', '.desktop-nav__entry');
    const workshop = itemForLink(youth, 'a[href="/workshops"]', '.desktop-nav__entry');

    expect(desktop.findAll('a[href="/workshops"]')).toHaveLength(1);
    expect(desktop.text()).not.toContain('Opportunities');
    expect(desktop.text()).not.toContain('Must not render at depth four');
    expect(desktop.get('a[href="/careers"]').text()).toBe('Careers');
    expect(workshop.attributes('data-nav-depth')).toBe('3');
    expect(workshop.get('a').attributes('aria-current')).toBeUndefined();
    expect(desktop.findAll('[aria-current="page"]')).toHaveLength(0);
    expect(ourWork.classes()).toContain('is-active');
    expect(youth.classes()).toContain('is-active');
    expect(workshop.classes()).toContain('is-active');
  });

  test('uses aria-current page only for an exact local path match', () => {
    window.history.replaceState({}, '', '/workshops');
    const wrapper = mountNav();

    expect(wrapper.get('.desktop-nav a[href="/workshops"]').attributes('aria-current')).toBe('page');
    expect(wrapper.get('.desktop-nav a[href="/page/youth-development"]').attributes('aria-current')).toBeUndefined();
  });

  test('keeps the mobile navigation IDREF target mounted while collapsed', async () => {
    const wrapper = mountNav();
    const menuButton = wrapper.get('.menu-button');
    const mobile = wrapper.get('.mobile-nav');

    expect(menuButton.attributes('aria-controls')).toBe(mobile.attributes('id'));
    expect(mobile.attributes()).toHaveProperty('hidden');
    expect(mobile.isVisible()).toBe(false);

    await menuButton.trigger('click');
    expect(mobile.attributes()).not.toHaveProperty('hidden');

    await menuButton.trigger('click');
    expect(mobile.attributes()).toHaveProperty('hidden');
    expect(menuButton.attributes('aria-expanded')).toBe('false');
  });

  test('uses reciprocal IDREFs and Escape closes the deepest desktop branch first', async () => {
    const wrapper = mountNav({ attachTo: document.body });
    const desktop = wrapper.get('.desktop-nav');
    const ourWork = itemForLink(desktop, 'a[href="/workshops"]', '.desktop-nav__item');
    const topToggle = ourWork.get('.desktop-nav__trigger');
    const topPanel = ourWork.get('.desktop-nav__dropdown:not(.desktop-nav__dropdown--nested)');
    const youth = itemForLink(ourWork, 'a[href="/page/youth-development"]', '.desktop-nav__entry');
    const youthToggle = youth.get('.desktop-nav__toggle');
    const youthPanel = youth.get('.desktop-nav__dropdown--nested');
    const workshopLink = youth.get('a[href="/workshops"]');

    expectDisclosureIdRefs(topToggle, topPanel, topToggle.get('.desktop-nav__label'));
    expectDisclosureIdRefs(youthToggle, youthPanel, youth.get('a[href="/page/youth-development"]'));
    expect(youthPanel.attributes('aria-labelledby')).not.toBe(youthToggle.attributes('id'));
    expect(topToggle.attributes('aria-expanded')).toBe('false');
    expect(topPanel.attributes()).toHaveProperty('hidden');

    await topToggle.trigger('click');
    await youthToggle.trigger('click');
    expect(topToggle.attributes('aria-expanded')).toBe('true');
    expect(youthToggle.attributes('aria-expanded')).toBe('true');
    expect(topPanel.attributes()).not.toHaveProperty('hidden');
    expect(youthPanel.attributes()).not.toHaveProperty('hidden');

    workshopLink.element.focus();
    await workshopLink.trigger('keydown', { key: 'Escape' });
    await wrapper.vm.$nextTick();
    expect(youthToggle.attributes('aria-expanded')).toBe('false');
    expect(youthPanel.attributes()).toHaveProperty('hidden');
    expect(topToggle.attributes('aria-expanded')).toBe('true');
    expect(document.activeElement).toBe(youthToggle.element);

    await youthToggle.trigger('keydown', { key: 'Escape' });
    await wrapper.vm.$nextTick();
    expect(topToggle.attributes('aria-expanded')).toBe('false');
    expect(topPanel.attributes()).toHaveProperty('hidden');
    expect(document.activeElement).toBe(topToggle.element);
  });

  test('synchronizes fine-pointer hover with the same expanded state and ignores touch hover', async () => {
    const wrapper = mountNav();
    const desktop = wrapper.get('.desktop-nav');
    const ourWork = itemForLink(desktop, 'a[href="/workshops"]', '.desktop-nav__item');
    const toggle = ourWork.get('.desktop-nav__trigger');
    const panel = ourWork.get('.desktop-nav__dropdown:not(.desktop-nav__dropdown--nested)');

    await ourWork.trigger('pointerenter', { pointerType: 'mouse' });
    expect(toggle.attributes('aria-expanded')).toBe('true');
    expect(panel.attributes()).not.toHaveProperty('hidden');

    await ourWork.trigger('pointerleave', { pointerType: 'mouse' });
    expect(toggle.attributes('aria-expanded')).toBe('false');
    expect(panel.attributes()).toHaveProperty('hidden');

    await ourWork.trigger('pointerenter', { pointerType: 'touch' });
    expect(toggle.attributes('aria-expanded')).toBe('false');
    expect(panel.attributes()).toHaveProperty('hidden');
  });

  test('opens active mobile accordion ancestors and restores focus one branch at a time', async () => {
    const wrapper = mountNav({ attachTo: document.body });
    const menuButton = wrapper.get('.menu-button');

    await menuButton.trigger('click');
    const mobile = wrapper.get('.mobile-nav');
    const ourWork = itemForLink(mobile, 'a[href="/workshops"]', '.mobile-nav__group');
    const topToggle = ourWork.get('.mobile-nav__parent');
    const topPanel = ourWork.get('.mobile-nav__submenu:not(.mobile-nav__submenu--nested)');
    const youth = itemForLink(ourWork, 'a[href="/page/youth-development"]', '.mobile-nav__entry');
    const youthToggle = youth.get('.mobile-nav__toggle');
    const youthPanel = youth.get('.mobile-nav__submenu--nested');
    const workshopLink = youth.get('a[href="/workshops"]');

    expectDisclosureIdRefs(topToggle, topPanel, topToggle.get('.mobile-nav__label'));
    expectDisclosureIdRefs(youthToggle, youthPanel, youth.get('a[href="/page/youth-development"]'));
    expect(youthPanel.attributes('aria-labelledby')).not.toBe(youthToggle.attributes('id'));
    expect(topToggle.attributes('aria-expanded')).toBe('true');
    expect(youthToggle.attributes('aria-expanded')).toBe('true');
    expect(workshopLink.isVisible()).toBe(true);
    expect(ourWork.classes()).toContain('is-active');
    expect(topToggle.classes()).toContain('active');

    workshopLink.element.focus();
    await workshopLink.trigger('keydown', { key: 'Escape' });
    await wrapper.vm.$nextTick();
    expect(youthToggle.attributes('aria-expanded')).toBe('false');
    expect(topToggle.attributes('aria-expanded')).toBe('true');
    expect(document.activeElement).toBe(youthToggle.element);

    await youthToggle.trigger('keydown', { key: 'Escape' });
    await wrapper.vm.$nextTick();
    expect(topToggle.attributes('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(topToggle.element);

    await topToggle.trigger('keydown', { key: 'Escape' });
    await wrapper.vm.$nextTick();
    expect(wrapper.get('.mobile-nav').attributes()).toHaveProperty('hidden');
    expect(menuButton.attributes('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(menuButton.element);
  });

  test('cleans disclosure state and transfers focus to the visible navigation across breakpoints', async () => {
    const wrapper = mountNav({ attachTo: document.body });
    const ourWork = itemForLink(wrapper.get('.desktop-nav'), 'a[href="/workshops"]', '.desktop-nav__item');
    const desktopToggle = ourWork.get('.desktop-nav__trigger');
    const menuButton = wrapper.get('.menu-button');
    const breakpoint = mediaQueries.get('(max-width: 1180px)');

    await desktopToggle.trigger('click');
    desktopToggle.element.focus();
    expect(desktopToggle.attributes('aria-expanded')).toBe('true');
    breakpoint.emit(true);
    await wrapper.vm.$nextTick();
    expect(desktopToggle.attributes('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(menuButton.element);

    await menuButton.trigger('click');
    const mobile = wrapper.get('.mobile-nav');
    const workshopLink = mobile.get('a[href="/workshops"]');
    expect(mobile.attributes()).not.toHaveProperty('hidden');
    workshopLink.element.focus();
    breakpoint.emit(false);
    await wrapper.vm.$nextTick();
    expect(mobile.attributes()).toHaveProperty('hidden');
    expect(menuButton.attributes('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(desktopToggle.element);
  });

  test('treats an explicit empty CMS menu as authoritative', async () => {
    usePage().props.appMenus = [];
    const wrapper = mountNav();

    expect(wrapper.findAll('.desktop-nav__item')).toHaveLength(0);
    expect(wrapper.text()).not.toContain('Opportunities');
    expect(wrapper.find('a[href="/workshops"]').exists()).toBe(false);

    await wrapper.get('.menu-button').trigger('click');
    expect(wrapper.findAll('.mobile-nav__group')).toHaveLength(0);
    expect(wrapper.get('.mobile-nav__sign-in').exists()).toBe(true);
  });

  test('uses the fallback only when appMenus is absent and nests Workshop under Our Work and Youth Development', async () => {
    delete usePage().props.appMenus;
    const wrapper = mountNav();
    const desktop = wrapper.get('.desktop-nav');
    const workshop = desktop.get('a[href="/workshops"]');
    const youth = itemForLink(desktop, 'a[href="/page/youth-development"]', '.desktop-nav__entry');
    const ourWork = itemForLink(desktop, 'a[href="/workshops"]', '.desktop-nav__item');
    const getInvolved = itemForLink(desktop, 'a[href="/careers"]', '.desktop-nav__item');

    expect(ourWork.get('.desktop-nav__trigger').text()).toContain('Our Work');
    expect(youth.get('a[href="/page/youth-development"]').text()).toContain('Youth Development');
    expect(youth.element.contains(workshop.element)).toBe(true);
    expect(getInvolved.find('a[href="/workshops"]').exists()).toBe(false);
    expect(desktop.findAll('a[href="/workshops"]')).toHaveLength(1);
    expect(desktop.get('a[href="/make-a-donation"]').text()).toBe('Make a Donation');
    expect(desktop.text()).not.toContain('Opportunities');
    expect(desktop.text()).not.toContain("Founder's Letter");

    await wrapper.get('.menu-button').trigger('click');
    const mobile = wrapper.get('.mobile-nav');
    const mobileYouth = itemForLink(mobile, 'a[href="/page/youth-development"]', '.mobile-nav__entry');
    expect(mobileYouth.findAll('a[href="/workshops"]')).toHaveLength(1);
    expect(mobile.text()).not.toContain('Opportunities');
  });

  test('uses configured support actions without duplicating them in the drawer', async () => {
    const wrapper = mountNav();
    const inlineSponsor = wrapper.get('.site-nav__actions .sponsor-button');
    const inlineDonate = wrapper.get('.site-nav__actions .donate-button');
    const phoneSponsor = wrapper.get('.mobile-action-bar .sponsor-button');
    const phoneDonate = wrapper.get('.mobile-action-bar .donate-button');

    expect([inlineSponsor, phoneSponsor].map(link => [link.text(), link.attributes('href')])).toEqual([
      ['Fund a learner', '/fund-a-learner'],
      ['Fund a learner', '/fund-a-learner'],
    ]);
    expect([inlineDonate, phoneDonate].map(link => [link.text(), link.attributes('href')])).toEqual([
      ['Give today', '/give-today'],
      ['Give today', '/give-today'],
    ]);

    await wrapper.get('.menu-button').trigger('click');
    expect(wrapper.get('.mobile-nav').find('.sponsor-button').exists()).toBe(false);
    expect(wrapper.get('.mobile-nav').find('.donate-button').exists()).toBe(false);
  });

  test('hides an intentionally blank sponsor action and lets the phone Donate action span the row', () => {
    usePage().props.siteSettings.header.sponsor_label = '';
    usePage().props.siteSettings.header.sponsor_url = '';
    const wrapper = mountNav();

    expect(wrapper.findAll('.sponsor-button')).toHaveLength(0);
    expect(wrapper.get('.mobile-action-bar .donate-button').classes()).toContain('mobile-action-bar__single');
  });

  test('rejects backslash network paths masquerading as local custom destinations', () => {
    usePage().props.appMenus = [
      { name: 'Unsafe destination', uuid: 'unsafe-destination', href: String.raw`/\evil.example/path` },
    ];
    const wrapper = mountNav();

    expect(wrapper.get('.desktop-nav a').attributes('href')).toBe('#');
    expect(wrapper.get('.mobile-nav a.mobile-nav__link').attributes('href')).toBe('#');
  });

  test('compiles and applies recursive desktop and mobile styles through the child component scope boundary', async () => {
    const descriptor = parse(appNavSource, { filename: 'AppNav.vue' }).descriptor;
    const scopedStyle = descriptor.styles.find(style => style.scoped);
    const result = compileStyle({
      source: scopedStyle.content,
      filename: 'AppNav.vue',
      id: 'data-v-app-nav-regression',
      scoped: true,
    });

    expect(result.errors).toHaveLength(0);
    [
      '.desktop-nav__item',
      '.desktop-nav__trigger',
      '.desktop-nav__dropdown',
      '.desktop-nav__child',
      '.mobile-nav__parent',
      '.mobile-nav__submenu',
      '.mobile-nav__child',
    ].forEach((selector) => {
      expect(result.code).toContain(`.site-nav[data-v-app-nav-regression] ${selector}`);
      expect(result.code).not.toContain(`${selector}[data-v-app-nav-regression]`);
    });
    expect(result.code).toContain('.site-nav[data-v-app-nav-regression] ul');
    expect(result.code).not.toContain('ul[data-v-app-nav-regression]');
    expect(result.code).toContain('.site-nav[data-v-app-nav-regression] a:focus-visible');
    expect(result.code).toContain('.desktop-nav__dropdown { position:absolute;');
    expect(result.code).toContain('.mobile-nav__parent');
    expect(result.code).toContain('min-height:48px;');

    const wrapper = mountNav({ attachTo: document.body });
    const style = document.createElement('style');
    wrapper.get('.site-nav').element.setAttribute('data-v-app-nav-regression', '');
    style.textContent = result.code;
    document.head.append(style);

    try {
      const ourWork = itemForLink(wrapper.get('.desktop-nav'), 'a[href="/workshops"]', '.desktop-nav__item');
      const desktopToggle = ourWork.get('.desktop-nav__trigger');
      const desktopChild = ourWork.get('.desktop-nav__child');

      expect(getComputedStyle(desktopToggle.element).position).toBe('relative');
      expect(getComputedStyle(desktopToggle.element).color).toBe('rgb(156, 69, 0)');
      expect(getComputedStyle(desktopToggle.element).textTransform).toBe('uppercase');
      expect(getComputedStyle(desktopChild.element).display).toBe('grid');
      expect(getComputedStyle(desktopChild.element).textDecoration).toContain('none');
      expect(getComputedStyle(desktopChild.element).color).not.toBe('rgb(0, 0, 238)');

      await desktopToggle.trigger('click');
      const dropdown = ourWork.get('.desktop-nav__dropdown:not(.desktop-nav__dropdown--nested)');
      expect(getComputedStyle(dropdown.element).position).toBe('absolute');
      expect(getComputedStyle(dropdown.element).minWidth).toBe('250px');
      expect(getComputedStyle(dropdown.element).padding).toBe('10px');
      expect(getComputedStyle(dropdown.element).backgroundColor).toBe('rgb(255, 255, 255)');

      const youth = itemForLink(ourWork, 'a[href="/page/youth-development"]', '.desktop-nav__entry');
      const youthToggle = youth.get('.desktop-nav__toggle');
      await youthToggle.trigger('click');
      const nestedDropdown = youth.get('.desktop-nav__dropdown--nested');
      expect(getComputedStyle(youth.element).position).toBe('relative');
      expect(getComputedStyle(nestedDropdown.element).position).toBe('absolute');
      expect(getComputedStyle(nestedDropdown.element).top).toBe('-11px');

      const outsideChild = document.createElement('a');
      outsideChild.className = 'desktop-nav__child';
      document.body.append(outsideChild);
      expect(getComputedStyle(outsideChild).display).not.toBe('grid');
      outsideChild.remove();

      await wrapper.get('.menu-button').trigger('click');
      const mobileParent = wrapper.get('.mobile-nav__parent');
      const mobileSubmenu = wrapper.get('.mobile-nav__submenu');
      expect(getComputedStyle(mobileParent.element).display).toBe('flex');
      expect(getComputedStyle(mobileParent.element).minHeight).toBe('48px');
      expect(getComputedStyle(mobileParent.element).textDecoration).toContain('none');
      expect(getComputedStyle(mobileSubmenu.element).padding).toBe('5px 0px 8px');
      expect(getComputedStyle(mobileSubmenu.element).backgroundColor).toBe('rgb(250, 248, 246)');
    } finally {
      style.remove();
    }
  });

  test('keeps state-driven visibility and responsive touch targets in component CSS', () => {
    expect(appNavSource).toContain('@media(max-width:1460px)');
    expect(appNavSource).toContain('@media(max-width:1180px)');
    expect(appNavSource).toContain('@media(min-width:1181px)');
    expect(appNavSource).toContain('.site-nav__actions>.site-nav__inline-action { display:none; }');
    expect(appNavSource).toContain('.desktop-nav__dropdown[hidden]');
    expect(appNavSource).toContain('.mobile-nav[hidden] { display:none; }');
    expect(appNavSource).toContain('.mobile-nav__group.is-active>:is(.mobile-nav__link,.mobile-nav__parent)');
    expect(appNavSource).toContain('width:44px');
    expect(appNavSource).toContain('min-height:44px');
    expect(appNavSource).toContain('100dvh');
    expect(appNavSource).toContain(':focus-visible');
    expect(appNavSource).not.toContain('.desktop-nav__item:hover .desktop-nav__dropdown');
    expect(appNavSource).not.toContain('.desktop-nav__item:focus-within .desktop-nav__dropdown');
    expect(appNavSource).not.toContain('.nav-icon { display:none; }');

    const desktopDescriptionColor = appNavSource.match(/\.desktop-nav__dropdown small\) \{ color:(#[\da-f]{6})/i)?.[1];
    const mobileDescriptionColor = appNavSource.match(/\.mobile-nav__child small\) \{ color:(#[\da-f]{6})/i)?.[1];
    expect(desktopDescriptionColor).toBeTruthy();
    expect(mobileDescriptionColor).toBe(desktopDescriptionColor);
    expect(contrastRatio(desktopDescriptionColor, '#ffffff')).toBeGreaterThanOrEqual(4.5);
    expect(contrastRatio(mobileDescriptionColor, '#faf8f6')).toBeGreaterThanOrEqual(4.5);
  });
});
