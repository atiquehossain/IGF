<template>
  <Layout>
    <div class="igf-payment">
      <section class="igf-payment__card">
        <div class="igf-payment__icon"><i class="fa-solid fa-xmark" aria-hidden="true" /></div>
        <p class="igf-eyebrow">{{ settings.failure_eyebrow }}</p>
        <h1>{{ settings.failure_title }}</h1>
        <p class="igf-payment__message">{{ data.message }}</p>
        <dl v-if="transaction" class="igf-payment__summary">
          <div v-if="transaction.amount"><dt>{{ settings.transaction_attempted_amount_label }}</dt><dd>{{ formatTransactionAmount(transaction.amount, transaction.currency) }}</dd></div>
          <div v-if="transaction.reference"><dt>{{ settings.transaction_reference_label }}</dt><dd>{{ transaction.reference }}</dd></div>
          <div v-if="transaction.created_at"><dt>{{ settings.transaction_date_label }}</dt><dd>{{ formatDate(transaction.created_at) }}</dd></div>
        </dl>
        <div class="igf-help"><i class="fa-regular fa-circle-question" aria-hidden="true" /><p><strong>{{ settings.failure_help_title }}</strong><span>{{ settings.failure_help_body }}</span></p></div>
        <div class="igf-payment__actions"><a class="igf-button igf-button--primary" :href="data.redirect_url">{{ settings.try_again_label }}</a><a class="igf-button" :href="route('frontend.contactUs')">{{ settings.contact_label }}</a></div>
      </section>
    </div>
  </Layout>
</template>
<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
import { formatDateTime, formatMoney } from '../Shared/composables/siteSettings';
const page = usePage();
const settings = computed(() => page.props.siteSettings?.system_pages || {});
const regional = computed(() => page.props.siteSettings?.regional || {});
const data = computed(() => page.props.data || {});
const transaction = computed(() => data.value.transaction || null);
const formatTransactionAmount = (amount, currency) => formatMoney(amount, regional.value, { currencyCode: currency, minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatDate = value => formatDateTime(value, regional.value);
</script>
<style scoped>
.igf-payment{--surface:var(--igf-surface,#f8f9fa);--orange:var(--igf-primary,#ff7500);--brown:var(--igf-accent,#9c4500);--ink:var(--igf-ink,#191c1d);--muted:color-mix(in srgb,var(--ink) 68%,var(--surface));--line:color-mix(in srgb,var(--ink) 14%,var(--surface));display:grid;min-height:75vh;padding:clamp(70px,9vw,110px) 20px;place-items:center;background:var(--surface);color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-payment__card{width:min(100%,660px);padding:clamp(30px,6vw,58px);border:1px solid var(--line);border-top:5px solid #a33b32;border-radius:20px;background:#fff;box-shadow:0 14px 44px rgba(25,28,29,.09);text-align:center}.igf-payment__icon{display:grid;width:72px;height:72px;margin:0 auto 23px;place-items:center;border-radius:50%;background:#fae9e7;color:#a33b32;font-size:30px}.igf-eyebrow{margin:0 0 12px;color:#92352d;font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-payment h1{margin:0;color:var(--ink);font:650 clamp(34px,5vw,48px)/1.12 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-payment h1::after{display:none!important}.igf-payment__message{margin:20px auto 28px;color:var(--muted);font-size:17px;line-height:1.65}.igf-payment__summary{margin:0 0 22px;padding:7px 24px;border:1px solid var(--line);border-radius:13px;background:var(--surface);text-align:left}.igf-payment__summary>div{display:grid;grid-template-columns:150px minmax(0,1fr);gap:15px;padding:14px 0;border-bottom:1px solid var(--line)}.igf-payment__summary>div:last-child{border:0}.igf-payment__summary dt{color:var(--muted);font-size:13px;font-weight:700}.igf-payment__summary dd{overflow-wrap:anywhere;margin:0;color:var(--ink);font-weight:800;text-align:right}.igf-help{display:flex;gap:14px;padding:20px;border-radius:12px;background:#fff3e9;text-align:left}.igf-help>i{margin-top:3px;color:var(--brown);font-size:21px}.igf-help p{display:grid;gap:5px;margin:0}.igf-help span{color:var(--muted);font-size:13px;line-height:1.55}.igf-payment__actions{display:flex;justify-content:center;gap:12px;margin-top:29px}.igf-button{display:inline-flex;min-height:49px;align-items:center;justify-content:center;padding:0 22px;border:1px solid #aaa;border-radius:999px;color:var(--ink);font-size:13px;font-weight:800;text-decoration:none}.igf-button--primary{border-color:var(--orange);background:var(--orange);color:var(--igf-on-primary,#000)}@media(max-width:560px){.igf-payment__summary>div{grid-template-columns:1fr;gap:5px}.igf-payment__summary dd{text-align:left}.igf-payment__actions{flex-direction:column}.igf-button{width:100%}}
</style>
