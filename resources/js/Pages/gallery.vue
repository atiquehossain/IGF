<template>
  <Layout>
    <AppBannerPage />
    <div class="igf-gallery">
      <section class="igf-gallery__intro">
        <div class="igf-shell">
          <p class="igf-eyebrow">{{ settings.eyebrow }}</p>
          <h1>{{ settings.title }}</h1>
          <p>{{ settings.introduction }}</p>
        </div>
      </section>
      <section class="igf-gallery__content">
        <div class="igf-shell">
          <form class="igf-gallery__filters" role="search" @submit.prevent="applyFilters">
            <label for="gallery-album">{{ settings.album_label }}<select id="gallery-album" v-model="albumId"><option value="">{{ settings.all_albums_label }}</option><option v-for="album in albums" :key="album.id" :value="album.id">{{ album.name }}</option></select></label>
            <label for="gallery-search">{{ settings.search_label }}<input id="gallery-search" v-model="search" type="search" :placeholder="settings.search_placeholder"></label>
            <button type="submit">{{ settings.apply_label }}</button>
            <button v-if="search || albumId" class="igf-filter-clear" type="button" @click="clearFilters">{{ settings.clear_label }}</button>
          </form>
          <p class="igf-result-count" aria-live="polite">{{ formattedTotalCount }} {{ totalCount === 1 ? settings.photo_singular : settings.photo_plural }}</p>
          <div v-if="items.length" class="igf-gallery__grid">
            <button v-for="item in items" :key="item.id" type="button" class="igf-photo" @click="openDialog(item)">
              <img :src="item.path" :alt="item.alt_text || item.name || settings.fallback_image_alt" loading="lazy">
              <span><strong>{{ item.name }}</strong><small v-if="item.album_name">{{ item.album_name }}</small></span>
            </button>
          </div>
          <div v-else class="igf-empty"><i class="fa-regular fa-images" aria-hidden="true" /><h2>{{ settings.empty_title }}</h2><p>{{ settings.empty_body }}</p></div>
          <v-pagination v-if="properties.total_page > 1" :model-value="properties.page" :length="properties.total_page" class="igf-pagination" @update:model-value="onPageChange" />
        </div>
      </section>

      <v-dialog v-model="dialog" max-width="1100">
        <div class="igf-lightbox" role="document">
          <button type="button" :aria-label="settings.close_image_label" @click="dialog = false"><i class="fa-solid fa-xmark" aria-hidden="true" /></button>
          <img v-if="activeItem" :src="activeItem.main_path" :alt="activeItem.alt_text || activeItem.name || settings.fallback_image_alt">
          <p v-if="activeItem"><strong>{{ activeItem.name }}</strong><span v-if="activeItem.album_name">{{ activeItem.album_name }}</span></p>
        </div>
      </v-dialog>
    </div>
  </Layout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
import AppBannerPage from '../component/banner.vue';
import { formatNumber } from '../Shared/composables/siteSettings';
const page = usePage();
const settings = computed(() => page.props.siteSettings?.gallery_page || {});
const regional = computed(() => page.props.siteSettings?.regional || {});
const items = computed(() => page.props.data?.items || []);
const albums = computed(() => page.props.data?.albums || []);
const properties = computed(() => page.props.properties || {});
const totalCount = computed(() => Number(properties.value.total_count || 0));
const formattedTotalCount = computed(() => formatNumber(totalCount.value, regional.value));
const search = ref(properties.value.search || '');
const albumId = ref(properties.value.album_id || '');
const dialog = ref(false);
const activeItem = ref(null);
function visit(extra = {}) { router.get(route('frontend.gallery'), { search: search.value || undefined, album_id: albumId.value || undefined, ...extra }, { preserveScroll: true }); }
function applyFilters() { visit({ page: 1 }); }
function clearFilters() { search.value = ''; albumId.value = ''; visit({ page: 1 }); }
function onPageChange(pageNumber) { visit({ page: pageNumber }); }
function openDialog(item) { activeItem.value = item; dialog.value = true; }
</script>

