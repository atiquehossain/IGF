<template>
  <div class="reusable-preview" @click.capture="stopNavigation" @submit.capture.stop.prevent>
    <aside class="reusable-preview__notice" role="status">
      <strong>Safe section preview</strong>
      <span>{{ localeLabel }} · {{ statusLabel }} · Links and form submissions are disabled.</span>
    </aside>
    <PageBlocks :blocks="[block]" />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import PageBlocks from '../Shared/PageBlocks.vue';

const props = defineProps({
  block: { type: Object, required: true },
  libraryStatus: { type: String, default: 'active' },
  previewLocale: { type: String, default: 'en' },
});

const statusLabel = computed(() => props.libraryStatus === 'disabled'
  ? 'Currently hidden from connected pages'
  : 'Currently available on connected pages');
const localeLabel = computed(() => props.previewLocale.toUpperCase());

function stopNavigation(event) {
  const link = event.target.closest?.('a');
  if (!link) return;
  event.preventDefault();
  event.stopPropagation();
}
</script>

<style scoped>
.reusable-preview { min-height:100vh; background:#fff; }
.reusable-preview__notice { position:sticky; z-index:100; top:0; display:flex; min-height:46px; align-items:center; justify-content:center; gap:8px; padding:8px 16px; border-bottom:1px solid #e4d8cc; background:#fff6ea; color:#62360f; font:13px/1.35 'Hanken Grotesk',Arial,sans-serif; text-align:center; }
.reusable-preview__notice strong { font-weight:850; }
.reusable-preview :deep(.igf-page-blocks) { min-height:calc(100vh - 46px); }
@media (max-width:600px) { .reusable-preview__notice { align-items:flex-start; flex-direction:column; gap:1px; } }
</style>
