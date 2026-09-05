<!-- Rich text is sanitized by ContentSanitizer before it reaches this view. -->
<!-- eslint-disable vue/no-v-html -->
<template>
  <Layout>
    <div v-if="event" class="igf-event">
      <header class="igf-event__hero">
        <div class="igf-shell">
          <a :href="route('frontend.events')"><span aria-hidden="true">&larr;</span> {{ archiveSettings.event_back_label }}</a>
          <p>{{ archiveSettings.event_detail_eyebrow }}</p>
          <h1>{{ event.title }}</h1>
          <div class="igf-event__meta"><span v-if="publishedDate"><i class="fa-regular fa-calendar" aria-hidden="true" /> {{ publishedDate }}</span><span v-if="event.location"><i class="fa-solid fa-location-dot" aria-hidden="true" /> {{ event.location }}</span></div>
        </div>
      </header>
      <figure v-if="event.image_url" class="igf-event__image"><img :src="event.image_url" :alt="event.title"></figure>
      <article class="igf-event__article">
        <p v-if="event.sub_title" class="igf-event__lead">{{ event.sub_title }}</p>
        <div v-html="event.description" />
        <footer><a :href="route('frontend.events')"><span aria-hidden="true">&larr;</span> {{ archiveSettings.event_footer_label }}</a></footer>
      </article>
    </div>
  </Layout>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
import { formatDate } from '../Shared/composables/siteSettings';
const page = usePage();
const event = computed(() => page.props.data?.event || null);
const archiveSettings = computed(() => page.props.siteSettings?.content_archives || {});
const regional = computed(() => page.props.siteSettings?.regional || {});
const publishedDate = computed(() => formatDate(
  event.value?.content_kind === 'event' && event.value?.event_start_at
    ? event.value.event_start_at
    : event.value?.published_at,
  regional.value,
));
</script>

<style scoped>
.igf-event{--orange:var(--igf-primary,#ff7500);--brown:var(--igf-accent,#9c4500);--ink:var(--igf-ink,#191c1d);--muted:color-mix(in srgb,var(--ink) 68%,var(--surface));--surface:var(--igf-surface,#f8f9fa);--line:color-mix(in srgb,var(--ink) 14%,var(--surface));color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell{width:min(100% - 40px,960px);margin:0 auto}.igf-event__hero{padding:clamp(95px,12vw,145px) 0 clamp(80px,10vw,120px);background:#202223;color:#fff}.igf-event__hero a{display:inline-flex;gap:8px;margin-bottom:45px;color:color-mix(in srgb,var(--orange) 42%,#fff);font-size:13px;font-weight:800;text-decoration:none}.igf-event__hero>div>p{margin:0 0 14px;color:color-mix(in srgb,var(--orange) 42%,#fff);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-event h1{max-width:880px;margin:0;color:#fff;font:650 clamp(42px,6vw,68px)/1.07 'Literata',Georgia,serif;letter-spacing:-.035em}.igf-event h1::after{display:none!important}.igf-event__meta{display:flex;flex-wrap:wrap;gap:22px;margin-top:27px;color:#d4d5d5;font-size:14px}.igf-event__meta span{display:flex;align-items:center;gap:8px}.igf-event__meta i{color:color-mix(in srgb,var(--orange) 42%,#fff)}.igf-event__image{width:min(100% - 40px,1120px);overflow:hidden;margin:-48px auto 0;border:8px solid #fff;border-radius:20px;background:#ddd;box-shadow:0 15px 40px rgba(25,28,29,.12)}.igf-event__image img{display:block;width:100%;max-height:600px;object-fit:cover}.igf-event__article{width:min(100% - 40px,820px);margin:0 auto;padding:clamp(65px,8vw,105px) 0;color:var(--muted);font-size:17px;line-height:1.8}.igf-event__lead{margin:0 0 36px;padding-left:24px;border-left:4px solid var(--orange);color:var(--ink);font:550 clamp(21px,3vw,27px)/1.55 'Literata',Georgia,serif}.igf-event__article :deep(h2),.igf-event__article :deep(h3){margin:1.5em 0 .55em;color:var(--ink);font-family:'Literata',Georgia,serif;letter-spacing:-.025em}.igf-event__article :deep(img){max-width:100%;height:auto;border-radius:14px}.igf-event__article :deep(a){color:var(--brown);font-weight:800}.igf-event__article footer{margin-top:55px;padding-top:25px;border-top:1px solid var(--line)}.igf-event__article footer a{color:var(--brown);font-size:13px;font-weight:800;text-decoration:none}
@media(max-width:600px){.igf-shell,.igf-event__article{width:min(100% - 28px,960px)}.igf-event__image{width:min(100% - 20px,1120px);border-width:5px}.igf-event__hero a{margin-bottom:32px}}
</style>
