<template>
  <Layout>
    <div class="igf-events">
      <header class="igf-events__hero">
        <div class="igf-shell"><p>{{ settings.events_eyebrow }}</p><h1>{{ title }}</h1><span>{{ settings.events_introduction }}</span></div>
      </header>
      <section class="igf-events__content" :aria-label="settings.events_listing_label">
        <div class="igf-shell">
          <div v-if="items.length" class="igf-events__grid">
            <CategoryItemCard v-for="event in items" :key="event.uuid || event.id" :title="event.title"
              :subtitle="event.sub_title" :thumbnail="event.image_url" :image-alt="event.image_alt || event.title"
              :eyebrow="settings.event_card_eyebrow" :link-label="settings.event_card_link_label"
              :link="route('frontend.event', event.slug)" />
          </div>
          <div v-else class="igf-events__empty"><i class="fa-regular fa-calendar" aria-hidden="true" /><h2>{{ settings.events_empty_title }}</h2><p>{{ settings.events_empty_body }}</p></div>
          <v-pagination v-if="properties?.total_page > 1" :model-value="properties.events" :length="properties.total_page"
            class="igf-pagination" @update:model-value="onPageChange" />
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App';
import CategoryItemCard from '../Shared/category-item-card.vue';
const page = usePage();
const settings = computed(() => page.props.siteSettings?.content_archives || {});
const title = computed(() => settings.value.events_default_title || page.props.title);
const properties = computed(() => page.props.properties || {});
const items = computed(() => page.props.data?.items || []);
function onPageChange(number) { router.get(page.url, {page:number}, {preserveState:true, preserveScroll:true}); }
</script>

<style scoped lang="scss">
.igf-events{--orange:var(--igf-primary,#ff7500);--brown:var(--igf-accent,#9c4500);--ink:var(--igf-ink,#191c1d);--muted:color-mix(in srgb,var(--ink) 68%,var(--surface));--surface:var(--igf-surface,#f8f9fa);color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell{width:min(calc(100% - 40px),var(--igf-content-width,1200px));margin-inline:auto}.igf-events__hero{padding:var(--igf-section-block,clamp(80px,10vw,130px)) 0;background:#242220;color:#fff}.igf-events__hero p{margin:0 0 15px;color:color-mix(in srgb,var(--orange) 42%,#fff);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-events__hero h1{max-width:850px;margin:0;color:#fff;font:650 var(--igf-heading-1,clamp(44px,6vw,72px))/1.05 'Literata',Georgia,serif;letter-spacing:-.035em}.igf-events__hero h1::after{display:none!important}.igf-events__hero span{display:block;max-width:700px;margin-top:22px;color:#d6d2cf;font-size:var(--igf-lead-size,19px);line-height:1.65}.igf-events__content{min-height:450px;padding:var(--igf-section-block,clamp(70px,8vw,110px)) 0;background:var(--surface)}.igf-events__grid{display:grid;grid-template-columns:repeat(var(--igf-card-columns,3),minmax(0,1fr));gap:25px}.igf-events__empty{padding:80px 20px;text-align:center}.igf-events__empty i{color:var(--orange);font-size:40px}.igf-events__empty h2{margin:15px 0 8px;font:650 30px 'Literata',Georgia,serif}.igf-events__empty p{color:var(--muted)}.igf-pagination{margin-top:45px}@media(max-width:900px){.igf-events__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.igf-shell{width:min(calc(100% - 28px),1200px)}.igf-events__grid{grid-template-columns:1fr}}
</style>
