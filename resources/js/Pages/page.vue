<!-- eslint-disable vue/no-v-html -->
<template>
    <layout>
        <app-banner-page v-if="!hasHeroBlock" />
        <v-container fluid class="page-container p-0">
            <PageBlocks v-if="page.visible_blocks?.length" :blocks="page.visible_blocks" />
            <article v-else v-html="page.description" class="igf-legacy-article" />
            <nav v-if="page.category_url" class="igf-page-context" :aria-label="page.category_name">
                <a :href="page.category_url"><span aria-hidden="true">←</span> {{ page.category_name }}</a>
            </nav>
        </v-container>
    </layout>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

import Layout from '../layouts/App';
import AppBannerPage from '../component/banner.vue';
import PageBlocks from '../Shared/PageBlocks.vue';

const currentPage = usePage();

const page = computed(() => currentPage.props.data?.page);
const hasHeroBlock = computed(() => (page.value?.visible_blocks || []).some(block => block?.type === 'hero'));
</script>

<style scoped lang="scss">
.about-section {
    padding-bottom: 0 !important;
}
.igf-legacy-article { width:min(calc(100% - 40px),900px); margin:0 auto; padding:clamp(70px,9vw,120px) 0; color:color-mix(in srgb,var(--igf-ink,#191c1d) 76%,var(--igf-surface,#f8f9fa)); font:17px/1.8 'Hanken Grotesk',Arial,sans-serif; }
.igf-legacy-article :deep(h2),.igf-legacy-article :deep(h3) { margin:1.3em 0 .5em; color:var(--igf-ink,#191c1d); font-family:'Literata',Georgia,serif; letter-spacing:-.02em; }
.igf-legacy-article :deep(a) { color:var(--igf-accent,#9c4500); font-weight:700; }
.igf-legacy-article :deep(img) { max-width:100%; height:auto; border-radius:14px; }
.igf-page-context { width:min(calc(100% - 40px),1120px); margin:0 auto; padding:32px 0 72px; }
.igf-page-context a { display:inline-flex; min-height:44px; align-items:center; gap:8px; color:var(--igf-accent,#9c4500); font-weight:800; text-decoration:none; }
.igf-page-context a:hover,.igf-page-context a:focus-visible { color:var(--igf-accent,#9c4500); text-decoration:underline; text-underline-offset:4px; }
@media(max-width:600px){.igf-legacy-article{width:min(calc(100% - 28px),900px)}}

.author-details {
    border: unset !important;
    padding: 12px;
    display: flex;
    justify-content: space-between;

    .title-section {
        display: flex;
        flex-direction: column;
        row-gap: 8px;

        .title {
            margin: 0 !important;
            font-size: 24px !important;
            font-weight: bolder;
        }

        .sub-title {
            margin: 0 !important;
            font-size: 18px;
        }
    }

    .content-section {
        display: flex;
        flex-direction: column;
        row-gap: 8px;
        display: none;

        p {
            margin: 0;
            font-size: 18px;
            font-weight: 400;
        }
    }
}
</style>
