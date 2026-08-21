<template>
  <Layout>
    <div class="igf-reports">
      <header class="igf-reports__hero">
        <div class="igf-shell"><p>{{ settings.eyebrow }}</p><h1>{{ settings.title }}</h1><span>{{ settings.introduction }}</span></div>
      </header>
      <section class="igf-reports__body" aria-labelledby="report-library-title">
        <div class="igf-shell">
          <div class="igf-reports__heading"><div><p>{{ settings.library_eyebrow }}</p><h2 id="report-library-title">{{ settings.library_title }}</h2></div><span>{{ formattedReportCount }} {{ reportCount === 1 ? settings.document_singular : settings.document_plural }}</span></div>
          <form class="igf-reports__filters" role="search" @submit.prevent="applyFilters">
            <label for="report-search">{{ settings.search_label }}<input id="report-search" v-model="search" type="search" :placeholder="settings.search_placeholder"></label>
            <label for="report-date">{{ settings.date_label }}<input id="report-date" v-model="publishedAt" type="date"></label>
            <button type="submit">{{ settings.apply_label }}</button>
            <button v-if="search || publishedAt" type="button" class="igf-clear" @click="clearFilters">{{ settings.clear_label }}</button>
          </form>

          <div v-if="items.length" class="igf-report-table-wrap">
            <table>
              <thead><tr><th scope="col">{{ settings.number_column_label }}</th><th scope="col">{{ settings.report_column_label }}</th><th scope="col">{{ settings.released_column_label }}</th><th scope="col"><span class="sr-only">{{ settings.download_column_label }}</span></th></tr></thead>
              <tbody>
                <tr v-for="(item,index) in items" :key="item.uuid || item.id">
                  <td>{{ number(firstItem + index) }}</td>
                  <td><i class="fa-regular fa-file-pdf" aria-hidden="true" /><div><strong><a :href="item.landing_url">{{ item.title }}</a></strong><small>{{ settings.format_label }}</small></div></td>
                  <td>{{ reportDate(item) || settings.unknown_date_label }}</td>
                  <td><div class="igf-report-actions"><a :href="item.landing_url">{{ settings.view_label }} <span aria-hidden="true">&rarr;</span></a><a :href="item.download_url">{{ settings.download_label }} <i class="fa-solid fa-download" aria-hidden="true" /></a></div></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="igf-reports__empty"><i class="fa-regular fa-file-lines" aria-hidden="true" /><h2>{{ settings.empty_title }}</h2><p>{{ settings.empty_body }}</p></div>
          <v-pagination v-if="properties.total_page > 1" :model-value="properties.current_page" :length="properties.total_page" class="igf-pagination" @update:model-value="onPageChange" />
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App';
import { formatDate, formatNumber } from '../Shared/composables/siteSettings';
const page = usePage();
const settings = computed(() => page.props.siteSettings?.reports_page || {});
const regional = computed(() => page.props.siteSettings?.regional || {});
const search = ref(page.props.data?.search || '');
const publishedAt = ref(page.props.data?.published_at || '');
const items = computed(() => page.props.data?.items || []);
const properties = computed(() => page.props.properties || {});
const reportCount = computed(() => Number(properties.value.total ?? items.value.length));
const formattedReportCount = computed(() => formatNumber(reportCount.value, regional.value));
const firstItem = computed(() => ((Number(properties.value.current_page || 1) - 1) * Number(properties.value.per_page || items.value.length || 1)) + 1);
const number = value => formatNumber(value, regional.value);
const reportDate = item => formatDate(item?.published_at, regional.value);
function visit(filters) { router.get(route('frontend.annual_report.index'), filters, {preserveState:true,preserveScroll:true,replace:true}); }
function applyFilters() { visit({search:search.value,published_at:publishedAt.value}); }
function clearFilters() { search.value='';publishedAt.value='';visit({}); }
function onPageChange(pageNumber) { visit({page:pageNumber,search:search.value,published_at:publishedAt.value}); }
</script>

