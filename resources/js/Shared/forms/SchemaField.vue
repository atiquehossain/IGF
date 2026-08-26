<template>
  <div class="igf-schema-field" :class="{ 'igf-schema-field--error': errorMessages.length, 'igf-schema-field--wide': isWide }">
    <fieldset v-if="isChoiceGroup" :id="inputId" class="igf-schema-field__fieldset" tabindex="-1" :aria-describedby="describedBy || undefined" :aria-invalid="errorMessages.length ? 'true' : undefined">
      <legend>
        {{ field.label }}
        <span v-if="field.required" aria-hidden="true">*</span>
        <span v-if="field.required" class="sr-only">{{ copy.required_label || 'Required' }}</span>
      </legend>
      <p v-if="field.help" :id="helpId" class="igf-schema-field__help">{{ field.help }}</p>
      <div class="igf-schema-field__choices">
        <label v-for="option in choiceOptions" :key="String(option.value)" class="igf-schema-choice">
          <input
            :type="isMultiChoice ? 'checkbox' : 'radio'"
            :name="isMultiChoice ? `${responseKey}[]` : responseKey"
            :value="option.value"
            :checked="isOptionChecked(option.value)"
            :disabled="disabled"
            @change="updateChoice(option.value, $event.target.checked)"
            @blur="$emit('blur')"
          >
          <span>{{ option.label }}</span>
        </label>
      </div>
      <div v-if="errorMessages.length" :id="errorId" class="igf-schema-field__error">
        <span v-for="message in errorMessages" :key="message">{{ message }}</span>
      </div>
    </fieldset>

    <template v-else>
      <label :for="inputId" class="igf-schema-field__label">
        {{ field.label }}
        <span v-if="field.required" aria-hidden="true">*</span>
        <span v-if="field.required" class="sr-only">{{ copy.required_label || 'Required' }}</span>
      </label>
      <p v-if="field.help" :id="helpId" class="igf-schema-field__help">{{ field.help }}</p>

      <textarea
        v-if="controlType === 'textarea'"
        :id="inputId"
        :name="responseKey"
        :value="modelValue ?? ''"
        :placeholder="field.placeholder || undefined"
        :required="field.required"
        :disabled="disabled"
        :maxlength="maximumLength"
        :minlength="minimumLength"
        :rows="Number(field.validation?.rows || 5)"
        :aria-describedby="describedBy || undefined"
        :aria-invalid="errorMessages.length ? 'true' : undefined"
        @input="$emit('update:modelValue', $event.target.value)"
        @blur="$emit('blur')"
      />

      <select
        v-else-if="controlType === 'select'"
        :id="inputId"
        :name="responseKey"
        :value="modelValue ?? ''"
        :required="field.required"
        :disabled="disabled"
        :aria-describedby="describedBy || undefined"
        :aria-invalid="errorMessages.length ? 'true' : undefined"
        @change="$emit('update:modelValue', $event.target.value)"
        @blur="$emit('blur')"
      >
        <option value="">{{ field.placeholder || copy.select_placeholder || 'Select an option' }}</option>
        <option v-for="option in normalizedOptions" :key="String(option.value)" :value="option.value">{{ option.label }}</option>
      </select>

      <div v-else-if="controlType === 'file'" class="igf-schema-file">
        <input
          :id="inputId"
          :name="responseKey"
          type="file"
          :required="field.required"
          :disabled="disabled"
          :accept="acceptedFiles || undefined"
          :aria-describedby="describedBy || undefined"
          :aria-invalid="errorMessages.length ? 'true' : undefined"
          @change="updateFile"
          @blur="$emit('blur')"
        >
        <span v-if="modelValue?.name" class="igf-schema-file__name">{{ modelValue.name }}</span>
      </div>

      <input
        v-else
        :id="inputId"
        :name="responseKey"
        :type="controlType"
        :value="modelValue ?? ''"
        :placeholder="field.placeholder || undefined"
        :required="field.required"
        :disabled="disabled"
        :autocomplete="autocomplete"
        :inputmode="inputMode"
        :maxlength="maximumLength"
        :minlength="minimumLength"
        :min="field.validation?.min ?? undefined"
        :max="field.validation?.max ?? undefined"
        :step="field.validation?.step ?? undefined"
        :pattern="field.validation?.pattern || undefined"
        :aria-describedby="describedBy || undefined"
        :aria-invalid="errorMessages.length ? 'true' : undefined"
        @input="$emit('update:modelValue', inputValue($event))"
        @blur="$emit('blur')"
      >

      <div v-if="errorMessages.length" :id="errorId" class="igf-schema-field__error">
        <span v-for="message in errorMessages" :key="message">{{ message }}</span>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { fieldResponseKey } from './schemaConditions'

