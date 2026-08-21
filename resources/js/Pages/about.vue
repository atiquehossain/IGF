<!-- Rich text is sanitized by ContentSanitizer before it reaches this view. -->
<!-- eslint-disable vue/no-v-html -->
<template>
  <Layout>
    <AppBannerPage v-if="!hasHeroBlock" />
    <div class="igf-about">
      <PageBlocks v-if="about?.visible_blocks?.length" :blocks="about.visible_blocks" />
      <section v-else class="igf-about__legacy">
        <div class="igf-shell">
          <p v-if="copy.aboutEyebrow" class="igf-eyebrow">{{ copy.aboutEyebrow }}</p>
          <h2>{{ about?.name || copy.aboutTitle }}</h2>
          <article v-if="about?.description" v-html="about.description" />
        </div>
      </section>
      <section v-if="!about?.visible_blocks?.length && foundersLetter?.description" class="igf-founder" aria-labelledby="founder-heading">
        <div class="igf-shell igf-founder__inner">
          <header><p v-if="copy.founderEyebrow" class="igf-eyebrow">{{ copy.founderEyebrow }}</p><h2 id="founder-heading">{{ foundersLetter.name }}</h2></header>
          <article v-html="foundersLetter.description" />
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
import AppBannerPage from '../component/banner.vue';
import PageBlocks from '../Shared/PageBlocks.vue';
const page = usePage();
const about = computed(() => page.props.data?.about_us || null);
const foundersLetter = computed(() => page.props.data?.founders_letter || null);
const hasHeroBlock = computed(() => (about.value?.visible_blocks || []).some(block => block?.type === 'hero'));
const copy = computed(() => ({
  aboutEyebrow: page.props.siteSettings?.shared_blocks?.about_eyebrow || '',
  aboutTitle: page.props.siteSettings?.shared_blocks?.about_fallback_title || '',
  founderEyebrow: page.props.siteSettings?.shared_blocks?.founder_eyebrow || '',
}));
</script>

<style scoped>
.igf-about{--brown:#9c4500;--ink:#191c1d;--muted:#5f6065;--surface:#f8f9fa;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell{width:min(100% - 40px,1040px);margin:0 auto}.igf-eyebrow{margin:0 0 14px;color:var(--brown);font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-about__legacy{padding:clamp(75px,9vw,120px) 0;background:#fff}.igf-about h1,.igf-about h2{margin:0;color:var(--ink);font:650 clamp(40px,5vw,58px)/1.1 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-about :is(h1,h2)::after{display:none!important}.igf-about__legacy article{max-width:880px;margin-top:30px;color:var(--muted);font-size:17px;line-height:1.8}.igf-about :deep(article img){max-width:100%;height:auto;border-radius:16px}.igf-about :deep(article h2),.igf-about :deep(article h3){margin:1.4em 0 .5em;color:var(--ink);font-family:'Literata',Georgia,serif}.igf-about :deep(article a){color:var(--brown);font-weight:800}.igf-founder{padding:clamp(75px,9vw,120px) 0;background:var(--surface)}.igf-founder__inner{display:grid;grid-template-columns:minmax(260px,.7fr) minmax(0,1.3fr);gap:clamp(40px,7vw,90px)}.igf-founder h2{font-size:clamp(34px,4vw,48px)}.igf-founder article{color:var(--muted);font-size:17px;line-height:1.8}
@media(max-width:720px){.igf-shell{width:min(100% - 32px,1040px)}.igf-founder__inner{grid-template-columns:1fr;gap:24px}}
</style>
