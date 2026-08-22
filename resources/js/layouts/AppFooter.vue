<template>
  <footer class="site-footer">
    <div class="site-footer__grid">
      <div class="footer-brand">
        <a href="/" class="footer-brand__name"><img :src="branding.footerLogo" :alt="branding.footerLogoAlt" :width="branding.footerLogoWidth" :height="branding.footerLogoHeight" decoding="async"></a>
        <small v-if="branding.tagline" class="footer-brand__tagline">{{ branding.tagline }}</small>
        <p>{{ footer.about }}</p>
        <div class="footer-contact">
          <a :href="`mailto:${contact.email}`"><i class="fa-regular fa-envelope" aria-hidden="true" /> {{ contact.email }}</a>
          <a :href="`tel:${phoneHref}`"><i class="fa-solid fa-phone" aria-hidden="true" /> {{ contact.phone_primary }}</a>
          <a v-if="contact.phone_secondary" :href="`tel:${phoneSecondaryHref}`"><i class="fa-solid fa-phone" aria-hidden="true" /> {{ contact.phone_secondary }}</a>
          <span><i class="fa-solid fa-location-dot" aria-hidden="true" /> {{ contact.address }}</span>
        </div>
        <div class="footer-social" :aria-label="footer.socialProfilesLabel">
          <a v-if="social.facebook" :href="social.facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true" /></a>
          <a v-if="social.instagram" :href="social.instagram" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true" /></a>
          <a v-if="social.linkedin" :href="social.linkedin" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true" /></a>
          <a v-if="social.tiktok" :href="social.tiktok" target="_blank" rel="noopener noreferrer" aria-label="TikTok"><i class="fa-brands fa-tiktok" aria-hidden="true" /></a>
          <a v-if="social.youtube" :href="social.youtube" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><i class="fa-brands fa-youtube" aria-hidden="true" /></a>
        </div>
      </div>

      <div class="footer-main">
        <section v-if="footer.newsletterTitle || footer.newsletterBody" class="footer-newsletter">
          <div><h2>{{ footer.newsletterTitle }}</h2><p>{{ footer.newsletterBody }}</p></div>
          <form @submit.prevent="subscribe">
            <label class="sr-only" for="footer-newsletter-email">{{ shared.newsletter_email_label }}</label>
            <div class="footer-newsletter__row">
              <input id="footer-newsletter-email" v-model="newsletterEmail" name="email" type="email" autocomplete="email" :placeholder="shared.newsletter_email_placeholder" required>
              <button type="submit" :disabled="newsletterBusy">{{ newsletterBusy ? shared.newsletter_subscribing_label : shared.newsletter_subscribe_label }}</button>
            </div>
            <label class="footer-newsletter__consent"><input v-model="newsletterConsent" type="checkbox" required> <span>{{ shared.newsletter_consent_prefix }} <a :href="shared.newsletter_privacy_url">{{ shared.newsletter_privacy_label }}</a>.</span></label>
            <p v-if="newsletterMessage" class="footer-newsletter__message" role="status">{{ newsletterMessage }}</p>
          </form>
        </section>

        <div class="footer-links">
          <section v-for="column in footerColumns" :key="column.title">
            <h2>{{ column.title }}</h2>
            <a v-for="item in column.links" :key="item.name" :href="menuHref(item)">{{ item.name }}</a>
          </section>
        </div>
      </div>

      <div class="footer-bottom">
        <p>{{ footer.copyright }}</p>
        <span><i class="fa-solid fa-circle-check" aria-hidden="true" /> {{ footer.trustBadge }}</span>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed, ref } from 'vue';
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
  return {
    siteName,
    footerLogo,
    footerLogoAlt: values.footer_logo_alt || values.logo_alt || siteName,
    footerLogoWidth: footerLogoMedia.width,
    footerLogoHeight: footerLogoMedia.height,
    tagline: values.tagline || '',
  };
});
const footer = computed(() => ({
  about: settings.value.footer?.about || 'Ignite Global Foundation works alongside communities to create lasting change through education, health, livelihoods, and humanitarian action.',
  newsletterTitle: settings.value.footer?.newsletter_title || '',
  newsletterBody: settings.value.footer?.newsletter_body || '',
  copyright: settings.value.footer?.copyright || `© ${new Date().getFullYear()} Ignite Global Foundation. All rights reserved.`,
  trustBadge: settings.value.footer?.trust_badge || 'Community-led nonprofit in Bangladesh',
  socialProfilesLabel: settings.value.header?.social_profiles_label || 'Social profiles',
}));
const shared = computed(() => settings.value.shared_blocks || {});
const newsletterEmail = ref('');
const newsletterConsent = ref(false);
const newsletterBusy = ref(false);
const newsletterMessage = ref('');
const contact = computed(() => ({
  email: settings.value.contact?.email || 'info@ignite.org.bd',
  phone_primary: settings.value.contact?.phone_primary || '+880 1972 016221',
  phone_secondary: settings.value.contact?.phone_secondary || '',
  address: settings.value.contact?.address || 'Mirpur, Dhaka, Bangladesh',
}));
const phoneHref = computed(() => contact.value.phone_primary.replace(/[^+\d]/g, ''));
const phoneSecondaryHref = computed(() => contact.value.phone_secondary.replace(/[^+\d]/g, ''));
const social = computed(() => settings.value.social || {});
const footerColumns = computed(() => {
  const menus = inertiaPage.props.appFooterMenus || [];
  return menus.map(item => ({ title:item.name, links:item.children?.length ? item.children : [item] }));
});
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
function subscribe() {
  if (!newsletterConsent.value) return;
  newsletterBusy.value = true;
  newsletterMessage.value = '';
  router.post(route('frontend.subscribe'), { email: newsletterEmail.value }, {
    preserveScroll: true,
    onSuccess: () => {
      newsletterEmail.value = '';
      newsletterConsent.value = false;
      newsletterMessage.value = shared.value.newsletter_success_message;
    },
    onError: errors => { newsletterMessage.value = errors.email || shared.value.newsletter_error_message; },
    onFinish: () => { newsletterBusy.value = false; },
  });
}
</script>

