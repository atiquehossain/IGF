<!-- Rich text is sanitized by ContentSanitizer before it reaches this view. -->
<!-- eslint-disable vue/no-v-html -->
<template>
  <Layout>
    <PageBlocks v-if="landingBlocks.length" :blocks="landingBlocks" />
    <div v-else class="igf-listing" :class="{ 'igf-listing--awards': isAwardsCategory }">
      <section class="igf-listing__intro">
        <div class="igf-shell" :class="{
          'igf-awards-hero': isAwardsCategory,
          'igf-program-hero': !isAwardsCategory,
          'igf-program-hero--split': showProgramHeroMedia,
        }">
          <div class="igf-listing__intro-copy">
            <p class="igf-eyebrow">{{ isAwardsCategory ? settings.awards_eyebrow : settings.category_eyebrow }}</p>
            <h1>{{ category?.name || settings.category_default_title }}</h1>
            <article v-if="category?.description" v-html="category.description" />
            <div v-if="!isAwardsCategory && (heroPrimaryCta || (isProgramsArchive && items.length))"
              class="igf-program-hero__actions">
              <a v-if="heroPrimaryCta" class="igf-program-hero__primary" :href="heroPrimaryCta.url">
                {{ heroPrimaryCta.label }}
                <span aria-hidden="true">&rarr;</span>
              </a>
              <a v-if="isProgramsArchive && items.length" class="igf-program-hero__jump" href="#program-list">
                {{ browseProgramsLabel }}
                <span aria-hidden="true">&darr;</span>
              </a>
            </div>
          </div>
          <div v-if="isAwardsCategory" class="igf-awards-count" :aria-label="`${awardCount} ${awardCountLabel}`">
            <strong>{{ awardCount }}</strong>
            <span>{{ awardCountLabel }}</span>
          </div>
          <div v-else-if="showProgramHeroMedia" class="igf-program-hero__visual">
            <img :src="heroImage" :alt="heroImageAlt" width="960" height="600" loading="eager"
              fetchpriority="high" decoding="async" @error="onHeroImageError">
            <span class="igf-program-hero__accent" aria-hidden="true" />
          </div>
        </div>
      </section>
      <section id="program-list" class="igf-listing__content" :aria-label="settings.category_listing_label">
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
            'igf-card-grid--four': !isAwardsCategory && items.length === 4 && archiveDesign.card_columns === 'auto',
            [`igf-card-grid--columns-${archiveDesign.card_columns}`]: !isAwardsCategory && archiveDesign.card_columns !== 'auto',
          }">
            <CategoryItemCard v-for="(post, index) in items" :key="post.uuid || post.id" :title="post.name"
              :subtitle="post.sub_title" :thumbnail="post.thumbnail" :image-alt="post.thumbnail_alt || ''"
              :eyebrow="isAwardsCategory ? settings.awards_card_eyebrow : programCardEyebrow"
              :link-label="isAwardsCategory ? settings.awards_card_link_label : programCardLinkLabel"
              :show-link="isAwardsCategory || archiveDesign.show_card_link"
              :link="post.public_url || route('frontend.page', post.slug)" :variant="isAwardsCategory ? 'award' : 'default'"
              :ordinal="isAwardsCategory ? index + 1 : 0" />
          </div>
          <div v-else class="igf-empty"><i class="fa-regular fa-folder-open" aria-hidden="true" /><h2>{{ settings.category_empty_title }}</h2><p>{{ settings.category_empty_body }}</p></div>
          <v-pagination v-if="properties?.total_page > 1" :model-value="properties.page" :length="properties.total_page"
            class="igf-pagination" @update:model-value="onPageChange" />
          <aside v-if="bottomCta" class="igf-listing__cta" :aria-label="bottomCta.title">
            <div>
              <h2>{{ bottomCta.title }}</h2>
              <p v-if="bottomCta.body">{{ bottomCta.body }}</p>
            </div>
            <a :href="bottomCta.url">
              {{ bottomCta.label }}
              <span aria-hidden="true">&rarr;</span>
            </a>
          </aside>
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
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
const isProgramsArchive = computed(() => category.value?.slug === 'our-causes');
const archiveBanner = computed(() => inertiaPage.props.data?.banner || category.value?.banner || null);
const archiveDesignPayload = computed(() => inertiaPage.props.data?.archive_design || {});
const archiveDesign = computed(() => ({
  hero_layout: 'compact',
  show_banner: false,
  card_columns: 'auto',
  card_eyebrow: '',
  show_card_eyebrow: false,
  card_link_label: '',
  show_card_link: true,
  bottom_cta: null,
  ...(inertiaPage.props.data?.archive_design || {}),
}));
const locale = computed(() => String(inertiaPage.props.locale || 'en').toLowerCase());
const isBangla = computed(() => locale.value.startsWith('bn'));
const configuredHeroImage = computed(() => archiveBanner.value?.image_url || '');
const failedHeroImage = ref('');
const configuredHeroImageFailed = computed(() => Boolean(configuredHeroImage.value)
  && failedHeroImage.value === configuredHeroImage.value);
