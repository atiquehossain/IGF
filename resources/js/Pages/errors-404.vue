<template>
  <Layout>
    <div class="igf-not-found">
      <div class="igf-not-found__inner">
        <div class="igf-not-found__code" aria-hidden="true">404</div>
        <p>{{ settings.not_found_eyebrow }}</p>
        <h1>{{ settings.not_found_title }}</h1>
        <span>{{ settings.not_found_body }}</span>
        <div><a class="igf-button igf-button--primary" :href="route('frontend.home')">{{ settings.home_label }}</a><a class="igf-button" :href="route('search')">{{ settings.search_label }}</a></div>
      </div>
    </div>
  </Layout>
</template>
<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
const page = usePage();
const settings = computed(() => page.props.siteSettings?.system_pages || {});
</script>
<style scoped>
.igf-not-found{display:grid;min-height:72vh;padding:90px 20px;place-items:center;background:#202223;color:#fff;font-family:'Hanken Grotesk',Arial,sans-serif;text-align:center}.igf-not-found__inner{width:min(100%,700px)}.igf-not-found__code{margin-bottom:14px;color:#ff7500;font:750 clamp(90px,18vw,180px)/.8 'Literata',Georgia,serif;letter-spacing:-.08em}.igf-not-found__inner>p{margin:0 0 15px;color:#ffb070;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.igf-not-found h1{margin:0;color:#fff;font:650 clamp(38px,6vw,58px)/1.08 'Literata',Georgia,serif;letter-spacing:-.035em}.igf-not-found h1::after{display:none!important}.igf-not-found__inner>span{display:block;max-width:590px;margin:22px auto 0;color:#d5d6d7;font-size:17px;line-height:1.65}.igf-not-found__inner>div:last-child{display:flex;flex-wrap:wrap;justify-content:center;gap:12px;margin-top:32px}.igf-button{display:inline-flex;min-height:50px;align-items:center;justify-content:center;padding:0 24px;border:1px solid rgba(255,255,255,.45);border-radius:999px;color:#fff;font-size:13px;font-weight:800;text-decoration:none}.igf-button--primary{border-color:#ff7500;background:#ff7500}@media(max-width:480px){.igf-not-found__inner>div:last-child{flex-direction:column}.igf-button{width:100%}}
</style>
