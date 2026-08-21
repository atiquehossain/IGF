import { mount } from '@vue/test-utils'
import { usePage } from '@inertiajs/vue3'
import AnnualReport from '@/Pages/annual-report.vue'
import EventDetail from '@/Pages/event.vue'
import Gallery from '@/Pages/gallery.vue'
import Project from '@/Pages/project.vue'
import Search from '@/Pages/search.vue'

const layoutStub = { template: '<main><slot /></main>' }
const regional = {
  number_locale: 'bn-BD',
  date_locale: 'bn-BD',
  timezone: 'Asia/Dhaka',
}

function mountPage(component, props, stubs = {}) {
  usePage().props = {
    ...props,
    siteSettings: {
      ...(props.siteSettings || {}),
      regional,
    },
  }

  return mount(component, {
    global: {
      stubs: {
        App: layoutStub,
        Layout: layoutStub,
        AppBannerPage: true,
        CategoryItemCard: true,
        'v-dialog': true,
        'v-pagination': true,
        ...stubs,
      },
    },
  })
}

describe('active public page regional formatting', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  test('formats project, gallery, and search result totals with the configured number locale', () => {
    const expected = new Intl.NumberFormat('bn-BD').format(12345)
    const project = mountPage(Project, {
      data: { tag: null, items: [] },
      properties: { total_count: 12345, total_page: 1 },
      siteSettings: { content_archives: { project_count_plural: 'projects' } },
    })
    expect(project.get('.igf-projects__intro small').text()).toContain(expected)
    project.unmount()

    const gallery = mountPage(Gallery, {
      data: { items: [], albums: [] },
      properties: { total_count: 12345, total_page: 1 },
      siteSettings: { gallery_page: { photo_plural: 'photos' } },
    })
    expect(gallery.get('.igf-result-count').text()).toContain(expected)
    gallery.unmount()

    const search = mountPage(Search, {
      data: { pages: [] },
      properties: { search: 'education', total_count: 12345, total_page: 1 },
      siteSettings: { search_page: { results_for_label: 'results for' } },
    })
    expect(search.get('.igf-search__summary strong').text()).toBe(expected)
    search.unmount()
  })

  test('formats annual-report totals, row numbers, and raw ISO dates', () => {
    const publishedAt = '2026-08-19'
    const wrapper = mountPage(AnnualReport, {
      data: {
        items: [{ id: 1, title: 'Impact report', published_at: publishedAt, download_url: '/annual-report/download/impact' }],
      },
      properties: { total: 12345, current_page: 2, per_page: 12, total_page: 2 },
      siteSettings: {
        reports_page: {
          document_plural: 'documents',
          format_label: 'PDF',
          download_label: 'Download',
        },
      },
    })
    const cells = wrapper.findAll('tbody td')

    expect(wrapper.get('.igf-reports__heading > span').text()).toContain(new Intl.NumberFormat('bn-BD').format(12345))
    expect(cells[0].text()).toBe(new Intl.NumberFormat('bn-BD').format(13))
    expect(cells[2].text()).toBe(
      new Intl.DateTimeFormat('bn-BD', { dateStyle: 'long', timeZone: 'Asia/Dhaka' }).format(new Date(publishedAt)),
    )
    wrapper.unmount()
  })

  test('formats event-detail raw ISO dates with the configured locale', () => {
    const publishedAt = '2026-08-19'
    const wrapper = mountPage(EventDetail, {
      data: { event: { title: 'Community day', published_at: publishedAt, description: '' } },
      siteSettings: { content_archives: {} },
    })

    expect(wrapper.get('.igf-event__meta span').text()).toContain(
      new Intl.DateTimeFormat('bn-BD', { dateStyle: 'long', timeZone: 'Asia/Dhaka' }).format(new Date(publishedAt)),
    )
    wrapper.unmount()
  })
})
