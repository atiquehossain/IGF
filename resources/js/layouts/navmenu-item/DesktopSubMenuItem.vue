<template>
  <!-- Simple List Item -->
  <v-list-item v-if="!hasChildren" :href="route(item.link, [item.slug])">
    <v-list-item-title>{{ item.name }}</v-list-item-title>
  </v-list-item>

  <!-- Nested Dropdown -->
  <v-menu v-else location="end top" transition="scale-transition" open-on-hover>
    <template v-slot:activator="{ props: activatorProps }">
      <v-list-item v-bind="{ ...activatorProps, ...(item.slug ? { href: route(item.link, [item.slug]) } : {}) }">
        <v-list-item-title>
          {{ item.name }}
          <v-icon size="small" class="ml-1">mdi-chevron-right</v-icon>
        </v-list-item-title>
      </v-list-item>
    </template>
    <v-list>
      <DesktopSubMenuItem v-for="(child, childIndex) in item.children" :key="`desktop-nested-${level}-${childIndex}`"
        :item="child" :index="childIndex" :level="level + 1" />
    </v-list>
  </v-menu>
</template>

<script setup>
import { computed } from 'vue';

defineOptions({
  name: 'DesktopSubMenuItem'
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
    default: 1
  }
});

const hasChildren = computed(() => props.item.children && props.item.children.length > 0);

// Access the global route function
const route = window.route || (() => '#');
</script>

<style scoped lang="scss">
@use '../../../scss/variables' as *;

.v-list-item-title {
  font-size: 14px;
}

.v-list-item:hover {
  color: $primary-color;
  text-shadow: 0 0 0.4px $primary-hover;
}
</style>
