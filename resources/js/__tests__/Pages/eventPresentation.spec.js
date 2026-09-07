import { mount } from '@vue/test-utils'
import { router, usePage } from '@inertiajs/vue3'
import EventDetail from '@/Pages/event.vue'
import Events from '@/Pages/events.vue'
import EventFacts from '@/Shared/EventFacts.vue'

const layoutStub = { template: '<main><slot /></main>' }
const regional = {
  date_locale: 'en-GB',
  number_locale: 'en-GB',
  timezone: 'Asia/Dhaka',
}
const settings = {
  events_eyebrow: 'Stay involved',
  events_introduction: 'Events and community stories.',
  events_listing_label: 'Events and news',
  events_empty_title: 'Nothing scheduled yet',
  events_empty_body: 'Check back for updates.',
  events_default_title: 'Events & latest news',
  events_pagination_label: 'Event and news listing pages',
  events_pagination_page_label: 'Go to page {0}',
  events_pagination_current_label: 'Current page, page {0}',
  events_pagination_previous_label: 'Previous page',
  events_pagination_next_label: 'Next page',
  event_card_eyebrow: 'Event or story',
  event_card_link_label: 'Read more',
  event_back_label: 'All events',
  event_detail_eyebrow: 'Community update',
  event_footer_label: 'Back to all events',
  event_facts_label: 'Managed event details',
  event_start_label: 'Begins',
  event_end_label: 'Finishes',
  event_status_label: 'Current status',
  event_attendance_label: 'How to attend',
  event_status_scheduled_label: 'Going ahead',
  event_status_postponed_label: 'Delayed',
  event_status_rescheduled_label: 'New date',
  event_status_moved_online_label: 'Now online',
  event_status_cancelled_label: 'Called off',
  event_attendance_offline_label: 'At the venue',
  event_attendance_online_label: 'Join online',
  event_attendance_mixed_label: 'Venue and online',
}

const paginationStub = {
  props: [
    'modelValue',
    'length',
    'ariaLabel',
    'pageAriaLabel',
    'currentPageAriaLabel',
    'previousAriaLabel',
    'nextAriaLabel',
  ],
  emits: ['update:modelValue'],
  template: '<button type="button" data-test="pagination" @click="$emit(\'update:modelValue\', 2)">{{ ariaLabel }}</button>',
}

const managedEvent = {
  id: 1,
  title: 'Community day',
  sub_title: 'Meet local volunteers.',
  slug: 'community-day',
  description: '<p>Everyone is welcome.</p>',
  image_url: '/storage/community-day.jpg',
  image_alt: 'Volunteers welcoming families at the community day',
  content_kind: 'event',
  event_start_at: '2026-09-10T04:00:00.000Z',
  event_end_at: '2026-09-10T08:00:00.000Z',
  event_status: 'moved-online',
  event_attendance_mode: 'mixed',
  location: 'Dhaka Community Centre',
}

function mountPublic(component, props, url = '/events') {
  usePage().props = {
    ...props,
    siteSettings: {
      content_archives: settings,
      regional,
    },
  }
  usePage().url = url

  return mount(component, {
    global: {
      mocks: { route: globalThis.route },
      stubs: { App: layoutStub, Layout: layoutStub, 'v-pagination': paginationStub },
    },
  })
}

