import {
  FIXED_RESPONSE_KEYS,
  clearHiddenAnswers,
  fieldResponseKey,
  visibleSchemaFields,
} from './schemaConditions'

export function normalizeFormFields(form) {
  if (Array.isArray(form)) return form
  if (Array.isArray(form?.fields)) return form.fields
  if (Array.isArray(form?.questions)) return form.questions

  return []
}

export function listingActionUrl(publicUrl, action) {
  const base = String(publicUrl || '').trim().split(/[?#]/, 1)[0].replace(/\/+$/, '')
  const suffix = String(action || '').trim().replace(/^\/+|\/+$/g, '')

  return base && suffix ? `${base}/${suffix}` : ''
}

export function withFixedFields(fields = [], definitions = []) {
  const normalized = [...fields]
  const existing = new Set(normalized.map(field => String(field?.key || '').trim()).filter(Boolean))

  definitions.forEach(field => {
    if (!existing.has(field.key)) normalized.push(field)
  })

  return normalized
}

function normalizedAnswer(field, value) {
  const type = String(field?.type || '').toLocaleLowerCase()
  if (['checkbox', 'checkboxes', 'multi_select'].includes(type)) {
    return Array.isArray(value) ? value : (value === undefined || value === null || value === '' ? [] : [value])
  }

  return value ?? ''
}

export function buildSchemaPayload(fields = [], answers = {}) {
  const cleaned = clearHiddenAnswers(fields, answers)
  const payload = { responses: {} }

  visibleSchemaFields(fields, cleaned).forEach(field => {
    const responseKey = fieldResponseKey(field)
    if (!responseKey) return

    const value = normalizedAnswer(field, cleaned[responseKey])
    if (FIXED_RESPONSE_KEYS.includes(String(field.key || '').trim())) {
      payload[field.key] = value
      return
    }

    payload.responses[responseKey] = value
  })

  return payload
}
