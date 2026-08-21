<template>
  <!-- Simple List Item -->
  <v-list-item v-if="!hasChildren" :href="route(item.link, [item.slug])" class="mobile-list-item py-3"
    :style="{ paddingLeft: (level * 16 + 16) + 'px' }">
    <v-list-item-title class="mobile-menu-text">
      {{ item.name }}
    </v-list-item-title>
  </v-list-item>

  <!-- Expandable Group -->
  <v-list-group v-else :value="`mobile-group-${level}-${index}`" class="mobile-list-group">
    <template v-slot:activator="{ props: activatorProps }">
      <v-list-item v-bind="activatorProps" class="mobile-list-item py-3" :style="{ paddingLeft: (level * 16 + 16) + 'px' }">
        <template v-slot:title>
          <a v-if="item.slug" :href="route(item.link, [item.slug])" class="mobile-menu-text">
            {{ item.name }}
          </a>
          <span v-else class="mobile-menu-text">
            {{ item.name }}
          </span>
        </template>
      </v-list-item>
    </template>

    <MobileMenuItem v-for="(child, childIndex) in item.children" :key="`mobile-child-${level}-${childIndex}`"
      :item="child" :index="childIndex" :level="level + 1" />
  </v-list-group>
</template>

<script setup>
import { computed } from 'vue';

defineOptions({
  name: 'MobileMenuItem'
});

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  index: {
    type: Number,
    required: true
  },
  level: {
    type: Number,
    default: 0
  }
});

const hasChildren = computed(() => props.item.children && props.item.children.length > 0);

// Access the global route function
const route = window.route || (() => '#');
</script>

<style scoped lang="scss">
@use '../../../scss/variables' as *;

.mobile-menu-text {
  font-size: 12px;
  font-weight: 500;
  color: $black;
}

.mobile-list-item {
  min-height: 44px;

  &:hover,
  &.v-list-item--active {
    color: $primary-color !important;
    background-color: transparent !important;

    .mobile-menu-text {
      color: $primary-color !important;
    }
  }
}

.mobile-list-group {
  :deep(.v-list-group__header) {
    min-height: 44px;
  }

  :deep(.v-list-item__title) {
    font-size: 12px;
  }

  :deep(.v-list-group__items .v-list-item) {
    padding-left: 36px;
  }
}
</style>
