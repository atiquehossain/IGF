<template>
  <header v-if="banner" class="igf-page-hero">
    <picture v-if="banner.imageUrl" class="igf-page-hero__picture">
      <source v-if="banner.media.avifSrcset" type="image/avif" :srcset="banner.media.avifSrcset" :sizes="banner.media.sizes">
      <source v-if="banner.media.webpSrcset" type="image/webp" :srcset="banner.media.webpSrcset" :sizes="banner.media.sizes">
      <img
        class="igf-page-hero__media"
        :src="banner.media.src"
        :alt="banner.imageAlt"
        :width="banner.media.width"
        :height="banner.media.height"
        loading="eager"
        fetchpriority="high"
        decoding="async"
      >
    </picture>
    <div class="igf-page-hero__overlay" />
    <div class="igf-page-hero__inner">
      <p v-if="banner.eyebrow" class="igf-page-hero__eyebrow">{{ banner.eyebrow }}</p>
      <h1>{{ banner.title || banner.subtitle }}</h1>
      <p v-if="banner.title && banner.subtitle" class="igf-page-hero__subtitle">{{ banner.subtitle }}</p>
      <p v-if="banner.description" class="igf-page-hero__description">{{ banner.description }}</p>
      <a v-if="banner.ctaUrl" class="igf-page-hero__cta" :href="banner.ctaUrl" rel="noopener">
        {{ banner.ctaLabel }}
      </a>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { presentBanner } from '../Shared/composables/bannerPresentation';
import { responsiveImagePresentation } from '../Shared/composables/responsiveImage';

defineOptions({ name: 'Banner' });
const inertiaPage = usePage();
const bannerDefaults = computed(() => inertiaPage.props?.siteSettings?.banners || {});
const banner = computed(() => {
  const raw = inertiaPage.props?.data?.banner || null;
  if (raw) {
    const presentation = presentBanner(raw, bannerDefaults.value);
    return {
      ...presentation,
      media: responsiveImagePresentation(presentation.imageUrl),
    };
  }

  const subject = inertiaPage.props?.data?.page
    || inertiaPage.props?.data?.about_us
    || inertiaPage.props?.data?.zakat
    || null;
  if (!subject) return null;

  const presentation = presentBanner({
    headline: subject.name || subject.title || inertiaPage.props?.title || '',
    subheadline: subject.sub_title || '',
    description: '',
    path: '',
  }, bannerDefaults.value);
  return {
    ...presentation,
    media: responsiveImagePresentation(presentation.imageUrl),
  };
});
</script>

<style scoped lang="scss">
.igf-page-hero { position:relative; display:flex; min-height:var(--igf-hero-height,min(590px,70vh)); align-items:flex-end; padding:clamp(80px,10vw,125px) clamp(20px,5vw,48px); overflow:hidden; background:#242220 center/cover no-repeat; color:#fff; font-family:'Hanken Grotesk',Arial,sans-serif; }
.igf-page-hero__picture { position:absolute; inset:0; }
.igf-page-hero__media { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
.igf-page-hero__overlay { position:absolute; inset:0; background:rgba(21,22,23,.68); }
.igf-page-hero__inner { position:relative; z-index:1; width:min(100%,var(--igf-content-width,1200px)); margin:0 auto; }
.igf-page-hero__eyebrow { margin:0 0 15px; color:#ffad72; font-size:12px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
.igf-page-hero h1 { max-width:820px; margin:0; color:#fff; font:650 var(--igf-heading-1,clamp(44px,6vw,74px))/1.04 'Literata',Georgia,serif; letter-spacing:-.035em; }
.igf-page-hero h1::after { display:none!important; }
.igf-page-hero__subtitle { max-width:790px; margin:18px 0 0; color:#fff; font:550 clamp(25px,3vw,38px)/1.2 'Literata',Georgia,serif; }
.igf-page-hero__description { max-width:680px; margin:22px 0 0; color:#d7d4d1; font-size:var(--igf-lead-size,clamp(17px,2vw,20px)); line-height:1.65; }
.igf-page-hero__cta { display:inline-flex; margin-top:28px; border-radius:999px; padding:13px 24px; background:#e76f2e; color:#fff; font-weight:800; text-decoration:none; }
.igf-page-hero__cta:hover,.igf-page-hero__cta:focus-visible { background:#c85319; color:#fff; }
@media(max-width:600px){.igf-page-hero{min-height:var(--igf-hero-height-mobile,590px);padding-block:72px}.igf-page-hero h1{font-size:var(--igf-heading-1-mobile,42px)}.igf-page-hero__subtitle{font-size:24px}}
</style>