<style scoped>
.igf-gallery{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#5f6065;--surface:#f8f9fa;--line:#dedbd7;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell{width:min(100% - 40px,1240px);margin:0 auto}.igf-gallery__intro{padding:clamp(70px,8vw,105px) 0;background:#fff}.igf-eyebrow{margin:0 0 13px;color:var(--brown);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-gallery h1{margin:0;color:var(--ink);font:650 clamp(42px,5vw,60px)/1.08 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-gallery h1::after{display:none!important}.igf-gallery__intro>div>p:not(.igf-eyebrow){max-width:690px;margin:20px 0 0;color:var(--muted);font-size:17px;line-height:1.7}.igf-gallery__content{min-height:450px;padding:clamp(60px,8vw,100px) 0;background:var(--surface)}.igf-gallery__filters{display:grid;grid-template-columns:minmax(180px,.65fr) minmax(240px,1fr) auto auto;align-items:end;gap:12px;margin-bottom:15px;padding:20px;border:1px solid var(--line);border-radius:14px;background:#fff}.igf-gallery__filters label{display:grid;gap:7px;color:var(--ink);font-size:12px;font-weight:800}.igf-gallery__filters :is(input,select){width:100%;height:46px;padding:0 13px;border:1px solid #c9c4bf;border-radius:8px;background:#fff;color:var(--ink);font:15px 'Hanken Grotesk',Arial,sans-serif}.igf-gallery__filters button{height:46px;padding:0 22px;border:1px solid var(--orange);border-radius:999px;background:var(--orange);color:#fff;font-weight:800}.igf-gallery__filters .igf-filter-clear{border-color:#aaa;background:#fff;color:var(--ink)}.igf-result-count{margin:0 0 24px;color:var(--muted);font-size:13px}.igf-gallery__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.igf-photo{position:relative;overflow:hidden;padding:0;border:0;border-radius:14px;background:#ddd;cursor:pointer;text-align:left}.igf-photo img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover;transition:.25s ease}.igf-photo>span{position:absolute;right:0;bottom:0;left:0;display:grid;gap:3px;padding:42px 18px 16px;background:rgba(25,28,29,.88);color:#fff;clip-path:polygon(0 35%,100% 0,100% 100%,0 100%)}.igf-photo strong{padding-top:10px;font-size:15px}.igf-photo small{color:#ddd}.igf-photo:hover img,.igf-photo:focus-visible img{transform:scale(1.035)}.igf-photo:focus-visible{outline:3px solid var(--orange);outline-offset:3px}.igf-empty{padding:80px 20px;text-align:center}.igf-empty i{color:var(--orange);font-size:40px}.igf-empty h2{margin:15px 0 7px;font:650 30px 'Literata',Georgia,serif}.igf-empty p{color:var(--muted)}.igf-pagination{margin-top:45px}.igf-lightbox{position:relative;overflow:hidden;border-radius:16px;background:#18191a;color:#fff}.igf-lightbox>button{position:absolute;z-index:2;top:13px;right:13px;display:grid;width:44px;height:44px;place-items:center;border:1px solid rgba(255,255,255,.35);border-radius:50%;background:rgba(0,0,0,.65);color:#fff}.igf-lightbox img{display:block;width:100%;max-height:78vh;object-fit:contain}.igf-lightbox p{display:flex;justify-content:space-between;gap:20px;margin:0;padding:16px 20px}.igf-lightbox span{color:#c9c9c9}
@media(max-width:900px){.igf-gallery__grid{grid-template-columns:repeat(2,1fr)}.igf-gallery__filters{grid-template-columns:1fr 1fr}.igf-gallery__filters button{width:100%}}
@media(max-width:600px){.igf-shell{width:min(100% - 28px,1240px)}.igf-gallery__filters,.igf-gallery__grid{grid-template-columns:1fr}.igf-lightbox p{flex-direction:column;gap:4px}}
</style>
