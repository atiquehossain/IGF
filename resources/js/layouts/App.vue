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
      <meta v-if="ogImageAlt" head-key="og:image:alt" property="og:image:alt" :content="ogImageAlt">
      <meta head-key="og:description" property="og:description" :content="metaTag.og_description || metaTag.meta_description || ''">
      <meta head-key="og:site_name" property="og:site_name" :content="appName">

      <meta head-key="twitter:card" name="twitter:card" :content="metaTag.twitter_card || 'summary_large_image'">
      <meta head-key="twitter:title" name="twitter:title" :content="metaTag.twitter_title || metaTag.og_title || seoTitle">
      <meta head-key="twitter:description" name="twitter:description" :content="metaTag.twitter_description || metaTag.og_description || metaTag.meta_description || ''">
      <meta v-if="metaTag.twitter_image || metaTag.og_image" head-key="twitter:image" name="twitter:image" :content="metaTag.twitter_image || metaTag.og_image">
      <meta v-if="twitterImageAlt" head-key="twitter:image:alt" name="twitter:image:alt" :content="twitterImageAlt">
    </Head>

    <StructuredData v-if="schemaJson" :json="schemaJson" />

    <div class="igf-site-shell" :class="locale" :id="id" :style="themeStyle">
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
import { resolveSeoAlternates, resolveSeoMetadata, resolveStructuredData } from '../Shared/seoMetadata';
import { resolvePageCss } from '../Shared/pageCss';
import { managedThemeTokens } from '../Shared/utils/themeColors';
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
    seoPolicy: inertiaPage.props?.seoPolicy,
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
const ogImageAlt = computed(() => String(metaTag.value?.og_image_alt || '').trim());
const twitterImageAlt = computed(() => String(metaTag.value?.twitter_image_alt || '').trim());
const shellLabels = computed(() => ({
  skipLink: inertiaPage.props?.siteSettings?.header?.skip_link_label || 'Skip to main content',
}));
const schemaJson = computed(() => {
  return resolveStructuredData({
    schema: metaTag.value?.schema_markup,
    identity: inertiaPage.props?.seoSchemaIdentity,
    metadata: metaTag.value,
    locale: localeSeo.value.current,
  });
});

