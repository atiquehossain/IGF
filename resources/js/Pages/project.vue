<template>
  <Layout>
    <div class="igf-projects">
      <section class="igf-projects__intro">
        <div class="igf-shell">
          <p class="igf-eyebrow">{{ settings.project_eyebrow }}</p>
          <h1>{{ tag?.name || settings.project_default_title }}</h1>
          <p v-if="tag?.description">{{ plainText(tag.description) }}</p>
          <small>{{ formattedTotalCount }} {{ totalCount === 1 ? settings.project_count_singular : settings.project_count_plural }}</small>
        </div>
      </section>
      <section class="igf-projects__listing" :aria-label="settings.project_listing_label">
        <div class="igf-shell">
          <div v-if="items.length" class="igf-project-grid">
            <CategoryItemCard v-for="item in items" :key="item.uuid || item.id" :title="item.name" :subtitle="item.sub_title || plainText(item.description)" :thumbnail="item.thumbnail" :image-alt="item.thumbnail_alt || item.name" :eyebrow="settings.project_card_eyebrow" :link-label="settings.project_card_link_label" :link="route('frontend.page', item.slug)" />
          </div>
          <div v-else class="igf-empty"><i class="fa-regular fa-folder-open" aria-hidden="true" /><h2>{{ settings.project_empty_title }}</h2><p>{{ settings.project_empty_body }}</p></div>
          <v-pagination v-if="properties.total_page > 1" :model-value="properties.page" :length="properties.total_page" class="igf-pagination" @update:model-value="onPageChange" />
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
import CategoryItemCard from '../Shared/category-item-card.vue';
import { formatNumber } from '../Shared/composables/siteSettings';
const page = usePage();
const settings = computed(() => page.props.siteSettings?.content_archives || {});
const regional = computed(() => page.props.siteSettings?.regional || {});
const tag = computed(() => page.props.data?.tag || null);
const items = computed(() => page.props.data?.items || []);
const properties = computed(() => page.props.properties || {});
const totalCount = computed(() => Number(properties.value.total_count || 0));
const formattedTotalCount = computed(() => formatNumber(totalCount.value, regional.value));
const plainText = value => String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
function onPageChange(pageNumber) { router.get(page.url, { page: pageNumber }, { preserveScroll: true, preserveState: true }); }
</script>

<style scoped>
.igf-projects{--ink:var(--igf-ink,#191c1d);--muted:color-mix(in srgb,var(--ink) 68%,var(--surface));--brown:var(--igf-accent,#9c4500);--orange:var(--igf-primary,#ff7500);--surface:var(--igf-surface,#f8f9fa);color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell{width:min(100% - 40px,var(--igf-content-width,1200px));margin:0 auto}.igf-projects__intro{padding:var(--igf-section-block,clamp(70px,8vw,105px)) 0;background:#fff}.igf-eyebrow{margin:0 0 13px;color:var(--brown);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-projects h1{margin:0;color:var(--ink);font:650 var(--igf-heading-1,clamp(40px,5vw,58px))/1.08 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-projects h1::after{display:none!important}.igf-projects__intro>div>p:not(.igf-eyebrow){max-width:760px;margin:20px 0 12px;color:var(--muted);font-size:var(--igf-body-size,17px);line-height:1.7}.igf-projects__intro small{color:var(--brown);font-weight:800}.igf-projects__listing{min-height:400px;padding:var(--igf-section-block,clamp(65px,8vw,105px)) 0;background:var(--surface)}.igf-project-grid{display:grid;grid-template-columns:repeat(var(--igf-card-columns,3),minmax(0,1fr));gap:25px}.igf-empty{padding:70px 20px;text-align:center}.igf-empty i{color:var(--orange);font-size:38px}.igf-empty h2{margin:15px 0 7px;font:650 30px 'Literata',Georgia,serif}.igf-empty p{color:var(--muted)}.igf-pagination{margin-top:44px}
@media(max-width:900px){.igf-project-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.igf-shell{width:min(100% - 28px,1200px)}.igf-project-grid{grid-template-columns:1fr}}
</style>
