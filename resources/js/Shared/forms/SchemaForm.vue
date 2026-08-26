<template>
  <form class="igf-schema-form" novalidate :aria-busy="processing ? 'true' : 'false'" @submit.prevent="submit">
    <input v-if="formToken" type="hidden" name="form_token" :value="formToken">
    <div v-if="formToken" class="igf-schema-form__honeypot" aria-hidden="true">
      <label :for="honeypotId">{{ messages.honeypot_label }}</label>
      <input
        :id="honeypotId"
        v-model="honeypot"
        :name="honeypotName"
        type="text"
        tabindex="-1"
        autocomplete="off"
        :disabled="processing || disabled"
      >
    </div>
    <FormErrorSummary ref="errorSummary" :errors="combinedErrors" :fields="visibleFields" :copy="messages.error_summary" />

    <div v-if="visibleFields.length" class="igf-schema-form__grid">
      <SchemaField
        v-for="field in visibleFields"
        :key="fieldResponseKey(field)"
        :field="field"
        :model-value="answers[fieldResponseKey(field)]"
        :errors="errorsForField(field)"
        :copy="messages"
        :disabled="processing || disabled"
        @update:model-value="value => updateAnswer(field, value)"
        @blur="validateOne(field)"
      />
    </div>
    <p v-else class="igf-schema-form__empty" role="status">{{ messages.form_unavailable }}</p>

    <p v-if="privacyMessage" class="igf-schema-form__privacy">
      <span aria-hidden="true">●</span>
      {{ privacyMessage }}
    </p>

    <button v-if="visibleFields.length" class="igf-schema-form__submit" type="submit" :disabled="processing || disabled">
      <span>{{ processing ? messages.submitting_label : submitLabel }}</span>
      <span aria-hidden="true">→</span>
    </button>
    <p v-if="processing" class="sr-only" role="status" aria-live="polite">{{ messages.submitting_label }}</p>
  </form>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import FormErrorSummary from './FormErrorSummary.vue'
import SchemaField from './SchemaField.vue'
import {
  clearHiddenAnswers,
  fieldResponseKey,
  isAnswerBlank,
  visibleSchemaFields,
} from './schemaConditions'

const props = defineProps({
  fields: { type: Array, default: () => [] },
  modelValue: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  copy: { type: Object, default: () => ({}) },
  processing: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  requireCv: { type: Boolean, default: false },
  submitLabel: { type: String, default: 'Submit' },
  privacyMessage: { type: String, default: '' },
  formToken: { type: String, default: '' },
  honeypotName: { type: String, default: 'company_website' },
})

const emit = defineEmits(['update:modelValue', 'submit'])
const errorSummary = ref(null)
const clientErrors = ref({})
const honeypot = ref('')
const honeypotId = 'opportunity-company-website'
const answers = computed(() => props.modelValue || {})
const messages = computed(() => ({
  required_label: 'Required',
  required_message: '{field} is required.',
  invalid_email_message: 'Enter a valid email address.',
  invalid_number_message: 'Enter a valid number.',
  invalid_date_message: 'Enter a valid date.',
  minimum_length_message: '{field} must contain at least {min} characters.',
  maximum_length_message: '{field} must contain no more than {max} characters.',
  minimum_value_message: '{field} must be at least {min}.',
  maximum_value_message: '{field} must be no more than {max}.',
  minimum_selections_message: 'Choose at least {min} options for {field}.',
  maximum_selections_message: 'Choose no more than {max} options for {field}.',
  invalid_format_message: 'Check the format of {field}.',
  invalid_file_type_message: 'Upload a PDF file.',
  file_too_large_message: 'The file must be {max} or smaller.',
  select_placeholder: 'Select an option',
  yes_label: 'Yes',
  no_label: 'No',
  submitting_label: 'Submitting…',
  form_unavailable: 'This form is not available.',
  honeypot_label: 'Leave this field blank',
  ...props.copy,
  error_summary: {
    title: 'Please check the form',
    introduction: 'Correct the fields below and submit again.',
    ...(props.copy.error_summary || {}),
  },
}))

const effectiveFields = computed(() => props.fields.map(field => {
  if (!props.requireCv || field.key !== 'cv') return field

  return {
    ...field,
    type: 'file',
    required: true,
    validation: {
      ...(field.validation || {}),
      accept: ['.pdf', 'application/pdf'],
      max_file_size_mb: 5,
    },
  }
}))
const visibleFields = computed(() => visibleSchemaFields(effectiveFields.value, answers.value))
const combinedErrors = computed(() => ({ ...props.errors, ...clientErrors.value }))

