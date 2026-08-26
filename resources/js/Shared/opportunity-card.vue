<template>
  <article class="igf-opportunity-card" :class="`igf-opportunity-card--${kind}`">
    <figure v-if="listing.image_url" class="igf-opportunity-card__media">
      <img :src="listing.image_url" :alt="listing.image_alt ?? listing.title" loading="lazy" decoding="async">
    </figure>
    <div class="igf-opportunity-card__body">
      <div class="igf-opportunity-card__topline">
        <span>{{ eyebrow }}</span>
        <span v-if="statusLabel" class="igf-opportunity-card__status">{{ statusLabel }}</span>
      </div>
      <h2><a :href="href">{{ listing.title }}</a></h2>
      <p v-if="showSummary && (listing.summary || listing.sub_title)">{{ listing.summary || listing.sub_title }}</p>
      <dl v-if="metadata.length" class="igf-opportunity-card__meta">
        <div v-for="item in metadata" :key="item.label">
          <dt class="sr-only">{{ item.label }}</dt>
          <dd>{{ item.value }}</dd>
        </div>
      </dl>
      <a class="igf-opportunity-card__link" :href="href">
        {{ copy.link_label || (kind === 'job' ? 'View job and apply' : 'View workshop and register') }}
        <span aria-hidden="true">→</span>
      </a>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  listing: { type: Object, required: true },
  href: { type: String, required: true },
  kind: { type: String, default: 'job' },
  copy: { type: Object, default: () => ({}) },
  showSummary: { type: Boolean, default: true },
})

const eyebrow = computed(() => props.listing.eyebrow
  || (props.kind === 'job' ? props.listing.department : props.listing.format_label)
  || (props.kind === 'job' ? props.copy.job_eyebrow : props.copy.workshop_eyebrow)
  || '')
const statusLabel = computed(() => props.listing.status_label || props.listing.registration_status_label || '')
const metadata = computed(() => {
  const source = props.kind === 'job'
    ? [
        [props.copy.location_label || 'Location', props.listing.location],
        [props.copy.employment_type_label || 'Employment type', props.listing.employment_type_label || props.listing.employment_type],
        [props.copy.deadline_label || 'Deadline', props.listing.application_deadline_label || props.listing.deadline_label],
      ]
    : [
        [props.copy.date_label || 'Date', props.listing.workshop_date_label || props.listing.date_label],
        [props.copy.venue_label || 'Venue', props.listing.venue_label || props.listing.venue],
        [props.copy.registration_deadline_label || 'Registration deadline', props.listing.registration_deadline_label],
      ]

  return source.filter(([, value]) => value).map(([label, value]) => ({ label, value }))
})
</script>

<style scoped>
.igf-opportunity-card{display:grid;overflow:hidden;border:1px solid #ded9d4;border-radius:17px;background:#fff;box-shadow:0 8px 25px rgba(25,28,29,.05)}.igf-opportunity-card__media{margin:0;overflow:hidden;background:#eee}.igf-opportunity-card__media img{display:block;width:100%;aspect-ratio:16/9;object-fit:cover;transition:transform .25s ease}.igf-opportunity-card--workshop .igf-opportunity-card__media img{aspect-ratio:4/3;object-fit:contain}.igf-opportunity-card:hover .igf-opportunity-card__media img{transform:scale(1.025)}.igf-opportunity-card__body{display:flex;min-width:0;flex-direction:column;padding:25px}.igf-opportunity-card__topline{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;color:#9c4500;font-size:10px;font-weight:900;letter-spacing:.09em;text-transform:uppercase}.igf-opportunity-card__status{padding:5px 8px;border-radius:999px;background:#eaf6ed;color:#247542;letter-spacing:.04em}.igf-opportunity-card h2{margin:0;font:700 clamp(22px,3vw,28px)/1.22 'Literata',Georgia,serif;letter-spacing:-.025em}.igf-opportunity-card h2::after{display:none!important}.igf-opportunity-card h2 a{color:#191c1d;text-decoration:none}.igf-opportunity-card h2 a:focus-visible{outline:3px solid rgba(255,117,0,.32);outline-offset:4px}.igf-opportunity-card__body>p{margin:13px 0 0;color:#5f6065;font-size:15px;line-height:1.62}.igf-opportunity-card__meta{display:flex;flex-wrap:wrap;gap:7px 16px;margin:20px 0 0}.igf-opportunity-card__meta div{position:relative}.igf-opportunity-card__meta div+div::before{position:absolute;left:-10px;color:#bdb6b0;content:'·'}.igf-opportunity-card__meta dd{margin:0;color:#5b5652;font-size:12px;font-weight:750}.igf-opportunity-card__link{display:inline-flex;align-items:center;gap:9px;width:max-content;max-width:100%;margin-top:24px;color:#9c4500;font-size:13px;font-weight:900;text-decoration:none}.igf-opportunity-card__link:hover{text-decoration:underline;text-underline-offset:4px}@media(prefers-reduced-motion:reduce){.igf-opportunity-card__media img{transition:none}}@media(max-width:560px){.igf-opportunity-card__body{padding:21px}.igf-opportunity-card__topline{align-items:flex-start;flex-direction:column}.igf-opportunity-card__meta{display:grid}.igf-opportunity-card__meta div+div::before{display:none}}
</style>
