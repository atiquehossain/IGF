<template>
  <Layout>
    <div class="igf-payment" :class="`igf-payment--${resultState}`" :data-result-state="resultState">
      <section class="igf-payment__card" aria-labelledby="payment-result-title">
        <div class="igf-payment__icon" data-test="payment-result-icon"><i :class="resultIcon" aria-hidden="true" /></div>
        <p class="igf-eyebrow">{{ resultCopy.eyebrow }}</p>
        <h1 id="payment-result-title">{{ resultCopy.title }}</h1>
        <p class="igf-payment__message">{{ resultCopy.message }}</p>
        <dl v-if="transaction" class="igf-payment__summary">
          <div v-if="transaction.donor_name"><dt>{{ settings.transaction_donor_label }}</dt><dd>{{ transaction.donor_name }}</dd></div>
          <div><dt>{{ settings.transaction_amount_label }}</dt><dd>{{ formatTransactionAmount(transaction.amount, transaction.currency) }}</dd></div>
          <div v-if="transaction.payment_method"><dt>{{ settings.transaction_method_label }}</dt><dd>{{ transaction.payment_method }}</dd></div>
          <div v-if="transaction.reference"><dt>{{ settings.transaction_reference_label }}</dt><dd>{{ transaction.reference }}</dd></div>
          <div v-if="transaction.created_at"><dt>{{ settings.transaction_date_label }}</dt><dd>{{ formatDate(transaction.created_at) }}</dd></div>
        </dl>
        <p class="igf-payment__note"><i :class="resultNoteIcon" aria-hidden="true" /> {{ resultCopy.note }}</p>
        <div class="igf-payment__actions"><a class="igf-button igf-button--primary" :href="route('frontend.home')">{{ settings.home_label }}</a><a class="igf-button" :href="data.redirect_url">{{ settings.another_donation_label }}</a></div>
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
const rawResultState = computed(() => String(data.value.result_state || '').trim().toLowerCase());
const isConfirmedSuccess = computed(() => rawResultState.value === 'success');
const resultState = computed(() => isConfirmedSuccess.value ? 'success' : 'review');
const resultIcon = computed(() => isConfirmedSuccess.value ? 'fa-solid fa-check' : 'fa-regular fa-clock');
const resultNoteIcon = computed(() => isConfirmedSuccess.value ? 'fa-solid fa-shield-halved' : 'fa-solid fa-circle-info');
const resultCopy = computed(() => {
  const suppliedCopy = data.value.result_copy && typeof data.value.result_copy === 'object'
    ? data.value.result_copy
    : {};

  if (isConfirmedSuccess.value) {
    return {
      eyebrow: settings.value.success_eyebrow || suppliedCopy.eyebrow || '',
      title: settings.value.success_title || suppliedCopy.title || '',
      message: data.value.message || suppliedCopy.message || '',
      note: settings.value.success_note || suppliedCopy.note || '',
    };
  }

  // Unknown states deliberately ignore server-supplied success-like claims and
  // fall back to the localized, neutral review copy shared by the CMS.
  const controllerCopy = rawResultState.value === 'review'
    ? suppliedCopy
    : {};

  return {
    eyebrow: controllerCopy.eyebrow || settings.value.review_eyebrow || '',
    title: controllerCopy.title || settings.value.review_title || '',
    message: controllerCopy.message || settings.value.review_message || '',
    note: controllerCopy.note || settings.value.review_note || '',
  };
});
const formatTransactionAmount = (amount, currency) => formatMoney(amount, regional.value, { currencyCode: currency, minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatDate = value => formatDateTime(value, regional.value);
</script>
<style scoped>
.igf-payment{--surface:var(--igf-surface,#f8f9fa);--orange:var(--igf-primary,#ff7500);--action-orange:var(--igf-accent,#9c4500);--action-orange-hover:var(--igf-accent,#9c4500);--brown:var(--igf-accent,#9c4500);--ink:var(--igf-ink,#191c1d);--muted:color-mix(in srgb,var(--ink) 68%,var(--surface));--line:color-mix(in srgb,var(--ink) 14%,var(--surface));display:grid;min-height:75vh;padding:clamp(70px,9vw,110px) 20px;place-items:center;background:var(--surface);color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-payment__card{width:min(100%,690px);padding:clamp(30px,6vw,58px);border:1px solid var(--line);border-top:5px solid var(--orange);border-radius:20px;background:#fff;box-shadow:0 14px 44px rgba(25,28,29,.09);text-align:center}.igf-payment__icon{display:grid;width:72px;height:72px;margin:0 auto 23px;place-items:center;border-radius:50%;font-size:30px}.igf-payment--success .igf-payment__icon{background:#e9f6ec;color:#18733a}.igf-payment--review .igf-payment__card{border-top-color:#c47a00}.igf-payment--review .igf-payment__icon{background:#fff1cf;color:#8a5700}.igf-eyebrow{margin:0 0 12px;color:var(--brown);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-payment--review .igf-eyebrow{color:#805100}.igf-payment h1{margin:0;color:var(--ink);font:650 clamp(34px,5vw,48px)/1.12 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-payment h1::after{display:none!important}.igf-payment__message{margin:20px auto 28px;color:var(--muted);font-size:17px;line-height:1.65}.igf-payment__summary{margin:0;padding:7px 24px;border:1px solid var(--line);border-radius:13px;background:var(--surface);text-align:left}.igf-payment--review .igf-payment__summary{border-color:#ead19f;background:#fffaf0}.igf-payment__summary>div{display:grid;grid-template-columns:150px minmax(0,1fr);gap:15px;padding:14px 0;border-bottom:1px solid var(--line)}.igf-payment__summary>div:last-child{border:0}.igf-payment__summary dt{color:var(--muted);font-size:13px;font-weight:700}.igf-payment__summary dd{overflow-wrap:anywhere;margin:0;color:var(--ink);font-weight:800;text-align:right}.igf-payment__note{display:flex;align-items:flex-start;gap:9px;margin:22px 0 0;color:var(--muted);font-size:12px;text-align:left}.igf-payment__note i{margin-top:2px;color:var(--brown)}.igf-payment--review .igf-payment__note{border-left:3px solid #c47a00;padding:11px 13px;background:#fff8e7;color:#5f4b27}.igf-payment--review .igf-payment__note i{color:#8a5700}.igf-payment__actions{display:flex;justify-content:center;gap:12px;margin-top:29px}.igf-button{display:inline-flex;min-height:49px;align-items:center;justify-content:center;padding:0 22px;border:1px solid #aaa;border-radius:999px;color:var(--ink);font-size:13px;font-weight:800;text-decoration:none}.igf-button--primary{border-color:var(--action-orange);background:var(--action-orange);color:var(--igf-on-accent,#fff)}.igf-button--primary:hover{border-color:var(--action-orange-hover);background:var(--action-orange-hover)}.igf-button:focus-visible{outline:3px solid color-mix(in srgb,var(--brown) 30%,transparent);outline-offset:3px}@media(max-width:560px){.igf-payment__summary>div{grid-template-columns:1fr;gap:5px}.igf-payment__summary dd{text-align:left}.igf-payment__actions{flex-direction:column}.igf-button{width:100%}}
</style>
