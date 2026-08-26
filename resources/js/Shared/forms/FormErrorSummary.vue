<template>
  <section
    v-if="entries.length"
    ref="summary"
    class="igf-form-errors"
    role="alert"
    aria-live="assertive"
    tabindex="-1"
    data-test="form-error-summary"
  >
    <h3>{{ copy.title || 'Please check the form' }}</h3>
    <p v-if="copy.introduction">{{ copy.introduction }}</p>
    <ul>
      <li v-for="entry in entries" :key="entry.key">
        <a v-if="entry.target" :href="`#${entry.target}`">{{ entry.label }}: {{ entry.message }}</a>
        <span v-else>{{ entry.label }}: {{ entry.message }}</span>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { fieldResponseKey } from './schemaConditions'

const props = defineProps({
  errors: { type: Object, default: () => ({}) },
  fields: { type: Array, default: () => [] },
  copy: { type: Object, default: () => ({}) },
})

const summary = ref(null)

function fieldDomId(field) {
  const identity = String(field.uuid || field.key || 'question').replace(/[^A-Za-z0-9_-]/g, '-')
  return `field-${identity}`
}

function firstMessage(value) {
  if (Array.isArray(value)) return String(value[0] || '')
  if (value && typeof value === 'object') return firstMessage(Object.values(value)[0])
  return value === null || value === undefined ? '' : String(value)
}

function errorFor(field) {
  const key = fieldResponseKey(field)
  const candidates = [
    props.errors[key],
    props.errors[`responses.${key}`],
    props.errors.responses?.[key],
  ]

  return firstMessage(candidates.find(value => firstMessage(value) !== ''))
}

const entries = computed(() => props.fields.map(field => ({
  key: fieldResponseKey(field),
  target: fieldDomId(field),
  label: field.label || props.copy.question_label || 'Question',
  message: errorFor(field),
})).filter(entry => entry.key && entry.message).concat(globalErrors.value))

const globalErrors = computed(() => {
  const fieldKeys = new Set()
  props.fields.forEach(field => {
    const key = fieldResponseKey(field)
    fieldKeys.add(key)
    fieldKeys.add(`responses.${key}`)
  })

  return flattenErrors(props.errors)
    .filter(entry => !fieldKeys.has(entry.key))
    .map(entry => ({
      key: `global-${entry.key}`,
      target: '',
      label: entry.key === 'submission'
        ? (props.copy.submission_label || 'Submission')
        : (props.copy.general_label || 'Form'),
      message: entry.message,
    }))
})

function flattenErrors(errors, prefix = '') {
  return Object.entries(errors || {}).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    if (value && typeof value === 'object' && !Array.isArray(value)) return flattenErrors(value, path)

    const message = firstMessage(value)
    return message ? [{ key: path, message }] : []
  })
}

function focus() {
  summary.value?.focus()
}

defineExpose({ focus })
</script>

<style scoped>
.igf-form-errors{margin:0 0 24px;padding:18px 20px;border:1px solid #d89a93;border-left:5px solid #a52c24;border-radius:10px;background:#fff5f3;color:#76251f}.igf-form-errors:focus{outline:3px solid rgba(165,44,36,.25);outline-offset:3px}.igf-form-errors h3{margin:0 0 6px;font:700 20px/1.3 'Literata',Georgia,serif}.igf-form-errors p{margin:0 0 9px}.igf-form-errors ul{margin:8px 0 0;padding-left:21px}.igf-form-errors li+li{margin-top:5px}.igf-form-errors a{color:#76251f;font-weight:700;text-underline-offset:3px}
</style>
