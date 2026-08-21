<template>
  <v-app>

    <Head :title="seoTitle">
      <meta head-key="description" name="description" :content="metaTag.meta_description || ''">
      <meta head-key="robots" name="robots" :content="metaTag.robots || 'index,follow'">
      <link v-if="metaTag.canonical_url" head-key="canonical" rel="canonical" :href="metaTag.canonical_url">
      <link
        v-for="alternate in alternateLinks"
        :key="alternate.locale"
        :head-key="`alternate-${alternate.locale}`"
        rel="alternate"
        :hreflang="alternate.locale"
        :href="alternate.url"
      >
      <link head-key="alternate-x-default" rel="alternate" hreflang="x-default" :href="xDefaultUrl">

      <meta head-key="og:title" property="og:title" :content="metaTag.og_title || seoTitle">
      <meta head-key="og:type" property="og:type" content="website">
      <meta head-key="og:url" property="og:url" :content="metaTag.canonical_url || ''">
      <meta v-if="metaTag.og_image" head-key="og:image" property="og:image" :content="metaTag.og_image">
      <meta head-key="og:description" property="og:description" :content="metaTag.og_description || metaTag.meta_description || ''">
      <meta head-key="og:site_name" property="og:site_name" :content="appName">

      <meta head-key="twitter:card" name="twitter:card" :content="metaTag.twitter_card || 'summary_large_image'">
      <meta head-key="twitter:title" name="twitter:title" :content="metaTag.twitter_title || metaTag.og_title || seoTitle">
      <meta head-key="twitter:description" name="twitter:description" :content="metaTag.twitter_description || metaTag.og_description || metaTag.meta_description || ''">
      <meta v-if="metaTag.twitter_image || metaTag.og_image" head-key="twitter:image" name="twitter:image" :content="metaTag.twitter_image || metaTag.og_image">
    </Head>

    <StructuredData v-if="schemaJson" :json="schemaJson" />

    <div :class="locale" :id="id" :style="themeStyle">
      <a class="igf-skip-link" href="#main-content">{{ shellLabels.skipLink }}</a>
      <SafeStyle v-if="pageCss" element-id="customStyle" :css="pageCss" />
      <AppSplashScreen />
      <!-- Sticky Header Container -->
      <div class="sticky-header">
        <AppHeader />
        <AppNav />
      </div>
      <!-- <AppSocial /> -->
      <!-- <LanguageSelector /> -->
      <main id="main-content" class="content" tabindex="-1">
        <slot />
      </main>
      <AppFooter />
      <WebsiteChat />
      <!-- <AppCookies /> -->
    </div>
  </v-app>
</template>

<script setup>
import { computed, watch } from 'vue';
import { usePage, Head } from '@inertiajs/vue3';
import { useGlobal } from '../Shared/composables/global';

// layout
import AppHeader from './AppHeader';
import AppSplashScreen from './AppSplashScreen.vue';
import AppNav from './AppNav';
// import AppSocial from './AppSocial';
// import LanguageSelector from '../Shared/LanguageSelector';
import AppFooter from './AppFooter';
import SafeStyle from '../Shared/SafeStyle';
import StructuredData from '../Shared/StructuredData';
import WebsiteChat from '../Shared/WebsiteChat';
import { resolveSeoAlternates, resolveSeoMetadata } from '../Shared/seoMetadata';
import { resolvePageCss } from '../Shared/pageCss';
// import AppCookies from './AppCookies';

defineProps({
  id: {
    type: String,
    default: undefined,
  },
});

const inertiaPage = usePage();
const { $toast } = useGlobal();

