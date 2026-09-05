<template>
  <Layout>
    <article v-if="report" class="igf-report-detail">
      <header class="igf-report-detail__hero">
        <div class="igf-shell">
          <a class="igf-back" :href="route('frontend.annual_report.index')"><span aria-hidden="true">&larr;</span> {{ settings.detail_back_label }}</a>
          <p>{{ settings.detail_eyebrow }}</p>
          <h1>{{ report.title }}</h1>
          <p v-if="report.sub_title" class="igf-report-detail__lead">{{ report.sub_title }}</p>
        </div>
      </header>

      <section class="igf-report-detail__body" :aria-labelledby="summaryId">
        <div class="igf-shell igf-report-detail__grid">
          <div class="igf-report-detail__summary">
            <figure v-if="report.image_url" class="igf-report-detail__cover">
              <img :src="report.image_url" :alt="coverAlt">
            </figure>
            <p class="igf-eyebrow">{{ settings.format_label }}</p>
            <h2 :id="summaryId">{{ settings.detail_summary_title }}</h2>
            <p>{{ report.summary }}</p>
          </div>

          <aside class="igf-report-detail__card" :aria-label="settings.report_column_label">
            <dl>
              <div v-if="report.year"><dt>{{ settings.detail_year_label }}</dt><dd>{{ number(report.year) }}</dd></div>
              <div><dt>{{ settings.detail_publisher_label }}</dt><dd>{{ report.publisher_name }}</dd></div>
              <div v-if="publishedDate"><dt>{{ settings.detail_release_label }}</dt><dd>{{ publishedDate }}</dd></div>
              <div v-if="fileSize"><dt>{{ settings.format_label }}</dt><dd>{{ fileDetails }}</dd></div>
            </dl>
            <a class="igf-download" :href="report.download_url"><i class="fa-solid fa-download" aria-hidden="true" /> {{ settings.detail_download_label }}</a>
            <small>{{ settings.detail_download_note }}</small>
            <a v-if="report.source_url" class="igf-source" :href="report.source_url" target="_blank" rel="noopener noreferrer">{{ settings.detail_source_label }} <span aria-hidden="true">&nearr;</span></a>
          </aside>
        </div>
      </section>
    </article>
  </Layout>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
import { formatDate, formatNumber, interpolateSetting } from '../Shared/composables/siteSettings';

const page = usePage();
const settings = computed(() => page.props.siteSettings?.reports_page || {});
const regional = computed(() => page.props.siteSettings?.regional || {});
const report = computed(() => page.props.data?.report || null);
const summaryId = 'annual-report-summary';
const publishedDate = computed(() => formatDate(report.value?.published_at, regional.value));
const number = value => formatNumber(value, regional.value);
const coverAlt = computed(() => {
  const title = String(report.value?.title || '').trim();
  return interpolateSetting(settings.value.detail_cover_alt_template, { title }).trim() || title;
});
const fileSize = computed(() => {
  const bytes = Number(report.value?.file_size || 0);
  if (!Number.isFinite(bytes) || bytes <= 0) return '';
  const units = [
    settings.value.detail_file_unit_bytes,
    settings.value.detail_file_unit_kilobytes,
    settings.value.detail_file_unit_megabytes,
    settings.value.detail_file_unit_gigabytes,
  ];
  const level = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  const value = bytes / (1024 ** level);
  const formatted = formatNumber(value, regional.value, { maximumFractionDigits: level ? 1 : 0 });
  const unit = String(units[level] || '').trim();
  return unit ? `${formatted} ${unit}` : formatted;
});
const fileDetails = computed(() => {
  const type = String(settings.value.detail_file_type_label || '').trim();
  if (!type) return fileSize.value;
  if (!fileSize.value) return type;
  const separator = String(settings.value.detail_file_separator || '').trim();
  return `${type}${separator ? ` ${separator} ` : ' '}${fileSize.value}`;
});
</script>

