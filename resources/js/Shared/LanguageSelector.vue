<template>
  <div class="ml-4">
    <Link :href="route('frontend.language', [selectableLocale])">
    <Icon class="w-5 h-5" width="50px" :name="selectableLocale" />
    </Link>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from './Icon.vue';

// Access current Inertia page props
const page = usePage();

// Determine opposite locale
const selectableLocale = computed(() => {
  return page.props?.locale === 'en' ? 'bn' : 'en';
});
</script>
