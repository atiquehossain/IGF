<template>
  <footer class="site-footer">
    <div class="site-footer__shell">
      <section
        v-if="footer.newsletterTitle || footer.newsletterBody"
        class="footer-newsletter"
        :aria-labelledby="footer.newsletterTitle ? 'footer-newsletter-title' : undefined"
      >
        <div class="footer-newsletter__intro">
          <h2 v-if="footer.newsletterTitle" id="footer-newsletter-title">{{ footer.newsletterTitle }}</h2>
          <p v-if="footer.newsletterBody">{{ footer.newsletterBody }}</p>
        </div>
        <form @submit.prevent="subscribe">
          <label class="sr-only" for="footer-newsletter-email">{{ shared.newsletter_email_label }}</label>
          <div class="footer-newsletter__row">
            <input id="footer-newsletter-email" v-model="newsletterEmail" name="email" type="email" autocomplete="email" :placeholder="shared.newsletter_email_placeholder" required :aria-invalid="newsletterFeedbackType === 'error' ? 'true' : undefined" :aria-describedby="newsletterMessage ? 'footer-newsletter-message' : undefined">
            <button type="submit" :disabled="newsletterBusy">{{ newsletterBusy ? shared.newsletter_subscribing_label : shared.newsletter_subscribe_label }}</button>
          </div>
          <label class="footer-newsletter__consent"><input v-model="newsletterConsent" name="consent" type="checkbox" required> <span>{{ shared.newsletter_consent_prefix }} <a :href="shared.newsletter_privacy_url">{{ shared.newsletter_privacy_label }}</a>.</span></label>
          <p
            v-if="newsletterMessage"
            id="footer-newsletter-message"
            class="footer-newsletter__message"
            :class="`is-${newsletterFeedbackType}`"
            :role="newsletterFeedbackType === 'error' ? 'alert' : 'status'"
          >{{ newsletterMessage }}</p>
        </form>
      </section>

      <div class="footer-body" :class="{ 'has-legal-status': legalStatusColumn }">
        <div class="footer-brand">
          <a href="/" class="footer-brand__name"><img :src="branding.footerLogo" :alt="branding.footerLogoAlt" :width="branding.footerLogoWidth" :height="branding.footerLogoHeight" decoding="async"></a>
          <small v-if="branding.taglineLines.length" class="footer-brand__tagline">
            <span v-for="(line, index) in branding.taglineLines" :key="`${index}-${line}`">{{ line }}</span>
          </small>
          <div class="footer-contact-block">
            <address class="footer-contact">
              <span v-if="contact.address" class="footer-contact__address">
                <i class="fa-solid fa-location-dot" aria-hidden="true" />
                <span class="footer-contact__copy"><strong v-if="contact.footer_address_label">{{ contact.footer_address_label }}:</strong> {{ contact.address }}</span>
              </span>
              <a v-if="contact.phone_primary" :href="`tel:${phoneHref}`">
                <i class="fa-solid fa-phone" aria-hidden="true" />
                <span class="footer-contact__copy"><strong v-if="contact.footer_phone_label">{{ contact.footer_phone_label }}:</strong> {{ contact.phone_primary }}</span>
              </a>
              <a v-if="contact.phone_secondary" :href="`tel:${phoneSecondaryHref}`">
                <i class="fa-solid fa-phone" aria-hidden="true" />
                <span class="footer-contact__copy"><strong v-if="contact.footer_secondary_phone_label">{{ contact.footer_secondary_phone_label }}:</strong> {{ contact.phone_secondary }}</span>
              </a>
              <a v-if="contact.email" :href="`mailto:${contact.email}`">
                <i class="fa-regular fa-envelope" aria-hidden="true" />
                <span class="footer-contact__copy"><strong v-if="contact.footer_email_label">{{ contact.footer_email_label }}:</strong> {{ contact.email }}</span>
              </a>
            </address>
          </div>
          <div v-if="hasSocialProfiles" class="footer-social" :aria-label="footer.socialProfilesLabel">
            <a v-if="social.facebook" :href="social.facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true" /></a>
            <a v-if="social.instagram" :href="social.instagram" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true" /></a>
            <a v-if="social.linkedin" :href="social.linkedin" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true" /></a>
            <a v-if="social.youtube" :href="social.youtube" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><i class="fa-brands fa-youtube" aria-hidden="true" /></a>
          </div>
        </div>

        <div
          v-if="navigationColumns.length || legalStatusColumn"
          class="footer-content"
          :class="{ 'has-legal-status': legalStatusColumn }"
        >
          <nav
            v-if="navigationColumns.length"
            class="footer-links footer-navigation"
            :aria-label="footer.navigationLabel"
            :style="{ '--footer-nav-columns': Math.max(1, Math.min(navigationColumns.length, 3)) }"
          >
            <section
              v-for="(column, index) in navigationColumns"
              :key="column.uuid || column.title"
              class="footer-link-group"
              :data-footer-column="column.uuid || column.title"
            >
              <h2 class="footer-link-group__heading">
                <button
                  v-if="compactFooter"
                  class="footer-link-group__toggle"
                  type="button"
                  :aria-expanded="isFooterColumnOpen(column, index)"
                  :aria-controls="footerColumnPanelId(column, index)"
                  @click="toggleFooterColumn(column, index)"
                >
                  <span>{{ column.title }}</span>
                  <i class="fa-solid fa-chevron-down" aria-hidden="true" />
                </button>
                <span v-else>{{ column.title }}</span>
              </h2>
              <div
                v-show="isFooterColumnOpen(column, index)"
                :id="footerColumnPanelId(column, index)"
                class="footer-link-group__links"
              >
                <a v-for="item in column.links" :key="item.uuid || item.name" :href="menuHref(item)">{{ item.name }}</a>
              </div>
            </section>
          </nav>

          <section v-if="legalStatusColumn" class="footer-legal-status" :data-footer-column="legalStatusColumn.uuid">
            <h2>{{ legalStatus.heading }}</h2>
            <ul class="footer-legal-status__list">
              <li v-for="item in legalStatus.items" :key="item.key" class="footer-legal-status__item">
                <img
                  v-if="item.logo"
                  class="footer-legal-status__logo"
                  :src="item.logo"
                  alt=""
                  loading="lazy"
                  decoding="async"
                >
                <span v-else class="footer-legal-status__badge" aria-hidden="true">{{ item.badge }}</span>
                <span class="footer-legal-status__copy">
                  <strong>{{ item.label }}</strong>
                  <small>{{ item.registration }}</small>
                </span>
              </li>
            </ul>
          </section>
        </div>
      </div>

      <div v-if="footer.copyright" class="footer-bottom">
        <p>{{ footer.copyright }}</p>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { responsiveImagePresentation } from '../Shared/composables/responsiveImage';

