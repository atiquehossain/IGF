<!-- Rich text is sanitized by ContentSanitizer before it reaches this view. -->
<!-- eslint-disable vue/no-v-html -->
<template>
  <Layout>
    <PageBlocks v-if="landingBlocks.length" :blocks="landingBlocks" />
    <div v-else class="igf-listing" :class="{ 'igf-listing--awards': isAwardsCategory }">
      <section class="igf-listing__intro">
        <div class="igf-shell" :class="{ 'igf-awards-hero': isAwardsCategory }">
          <div class="igf-listing__intro-copy">
            <p class="igf-eyebrow">{{ isAwardsCategory ? settings.awards_eyebrow : settings.category_eyebrow }}</p>
            <h1>{{ category?.name || settings.category_default_title }}</h1>
            <article v-if="category?.description" v-html="category.description" />
          </div>
          <div v-if="isAwardsCategory" class="igf-awards-count" :aria-label="`${awardCount} ${awardCountLabel}`">
            <strong>{{ awardCount }}</strong>
            <span>{{ awardCountLabel }}</span>
          </div>
        </div>
      </section>
      <section class="igf-listing__content" :aria-label="settings.category_listing_label">
        <div class="igf-shell">
          <header v-if="isAwardsCategory && items.length" class="igf-awards-intro">
            <div>
              <p class="igf-eyebrow">{{ settings.awards_listing_eyebrow }}</p>
              <h2>{{ settings.awards_listing_title }}</h2>
            </div>
            <p>{{ settings.awards_listing_body }}</p>
          </header>
          <div v-if="items.length" class="igf-card-grid" :class="{
            'igf-card-grid--awards': isAwardsCategory,
            'igf-card-grid--five': isAwardsCategory && items.length === 5,
          }">
            <CategoryItemCard v-for="(post, index) in items" :key="post.uuid || post.id" :title="post.name"
              :subtitle="post.sub_title" :thumbnail="post.thumbnail" :image-alt="post.thumbnail_alt || post.name"
              :eyebrow="isAwardsCategory ? settings.awards_card_eyebrow : settings.category_card_eyebrow"
              :link-label="isAwardsCategory ? settings.awards_card_link_label : settings.category_card_link_label"
              :link="post.public_url || route('frontend.page', post.slug)" :variant="isAwardsCategory ? 'award' : 'default'"
              :ordinal="isAwardsCategory ? index + 1 : 0" />
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
const isAwardsCategory = computed(() => Boolean(inertiaPage.props.data?.is_awards_category));
const awardCount = computed(() => Number(properties.value?.total_count ?? items.value.length));
const awardCountLabel = computed(() => awardCount.value === 1
  ? (settings.value.awards_count_singular || 'recognition')
  : (settings.value.awards_count_plural || 'recognitions'));
function onPageChange(page) { router.get(inertiaPage.url, { page }, { preserveState:true, preserveScroll:true }); }
</script>

