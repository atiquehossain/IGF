import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import SchemaForm from '@/Shared/forms/SchemaForm.vue'

const dynamicField = (type, overrides = {}) => ({
  uuid: `${type}-uuid`,
  key: `custom_${type}`,
  type,
  label: `${type} field`,
  required: false,
  validation: {},
  ...overrides,
})

describe('SchemaForm', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  test('renders visible schema fields and submits clean answers', async () => {
    const fields = [
      { uuid: 'fixed-name', key: 'applicant_name', type: 'short_text', label: 'Full name', required: true },
      dynamicField('long_text', { uuid: 'motivation', label: 'Motivation', required: true }),
    ]
    const answers = { applicant_name: 'Rafi Ahmed', motivation: 'I want to contribute.' }
    const wrapper = mount(SchemaForm, { props: { fields, modelValue: answers, submitLabel: 'Apply now' } })

    expect(wrapper.findAll('.igf-schema-field')).toHaveLength(2)
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('submit')[0][0]).toEqual(answers)
    expect(wrapper.emitted('submit')[0][1]).toEqual({
      form_token: '',
      honeypot_name: 'company_website',
      honeypot: '',
    })
  })

  test('renders and emits the signed token and named honeypot without exposing it to assistive technology', async () => {
    const wrapper = mount(SchemaForm, {
      props: {
        fields: [dynamicField('short_text')],
        modelValue: { 'short_text-uuid': 'Answer' },
        formToken: 'signed-form-token',
        honeypotName: 'website_check',
      },
    })

    expect(wrapper.get('input[type="hidden"][name="form_token"]').attributes('value')).toBe('signed-form-token')
    const trap = wrapper.get('#opportunity-company-website')
    expect(trap.attributes('name')).toBe('website_check')
    expect(trap.attributes()).toMatchObject({ tabindex: '-1', autocomplete: 'off' })
    expect(trap.element.closest('[aria-hidden="true"]')).not.toBeNull()
    await trap.setValue('')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('submit')[0][1]).toEqual({
      form_token: 'signed-form-token',
      honeypot_name: 'website_check',
      honeypot: '',
    })
  })

  test('focuses a bilingual error summary and blocks an invalid submission', async () => {
    const wrapper = mount(SchemaForm, {
      attachTo: document.body,
      props: {
        fields: [{ uuid: 'fixed-name', key: 'applicant_name', type: 'short_text', label: 'পূর্ণ নাম', required: true }],
        modelValue: {},
        copy: {
          required_message: '{field} লিখুন।',
          error_summary: { title: 'ফর্মটি যাচাই করুন', introduction: 'নিচের তথ্য ঠিক করুন।' },
        },
      },
    })

    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('submit')).toBeUndefined()
    expect(wrapper.get('[data-test="form-error-summary"]').text()).toContain('পূর্ণ নাম লিখুন।')
    expect(document.activeElement).toBe(wrapper.get('[data-test="form-error-summary"]').element)
  })

  test('uses editor-authored localized validation templates with interpolated field limits', async () => {
    const wrapper = mount(SchemaForm, {
      attachTo: document.body,
      props: {
        fields: [
          { uuid: 'fixed-name', key: 'applicant_name', type: 'short_text', label: 'পূর্ণ নাম', required: true },
          dynamicField('short_text', {
            uuid: 'experience-uuid',
            key: 'experience',
            label: 'অভিজ্ঞতা',
            validation: { min_length: 4 },
          }),
        ],
        modelValue: { 'experience-uuid': 'দুই' },
        copy: {
          required_label: 'আবশ্যক তথ্য',
          required_message: 'অনুগ্রহ করে {field} লিখুন।',
          minimum_length_message: '{field} ঘরে অন্তত {min} অক্ষর লিখুন।',
          error_summary: {
            title: 'আবেদনের তথ্য দেখুন',
            introduction: 'চিহ্নিত ঘরগুলো ঠিক করুন।',
          },
        },
      },
    })

    await wrapper.get('form').trigger('submit')

    const summary = wrapper.get('[data-test="form-error-summary"]')
    expect(wrapper.emitted('submit')).toBeUndefined()
    expect(summary.text()).toContain('আবেদনের তথ্য দেখুন')
    expect(summary.text()).toContain('চিহ্নিত ঘরগুলো ঠিক করুন।')
    expect(summary.text()).toContain('অনুগ্রহ করে পূর্ণ নাম লিখুন।')
    expect(summary.text()).toContain('অভিজ্ঞতা ঘরে অন্তত 4 অক্ষর লিখুন।')
    expect(wrapper.get('.sr-only').text()).toBe('আবশ্যক তথ্য')
  })

  test.each([
    [dynamicField('email', { key: 'email', uuid: 'fixed-email' }), 'invalid', false],
    [dynamicField('email', { key: 'email', uuid: 'fixed-email' }), 'valid@example.test', true],
    [dynamicField('number', { validation: { min: 3, max: 5 } }), 2, false],
    [dynamicField('number', { validation: { min: 3, max: 5 } }), 4, true],
    [dynamicField('date'), 'not-a-date', false],
    [dynamicField('date'), '2026-09-20', true],
    [dynamicField('short_text', { validation: { min_length: 3, max_length: 5 } }), 'ab', false],
    [dynamicField('short_text', { validation: { min_length: 3, max_length: 5 } }), 'abcd', true],
    [dynamicField('short_text', { validation: { pattern: '^IGF-[0-9]+$' } }), 'wrong', false],
    [dynamicField('short_text', { validation: { pattern: '^IGF-[0-9]+$' } }), 'IGF-42', true],
    [dynamicField('checkboxes', { validation: { min_selections: 2, max_selections: 3 } }), ['one'], false],
    [dynamicField('checkboxes', { validation: { min_selections: 2, max_selections: 3 } }), ['one', 'two'], true],
  ])('validates field rule case %#', (field, answer, expected) => {
    const key = field.key === 'email' ? 'email' : field.uuid
    const wrapper = mount(SchemaForm, { props: { fields: [field], modelValue: { [key]: answer } } })
    expect(wrapper.vm.validate()).toBe(expected)
  })

  test('requires a real PDF CV of no more than 5 MB', () => {
    const cvField = { uuid: 'fixed-cv', key: 'cv', type: 'file', label: 'CV', required: false }
    const missing = mount(SchemaForm, { props: { fields: [cvField], modelValue: {}, requireCv: true } })
    expect(missing.vm.validate()).toBe(false)

    const wrongType = new File(['hello'], 'resume.txt', { type: 'text/plain' })
    const wrong = mount(SchemaForm, { props: { fields: [cvField], modelValue: { cv: wrongType }, requireCv: true } })
    expect(wrong.vm.validate()).toBe(false)

    const tooLarge = new File([new Uint8Array((5 * 1024 * 1024) + 1)], 'resume.pdf', { type: 'application/pdf' })
    const large = mount(SchemaForm, { props: { fields: [cvField], modelValue: { cv: tooLarge }, requireCv: true } })
    expect(large.vm.validate()).toBe(false)

    const atLimit = new File([new Uint8Array(5 * 1024 * 1024)], 'resume.pdf', { type: 'application/pdf' })
    const exact = mount(SchemaForm, { props: { fields: [cvField], modelValue: { cv: atLimit }, requireCv: true } })
    expect(exact.vm.validate()).toBe(true)

    const valid = new File(['%PDF-1.7'], 'resume.pdf', { type: 'application/pdf' })
    const accepted = mount(SchemaForm, { props: { fields: [cvField], modelValue: { cv: valid }, requireCv: true } })
    expect(accepted.vm.validate()).toBe(true)
  })

  test('enforces backend extensions and max_kb file settings', async () => {
    const upload = dynamicField('file', { validation: { extensions: ['pdf'], max_kb: 1 } })

    const wrongExtension = mount(SchemaForm, {
      props: { fields: [upload], modelValue: { 'file-uuid': new File(['text'], 'notes.txt', { type: 'text/plain' }) } },
    })
    expect(wrongExtension.vm.validate()).toBe(false)

    const tooLarge = mount(SchemaForm, {
      props: { fields: [upload], modelValue: { 'file-uuid': new File([new Uint8Array(1025)], 'document.pdf', { type: 'application/pdf' }) } },
    })
    expect(tooLarge.vm.validate()).toBe(false)
    await nextTick()
    expect(tooLarge.text()).toContain('1 KB')

    const valid = mount(SchemaForm, {
      props: { fields: [upload], modelValue: { 'file-uuid': new File([new Uint8Array(1024)], 'document.pdf', { type: 'application/pdf' }) } },
    })
    expect(valid.vm.validate()).toBe(true)
  })

  test('clears a conditional answer as soon as its controlling answer changes', async () => {
    const fields = [
      dynamicField('select', { uuid: 'country', options: [{ value: 'bd', label: 'Bangladesh' }, { value: 'us', label: 'United States' }] }),
      dynamicField('long_text', { uuid: 'district', condition: { field_uuid: 'country', value: 'bd' } }),
    ]
    const wrapper = mount(SchemaForm, { props: { fields, modelValue: { country: 'bd', district: 'Dhaka' } } })
    await wrapper.setProps({ modelValue: { country: 'us', district: 'Dhaka' } })
    await nextTick()

    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([{ country: 'us' }])
    expect(wrapper.text()).not.toContain('long_text field')
  })

  test('maps nested server validation errors and moves focus to the summary', async () => {
    const wrapper = mount(SchemaForm, {
      attachTo: document.body,
      props: { fields: [dynamicField('short_text', { uuid: 'question-1', label: 'Experience' })], modelValue: {} },
    })
    await wrapper.setProps({ errors: { 'responses.question-1': 'Tell us about your experience.' } })
    await nextTick()

    expect(wrapper.get('[data-test="form-error-summary"]').text()).toContain('Tell us about your experience.')
    expect(wrapper.get('input').attributes('aria-invalid')).toBe('true')
    expect(document.activeElement).toBe(wrapper.get('[data-test="form-error-summary"]').element)
  })

  test('disables all controls while processing and announces progress', () => {
    const wrapper = mount(SchemaForm, { props: { fields: [dynamicField('short_text')], modelValue: {}, processing: true } })
    expect(wrapper.get('form').attributes('aria-busy')).toBe('true')
    expect(wrapper.get('input').attributes()).toHaveProperty('disabled')
    expect(wrapper.get('button[type="submit"]').attributes()).toHaveProperty('disabled')
    expect(wrapper.get('[role="status"]').text()).toContain('Submitting')
  })
})
