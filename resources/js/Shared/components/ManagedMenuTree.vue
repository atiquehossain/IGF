<template>
  <ul
    v-if="visibleItems.length"
    class="managed-menu-tree"
    :data-menu-depth="depth"
    :id="listId || undefined"
    :aria-labelledby="labelledBy || undefined"
    :hidden="hidden || undefined"
  >
    <li
      v-for="(item, index) in visibleItems"
      :key="item.uuid || item.id || `${depth}-${index}-${item.name}`"
      class="managed-menu-tree__item"
      :class="{ 'has-children': hasChildren(item) }"
      @keydown="handleItemKeydown($event, item, index)"
    >
      <template v-if="disclosure">
        <div v-if="hasChildren(item) && href(item) !== '#'" class="managed-menu-tree__parent-row">
          <a
            :id="labelId(item, index)"
            class="managed-menu-tree__link"
            :href="href(item)"
            :data-mobile-nav-control="drawerControls ? '' : undefined"
            @click="emit('navigate', item)"
          >{{ item.name }}</a>
          <button
            :id="toggleId(item, index)"
            class="managed-menu-tree__toggle"
            type="button"
            :aria-expanded="String(isExpanded(item, index))"
            :aria-controls="panelId(item, index)"
            :aria-label="toggleLabel(item, index)"
            :data-mobile-nav-control="drawerControls ? '' : undefined"
            @click="toggle(item, index)"
          >
            <i class="fa-solid fa-chevron-down" :class="{ 'is-rotated': isExpanded(item, index) }" aria-hidden="true" />
          </button>
        </div>
        <button
          v-else-if="hasChildren(item)"
          :id="toggleId(item, index)"
          class="managed-menu-tree__disclosure"
          type="button"
          :aria-expanded="String(isExpanded(item, index))"
          :aria-controls="panelId(item, index)"
          :data-mobile-nav-control="drawerControls ? '' : undefined"
          @click="toggle(item, index)"
        >
          <span :id="labelId(item, index)">{{ item.name }}</span>
          <i class="fa-solid fa-chevron-down" :class="{ 'is-rotated': isExpanded(item, index) }" aria-hidden="true" />
        </button>
        <a
          v-else-if="href(item) !== '#'"
          class="managed-menu-tree__link"
          :href="href(item)"
          :data-mobile-nav-control="drawerControls ? '' : undefined"
          @click="emit('navigate', item)"
        >{{ item.name }}</a>
        <span v-else class="managed-menu-tree__label">{{ item.name }}</span>
      </template>

      <template v-else>
        <a
          v-if="href(item) !== '#'"
          class="managed-menu-tree__link"
          :href="href(item)"
          :aria-haspopup="hasChildren(item) ? 'true' : undefined"
        >{{ item.name }}</a>
        <span
          v-else
          class="managed-menu-tree__label"
          :tabindex="focusBranchLabels && hasChildren(item) ? 0 : undefined"
          :aria-haspopup="focusBranchLabels && hasChildren(item) ? 'true' : undefined"
        >{{ item.name }}</span>
      </template>

      <ManagedMenuTree
        v-if="hasChildren(item)"
        :items="item.children"
        :depth="depth + 1"
        :max-depth="maxDepth"
        :focus-branch-labels="focusBranchLabels"
        :disclosure="disclosure"
        :drawer-controls="drawerControls"
        :id-prefix="childIdPrefix(item, index)"
        :list-id="disclosure ? panelId(item, index) : ''"
        :labelled-by="disclosure ? labelId(item, index) : ''"
        :hidden="disclosure && !isExpanded(item, index)"
        :open-label-template="openLabelTemplate"
        :close-label-template="closeLabelTemplate"
        @navigate="emit('navigate', $event)"
      />
    </li>
  </ul>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';
import { interpolateSetting } from '../composables/siteSettings';
import { publicMenuHref } from '../utils/publicMenu';

defineOptions({ name: 'ManagedMenuTree' });
const props = defineProps({
  items: { type: Array, default: () => [] },
  depth: { type: Number, default: 1 },
  maxDepth: { type: Number, default: 3 },
  focusBranchLabels: { type: Boolean, default: false },
  disclosure: { type: Boolean, default: false },
  drawerControls: { type: Boolean, default: false },
  idPrefix: { type: String, default: 'managed-menu' },
  listId: { type: String, default: '' },
  labelledBy: { type: String, default: '' },
  hidden: { type: Boolean, default: false },
  openLabelTemplate: { type: String, default: 'Open {item} submenu' },
  closeLabelTemplate: { type: String, default: 'Close {item} submenu' },
});
const emit = defineEmits(['navigate']);
const openBranches = ref(new Set());

const visibleItems = computed(() => props.items.filter(isVisibleItem));

function isVisibleItem(item) {
  return item && typeof item === 'object' && String(item.name || '').trim();
}

function href(item) {
  return publicMenuHref(item);
}

function hasChildren(item) {
  return props.depth < props.maxDepth && Array.isArray(item.children) && item.children.some(isVisibleItem);
}

function itemToken(item, index) {
  const identity = String(item?.uuid || item?.id || item?.name || 'item');
  let hash = 2166136261;
  for (let character = 0; character < identity.length; character += 1) {
    hash ^= identity.charCodeAt(character);
    hash = Math.imul(hash, 16777619);
  }
  return `${index}-${(hash >>> 0).toString(36)}`;
}

function branchKey(item, index) {
  return itemToken(item, index);
}

function childIdPrefix(item, index) {
  return `${props.idPrefix}-${itemToken(item, index)}`;
}

function toggleId(item, index) {
  return `${childIdPrefix(item, index)}-toggle`;
}

function panelId(item, index) {
  return `${childIdPrefix(item, index)}-panel`;
}

function labelId(item, index) {
  return `${childIdPrefix(item, index)}-label`;
}

function isExpanded(item, index) {
  return openBranches.value.has(branchKey(item, index));
}

function toggle(item, index) {
  const key = branchKey(item, index);
  const next = new Set(openBranches.value);
  if (next.has(key)) next.delete(key);
  else next.add(key);
  openBranches.value = next;
}

function toggleLabel(item, index) {
  return interpolateSetting(
    isExpanded(item, index) ? props.closeLabelTemplate : props.openLabelTemplate,
    { item: item?.name || '' }
  );
}

function handleItemKeydown(event, item, index) {
  if (!props.disclosure || event.key !== 'Escape' || !hasChildren(item) || !isExpanded(item, index)) return;
  event.preventDefault();
  event.stopPropagation();
  toggle(item, index);
  nextTick(() => document.getElementById(toggleId(item, index))?.focus());
}
</script>