function interpolate(message, replacements = {}) {
  return Object.entries(replacements).reduce(
    (value, [key, replacement]) => String(value).replaceAll(`{${key}}`, String(replacement)),
    String(message || ''),
  )
}

function firstMessage(value) {
  if (Array.isArray(value)) return String(value[0] || '')
  if (value && typeof value === 'object') return firstMessage(Object.values(value)[0])
  return value === null || value === undefined ? '' : String(value)
}

function errorsForField(field) {
  const key = fieldResponseKey(field)
  const candidates = [
    combinedErrors.value[key],
    combinedErrors.value[`responses.${key}`],
    combinedErrors.value.responses?.[key],
  ]

  return candidates.map(firstMessage).filter(Boolean)
}

function validationMessage(field, key, fallback, replacements = {}) {
  const authored = field.validation?.messages?.[key]
  return interpolate(authored || messages.value[fallback], { field: field.label || 'This field', ...replacements })
}

function validateField(field, value) {
  const validation = field.validation || {}
  const type = String(field.type || 'short_text').toLocaleLowerCase()

  if (field.required && isAnswerBlank(value)) {
    return validationMessage(field, 'required', 'required_message')
  }
  if (isAnswerBlank(value)) return ''

  if ((type === 'email' || field.key === 'email') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value))) {
    return validationMessage(field, 'email', 'invalid_email_message')
  }

  if (type === 'number') {
    const number = Number(value)
    if (!Number.isFinite(number)) return validationMessage(field, 'number', 'invalid_number_message')
    if (validation.min !== undefined && number < Number(validation.min)) {
      return validationMessage(field, 'min', 'minimum_value_message', { min: validation.min })
    }
    if (validation.max !== undefined && number > Number(validation.max)) {
      return validationMessage(field, 'max', 'maximum_value_message', { max: validation.max })
    }
  }

  if (type === 'date' && Number.isNaN(Date.parse(`${value}T00:00:00`))) {
    return validationMessage(field, 'date', 'invalid_date_message')
  }

  if (Array.isArray(value)) {
    const minimum = validation.min_selections ?? validation.min
    const maximum = validation.max_selections ?? validation.max
    if (minimum !== undefined && value.length < Number(minimum)) {
      return validationMessage(field, 'min_selections', 'minimum_selections_message', { min: minimum })
    }
    if (maximum !== undefined && value.length > Number(maximum)) {
      return validationMessage(field, 'max_selections', 'maximum_selections_message', { max: maximum })
    }
  }

  const isFile = typeof File !== 'undefined' && value instanceof File
  if (isFile) {
    const isCv = field.key === 'cv'
    const maximumBytes = maximumFileBytes(validation, isCv)
    const extension = String(value.name || '').split('.').pop()?.toLocaleLowerCase()
    const configuredExtensions = validation.extensions || validation.allowed_extensions
    const allowed = Array.isArray(configuredExtensions) ? configuredExtensions.map(item => String(item).replace(/^\./, '').toLocaleLowerCase()) : []
    if (isCv && (extension !== 'pdf' || (value.type && value.type !== 'application/pdf'))) {
      return validationMessage(field, 'file_type', 'invalid_file_type_message')
    }
    if (allowed.length && !allowed.includes(extension)) {
      return validationMessage(field, 'file_type', 'invalid_file_type_message')
    }
    if (maximumBytes > 0 && value.size > maximumBytes) {
      return validationMessage(field, 'file_size', 'file_too_large_message', { max: formatFileSize(maximumBytes) })
    }
  }

  if (!Array.isArray(value) && !isFile) {
    const text = String(value)
    const minimumLength = Number(validation.min_length ?? validation.minlength ?? 0)
    const maximumLength = Number(validation.max_length ?? validation.maxlength ?? 0)
    if (minimumLength > 0 && text.length < minimumLength) {
      return validationMessage(field, 'min_length', 'minimum_length_message', { min: minimumLength })
    }
    if (maximumLength > 0 && text.length > maximumLength) {
      return validationMessage(field, 'max_length', 'maximum_length_message', { max: maximumLength })
    }
    if (validation.pattern) {
      try {
        if (!new RegExp(validation.pattern).test(text)) return validationMessage(field, 'pattern', 'invalid_format_message')
      } catch {
        return validationMessage(field, 'pattern', 'invalid_format_message')
      }
    }
  }

  return ''
}