<style scoped lang="scss">
.igf-listing { --orange:var(--igf-primary,#ff7500);--brown:var(--igf-accent,#9c4500);--ink:var(--igf-ink,#191c1d);--surface:var(--igf-surface,#f8f9fa);--muted:color-mix(in srgb,var(--ink) 68%,var(--surface)); color:var(--ink); font-family:'Hanken Grotesk',Arial,sans-serif; }
.igf-shell { width:min(calc(100% - 40px),var(--igf-content-width,1200px)); margin-inline:auto; }
.igf-listing__intro { padding:clamp(70px,8vw,105px) 0; background:#fff; }
.igf-eyebrow { margin:0 0 13px; color:var(--brown); font-size:11px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
.igf-listing h1 { margin:0; font:650 clamp(40px,5vw,58px)/1.08 'Literata',Georgia,serif; letter-spacing:-.03em; }
.igf-listing h1::after,.igf-listing h2::after { display:none!important; }
.igf-listing__intro article { max-width:780px; margin-top:22px; color:var(--muted); font-size:17px; line-height:1.72; }
.igf-listing__content { min-height:420px; padding:clamp(65px,8vw,105px) 0; background:var(--surface); }
.igf-card-grid { display:grid; grid-template-columns:repeat(var(--igf-card-columns,3),minmax(0,1fr)); gap:25px; }
.igf-empty { padding:80px 20px; text-align:center; }
.igf-empty i { color:var(--orange); font-size:38px; }
.igf-empty h2 { margin:15px 0 7px; font:650 30px 'Literata',Georgia,serif; }
.igf-empty p { color:var(--muted); }
.igf-pagination { margin-top:45px; }
.igf-listing--awards .igf-listing__intro { position:relative; overflow:hidden; padding:clamp(54px,7vw,86px) 0; background:radial-gradient(circle at 86% 18%,color-mix(in srgb,var(--orange) 28%,transparent),transparent 24%),linear-gradient(135deg,#211f1d 0%,#302b27 58%,#432b1c 100%); }
.igf-listing--awards .igf-listing__intro::after { position:absolute; right:-90px; bottom:-180px; width:360px; height:360px; border:1px solid rgba(255,255,255,.12); border-radius:50%; content:''; }
.igf-awards-hero { position:relative; z-index:1; display:grid; grid-template-columns:minmax(0,1fr) auto; gap:clamp(35px,7vw,90px); align-items:center; }
.igf-listing--awards .igf-eyebrow { color:color-mix(in srgb,var(--orange) 42%,#fff); }
.igf-listing--awards h1 { max-width:760px; color:#fff; font-size:clamp(46px,6vw,72px); }
.igf-listing--awards .igf-listing__intro article { max-width:720px; margin-top:18px; color:rgba(255,255,255,.76); font-size:18px; }
.igf-listing--awards .igf-listing__intro article :deep(p) { margin:0; }
.igf-awards-count { display:flex; width:158px; aspect-ratio:1; flex-direction:column; align-items:center; justify-content:center; border:1px solid rgba(255,255,255,.28); border-radius:50%; background:rgba(255,255,255,.08); color:#fff; text-align:center; backdrop-filter:blur(8px); }
.igf-awards-count strong { font:650 52px/1 'Literata',Georgia,serif; }
.igf-awards-count span { max-width:100px; margin-top:8px; color:color-mix(in srgb,var(--orange) 28%,#fff); font-size:10px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
.igf-listing--awards .igf-listing__content { padding:clamp(58px,7vw,90px) 0; background:linear-gradient(180deg,color-mix(in srgb,var(--orange) 6%,var(--surface)) 0%,var(--surface) 48%,color-mix(in srgb,var(--surface) 28%,#fff) 100%); }
.igf-awards-intro { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(260px,.9fr); gap:clamp(30px,8vw,110px); align-items:end; margin-bottom:38px; }
.igf-awards-intro .igf-eyebrow { color:var(--brown); }
.igf-awards-intro h2 { max-width:700px; margin:0; font:650 clamp(31px,4vw,44px)/1.12 'Literata',Georgia,serif; letter-spacing:-.03em; }
.igf-awards-intro>p { margin:0; color:var(--muted); font-size:16px; line-height:1.7; }
.igf-card-grid--awards { grid-template-columns:repeat(6,minmax(0,1fr)); gap:28px; }
.igf-card-grid--awards > * { grid-column:span 2; }
.igf-card-grid--five > *:nth-child(4) { grid-column:2/span 2; }
@media(max-width:900px){.igf-card-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.igf-awards-hero{grid-template-columns:minmax(0,1fr) auto}.igf-awards-count{width:132px}.igf-awards-count strong{font-size:44px}.igf-awards-intro{grid-template-columns:1fr;gap:16px}.igf-card-grid--awards{grid-template-columns:repeat(2,minmax(0,1fr))}.igf-card-grid--awards>*{grid-column:auto}.igf-card-grid--five>*:nth-child(4){grid-column:auto}.igf-card-grid--five>*:nth-child(5){grid-column:1/-1;width:calc((100% - 28px)/2);justify-self:center}}
@media(max-width:600px){.igf-shell{width:min(calc(100% - 28px),1200px)}.igf-card-grid{grid-template-columns:1fr}.igf-awards-hero{grid-template-columns:1fr;gap:28px}.igf-awards-count{width:112px}.igf-awards-count strong{font-size:38px}.igf-listing--awards h1{font-size:clamp(42px,13vw,56px)}.igf-card-grid--awards{grid-template-columns:1fr}.igf-card-grid--five>*:nth-child(5){grid-column:auto;width:100%}}
</style>