const requestedHeroLayout = computed(() => {
  const requested = String(archiveDesignPayload.value.hero_layout
    ?? settings.value.category_hero_layout
    ?? 'compact');
  return requested === 'split' ? 'split' : 'compact';
});
const bannerSettingEnabled = computed(() => {
  const value = archiveDesignPayload.value.show_banner ?? settings.value.category_show_banner;
  return value === undefined || value === null || value === true || value === 1 || value === '1' || value === 'true';
});
const showProgramHeroMedia = computed(() => {
  if (requestedHeroLayout.value !== 'split' || !bannerSettingEnabled.value) return false;
  if (!configuredHeroImage.value) return isProgramsArchive.value;
  return !configuredHeroImageFailed.value || isProgramsArchive.value;
});
const heroImage = computed(() => {
  if (configuredHeroImage.value && !configuredHeroImageFailed.value) return configuredHeroImage.value;
  return isProgramsArchive.value ? '/image/our-cause/our-causes-bg.webp' : '';
});
const heroImageAlt = computed(() => configuredHeroImage.value && !configuredHeroImageFailed.value
  ? (archiveBanner.value?.image_alt || '')
  : '');
const heroPrimaryCta = computed(() => {
  const label = String(archiveBanner.value?.cta_label || '').trim();
  const url = String(archiveBanner.value?.cta_url || '').trim();
  return label && url ? { label, url } : null;
});
const browseProgramsLabel = computed(() => settings.value.category_browse_label
  || (isBangla.value ? 'কর্মসূচিগুলো দেখুন' : 'Browse programs'));
const programCardEyebrow = computed(() => archiveDesign.value.show_card_eyebrow
  ? archiveDesign.value.card_eyebrow
  : '');
const programCardLinkLabel = computed(() => {
  const label = String(archiveDesign.value.card_link_label || '').trim();
  if (!label || label === 'Read the story' || label === 'গল্পটি পড়ুন') {
    return isBangla.value ? 'কর্মসূচি দেখুন' : 'Explore program';
  }
  return label;
});
const bottomCta = computed(() => {
  const cta = archiveDesign.value.bottom_cta;
  if (!isProgramsArchive.value || !cta?.enabled || !cta.title || !cta.label || !cta.url) return null;
  return cta;
});
const awardCount = computed(() => Number(properties.value?.total_count ?? items.value.length));
const awardCountLabel = computed(() => awardCount.value === 1
  ? (settings.value.awards_count_singular || 'recognition')
  : (settings.value.awards_count_plural || 'recognitions'));
