<template>
  <!-- Simple Link Item -->
  <v-btn v-if="!hasChildren" variant="text" class="nav-link mx-1" :id="`desktop-nav-${index}`"
    :href="route(item.link, [item.slug])">
    {{ item.name }}
  </v-btn>

  <!-- Dropdown Menu -->
  <v-menu v-else location="bottom start" transition="scale-transition" open-on-hover>
    <template v-slot:activator="{ props: activatorProps }">
      <v-btn variant="text" class="nav-link mx-1" v-bind="activatorProps">
        {{ item.name }}
        <v-icon size="small" class="ml-1">mdi-chevron-down</v-icon>
      </v-btn>
    </template>
    <v-list>
      <DesktopSubMenuItem v-for="(child, childIndex) in item.children" :key="`desktop-dropdown-${childIndex}`"
        :item="child" :index="childIndex" :level="level + 1" />
    </v-list>
  </v-menu>
</template>

<script setup>
import { computed } from 'vue';
import DesktopSubMenuItem from './DesktopSubMenuItem.vue';

defineOptions({
  name: 'DesktopMenuItem'
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

.nav-link {
  color: rgba(0, 0, 0, 0.87);
  font-weight: normal;
  letter-spacing: normal;
  text-transform: none;
  position: relative;
  transition: all 0.3s ease;
  height: auto;
  min-width: auto;

  &:hover {
    color: $primary-color;
    text-shadow: 0 0 0.4px $primary-hover;
  }

  &.active::after,
  &:focus::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 10px;
    right: 10px;
    height: 2px;
    background-color: $primary-color;
  }
}
</style>
