import { mount } from '@vue/test-utils'
import { usePage } from '@inertiajs/vue3'
import AnnualReportDetail from '@/Pages/annual-report-detail.vue'

const layoutStub = { template: '<main><slot /></main>' }

function mountDetail(
  imageUrl = '/storage/media/reports/impact-cover.webp',
  reportOverrides = {},
  settingOverrides = {},
) {
  usePage().props = {
    data: {
      report: {
        title: 'Impact Report 2025',
        sub_title: 'A year of community-led outcomes.',
        summary: 'Programs, governance, and audited stewardship.',
        publisher_name: 'Impact Team',
        published_at: '2025-12-31',
        year: 2025,
        file_size: 2048,
        image_url: imageUrl,
        download_url: '/annual-report/download/impact-report-2025',
        source_url: 'https://reports.example.test/impact-report-2025',
        ...reportOverrides,
      },
    },
    siteSettings: {
      regional: { number_locale: 'en-US', date_locale: 'en-US', timezone: 'Asia/Dhaka' },
      reports_page: {
        detail_back_label: 'Back to reports',
        detail_eyebrow: 'Annual report',
        detail_summary_title: 'Report summary',
        detail_year_label: 'Year',
        detail_publisher_label: 'Publisher',
        detail_release_label: 'Released',
        detail_download_label: 'Download report',
        detail_download_note: 'PDF document',
        detail_source_label: 'Publisher page',
        detail_cover_alt_template: '{title} cover',
        detail_file_type_label: 'PDF',
        detail_file_separator: '·',
        detail_file_unit_bytes: 'B',
        detail_file_unit_kilobytes: 'KB',
        detail_file_unit_megabytes: 'MB',
        detail_file_unit_gigabytes: 'GB',
        format_label: 'Format',
        report_column_label: 'Report details',
        ...settingOverrides,
      },
    },
  }

  return mount(AnnualReportDetail, {
    global: { stubs: { Layout: layoutStub, App: layoutStub } },
  })
}

describe('annual-report detail cover', () => {
  beforeEach(() => {
    vi.stubGlobal('route', vi.fn(() => '/annual-report'))
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  test('renders the managed cover with meaningful alternative text', () => {
    const wrapper = mountDetail()
    const image = wrapper.get('.igf-report-detail__cover img')

    expect(image.attributes()).toMatchObject({
      src: '/storage/media/reports/impact-cover.webp',
      alt: 'Impact Report 2025 cover',
    })
    expect(wrapper.get('.igf-report-detail__summary').text()).toContain('Programs, governance, and audited stewardship.')
  })

  test('keeps the detail layout intact when no cover is configured', () => {
    const wrapper = mountDetail(null)

    expect(wrapper.find('.igf-report-detail__cover').exists()).toBe(false)
    expect(wrapper.get('h1').text()).toBe('Impact Report 2025')
    expect(wrapper.get('.igf-download').attributes('href')).toBe('/annual-report/download/impact-report-2025')
  })

  test('uses managed localized cover and file-detail copy', () => {
    const wrapper = mountDetail(
      '/storage/media/reports/impact-cover.webp',
      { file_size: 2048 },
      {
        detail_cover_alt_template: 'Preview: {title}',
        detail_file_type_label: 'Portable document',
        detail_file_separator: '/',
        detail_file_unit_kilobytes: 'KiB',
      },
    )

    expect(wrapper.get('.igf-report-detail__cover img').attributes('alt')).toBe('Preview: Impact Report 2025')
    expect(wrapper.get('.igf-report-detail__card').text()).toContain('Portable document / 2 KiB')
  })
})
