<template>
  <Layout id="home-page">
    <HomeBanner v-if="managedBanner.show" :class="managedBanner.visibilityClass" />
    <PageBlocks v-if="homeBlocks.length" :blocks="homeBlocks" />
    <section v-else-if="!managedBanner.show" class="home-empty" aria-live="polite">
      <h1>{{ emptyCopy.title }}</h1>
      <p v-if="emptyCopy.message">{{ emptyCopy.message }}</p>
    </section>
  </Layout>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Layout from '../../layouts/App';
import PageBlocks from '../../Shared/PageBlocks.vue';
import HomeBanner from './component/banner.vue';
import { managedHomeBannerPresentation } from './homePresentation';

const currentPage = usePage();
const homeBlocks = computed(() => currentPage.props.data?.homePage?.visible_blocks || []);
const managedBanner = computed(() => managedHomeBannerPresentation(
  homeBlocks.value,
  (currentPage.props.data?.sliders || []).length > 0,
));
const emptyCopy = computed(() => ({
  title: currentPage.props.siteSettings?.shared_blocks?.home_empty_title
    || currentPage.props.siteSettings?.branding?.site_name
    || currentPage.props.appName
    || '',
  message: currentPage.props.siteSettings?.shared_blocks?.home_empty_message || '',
}));
</script>

<style scoped>
.home-empty { min-height:60vh; padding:100px 24px; text-align:center; }
.home-empty h1 { margin-bottom:16px; font-family:'Literata',Georgia,serif; }
.home-managed-banner--mobile-only { display:none; }
@media (max-width:767px) {
  .home-managed-banner--mobile-only { display:block; }
  .home-managed-banner--desktop-only { display:none; }
}
</style>
