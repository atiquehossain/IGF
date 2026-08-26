import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Job from '@/Pages/job.vue'
import Workshop from '@/Pages/workshop.vue'

const layoutStub = { template: '<div><slot /></div>' }

function mountPage(component) {
  return mount(component, { global: { stubs: { Layout: layoutStub } } })
}

function setFile(wrapper, selector, file) {
  const input = wrapper.get(selector)
  Object.defineProperty(input.element, 'files', { configurable: true, value: [file] })
  return input.trigger('change')
}

describe('public job detail and application', () => {
  beforeEach(() => {
    vi.stubGlobal('route', vi.fn((name, slug) => `/${name}/${slug || ''}`))
    usePage().url = '/careers/program-officer'
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  test('submits fixed fields and UUID-keyed answers with FormData enabled', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {})
    usePage().props = {
      title: 'Program Officer', errors: {},
      data: {
        listing: {
          slug: 'program-officer',
          public_url: 'https://igf.test/careers/program-officer?lang=en#application',
          title: 'Program Officer',
          description: '<p>Lead community programs.</p>',
          responsibilities: '<ul><li>Coordinate partners.</li></ul>',
          requirements: '<p>Three years of experience.</p>',
          is_open: true,
          location: 'Dhaka',
        },
        form: { token: 'signed-job-token', honeypot_name: 'company_website', fields: [
          { uuid: 'name-fixed', key: 'applicant_name', type: 'short_text', label: 'Full name', required: true },
          { uuid: 'email-fixed', key: 'email', type: 'email', label: 'Email', required: true },
          { uuid: 'cv-fixed', key: 'cv', type: 'file', label: 'CV', required: true },
          { uuid: 'motivation-uuid', key: 'motivation', type: 'long_text', label: 'Why apply?', required: true },
        ] },
      },
    }
    const wrapper = mountPage(Job)
    expect(wrapper.get('#job-responsibilities-title').text()).toBe('Responsibilities')
    expect(wrapper.text()).toContain('Coordinate partners.')
    expect(wrapper.get('#job-requirements-title').text()).toBe('Requirements')
    expect(wrapper.text()).toContain('Three years of experience.')
    await wrapper.get('#field-name-fixed').setValue('Nusrat Jahan')
    await wrapper.get('#field-email-fixed').setValue('nusrat@example.test')
    await wrapper.get('#field-motivation-uuid').setValue('I want to serve communities.')
    const cv = new File(['%PDF-1.7'], 'nusrat-cv.pdf', { type: 'application/pdf' })
    await setFile(wrapper, '#field-cv-fixed', cv)
    await wrapper.get('form').trigger('submit')

    expect(post).toHaveBeenCalledTimes(1)
    const [url, payload, options] = post.mock.calls[0]
    expect(url).toBe('https://igf.test/careers/program-officer/apply')
    expect(payload).toMatchObject({
      applicant_name: 'Nusrat Jahan',
      email: 'nusrat@example.test',
      form_token: 'signed-job-token',
      company_website: '',
      responses: { 'motivation-uuid': 'I want to serve communities.' },
    })
    expect(payload.cv).toBe(cv)
    expect(options).toMatchObject({ forceFormData: true, preserveScroll: true })

    options.onSuccess({ props: { data: { submission_reference: 'IGF-JOB-2026-0100', submission_status_label: 'Received', submission_updated: true } } })
    options.onFinish()
    await nextTick()
    expect(wrapper.get('[data-test="submission-reference"]').text()).toContain('IGF-JOB-2026-0100')
    expect(wrapper.get('[data-test="submission-reference"]').text()).toContain('replaced the earlier one')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  test('forces a required 5 MB PDF CV field even when the schema omits it', () => {
    usePage().props = {
      data: { listing: { slug: 'designer', title: 'Designer', is_open: true }, form: { fields: [] }, copy: { cv_label: 'জীবনবৃত্তান্ত (PDF)', cv_help: 'সর্বোচ্চ ৫ এমবি।' } },
      errors: {},
    }
    const wrapper = mountPage(Job)
    const cv = wrapper.get('input[name="cv"]')
    expect(cv.attributes()).toMatchObject({ required: '', accept: '.pdf,application/pdf' })
    expect(wrapper.text()).toContain('জীবনবৃত্তান্ত (PDF)')
    expect(wrapper.text()).toContain('সর্বোচ্চ ৫ এমবি।')
  })

  test('keeps a closed job detail visible but removes the active form', () => {
    usePage().props = {
      data: { listing: { slug: 'closed-role', title: 'Closed role', description: '<p>Historical job details.</p>', state: 'closed', is_open: false }, form: { fields: [] } },
      errors: {},
    }
    const wrapper = mountPage(Job)
    expect(wrapper.get('h1').text()).toBe('Closed role')
    expect(wrapper.text()).toContain('Historical job details.')
    expect(wrapper.get('[role="status"]').text()).toContain('Applications closed')
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.get('aside').attributes('aria-labelledby')).toBe('job-closed-title')
    expect(wrapper.get('#job-closed-title').exists()).toBe(true)
  })

  test('shows an existing reference instead of allowing another immediate post', () => {
    usePage().props = {
      data: { listing: { slug: 'role', title: 'Role', is_open: true }, form: { fields: [] }, submission_reference: 'IGF-JOB-2026-0008' },
      errors: {},
    }
    const wrapper = mountPage(Job)
    expect(wrapper.get('[data-test="submission-reference"]').text()).toContain('IGF-JOB-2026-0008')
    expect(wrapper.find('form').exists()).toBe(false)
  })
})

describe('public workshop detail and registration', () => {
  beforeEach(() => {
    vi.stubGlobal('route', vi.fn((name, slug) => `/${name}/${slug || ''}`))
    usePage().url = '/workshops/leadership'
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  test('submits registration answers without adding CV or payment fields', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {})
    usePage().props = {
      errors: {},
      data: {
        listing: { slug: 'leadership', public_url: '/workshops/leadership/', title: 'Leadership Workshop', description: '<p>A free practical session.</p>', is_open: true, format_label: 'Online' },
        form: { token: 'signed-workshop-token', honeypot_name: 'company_website', fields: [
          { uuid: 'name-fixed', key: 'applicant_name', type: 'short_text', label: 'Full name', required: true },
          { uuid: 'email-fixed', key: 'email', type: 'email', label: 'Email', required: true },
          { uuid: 'experience-uuid', key: 'experience', type: 'long_text', label: 'Experience', required: true },
        ] },
      },
    }
    const wrapper = mountPage(Workshop)
    expect(wrapper.find('input[type="file"]').exists()).toBe(false)
    expect(wrapper.text().toLocaleLowerCase()).not.toMatch(/payment|certificate|feedback|qr check/)
    await wrapper.get('#field-name-fixed').setValue('Sabbir Hasan')
    await wrapper.get('#field-email-fixed').setValue('sabbir@example.test')
    await wrapper.get('#field-experience-uuid').setValue('Community organizer')
    await wrapper.get('form').trigger('submit')

    const [url, payload, options] = post.mock.calls[0]
    expect(url).toBe('/workshops/leadership/register')
    expect(payload).toMatchObject({
      applicant_name: 'Sabbir Hasan',
      email: 'sabbir@example.test',
      form_token: 'signed-workshop-token',
      company_website: '',
      responses: { 'experience-uuid': 'Community organizer' },
    })
    expect(payload).not.toHaveProperty('cv')
    expect(options.forceFormData).toBe(true)
  })

  test('renders waitlist confirmation returned by the server', () => {
    usePage().props = {
      errors: {},
      data: {
        listing: { slug: 'full-session', title: 'Full session', is_open: true },
        form: { fields: [] },
        submission_reference: 'IGF-WS-2026-0042',
        submission_status_label: 'Waiting list',
        copy: { submission: { title: 'Registration received' } },
      },
    }
    const wrapper = mountPage(Workshop)
    expect(wrapper.get('[data-test="submission-reference"]').text()).toContain('Waiting list')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  test('keeps closed workshop information visible without a registration form', () => {
    usePage().props = {
      errors: {},
      data: { listing: { slug: 'past-session', title: 'Past session', description: '<p>Workshop information remains available.</p>', is_open: false, registration_state: 'closed' }, form: { fields: [] } },
    }
    const wrapper = mountPage(Workshop)
    expect(wrapper.text()).toContain('Workshop information remains available.')
    expect(wrapper.get('[role="status"]').text()).toContain('Registration closed')
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.get('aside').attributes('aria-labelledby')).toBe('workshop-closed-title')
    expect(wrapper.get('#workshop-closed-title').exists()).toBe(true)
  })
})
