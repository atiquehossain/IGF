<template>
  <v-container v-if="sliders.length" fluid class="pa-0">
    <v-carousel v-model="currentSlide" cycle height="600px" hide-delimiter-background class="banner-carousel">
      <!-- Custom navigation arrows -->
      <template #prev="{ props }">
        <v-btn v-bind="props" :aria-label="bannerSettings.previous_slide_label || 'Previous banner'" class="banner-nav-btn banner-nav-prev" icon size="80" color="transparent" elevation="0">
          <v-icon size="60" color="white">mdi-chevron-left</v-icon>
        </v-btn>
      </template>

      <template #next="{ props }">
        <v-btn v-bind="props" :aria-label="bannerSettings.next_slide_label || 'Next banner'" class="banner-nav-btn banner-nav-next" icon size="80" color="transparent" elevation="0">
          <v-icon size="60" color="white">mdi-chevron-right</v-icon>
        </v-btn>
      </template>
      <!-- Dynamic Slides -->
      <v-carousel-item v-for="(slide, index) in sliders" :key="index" class="banner-slide">
        <div class="banner-background">
          <img v-if="slide.imageUrl" class="banner-media" :src="slide.imageUrl" :alt="slide.imageAlt">
          <div class="overlay"></div>
          <v-container class="h-100">
            <v-row class="h-100 justify-center align-center">
              <v-col lg="8" md="10" sm="12" class="content-container text-start position-relative">
                <p v-if="slide.eyebrow" class="banner-eyebrow text-white">{{ slide.eyebrow }}</p>
                <h1 class="banner-heading text-white pb-4">
                  <strong>{{ slide.title }}</strong>
                  <span class="banner-subheading d-block text-white pt-1">
                    {{ slide.subtitle }}
                  </span>
                </h1>
                <p class="banner-subtext text-white pb-4">
                  {{ slide.description }}
                </p>
                <v-btn v-if="slide.ctaUrl" class="btn-orange" size="large" rounded="pill" :href="slide.ctaUrl" rel="noopener">
                  {{ slide.ctaLabel }}
                </v-btn>
              </v-col>
            </v-row>
          </v-container>
        </div>
      </v-carousel-item>
    </v-carousel>
  </v-container>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { presentBanner } from '../../../Shared/composables/bannerPresentation';

defineOptions({
  name: 'Banner'
})

const currentSlide = ref(0);
const inertiaPage = usePage();
const bannerSettings = computed(() => inertiaPage.props?.siteSettings?.banners || {});

const sliders = computed(() => {
  const raw = inertiaPage.props?.data?.sliders || [];

  return raw.map(slider => presentBanner(slider, bannerSettings.value));
});
</script>

<style scoped lang="scss">
@use "../../../../scss/variables" as *;

.banner-carousel {
  :deep(.v-carousel__controls) {
    bottom: 20px !important;
    z-index: 3 !important;
  }

  :deep(.v-carousel__controls__item) {
    width: 15px !important;
    height: 15px !important;
    border-radius: 50% !important;
    margin: 0 4px !important;
    opacity: 1 !important;
    background-color: $white !important;

    &:hover,
    &.v-btn--active {
      background: $primary-color !important;
      opacity: 1 !important;
    }

    .v-icon {
      display: none !important;
    }
  }
}

.banner-slide {
  position: relative;
  height: 600px;
  padding: 0;
}

.banner-background {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-size: cover;
  background-position: center;
  display: flex;
  align-items: center;

  .banner-media {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    z-index: 1;
  }
}

.content-container {
  z-index: 2;
  position: relative;
//   padding: 100px 0;

  .banner-eyebrow {
    margin: 0 0 14px;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
  }

  .banner-heading {
    font-size: 60px;
    line-height: 100%;
    font-weight: 700;
  }

  .banner-subheading {
    font-weight: 400;
    margin-top: 20px;
    display: block;
  }

  .banner-subtext {
    font-size: 20px;
    line-height: 28px;
    // margin-bottom: 60px;
    text-transform: capitalize;
  }
}

/* Custom navigation buttons */
.banner-nav-btn {
  background-color: $primary-color !important;
  transition: background-color 0.3s ease;
  width: 80px !important;
  height: 80px !important;

  &:hover {
    background-color: $primary-hover !important;
  }
}

:deep(.v-carousel__prev) {
  left: 150px !important;
}

:deep(.v-carousel__next) {
  right: 150px !important;
}

/* Responsive */
@media (max-width: 960px) {
  .banner-carousel {
    :deep(.v-carousel__controls) {
      bottom: 15px !important;
    }
  }

  .content-container {
    padding: 80px 90px;

    .banner-heading {
      font-size: 48px;
    }

    .banner-subtext {
      font-size: 18px;
    }
  }

  .banner-nav-btn {
    width: 60px !important;
    height: 60px !important;

    :deep(.v-icon) {
      font-size: 26px !important;
    }
  }

  :deep(.v-carousel__prev) {
    left: 60px !important;
  }

  :deep(.v-carousel__next) {
    right: 60px !important;
  }
}

@media (max-width: 600px) {
  .banner-carousel {
    height: 500px !important;

    :deep(.v-carousel__controls) {
      bottom: 10px !important;
    }

    :deep(.v-carousel__controls__item) {
      width: 12px !important;
      height: 12px !important;
      margin: 0 3px !important;
    }
  }

  .banner-slide {
    height: 500px;
  }

  .content-container {
    padding: 60px 20px;

    .banner-heading {
      font-size: 36px;
    }

    .banner-subheading {
      font-size: 30px;
    }

    .banner-subtext {
      font-size: 16px;
      margin-bottom: 40px;
    }
  }

  .banner-nav-btn {
    display: none !important;
  }

  :deep(.v-carousel__prev),
  :deep(.v-carousel__next) {
    display: none !important;
  }
}
</style>
