import { describe, expect, it } from 'vitest'
import {
  conditionMatches,
  escapeHtml,
  groupedConditionsMatch,
  translatedBuilderText,
} from '../../../public/admin-assets/application-form-builder/form-builder.js'

describe('admin application form builder helpers', () => {
  it('escapes staff-authored copy before inserting it into editor markup', () => {
    expect(escapeHtml('</textarea><script>alert("x")</script>'))
      .toBe('&lt;/textarea&gt;&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;')
  })

  it.each([
    [['writing', 'research'], 'contains', 'writing', true],
    [['writing'], 'not_contains', 'finance', true],
    ['  ProgrammeS ', 'equals', 'programmes', true],
    ['', 'is_empty', null, true],
    ['7', 'greater_than', 3, true],
    ['not-a-number', 'less_than', 3, false],
  ])('evaluates %s %s conditions consistently', (actual, operator, expected, result) => {
    expect(conditionMatches(actual, operator, expected)).toBe(result)
  })

  it('uses OR between groups and the stored connector within each group', () => {
    const conditions = [
      { source_key: 'country', group: 1, connector: 'and', operator: 'equals', value: 'bd' },
      { source_key: 'experience', group: 1, connector: 'and', operator: 'greater_than', value: 2 },
      { source_key: 'country', group: 2, connector: 'and', operator: 'equals', value: 'uk' },
    ]

    expect(groupedConditionsMatch(conditions, { country: 'bd', experience: 4 })).toBe(true)
    expect(groupedConditionsMatch(conditions, { country: 'uk', experience: 0 })).toBe(true)
    expect(groupedConditionsMatch(conditions, { country: 'bd', experience: 1 })).toBe(false)
  })

  it('uses the trusted server dictionary with placeholder replacement', () => {
    expect(translatedBuilderText(
      { move_up: ':name উপরে নিন' },
      'move_up',
      { name: 'অভিজ্ঞতা' },
    )).toBe('অভিজ্ঞতা উপরে নিন')
  })

  it('falls back to safe English operating copy when a dictionary entry is absent', () => {
    expect(translatedBuilderText({}, 'publish_error')).toBe('The form could not be published.')
    expect(translatedBuilderText(null, 'question_number', { number: 4 })).toBe('Question 4')
  })
})