describe('managed public event presentation', () => {
  beforeEach(() => {
    vi.stubGlobal('route', vi.fn((name, slug) => `/${name}/${slug || ''}`))
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  test('shows schedule, status, and attendance on event cards but not ordinary article cards', () => {
    const article = {
      id: 2,
      title: 'Field update',
      sub_title: 'A published story.',
      slug: 'field-update',
      content_kind: 'article',
      published_at: '2026-09-01',
    }
    const wrapper = mountPublic(Events, {
      title: 'Events & news',
      data: { items: [managedEvent, article] },
      properties: { events: 1, total_page: 1 },
    })
    const cards = wrapper.findAll('.igf-content-card')

    expect(cards).toHaveLength(2)
    expect(cards[0].get('.igf-event-facts').attributes('aria-label')).toBe('Managed event details')
    expect(cards[0].text()).toContain('Begins')
    expect(cards[0].text()).toContain('Finishes')
    expect(cards[0].text()).toContain('Now online')
    expect(cards[0].text()).toContain('Venue and online')
    expect(cards[0].findAll('time')).toHaveLength(2)
    expect(cards[0].get('img').attributes('alt')).toBe(managedEvent.image_alt)
    expect(cards[1].find('.igf-event-facts').exists()).toBe(false)

    wrapper.unmount()
  })

  test('uses the selected archive copy and accessible pagination labels', () => {
    const article = {
      id: 2,
      title: 'Field update',
      sub_title: 'A published story.',
      slug: 'field-update',
      content_kind: 'article',
    }
    const wrapper = mountPublic(Events, {
      title: 'Fallback title',
      archive_kind: 'article',
      archive_presentation: {
        title: 'Latest field news',
        introduction: 'Fresh reporting from our programs.',
        listing_label: 'Published field news',
        empty_title: 'No field news yet',
        empty_body: 'Please check again soon.',
        card_eyebrow: 'News',
      },
      data: { items: [article] },
      properties: { events: 1, total_page: 2 },
    })

    expect(wrapper.get('.igf-events__hero h1').text()).toBe('Latest field news')
    expect(wrapper.get('.igf-events__hero span').text()).toBe('Fresh reporting from our programs.')
    expect(wrapper.get('.igf-events__content').attributes('aria-label')).toBe('Published field news')
    expect(wrapper.get('.igf-content-card__eyebrow').text()).toBe('News')

    const pagination = wrapper.getComponent(paginationStub)
    expect(pagination.props()).toMatchObject({
      ariaLabel: settings.events_pagination_label,
      pageAriaLabel: settings.events_pagination_page_label,
      currentPageAriaLabel: settings.events_pagination_current_label,
      previousAriaLabel: settings.events_pagination_previous_label,
      nextAriaLabel: settings.events_pagination_next_label,
    })
    wrapper.unmount()

    const emptyWrapper = mountPublic(Events, {
      title: 'Fallback title',
      archive_kind: 'article',
      archive_presentation: {
        empty_title: 'No field news yet',
        empty_body: 'Please check again soon.',
      },
      data: { items: [] },
      properties: { events: 1, total_page: 1 },
    })

    expect(emptyWrapper.get('.igf-events__empty h2').text()).toBe('No field news yet')
    expect(emptyWrapper.get('.igf-events__empty p').text()).toBe('Please check again soon.')
    emptyWrapper.unmount()
  })

  test('keeps the configured locale query and fixed route when changing news pages', async () => {
    const get = vi.spyOn(router, 'get').mockImplementation(() => {})
    const wrapper = mountPublic(Events, {
      title: 'Latest news',
      archive_kind: 'article',
      archive_route: 'frontend.news',
      archive_presentation: {},
      seoLocale: { query_parameter: 'locale' },
      data: { items: [{ id: 2, title: 'News', slug: 'news', content_kind: 'article' }] },
      properties: { events: 1, total_page: 2 },
    }, '/news?locale=bn')

    await wrapper.get('[data-test="pagination"]').trigger('click')

    expect(get).toHaveBeenCalledWith('/frontend.news/', {
      page: 2,
      locale: 'bn',
    }, { preserveState: true, preserveScroll: true })
    wrapper.unmount()
  })

  test('shows the same accessible managed facts on an event detail page', () => {
    const wrapper = mountPublic(EventDetail, {
      archive_route: 'frontend.events',
      archive_url: '/events',
      archive_presentation: { title: 'All events' },
      data: { event: managedEvent },
    })
    const facts = wrapper.get('.igf-event__schedule .igf-event-facts')

    expect(facts.attributes('aria-label')).toBe('Managed event details')
    expect(facts.text()).toContain('Current status')
    expect(facts.text()).toContain('Now online')
    expect(facts.text()).toContain('How to attend')
    expect(facts.text()).toContain('Venue and online')
    expect(facts.findAll('time').map(item => item.attributes('datetime'))).toEqual([
      managedEvent.event_start_at,
      managedEvent.event_end_at,
    ])
    expect(wrapper.get('.igf-event__image img').attributes('alt')).toBe(managedEvent.image_alt)
    expect(wrapper.get('.igf-event__hero a').attributes('href')).toBe('/events')
    expect(wrapper.get('.igf-event__hero a').text()).toContain('All events')
    expect(wrapper.get('.igf-event__article footer a').attributes('href')).toBe('/events')

    wrapper.unmount()
  })

  test.each([
    ['scheduled', 'Going ahead'],
    ['postponed', 'Delayed'],
    ['rescheduled', 'New date'],
    ['moved-online', 'Now online'],
    ['cancelled', 'Called off'],
  ])('uses the managed label for the %s event status', (status, expected) => {
    const wrapper = mount(EventFacts, {
      props: { event: { ...managedEvent, event_status: status }, settings, regional },
    })

    expect(wrapper.get('.igf-event-facts__item--status dd').text()).toBe(expected)
    wrapper.unmount()
  })

  test.each([
    ['offline', 'At the venue'],
    ['online', 'Join online'],
    ['mixed', 'Venue and online'],
  ])('uses the managed label for %s attendance', (mode, expected) => {
    const wrapper = mount(EventFacts, {
      props: { event: { ...managedEvent, event_attendance_mode: mode }, settings, regional },
    })

    expect(wrapper.get('.igf-event-facts__item--attendance dd').text()).toBe(expected)
    wrapper.unmount()
  })

  test('keeps an ordinary news detail as an article with its publication date', () => {
    const wrapper = mountPublic(EventDetail, {
      archive_route: 'frontend.news',
      archive_url: '/news',
      archive_presentation: { title: 'Latest news' },
      data: {
        event: {
          title: 'Field update',
          content_kind: 'article',
          published_at: '2026-09-01',
          image_url: '/storage/field-update.jpg',
          description: '<p>A community story.</p>',
        },
      },
    })

    expect(wrapper.find('.igf-event__schedule').exists()).toBe(false)
    expect(wrapper.get('.igf-event__meta').text()).toContain('1 September 2026')
    expect(wrapper.get('.igf-event__image img').attributes('alt')).toBe('Field update')
    expect(wrapper.get('.igf-event__hero a').attributes('href')).toBe('/news')
    expect(wrapper.get('.igf-event__hero a').text()).toContain('Latest news')
    expect(wrapper.get('.igf-event__article footer a').attributes('href')).toBe('/news')
    wrapper.unmount()
  })
})
