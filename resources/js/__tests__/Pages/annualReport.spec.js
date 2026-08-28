import { mount } from '@vue/test-utils'
import { router, usePage } from '@inertiajs/vue3'
import AnnualReport from '@/Pages/annual-report.vue'

const layoutStub = { template: '<main><slot /></main>' }
const paginationStub = {
  props: ['modelValue', 'length'],
  emits: ['update:modelValue'],
  template: '<button data-test="pagination" @click="$emit(\'update:modelValue\', 2)">Next</button>',
}

const reportSettings = {
  eyebrow: 'Transparency & accountability',
  title: 'Annual reports',
  introduction: 'Review our published reports.',
  library_eyebrow: 'Document library',
  library_title: 'Published reports',
  search_label: 'Search reports',
  search_placeholder: 'Search by report title',
  date_label: 'Release date',
  apply_label: 'Apply filters',
  clear_label: 'Clear',
  download_label: 'Download',
  view_label: 'View report',
  document_singular: 'document',
  document_plural: 'documents',
  format_label: 'PDF report',
  unknown_date_label: 'Not specified',
  empty_title: 'No reports found',
  empty_body: 'Try another search or clear the filters.',
}

function mountReports({ items = [], data = {}, properties = {} } = {}) {
  usePage().props = {
    appName: 'Ignite Global Foundation',
    data: { items, ...data },
    properties: { total: items.length, current_page: 1, total_page: 1, ...properties },
    siteSettings: {
      branding: { site_name: 'Ignite Global Foundation' },
      regional: { number_locale: 'en-US', date_locale: 'en-US', timezone: 'Asia/Dhaka' },
      reports_page: reportSettings,
    },
  }

  return mount(AnnualReport, {
    global: {
      stubs: { Layout: layoutStub, App: layoutStub, 'v-pagination': paginationStub },
    },
  })
}

describe('annual-report card library', () => {
  beforeEach(() => {
    vi.stubGlobal('route', vi.fn(() => '/annual-report'))
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  test('renders responsive report cards with managed covers and a branded fallback', () => {
    const wrapper = mountReports({
      items: [
        {
          id: 1,
          title: 'Annual Report 2025',
          sub_title: 'Our impact during 2025.',
          published_at: '2025-12-31',
          image_url: '/storage/reports/annual-report-2025.webp',
          landing_url: '/annual-report/annual-report-2025',
          download_url: '/annual-report/annual-report-2025/download',
        },
        {
          id: 2,
          title: 'Annual Report 2024',
          summary: 'A year of community-led progress.',
          published_at: '2024-12-31',
          image_url: 'https://tracking.example/report.webp',
          landing_url: '/annual-report/annual-report-2024',
          download_url: '/annual-report/annual-report-2024/download',
        },
      ],
    })

    const cards = wrapper.findAll('.igf-report-card')
    expect(cards).toHaveLength(2)
    expect(wrapper.find('table').exists()).toBe(false)
    expect(cards[0].get('img').attributes()).toMatchObject({
      src: '/storage/reports/annual-report-2025.webp',
      alt: '',
      loading: 'lazy',
      decoding: 'async',
    })
    expect(cards[1].find('img').exists()).toBe(false)
    expect(cards[1].get('.igf-report-card__fallback').text()).toContain('Ignite Global Foundation')
    expect(cards[1].get('.igf-report-card__year').text()).toBe('2024')
    expect(cards[0].get('.igf-report-card__body > p').text()).toBe('Our impact during 2025.')
    expect(cards[1].get('.igf-report-card__body > p').text()).toBe('A year of community-led progress.')

    expect(cards[0].findAll('a')).toHaveLength(2)
    expect(cards[0].findAll('a').map(link => link.attributes('aria-label'))).toEqual([
      'View report: Annual Report 2025',
      'Download: Annual Report 2025',
    ])
    expect(cards[0].findAll('a').map(link => link.attributes('href'))).toEqual([
      '/annual-report/annual-report-2025',
      '/annual-report/annual-report-2025/download',
    ])
    expect(cards[0].get('time').attributes('datetime')).toBe('2025-12-31')
  })

  test('announces an empty result without rendering the card grid', () => {
    const wrapper = mountReports()

    expect(wrapper.find('.igf-report-grid').exists()).toBe(false)
    expect(wrapper.get('.igf-reports__empty').attributes()).toMatchObject({
      role: 'status',
      'aria-live': 'polite',
    })
    expect(wrapper.get('.igf-reports__empty h3').text()).toBe('No reports found')
  })

  test('preserves filters for search, clear, and pagination visits', async () => {
    const get = vi.spyOn(router, 'get').mockImplementation(() => {})
    const wrapper = mountReports({
      items: [{
        id: 1,
        title: 'Annual Report 2025',
        published_at: '2025-12-31',
        landing_url: '/annual-report/annual-report-2025',
        download_url: '/annual-report/annual-report-2025/download',
      }],
      data: { search: 'impact', published_at: '2025-12-31' },
      properties: { total_page: 3 },
    })

    await wrapper.get('form').trigger('submit')
    expect(get).toHaveBeenLastCalledWith('/annual-report', {
      search: 'impact',
      published_at: '2025-12-31',
    }, { preserveState: true, preserveScroll: true, replace: true })

    await wrapper.get('[data-test="pagination"]').trigger('click')
    expect(get).toHaveBeenLastCalledWith('/annual-report', {
      page: 2,
      search: 'impact',
      published_at: '2025-12-31',
    }, { preserveState: true, preserveScroll: true, replace: true })

    await wrapper.get('.igf-clear').trigger('click')
    expect(get).toHaveBeenLastCalledWith('/annual-report', {}, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    })
  })
})