const pageCss = computed(() => resolvePageCss(inertiaPage.component, inertiaPage.props?.data));
const locale = computed(() => inertiaPage.props?.locale || 'en');
const designPresets = {
  font_pairing: {
    editorial: { '--igf-font-body': "'Hanken Grotesk',Arial,sans-serif", '--igf-font-heading': "'Literata',Georgia,serif" },
    modern: { '--igf-font-body': "'Hanken Grotesk',Arial,sans-serif", '--igf-font-heading': "'Hanken Grotesk',Arial,sans-serif" },
    classic: { '--igf-font-body': 'Arial,Helvetica,sans-serif', '--igf-font-heading': "Georgia,'Times New Roman',serif" },
  },
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
    soft: { '--igf-card-border': 'color-mix(in srgb,var(--igf-ink) 12%,var(--igf-surface))', '--igf-card-radius': 'var(--igf-card-radius-soft)', '--igf-card-shadow': 'var(--igf-shadow-card-soft)' },
    outlined: { '--igf-card-border': 'color-mix(in srgb,var(--igf-ink) 36%,var(--igf-surface))', '--igf-card-radius': 'var(--igf-card-radius-outlined)', '--igf-card-shadow': 'none' },
    elevated: { '--igf-card-border': 'transparent', '--igf-card-radius': 'var(--igf-card-radius-elevated)', '--igf-card-shadow': 'var(--igf-shadow-card-elevated)' },
  },
  corner_radius: {
    square: { '--igf-radius-sm': '2px', '--igf-radius-md': '4px', '--igf-radius-lg': '6px', '--igf-card-radius-soft': '4px', '--igf-card-radius-outlined': '2px', '--igf-card-radius-elevated': '6px' },
    soft: { '--igf-radius-sm': '7px', '--igf-radius-md': '10px', '--igf-radius-lg': '16px', '--igf-card-radius-soft': '16px', '--igf-card-radius-outlined': '10px', '--igf-card-radius-elevated': '20px' },
    rounded: { '--igf-radius-sm': '12px', '--igf-radius-md': '18px', '--igf-radius-lg': '28px', '--igf-card-radius-soft': '24px', '--igf-card-radius-outlined': '18px', '--igf-card-radius-elevated': '30px' },
  },
  shadow_density: {
    flat: { '--igf-shadow-header': 'none', '--igf-shadow-floating': 'none', '--igf-shadow-control': 'none', '--igf-shadow-card-soft': 'none', '--igf-shadow-card-elevated': 'none' },
    subtle: { '--igf-shadow-header': '0 2px 8px rgba(25,28,29,.07)', '--igf-shadow-floating': '0 14px 34px rgba(25,28,29,.16)', '--igf-shadow-control': '0 5px 14px color-mix(in srgb,var(--igf-primary) 18%,transparent)', '--igf-shadow-card-soft': '0 5px 18px rgba(25,28,29,.05)', '--igf-shadow-card-elevated': '0 16px 36px rgba(25,28,29,.14)' },
    strong: { '--igf-shadow-header': '0 5px 18px rgba(25,28,29,.16)', '--igf-shadow-floating': '0 20px 46px rgba(25,28,29,.24)', '--igf-shadow-control': '0 8px 22px color-mix(in srgb,var(--igf-primary) 30%,transparent)', '--igf-shadow-card-soft': '0 9px 28px rgba(25,28,29,.12)', '--igf-shadow-card-elevated': '0 24px 50px rgba(25,28,29,.22)' },
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
  font_pairing: 'editorial', content_width: 'standard', heading_size: 'standard', body_text_size: 'standard', section_spacing: 'standard',
  hero_height: 'standard', image_size: 'standard', image_shape: 'landscape', card_columns: '3',
  card_style: 'soft', corner_radius: 'soft', shadow_density: 'subtle', button_shape: 'pill', logo_size: 'standard',
};
const designTokens = computed(() => Object.entries(designPresets).reduce((tokens, [field, presets]) => {
  const selected = String(inertiaPage.props?.siteSettings?.design?.[field] ?? designDefaults[field]);
  return { ...tokens, ...(presets[selected] ?? presets[designDefaults[field]]) };
}, {}));
const headerPresentationPresets = {
  classic: { '--igf-header-announcement-bg': 'var(--igf-accent)', '--igf-header-announcement-text': 'var(--igf-on-accent)', '--igf-header-utility-bg': 'color-mix(in srgb,var(--igf-ink) 4%,var(--igf-surface))', '--igf-header-nav-bg': 'color-mix(in srgb,var(--igf-surface) 84%,#fff)', '--igf-header-border': 'color-mix(in srgb,var(--igf-ink) 12%,var(--igf-surface))', '--igf-header-text': 'color-mix(in srgb,var(--igf-ink) 72%,var(--igf-surface))', '--igf-header-strong': 'var(--igf-ink)', '--igf-header-hover-bg': 'color-mix(in srgb,var(--igf-primary) 5%,var(--igf-surface))', '--igf-header-active-bg': 'color-mix(in srgb,var(--igf-primary) 10%,var(--igf-surface))', '--igf-header-active-text': 'var(--igf-accent)' },
  minimal: { '--igf-header-announcement-bg': '#303236', '--igf-header-announcement-text': '#fff', '--igf-header-utility-bg': 'var(--igf-surface)', '--igf-header-nav-bg': 'color-mix(in srgb,var(--igf-surface) 84%,#fff)', '--igf-header-border': 'color-mix(in srgb,var(--igf-ink) 10%,var(--igf-surface))', '--igf-header-text': 'color-mix(in srgb,var(--igf-ink) 72%,var(--igf-surface))', '--igf-header-strong': 'var(--igf-ink)', '--igf-header-hover-bg': 'color-mix(in srgb,var(--igf-ink) 4%,var(--igf-surface))', '--igf-header-active-bg': 'color-mix(in srgb,var(--igf-ink) 6%,var(--igf-surface))', '--igf-header-active-text': 'var(--igf-ink)' },
  soft: { '--igf-header-announcement-bg': 'var(--igf-accent)', '--igf-header-announcement-text': 'var(--igf-on-accent)', '--igf-header-utility-bg': 'color-mix(in srgb,var(--igf-primary) 7%,var(--igf-surface))', '--igf-header-nav-bg': 'color-mix(in srgb,var(--igf-primary) 3%,var(--igf-surface))', '--igf-header-border': 'color-mix(in srgb,var(--igf-accent) 18%,var(--igf-surface))', '--igf-header-text': 'color-mix(in srgb,var(--igf-ink) 72%,var(--igf-surface))', '--igf-header-strong': 'var(--igf-ink)', '--igf-header-hover-bg': 'color-mix(in srgb,var(--igf-primary) 14%,var(--igf-surface))', '--igf-header-active-bg': 'color-mix(in srgb,var(--igf-primary) 18%,var(--igf-surface))', '--igf-header-active-text': 'var(--igf-accent)' },
};
const headerDensityPresets = {
  compact: { '--igf-header-utility-height': '34px', '--igf-header-nav-height': '70px', '--igf-header-nav-height-mobile': '64px' },
  standard: { '--igf-header-utility-height': '38px', '--igf-header-nav-height': '80px', '--igf-header-nav-height-mobile': '70px' },
  spacious: { '--igf-header-utility-height': '44px', '--igf-header-nav-height': '92px', '--igf-header-nav-height-mobile': '78px' },
};
const footerPresentationPresets = {
  dark: { '--igf-footer-bg': '#202124', '--igf-footer-text': '#d2d3d4', '--igf-footer-muted': '#b9bbbd', '--igf-footer-strong': '#fff', '--igf-footer-heading': 'color-mix(in srgb,var(--igf-primary) 62%,#fff)', '--igf-footer-link': '#c5c7c8', '--igf-footer-link-hover': 'color-mix(in srgb,var(--igf-primary) 48%,#fff)', '--igf-footer-border': 'rgba(255,255,255,.12)', '--igf-footer-field-border': 'rgba(255,255,255,.24)', '--igf-footer-field-bg': '#fff', '--igf-footer-field-text': '#202124', '--igf-footer-social-bg': 'rgba(255,255,255,.08)', '--igf-footer-badge-bg': 'color-mix(in srgb,var(--igf-primary) 12%,transparent)', '--igf-footer-badge-text': 'color-mix(in srgb,var(--igf-primary) 40%,#fff)', '--igf-footer-success-bg': 'rgba(38,129,68,.2)', '--igf-footer-success-border': 'rgba(119,221,151,.45)', '--igf-footer-success-text': '#c9f7d7', '--igf-footer-error-bg': 'rgba(176,51,40,.22)', '--igf-footer-error-border': 'rgba(255,138,128,.55)', '--igf-footer-error-text': '#ffd8d4' },
  light: { '--igf-footer-bg': '#f4f1ed', '--igf-footer-text': '#45464a', '--igf-footer-muted': '#626469', '--igf-footer-strong': 'var(--igf-ink)', '--igf-footer-heading': 'var(--igf-accent)', '--igf-footer-link': '#45464a', '--igf-footer-link-hover': 'color-mix(in srgb,var(--igf-accent) 82%,#000)', '--igf-footer-border': 'rgba(25,28,29,.16)', '--igf-footer-field-border': 'rgba(25,28,29,.3)', '--igf-footer-field-bg': '#fff', '--igf-footer-field-text': '#202124', '--igf-footer-social-bg': 'rgba(25,28,29,.09)', '--igf-footer-badge-bg': 'color-mix(in srgb,var(--igf-accent) 10%,transparent)', '--igf-footer-badge-text': 'var(--igf-accent)', '--igf-footer-success-bg': '#e3f5e8', '--igf-footer-success-border': '#438757', '--igf-footer-success-text': '#225c33', '--igf-footer-error-bg': '#fde8e6', '--igf-footer-error-border': '#b34a3f', '--igf-footer-error-text': '#7e2e27' },
  warm: { '--igf-footer-bg': '#3b2114', '--igf-footer-text': '#eaded5', '--igf-footer-muted': '#d5c2b4', '--igf-footer-strong': '#fffaf5', '--igf-footer-heading': 'color-mix(in srgb,var(--igf-primary) 48%,#fff)', '--igf-footer-link': '#eaded5', '--igf-footer-link-hover': 'color-mix(in srgb,var(--igf-primary) 36%,#fff)', '--igf-footer-border': 'rgba(255,250,245,.17)', '--igf-footer-field-border': 'rgba(255,250,245,.3)', '--igf-footer-field-bg': '#fffaf5', '--igf-footer-field-text': '#28180e', '--igf-footer-social-bg': 'rgba(255,250,245,.1)', '--igf-footer-badge-bg': 'color-mix(in srgb,var(--igf-primary) 14%,transparent)', '--igf-footer-badge-text': 'color-mix(in srgb,var(--igf-primary) 36%,#fff)', '--igf-footer-success-bg': 'rgba(38,129,68,.28)', '--igf-footer-success-border': 'rgba(119,221,151,.55)', '--igf-footer-success-text': '#d9fbe3', '--igf-footer-error-bg': 'rgba(176,51,40,.3)', '--igf-footer-error-border': 'rgba(255,138,128,.65)', '--igf-footer-error-text': '#ffe1de' },
};
const footerLayoutPresets = {
  columns: { '--igf-footer-shell-width': '1440px', '--igf-footer-body-columns': 'minmax(220px,.9fr) minmax(0,2.1fr)', '--igf-footer-body-legal-columns': 'minmax(250px,.85fr) minmax(0,3.15fr)', '--igf-footer-body-tablet-columns': 'minmax(240px,.82fr) minmax(0,1.8fr)', '--igf-footer-content-legal-columns': 'minmax(0,2.1fr) minmax(300px,1fr)', '--igf-footer-content-tablet-columns': 'repeat(2,minmax(0,1fr))' },
  stacked: { '--igf-footer-shell-width': '1100px', '--igf-footer-body-columns': '1fr', '--igf-footer-body-legal-columns': '1fr', '--igf-footer-body-tablet-columns': '1fr', '--igf-footer-content-legal-columns': '1fr', '--igf-footer-content-tablet-columns': '1fr' },
};
function presetTokens(group, field, presets, fallback) {
  const selected = String(inertiaPage.props?.siteSettings?.[group]?.[field] ?? fallback);
  return presets[selected] ?? presets[fallback];
}
const headerTokens = computed(() => {
  const sticky = inertiaPage.props?.siteSettings?.header?.sticky;
  const isSticky = sticky === undefined || sticky === true || sticky === 1 || sticky === '1';
  return {
    ...presetTokens('header', 'presentation', headerPresentationPresets, 'classic'),
    ...presetTokens('header', 'density', headerDensityPresets, 'standard'),
    '--igf-header-position': isSticky ? 'sticky' : 'relative',
  };
});
const footerTokens = computed(() => ({
  ...presetTokens('footer', 'presentation', footerPresentationPresets, 'dark'),
  ...presetTokens('footer', 'layout', footerLayoutPresets, 'columns'),
}));
const themeStyle = computed(() => {
  const colors = managedThemeTokens(inertiaPage.props?.siteSettings?.theme);

  return {
    ...colors,
    '--orange': colors['--igf-primary'],
    '--brown': colors['--igf-accent'],
    '--ink': colors['--igf-ink'],
    '--surface': colors['--igf-surface'],
    ...designTokens.value,
    ...headerTokens.value,
    ...footerTokens.value,
  };
});

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
  },
  { immediate: true }
);

