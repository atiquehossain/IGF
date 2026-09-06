import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import { beforeEach, describe, expect, test, vi } from 'vitest';
import PageBlocks from '@/Shared/PageBlocks.vue';

function configurePage() {
  usePage().props = {
    locale: 'en',
    siteSettings: {
      shared_blocks: {
        partner_external_link_label: '{name}, opens in a new tab',
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

function block(uuid, content) {
  return {
    uuid,
    type: 'partners',
    label: 'Partners',
    is_enabled: true,
    show_on_desktop: true,
    show_on_mobile: true,
    content,
  };
}

describe('PageBlocks client-handoff design contract', () => {
  beforeEach(() => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: false });
    configurePage();
  });

  test.each([
    ['compact', 'left', 'auto'],
    ['standard', 'center', '2'],
    ['spacious', 'left', '3'],
    ['compact', 'center', '4'],
  ])('applies safe spacing %s, alignment %s, and columns %s classes', (spacing, alignment, columns) => {
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [block(`design-${spacing}-${alignment}-${columns}`, {
          heading: 'Trusted partners',
          section_spacing: spacing,
          content_alignment: alignment,
          column_count: columns,
          items: [],
        })],
      },
    });

    const section = wrapper.get('section.igf-page-block');
    expect(section.classes()).toEqual(expect.arrayContaining([
      `igf-page-block--spacing-${spacing}`,
      `igf-page-block--align-${alignment}`,
      `igf-page-block--columns-${columns}`,
    ]));

    wrapper.unmount();
  });

  test('normalizes unsupported design tokens instead of turning them into classes', () => {
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [block('unsafe-design', {
          heading: 'Trusted partners',
          section_spacing: 'spacious injected-class',
          content_alignment: 'right',
          column_count: '12',
          items: [],
        })],
      },
    });

    const section = wrapper.get('section.igf-page-block');
    expect(section.classes()).toEqual(expect.arrayContaining([
      'igf-page-block--spacing-standard',
      'igf-page-block--align-left',
      'igf-page-block--columns-auto',
    ]));
    expect(section.attributes('class')).not.toContain('injected-class');
    expect(section.attributes('class')).not.toContain('columns-12');

    wrapper.unmount();
  });

  test('uses authored partner image alt text with the partner heading as its fallback', () => {
    const wrapper = mount(PageBlocks, {
      props: {
        blocks: [block('partner-alt', {
          heading: 'Trusted partners',
          items: [
            {
              heading: 'Accessible Foundation',
              image: '/images/accessible-foundation.png',
              image_alt: 'Accessible Foundation tree emblem',
              url: 'https://example.org',
            },
            {
              heading: 'Community Network',
              image: '/images/community-network.png',
              image_alt: '',
              url: '',
            },
          ],
        })],
      },
    });

    expect(wrapper.findAll('.igf-partner-card img').map(image => image.attributes('alt'))).toEqual([
      'Accessible Foundation tree emblem',
      'Community Network',
    ]);

    wrapper.unmount();
  });
});
