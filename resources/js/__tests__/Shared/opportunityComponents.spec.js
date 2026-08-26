import { mount } from '@vue/test-utils'
import OpportunityCard from '@/Shared/opportunity-card.vue'
import SubmissionReference from '@/Shared/forms/SubmissionReference.vue'

describe('public opportunity components', () => {
  test('renders an accessible job card with structured metadata', () => {
    const wrapper = mount(OpportunityCard, {
      props: {
        kind: 'job',
        href: '/careers/program-officer',
        listing: {
          title: 'Program Officer',
          summary: 'Lead community programs.',
          department: 'Programs',
          location: 'Dhaka',
          employment_type_label: 'Full time',
          application_deadline_label: '30 September 2026',
          status_label: 'Open',
        },
      },
    })

    expect(wrapper.get('article').exists()).toBe(true)
    expect(wrapper.get('h2 a').attributes('href')).toBe('/careers/program-officer')
    expect(wrapper.text()).toContain('Dhaka')
    expect(wrapper.text()).toContain('Full time')
    expect(wrapper.text()).toContain('30 September 2026')
    expect(wrapper.text()).toContain('Lead community programs.')
  })

  test('renders a contained workshop poster and metadata without its card summary', () => {
    const wrapper = mount(OpportunityCard, {
      props: {
        kind: 'workshop',
        href: '/workshops/community-leadership',
        showSummary: false,
        listing: {
          title: 'Community Leadership',
          summary: 'A practical leadership workshop.',
          image_url: '/storage/workshops/community-leadership.webp',
          image_alt: '',
          workshop_date_label: '12 October',
          venue: 'Dhaka',
          registration_deadline_label: '8 October',
        },
      },
    })

    const poster = wrapper.get('.igf-opportunity-card__media img')
    expect(poster.attributes('src')).toBe('/storage/workshops/community-leadership.webp')
    expect(poster.attributes('alt')).toBe('')
    expect(poster.attributes('loading')).toBe('lazy')
    expect(poster.attributes('decoding')).toBe('async')
    expect(wrapper.text()).toContain('12 October')
    expect(wrapper.text()).toContain('Dhaka')
    expect(wrapper.text()).not.toContain('A practical leadership workshop.')
    expect(wrapper.text().toLocaleLowerCase()).not.toMatch(/payment|certificate|qr/)
  })

  test('announces and focuses a submission reference', async () => {
    const wrapper = mount(SubmissionReference, {
      attachTo: document.body,
      props: { reference: 'IGF-JOB-2026-0012', copy: { title: 'Application received', reference_label: 'Save this number' } },
    })
    await wrapper.vm.$nextTick()
    expect(wrapper.get('[role="status"]').text()).toContain('IGF-JOB-2026-0012')
    expect(document.activeElement).toBe(wrapper.get('[role="status"]').element)
    wrapper.unmount()
  })

  test('explains when the latest submission replaced an earlier one', () => {
    const wrapper = mount(SubmissionReference, {
      props: {
        reference: 'IGF-JOB-2026-0012',
        updated: true,
        copy: { updated_message: 'Your application was updated.' },
      },
    })

    expect(wrapper.text()).toContain('Your application was updated.')
  })
})
