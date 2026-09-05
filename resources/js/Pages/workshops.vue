<template>
  <Layout>
    <div class="igf-opportunities">
      <header class="igf-opportunities__hero">
        <div class="igf-shell"><p>{{ copy.eyebrow }}</p><h1>{{ copy.title || title }}</h1><span>{{ copy.introduction }}</span></div>
      </header>
      <section class="igf-opportunities__content" aria-labelledby="workshop-listing-title">
        <div class="igf-shell">
          <header class="igf-opportunities__heading">
            <h2 id="workshop-listing-title">{{ copy.listing_title }}</h2>
            <p v-if="copy.listing_introduction">{{ copy.listing_introduction }}</p>
          </header>
          <div v-if="items.length" class="igf-opportunities__grid">
            <OpportunityCard
              v-for="listing in items"
              :key="listing.uuid || listing.id"
              :listing="listing"
              :href="listing.public_url || route('frontend.workshops.show', listing.slug)"
              kind="workshop"
              :copy="cardCopy"
              :show-summary="false"
            />
          </div>
          <div v-else class="igf-opportunities__empty" role="status">
            <span aria-hidden="true">◇</span><h2>{{ copy.empty_title }}</h2><p>{{ copy.empty_message }}</p>
          </div>
          <v-pagination v-if="properties.total_page > 1" :model-value="properties.page || 1" :length="properties.total_page"
            class="igf-opportunities__pagination" :aria-label="copy.pagination_label" @update:model-value="changePage" />
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Layout from '../layouts/App.vue'
import OpportunityCard from '../Shared/opportunity-card.vue'

const page = usePage()
const data = computed(() => page.props.data || {})
const title = computed(() => page.props.title || 'Workshops')
const items = computed(() => data.value.items || [])
const properties = computed(() => page.props.properties || {})
const copy = computed(() => ({
  eyebrow: 'Learn together',
  title: '',
  introduction: 'Register for workshops led by Ignite Global Foundation.',
  listing_title: 'Upcoming workshops',
  listing_introduction: '',
  empty_title: 'No workshops are open right now',
  empty_message: 'Please check again for upcoming sessions.',
  pagination_label: 'Workshop listing pages',
  ...(page.props.copy || {}),
  ...(data.value.copy || {}),
}))
const cardCopy = computed(() => ({ ...copy.value, ...(copy.value.card || {}) }))

function changePage(number) {
  router.get(page.url, { page: number }, { preserveState: true, preserveScroll: true })
}
</script>

<style scoped>
.igf-opportunities{--orange:var(--igf-primary,#ff7500);--ink:var(--igf-ink,#191c1d);--muted:color-mix(in srgb,var(--ink) 68%,var(--surface));--surface:var(--igf-surface,#f8f9fa);color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell{width:min(calc(100% - 40px),var(--igf-content-width,1200px));margin-inline:auto}.igf-opportunities__hero{padding:var(--igf-section-block,clamp(80px,10vw,130px)) 0;background:radial-gradient(circle at 84% 20%,color-mix(in srgb,var(--orange) 20%,transparent),transparent 26%),#242220;color:#fff}.igf-opportunities__hero p{margin:0 0 15px;color:color-mix(in srgb,var(--orange) 42%,#fff);font-size:11px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.igf-opportunities__hero h1{max-width:850px;margin:0;color:#fff;font:650 var(--igf-heading-1,clamp(44px,6vw,72px))/1.05 'Literata',Georgia,serif;letter-spacing:-.035em}.igf-opportunities__hero h1::after,.igf-opportunities__heading h2::after{display:none!important}.igf-opportunities__hero span{display:block;max-width:720px;margin-top:20px;color:#ddd8d4;font-size:18px;line-height:1.65}.igf-opportunities__content{min-height:430px;padding:clamp(65px,8vw,105px) 0;background:var(--surface)}.igf-opportunities__heading{display:grid;grid-template-columns:minmax(0,1fr) minmax(250px,.75fr);align-items:end;gap:35px;margin-bottom:34px}.igf-opportunities__heading h2{margin:0;font:650 clamp(31px,4vw,46px)/1.13 'Literata',Georgia,serif}.igf-opportunities__heading p{margin:0;color:var(--muted);line-height:1.65}.igf-opportunities__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}.igf-opportunities__empty{padding:75px 20px;text-align:center}.igf-opportunities__empty>span{color:var(--orange);font-size:42px}.igf-opportunities__empty h2{margin:13px 0 7px;font:650 29px 'Literata',Georgia,serif}.igf-opportunities__empty p{color:var(--muted)}.igf-opportunities__pagination{margin-top:42px}@media(max-width:760px){.igf-opportunities__heading{grid-template-columns:1fr;gap:12px}.igf-opportunities__grid{grid-template-columns:1fr}}@media(max-width:600px){.igf-shell{width:min(calc(100% - 28px),1200px)}.igf-opportunities__hero{padding:72px 0}.igf-opportunities__hero span{font-size:16px}}
</style>
