const EMPTY_VALUES = new Set([null, undefined, ''])

export const FIXED_RESPONSE_KEYS = Object.freeze([
  'applicant_name',
  'email',
  'phone',
  'cv',
])

export function fieldResponseKey(field = {}) {
  const key = String(field.key || '').trim()

  if (FIXED_RESPONSE_KEYS.includes(key)) return key

  return String(field.uuid || '').trim()
}

export function isAnswerBlank(value) {
  if (EMPTY_VALUES.has(value)) return true
  if (Array.isArray(value)) return value.length === 0
  if (typeof value === 'string') return value.trim() === ''

  return false
}

function comparable(value) {
  if (typeof value === 'boolean') return value ? 'true' : 'false'
  if (value === null || value === undefined) return ''

  return String(value).trim().toLocaleLowerCase()
}

function sameValue(actual, expected) {
  if (Array.isArray(actual)) {
    if (!Array.isArray(expected)) return actual.some(value => comparable(value) === comparable(expected))

    const actualValues = actual.map(comparable).sort()
    const expectedValues = expected.map(comparable).sort()
    return actualValues.length === expectedValues.length
      && actualValues.every((value, index) => value === expectedValues[index])
  }

  return comparable(actual) === comparable(expected)
}

function conditionReference(condition = {}) {
  return String(
    condition.source_key
      || condition.field_uuid
      || condition.field_key
      || condition.field
      || condition.depends_on
      || condition.source
      || '',
  ).trim()
}

function answerForReference(reference, answers = {}, fields = []) {
  if (!reference) return undefined
  if (Object.prototype.hasOwnProperty.call(answers, reference)) return answers[reference]

  const source = fields.find(field => String(field.uuid || '') === reference || String(field.key || '') === reference)
  const responseKey = source ? fieldResponseKey(source) : ''

  return responseKey && Object.prototype.hasOwnProperty.call(answers, responseKey)
    ? answers[responseKey]
    : undefined
}

function expectedValues(condition = {}) {
  if (Array.isArray(condition.values)) return condition.values
  if (Array.isArray(condition.value)) return condition.value

  return [condition.value]
}

function evaluateRule(condition, answers, fields) {
  const reference = conditionReference(condition)
  if (!reference) return true

  const actual = answerForReference(reference, answers, fields)
  const values = expectedValues(condition)
  const expected = values.length > 1 ? values : values[0]
  const operator = String(condition.operator || 'equals').trim().toLocaleLowerCase()

  switch (operator) {
    case 'answered':
    case 'is_answered':
      return !isAnswerBlank(actual)
    case 'not_answered':
    case 'is_empty':
      return isAnswerBlank(actual)
    case 'is_not_empty':
      return !isAnswerBlank(actual)
    case 'truthy':
      return ['true', '1', 'yes', 'y', 'on'].includes(comparable(actual))
    case 'falsy':
      return isAnswerBlank(actual) || ['false', '0', 'no', 'n', 'off'].includes(comparable(actual))
    case 'not_equals':
    case 'does_not_equal':
      return !sameValue(actual, expected)
    case 'contains':
      if (Array.isArray(actual)) return values.every(value => actual.some(item => sameValue(item, value)))
      return values.every(value => comparable(actual).includes(comparable(value)))
    case 'not_contains':
      if (Array.isArray(actual)) return values.every(value => !actual.some(item => sameValue(item, value)))
      return values.every(value => !comparable(actual).includes(comparable(value)))
    case 'in':
    case 'one_of':
      if (Array.isArray(actual)) return actual.some(item => values.some(value => sameValue(item, value)))
      return values.some(value => sameValue(actual, value))
    case 'not_in':
    case 'none_of':
      if (Array.isArray(actual)) return actual.every(item => values.every(value => !sameValue(item, value)))
      return values.every(value => !sameValue(actual, value))
    case 'greater_than':
      return numbersAreComparable(actual, values[0]) && Number(actual) > Number(values[0])
    case 'greater_or_equal':
    case 'greater_than_or_equal':
      return numbersAreComparable(actual, values[0]) && Number(actual) >= Number(values[0])
    case 'less_than':
      return numbersAreComparable(actual, values[0]) && Number(actual) < Number(values[0])
    case 'less_or_equal':
    case 'less_than_or_equal':
      return numbersAreComparable(actual, values[0]) && Number(actual) <= Number(values[0])
    case 'equals':
    case 'is':
    default:
      return sameValue(actual, expected)
  }
}

function numbersAreComparable(actual, expected) {
  return !isAnswerBlank(actual)
    && !isAnswerBlank(expected)
    && Number.isFinite(Number(actual))
    && Number.isFinite(Number(expected))
}

export function groupedConditionsMatch(conditions = [], answers = {}, fields = []) {
  if (!Array.isArray(conditions) || conditions.length === 0) return true

  const groups = new Map()
  conditions.forEach(condition => {
    const group = Number.isInteger(Number(condition?.group)) ? Number(condition.group) : 1
    if (!groups.has(group)) groups.set(group, [])
    groups.get(group).push(condition || {})
  })

  return [...groups.values()].some(rules => {
    let result = null
    rules.forEach(rule => {
      const matched = evaluateRule(rule, answers, fields)
      if (result === null) {
        result = matched
        return
      }

      result = String(rule.connector || 'and').toLocaleLowerCase() === 'or'
        ? result || matched
        : result && matched
    })

    return result ?? true
  })
}

export function conditionMatches(condition, answers = {}, fields = []) {
  if (!condition || condition.enabled === false) return true
  if (Array.isArray(condition)) return condition.every(rule => conditionMatches(rule, answers, fields))

  const all = Array.isArray(condition.all) ? condition.all : null
  if (all) return all.every(rule => conditionMatches(rule, answers, fields))

  const any = Array.isArray(condition.any) ? condition.any : null
  if (any) return any.some(rule => conditionMatches(rule, answers, fields))

  if (Array.isArray(condition.rules)) {
    const mode = String(condition.logic || condition.match || 'all').toLocaleLowerCase()
    return mode === 'any'
      ? condition.rules.some(rule => conditionMatches(rule, answers, fields))
      : condition.rules.every(rule => conditionMatches(rule, answers, fields))
  }

  return evaluateRule(condition, answers, fields)
}

export function isFieldVisible(field, answers = {}, fields = []) {
  if (Array.isArray(field?.conditions) && field.conditions.length) {
    return groupedConditionsMatch(field.conditions, answers, fields)
  }

  return conditionMatches(field?.condition, answers, fields)
}

export function visibleSchemaFields(fields = [], answers = {}) {
  return fields.filter(field => fieldResponseKey(field) && isFieldVisible(field, answers, fields))
}

export function clearHiddenAnswers(fields = [], answers = {}) {
  const cleaned = { ...answers }
  let changed = true
  let passes = 0

  while (changed && passes <= fields.length) {
    changed = false
    passes += 1

    fields.forEach(field => {
      const key = fieldResponseKey(field)
      if (!key || isFieldVisible(field, cleaned, fields) || !Object.prototype.hasOwnProperty.call(cleaned, key)) return

      delete cleaned[key]
      changed = true
    })
  }

  return cleaned
}
