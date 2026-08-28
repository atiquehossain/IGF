import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import ContactUs from '@/Pages/contactUs.vue';
import contactSource from '@/Pages/contactUs.vue?raw';

const layoutStub = { template: '<main><slot /></main>' };

function mountContact() {
  usePage().props = {
    siteSettings: {
      contact: {},
      contact_page: {
        faq_eyebrow: 'Helpful information',
        faq_title: 'Frequently asked questions',
        faqs: [
          { question: 'What is Ignite?', answer: 'Ignite works alongside communities.', is_active: true },
          { question: 'Is Ignite registered?', answer: 'Yes. Registration details are published.', is_active: true },
          { question: 'Hidden question?', answer: 'This must not render.', is_active: false },
        ],
      },
    },
  };

  return mount(ContactUs, {
    attachTo: document.body,
    global: { stubs: { App: layoutStub, Layout: layoutStub } },
  });
}

describe('Contact FAQ accordion animation', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  test('preserves reciprocal ARIA relationships while starting with only the first answer open', () => {
    const wrapper = mountContact();
    const buttons = wrapper.findAll('.igf-accordion button');
    const panels = wrapper.findAll('.igf-accordion__panel');

    expect(buttons).toHaveLength(2);
    expect(panels).toHaveLength(2);
    expect(buttons[0].attributes('aria-expanded')).toBe('true');
    expect(buttons[0].attributes('aria-controls')).toBe(panels[0].attributes('id'));
    expect(panels[0].attributes('aria-labelledby')).toBe(buttons[0].attributes('id'));
    expect(panels[0].attributes('aria-hidden')).toBe('false');
    expect(panels[0].classes()).toContain('is-open');
    expect(panels[0].attributes()).not.toHaveProperty('inert');

    expect(buttons[1].attributes('aria-expanded')).toBe('false');
    expect(panels[1].attributes('aria-hidden')).toBe('true');
    expect(panels[1].classes()).not.toContain('is-open');
    expect(panels[1].attributes()).toHaveProperty('inert');
  });

  test('animates independent answers without replacing the icon or moving trigger focus', async () => {
    const wrapper = mountContact();
    const buttons = wrapper.findAll('.igf-accordion button');
    const panels = wrapper.findAll('.igf-accordion__panel');
    const secondIcon = buttons[1].get('.igf-accordion__icon').element;

    buttons[1].element.focus();
    await buttons[1].trigger('click');

    expect(document.activeElement).toBe(buttons[1].element);
    expect(buttons[0].attributes('aria-expanded')).toBe('true');
    expect(buttons[1].attributes('aria-expanded')).toBe('true');
    expect(panels[1].classes()).toContain('is-open');
    expect(panels[1].attributes('aria-hidden')).toBe('false');
    expect(panels[1].attributes()).not.toHaveProperty('inert');
    expect(buttons[1].get('.igf-accordion__icon').element).toBe(secondIcon);

    await buttons[1].trigger('click');
    expect(buttons[1].attributes('aria-expanded')).toBe('false');
    expect(panels[1].classes()).not.toContain('is-open');
    expect(panels[1].attributes('aria-hidden')).toBe('true');
    expect(buttons[1].get('.igf-accordion__icon').element).toBe(secondIcon);
  });

  test('uses content-driven grid animation, icon motion, and a reduced-motion fallback', () => {
    expect(contactSource).toContain('grid-template-rows:0fr');
    expect(contactSource).toContain('grid-template-rows:1fr');
    expect(contactSource).toContain('transition:grid-template-rows .32s');
    expect(contactSource).toContain('scaleY(0)');
    expect(contactSource).toContain('@media(prefers-reduced-motion:reduce)');
    expect(contactSource).not.toContain('v-show="openItems.includes(index)"');
    expect(contactSource).not.toContain("fa-solid fa-minus");
  });
});
