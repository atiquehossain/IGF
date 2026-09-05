<template>
  <v-app>
    <Head :title="seoTitle">
      <meta head-key="description" name="description" :content="metaTag.meta_description || ''">
      <meta head-key="robots" name="robots" :content="metaTag.robots || 'noindex,nofollow,noarchive'">
    </Head>
    <main id="main-content" class="igf-guest-theme" :style="themeStyle" tabindex="-1"><slot /></main>
  </v-app>
</template>

<script setup>
import { computed, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { useGlobal } from '../Shared/composables/global'; // Adjust the path to where your composable is
import { managedThemeTokens } from '../Shared/utils/themeColors';

const inertiaPage = usePage();
const { $toast } = useGlobal();
const metaTag = computed(() => inertiaPage.props?.meta_tag || {});
const seoTitle = computed(() => metaTag.value.meta_title || inertiaPage.props?.title || 'Ignite Global Foundation');
const themeStyle = computed(() => managedThemeTokens(inertiaPage.props?.siteSettings?.theme));

watch(
  () => inertiaPage.props.flash?.message,
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

<style scoped>
.igf-guest-theme :deep(.igf-auth),
.igf-guest-theme :deep(.igf-verify) {
  --orange: var(--igf-primary) !important;
  --brown: var(--igf-accent) !important;
  --ink: var(--igf-ink) !important;
  --surface: var(--igf-surface) !important;
}
</style>
