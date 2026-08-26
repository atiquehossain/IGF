import { mount } from '@vue/test-utils'
import FormErrorSummary from '@/Shared/forms/FormErrorSummary.vue'

describe('FormErrorSummary', () => {
  test('links fixed and dynamic validation errors to their controls', () => {
    const wrapper = mount(FormErrorSummary, {
      props: {
        fields: [
          { uuid: 'fixed-email', key: 'email', label: 'Email' },
          { uuid: 'question-uuid', key: 'custom', label: 'Why apply?' },
        ],
        errors: { email: 'Email is required.', responses: { 'question-uuid': ['Answer this question.'] } },
      },
    })

    const links = wrapper.findAll('a')
    expect(links).toHaveLength(2)
    expect(links[0].attributes('href')).toBe('#field-fixed-email')
    expect(links[1].attributes('href')).toBe('#field-question-uuid')
  })

  test('does not render an empty alert', () => {
    const wrapper = mount(FormErrorSummary, { props: { fields: [], errors: {} } })
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  test('announces a global submission failure without linking to a nonexistent field', () => {
    const wrapper = mount(FormErrorSummary, {
      props: {
        fields: [{ uuid: 'fixed-email', key: 'email', label: 'Email' }],
        errors: { submission: 'Refresh the page and try again.' },
        copy: { submission_label: 'Application' },
      },
    })

    expect(wrapper.get('[role="alert"]').text()).toContain('Application: Refresh the page and try again.')
    expect(wrapper.find('a').exists()).toBe(false)
  })
})
