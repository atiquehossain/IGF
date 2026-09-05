<template>
  <Layout>
    <div class="igf-reports">
      <header class="igf-reports__hero">
        <div class="igf-shell"><p>{{ settings.eyebrow }}</p><h1>{{ settings.title }}</h1><span>{{ settings.introduction }}</span></div>
      </header>
      <section class="igf-reports__body" aria-labelledby="report-library-title">
        <div class="igf-shell">
          <div class="igf-reports__heading">
            <div><p>{{ settings.library_eyebrow }}</p><h2 id="report-library-title">{{ settings.library_title }}</h2></div>
            <span class="igf-report-count" role="status" aria-live="polite" aria-atomic="true">{{ formattedReportCount }} {{ reportCount === 1 ? settings.document_singular : settings.document_plural }}</span>
          </div>
          <form class="igf-reports__filters" role="search" @submit.prevent="applyFilters">
            <label for="report-search">{{ settings.search_label }}<input id="report-search" v-model="search" type="search" :placeholder="settings.search_placeholder"></label>
            <label for="report-date">{{ settings.date_label }}<input id="report-date" v-model="publishedAt" type="date"></label>
            <button type="submit">{{ settings.apply_label }}</button>
            <button v-if="search || publishedAt" type="button" class="igf-clear" @click="clearFilters">{{ settings.clear_label }}</button>
          </form>

          <ul v-if="items.length" class="igf-report-grid" role="list">
            <li v-for="item in items" :key="item.uuid || item.id">
              <article class="igf-report-card">
                <div class="igf-report-card__cover">
                  <img
                    v-if="reportCoverUrl(item)"
                    :src="reportCoverUrl(item)"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    @error="markCoverFailed(item)"
                  >
                  <div v-else class="igf-report-card__fallback" aria-hidden="true">
                    <span class="igf-report-card__brand">{{ organizationName }}</span>
                    <i class="fa-regular fa-file-lines" />
                    <strong>{{ settings.title || settings.format_label }}</strong>
                    <span class="igf-report-card__year">{{ reportYear(item) || '—' }}</span>
                  </div>
                </div>
                <div class="igf-report-card__body">
                  <div class="igf-report-card__meta">
                    <span><i class="fa-regular fa-file-pdf" aria-hidden="true" />{{ settings.format_label }}</span>
                    <time v-if="reportDate(item)" :datetime="item.published_at">{{ reportDate(item) }}</time>
                    <span v-else>{{ settings.unknown_date_label }}</span>
                  </div>
                  <h3>{{ item.title }}</h3>
                  <p v-if="reportSummary(item)">{{ reportSummary(item) }}</p>
                  <div class="igf-report-card__actions">
                    <a class="igf-report-card__primary" :href="item.landing_url" :aria-label="actionLabel(settings.view_label, item.title)">
                      {{ settings.view_label }} <span aria-hidden="true">&rarr;</span>
                    </a>
                    <a class="igf-report-card__secondary" :href="item.download_url" :aria-label="actionLabel(settings.download_label, item.title)">
                      <i class="fa-solid fa-download" aria-hidden="true" /> {{ settings.download_label }}
                    </a>
                  </div>
                </div>
              </article>
            </li>
          </ul>
          <div v-else class="igf-reports__empty" role="status" aria-live="polite"><i class="fa-regular fa-file-lines" aria-hidden="true" /><h3>{{ settings.empty_title }}</h3><p>{{ settings.empty_body }}</p></div>
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
const organizationName = computed(() => page.props.siteSettings?.branding?.site_name || page.props.appName || 'Ignite Global Foundation');
const search = ref(page.props.data?.search || '');
const publishedAt = ref(page.props.data?.published_at || '');
const items = computed(() => page.props.data?.items || []);
const properties = computed(() => page.props.properties || {});
const reportCount = computed(() => Number(properties.value.total ?? items.value.length));
const formattedReportCount = computed(() => formatNumber(reportCount.value, regional.value));
const failedCovers = ref(new Set());
const reportDate = item => formatDate(item?.published_at, regional.value);
const reportSummary = item => item?.sub_title || item?.summary || '';
const itemKey = item => String(item?.uuid || item?.id || item?.slug || item?.title || '');
const reportYear = item => {
  const dateYear = String(item?.published_at || '').match(/^(\d{4})/)?.[1];
  const titleYear = String(item?.title || '').match(/\b(20\d{2})\b/)?.[1];
  const year = dateYear || titleYear;
  return year ? formatNumber(Number(year), regional.value, { useGrouping: false }) : '';
};
const reportCoverUrl = item => {
  if (failedCovers.value.has(itemKey(item))) return '';
  const value = String(item?.image_url || '').trim();
  return /^\/(?!\/)/.test(value) ? value : '';
};
function markCoverFailed(item) {
  failedCovers.value = new Set([...failedCovers.value, itemKey(item)]);
}
const actionLabel = (label, title) => `${label || ''}: ${title || ''}`;
function visit(filters) { router.get(route('frontend.annual_report.index'), filters, {preserveState:true,preserveScroll:true,replace:true}); }
function applyFilters() { visit({search:search.value,published_at:publishedAt.value}); }
function clearFilters() { search.value='';publishedAt.value='';visit({}); }
function onPageChange(pageNumber) { visit({page:pageNumber,search:search.value,published_at:publishedAt.value}); }
</script>