</script>

<style scoped lang="scss">
.igf-site-shell { font-family:var(--igf-font-body,'Hanken Grotesk',Arial,sans-serif); }
.sticky-header {
  position: var(--igf-header-position,sticky);
  top: 0;
  z-index: 1000;
  box-shadow: var(--igf-shadow-header,0 2px 8px rgba(25,28,29,.07));
}
.content { min-height:50vh; font-family:var(--igf-font-body,'Hanken Grotesk',Arial,sans-serif); font-size:var(--igf-body-size,17px); }
.content :deep(:where(div,section,article,aside,nav,header,footer,p,a,button,input,select,textarea,label,li,dt,dd,small,legend,blockquote,figcaption)) { font-family:var(--igf-font-body,'Hanken Grotesk',Arial,sans-serif)!important; }
.content :deep(h1),.content :deep(h2),.content :deep(h3),.content :deep(h4),.content :deep(h5),.content :deep(h6) { font-family:var(--igf-font-heading,'Literata',Georgia,serif)!important; }
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
:deep(.footer-contact i) { color:var(--igf-primary) !important; }
.sticky-header :deep(.donate-button),
:deep(.footer-social a:hover) { background:var(--igf-primary) !important; color:var(--igf-on-primary) !important; }
.igf-skip-link {
  position: fixed;
  z-index: 2000;
  top: 10px;
  left: 10px;
  padding: 11px 16px;
  border-radius: var(--igf-radius-sm,8px);
  background: #191c1d;
  color: #fff;
  font-family:var(--igf-font-body,'Hanken Grotesk',Arial,sans-serif);
  font-weight: 800;
  text-decoration: none;
  transform: translateY(-160%);
}
.igf-skip-link:focus { transform: translateY(0); outline: 3px solid var(--igf-primary); outline-offset: 2px; }
</style>
