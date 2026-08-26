import {
  clearHiddenAnswers,
  conditionMatches,
  fieldResponseKey,
  groupedConditionsMatch,
  isAnswerBlank,
  visibleSchemaFields,
} from '@/Shared/forms/schemaConditions'

const fields = [
  { uuid: 'name-uuid', key: 'applicant_name', type: 'short_text' },
  { uuid: 'country-uuid', key: 'country', type: 'select' },
  { uuid: 'skills-uuid', key: 'skills', type: 'checkboxes' },
  { uuid: 'details-uuid', key: 'details', type: 'long_text', condition: { field_uuid: 'country-uuid', operator: 'equals', value: 'bd' } },
]

describe('schema conditions', () => {
  test('uses fixed keys for core fields and UUIDs for configurable questions', () => {
    expect(fieldResponseKey(fields[0])).toBe('applicant_name')
    expect(fieldResponseKey(fields[1])).toBe('country-uuid')
    expect(fieldResponseKey({ key: 'custom-without-uuid' })).toBe('')
  })

  test.each([
    [null, true],
    [undefined, true],
    ['', true],
    ['  ', true],
    [[], true],
    [0, false],
    [false, false],
    ['no', false],
  ])('recognizes blank answers %#', (value, expected) => {
    expect(isAnswerBlank(value)).toBe(expected)
  })

  test.each([
    [{ field: 'country', operator: 'equals', value: 'BD' }, true],
    [{ field: 'country-uuid', operator: 'not_equals', value: 'us' }, true],
    [{ field: 'skills', operator: 'contains', value: 'writing' }, true],
    [{ field: 'skills-uuid', operator: 'not_contains', value: 'finance' }, true],
    [{ field: 'country', operator: 'in', values: ['us', 'bd'] }, true],
    [{ field: 'country', operator: 'not_in', values: ['uk', 'us'] }, true],
    [{ field: 'country', operator: 'answered' }, true],
    [{ field: 'missing', operator: 'not_answered' }, true],
    [{ field: 'accepted', operator: 'truthy' }, true],
    [{ field: 'declined', operator: 'falsy' }, true],
    [{ field: 'score', operator: 'greater_than', value: 2 }, true],
    [{ source_key: 'score', operator: 'greater_or_equal', value: 3 }, true],
    [{ source_key: 'score', operator: 'less_or_equal', value: 3 }, true],
    [{ source_key: 'country-uuid', operator: 'is_not_empty' }, true],
    [{ field: 'score', operator: 'less_than_or_equal', value: 3 }, true],
    [{ field: 'missing', operator: 'greater_than', value: -1 }, false],
    [{ field: 'country', operator: 'equals', value: 'us' }, false],
  ])('evaluates operator %#', (condition, expected) => {
    const answers = { 'country-uuid': 'bd', 'skills-uuid': ['writing', 'training'], accepted: 'yes', declined: 'no', score: 3 }
    expect(conditionMatches(condition, answers, fields)).toBe(expected)
  })

  test('supports nested all, any, and rules groups', () => {
    const answers = { 'country-uuid': 'bd', 'skills-uuid': ['writing'] }
    expect(conditionMatches({ all: [
      { field: 'country', value: 'bd' },
      { any: [{ field: 'skills', operator: 'contains', value: 'writing' }, { field: 'skills', operator: 'contains', value: 'finance' }] },
    ] }, answers, fields)).toBe(true)
    expect(conditionMatches({ logic: 'any', rules: [
      { field: 'country', value: 'us' },
      { field: 'country', value: 'bd' },
    ] }, answers, fields)).toBe(true)
  })

  test('reduces backend conditions in order inside a group', () => {
    const conditions = [
      { source_key: 'country-uuid', group: 1, connector: 'and', operator: 'equals', value: 'us' },
      { source_key: 'skills-uuid', group: 1, connector: 'or', operator: 'contains', value: 'writing' },
      { source_key: 'score', group: 1, connector: 'and', operator: 'greater_than', value: 3 },
    ]

    expect(groupedConditionsMatch(conditions, {
      'country-uuid': 'bd',
      'skills-uuid': ['writing'],
      score: 2,
    }, fields)).toBe(false)

    expect(groupedConditionsMatch(conditions, {
      'country-uuid': 'bd',
      'skills-uuid': ['writing'],
      score: 4,
    }, fields)).toBe(true)
  })

  test('treats numeric condition groups as alternatives and prefers them to the legacy condition', () => {
    const conditional = {
      uuid: 'grouped-uuid',
      key: 'grouped',
      conditions: [
        { source_key: 'country-uuid', group: 1, connector: 'and', operator: 'equals', value: 'us' },
        { source_key: 'skills-uuid', group: 1, connector: 'and', operator: 'contains', value: 'finance' },
        { source_key: 'country-uuid', group: 2, connector: 'and', operator: 'equals', value: 'bd' },
      ],
      condition: { field: 'country-uuid', operator: 'equals', value: 'us' },
    }

    const visible = visibleSchemaFields([...fields, conditional], {
      'country-uuid': 'bd',
      'skills-uuid': ['writing'],
    })
    expect(visible).toContain(conditional)
  })

  test('filters visible fields and clears hidden answers, including cascading dependants', () => {
    const cascading = [
      ...fields,
      { uuid: 'follow-up', key: 'follow_up', condition: { field: 'details-uuid', operator: 'answered' } },
    ]
    const visible = visibleSchemaFields(cascading, { 'country-uuid': 'bd', 'details-uuid': 'Available', 'follow-up': 'Call me' })
    expect(visible.map(field => field.uuid)).toContain('details-uuid')

    const cleaned = clearHiddenAnswers(cascading, { 'country-uuid': 'us', 'details-uuid': 'Stale', 'follow-up': 'Also stale' })
    expect(cleaned).toEqual({ 'country-uuid': 'us' })
  })

  test('clears answers hidden by backend grouped conditions', () => {
    const conditional = {
      uuid: 'grouped-uuid',
      key: 'grouped',
      conditions: [
        { source_key: 'country-uuid', group: 1, connector: 'and', operator: 'equals', value: 'bd' },
        { source_key: 'skills-uuid', group: 1, connector: 'and', operator: 'contains', value: 'training' },
      ],
    }

    expect(clearHiddenAnswers([...fields, conditional], {
      'country-uuid': 'bd',
      'skills-uuid': ['writing'],
      'grouped-uuid': 'Stale',
    })).toEqual({
      'country-uuid': 'bd',
      'skills-uuid': ['writing'],
    })
  })
})