const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { type: [String, Number, Boolean, Array, Object], default: '' },
  errors: { type: [String, Array], default: '' },
  copy: { type: Object, default: () => ({}) },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'blur'])
const responseKey = computed(() => fieldResponseKey(props.field))
const identity = computed(() => String(props.field.uuid || props.field.key || 'question').replace(/[^A-Za-z0-9_-]/g, '-'))
const inputId = computed(() => `field-${identity.value}`)
const helpId = computed(() => `${inputId.value}-help`)
const errorId = computed(() => `${inputId.value}-error`)
const errorMessages = computed(() => (Array.isArray(props.errors) ? props.errors : [props.errors]).map(value => String(value || '')).filter(Boolean))
const describedBy = computed(() => [props.field.help ? helpId.value : '', errorMessages.value.length ? errorId.value : ''].filter(Boolean).join(' '))
const normalizedType = computed(() => String(props.field.type || 'short_text').toLocaleLowerCase())
const isMultiChoice = computed(() => ['checkbox', 'checkboxes', 'multi_select'].includes(normalizedType.value))
const isChoiceGroup = computed(() => isMultiChoice.value || ['radio', 'multiple_choice', 'yes_no', 'boolean'].includes(normalizedType.value))
const isWide = computed(() => ['long_text', 'textarea', 'checkbox', 'checkboxes', 'radio', 'multiple_choice', 'file', 'upload', 'cv'].includes(normalizedType.value) || props.field.key === 'cv')
const normalizedOptions = computed(() => (Array.isArray(props.field.options) ? props.field.options : []).map(option => (
  option && typeof option === 'object'
    ? { value: option.value ?? '', label: option.label ?? String(option.value ?? '') }
    : { value: option, label: String(option) }
)))
const choiceOptions = computed(() => ['yes_no', 'boolean'].includes(normalizedType.value) && normalizedOptions.value.length === 0
  ? [{ value: 'yes', label: props.copy.yes_label || 'Yes' }, { value: 'no', label: props.copy.no_label || 'No' }]
  : normalizedOptions.value)
const controlType = computed(() => {
  if (['long_text', 'textarea'].includes(normalizedType.value)) return 'textarea'
  if (['select', 'dropdown'].includes(normalizedType.value)) return 'select'
  if (['file', 'upload', 'cv'].includes(normalizedType.value) || props.field.key === 'cv') return 'file'
  if (normalizedType.value === 'email' || props.field.key === 'email') return 'email'
  if (['phone', 'tel'].includes(normalizedType.value) || props.field.key === 'phone') return 'tel'
  if (normalizedType.value === 'number') return 'number'
  if (normalizedType.value === 'date') return 'date'
  return 'text'
})
const autocomplete = computed(() => ({
  applicant_name: 'name',
  email: 'email',
  phone: 'tel',
}[props.field.key] || props.field.validation?.autocomplete || 'off'))
const inputMode = computed(() => controlType.value === 'tel' ? 'tel' : (controlType.value === 'number' ? 'decimal' : undefined))
const maximumLength = computed(() => props.field.validation?.max_length ?? props.field.validation?.maxlength ?? undefined)
const minimumLength = computed(() => props.field.validation?.min_length ?? props.field.validation?.minlength ?? undefined)
const acceptedFiles = computed(() => {
  if (props.field.key === 'cv') return '.pdf,application/pdf'
  const accept = props.field.validation?.accept || props.field.validation?.allowed_types
  if (accept) return Array.isArray(accept) ? accept.join(',') : accept

  const extensions = props.field.validation?.extensions || props.field.validation?.allowed_extensions
  return Array.isArray(extensions)
    ? extensions.map(extension => `.${String(extension).replace(/^\./, '')}`).join(',')
    : ''
})

