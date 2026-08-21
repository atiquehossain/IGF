import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import AppNav from '@/layouts/AppNav.vue';
import appNavSource from '@/layouts/AppNav.vue?raw';

describe('AppNav submenu behavior', () => {
  beforeEach(() => {
    usePage().props = {
      appName: 'Ignite Global Foundation',
      appMenus: [
        { name: 'Home', href: '/' },
        {
          name: 'Projects',
          link: 'custom',
          slug: '#',
          children: [
            { name: 'Current Projects', href: '/projects/current-project' },
            { name: 'Completed Projects', href: '/projects/completed-project' },
          ],
        },
      ],
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
    window.history.replaceState({}, '', '/projects/current-project');
    window.route = (name) => name ? '/' : ({ has: () => false });
  });

  test('opens and closes the desktop Projects submenu with accessible state', async () => {
    const wrapper = mount(AppNav, { global: { mocks: { route: window.route } } });
    const logo = wrapper.get('.site-brand__logo');

    expect(logo.attributes('src')).toBe('/image/logo.png');
    expect(logo.attributes('alt')).toBe('Ignite Global Foundation');

    const trigger = wrapper.get('.desktop-nav__trigger');

    expect(trigger.text()).toContain('Projects');
    expect(trigger.attributes('aria-expanded')).toBe('false');
    expect(wrapper.findAll('.desktop-nav__dropdown a strong').map(link => link.text())).toEqual([
      'Current Projects',
      'Completed Projects',
    ]);

    await trigger.trigger('click');
    expect(trigger.attributes('aria-expanded')).toBe('true');
    expect(wrapper.get('.desktop-nav__item.is-open').exists()).toBe(true);

    await trigger.trigger('keydown', { key: 'Escape' });
    expect(trigger.attributes('aria-expanded')).toBe('false');
  });

  test('uses a collapsed mobile accordion instead of an always-expanded link list', async () => {
    const wrapper = mount(AppNav, { global: { mocks: { route: window.route } } });

    await wrapper.get('.menu-button').trigger('click');
    const parent = wrapper.get('.mobile-nav__parent');
    const submenu = wrapper.get('.mobile-nav__submenu');

    expect(parent.text()).toContain('Projects');
    expect(parent.attributes('aria-expanded')).toBe('true');
    expect(submenu.isVisible()).toBe(true);

    await parent.trigger('click');
    expect(parent.attributes('aria-expanded')).toBe('false');
    expect(submenu.attributes('style') || '').toContain('display: none');
    expect(submenu.findAll('a strong').map(link => link.text())).toEqual([
      'Current Projects',
      'Completed Projects',
    ]);
  });

  test('uses the configured support actions in both persistent header variants without duplicating them in the drawer', async () => {
    const wrapper = mount(AppNav, { global: { mocks: { route: window.route } } });

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
    const drawer = wrapper.get('.mobile-nav');
    expect(drawer.find('.sponsor-button').exists()).toBe(false);
    expect(drawer.find('.donate-button').exists()).toBe(false);
  });

  test('hides an intentionally blank sponsor action and lets the phone Donate action span the row', () => {
    usePage().props.siteSettings.header.sponsor_label = '';
    usePage().props.siteSettings.header.sponsor_url = '';

    const wrapper = mount(AppNav, { global: { mocks: { route: window.route } } });

    expect(wrapper.findAll('.sponsor-button')).toHaveLength(0);
    expect(wrapper.get('.mobile-action-bar .donate-button').classes()).toContain('mobile-action-bar__single');
  });

  test('closes the drawer with Escape and restores focus to its menu button', async () => {
    const wrapper = mount(AppNav, { attachTo: document.body, global: { mocks: { route: window.route } } });
    const menuButton = wrapper.get('.menu-button');

    await menuButton.trigger('click');
    expect(menuButton.attributes('aria-expanded')).toBe('true');

    const drawer = wrapper.get('.mobile-nav');
    drawer.get('a').element.focus();
    await drawer.trigger('keydown', { key: 'Escape' });
    await wrapper.vm.$nextTick();

    expect(menuButton.attributes('aria-expanded')).toBe('false');
    expect(wrapper.find('.mobile-nav').exists()).toBe(false);
    expect(document.activeElement).toBe(menuButton.element);

    await menuButton.trigger('click');
    await menuButton.trigger('keydown', { key: 'Escape' });
    await wrapper.vm.$nextTick();
    expect(menuButton.attributes('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(menuButton.element);
    wrapper.unmount();
  });

  test('keeps a gap-free responsive action contract in the component CSS', () => {
    expect(appNavSource).toContain('@media(max-width:1180px)');
    expect(appNavSource).toContain('@media(max-width:720px)');
    expect(appNavSource).toContain('.site-nav__actions>.site-nav__inline-action { display:none; }');
    expect(appNavSource).toContain('top:100%');
    expect(appNavSource).toContain('100dvh');
    expect(appNavSource).toContain('min-height:44px');
    expect(appNavSource).toContain(':focus-visible');
    expect(appNavSource).not.toContain('@media(max-width:1350px)');
    expect(appNavSource).not.toContain('@media(max-width:920px)');
    expect(appNavSource).not.toContain('@media(max-width:860px)');
    expect(appNavSource).not.toContain('.nav-icon { display:none; }');
  });
});