// Computed values
const title = computed(() => inertiaPage.props?.title);
const localeSeo = computed(() => ({
  current: String(inertiaPage.props?.seoLocale?.current || inertiaPage.props?.locale || 'en'),
  default: String(inertiaPage.props?.seoLocale?.default || 'en'),
  public: Array.isArray(inertiaPage.props?.seoLocale?.public) ? inertiaPage.props.seoLocale.public : ['en'],
  parameter: String(inertiaPage.props?.seoLocale?.query_parameter || 'lang'),
}));
const localizedUrl = (value, targetLocale) => {
  if (!value) return '';
  const isAbsolute = /^[a-z][a-z\d+.-]*:\/\//i.test(value);
  const hasExplicitPath = !isAbsolute || /^https?:\/\/[^/?#]+\//i.test(value);
  const parsed = new URL(value, 'http://localhost');
  parsed.hash = '';
  parsed.searchParams.delete(localeSeo.value.parameter);
  if (targetLocale !== localeSeo.value.default) {
    parsed.searchParams.set(localeSeo.value.parameter, targetLocale);
  }
  if (!isAbsolute) return `${parsed.pathname}${parsed.search}`;
  if (!hasExplicitPath && parsed.pathname === '/') return `${parsed.origin}${parsed.search}`;
  return parsed.toString();
};
const metaTag = computed(() => {
  const merged = resolveSeoMetadata({
    seoDefaults: inertiaPage.props?.seoDefaults,
    metaTag: inertiaPage.props?.meta_tag,
    routeSeo: inertiaPage.props?.routeSeo,
    contentSeo: inertiaPage.props?.contentSeo,
  });
  return {
    ...merged,
    canonical_url: localizedUrl(merged.canonical_url || '', localeSeo.value.current),
  };
});
const alternateCluster = computed(() => resolveSeoAlternates({
  cluster: inertiaPage.props?.seoAlternates,
  canonicalUrl: metaTag.value.canonical_url,
  currentLocale: localeSeo.value.current,
}));
const alternateLinks = computed(() => alternateCluster.value.links);
const xDefaultUrl = computed(() => alternateCluster.value.xDefault);
const appName = computed(() => inertiaPage.props?.siteSettings?.branding?.site_name || inertiaPage.props?.appName || 'Ignite Global Foundation');
const seoTitle = computed(() => metaTag.value?.meta_title || title.value || appName.value);
const shellLabels = computed(() => ({
  skipLink: inertiaPage.props?.siteSettings?.header?.skip_link_label || 'Skip to main content',
}));
const schemaJson = computed(() => {
  const schema = metaTag.value?.schema_markup;
  if (!schema) return '';
  return typeof schema === 'string' ? schema : JSON.stringify(schema);
});

const pageCss = computed(() => resolvePageCss(inertiaPage.component, inertiaPage.props?.data));
const locale = computed(() => inertiaPage.props?.locale || 'en');
const designPresets = {
  content_width: {
    compact: { '--igf-content-width': '1040px' },
    standard: { '--igf-content-width': '1240px' },
    wide: { '--igf-content-width': '1400px' },
  },
  heading_size: {
    compact: { '--igf-heading-1': 'clamp(38px,5vw,64px)', '--igf-heading-2': 'clamp(30px,3.6vw,44px)', '--igf-heading-3': '20px', '--igf-heading-1-mobile': '36px', '--igf-heading-2-mobile': '30px' },
    standard: { '--igf-heading-1': 'clamp(42px,6vw,76px)', '--igf-heading-2': 'clamp(34px,4vw,52px)', '--igf-heading-3': '22px', '--igf-heading-1-mobile': '42px', '--igf-heading-2-mobile': '34px' },
    large: { '--igf-heading-1': 'clamp(48px,7vw,88px)', '--igf-heading-2': 'clamp(38px,5vw,62px)', '--igf-heading-3': '25px', '--igf-heading-1-mobile': '48px', '--igf-heading-2-mobile': '38px' },
  },
  body_text_size: {
    compact: { '--igf-body-size': '15px', '--igf-lead-size': 'clamp(17px,1.8vw,19px)', '--igf-testimonial-text-size': 'clamp(20px,2.5vw,28px)' },
    standard: { '--igf-body-size': '17px', '--igf-lead-size': 'clamp(18px,2vw,21px)', '--igf-testimonial-text-size': 'clamp(22px,3vw,33px)' },
    large: { '--igf-body-size': '19px', '--igf-lead-size': 'clamp(20px,2.2vw,24px)', '--igf-testimonial-text-size': 'clamp(26px,3.4vw,38px)' },
  },
  section_spacing: {
    compact: { '--igf-section-block': 'clamp(56px,7vw,88px)', '--igf-section-mobile': '52px' },
    standard: { '--igf-section-block': 'clamp(72px,9vw,120px)', '--igf-section-mobile': '68px' },
    generous: { '--igf-section-block': 'clamp(88px,11vw,144px)', '--igf-section-mobile': '82px' },
  },
  hero_height: {
    compact: { '--igf-hero-height': 'min(650px,78vh)', '--igf-hero-height-mobile': '580px' },
    standard: { '--igf-hero-height': 'min(780px,86vh)', '--igf-hero-height-mobile': '680px' },
    tall: { '--igf-hero-height': 'min(900px,92vh)', '--igf-hero-height-mobile': '780px' },
  },
  image_size: {
    compact: { '--igf-card-media-height': '170px' },
    standard: { '--igf-card-media-height': '230px' },
    large: { '--igf-card-media-height': '310px' },
  },
  image_shape: {
    square: { '--igf-image-aspect': '1 / 1' },
    portrait: { '--igf-image-aspect': '4 / 5' },
    landscape: { '--igf-image-aspect': '4 / 3' },
    wide: { '--igf-image-aspect': '16 / 9' },
  },
  card_columns: {
    2: { '--igf-card-columns': '2' },
    3: { '--igf-card-columns': '3' },
    4: { '--igf-card-columns': '4' },
  },
  card_style: {
    soft: { '--igf-card-border': '#e5e0dc', '--igf-card-radius': '16px', '--igf-card-shadow': '0 5px 18px rgba(25,28,29,.05)' },
    outlined: { '--igf-card-border': '#9f9389', '--igf-card-radius': '10px', '--igf-card-shadow': 'none' },
    elevated: { '--igf-card-border': 'transparent', '--igf-card-radius': '20px', '--igf-card-shadow': '0 16px 36px rgba(25,28,29,.14)' },
  },
  button_shape: {
    square: { '--igf-button-radius': '4px' },
    rounded: { '--igf-button-radius': '10px' },
    pill: { '--igf-button-radius': '999px' },
  },
  logo_size: {
    compact: { '--igf-brand-font-size': '17px', '--igf-brand-mark-width': '32px', '--igf-brand-mark-size': '27px' },
    standard: { '--igf-brand-font-size': '20px', '--igf-brand-mark-width': '38px', '--igf-brand-mark-size': '32px' },
    large: { '--igf-brand-font-size': '23px', '--igf-brand-mark-width': '44px', '--igf-brand-mark-size': '38px' },
  },
};
const designDefaults = {
  content_width: 'standard', heading_size: 'standard', body_text_size: 'standard', section_spacing: 'standard',
  hero_height: 'standard', image_size: 'standard', image_shape: 'landscape', card_columns: '3',
  card_style: 'soft', button_shape: 'pill', logo_size: 'standard',
};
const designTokens = computed(() => Object.entries(designPresets).reduce((tokens, [field, presets]) => {
  const selected = String(inertiaPage.props?.siteSettings?.design?.[field] ?? designDefaults[field]);
  return { ...tokens, ...(presets[selected] ?? presets[designDefaults[field]]) };
}, {}));
const themeStyle = computed(() => ({
  '--igf-primary': inertiaPage.props?.siteSettings?.theme?.primary_color || '#ff7500',
  '--igf-accent': inertiaPage.props?.siteSettings?.theme?.accent_color || '#9c4500',
  '--igf-ink': inertiaPage.props?.siteSettings?.theme?.ink_color || '#191c1d',
  '--igf-surface': inertiaPage.props?.siteSettings?.theme?.surface_color || '#f8f9fa',
  '--orange': inertiaPage.props?.siteSettings?.theme?.primary_color || '#ff7500',
  '--brown': inertiaPage.props?.siteSettings?.theme?.accent_color || '#9c4500',
  '--ink': inertiaPage.props?.siteSettings?.theme?.ink_color || '#191c1d',
  '--surface': inertiaPage.props?.siteSettings?.theme?.surface_color || '#f8f9fa',
  ...designTokens.value,
}));

// Flash message toast handling
watch(
  () => inertiaPage.props?.flash?.message,
  (message) => {
    if (!message) return;

    switch (message.type) {
      case 'success':
        $toast.success(message.text);
        break;
      case 'info':
        $toast.info(message.text);
        break;
      case 'warning':
        $toast.warning(message.text);
        break;
      case 'error':
        $toast.error(message.text);
        break;
    }
  }
);

</script>

<style scoped lang="scss">
.sticky-header {
  position: sticky;
  top: 0;
  z-index: 1000;
  box-shadow: 0 2px 8px rgba(25, 28, 29, 0.07);
}
.content { min-height: 50vh; }
.content :deep(*) {
  --orange: var(--igf-primary) !important;
  --brown: var(--igf-accent) !important;
  --ink: var(--igf-ink) !important;
  --surface: var(--igf-surface) !important;
}
.sticky-header :deep(.site-brand__mark),
.sticky-header :deep(.desktop-nav__item a:hover),
.sticky-header :deep(.desktop-nav__trigger:hover),
.sticky-header :deep(.nav-icon:hover),
:deep(.footer-brand__name i),
:deep(.footer-contact i),
:deep(.footer-links h2) { color:var(--igf-primary) !important; }
.sticky-header :deep(.donate-button),
:deep(.footer-social a:hover) { background:var(--igf-primary) !important; }
.igf-skip-link {
  position: fixed;
  z-index: 2000;
  top: 10px;
  left: 10px;
  padding: 11px 16px;
  border-radius: 8px;
  background: #191c1d;
  color: #fff;
  font-weight: 800;
  text-decoration: none;
  transform: translateY(-160%);
}
.igf-skip-link:focus { transform: translateY(0); outline: 3px solid #ff7500; outline-offset: 2px; }
</style>
