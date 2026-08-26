import {
  buildSchemaPayload,
  listingActionUrl,
  normalizeFormFields,
  withFixedFields,
} from '@/Shared/forms/schemaPayload'

describe('schema payloads', () => {
  const fields = [
    { uuid: 'fixed-name', key: 'applicant_name', type: 'short_text', required: true },
    { uuid: 'fixed-email', key: 'email', type: 'email', required: true },
    { uuid: 'motivation-uuid', key: 'motivation', type: 'long_text' },
    { uuid: 'skills-uuid', key: 'skills', type: 'checkboxes' },
    { uuid: 'conditional-uuid', key: 'conditional', condition: { field_uuid: 'skills-uuid', operator: 'contains', value: 'training' } },
  ]

  test('separates fixed identity values from UUID-keyed dynamic responses', () => {
    expect(buildSchemaPayload(fields, {
      applicant_name: 'Amina Rahman',
      email: 'amina@example.test',
      'motivation-uuid': 'Community impact',
      'skills-uuid': ['writing'],
      'conditional-uuid': 'Must not leak',
    })).toEqual({
      applicant_name: 'Amina Rahman',
      email: 'amina@example.test',
      responses: {
        'motivation-uuid': 'Community impact',
        'skills-uuid': ['writing'],
      },
    })
  })

  test('preserves File objects and normalizes empty checkbox answers', () => {
    const file = new File(['pdf'], 'cv.pdf', { type: 'application/pdf' })
    const payload = buildSchemaPayload([
      { uuid: 'fixed-cv', key: 'cv', type: 'file' },
      { uuid: 'choices', key: 'choices', type: 'checkboxes' },
    ], { cv: file })

    expect(payload.cv).toBe(file)
    expect(payload.responses.choices).toEqual([])
  })

  test('normalizes form containers and adds only missing fixed fields', () => {
    expect(normalizeFormFields(fields)).toBe(fields)
    expect(normalizeFormFields({ fields })).toBe(fields)
    expect(normalizeFormFields({ questions: fields })).toBe(fields)
    expect(normalizeFormFields({})).toEqual([])

    const result = withFixedFields(fields, [
      { uuid: 'other-name', key: 'applicant_name' },
      { uuid: 'fixed-phone', key: 'phone' },
    ])
    expect(result.filter(field => field.key === 'applicant_name')).toHaveLength(1)
    expect(result.at(-1).key).toBe('phone')
  })

  test('builds a submission URL from a trusted listing URL without retaining query or fragment data', () => {
    expect(listingActionUrl('https://igf.test/careers/program-officer/?lang=en#form', '/apply/'))
      .toBe('https://igf.test/careers/program-officer/apply')
    expect(listingActionUrl('/workshops/leadership', 'register')).toBe('/workshops/leadership/register')
    expect(listingActionUrl('', 'apply')).toBe('')
  })
})