function maximumFileBytes(validation, isCv) {
  const megabytes = validation.max_file_size_mb ?? validation.max_size_mb
  if (megabytes !== undefined && megabytes !== null && megabytes !== '') return Number(megabytes) * 1024 * 1024
  if (validation.max_kb !== undefined && validation.max_kb !== null && validation.max_kb !== '') return Number(validation.max_kb) * 1024

  return isCv ? 5 * 1024 * 1024 : 0
}

function formatFileSize(bytes) {
  if (bytes >= 1024 * 1024 && bytes % (1024 * 1024) === 0) return `${bytes / (1024 * 1024)} MB`
  if (bytes >= 1024 && bytes % 1024 === 0) return `${bytes / 1024} KB`

  return `${bytes} bytes`
}

function validateOne(field) {
  const key = fieldResponseKey(field)
  const message = validateField(field, answers.value[key])
  const nextErrors = { ...clientErrors.value }
  if (message) nextErrors[key] = message
  else delete nextErrors[key]
  clientErrors.value = nextErrors
  return message === ''
}

function validate() {
  const errors = {}
  visibleFields.value.forEach(field => {
    const key = fieldResponseKey(field)
    const message = validateField(field, answers.value[key])
    if (message) errors[key] = message
  })
  clientErrors.value = errors
  return Object.keys(errors).length === 0
}

function updateAnswer(field, value) {
  const key = fieldResponseKey(field)
  const nextAnswers = { ...answers.value, [key]: value }
  const nextErrors = { ...clientErrors.value }
  delete nextErrors[key]
  clientErrors.value = nextErrors
  emit('update:modelValue', clearHiddenAnswers(effectiveFields.value, nextAnswers))
}

async function focusFirstError() {
  await nextTick()
  errorSummary.value?.focus()
}

async function submit() {
  const cleaned = clearHiddenAnswers(effectiveFields.value, answers.value)
  if (!sameAnswers(cleaned, answers.value)) emit('update:modelValue', cleaned)
  if (!validate()) {
    await focusFirstError()
    return
  }

  emit('submit', cleaned, {
    form_token: props.formToken,
    honeypot_name: props.honeypotName,
    honeypot: honeypot.value,
  })
}

function sameAnswers(left, right) {
  const leftKeys = Object.keys(left)
  const rightKeys = Object.keys(right)
  if (leftKeys.length !== rightKeys.length || leftKeys.some(key => !rightKeys.includes(key))) return false

  return leftKeys.every(key => {
    if (Array.isArray(left[key]) && Array.isArray(right[key])) {
      return left[key].length === right[key].length && left[key].every((value, index) => value === right[key][index])
    }
    return left[key] === right[key]
  })
}

watch([effectiveFields, answers], ([fields, currentAnswers]) => {
  const cleaned = clearHiddenAnswers(fields, currentAnswers)
  if (!sameAnswers(cleaned, currentAnswers)) emit('update:modelValue', cleaned)
}, { deep: true })

watch(() => props.errors, async errors => {
  if (errors && Object.keys(errors).length) await focusFirstError()
}, { deep: true })

defineExpose({ validate, focusFirstError })
</script>

<style scoped>
.igf-schema-form{display:grid;gap:22px}.igf-schema-form__honeypot{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}.igf-schema-form__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px 18px}.igf-schema-form__empty{margin:0;padding:24px;border:1px dashed #bdb5ae;border-radius:10px;background:#faf8f6;color:#66605c;text-align:center}.igf-schema-form__privacy{display:flex;align-items:flex-start;gap:10px;margin:0;color:#625d59;font-size:13px;line-height:1.55}.igf-schema-form__privacy span{margin-top:5px;color:#9c4500;font-size:8px}.igf-schema-form__submit{display:flex;width:100%;min-height:54px;align-items:center;justify-content:center;gap:12px;border:0;border-radius:999px;background:#9c4500;color:#fff;font-size:13px;font-weight:900;letter-spacing:.045em;text-transform:uppercase;cursor:pointer}.igf-schema-form__submit:hover{background:#783300}.igf-schema-form__submit:focus-visible{outline:3px solid rgba(255,117,0,.32);outline-offset:3px}.igf-schema-form__submit:disabled{cursor:not-allowed;opacity:.55}@media(max-width:640px){.igf-schema-form__grid{grid-template-columns:1fr}}
</style>
