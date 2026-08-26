import { mount } from '@vue/test-utils'
import SchemaField from '@/Shared/forms/SchemaField.vue'

const field = (type, overrides = {}) => ({
  uuid: `${type}-uuid`,
  key: `custom_${type}`,
  type,
  label: `${type} question`,
  help: `${type} guidance`,
  required: true,
  options: [{ value: 'one', label: 'Option one' }, { value: 'two', label: 'Option two' }],
  validation: {},
  ...overrides,
})

describe('SchemaField', () => {
  test.each([
    ['short_text', 'input[type="text"]'],
    ['long_text', 'textarea'],
    ['email', 'input[type="email"]'],
    ['phone', 'input[type="tel"]'],
    ['number', 'input[type="number"]'],
    ['date', 'input[type="date"]'],
    ['select', 'select'],
    ['file', 'input[type="file"]'],
  ])('renders %s with a native control', (type, selector) => {
    const wrapper = mount(SchemaField, { props: { field: field(type) } })
    expect(wrapper.get(selector).exists()).toBe(true)
    expect(wrapper.text()).toContain(`${type} guidance`)
  })

  test.each([
    ['radio', 'radio', 2],
    ['multiple_choice', 'radio', 2],
    ['checkboxes', 'checkbox', 2],
    ['yes_no', 'radio', 2],
  ])('renders %s as an accessible fieldset', (type, inputType, count) => {
    const overrides = type === 'yes_no' ? { options: [] } : {}
    const wrapper = mount(SchemaField, { props: { field: field(type, overrides), copy: { yes_label: 'হ্যাঁ', no_label: 'না' } } })
    expect(wrapper.get('fieldset').attributes('aria-describedby')).toContain('-help')
    expect(wrapper.findAll(`input[type="${inputType}"]`)).toHaveLength(count)
    if (type === 'yes_no') expect(wrapper.text()).toContain('হ্যাঁ')
  })

  test('wires errors, required state, and descriptions to the control', () => {
    const wrapper = mount(SchemaField, { props: { field: field('short_text'), errors: ['বাংলায় ত্রুটি'] } })
    const input = wrapper.get('input')
    expect(input.attributes()).toMatchObject({ required: '', 'aria-invalid': 'true' })
    expect(input.attributes('aria-describedby')).toContain('-help')
    expect(input.attributes('aria-describedby')).toContain('-error')
    expect(wrapper.text()).toContain('বাংলায় ত্রুটি')
  })

  test('emits scalar and checkbox values', async () => {
    const text = mount(SchemaField, { props: { field: field('short_text') } })
    await text.get('input').setValue('Answer')
    expect(text.emitted('update:modelValue').at(-1)).toEqual(['Answer'])

    const checkboxes = mount(SchemaField, { props: { field: field('checkboxes'), modelValue: ['one'] } })
    await checkboxes.findAll('input')[1].setValue(true)
    expect(checkboxes.emitted('update:modelValue').at(-1)).toEqual([['one', 'two']])
  })

  test('enforces the CV file picker contract and emits the selected file', async () => {
    const wrapper = mount(SchemaField, { props: { field: field('file', { key: 'cv', label: 'CV' }) } })
    const input = wrapper.get('input[type="file"]')
    expect(input.attributes('accept')).toBe('.pdf,application/pdf')
    const cv = new File(['resume'], 'resume.pdf', { type: 'application/pdf' })
    Object.defineProperty(input.element, 'files', { configurable: true, value: [cv] })
    await input.trigger('change')
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([cv])
  })

  test('translates backend file extensions into a native accept filter', () => {
    const wrapper = mount(SchemaField, {
      props: { field: field('file', { validation: { extensions: ['pdf', '.docx'], max_kb: 5120 } }) },
    })

    expect(wrapper.get('input[type="file"]').attributes('accept')).toBe('.pdf,.docx')
  })
})