<style scoped>
.igf-report-detail{--orange:var(--igf-primary,#ff7500);--brown:var(--igf-accent,#9c4500);--action:var(--brown);--action-hover:var(--brown);--ink:var(--igf-ink,#191c1d);--surface:var(--igf-surface,#f8f9fa);--muted:color-mix(in srgb,var(--ink) 68%,var(--surface));--line:color-mix(in srgb,var(--ink) 14%,var(--surface));--brand-on-dark:color-mix(in srgb,var(--orange) 42%,#fff);color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell{width:min(100% - 40px,1120px);margin-inline:auto}.igf-report-detail__hero{padding:clamp(88px,11vw,140px) 0 clamp(80px,9vw,115px);background:#202223;color:#fff}.igf-back{display:inline-flex;gap:8px;margin-bottom:44px;color:var(--brand-on-dark);font-size:13px;font-weight:800;text-decoration:none}.igf-report-detail__hero>div>p:not(.igf-report-detail__lead){margin:0 0 14px;color:var(--brand-on-dark);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-report-detail h1{max-width:920px;margin:0;color:#fff;font:650 clamp(42px,6vw,70px)/1.06 'Literata',Georgia,serif;letter-spacing:-.035em}.igf-report-detail h1::after,.igf-report-detail h2::after{display:none!important}.igf-report-detail__lead{max-width:760px;margin:24px 0 0;color:#d5d6d7;font-size:20px;line-height:1.65}.igf-report-detail__body{padding:clamp(68px,9vw,110px) 0;background:var(--surface)}.igf-report-detail__grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,390px);align-items:start;gap:clamp(40px,7vw,90px)}.igf-report-detail__cover{overflow:hidden;margin:0 0 34px;border-radius:14px;background:color-mix(in srgb,var(--surface) 90%,var(--ink));box-shadow:0 14px 38px color-mix(in srgb,var(--ink) 10%,transparent)}.igf-report-detail__cover img{display:block;width:100%;aspect-ratio:16/10;object-fit:cover}.igf-eyebrow{margin:0 0 12px;color:var(--brown);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-report-detail__summary h2{margin:0;font:650 clamp(32px,4vw,48px)/1.1 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-report-detail__summary>p:last-child{margin:24px 0 0;color:var(--muted);font-size:18px;line-height:1.8;white-space:pre-line}.igf-report-detail__card{border:1px solid var(--line);border-top:4px solid var(--orange);border-radius:14px;padding:28px;background:#fff;box-shadow:0 14px 38px color-mix(in srgb,var(--ink) 8%,transparent)}.igf-report-detail__card dl{display:grid;gap:0;margin:0 0 25px}.igf-report-detail__card dl>div{padding:15px 0;border-bottom:1px solid var(--line)}.igf-report-detail__card dt{color:var(--muted);font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.igf-report-detail__card dd{margin:5px 0 0;color:var(--ink);font-weight:700}.igf-download{display:flex;align-items:center;justify-content:center;gap:9px;min-height:48px;border-radius:8px;background:var(--action);color:var(--igf-on-accent,#fff);font-weight:800;text-decoration:none}.igf-download:hover{background:var(--action-hover)}.igf-download:focus-visible,.igf-source:focus-visible{outline:3px solid var(--ink);outline-offset:3px}.igf-back:focus-visible{outline:3px solid #fff;outline-offset:3px}.igf-report-detail__card small{display:block;margin:12px 0 0;color:var(--muted);line-height:1.45}.igf-source{display:inline-flex;gap:6px;margin-top:22px;color:var(--brown);font-size:13px;font-weight:800;text-decoration:none}@media(max-width:760px){.igf-shell{width:min(100% - 28px,1120px)}.igf-report-detail__grid{grid-template-columns:1fr}.igf-report-detail__card{order:-1}.igf-report-detail__hero{padding-block:72px}}
</style>
