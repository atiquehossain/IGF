<template>
  <v-card class="award-card flex-grow-1 rounded-30">
    <div class="card-inner">
      <v-img :src="card.thumbnail" :alt="card.name" height="192" cover class="card-image">
        <template #placeholder>
          <div class="d-flex align-center justify-center fill-height">
            <v-progress-circular color="grey-lighten-4" indeterminate />
          </div>
        </template>
      </v-img>

      <v-card-text class="pa-0 pt-4">
        <h3 class="card-title mb-3 text-ellipsis">{{ card.name }}</h3>

        <div class="mb-3">
          <v-chip v-for="(page_tag, index) in card.page_tags" :key="index" class="custom-tag-chip me-2 mb-1"
            variant="flat">
            {{ page_tag.tag.name }}
          </v-chip>
        </div>

        <p class="card-description mb-3">{{ truncatedSubtitle }}</p>
      </v-card-text>

      <v-card-actions class="pa-0 pb-3">
        <a :href="route('frontend.page', card.slug)" class="learn-more">Learn More</a>
      </v-card-actions>
    </div>
  </v-card>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  card: {
    type: Object,
    default: () => ({ page_tags: [] }),
  },
});

const truncatedSubtitle = computed(() =>
  props?.card?.sub_title?.length > 120 ? props?.card?.sub_title.slice(0, 120) + '...' : props?.card?.sub_title
);
</script>

<style scoped lang="scss">
@use "../../scss/variables" as *;

.award-card {
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
  border-radius: 20px !important;

  @media (min-width: 1264px) {
    max-width: 380px;
  }
}

.card-inner {
  padding: 30px;
  display: flex;
  flex-direction: column;
}

.card-title {
  font-size: 20px;
  font-weight: bold;
}


.card-description {
  font-size: 16px;
  line-height: 30px;
  color: rgba(93, 93, 93, 1);
}

.custom-tag-chip {
  background-color: $lumber;
  border: 1px solid $primary-color;
  color: $black;
  height: 35px;
  font-size: 13px;
  border-radius: 30px;
  padding: 0 12px;
  font-weight: 500;
}

.v-btn {
  font-weight: 400;
  color: $primary-color !important;
  text-transform: none;
  font-size: 16px;
  padding-left: 0;

  &:hover {
    text-decoration: underline;
    background-color: transparent !important;
  }
}
</style>