function isOptionChecked(value) {
  return isMultiChoice.value
    ? (Array.isArray(props.modelValue) && props.modelValue.map(String).includes(String(value)))
    : String(props.modelValue ?? '') === String(value)
}

function updateChoice(value, checked) {
  if (!isMultiChoice.value) {
    emit('update:modelValue', value)
    return
  }

  const values = Array.isArray(props.modelValue) ? [...props.modelValue] : []
  const index = values.findIndex(item => String(item) === String(value))
  if (checked && index === -1) values.push(value)
  if (!checked && index !== -1) values.splice(index, 1)
  emit('update:modelValue', values)
}

function updateFile(event) {
  emit('update:modelValue', event.target.files?.[0] || null)
}

function inputValue(event) {
  return controlType.value === 'number' && event.target.value !== ''
    ? Number(event.target.value)
    : event.target.value
}
</script>

<style scoped>
.igf-schema-field{display:grid;align-content:start;gap:7px;min-width:0}.igf-schema-field--wide{grid-column:1/-1}.igf-schema-field__label,.igf-schema-field legend{margin:0;color:#34312f;font-size:14px;font-weight:800}.igf-schema-field__label>span[aria-hidden],.igf-schema-field legend>span[aria-hidden]{margin-left:3px;color:#a52c24}.igf-schema-field__help{margin:0;color:#676260;font-size:13px;line-height:1.5}.igf-schema-field input:not([type=radio]):not([type=checkbox]),.igf-schema-field textarea,.igf-schema-field select{width:100%;min-height:50px;padding:11px 13px;border:1px solid #a9a29c;border-radius:9px;background:#fff;color:#191c1d;font:500 16px/1.45 'Hanken Grotesk',Arial,sans-serif}.igf-schema-field textarea{min-height:130px;resize:vertical}.igf-schema-field input:focus,.igf-schema-field textarea:focus,.igf-schema-field select:focus,.igf-schema-field__fieldset:focus{border-color:#9c4500;outline:3px solid rgba(255,117,0,.2);outline-offset:2px}.igf-schema-field--error input:not([type=radio]):not([type=checkbox]),.igf-schema-field--error textarea,.igf-schema-field--error select,.igf-schema-field--error .igf-schema-field__fieldset{border-color:#a52c24}.igf-schema-field__fieldset{min-width:0;margin:0;padding:14px;border:1px solid #bbb3ad;border-radius:9px}.igf-schema-field__fieldset legend{width:auto;padding:0 5px}.igf-schema-field__choices{display:grid;gap:9px;margin-top:8px}.igf-schema-choice{display:flex;min-height:44px;align-items:center;gap:10px;margin:0;padding:7px 10px;border-radius:7px;background:#faf8f6;color:#34312f;cursor:pointer}.igf-schema-choice input{width:19px;height:19px;flex:0 0 auto;accent-color:#9c4500}.igf-schema-field__error{display:grid;gap:3px;color:#922a22;font-size:13px;font-weight:700}.igf-schema-file{display:grid;gap:7px}.igf-schema-file input{padding:9px!important}.igf-schema-file__name{overflow-wrap:anywhere;color:#625c58;font-size:13px}@media(max-width:640px){.igf-schema-field--wide{grid-column:auto}.igf-schema-field input:not([type=radio]):not([type=checkbox]),.igf-schema-field textarea,.igf-schema-field select{font-size:16px}}
</style>
