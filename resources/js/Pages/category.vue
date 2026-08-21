<!-- Rich text is sanitized by ContentSanitizer before it reaches this view. -->
<!-- eslint-disable vue/no-v-html -->
<template>
  <Layout>
    <PageBlocks v-if="landingBlocks.length" :blocks="landingBlocks" />
    <div v-else class="igf-listing">
      <section class="igf-listing__intro">
        <div class="igf-shell">
          <p class="igf-eyebrow">{{ settings.category_eyebrow }}</p>
          <h1>{{ category?.name || settings.category_default_title }}</h1>
          <article v-if="category?.description" v-html="category.description" />
        </div>
      </section>
      <section class="igf-listing__content" :aria-label="settings.category_listing_label">
        <div class="igf-shell">
          <div v-if="items.length" class="igf-card-grid">
            <CategoryItemCard v-for="post in items" :key="post.uuid || post.id" :title="post.name"
              :subtitle="post.sub_title" :thumbnail="post.thumbnail" :image-alt="post.thumbnail_alt || post.name"
              :eyebrow="settings.category_card_eyebrow" :link-label="settings.category_card_link_label"
              :link="route('frontend.page', post.slug)" />
          </div>
          <div v-else class="igf-empty"><i class="fa-regular fa-folder-open" aria-hidden="true" /><h2>{{ settings.category_empty_title }}</h2><p>{{ settings.category_empty_body }}</p></div>
          <v-pagination v-if="properties?.total_page > 1" :model-value="properties.page" :length="properties.total_page"
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
import PageBlocks from '../Shared/PageBlocks.vue';
const inertiaPage = usePage();
const settings = computed(() => inertiaPage.props.siteSettings?.content_archives || {});
const category = computed(() => inertiaPage.props.data?.category || null);
const landingPage = computed(() => inertiaPage.props.data?.landing_page || null);
const landingBlocks = computed(() => landingPage.value?.visible_blocks || []);
const items = computed(() => inertiaPage.props.data?.items || []);
const properties = computed(() => inertiaPage.props.properties || {});
function onPageChange(page) { router.get(inertiaPage.url, { page }, { preserveState:true, preserveScroll:true }); }
</script>

<style scoped lang="scss">
.igf-listing { --orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#5e5d66;--surface:#f8f9fa; color:var(--ink); font-family:'Hanken Grotesk',Arial,sans-serif; }
.igf-shell { width:min(calc(100% - 40px),var(--igf-content-width,1200px)); margin-inline:auto; }
.igf-listing__intro { padding:clamp(70px,8vw,105px) 0; background:#fff; }
.igf-eyebrow { margin:0 0 13px; color:var(--brown); font-size:11px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
.igf-listing h1 { margin:0; font:650 clamp(40px,5vw,58px)/1.08 'Literata',Georgia,serif; letter-spacing:-.03em; }
.igf-listing h1::after { display:none!important; }
.igf-listing__intro article { max-width:780px; margin-top:22px; color:var(--muted); font-size:17px; line-height:1.72; }
.igf-listing__content { min-height:420px; padding:clamp(65px,8vw,105px) 0; background:var(--surface); }
.igf-card-grid { display:grid; grid-template-columns:repeat(var(--igf-card-columns,3),minmax(0,1fr)); gap:25px; }
.igf-empty { padding:80px 20px; text-align:center; }
.igf-empty i { color:var(--orange); font-size:38px; }
.igf-empty h2 { margin:15px 0 7px; font:650 30px 'Literata',Georgia,serif; }
.igf-empty p { color:var(--muted); }
.igf-pagination { margin-top:45px; }
@media(max-width:900px){.igf-card-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.igf-shell{width:min(calc(100% - 28px),1200px)}.igf-card-grid{grid-template-columns:1fr}}
</style>
