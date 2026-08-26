import { mount } from '@vue/test-utils'
import { router, usePage } from '@inertiajs/vue3'
import Careers from '@/Pages/careers.vue'
import Workshops from '@/Pages/workshops.vue'

const layoutStub = { template: '<div><slot /></div>' }
const paginationStub = {
  props: ['modelValue', 'length'],
  emits: ['update:modelValue'],
  template: '<button data-test="pagination" @click="$emit(\'update:modelValue\', 2)">Next</button>',
}

function mountPage(component) {
  return mount(component, {
    global: {
      mocks: { route: globalThis.route },
      stubs: { Layout: layoutStub, 'v-pagination': paginationStub },
    },
  })
}

describe('public opportunity listings', () => {
  beforeEach(() => {
    vi.stubGlobal('route', vi.fn((name, slug) => `/${name}/${slug || ''}`))
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  test('renders active career items with recruitment metadata and links', () => {
    usePage().props = {
      title: 'Careers',
      data: {
        items: [
          { id: 1, slug: 'program-officer', title: 'Program Officer', department: 'Programs', location: 'Dhaka', employment_type_label: 'Full time', application_deadline_label: '30 September' },
          { id: 2, slug: 'finance-intern', title: 'Finance Intern', department: 'Finance', location: 'Hybrid' },
        ],
        copy: { listing_title: 'Open positions', card: { link_label: 'See details and apply' } },
      },
      properties: { page: 1, total_page: 1 },
    }
    usePage().url = '/careers'
    const wrapper = mountPage(Careers)

    expect(wrapper.findAll('.igf-opportunity-card')).toHaveLength(2)
    expect(wrapper.get('#career-listing-title').text()).toBe('Open positions')
    expect(wrapper.get('h2 a').attributes('href')).toBe('/frontend.jobs.show/program-officer')
    expect(wrapper.text()).toContain('See details and apply')
    expect(wrapper.text()).toContain('Full time')
  })

  test('renders a bilingual-ready empty Careers state from server copy', () => {
    usePage().props = {
      title: 'চাকরি',
      data: { items: [], copy: { eyebrow: 'আমাদের সঙ্গে কাজ করুন', title: 'ক্যারিয়ার', listing_title: 'বর্তমান সুযোগ', empty_title: 'এখন কোনো পদ খালি নেই', empty_message: 'পরে আবার দেখুন।' } },
      properties: {},
    }
    const wrapper = mountPage(Careers)
    expect(wrapper.get('h1').text()).toBe('ক্যারিয়ার')
    expect(wrapper.get('[role="status"]').text()).toContain('এখন কোনো পদ খালি নেই')
  })

  test('preserves listing state when changing a Careers page', async () => {
    const get = vi.spyOn(router, 'get').mockImplementation(() => {})
    usePage().props = { title: 'Careers', data: { items: [{ id: 1, slug: 'one', title: 'One' }] }, properties: { page: 1, total_page: 3 } }
    usePage().url = '/careers?department=programs'
    const wrapper = mountPage(Careers)
    await wrapper.get('[data-test="pagination"]').trigger('click')
    expect(get).toHaveBeenCalledWith('/careers?department=programs', { page: 2 }, { preserveState: true, preserveScroll: true })
  })

  test('renders workshop-specific cards and server-authored Bangla copy', () => {
    usePage().props = {
      title: 'কর্মশালা',
      data: {
        items: [{
          id: 4,
          slug: 'leadership',
          title: 'নেতৃত্ব কর্মশালা',
          summary: 'এই সারসংক্ষেপটি কেবল বিস্তারিত পাতায় দেখা যাবে।',
          image_url: '/storage/workshops/leadership-poster.webp',
          image_alt: 'নেতৃত্ব কর্মশালার পোস্টার',
          workshop_date_label: '১২ অক্টোবর',
          venue: 'ঢাকা',
          registration_deadline_label: '৮ অক্টোবর',
        }],
        copy: { title: 'বিনামূল্যের কর্মশালা', listing_title: 'আসন্ন কর্মশালা', card: { link_label: 'বিস্তারিত ও নিবন্ধন' } },
      },
      properties: { page: 1, total_page: 1 },
    }
    const wrapper = mountPage(Workshops)
    expect(wrapper.get('h1').text()).toBe('বিনামূল্যের কর্মশালা')
    expect(wrapper.get('h2 a').attributes('href')).toBe('/frontend.workshops.show/leadership')
    expect(wrapper.get('.igf-opportunity-card__media img').attributes()).toMatchObject({
      src: '/storage/workshops/leadership-poster.webp',
      alt: 'নেতৃত্ব কর্মশালার পোস্টার',
      loading: 'lazy',
      decoding: 'async',
    })
    expect(wrapper.text()).toContain('১২ অক্টোবর')
    expect(wrapper.text()).toContain('বিস্তারিত ও নিবন্ধন')
    expect(wrapper.text()).not.toContain('এই সারসংক্ষেপটি কেবল বিস্তারিত পাতায় দেখা যাবে।')
    expect(wrapper.text().toLocaleLowerCase()).not.toMatch(/payment|certificate|feedback/)
  })

  test('renders the workshop empty state without adding unrelated modules', () => {
    usePage().props = { title: 'Workshops', data: { items: [], copy: { empty_title: 'No sessions available', empty_message: 'Check later.' } }, properties: {} }
    const wrapper = mountPage(Workshops)
    expect(wrapper.get('[role="status"]').text()).toContain('No sessions available')
    expect(wrapper.find('input[type="file"]').exists()).toBe(false)
  })
})
