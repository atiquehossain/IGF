<!-- Rich text is sanitized by the server before it reaches this view. -->
<!-- eslint-disable vue/no-v-html -->
<template>
  <Layout>
    <div v-if="listing" class="igf-opportunity-detail">
      <header class="igf-opportunity-detail__hero">
        <div class="igf-shell">
          <a :href="route('frontend.workshops.index')"><span aria-hidden="true">←</span> {{ copy.back_label }}</a>
          <div class="igf-opportunity-detail__status-row">
            <p>{{ copy.eyebrow }}</p><span v-if="listing.status_label || listing.registration_status_label">{{ listing.status_label || listing.registration_status_label }}</span>
          </div>
          <h1>{{ listing.title }}</h1>
          <p v-if="listing.summary || listing.sub_title" class="igf-opportunity-detail__lead">{{ listing.summary || listing.sub_title }}</p>
          <dl class="igf-opportunity-detail__meta">
            <div v-for="item in metadata" :key="item.label"><dt>{{ item.label }}</dt><dd>{{ item.value }}</dd></div>
          </dl>
        </div>
      </header>

      <div class="igf-opportunity-detail__main">
        <article class="igf-opportunity-detail__article">
          <div v-if="listing.description" v-html="listing.description" />
          <section v-if="listing.venue_address" aria-labelledby="workshop-venue-title">
            <h2 id="workshop-venue-title">{{ copy.venue_details_title }}</h2>
            <p>{{ listing.venue_address }}</p>
          </section>
          <section v-if="listing.registration_instructions" aria-labelledby="workshop-instructions-title">
            <h2 id="workshop-instructions-title">{{ copy.registration_instructions_title }}</h2>
            <div v-html="listing.registration_instructions" />
          </section>
        </article>
        <aside
          class="igf-opportunity-detail__form-panel"
          :aria-labelledby="submissionReference ? undefined : (isOpen ? 'workshop-form-title' : 'workshop-closed-title')"
        >
          <SubmissionReference v-if="submissionReference" :reference="submissionReference" :status="submissionStatus" :updated="submissionUpdated" :copy="copy.submission || {}" />
          <template v-else-if="isOpen">
            <header class="igf-opportunity-detail__form-head">
              <p>{{ copy.form_eyebrow }}</p><h2 id="workshop-form-title">{{ copy.form_title }}</h2><span>{{ copy.form_introduction }}</span>
            </header>
            <SchemaForm ref="schemaForm" v-model="answers" :fields="fields" :errors="errors" :copy="formCopy" :processing="processing"
              :submit-label="copy.submit_label" :privacy-message="copy.privacy_message" :form-token="data.form?.token || ''"
              :honeypot-name="data.form?.honeypot_name || 'company_website'" @submit="submitRegistration" />
          </template>
          <section v-else class="igf-opportunity-detail__closed" role="status">
            <span aria-hidden="true">◇</span><h2 id="workshop-closed-title">{{ closedTitle }}</h2><p>{{ closedMessage }}</p>
          </section>
        </aside>
      </div>
    </div>
  </Layout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Layout from '../layouts/App.vue'
import SchemaForm from '../Shared/forms/SchemaForm.vue'
import SubmissionReference from '../Shared/forms/SubmissionReference.vue'
import { buildSchemaPayload, listingActionUrl, normalizeFormFields, withFixedFields } from '../Shared/forms/schemaPayload'

