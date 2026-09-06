import { mount } from '@vue/test-utils'
import { usePage } from '@inertiajs/vue3'
import Gallery from '@/Pages/gallery.vue'

const layoutStub = { template: '<main><slot /></main>' }
const dialogStub = {
  props: ['modelValue'],
  emits: ['update:modelValue'],
  template: '<div v-if="modelValue" data-test="dialog"><slot /></div>',
}

function mountGallery() {
  usePage().props = {
    siteSettings: {
      gallery_page: {
        eyebrow: 'Stories in pictures',
        title: 'Photo gallery',
        introduction: 'Community moments.',
        album_label: 'Album',
        all_albums_label: 'All albums',
        search_label: 'Search photos',
        search_placeholder: 'Search by title',
        apply_label: 'Apply filters',
        clear_label: 'Clear',
        empty_title: 'No photos',
        empty_body: 'Try again.',
        photo_singular: 'photo',
        photo_plural: 'photos',
        open_image_label: 'Enlarge {name}',
        close_image_label: 'Close photo viewer',
        previous_image_label: 'Earlier photo',
        next_image_label: 'Later photo',
        image_position_label: 'Image {current} / {total}',
        fallback_image_alt: 'Community photo',
      },
      regional: { number_locale: 'en-US' },
    },
    properties: { page: 1, total_page: 1, total_count: 2, search: '', album_id: null },
    data: {
      albums: [{ id: 1, name: 'Community' }],
      items: [
        { id: 10, name: 'Workshop', alt_text: 'People learning together.', path: '/thumb-one.jpg', main_path: '/one.jpg', album_name: 'Community' },
        { id: 11, name: 'Supply delivery', alt_text: '', path: '/thumb-two.jpg', main_path: '/two.jpg', album_name: 'Community' },
      ],
    },
  }

  return mount(Gallery, {
    global: {
      stubs: {
        App: layoutStub,
        Layout: layoutStub,
        AppBannerPage: true,
        'v-dialog': dialogStub,
        'v-pagination': true,
      },
    },
  })
}

describe('public gallery viewer', () => {
  test('uses editor captions and accessible descriptions in the gallery grid', () => {
    const wrapper = mountGallery()
    const photos = wrapper.findAll('.igf-photo')

    expect(photos).toHaveLength(2)
    expect(photos[0].attributes('aria-label')).toBe('Enlarge Workshop')
    expect(photos[0].get('img').attributes('alt')).toBe('People learning together.')
    expect(photos[1].get('img').attributes('alt')).toBe('Supply delivery')
    expect(photos[0].text()).toContain('Workshop')
    expect(photos[0].text()).toContain('Community')

    wrapper.unmount()
  })

  test('navigates the lightbox with visible controls and arrow keys', async () => {
    const wrapper = mountGallery()

    await wrapper.findAll('.igf-photo')[0].trigger('click')
    expect(wrapper.get('.igf-lightbox img').attributes('src')).toBe('/one.jpg')
    expect(wrapper.get('.igf-lightbox img').attributes('alt')).toBe('People learning together.')
    expect(wrapper.get('.igf-lightbox__nav--previous').attributes('aria-label')).toBe('Earlier photo')
    expect(wrapper.get('.igf-lightbox__nav--next').attributes('aria-label')).toBe('Later photo')
    expect(wrapper.get('.igf-lightbox__close').attributes('aria-label')).toBe('Close photo viewer')
    expect(wrapper.get('.igf-lightbox__caption small').text()).toBe('Image 1 / 2')

    await wrapper.get('.igf-lightbox__nav--next').trigger('click')
    expect(wrapper.get('.igf-lightbox img').attributes('src')).toBe('/two.jpg')
    expect(wrapper.get('.igf-lightbox__caption small').text()).toBe('Image 2 / 2')

    await wrapper.get('.igf-lightbox').trigger('keydown', { key: 'ArrowLeft' })
    expect(wrapper.get('.igf-lightbox img').attributes('src')).toBe('/one.jpg')

    await wrapper.get('.igf-lightbox__nav--previous').trigger('click')
    expect(wrapper.get('.igf-lightbox img').attributes('src')).toBe('/two.jpg')

    wrapper.unmount()
  })
})
