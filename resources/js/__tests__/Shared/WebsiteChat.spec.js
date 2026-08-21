import { flushPromises, mount } from '@vue/test-utils'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import WebsiteChat from '@/Shared/WebsiteChat.vue'

vi.mock('axios', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

const quickQuestion = {
  id: 'faq-1',
  question: 'How can I donate?',
  answer: 'Open the Donate page and follow the secure payment steps.',
}

function bootstrap(overrides = {}) {
  return {
    enabled: true,
    title: 'Chat with Ignite',
    welcome_message: 'Choose a common question or send a message.',
    privacy_message: 'The team can see the details you submit.',
    viewer: null,
    quick_questions: [quickQuestion],
    conversation: null,
    ...overrides,
  }
}

async function mountChat(payload = bootstrap()) {
  axios.get.mockResolvedValueOnce({ data: payload })
  const wrapper = mount(WebsiteChat, { attachTo: document.body })
  await flushPromises()
  await wrapper.get('.igf-chat__launcher').trigger('click')
  await flushPromises()
  return wrapper
}

describe('WebsiteChat question workflow', () => {
  beforeEach(() => {
    document.documentElement.lang = 'en'
    usePage().props = {
      appName: 'Ignite Global Foundation',
      siteSettings: {
        branding: {
          logo: '/storage/media/main-logo.png',
          logo_alt: 'Ignite Global Foundation',
        },
        regional: {
          date_locale: 'en-BD',
          timezone: 'Asia/Dhaka',
        },
      },
    }
    vi.stubGlobal('route', vi.fn(name => name))
    vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: false })))
    Object.defineProperty(HTMLElement.prototype, 'scrollTo', {
      configurable: true,
      value: vi.fn(),
    })
    axios.get.mockReset()
    axios.post.mockReset()
    axios.post.mockResolvedValue({ data: {} })
  })

  afterEach(() => {
    document.body.innerHTML = ''
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  test('uses the same admin-managed logo as the main site header', async () => {
    const wrapper = await mountChat()
    const logo = wrapper.get('.igf-chat__brand-logo')

    expect(logo.attributes('src')).toBe('/storage/media/main-logo.png')
    expect(logo.attributes('alt')).toBe('Ignite Global Foundation')

    wrapper.unmount()
  })

  test('derives avatar initials from the admin-managed site name', async () => {
    usePage().props.siteSettings.branding.site_name = 'Community Hope Network'
    const wrapper = await mountChat()

    expect(wrapper.get('.igf-chat__avatar').text()).toBe('CH')
    wrapper.unmount()
  })

  test('shows a predefined answer locally and records only anonymous click analytics', async () => {
    const intervalSpy = vi.spyOn(window, 'setInterval')
    const wrapper = await mountChat()

    expect(wrapper.find('.igf-chat__contact').exists()).toBe(false)
    expect(wrapper.get('#igf-chat-message').element.value).toBe('')

    await wrapper.get('.igf-chat__quick button').trigger('click')
    await flushPromises()

    const localAnswer = wrapper.get('.igf-chat__local-faq')
    expect(localAnswer.text()).toContain(quickQuestion.question)
    expect(localAnswer.text()).toContain(quickQuestion.answer)
    expect(wrapper.get('#igf-chat-message').element.value).toBe('')
    expect(wrapper.find('.igf-chat__contact').exists()).toBe(false)
    expect(axios.post).toHaveBeenCalledWith('chat.faqs.click', { faq_id: quickQuestion.id })
    expect(axios.post.mock.calls.some(([url]) => url === 'chat.conversations.store')).toBe(false)
    expect(axios.post.mock.calls.some(([url]) => url === 'chat.messages.store')).toBe(false)
    expect(intervalSpy).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  test('keeps a predefined answer separate from an existing support transcript', async () => {
    const intervalSpy = vi.spyOn(window, 'setInterval')
    const wrapper = await mountChat(bootstrap({
      conversation: {
        id: 'existing-conversation',
        status: 'answered',
        messages: [{
          id: 'existing-message',
          sender_type: 'admin',
          body: 'This is the existing staff reply.',
          created_at: '2026-08-19T00:00:00+06:00',
        }],
      },
    }))
    const pollingStartsBeforeClick = intervalSpy.mock.calls.length
    const transcriptBeforeClick = wrapper.get('.igf-chat__messages').text()

    await wrapper.get('.igf-chat__quick button').trigger('click')
    await flushPromises()

    expect(wrapper.get('.igf-chat__messages').text()).toBe(transcriptBeforeClick)
    expect(wrapper.get('.igf-chat__messages').text()).not.toContain(quickQuestion.question)
    expect(wrapper.get('.igf-chat__local-faq').text()).toContain(quickQuestion.answer)
    expect(intervalSpy).toHaveBeenCalledTimes(pollingStartsBeforeClick)
    expect(axios.post.mock.calls.some(([url]) => url === 'chat.conversations.store')).toBe(false)
    expect(axios.post.mock.calls.some(([url]) => url === 'chat.messages.store')).toBe(false)

    wrapper.unmount()
  })

  test('formats message times with the configured public locale and timezone', async () => {
    usePage().props.siteSettings.regional = { date_locale: 'en-US', timezone: 'UTC' }
    const createdAt = '2026-08-19T00:00:00Z'
    const wrapper = await mountChat(bootstrap({
      conversation: {
        id: 'regional-time-conversation',
        status: 'answered',
        messages: [{
          id: 'regional-time-message',
          sender_type: 'admin',
          body: 'A time-aware reply.',
          created_at: createdAt,
        }],
      },
    }))

    expect(wrapper.get('.igf-chat__messages time').text()).toBe(
      new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', timeZone: 'UTC' }).format(new Date(createdAt)),
    )
    wrapper.unmount()
  })

  test('keeps a guest custom question local until contact details are explicitly submitted', async () => {
    const wrapper = await mountChat()
    const question = 'Can someone contact me about volunteering?'

    await wrapper.get('#igf-chat-message').setValue(question)
    await wrapper.get('.igf-chat__composer').trigger('submit')
    await flushPromises()

    expect(wrapper.get('.igf-chat__contact').exists()).toBe(true)
    expect(wrapper.get('#igf-chat-message').element.value).toBe(question)
    expect(axios.post).not.toHaveBeenCalled()

    await wrapper.get('[name="chat_name"]').setValue('Guest Visitor')
    await wrapper.get('[name="chat_email"]').setValue('guest@example.com')
    axios.post.mockResolvedValueOnce({
      data: { conversation: { id: 'conversation-1', status: 'waiting', messages: [] } },
    })
    await wrapper.get('.igf-chat__composer').trigger('submit')
    await flushPromises()

    expect(axios.post).toHaveBeenCalledTimes(1)
    expect(axios.post).toHaveBeenCalledWith('chat.conversations.store', {
      name: 'Guest Visitor',
      email: 'guest@example.com',
      phone: null,
      body: question,
      page_url: `${window.location.origin}${window.location.pathname}`,
    })

    wrapper.unmount()
  })

  test('lets an approved signed-in member submit a custom question directly', async () => {
    const wrapper = await mountChat(bootstrap({
      viewer: { id: 42, name: 'Approved Member' },
    }))
    const question = 'Please update me about my application.'
    axios.post.mockResolvedValueOnce({
      data: { conversation: { id: 'conversation-2', status: 'waiting', messages: [] } },
    })

    await wrapper.get('#igf-chat-message').setValue(question)
    await wrapper.get('.igf-chat__composer').trigger('submit')
    await flushPromises()

    expect(wrapper.find('.igf-chat__contact').exists()).toBe(false)
    expect(axios.post).toHaveBeenCalledWith('chat.conversations.store', {
      body: question,
      page_url: `${window.location.origin}${window.location.pathname}`,
    })

    wrapper.unmount()
  })
})
