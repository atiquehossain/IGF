<template>
  <Layout>
    <div class="igf-search">
      <section class="igf-search__hero">
        <div class="igf-shell"><p class="igf-eyebrow">{{ settings.eyebrow }}</p><h1>{{ settings.title }}</h1><p>{{ settings.introduction }}</p></div>
      </section>
      <section class="igf-search__results">
        <div class="igf-shell">
          <form class="igf-search-form" role="search" @submit.prevent="submitSearch">
            <label class="sr-only" for="site-search">{{ settings.accessible_label }}</label>
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
            <input id="site-search" v-model="query" type="search" name="search" :placeholder="settings.placeholder" autofocus>
            <button type="submit">{{ settings.button_label }}</button>
          </form>
          <div class="igf-search__summary" aria-live="polite">
            <p v-if="properties.search"><strong>{{ formattedTotalCount }}</strong> {{ settings.results_for_label }} <q>{{ properties.search }}</q></p>
            <p v-else>{{ settings.prompt }}</p>
          </div>
          <div v-if="pages.length" class="igf-search-list">
            <article v-for="item in pages" :key="item.id">
              <p>{{ typeLabel(item.view_type) }}</p>
              <h2><a :href="resultUrl(item)">{{ item.name }}</a></h2>
              <p>{{ excerpt(item.description || item.sub_title) }}</p>
              <a class="igf-result-link" :href="resultUrl(item)">{{ settings.result_link_label }} <span aria-hidden="true">&rarr;</span></a>
            </article>
          </div>
          <div v-else-if="properties.search" class="igf-empty"><i class="fa-solid fa-magnifying-glass" aria-hidden="true" /><h2>{{ settings.empty_title }}</h2><p>{{ settings.empty_body }}</p></div>
          <v-pagination v-if="properties.total_page > 1" :model-value="properties.page" :length="properties.total_page" class="igf-pagination" @update:model-value="onPageChange" />
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
import { formatNumber } from '../Shared/composables/siteSettings';
const page = usePage();
const settings = computed(() => page.props.siteSettings?.search_page || {});
const regional = computed(() => page.props.siteSettings?.regional || {});
const pages = computed(() => page.props.data?.pages || []);
const properties = computed(() => page.props.properties || {});
const formattedTotalCount = computed(() => formatNumber(properties.value.total_count || 0, regional.value));
const query = ref(properties.value.search || '');
const excerpt = value => {
  const plain = String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  return plain.length > 190 ? `${plain.slice(0, 190)}...` : plain;
};
const typeLabel = type => ({
  page: settings.value.page_type_label,
  program: settings.value.program_type_label,
  event: settings.value.event_type_label,
  report: settings.value.report_type_label,
  gallery: settings.value.gallery_type_label,
}[type] || settings.value.default_type_label || 'Published content');
function resultUrl(item) { return item.result_url || '/'; }
function submitSearch() { router.get(route('search'), { search: query.value || undefined }); }
function onPageChange(pageNumber) { router.get(route('search'), { search: properties.value.search, page: pageNumber }, { preserveScroll: true, preserveState: true }); }
</script>

<style scoped>
.igf-search{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#5f6065;--surface:#f8f9fa;--line:#dedbd7;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell{width:min(100% - 40px,960px);margin:0 auto}.igf-search__hero{padding:clamp(95px,12vw,145px) 0 110px;background:#202223;color:#fff}.igf-eyebrow{margin:0 0 14px!important;color:#ffb070!important;font-size:11px!important;font-weight:800!important;letter-spacing:.1em;text-transform:uppercase}.igf-search h1{margin:0;color:#fff;font:650 clamp(44px,6vw,70px)/1.05 'Literata',Georgia,serif;letter-spacing:-.035em}.igf-search h1::after,.igf-search h2::after{display:none!important}.igf-search__hero>div>p:not(.igf-eyebrow){margin:20px 0 0;color:#d5d6d7;font-size:19px}.igf-search__results{min-height:500px;padding:0 0 clamp(75px,9vw,115px);background:var(--surface)}.igf-search-form{position:relative;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:12px;transform:translateY(-34px);padding:10px 10px 10px 22px;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:0 14px 36px rgba(25,28,29,.12)}.igf-search-form>i{color:var(--muted)}.igf-search-form input{min-width:0;height:48px;border:0;outline:0;color:var(--ink);font:17px 'Hanken Grotesk',Arial,sans-serif}.igf-search-form button{height:48px;padding:0 27px;border:0;border-radius:999px;background:var(--orange);color:#fff;font-weight:800}.igf-search__summary{margin:-3px 0 30px;color:var(--muted)}.igf-search__summary strong{color:var(--ink)}.igf-search-list{display:grid;gap:14px}.igf-search-list article{padding:28px 30px;border:1px solid var(--line);border-left:4px solid var(--orange);border-radius:12px;background:#fff}.igf-search-list article>p:first-child{margin:0 0 9px;color:var(--brown);font-size:10px;font-weight:800;letter-spacing:.09em;text-transform:uppercase}.igf-search-list h2{margin:0;font:650 25px/1.25 'Literata',Georgia,serif}.igf-search-list h2 a{color:var(--ink);text-decoration:none}.igf-search-list article>p:nth-of-type(2){margin:13px 0;color:var(--muted);line-height:1.65}.igf-result-link{color:var(--brown);font-size:13px;font-weight:800;text-decoration:none}.igf-empty{padding:75px 20px;text-align:center}.igf-empty i{color:var(--orange);font-size:38px}.igf-empty h2{margin:15px 0 7px;font:650 30px 'Literata',Georgia,serif}.igf-empty p{color:var(--muted)}.igf-pagination{margin-top:40px}.sr-only{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important}
@media(max-width:600px){.igf-shell{width:min(100% - 28px,960px)}.igf-search-form{grid-template-columns:auto 1fr;padding:12px 14px}.igf-search-form button{grid-column:1/-1;width:100%}.igf-search-list article{padding:24px 20px}}
</style>