defineOptions({ name: 'AppFooter' });
const inertiaPage = usePage();
const settings = computed(() => inertiaPage.props.siteSettings || {});
const branding = computed(() => {
  const values = settings.value.branding || {};
  const siteName = values.site_name || inertiaPage.props.appName || 'Ignite Global Foundation';
  const footerLogo = values.footer_logo || values.logo || '/image/logo-footer.png';
  const footerLogoMedia = responsiveImagePresentation(footerLogo);
  const tagline = String(values.tagline ?? '').trim();
  return {
    siteName,
    footerLogo,
    footerLogoAlt: values.footer_logo_alt || values.logo_alt || siteName,
    footerLogoWidth: footerLogoMedia.width,
    footerLogoHeight: footerLogoMedia.height,
    taglineLines: tagline.match(/[^.!?।]+[.!?।]+|[^.!?।]+$/g)?.map(line => line.trim()).filter(Boolean) || [],
  };
});
const footer = computed(() => ({
  about: settings.value.footer?.about ?? 'Ignite Global Foundation works alongside communities to create lasting change through education, health, livelihoods, and humanitarian action.',
  newsletterTitle: settings.value.footer?.newsletter_title ?? '',
  newsletterBody: settings.value.footer?.newsletter_body ?? '',
  copyright: settings.value.footer?.copyright ?? `© ${new Date().getFullYear()} Ignite Global Foundation. All rights reserved.`,
  navigationLabel: settings.value.footer?.navigation_label || 'Footer navigation',
  socialProfilesLabel: settings.value.header?.social_profiles_label || 'Social profiles',
}));
const shared = computed(() => settings.value.shared_blocks || {});
const newsletterEmail = ref('');
const newsletterConsent = ref(false);
const newsletterBusy = ref(false);
const newsletterMessage = ref('');
const newsletterFeedbackType = ref('');
const contact = computed(() => ({
  email: settings.value.contact?.email ?? 'info@ignite.org.bd',
  phone_primary: settings.value.contact?.phone_primary ?? '+8801972016221',
  phone_secondary: settings.value.contact?.phone_secondary ?? '',
  address: settings.value.contact?.address ?? 'Madrasah Road, House-847, Level (A-1), East Kazi Para, Mirpur, Dhaka-1216',
  footer_address_label: settings.value.contact?.footer_address_label ?? 'Address',
  footer_phone_label: settings.value.contact?.footer_phone_label ?? 'Cell',
  footer_secondary_phone_label: settings.value.contact?.footer_secondary_phone_label ?? 'Alternate cell',
  footer_email_label: settings.value.contact?.footer_email_label ?? 'Email',
}));
const phoneHref = computed(() => contact.value.phone_primary.replace(/[^+\d]/g, ''));
const phoneSecondaryHref = computed(() => contact.value.phone_secondary.replace(/[^+\d]/g, ''));
const social = computed(() => settings.value.social || {});
const hasSocialProfiles = computed(() => ['facebook', 'instagram', 'linkedin', 'youtube'].some(key => social.value[key]));
const LEGAL_STATUS_MENU_UUID = '7f030000-0000-4000-8000-000000000300';
const legalStatus = computed(() => {
  const values = settings.value.legal_status || {};
  const defaults = [
    ['', '', 'MRA'],
    ['NGO Affairs Bureau Registration No.', '3461', 'NGOAB'],
    ['Joint Stock & Firms Registration No.', 'S-13907/2022', 'RJSC'],
  ];
  const items = defaults.map(([defaultLabel, defaultRegistration, badge], index) => {
    const number = index + 1;
    const label = String(values[`authority_${number}_label`] ?? defaultLabel).trim();
    const registration = String(values[`authority_${number}_registration`] ?? defaultRegistration).trim();
    const logo = safeImageSrc(values[`authority_${number}_logo`]);
    return { key: number, label, registration, logo, badge };
  }).filter(item => item.label || item.registration || item.logo);

  return {
    enabled: values.enabled !== false && values.enabled !== 0 && values.enabled !== '0',
    heading: String(values.heading ?? 'Legal Status').trim(),
    items,
  };
});
const footerColumns = computed(() => {
  const menus = inertiaPage.props.appFooterMenus || [];
  return menus.map(item => ({
    uuid: item.uuid,
    title: item.name,
    links: item.children?.length ? item.children : [item],
    isLegalStatus: legalStatus.value.enabled && item.uuid === LEGAL_STATUS_MENU_UUID,
  }));
});
const navigationColumns = computed(() => footerColumns.value.filter(column => !column.isLegalStatus));
const legalStatusColumn = computed(() => footerColumns.value.find(column => column.isLegalStatus) || null);
const compactFooter = ref(false);
const expandedFooterColumns = ref(new Set());
let footerBreakpoint = null;
function syncFooterBreakpoint(event) {
  compactFooter.value = Boolean(event.matches);
}
onMounted(() => {
  footerBreakpoint = window.matchMedia?.('(max-width: 860px)') || null;
  if (!footerBreakpoint) return;
  syncFooterBreakpoint(footerBreakpoint);
  if (footerBreakpoint.addEventListener) footerBreakpoint.addEventListener('change', syncFooterBreakpoint);
  else footerBreakpoint.addListener?.(syncFooterBreakpoint);
});
onBeforeUnmount(() => {
  if (footerBreakpoint?.removeEventListener) footerBreakpoint.removeEventListener('change', syncFooterBreakpoint);
  else footerBreakpoint?.removeListener?.(syncFooterBreakpoint);
});
function footerColumnKey(column, index) {
  return String(column.uuid || `${column.title}-${index}`);
}
function footerColumnPanelId(column, index) {
  return `footer-column-${footerColumnKey(column, index).replace(/[^a-zA-Z0-9_-]/g, '-')}`;
}
function isFooterColumnOpen(column, index) {
  return !compactFooter.value || expandedFooterColumns.value.has(footerColumnKey(column, index));
}
function toggleFooterColumn(column, index) {
  if (!compactFooter.value) return;
  const key = footerColumnKey(column, index);
  const next = new Set(expandedFooterColumns.value);
  if (next.has(key)) next.delete(key); else next.add(key);
  expandedFooterColumns.value = next;
}
function menuHref(item) {
  if (item.href) return item.href;
  if (item.link === 'custom') return safeCustomHref(item.slug);
  try { if (item.link && window.route().has(item.link)) return window.route(item.link, item.slug ? [item.slug] : []); } catch { /* safe fallback below */ }
  return item.slug ? `/page/${item.slug}` : '#';
}
function safeCustomHref(value) {
  const href = String(value || '').trim();
  if (/^\/(?!\/)/.test(href) || /^(https?:|mailto:|tel:)/i.test(href)) return href;
  return '#';
}
function safeImageSrc(value) {
  const src = String(value || '').trim();
  return /^\/(?!\/)/.test(src) || /^https?:\/\//i.test(src) ? src : '';
}
function subscribe() {
  if (!newsletterConsent.value) return;
  newsletterBusy.value = true;
  newsletterMessage.value = '';
  newsletterFeedbackType.value = '';
  router.post(route('frontend.subscribe'), { email: newsletterEmail.value, consent: newsletterConsent.value }, {
    preserveScroll: true,
    onSuccess: () => {
      newsletterEmail.value = '';
      newsletterConsent.value = false;
      newsletterMessage.value = shared.value.newsletter_success_message;
      newsletterFeedbackType.value = 'success';
    },
    onError: errors => {
      newsletterMessage.value = errors.email || shared.value.newsletter_error_message;
      newsletterFeedbackType.value = 'error';
    },
    onFinish: () => { newsletterBusy.value = false; },
  });
}
</script>

