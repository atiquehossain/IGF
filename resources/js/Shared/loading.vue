<template>
  <v-dialog v-bind="dialogProps" @update:model-value="updateIsLoading" fullscreen hide-overlay
    content-class="v-dialog-loading" transition="dialog-top-transition">
    <div style="height: 100%" class="page-hero d-flex align-items-center justify-content-center">
      <div class="text-center">
        <v-progress-circular :size="80" :width="7" color="amber" indeterminate></v-progress-circular>
      </div>
    </div>
  </v-dialog>
</template>

<script setup>
import { computed, defineProps, defineComponent } from 'vue';

defineComponent({
  name: 'Loading',
});

const props = defineProps({
  isLoading: {
    type: Boolean,
    required: true
  }
});

const emit = defineEmits(['update:isLoading']);
const dialogProps = computed(() => ({
  modelValue: props.isLoading
}));

const updateIsLoading = (value) => {
  emit('update:isLoading', value);
};
</script>