<style scoped>
.site-footer { padding:88px clamp(20px,5vw,48px) 30px; background:#202124; color:#d2d3d4; font-family:'Hanken Grotesk',Arial,sans-serif; }
.site-footer__grid { display:grid; width:min(100%,1240px); grid-template-columns:5fr 7fr; gap:70px; margin:0 auto; }
.footer-brand__name { display:inline-flex; align-items:center; gap:10px; color:#fff; font:650 24px/1.2 'Literata',Georgia,serif; text-decoration:none; }
.footer-brand__name img { display:block; width:auto; max-width:min(230px,100%); max-height:76px; object-fit:contain; object-position:left center; }
.footer-brand__tagline { display:block; max-width:430px; margin-top:12px; color:#ffb174; font-size:13px; font-weight:800; letter-spacing:.02em; }
.footer-brand>p { max-width:430px; margin:24px 0; color:#b9bbbd; font-size:15px; line-height:1.7; }
.footer-contact { display:grid; gap:10px; max-width:430px; margin-bottom:24px; }
.footer-contact a,.footer-contact span { display:flex; align-items:flex-start; gap:9px; color:#b9bbbd; font-size:13px; line-height:1.5; text-decoration:none; }
.footer-contact i { width:16px; padding-top:3px; color:#ff9b48; text-align:center; }
.footer-social { display:flex; gap:10px; }
.footer-social a { display:grid; width:40px; height:40px; place-content:center; border-radius:50%; background:rgba(255,255,255,.08); color:#d2d3d4; text-decoration:none; }
.footer-social a:hover { background:#ff7500; color:#fff; }
.footer-main { display:grid; align-content:start; gap:34px; }
.footer-newsletter { display:grid; grid-template-columns:minmax(180px,.8fr) minmax(280px,1.2fr); gap:24px; padding-bottom:30px; border-bottom:1px solid rgba(255,255,255,.12); }
.footer-newsletter h2 { margin:0 0 8px; color:#fff; font:650 22px/1.2 'Literata',Georgia,serif; }
.footer-newsletter h2::after { display:none!important; content:none!important; }
.footer-newsletter p { margin:0; color:#b9bbbd; font-size:13px; line-height:1.5; }
.footer-newsletter__row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:8px; }
.footer-newsletter__row input { min-width:0; padding:11px 12px; border:1px solid rgba(255,255,255,.2); border-radius:7px; background:#fff; color:#202124; }
.footer-newsletter__row button { padding:11px 16px; border:0; border-radius:7px; background:#ff7500; color:#fff; font-weight:850; cursor:pointer; }
.footer-newsletter__row button:disabled { cursor:wait; opacity:.65; }
.footer-newsletter__consent { display:flex; align-items:flex-start; gap:7px; margin-top:9px; color:#aeb0b2; font-size:10px; line-height:1.45; }
.footer-newsletter__consent input { flex:0 0 auto; margin-top:2px; accent-color:#ff7500; }
.footer-newsletter__consent a { color:#ffb174; }
.footer-newsletter__message { margin-top:8px!important; color:#fff!important; }
.footer-links { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:28px; }
.footer-links h2 { display:block; margin:0 0 22px; color:#ff9b48; font:800 12px/1.2 'Hanken Grotesk',Arial,sans-serif; letter-spacing:.07em; text-transform:uppercase; }
.footer-links h2::after { display:none!important; content:none!important; }
.footer-links section { display:flex; flex-direction:column; align-items:flex-start; gap:13px; }
.footer-links a { color:#b9bbbd; font-size:14px; text-decoration:none; }
.footer-links a:hover { color:#ff9b48; }
.footer-bottom { display:flex; grid-column:1/-1; align-items:center; justify-content:space-between; gap:30px; margin-top:12px; padding-top:28px; border-top:1px solid rgba(255,255,255,.12); color:#888b8d; font-size:12px; }
.footer-bottom p { margin:0; color:inherit; font:inherit; }
.footer-bottom span { display:flex; align-items:center; gap:7px; }
@media(max-width:960px) { .site-footer__grid { grid-template-columns:1fr; gap:50px; } .footer-links { grid-template-columns:repeat(2,1fr); } }
@media(max-width:560px) { .site-footer { padding-top:64px; } .footer-newsletter { grid-template-columns:1fr; } .footer-newsletter__row { grid-template-columns:1fr; } .footer-links { grid-template-columns:1fr 1fr; gap:34px 20px; } .footer-bottom { align-items:flex-start; flex-direction:column; } .footer-brand__name { font-size:20px; } }
</style>
