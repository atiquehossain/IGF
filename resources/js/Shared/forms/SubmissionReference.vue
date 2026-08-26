<template>
  <section
    v-if="reference"
    ref="receipt"
    class="igf-submission-reference"
    role="status"
    aria-live="polite"
    tabindex="-1"
    data-test="submission-reference"
  >
    <span class="igf-submission-reference__icon" aria-hidden="true">✓</span>
    <div>
      <p class="igf-submission-reference__eyebrow">{{ copy.eyebrow || 'Submission received' }}</p>
      <h2>{{ copy.title || 'Thank you' }}</h2>
      <p>{{ copy.message || 'Your information has been received. Our team will contact selected applicants.' }}</p>
      <p v-if="updated" class="igf-submission-reference__updated">{{ copy.updated_message || 'Your latest submission replaced the earlier one for this opportunity.' }}</p>
      <p v-if="status" class="igf-submission-reference__status">{{ status }}</p>
      <div class="igf-submission-reference__number">
        <span>{{ copy.reference_label || 'Reference number' }}</span>
        <code>{{ reference }}</code>
      </div>
    </div>
  </section>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'

const props = defineProps({
  reference: { type: String, default: '' },
  status: { type: String, default: '' },
  updated: { type: Boolean, default: false },
  copy: { type: Object, default: () => ({}) },
})

const receipt = ref(null)

watch(() => props.reference, async reference => {
  if (!reference) return
  await nextTick()
  receipt.value?.focus()
}, { immediate: true })
</script>

<style scoped>
.igf-submission-reference{display:grid;grid-template-columns:auto minmax(0,1fr);gap:18px;padding:26px;border:1px solid #b9dbc5;border-radius:16px;background:#f2fbf5;color:#1d4c2d}.igf-submission-reference:focus{outline:3px solid rgba(36,117,66,.25);outline-offset:3px}.igf-submission-reference__icon{display:grid;width:46px;height:46px;place-items:center;border-radius:50%;background:#247542;color:#fff;font-size:24px;font-weight:900}.igf-submission-reference__eyebrow{margin:0 0 7px!important;color:#247542!important;font-size:11px!important;font-weight:900!important;letter-spacing:.09em;text-transform:uppercase}.igf-submission-reference h2{margin:0 0 10px;font:700 clamp(27px,4vw,36px)/1.15 'Literata',Georgia,serif}.igf-submission-reference p{margin:0;color:#365a42;line-height:1.6}.igf-submission-reference__updated{margin-top:8px!important;font-weight:750}.igf-submission-reference__status{margin-top:8px!important;font-weight:800}.igf-submission-reference__number{display:grid;gap:5px;margin-top:18px}.igf-submission-reference__number span{font-size:12px;font-weight:800}.igf-submission-reference code{width:max-content;max-width:100%;padding:8px 11px;border:1px solid #a9d3b7;border-radius:7px;background:#fff;color:#174525;font:800 16px/1.2 ui-monospace,SFMono-Regular,Consolas,monospace;overflow-wrap:anywhere}@media(max-width:520px){.igf-submission-reference{grid-template-columns:1fr;padding:21px}.igf-submission-reference__icon{width:40px;height:40px}}
</style>