watch(configuredHeroImage, () => { failedHeroImage.value = ''; });
function onHeroImageError(event) {
  const failedSource = event.currentTarget?.getAttribute('src') || '';
  if (failedSource === configuredHeroImage.value) failedHeroImage.value = configuredHeroImage.value;
}
function onPageChange(page) { router.get(inertiaPage.url, { page }, { preserveState:true, preserveScroll:true }); }
</script>

<style scoped lang="scss">
.igf-listing { --orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#5e5d66;--surface:#f8f9fa; color:var(--ink); font-family:'Hanken Grotesk',Arial,sans-serif; }
.igf-shell { width:min(calc(100% - 40px),var(--igf-content-width,1200px)); margin-inline:auto; }
.igf-listing__intro { position:relative; overflow:hidden; padding:var(--igf-section-block,clamp(80px,10vw,130px)) 0; background:radial-gradient(circle at 86% 16%,rgba(255,117,0,.2),transparent 27%),#242220; color:#fff; }
.igf-listing:not(.igf-listing--awards) .igf-listing__intro::before { position:absolute; right:-120px; bottom:-245px; width:470px; aspect-ratio:1; border:1px solid rgba(255,117,0,.2); border-radius:50%; content:''; }
.igf-eyebrow { margin:0 0 15px; color:#ffad72; font-size:11px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
.igf-listing h1 { margin:0; color:#fff; font:650 var(--igf-heading-1,clamp(44px,6vw,72px))/1.05 'Literata',Georgia,serif; letter-spacing:-.035em; }
.igf-listing h1::after,.igf-listing h2::after { display:none!important; }
.igf-listing__intro-copy { position:relative; z-index:1; max-width:720px; }
.igf-listing__intro article { max-width:680px; margin:22px 0 0; color:#d6d2cf; font-size:var(--igf-lead-size,19px); line-height:1.65; }
.igf-listing__intro article :deep(p) { margin:0; }
.igf-listing__intro article :deep(p+p) { margin-top:.75em; }
.igf-program-hero { position:relative; }
.igf-program-hero--split { display:grid; grid-template-columns:minmax(0,.88fr) minmax(360px,1.12fr); gap:clamp(32px,6vw,76px); align-items:center; }
.igf-program-hero__actions { display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-top:30px; }
.igf-program-hero__primary,.igf-program-hero__jump { display:inline-flex; min-height:48px; align-items:center; justify-content:center; gap:11px; padding:0 22px; border:1px solid transparent; border-radius:999px; font-size:13px; font-weight:800; letter-spacing:.025em; text-decoration:none; text-transform:uppercase; }
.igf-program-hero__primary { background:var(--orange); box-shadow:0 10px 24px rgba(0,0,0,.2); color:#fff; }
.igf-program-hero__primary:hover { background:#ff9140; color:#fff; }
.igf-program-hero__primary:focus-visible,.igf-program-hero__jump:focus-visible { outline:3px solid #ffad72; outline-offset:4px; }
.igf-program-hero__jump { border-color:rgba(255,255,255,.34); color:#fff; }
.igf-program-hero__jump:only-child { border-color:var(--orange); background:var(--orange); box-shadow:0 10px 24px rgba(0,0,0,.2); }
.igf-program-hero__jump span { display:grid; width:26px; aspect-ratio:1; border-radius:50%; background:rgba(255,255,255,.13); place-items:center; transition:background-color .2s ease,transform .2s ease; }
.igf-program-hero__jump:hover { border-color:#ffad72; background:rgba(255,255,255,.08); color:#fff; }
.igf-program-hero__jump:only-child:hover { border-color:#ff9140; background:#ff9140; }
.igf-program-hero__jump:hover span { background:rgba(255,255,255,.2); transform:translateY(2px); }
.igf-program-hero__visual { position:relative; z-index:1; height:clamp(230px,24vw,340px); overflow:hidden; border:1px solid rgba(255,255,255,.16); border-radius:24px; background:#343637; box-shadow:0 24px 58px rgba(0,0,0,.24); }
.igf-program-hero__visual img { display:block; width:100%; height:100%; object-fit:cover; object-position:center 46%; }
.igf-program-hero__accent { position:absolute; right:18px; bottom:18px; width:52px; height:7px; border-radius:999px; background:var(--orange); box-shadow:0 0 0 7px rgba(36,34,32,.72); }
.igf-listing__content { min-height:420px; scroll-margin-top:130px; padding:clamp(54px,6vw,78px) 0; background:var(--surface); }
.igf-card-grid { display:grid; grid-template-columns:repeat(var(--igf-card-columns,3),minmax(0,1fr)); gap:clamp(22px,2.5vw,30px); align-items:stretch; }
.igf-card-grid--columns-2 { grid-template-columns:repeat(2,minmax(0,1fr)); }
.igf-card-grid--columns-3 { grid-template-columns:repeat(3,minmax(0,1fr)); }
.igf-card-grid--columns-4 { grid-template-columns:repeat(4,minmax(0,1fr)); }
.igf-card-grid--four { width:min(100%,1000px); grid-template-columns:repeat(2,minmax(0,1fr)); margin-inline:auto; }
.igf-empty { padding:70px 20px; text-align:center; }
.igf-empty i { color:var(--orange); font-size:38px; }
.igf-empty h2 { margin:15px 0 7px; font:650 30px 'Literata',Georgia,serif; }
.igf-empty p { color:var(--muted); }
.igf-pagination { margin-top:45px; }
.igf-listing__cta { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:clamp(28px,6vw,72px); align-items:center; margin-top:clamp(48px,7vw,78px); padding:clamp(30px,5vw,48px); overflow:hidden; border-radius:24px; background:radial-gradient(circle at 92% 12%,rgba(255,117,0,.28),transparent 28%),linear-gradient(135deg,#24211f,#3a2a20); color:#fff; }
.igf-listing__cta h2 { margin:0; color:#fff; font:650 clamp(28px,3.5vw,40px)/1.12 'Literata',Georgia,serif; letter-spacing:-.025em; }
.igf-listing__cta p { max-width:650px; margin:12px 0 0; color:rgba(255,255,255,.76); font-size:16px; line-height:1.65; }
.igf-listing__cta a { display:inline-flex; min-height:48px; align-items:center; justify-content:center; gap:12px; padding:0 22px; border-radius:999px; background:#ff7500; box-shadow:0 8px 22px rgba(0,0,0,.2); color:#fff; font-size:13px; font-weight:800; text-decoration:none; text-transform:uppercase; }
.igf-listing__cta a:hover { background:#fff; color:#773400; }
.igf-listing__cta a:focus-visible { outline:3px solid #fff; outline-offset:4px; }
.igf-listing--awards .igf-listing__intro { position:relative; overflow:hidden; padding:clamp(54px,7vw,86px) 0; background:radial-gradient(circle at 86% 18%,rgba(255,117,0,.28),transparent 24%),linear-gradient(135deg,#211f1d 0%,#302b27 58%,#432b1c 100%); }
.igf-listing--awards .igf-listing__intro::after { position:absolute; right:-90px; bottom:-180px; width:360px; height:360px; border:1px solid rgba(255,255,255,.12); border-radius:50%; content:''; }
.igf-awards-hero { position:relative; z-index:1; display:grid; grid-template-columns:minmax(0,1fr) auto; gap:clamp(35px,7vw,90px); align-items:center; }
.igf-listing--awards .igf-eyebrow { color:#ffad6a; }
.igf-listing--awards h1 { max-width:760px; color:#fff; font-size:clamp(46px,6vw,72px); }
.igf-listing--awards .igf-listing__intro article { max-width:720px; margin-top:18px; color:rgba(255,255,255,.76); font-size:18px; }
.igf-listing--awards .igf-listing__intro article :deep(p) { margin:0; }
.igf-awards-count { display:flex; width:158px; aspect-ratio:1; flex-direction:column; align-items:center; justify-content:center; border:1px solid rgba(255,255,255,.28); border-radius:50%; background:rgba(255,255,255,.08); color:#fff; text-align:center; backdrop-filter:blur(8px); }
.igf-awards-count strong { font:650 52px/1 'Literata',Georgia,serif; }
.igf-awards-count span { max-width:100px; margin-top:8px; color:#ffd2af; font-size:10px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
.igf-listing--awards .igf-listing__content { padding:clamp(58px,7vw,90px) 0; background:linear-gradient(180deg,#fbf6ef 0%,#f7f8f9 48%,#fff 100%); }
.igf-awards-intro { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(260px,.9fr); gap:clamp(30px,8vw,110px); align-items:end; margin-bottom:38px; }
.igf-awards-intro .igf-eyebrow { color:var(--brown); }
.igf-awards-intro h2 { max-width:700px; margin:0; font:650 clamp(31px,4vw,44px)/1.12 'Literata',Georgia,serif; letter-spacing:-.03em; }
.igf-awards-intro>p { margin:0; color:var(--muted); font-size:16px; line-height:1.7; }
.igf-card-grid--awards { --igf-card-media-aspect:4 / 3; grid-template-columns:repeat(6,minmax(0,1fr)); gap:28px; }
.igf-card-grid--awards > * { grid-column:span 2; }
.igf-card-grid--five > *:nth-child(4) { grid-column:2/span 2; }
@media(max-width:1100px){.igf-card-grid:not(.igf-card-grid--awards):not(.igf-card-grid--four){grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){.igf-program-hero--split{grid-template-columns:minmax(0,.9fr) minmax(320px,1.1fr);gap:28px}.igf-awards-hero{grid-template-columns:minmax(0,1fr) auto}.igf-awards-count{width:132px}.igf-awards-count strong{font-size:44px}.igf-awards-intro{grid-template-columns:1fr;gap:16px}.igf-card-grid--awards{grid-template-columns:repeat(2,minmax(0,1fr))}.igf-card-grid--awards>*{grid-column:auto}.igf-card-grid--five>*:nth-child(4){grid-column:auto}.igf-card-grid--five>*:nth-child(5){grid-column:1/-1;width:calc((100% - 28px)/2);justify-self:center}}
@media(max-width:860px){.igf-program-hero--split{grid-template-columns:1fr;gap:32px}.igf-program-hero__visual{height:clamp(210px,42vw,300px)}}
@media(max-width:760px){.igf-program-hero--split{gap:26px}.igf-program-hero__visual{height:clamp(160px,45vw,205px)}.igf-listing__cta{grid-template-columns:1fr}.igf-listing__cta a{justify-self:start}}
@media(max-width:600px){.igf-shell{width:min(calc(100% - 28px),1200px)}.igf-listing__intro{padding:62px 0}.igf-listing h1{font-size:clamp(40px,12vw,50px)}.igf-listing__intro article{font-size:16px;line-height:1.62}.igf-program-hero__actions{margin-top:24px}.igf-program-hero__primary,.igf-program-hero__jump{min-height:46px;padding-inline:18px;font-size:12px}.igf-listing__content{scroll-margin-top:125px;padding:48px 0}.igf-card-grid,.igf-card-grid--four{width:100%;grid-template-columns:1fr}.igf-listing__cta{padding:28px 24px;border-radius:19px}.igf-listing__cta a{width:100%}.igf-awards-hero{grid-template-columns:1fr;gap:28px}.igf-awards-count{width:112px}.igf-awards-count strong{font-size:38px}.igf-listing--awards h1{font-size:clamp(42px,13vw,56px)}.igf-card-grid--awards{grid-template-columns:1fr}.igf-card-grid--five>*:nth-child(5){grid-column:auto;width:100%}}
@media(prefers-reduced-motion:reduce){.igf-program-hero__jump span{transition:none}.igf-program-hero__jump:hover span{transform:none}}
</style>