const page = usePage()
const data = computed(() => page.props.data || {})
const listing = computed(() => data.value.listing || null)
const processing = ref(false)
const schemaForm = ref(null)
const answers = ref({ ...(data.value.form?.initial_values || {}) })
const submissionReference = ref(String(data.value.submission_reference || page.props.flash?.submission_reference || ''))
const submissionStatus = ref(String(data.value.submission_status_label || page.props.flash?.submission_status_label || ''))
const submissionUpdated = ref(Boolean(data.value.submission_updated || page.props.flash?.submission_updated))
const errors = computed(() => page.props.errors || {})
const copy = computed(() => ({
  back_label: 'Back to workshops',
  eyebrow: 'Free workshop',
  date_label: 'Workshop date',
  registration_opens_label: 'Registration opens',
  registration_deadline_label: 'Registration deadline',
  venue_label: 'Venue',
  format_label: 'Format',
  availability_label: 'Availability',
  venue_details_title: 'Venue details',
  registration_instructions_title: 'Registration information',
  form_eyebrow: 'Registration form',
  form_title: 'Register for this workshop',
  form_introduction: 'Complete the fields below. Required fields are marked with an asterisk.',
  applicant_name_label: 'Full name',
  applicant_name_placeholder: 'Enter your full name',
  email_label: 'Email address',
  email_placeholder: 'name@example.com',
  phone_label: 'Phone number',
  phone_placeholder: 'Enter your phone number',
  submit_label: 'Submit registration',
  privacy_message: 'Your registration details are private and available only to authorized workshop staff.',
  closed_title: 'Registration closed',
  closed_message: 'This workshop is no longer accepting registrations.',
  upcoming_title: 'Registration is not open yet',
  upcoming_message: 'Please return when the registration period begins.',
  ...(page.props.copy || {}),
  ...(data.value.copy || {}),
}))
const formCopy = computed(() => ({ ...copy.value, ...(data.value.form?.copy || {}), ...(copy.value.form || {}) }))
const fields = computed(() => withFixedFields(normalizeFormFields(data.value.form), [
  { uuid: 'fixed-applicant-name', key: 'applicant_name', type: 'short_text', label: copy.value.applicant_name_label, placeholder: copy.value.applicant_name_placeholder, required: true, validation: { max_length: 150 } },
  { uuid: 'fixed-email', key: 'email', type: 'email', label: copy.value.email_label, placeholder: copy.value.email_placeholder, required: true, validation: { max_length: 190 } },
  { uuid: 'fixed-phone', key: 'phone', type: 'phone', label: copy.value.phone_label, placeholder: copy.value.phone_placeholder, required: false, validation: { max_length: 30 } },
]))
const metadata = computed(() => [
  [copy.value.date_label, listing.value?.workshop_date_label || listing.value?.date_label],
  [copy.value.registration_opens_label, listing.value?.registration_opens_label],
  [copy.value.registration_deadline_label, listing.value?.registration_deadline_label],
  [copy.value.venue_label, listing.value?.venue_label || listing.value?.venue],
  [copy.value.format_label, listing.value?.format_label || listing.value?.attendance_mode],
  [copy.value.availability_label, listing.value?.availability_label],
].filter(([, value]) => value !== null && value !== undefined && value !== '').map(([label, value]) => ({ label, value })))
const isOpen = computed(() => listingAcceptsSubmissions(listing.value))
const isUpcoming = computed(() => ['upcoming', 'scheduled', 'not_open'].includes(String(listing.value?.registration_state || listing.value?.state || '').toLocaleLowerCase()))
const closedTitle = computed(() => isUpcoming.value ? copy.value.upcoming_title : copy.value.closed_title)
const closedMessage = computed(() => isUpcoming.value ? copy.value.upcoming_message : copy.value.closed_message)

function listingAcceptsSubmissions(value) {
  if (!value) return false
  for (const key of ['is_open', 'can_register', 'accepting_registrations']) {
    if (value[key] !== undefined && value[key] !== null) return Boolean(value[key])
  }
  return !['closed', 'expired', 'upcoming', 'scheduled', 'not_open', 'draft', 'paused', 'cancelled'].includes(
    String(value.registration_state || value.state || '').toLocaleLowerCase(),
  )
}

function submitRegistration(currentAnswers, security = {}) {
  if (!listing.value || processing.value) return
  processing.value = true
  const payload = buildSchemaPayload(fields.value, currentAnswers)
  payload.form_token = security.form_token || data.value.form?.token || ''
  payload[security.honeypot_name || data.value.form?.honeypot_name || 'company_website'] = security.honeypot || ''

  const actionUrl = listingActionUrl(listing.value.public_url, 'register')
    || route('frontend.workshops.register', listing.value.slug)
  router.post(actionUrl, payload, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: responsePage => {
      const responseData = responsePage.props?.data || {}
      submissionReference.value = String(responseData.submission_reference || responsePage.props?.flash?.submission_reference || '')
      submissionStatus.value = String(responseData.submission_status_label || responsePage.props?.flash?.submission_status_label || '')
      submissionUpdated.value = Boolean(responseData.submission_updated || responsePage.props?.flash?.submission_updated)
      if (submissionReference.value) answers.value = {}
    },
    onError: () => schemaForm.value?.focusFirstError(),
    onFinish: () => { processing.value = false },
  })
}
</script>

