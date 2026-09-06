<template>
  <aside
    v-if="configured && panelOpen"
    class="igf-consent"
    role="dialog"
    aria-modal="false"
    aria-labelledby="igf-consent-title"
    aria-describedby="igf-consent-message"
  >
    <div>
      <p id="igf-consent-title">{{ copy.title }}</p>
      <span id="igf-consent-message">{{ copy.message }}</span>
      <a v-if="copy.privacyUrl" :href="copy.privacyUrl">{{ copy.privacyLabel }}</a>
    </div>
    <div class="igf-consent__actions">
      <button type="button" class="igf-consent__decline" @click="choose('declined')">{{ copy.declineLabel }}</button>
      <button type="button" class="igf-consent__accept" @click="choose('accepted')">{{ copy.acceptLabel }}</button>
    </div>
  </aside>
  <button
    v-else-if="configured"
    type="button"
    class="igf-consent-settings"
    @click="panelOpen = true"
  >
    <span aria-hidden="true">◉</span> {{ copy.settingsLabel }}
  </button>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const CONSENT_COOKIE = 'igf_analytics_consent';
const SCRIPT_ID = 'igf-google-analytics';
const page = usePage();
const panelOpen = ref(false);
const choice = ref('');

const settings = computed(() => page.props?.siteSettings?.analytics || {});
const measurementId = computed(() => {
  const candidate = String(settings.value.google_analytics_id || '').trim().toUpperCase();
  return /^G-[A-Z0-9]+$/.test(candidate) ? candidate : '';
});
const configured = computed(() => measurementId.value !== '');
const copy = computed(() => ({
  title: settings.value.consent_title || 'Your privacy choices',
  message: settings.value.consent_message || 'We use optional analytics cookies to understand how people use this website. You can accept or decline; the site works either way.',
  acceptLabel: settings.value.accept_label || 'Accept analytics',
  declineLabel: settings.value.decline_label || 'Decline',
  privacyLabel: settings.value.privacy_label || 'Read our privacy policy',
  privacyUrl: settings.value.privacy_url || '/page/privacy-policy',
  settingsLabel: settings.value.settings_label || 'Privacy settings',
}));

function readChoice() {
  const match = document.cookie.split('; ').find(row => row.startsWith(`${CONSENT_COOKIE}=`));
  const value = match ? decodeURIComponent(match.slice(CONSENT_COOKIE.length + 1)) : '';
  return ['accepted', 'declined'].includes(value) ? value : '';
}

function remember(value) {
  const secure = window.location.protocol === 'https:' ? '; Secure' : '';
  document.cookie = `${CONSENT_COOKIE}=${encodeURIComponent(value)}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
}

function loadAnalytics() {
  if (!configured.value) return;

  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function gtag() { window.dataLayer.push(arguments); };
  window.igfAnalyticsId = measurementId.value;

  const existingScript = document.getElementById(SCRIPT_ID);
  if (!existingScript) window.gtag('js', new Date());
  window.gtag('config', measurementId.value, { anonymize_ip: true });

  if (existingScript) return;
  const script = document.createElement('script');
  script.id = SCRIPT_ID;
  script.async = true;
  script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId.value)}`;
  document.head.appendChild(script);
}

function disableAnalytics() {
  if (measurementId.value) window[`ga-disable-${measurementId.value}`] = true;
  delete window.igfAnalyticsId;
}

function choose(value) {
  choice.value = value;
  remember(value);
  panelOpen.value = false;
  if (value === 'accepted') {
    window[`ga-disable-${measurementId.value}`] = false;
    loadAnalytics();
  } else {
    disableAnalytics();
  }
}

function applySavedChoice() {
  choice.value = readChoice();
  panelOpen.value = configured.value && choice.value === '';
  if (choice.value === 'accepted') loadAnalytics();
  if (choice.value === 'declined') disableAnalytics();
}

onMounted(applySavedChoice);
watch(measurementId, (nextId, previousId) => {
  if (previousId && previousId !== nextId) window[`ga-disable-${previousId}`] = true;
  delete window.igfAnalyticsId;
  applySavedChoice();
});
</script>

<style scoped>
.igf-consent{position:fixed;z-index:1400;right:clamp(14px,3vw,34px);bottom:clamp(14px,3vw,28px);display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:24px;width:min(calc(100% - 28px),760px);padding:22px 24px;border:1px solid color-mix(in srgb,var(--igf-primary,#ff7500) 35%,#ddd);border-radius:16px;background:#fff;color:var(--igf-ink,#191c1d);box-shadow:0 18px 55px rgba(25,28,29,.2);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-consent p{margin:0 0 6px;font:700 20px/1.2 'Literata',Georgia,serif}.igf-consent span{display:block;color:#555b60;font-size:14px;line-height:1.55}.igf-consent a{display:inline-block;margin-top:8px;color:var(--igf-accent,#9c4500);font-size:13px;font-weight:800;text-underline-offset:3px}.igf-consent__actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}.igf-consent button,.igf-consent-settings{min-height:44px;padding:10px 17px;border-radius:999px;font-weight:850}.igf-consent__decline{border:1px solid #8c8178;background:#fff;color:#302d2a}.igf-consent__accept{border:1px solid var(--igf-primary,#ff7500);background:var(--igf-primary,#ff7500);color:#fff}.igf-consent-settings{position:fixed;z-index:1390;left:12px;bottom:12px;border:1px solid #d9d3ce;background:#fff;color:var(--igf-ink,#191c1d);box-shadow:0 5px 20px rgba(25,28,29,.12);font-size:12px}.igf-consent-settings span{color:var(--igf-primary,#ff7500)}@media(max-width:700px){.igf-consent{grid-template-columns:1fr;gap:16px;padding:19px}.igf-consent__actions{justify-content:stretch}.igf-consent__actions button{flex:1}.igf-consent-settings{max-width:calc(100% - 100px)}}
</style>