<style scoped>
.site-footer { --footer-primary:var(--igf-primary,#ff7500); --footer-accent:var(--igf-accent,#9c4500); padding:clamp(42px,5vw,58px) clamp(20px,4vw,48px) 24px; background:#202124; color:#d2d3d4; font-family:'Hanken Grotesk',Arial,sans-serif; }
.site-footer__shell { width:min(100%,1440px); margin:0 auto; }
.footer-newsletter { display:grid; grid-template-columns:minmax(200px,.75fr) minmax(360px,1.25fr); align-items:center; gap:clamp(24px,4vw,52px); margin-bottom:32px; padding-bottom:26px; border-bottom:1px solid rgba(255,255,255,.12); }
.footer-newsletter h2 { margin:0 0 6px; color:#fff; font:650 21px/1.2 'Literata',Georgia,serif; }
.footer-newsletter h2::after,.footer-link-group h2::after,.footer-legal-status h2::after { display:none!important; content:none!important; }
.footer-newsletter p { margin:0; color:#b9bbbd; font-size:13px; line-height:1.5; }
.footer-newsletter__row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:8px; }
.footer-newsletter__row input { min-width:0; min-height:44px; padding:10px 12px; border:1px solid rgba(255,255,255,.24); border-radius:8px; background:#fff; color:#202124; }
.footer-newsletter__row button { min-height:44px; padding:10px 17px; border:0; border-radius:8px; background:var(--footer-primary); color:#fff; font-weight:850; cursor:pointer; }
.footer-newsletter__row button:disabled { cursor:wait; opacity:.65; }
.footer-newsletter__consent { display:flex; align-items:flex-start; gap:7px; margin-top:8px; color:#b9bbbd; font-size:10px; line-height:1.45; }
.footer-newsletter__consent input { flex:0 0 auto; margin-top:2px; accent-color:var(--footer-primary); }
.footer-newsletter__consent a { color:#ffc08c; }
.footer-newsletter__message { margin:8px 0 0!important; padding:8px 10px; border:1px solid transparent; border-radius:7px; }
.footer-newsletter__message.is-success { border-color:rgba(119,221,151,.45); background:rgba(38,129,68,.2); color:#c9f7d7!important; }
.footer-newsletter__message.is-error { border-color:rgba(255,138,128,.55); background:rgba(176,51,40,.22); color:#ffd8d4!important; }
.footer-newsletter__row input[aria-invalid="true"] { border-color:#ff8a80; outline:3px solid rgba(255,138,128,.22); }
.footer-body { display:grid; grid-template-columns:minmax(220px,.9fr) minmax(0,2.1fr); align-items:start; gap:clamp(28px,4vw,48px); }
.footer-body.has-legal-status { grid-template-columns:minmax(250px,.85fr) minmax(0,3.15fr); gap:clamp(36px,4vw,64px); }
.footer-content { min-width:0; }
.footer-content.has-legal-status { display:grid; grid-template-columns:minmax(0,2.1fr) minmax(300px,1fr); align-items:start; gap:clamp(30px,3vw,48px); }
.footer-brand { min-width:0; }
.footer-brand__name { display:inline-flex; align-items:center; color:#fff; text-decoration:none; }
.footer-brand__name img { display:block; width:auto; max-width:min(170px,100%); max-height:58px; object-fit:contain; object-position:left center; }
.footer-brand__tagline { display:block; max-width:360px; margin-top:10px; color:#ffb174; font-size:12px; font-weight:800; line-height:1.4; }
.footer-brand__tagline span { display:block; }
.footer-contact-block { max-width:390px; margin:16px 0; }
.footer-contact { display:grid; gap:8px; margin:0; font-style:normal; }
.footer-contact a,.footer-contact span { display:flex; min-width:0; align-items:flex-start; gap:8px; color:#c9cbcc; font-size:12px; line-height:1.45; text-decoration:none; overflow-wrap:anywhere; }
.footer-contact__address { width:100%; }
.footer-contact i { width:14px; flex:0 0 14px; padding-top:3px; color:var(--footer-primary); text-align:center; }
.footer-contact .footer-contact__copy { display:block; color:inherit; }
.footer-contact__copy strong { color:#fff; font-weight:700; }
.footer-social { display:flex; flex-wrap:wrap; gap:8px; }
.footer-social a { display:grid; width:36px; height:36px; place-content:center; border-radius:50%; background:rgba(255,255,255,.08); color:#e0e1e2; text-decoration:none; }
.footer-social a:hover { background:var(--footer-primary); color:#fff; }
.footer-navigation { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,128px),1fr)); gap:26px 22px; min-width:0; }
.footer-content.has-legal-status>.footer-navigation { grid-template-columns:repeat(var(--footer-nav-columns),minmax(0,1fr)); gap:clamp(22px,2.5vw,36px); }
.footer-link-group { min-width:0; }
.footer-link-group__heading,.footer-legal-status h2 { margin:0 0 12px; color:#ff9b48; font:800 12px/1.25 'Hanken Grotesk',Arial,sans-serif; letter-spacing:.07em; text-transform:uppercase; }
.footer-link-group__links { display:flex; flex-direction:column; align-items:flex-start; gap:9px; }
.footer-links a { color:#c5c7c8; font-size:13px; line-height:1.4; text-decoration:none; overflow-wrap:anywhere; }
.footer-links a:hover { color:#ffb174; }
.footer-link-group__toggle { width:100%; min-height:44px; align-items:center; justify-content:space-between; gap:12px; padding:0; border:0; background:transparent; color:inherit; font:inherit; letter-spacing:inherit; text-align:left; text-transform:inherit; cursor:pointer; }
.footer-link-group__toggle i { transition:transform .18s ease; }
.footer-link-group__toggle[aria-expanded="true"] i { transform:rotate(180deg); }
.footer-legal-status { min-width:0; }
.footer-legal-status__list { display:grid; gap:12px; width:100%; margin:0; padding:0; list-style:none; }
.footer-legal-status__item { display:grid; grid-template-columns:34px minmax(0,1fr); align-items:center; gap:9px; min-width:0; }
.footer-legal-status__logo,.footer-legal-status__badge { display:grid; width:34px; height:34px; place-items:center; border:1px solid rgba(255,155,72,.36); border-radius:9px; background:#fff; }
.footer-legal-status__logo { object-fit:contain; padding:4px; }
.footer-legal-status__badge { background:rgba(255,117,0,.12); color:#ffc08c; font-size:8px; font-weight:900; letter-spacing:.03em; text-align:center; }
.footer-legal-status__copy { display:grid; gap:2px; min-width:0; }
.footer-legal-status__copy strong { color:#e9eaeb; font-size:11px; font-weight:800; letter-spacing:.015em; line-height:1.3; text-transform:uppercase; overflow-wrap:anywhere; }
.footer-legal-status__copy small { color:#ffc08c; font-size:12px; font-weight:700; font-variant-numeric:tabular-nums; line-height:1.3; overflow-wrap:anywhere; }
.footer-bottom { display:flex; align-items:center; justify-content:center; margin-top:28px; padding-top:19px; border-top:1px solid rgba(255,255,255,.12); color:#b5b7b9; font-size:11px; line-height:1.45; text-align:center; }
.footer-bottom p { margin:0; color:inherit; font:inherit; }
.site-footer a:focus-visible,.site-footer button:focus-visible,.site-footer input:focus-visible { outline:3px solid rgba(255,155,72,.55); outline-offset:3px; }
@media(max-width:1199px) {
  .footer-body.has-legal-status { grid-template-columns:minmax(240px,.82fr) minmax(0,1.8fr); gap:clamp(22px,3vw,32px); }
  .footer-content.has-legal-status { grid-template-columns:repeat(2,minmax(0,1fr)); gap:28px 24px; }
  .footer-content.has-legal-status>.footer-navigation { display:contents; }
  .footer-navigation { grid-template-columns:repeat(auto-fit,minmax(min(100%,118px),1fr)); gap:24px 18px; }
}
@media(max-width:860px) {
  .site-footer { padding:36px 20px 20px; }
  .footer-newsletter { grid-template-columns:1fr; gap:14px; margin-bottom:26px; padding-bottom:24px; }
  .footer-body,.footer-body.has-legal-status { grid-template-columns:1fr; gap:24px; }
  .footer-brand { order:1; }
  .footer-content,.footer-content.has-legal-status { display:flex; flex-direction:column; align-items:stretch; gap:24px; order:2; }
  .footer-legal-status { order:1; }
  .footer-navigation,.footer-content.has-legal-status>.footer-navigation { display:grid; grid-template-columns:1fr; gap:0; order:2; border-top:1px solid rgba(255,255,255,.12); }
  .footer-link-group { border-bottom:1px solid rgba(255,255,255,.12); }
  .footer-link-group__heading { margin:0; }
  .footer-link-group__heading>span { display:none; }
  .footer-link-group__toggle { display:flex; }
  .footer-link-group__links { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px 18px; padding:2px 0 14px; }
  .footer-social a { width:44px; height:44px; }
  .footer-bottom { margin-top:24px; }
}
@media(min-width:701px) and (max-width:860px) {
  .footer-legal-status__list { grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
}
@media(min-width:521px) and (max-width:700px) {
  .footer-legal-status__list { grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
}
@media(max-width:520px) {
  .footer-newsletter__row { grid-template-columns:1fr; }
  .footer-newsletter__row button { width:100%; }
}
</style>