<style scoped>
.igf-opportunity-detail{--brown:var(--igf-accent,#9c4500);--orange:var(--igf-primary,#ff7500);--ink:var(--igf-ink,#191c1d);--muted:color-mix(in srgb,var(--ink) 68%,var(--surface));--surface:var(--igf-surface,#f8f9fa);--line:color-mix(in srgb,var(--ink) 14%,var(--surface));color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell{width:min(calc(100% - 40px),1080px);margin-inline:auto}.igf-opportunity-detail__hero{padding:clamp(78px,10vw,125px) 0;background:radial-gradient(circle at 85% 12%,color-mix(in srgb,var(--orange) 23%,transparent),transparent 25%),#242220;color:#fff}.igf-opportunity-detail__hero>a{display:inline-flex;gap:8px;margin-bottom:38px;color:color-mix(in srgb,var(--orange) 42%,#fff);font-size:13px;font-weight:850;text-decoration:none}.igf-opportunity-detail__status-row{display:flex;align-items:center;justify-content:space-between;gap:16px}.igf-opportunity-detail__status-row p{margin:0;color:color-mix(in srgb,var(--orange) 42%,#fff);font-size:11px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.igf-opportunity-detail__status-row span{padding:6px 9px;border-radius:999px;background:rgba(255,255,255,.12);font-size:11px;font-weight:800}.igf-opportunity-detail h1{max-width:900px;margin:14px 0 0;color:#fff;font:650 clamp(42px,6vw,70px)/1.06 'Literata',Georgia,serif;letter-spacing:-.035em}.igf-opportunity-detail h1::after,.igf-opportunity-detail h2::after{display:none!important}.igf-opportunity-detail__lead{max-width:760px;margin:22px 0 0;color:#dedbd8;font-size:19px;line-height:1.65}.igf-opportunity-detail__meta{display:flex;flex-wrap:wrap;gap:18px 30px;margin:30px 0 0}.igf-opportunity-detail__meta div{display:grid;gap:3px}.igf-opportunity-detail__meta dt{color:#bdb8b4;font-size:10px;font-weight:850;letter-spacing:.06em;text-transform:uppercase}.igf-opportunity-detail__meta dd{margin:0;color:#fff;font-size:14px;font-weight:750}.igf-opportunity-detail__main{display:grid;grid-template-columns:minmax(0,.82fr) minmax(520px,1.18fr);gap:clamp(45px,7vw,85px);width:min(calc(100% - 40px),1180px);margin-inline:auto;padding:clamp(65px,8vw,105px) 0;align-items:start}.igf-opportunity-detail__article{min-width:0;color:var(--muted);font-size:17px;line-height:1.78}.igf-opportunity-detail__article :deep(h2),.igf-opportunity-detail__article :deep(h3){margin:1.55em 0 .55em;color:var(--ink);font-family:'Literata',Georgia,serif;letter-spacing:-.025em}.igf-opportunity-detail__article :deep(a){color:var(--brown);font-weight:800}.igf-opportunity-detail__article :deep(img){max-width:100%;height:auto}.igf-opportunity-detail__form-panel{min-width:0;padding:clamp(22px,3vw,36px);border:1px solid var(--line);border-radius:19px;background:#fff;box-shadow:0 14px 45px rgba(25,28,29,.07)}.igf-opportunity-detail__form-head{margin-bottom:28px}.igf-opportunity-detail__form-head>p{margin:0 0 9px;color:var(--brown);font-size:10px;font-weight:900;letter-spacing:.09em;text-transform:uppercase}.igf-opportunity-detail__form-head h2{margin:0;font:650 clamp(29px,4vw,40px)/1.15 'Literata',Georgia,serif}.igf-opportunity-detail__form-head>span{display:block;margin-top:11px;color:var(--muted);line-height:1.6}.igf-opportunity-detail__closed{padding:30px 15px;text-align:center}.igf-opportunity-detail__closed>span{color:var(--orange);font-size:40px}.igf-opportunity-detail__closed h2{margin:12px 0 8px;font:650 30px 'Literata',Georgia,serif}.igf-opportunity-detail__closed p{margin:0;color:var(--muted);line-height:1.6}@media(max-width:980px){.igf-opportunity-detail__main{grid-template-columns:1fr}.igf-opportunity-detail__article{max-width:820px}.igf-opportunity-detail__form-panel{width:100%}}@media(max-width:600px){.igf-shell,.igf-opportunity-detail__main{width:min(calc(100% - 28px),1180px)}.igf-opportunity-detail__hero{padding:65px 0}.igf-opportunity-detail__status-row{align-items:flex-start;flex-direction:column}.igf-opportunity-detail__form-panel{padding:21px 16px}}
</style>
