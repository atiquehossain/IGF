<!-- eslint-disable vue/no-v-html -->
<template>
  <div v-if="splashScreen && modalOpen">
    <div class="text-center">
      <v-dialog v-model="modalOpen" width="500" content-class="ecw-disclaimer-dialog">
        <v-card>
          <v-card-title class="text-h5 grey lighten-2 header-section">
            <h2 class="title">
              {{ splashScreen.title }}
            </h2>
            <p class="content">
              {{ settings.updated_label }} {{ releaseDate }}
            </p>
          </v-card-title>

          <v-card-text class="content-section">
            <div class="data">
              <article v-html="splashScreen.details" />
            </div>
          </v-card-text>

          <v-card-actions class="actions">
            <button type="button" @click="dismiss">
              {{ settings.dismiss_label }}
            </button>
            <button type="button" @click="dismiss">
              {{ settings.continue_label }}
            </button>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useGlobal } from '../Shared/composables/global';
import { formatDate } from '../Shared/composables/siteSettings';

const modalOpen = ref(false);

const inertiaPage = usePage();
const { $cookies } = useGlobal();

const splashScreen = computed(() => inertiaPage.props?.splashScreen || null);
const settings = computed(() => inertiaPage.props?.siteSettings?.splash || {});
const releaseDate = computed(() => formatDate(
  splashScreen.value?.published_at,
  inertiaPage.props?.siteSettings?.regional,
));
const cookieKey = computed(() => {
  const version = splashScreen.value?.public_version || splashScreen.value?.uuid || 'current';
  return `igf_splash_${String(version).replace(/[^a-z0-9_-]/gi, '')}`;
});

// Methods
const refreshVisibility = () => {
  modalOpen.value = !!splashScreen.value && !$cookies.get(cookieKey.value);
};

const dismiss = () => {
  $cookies.set(cookieKey.value, '1', '30d', '/');
  modalOpen.value = false;
};

// Initialize on mount
onMounted(() => {
  refreshVisibility();
});
watch(() => splashScreen.value?.public_version, refreshVisibility);
</script>

<style lang="scss" scoped>
h1,
h2,
h3,
.data__title {
  margin: 0;
  font-size: 16px;
  font-weight: bold;
  color: #24242B;
  text-align: left;
  line-height: 24px;
}

p .data__content {
  margin: 0;
  font-size: 16px;
  font-weight: normal;
  line-height: 24px;
  text-align: left;
  color: rgba(36, 36, 43, 0.75);
}
</style>