<style scoped lang="scss">
.igf-reports {
  --orange: var(--igf-primary, #ff7500);
  --brown: var(--igf-accent, #9c4500);
  --brown-dark: var(--brown);
  --ink: var(--igf-ink, #191c1d);
  --surface: var(--igf-surface, #f8f9fa);
  --muted: color-mix(in srgb, var(--ink) 68%, var(--surface));
  --line: color-mix(in srgb, var(--ink) 14%, var(--surface));
  --brand-on-dark: color-mix(in srgb, var(--orange) 42%, #fff);
  color: var(--ink);
  font-family: 'Hanken Grotesk', Arial, sans-serif;
}

.igf-shell {
  width: min(calc(100% - 40px), 1200px);
  margin-inline: auto;
}

.igf-reports__hero {
  padding: clamp(80px, 10vw, 130px) 0;
  background: #242220;
  color: #fff;
}

.igf-reports__hero p,
.igf-reports__heading p {
  margin: 0 0 14px;
  color: var(--brand-on-dark);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
}

.igf-reports__hero h1 {
  max-width: 800px;
  margin: 0;
  color: #fff;
  font: 650 clamp(46px, 6vw, 74px)/1.04 'Literata', Georgia, serif;
  letter-spacing: -.035em;
}

.igf-reports__hero h1::after,
.igf-reports h2::after,
.igf-reports h3::after {
  display: none !important;
}

.igf-reports__hero span {
  display: block;
  max-width: 680px;
  margin-top: 22px;
  color: #d7d3cf;
  font-size: 19px;
  line-height: 1.65;
}

.igf-reports__body {
  min-height: 480px;
  padding: clamp(70px, 9vw, 115px) 0;
  background: var(--surface);
}

.igf-reports__heading {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 30px;
}

.igf-reports__heading p {
  color: var(--brown);
}

.igf-reports__heading h2 {
  margin: 0;
  font: 650 clamp(32px, 4vw, 46px)/1.1 'Literata', Georgia, serif;
  letter-spacing: -.03em;
}

.igf-report-count {
  color: var(--muted);
  font-size: 13px;
  font-weight: 800;
}

.igf-reports__filters {
  display: grid;
  grid-template-columns: 1fr 240px auto auto;
  align-items: end;
  gap: 12px;
  margin-bottom: 34px;
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 18px;
  background: #fff;
}

.igf-reports__filters label {
  display: grid;
  gap: 7px;
  color: var(--ink);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: .04em;
  text-transform: uppercase;
}

.igf-reports__filters input {
  width: 100%;
  height: 45px;
  border: 1px solid #cfc7c1;
  border-radius: 8px;
  padding: 8px 12px;
  background: #fff;
  color: var(--ink);
  font: 14px 'Hanken Grotesk', Arial, sans-serif;
  text-transform: none;
}

.igf-reports__filters button {
  min-height: 45px;
  border: 1px solid var(--brown);
  border-radius: 8px;
  padding: 0 18px;
  background: var(--brown);
  color: var(--igf-on-accent, #fff);
  font-weight: 800;
  cursor: pointer;
}

.igf-reports__filters .igf-clear {
  border-color: #b9b1aa;
  background: #fff;
  color: var(--brown);
}

.igf-report-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: clamp(22px, 3vw, 34px);
  margin: 0;
  padding: 0;
  list-style: none;
}

.igf-report-grid > li {
  min-width: 0;
}

.igf-report-card {
  display: flex;
  height: 100%;
  min-width: 0;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 20px;
  background: #fff;
  box-shadow: 0 12px 32px rgba(44, 34, 26, .08);
  transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
}

.igf-report-card__cover {
  position: relative;
  aspect-ratio: 16 / 10;
  overflow: hidden;
  border-bottom: 1px solid var(--line);
  background: #ece8e4;
}

.igf-report-card__cover img {
  width: 100%;
  height: 100%;
  padding: 18px;
  background: #eee9e4;
  object-fit: contain;
}

.igf-report-card__fallback {
  position: relative;
  z-index: 0;
  display: flex;
  height: 100%;
  flex-direction: column;
  justify-content: flex-end;
  overflow: hidden;
  padding: clamp(24px, 4vw, 42px);
  background:
    radial-gradient(circle at 83% 14%, color-mix(in srgb, var(--orange) 38%, transparent), transparent 31%),
    linear-gradient(145deg, #181b1d 4%, #35251c 100%);
  color: #fff;
}

.igf-report-card__fallback::before,
.igf-report-card__fallback::after {
  position: absolute;
  z-index: -1;
  border: 1px solid color-mix(in srgb, var(--brand-on-dark) 28%, transparent);
  border-radius: 50%;
  content: '';
}

.igf-report-card__fallback::before {
  top: -24%;
  right: -7%;
  width: 56%;
  aspect-ratio: 1;
}

.igf-report-card__fallback::after {
  right: 9%;
  bottom: -31%;
  width: 48%;
  aspect-ratio: 1;
}

.igf-report-grid > li:nth-child(even) .igf-report-card__fallback {
  background:
    radial-gradient(circle at 15% 18%, color-mix(in srgb, var(--brand-on-dark) 25%, transparent), transparent 30%),
    linear-gradient(140deg, color-mix(in srgb, var(--brown) 58%, #000) 0%, color-mix(in srgb, var(--orange) 68%, var(--brown)) 100%);
}

.igf-report-card__brand {
  position: absolute;
  top: clamp(22px, 3vw, 34px);
  left: clamp(24px, 4vw, 42px);
  max-width: 72%;
  color: color-mix(in srgb, var(--orange) 28%, #fff);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: .09em;
  text-transform: uppercase;
}

.igf-report-card__fallback > i {
  width: 42px;
  height: 42px;
  margin-bottom: 18px;
  border: 1px solid rgba(255, 255, 255, .28);
  border-radius: 50%;
  color: color-mix(in srgb, var(--orange) 66%, #fff);
  font-size: 18px;
  line-height: 40px;
  text-align: center;
}

.igf-report-card__fallback strong {
  max-width: 78%;
  font: 650 clamp(25px, 3.4vw, 40px)/1.03 'Literata', Georgia, serif;
  letter-spacing: -.035em;
}

.igf-report-card__year {
  position: absolute;
  right: clamp(24px, 4vw, 42px);
  bottom: clamp(24px, 4vw, 40px);
  color: color-mix(in srgb, var(--orange) 52%, #fff);
  font: 700 clamp(23px, 3vw, 34px)/1 'Literata', Georgia, serif;
}

.igf-report-card__body {
  display: flex;
  min-height: 270px;
  flex: 1;
  flex-direction: column;
  padding: clamp(23px, 3vw, 32px);
}

.igf-report-card__meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 17px;
  color: var(--muted);
  font-size: 12px;
  font-weight: 700;
}

.igf-report-card__meta > span:first-child {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--brown);
  text-transform: uppercase;
}

.igf-report-card__meta i {
  color: var(--orange);
  font-size: 17px;
}

.igf-report-card h3 {
  margin: 0;
  color: var(--ink);
  font: 650 clamp(24px, 3vw, 31px)/1.2 'Literata', Georgia, serif;
  letter-spacing: -.025em;
}

.igf-report-card__body > p {
  display: -webkit-box;
  overflow: hidden;
  margin: 14px 0 24px;
  color: var(--muted);
  font-size: 15px;
  line-height: 1.6;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
}

.igf-report-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: auto;
}

.igf-report-card__actions a {
  display: inline-flex;
  min-height: 46px;
  align-items: center;
  justify-content: center;
  gap: 9px;
  border: 1px solid var(--brown);
  border-radius: 9px;
  padding: 10px 17px;
  font-size: 13px;
  font-weight: 800;
  text-decoration: none;
}

.igf-report-card__primary {
  flex: 1 1 170px;
  background: var(--brown);
  color: var(--igf-on-accent, #fff);
}

.igf-report-card__secondary {
  flex: 0 1 auto;
  background: #fff;
  color: var(--brown);
}

.igf-reports__empty {
  padding: 80px 20px;
  text-align: center;
}

.igf-reports__empty i {
  color: var(--orange);
  font-size: 38px;
}

.igf-reports__empty h3 {
  margin: 15px 0 7px;
  font: 650 30px 'Literata', Georgia, serif;
}

.igf-reports__empty p {
  color: var(--muted);
}

.igf-pagination {
  margin-top: 40px;
}

.igf-reports__filters button:focus-visible,
.igf-reports__filters input:focus-visible,
.igf-report-card__actions a:focus-visible {
  outline: 3px solid var(--ink);
  outline-offset: 3px;
}

@media (hover: hover) {
  .igf-report-card:hover {
    transform: translateY(-4px);
    border-color: #cfc4bc;
    box-shadow: 0 18px 42px rgba(44, 34, 26, .13);
  }

  .igf-reports__filters button:hover,
  .igf-report-card__primary:hover {
    border-color: var(--brown-dark);
    background: var(--brown-dark);
  }

  .igf-reports__filters .igf-clear:hover,
  .igf-report-card__secondary:hover {
    border-color: #8c8279;
    background: #f1eeeb;
    color: var(--brown-dark);
  }
}

@media (max-width: 850px) {
  .igf-reports__filters {
    grid-template-columns: 1fr 1fr;
  }

  .igf-reports__filters button {
    width: 100%;
  }
}

@media (max-width: 760px) {
  .igf-report-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 600px) {
  .igf-shell {
    width: min(calc(100% - 28px), 1200px);
  }

  .igf-reports__heading {
    align-items: start;
    flex-direction: column;
  }

  .igf-reports__filters {
    grid-template-columns: 1fr;
  }

  .igf-reports__hero {
    padding-block: 72px;
  }

  .igf-report-card__meta {
    align-items: flex-start;
    flex-direction: column;
    gap: 8px;
  }

  .igf-report-card__actions a {
    flex-basis: 100%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .igf-report-card {
    transition: none;
  }
}
</style>
