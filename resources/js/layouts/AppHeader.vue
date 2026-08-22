<template>
  <div>
    <aside v-if="announcement.enabled && announcement.text" class="announcement" :aria-label="announcement.label">
      <a v-if="announcement.url" :href="announcement.url">{{ announcement.text }}</a>
      <span v-else>{{ announcement.text }}</span>
    </aside>
    <div class="utility-bar">
      <div class="utility-bar__inner">
        <div class="utility-bar__contact">
          <a :href="`tel:${phoneHref}`"><i class="fa-solid fa-phone" aria-hidden="true" /> {{ contact.phone_primary }}</a>
          <a v-if="contact.phone_secondary" :href="`tel:${phoneSecondaryHref}`"><i class="fa-solid fa-phone" aria-hidden="true" /> {{ contact.phone_secondary }}</a>
          <a :href="`mailto:${contact.email}`"><i class="fa-regular fa-envelope" aria-hidden="true" /> {{ contact.email }}</a>
        </div>
        <div class="utility-bar__links">
          <div class="utility-social" :aria-label="header.socialProfilesLabel">
            <a v-if="social.facebook" :href="social.facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true" /></a>
            <a v-if="social.instagram" :href="social.instagram" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true" /></a>
            <a v-if="social.linkedin" :href="social.linkedin" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true" /></a>
            <a v-if="social.tiktok" :href="social.tiktok" target="_blank" rel="noopener noreferrer" aria-label="TikTok"><i class="fa-brands fa-tiktok" aria-hidden="true" /></a>
            <a v-if="social.youtube" :href="social.youtube" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><i class="fa-brands fa-youtube" aria-hidden="true" /></a>
          </div>
          <span aria-hidden="true">•</span>
          <a :href="header.annualReportsUrl">{{ header.annualReportsLabel }}</a>
          <span aria-hidden="true">•</span>
          <a :href="header.contactUrl">{{ header.contactLabel }}</a>
          <template v-if="showLanguage">
            <span aria-hidden="true">•</span>
            <template v-for="(link, index) in localeLinks" :key="link.locale">
              <span v-if="index > 0" aria-hidden="true">/</span>
              <a :href="link.url" :hreflang="link.locale" :lang="link.locale">{{ languageLabel(link.locale) }}</a>
            </template>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { usePublicLocaleSwitcher } from '../Shared/composables/publicLocaleSwitcher';

defineOptions({ name: 'AppHeader' });
const inertiaPage = usePage();
const {
  enabled: showLanguage,
  languageLabel,
  links: localeLinks,
} = usePublicLocaleSwitcher();
const settings = computed(() => inertiaPage.props.siteSettings || {});
const announcement = computed(() => ({
  enabled: Boolean(settings.value.header?.announcement_enabled),
  label: settings.value.header?.announcement_label || 'Announcement',
  text: settings.value.header?.announcement_text || '',
  url: settings.value.header?.announcement_url || '',
}));
const contact = computed(() => ({
  phone_primary: settings.value.contact?.phone_primary || '+880 1972 016221',
  phone_secondary: settings.value.contact?.phone_secondary || '',
  email: settings.value.contact?.email || 'info@ignite.org.bd',
}));
const social = computed(() => settings.value.social || {});
const header = computed(() => ({
  annualReportsLabel: settings.value.header?.annual_reports_label || 'Annual reports',
  annualReportsUrl: settings.value.header?.annual_reports_url || '/annual-report',
  contactLabel: settings.value.header?.contact_label || 'Contact',
  contactUrl: settings.value.header?.contact_url || '/contact-us',
  socialProfilesLabel: settings.value.header?.social_profiles_label || 'Social profiles',
}));
const phoneHref = computed(() => contact.value.phone_primary.replace(/[^+\d]/g, ''));
const phoneSecondaryHref = computed(() => contact.value.phone_secondary.replace(/[^+\d]/g, ''));
</script>

<style scoped>
.announcement { padding:8px 20px; background:#9c4500; color:#fff; text-align:center; font:700 13px/1.4 'Hanken Grotesk',Arial,sans-serif; }
.announcement a { color:inherit; }
.utility-bar { border-bottom:1px solid #e5e0dc; background:#f0f1f2; color:#56575b; font:600 12px/1.2 'Hanken Grotesk',Arial,sans-serif; }
.utility-bar__inner { display:flex; width:min(calc(100% - 40px),1240px); min-height:38px; align-items:center; justify-content:space-between; gap:24px; margin:0 auto; }
.utility-bar__contact,.utility-bar__links { display:flex; align-items:center; gap:18px; }
.utility-bar__links { gap:9px; }
.utility-social { display:flex; align-items:center; gap:5px; }
.utility-social a { display:grid; width:27px; height:27px; place-content:center; border-radius:50%; }
.utility-social a:hover { background:#fff; }
.utility-bar a { display:inline-flex; align-items:center; gap:7px; color:inherit; text-decoration:none; }
.utility-bar a:hover { color:#9c4500; }
@media(max-width:767px) { .utility-bar { display:none; } }
</style>
