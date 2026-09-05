<template>
  <div>
    <aside v-if="announcement.enabled && announcement.text" class="announcement" :aria-label="announcement.label">
      <a v-if="announcement.url" :href="announcement.url">{{ announcement.text }}</a>
      <span v-else>{{ announcement.text }}</span>
    </aside>
    <div class="utility-bar">
      <div class="utility-bar__inner">
        <div class="utility-bar__contact">
          <a v-if="contact.phone_primary" :href="`tel:${phoneHref}`"><i class="fa-solid fa-phone" aria-hidden="true" /> {{ contact.phone_primary }}</a>
          <a v-if="contact.phone_secondary" :href="`tel:${phoneSecondaryHref}`"><i class="fa-solid fa-phone" aria-hidden="true" /> {{ contact.phone_secondary }}</a>
          <a v-if="contact.email" :href="`mailto:${contact.email}`"><i class="fa-regular fa-envelope" aria-hidden="true" /> {{ contact.email }}</a>
        </div>
        <div class="utility-bar__links">
          <div class="utility-social" :aria-label="header.socialProfilesLabel">
            <a v-if="social.facebook" :href="social.facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true" /></a>
            <a v-if="social.instagram" :href="social.instagram" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true" /></a>
            <a v-if="social.linkedin" :href="social.linkedin" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true" /></a>
            <a v-if="social.youtube" :href="social.youtube" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><i class="fa-brands fa-youtube" aria-hidden="true" /></a>
          </div>
          <span v-if="hasSocialProfiles && utilityNavigation.length" aria-hidden="true">•</span>
          <nav v-if="utilityNavigation.length" class="utility-navigation" :aria-label="header.utilityNavigationLabel">
            <ManagedMenuTree :items="utilityNavigation" :max-depth="3" focus-branch-labels />
          </nav>
          <template v-if="showLanguage">
            <span v-if="hasSocialProfiles || utilityNavigation.length" aria-hidden="true">•</span>
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
import ManagedMenuTree from '../Shared/components/ManagedMenuTree.vue';
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
  phone_primary: settings.value.contact?.phone_primary ?? '+8801972016221',
  phone_secondary: settings.value.contact?.phone_secondary ?? '',
  email: settings.value.contact?.email ?? 'info@ignite.org.bd',
}));
const social = computed(() => settings.value.social || {});
const hasSocialProfiles = computed(() => ['facebook', 'instagram', 'linkedin', 'youtube'].some(key => social.value[key]));
const utilityNavigation = computed(() => (
  Array.isArray(inertiaPage.props.appUtilityMenus) ? inertiaPage.props.appUtilityMenus : []
));
const header = computed(() => ({
  socialProfilesLabel: settings.value.header?.social_profiles_label || 'Social profiles',
  utilityNavigationLabel: settings.value.header?.utility_navigation_label || 'Utility navigation',
}));
const phoneHref = computed(() => String(contact.value.phone_primary).replace(/[^+\d]/g, ''));
const phoneSecondaryHref = computed(() => String(contact.value.phone_secondary).replace(/[^+\d]/g, ''));
</script>

<style scoped>
.announcement { padding:8px 20px; background:var(--igf-header-announcement-bg,var(--igf-accent,#9c4500)); color:var(--igf-header-announcement-text,var(--igf-on-accent,#fff)); text-align:center; font:700 13px/1.4 var(--igf-font-body,'Hanken Grotesk',Arial,sans-serif); }
.announcement a { color:inherit; }
.utility-bar { border-bottom:1px solid var(--igf-header-border,#e5e0dc); background:var(--igf-header-utility-bg,#f0f1f2); color:var(--igf-header-text,#56575b); font:600 12px/1.2 var(--igf-font-body,'Hanken Grotesk',Arial,sans-serif); }
.utility-bar__inner { display:flex; width:min(calc(100% - 40px),var(--igf-content-width,1240px)); min-height:var(--igf-header-utility-height,38px); align-items:center; justify-content:space-between; gap:24px; margin:0 auto; }
.utility-bar__contact,.utility-bar__links { display:flex; align-items:center; gap:18px; }
.utility-bar__links { gap:9px; }
.utility-social { display:flex; align-items:center; gap:5px; }
.utility-social a { display:grid; width:27px; height:27px; place-content:center; border-radius:50%; }
.utility-social a:hover { background:var(--igf-header-nav-bg,#fff); }
.utility-bar a { display:inline-flex; align-items:center; gap:7px; color:inherit; text-decoration:none; }
.utility-bar a:hover { color:var(--igf-accent,#9c4500); }
.utility-navigation { align-self:stretch; }
.utility-navigation :deep(.managed-menu-tree) { display:flex; align-items:stretch; gap:3px; height:100%; margin:0; padding:0; list-style:none; }
.utility-navigation :deep(.managed-menu-tree__item) { position:relative; display:flex; align-items:center; min-width:0; }
.utility-navigation :deep(.managed-menu-tree__link),.utility-navigation :deep(.managed-menu-tree__label) { display:flex; min-height:27px; align-items:center; padding:4px 7px; border-radius:5px; color:inherit; white-space:nowrap; }
.utility-navigation :deep(.managed-menu-tree__label[tabindex]) { cursor:default; }
.utility-navigation :deep(.managed-menu-tree[data-menu-depth="2"]),.utility-navigation :deep(.managed-menu-tree[data-menu-depth="3"]) { position:absolute; z-index:80; top:calc(100% - 1px); left:0; display:none; width:max-content; min-width:180px; height:auto; padding:7px; border:1px solid var(--igf-header-border,#e5e0dc); border-radius:8px; background:var(--igf-header-nav-bg,#fff); box-shadow:0 12px 28px rgba(25,28,29,.14); }
.utility-navigation :deep(.managed-menu-tree[data-menu-depth="2"] .managed-menu-tree__item),.utility-navigation :deep(.managed-menu-tree[data-menu-depth="3"] .managed-menu-tree__item) { display:block; }
.utility-navigation :deep(.managed-menu-tree[data-menu-depth="2"] .managed-menu-tree__link),.utility-navigation :deep(.managed-menu-tree[data-menu-depth="2"] .managed-menu-tree__label),.utility-navigation :deep(.managed-menu-tree[data-menu-depth="3"] .managed-menu-tree__link),.utility-navigation :deep(.managed-menu-tree[data-menu-depth="3"] .managed-menu-tree__label) { width:100%; white-space:normal; }
.utility-navigation :deep(.managed-menu-tree[data-menu-depth="3"]) { top:-7px; left:calc(100% + 7px); }
.utility-navigation :deep(.managed-menu-tree__item:hover > .managed-menu-tree),.utility-navigation :deep(.managed-menu-tree__item:focus-within > .managed-menu-tree) { display:grid; }
.utility-navigation :deep(.managed-menu-tree__link:focus-visible),.utility-navigation :deep(.managed-menu-tree__label:focus-visible) { outline:3px solid color-mix(in srgb,var(--igf-primary,#ff7500) 24%,transparent); outline-offset:1px; }
@media(max-width:767px) { .utility-bar { display:none; } }
</style>