<style scoped lang="scss">
.igf-reports{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#5e5d66;--surface:#f8f9fa;--line:#e5e0dc;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell{width:min(calc(100% - 40px),1200px);margin-inline:auto}.igf-reports__hero{padding:clamp(80px,10vw,130px) 0;background:#242220;color:#fff}.igf-reports__hero p,.igf-reports__heading p{margin:0 0 14px;color:#ffad72;font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-reports__hero h1{max-width:800px;margin:0;color:#fff;font:650 clamp(46px,6vw,74px)/1.04 'Literata',Georgia,serif;letter-spacing:-.035em}.igf-reports__hero h1::after,.igf-reports h2::after{display:none!important}.igf-reports__hero span{display:block;max-width:680px;margin-top:22px;color:#d7d3cf;font-size:19px;line-height:1.65}.igf-reports__body{min-height:480px;padding:clamp(70px,9vw,115px) 0;background:var(--surface)}.igf-reports__heading{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:30px}.igf-reports__heading p{color:var(--brown)}.igf-reports__heading h2{margin:0;font:650 clamp(32px,4vw,46px)/1.1 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-reports__heading>span{color:var(--muted);font-size:13px;font-weight:800}.igf-reports__filters{display:grid;grid-template-columns:1fr 240px auto auto;align-items:end;gap:12px;margin-bottom:25px;border:1px solid var(--line);border-radius:12px;padding:18px;background:#fff}.igf-reports__filters label{display:grid;gap:7px;color:var(--ink);font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.igf-reports__filters input{width:100%;height:43px;border:1px solid #d3cec9;border-radius:7px;padding:8px 11px;background:#fff;color:var(--ink);font:14px 'Hanken Grotesk',Arial,sans-serif;text-transform:none}.igf-reports__filters button{height:43px;border:1px solid var(--orange);border-radius:7px;padding:0 18px;background:var(--orange);color:#fff;font-weight:800;cursor:pointer}.igf-reports__filters .igf-clear{border-color:#b9b1aa;background:#fff;color:var(--brown)}.igf-report-table-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:12px;background:#fff}.igf-report-table-wrap table{width:100%;min-width:720px;border-collapse:collapse}.igf-report-table-wrap th{padding:15px 20px;border-bottom:1px solid var(--line);background:#f1f2f3;color:#6d6966;font-size:10px;letter-spacing:.08em;text-align:left;text-transform:uppercase}.igf-report-table-wrap td{padding:20px;border-bottom:1px solid #eeeae7;color:var(--muted);font-size:13px}.igf-report-table-wrap tbody tr:last-child td{border:0}.igf-report-table-wrap td:nth-child(2){display:flex;align-items:center;gap:14px;color:var(--ink)}.igf-report-table-wrap td:nth-child(2)>i{color:var(--orange);font-size:27px}.igf-report-table-wrap strong,.igf-report-table-wrap small{display:block}.igf-report-table-wrap small{margin-top:4px;color:var(--muted)}.igf-report-table-wrap td:last-child{text-align:right}.igf-report-table-wrap a{display:inline-flex;align-items:center;gap:8px;color:var(--brown);font-weight:800;text-decoration:none}.igf-reports__empty{padding:80px 20px;text-align:center}.igf-reports__empty i{color:var(--orange);font-size:38px}.igf-reports__empty h2{margin:15px 0 7px;font:650 30px 'Literata',Georgia,serif}.igf-reports__empty p{color:var(--muted)}.igf-pagination{margin-top:40px}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}@media(max-width:850px){.igf-reports__filters{grid-template-columns:1fr 1fr}.igf-reports__filters button{width:100%}}@media(max-width:600px){.igf-shell{width:min(calc(100% - 28px),1200px)}.igf-reports__heading{align-items:start;flex-direction:column}.igf-reports__filters{grid-template-columns:1fr}.igf-reports__hero{padding-block:72px}}
</style>
<style scoped>
.igf-report-table-wrap strong a{color:var(--ink)}
.igf-report-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px 16px}
.igf-reports__filters button{border-color:var(--brown);background:var(--brown)}
.igf-reports__filters button:hover{border-color:#783300;background:#783300}
.igf-reports__filters .igf-clear:hover{border-color:#8c8279;background:#f1eeeb;color:#783300}
.igf-reports__filters button:focus-visible,.igf-report-table-wrap a:focus-visible{outline:3px solid var(--ink);outline-offset:3px}
</style>
