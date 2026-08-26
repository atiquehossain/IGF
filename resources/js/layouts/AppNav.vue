<template>
  <header ref="navRoot" class="site-nav">
    <div class="site-nav__inner">
      <Link :href="route('frontend.home')" class="site-brand" :aria-label="branding.homeLabel">
        <img class="site-brand__logo" :src="branding.logo" :alt="branding.logoAlt" width="100" height="80">
      </Link>

      <nav class="desktop-nav" :aria-label="header.primaryNavigationLabel">
        <div
          v-for="(item, index) in navigation"
          :key="item.name"
          class="desktop-nav__item"
          :class="{ 'is-open': openDesktop === index }"
          @keydown.esc.stop="closeMenus"
        >
          <template v-if="item.children?.length">
            <button
              v-if="isPlaceholderMenu(item)"
              class="desktop-nav__trigger"
              :class="{ active: isActive(item) }"
              type="button"
              :aria-expanded="openDesktop === index"
              :aria-controls="`desktop-submenu-${index}`"
              @click="toggleDesktop(index)"
            >
              {{ item.name }}
              <i class="fa-solid fa-chevron-down" aria-hidden="true" />
            </button>
            <div v-else class="desktop-nav__parent-row">
              <a :href="menuHref(item)" :class="{ active: isActive(item) }" :aria-current="isOwnActive(item) ? 'page' : undefined">{{ item.name }}</a>
              <button
                class="desktop-nav__toggle"
                type="button"
                :aria-label="submenuLabel(item)"
                :aria-expanded="openDesktop === index"
                :aria-controls="`desktop-submenu-${index}`"
                @click="toggleDesktop(index)"
              >
                <i class="fa-solid fa-chevron-down" aria-hidden="true" />
              </button>
            </div>
            <div :id="`desktop-submenu-${index}`" class="desktop-nav__dropdown">
              <a
                v-for="child in item.children"
                :key="child.name"
                :href="menuHref(child)"
                :class="{ active: isActive(child) }"
                :aria-current="isActive(child) ? 'page' : undefined"
                @click="closeMenus"
              >
                <strong>{{ child.name }}</strong>
                <small v-if="menuDescription(child)">{{ menuDescription(child) }}</small>
              </a>
            </div>
          </template>
          <a v-else :href="menuHref(item)" :class="{ active: isActive(item) }" :aria-current="isOwnActive(item) ? 'page' : undefined">{{ item.name }}</a>
        </div>
      </nav>

      <div class="site-nav__actions">
        <a class="nav-icon" :href="header.searchUrl" :aria-label="header.searchLabel"><i class="fa-solid fa-magnifying-glass" aria-hidden="true" /></a>
        <a class="sign-in" :href="header.signInUrl">{{ header.signInLabel }}</a>
        <a v-if="hasSponsorAction" class="sponsor-button site-nav__inline-action" :href="header.sponsorUrl">{{ header.sponsorLabel }}</a>
        <a class="donate-button site-nav__inline-action" :href="header.donateUrl"><i class="fa-solid fa-heart" aria-hidden="true" /> {{ header.donateLabel }}</a>
        <button ref="menuButton" class="menu-button" type="button" :aria-expanded="drawer" aria-controls="mobile-navigation" :aria-label="header.toggleNavigationLabel" @click="toggleDrawer" @keydown.esc.stop.prevent="closeMenusAndRestoreFocus">
          <i :class="drawer ? 'fa-solid fa-xmark' : 'fa-solid fa-bars'" aria-hidden="true" />
        </button>
      </div>
    </div>

    <div class="mobile-action-bar">
      <div class="mobile-action-bar__inner">
        <a v-if="hasSponsorAction" class="sponsor-button" :href="header.sponsorUrl">{{ header.sponsorLabel }}</a>
        <a class="donate-button" :class="{ 'mobile-action-bar__single': !hasSponsorAction }" :href="header.donateUrl"><i class="fa-solid fa-heart" aria-hidden="true" /> {{ header.donateLabel }}</a>
      </div>
    </div>

    <nav v-if="drawer" id="mobile-navigation" class="mobile-nav" :aria-label="header.mobileNavigationLabel" @keydown.esc.stop.prevent="closeMenusAndRestoreFocus">
      <div v-for="(item, index) in navigation" :key="`mobile-${item.name}`" class="mobile-nav__group">
        <template v-if="item.children?.length">
          <button
            v-if="isPlaceholderMenu(item)"
            class="mobile-nav__parent"
            type="button"
            :aria-expanded="openMobile === index"
            :aria-controls="`mobile-submenu-${index}`"
            @click="toggleMobile(index)"
          >
            <span>{{ item.name }}</span>
            <i class="fa-solid fa-chevron-down" :class="{ 'is-rotated': openMobile === index }" aria-hidden="true" />
          </button>
          <div v-else class="mobile-nav__parent-row">
            <a :href="menuHref(item)" :aria-current="isOwnActive(item) ? 'page' : undefined" @click="closeMenus">{{ item.name }}</a>
            <button
              type="button"
              :aria-label="submenuLabel(item)"
              :aria-expanded="openMobile === index"
              :aria-controls="`mobile-submenu-${index}`"
              @click="toggleMobile(index)"
            >
              <i class="fa-solid fa-chevron-down" :class="{ 'is-rotated': openMobile === index }" aria-hidden="true" />
            </button>
          </div>
          <div v-show="openMobile === index" :id="`mobile-submenu-${index}`" class="mobile-nav__submenu">
            <a
              v-for="child in item.children"
              :key="`mobile-child-${child.name}`"
              class="mobile-nav__child"
              :class="{ active: isActive(child) }"
              :aria-current="isActive(child) ? 'page' : undefined"
              :href="menuHref(child)"
              @click="closeMenus"
            >
              <strong>{{ child.name }}</strong>
              <small v-if="menuDescription(child)">{{ menuDescription(child) }}</small>
            </a>
          </div>
        </template>
        <a v-else :href="menuHref(item)" :aria-current="isOwnActive(item) ? 'page' : undefined" @click="closeMenus">{{ item.name }}</a>
      </div>
      <a class="mobile-nav__sign-in" :href="header.signInUrl">{{ header.signInLabel }}</a>
    </nav>
  </header>
</template>

<script setup>
import { ref, computed, nextTick, onBeforeUnmount, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { interpolateSetting } from '../Shared/composables/siteSettings';

defineOptions({ name: 'AppNav' });
const drawer = ref(false);
const openDesktop = ref(null);
const openMobile = ref(null);
const navRoot = ref(null);
const menuButton = ref(null);
const inertiaPage = usePage();
const fallbackNavigation = [
  { name:'Home', href:'/' },
  { name:'About Us', href:'#', children:[
    { name:'Who We Are', href:'/about-us' },
    { name:'Awards & Recognition', href:'/category/awards-&-recognition' },
    { name:'Photo Gallery', href:'/gallery' },
    { name:'Annual Reports', href:'/annual-report' },
    { name:'Contact Us', href:'/contact-us' },
  ] },
  { name:'Our Work', href:'#', children:[
    { name:'Program Overview', href:'/category/our-causes' },
    { name:'Inclusive Education', href:'/page/education' },
    { name:'Visit Ignite School', href:'/category/visit-ignite-school' },
    { name:'Youth Development', href:'/page/youth-development' },
    { name:'Disaster Resilience', href:'/page/disaster-response-and-resilience' },
    { name:'Current Projects', href:'/projects/current-project' },
    { name:'Completed Projects', href:'/projects/completed-project' },
  ] },
  { name:'Get Involved', href:'#', children:[
    { name:'Volunteer', href:'/volunteer/register' },
    { name:'Careers', href:'/careers' },
    { name:'Free Workshops', href:'/workshops' },
    { name:'Sponsor a Child', href:'/sponsor-child' },
  ] },
  { name:'News & Stories', href:'#', children:[
    { name:'Stories', href:'/category/stories' },
    { name:'Events & News', href:'/events' },
  ] },
  { name:'Donate', href:'#', children:[
    { name:'Make a Donation', href:'/donate' },
    { name:'Give Zakat', href:'/zakat' },
  ] },
];
const navigation = computed(() => withOpportunityLinks(
  Array.isArray(inertiaPage.props.appMenus) && inertiaPage.props.appMenus.length
    ? inertiaPage.props.appMenus
    : fallbackNavigation
));
const branding = computed(() => {
  const values = inertiaPage.props.siteSettings?.branding || {};
  const siteName = values.site_name || inertiaPage.props.appName || 'Ignite Global Foundation';
  return {
    siteName,
    logo: values.logo || '/image/logo.png',
    logoAlt: values.logo_alt || siteName,
    homeLabel: interpolateSetting(inertiaPage.props.siteSettings?.header?.brand_home_label || '{site} home', { site: siteName }),
  };
});
const header = computed(() => ({
  donateLabel: inertiaPage.props.siteSettings?.header?.donate_label || 'Donate',
  donateUrl: inertiaPage.props.siteSettings?.header?.donate_url || '/donate',
  sponsorLabel: inertiaPage.props.siteSettings?.header?.sponsor_label ?? 'Sponsor a Child',
  sponsorUrl: inertiaPage.props.siteSettings?.header?.sponsor_url ?? '/sponsor-child',
  searchLabel: inertiaPage.props.siteSettings?.header?.search_label || 'Search',
  searchUrl: inertiaPage.props.siteSettings?.header?.search_url || '/search',
  signInLabel: inertiaPage.props.siteSettings?.header?.sign_in_label || 'Sign in',
  signInUrl: inertiaPage.props.siteSettings?.header?.sign_in_url || '/login',
  primaryNavigationLabel: inertiaPage.props.siteSettings?.header?.primary_navigation_label || 'Primary navigation',
  mobileNavigationLabel: inertiaPage.props.siteSettings?.header?.mobile_navigation_label || 'Mobile navigation',
  toggleNavigationLabel: inertiaPage.props.siteSettings?.header?.toggle_navigation_label || 'Toggle navigation',
  openSubmenuLabel: inertiaPage.props.siteSettings?.header?.open_submenu_label || 'Open {item} submenu',
}));
const hasSponsorAction = computed(() => Boolean(
  String(header.value.sponsorLabel || '').trim() && String(header.value.sponsorUrl || '').trim()
));

function menuHref(item) {
  if (item.href) return item.href;
  if (item.link === 'custom') return safeCustomHref(item.slug);
  try {
    if (item.link && window.route().has(item.link)) return window.route(item.link, item.slug ? [item.slug] : []);
  } catch { /* fall through to a safe local URL */ }
  return item.slug ? `/page/${item.slug}` : '#';
}
function withOpportunityLinks(source) {
  let hasCareers = false;
  let hasWorkshops = false;
  const items = source.map(item => ({
    ...normalizeOpportunityItem(item),
    children: Array.isArray(item.children) ? item.children.map(child => {
      if (isCareerDestination(child)) {
        hasCareers = true;
        return normalizeOpportunityItem(child);
      }
      if (isWorkshopDestination(child)) hasWorkshops = true;
      return { ...child };
    }) : [],
  }));

  items.forEach(item => {
    if (isCareerDestination(item)) hasCareers = true;
    if (isWorkshopDestination(item)) hasWorkshops = true;
  });
  if (hasCareers && hasWorkshops) return items;

  const locale = inertiaPage.props.locale === 'bn' ? 'bn' : 'en';
  const additions = [];
  if (!hasCareers) additions.push({ name: locale === 'bn' ? 'চাকরি' : 'Careers', href: '/careers' });
  if (!hasWorkshops) additions.push({ name: locale === 'bn' ? 'বিনামূল্যের কর্মশালা' : 'Free Workshops', href: '/workshops' });
  const parentIndex = items.findIndex(item => item.children.some(child => {
    const href = rawDestination(child);
    return href === '/volunteer/register' || href === '/sponsor-child';
  }));

  if (parentIndex >= 0) {
    items[parentIndex] = { ...items[parentIndex], children: [...items[parentIndex].children, ...additions] };
  } else {
    items.push({
      name: locale === 'bn' ? 'সুযোগ' : 'Opportunities',
      href: '#',
      children: additions,
    });
  }

  return items;
}
function normalizeOpportunityItem(item) {
  return isLegacyCareerDestination(item)
    ? { ...item, href: '/careers', link: 'custom', slug: '/careers' }
    : { ...item };
}
function rawDestination(item) {
  if (item?.href) return String(item.href).replace(/\/$/, '') || '/';
  if (item?.link === 'custom') return String(item.slug || '').replace(/\/$/, '') || '#';
  if (item?.link === 'frontend.jobs.index') return '/careers';
  if (item?.link === 'frontend.workshops.index') return '/workshops';
  if (isLegacyCareerDestination(item)) return '/category/career';
  return '';
}
function isLegacyCareerDestination(item) {
  return (item?.link === 'frontend.category' && String(item?.slug || '').replace(/^\//, '') === 'career')
    || (item?.link === 'custom' && String(item?.slug || '').replace(/\/$/, '') === '/category/career')
    || rawHref(item) === '/category/career';
}
function isCareerDestination(item) {
  return rawDestination(item) === '/careers' || isLegacyCareerDestination(item);
}
function isWorkshopDestination(item) {
  return rawDestination(item) === '/workshops';
}
function rawHref(item) {
  return String(item?.href || '').replace(/\/$/, '') || '/';
}
function safeCustomHref(value) {
  const href = String(value || '').trim();
  if (/^\/(?!\/)/.test(href) || /^(https?:|mailto:|tel:)/i.test(href)) return href;
  return '#';
}
function isPlaceholderMenu(item) {
  return menuHref(item) === '#';
}
function menuDescription(item) {
  return String(item?.description || '').trim();
}
function submenuLabel(item) {
  return interpolateSetting(header.value.openSubmenuLabel, { item: item?.name || '' });
}
function isOwnActive(item) {
  const path = window.location.pathname.replace(/\/$/, '') || '/';
  const ownHref = menuHref(item).replace(window.location.origin, '').replace(/\/$/, '') || '/';
  return ownHref !== '#' && (ownHref === '/' ? path === '/' : path.startsWith(ownHref));
}
function isActive(item) {
  const path = window.location.pathname.replace(/\/$/, '') || '/';
  return isOwnActive(item) || Boolean(item.children?.some(child => {
    const childHref = menuHref(child).replace(window.location.origin, '').replace(/\/$/, '') || '/';
    return childHref !== '#' && (childHref === '/' ? path === '/' : path.startsWith(childHref));
  }));
}
function toggleDesktop(index) {
  openDesktop.value = openDesktop.value === index ? null : index;
}
function toggleMobile(index) {
  openMobile.value = openMobile.value === index ? null : index;
}
function toggleDrawer() {
  drawer.value = !drawer.value;
  if (drawer.value) {
    openMobile.value = navigation.value.findIndex(item => item.children?.some(child => isActive(child)));
    if (openMobile.value < 0) openMobile.value = null;
  } else {
    openMobile.value = null;
  }
}
function closeMenus() {
  openDesktop.value = null;
  openMobile.value = null;
  drawer.value = false;
}
function closeMenusAndRestoreFocus() {
  const wasOpen = drawer.value;
  closeMenus();
  if (wasOpen) nextTick(() => menuButton.value?.focus());
}
function closeFromOutside(event) {
  if (navRoot.value && !navRoot.value.contains(event.target)) closeMenus();
}

onMounted(() => document.addEventListener('click', closeFromOutside));
onBeforeUnmount(() => document.removeEventListener('click', closeFromOutside));
</script>

<style scoped>
.site-nav { position:relative; z-index:50; border-bottom:1px solid #e5e0dc; background:#fff; color:#191c1d; font-family:'Hanken Grotesk',Arial,sans-serif; }
.site-nav__inner { display:flex; width:min(calc(100% - 40px),var(--igf-content-width,1240px)); height:80px; align-items:center; justify-content:space-between; gap:24px; margin:0 auto; }
.site-brand { display:inline-flex; width:calc(var(--igf-brand-font-size,20px) * 5); min-width:calc(var(--igf-brand-font-size,20px) * 5); height:80px; align-items:center; justify-content:center; text-decoration:none; }
.site-brand__logo { display:block; width:100%; height:80px; object-fit:contain; }
.desktop-nav { display:flex; align-self:stretch; align-items:center; justify-content:center; gap:21px; }
.desktop-nav__item { position:relative; display:flex; height:100%; align-items:center; }
.desktop-nav__item>a,.desktop-nav__trigger,.desktop-nav__parent-row>a,.sign-in { position:relative; padding:9px 0; border:0; background:transparent; color:#56575b; font:800 12px/1.2 'Hanken Grotesk',Arial,sans-serif; letter-spacing:.025em; text-decoration:none; text-transform:uppercase; white-space:nowrap; cursor:pointer; }
.desktop-nav__trigger { display:inline-flex; align-items:center; gap:7px; }
.desktop-nav__trigger i,.desktop-nav__toggle i { font-size:9px; transition:transform .18s ease; }
.desktop-nav__item.is-open .desktop-nav__trigger i,.desktop-nav__item.is-open .desktop-nav__toggle i { transform:rotate(180deg); }
.desktop-nav__item>a::after,.desktop-nav__trigger::after,.desktop-nav__parent-row>a::after { position:absolute; right:0; bottom:0; left:0; height:2px; transform:scaleX(0); background:#ff7500; content:''; transition:.18s; }
.desktop-nav__item>a:hover,.desktop-nav__item>a.active,.desktop-nav__trigger:hover,.desktop-nav__trigger.active,.desktop-nav__parent-row>a:hover,.desktop-nav__parent-row>a.active,.sign-in:hover { color:#9c4500; }
.desktop-nav__item>a:hover::after,.desktop-nav__item>a.active::after,.desktop-nav__trigger:hover::after,.desktop-nav__trigger.active::after,.desktop-nav__parent-row>a:hover::after,.desktop-nav__parent-row>a.active::after { transform:scaleX(1); }
.desktop-nav__parent-row { display:flex; align-items:center; gap:5px; }
.desktop-nav__toggle { display:grid; width:26px; height:30px; place-content:center; border:0; border-radius:6px; background:transparent; color:#56575b; cursor:pointer; }
.desktop-nav__toggle:hover { background:#f6f3f0; color:#9c4500; }
.desktop-nav__dropdown { position:absolute; z-index:60; top:68px; left:-16px; display:none; min-width:230px; padding:10px; border:1px solid #e5e0dc; border-radius:10px; background:#fff; box-shadow:0 14px 34px rgba(25,28,29,.16); }
.desktop-nav__item:hover .desktop-nav__dropdown,.desktop-nav__item:focus-within .desktop-nav__dropdown,.desktop-nav__item.is-open .desktop-nav__dropdown { display:grid; }
.desktop-nav__dropdown a { display:grid; gap:3px; padding:11px 12px; border-radius:7px; color:#45464a; font-size:13px; text-decoration:none; text-transform:none; }
.desktop-nav__dropdown strong { font-size:13px; line-height:1.25; }
.desktop-nav__dropdown small { color:#77787c; font-size:11px; font-weight:500; line-height:1.35; }
.desktop-nav__dropdown a:hover,.desktop-nav__dropdown a.active { background:#fff3e8; color:#9c4500; }
.desktop-nav__dropdown a:hover small,.desktop-nav__dropdown a.active small { color:#7b3a08; }
.site-nav__actions { display:flex; align-items:center; gap:14px; }
.nav-icon { display:grid; width:44px; height:44px; place-content:center; border-radius:50%; color:#56575b; }
.nav-icon:hover { background:#f0f1f2; color:#9c4500; }
.donate-button { display:inline-flex; min-height:44px; align-items:center; justify-content:center; gap:8px; padding:0 20px; border-radius:var(--igf-button-radius,999px); background:#ff7500; color:#fff; font-size:12px; font-weight:800; letter-spacing:.04em; text-decoration:none; text-transform:uppercase; box-shadow:0 5px 14px rgba(255,117,0,.18); }
.donate-button:hover { background:#9c4500; color:#fff; }
.sponsor-button { display:inline-flex; min-height:44px; align-items:center; justify-content:center; padding:0 15px; border:1px solid #b65a14; border-radius:var(--igf-button-radius,999px); color:#8b3e08; font-size:11px; font-weight:800; letter-spacing:.03em; text-decoration:none; text-transform:uppercase; white-space:nowrap; }
.sponsor-button:hover { border-color:#9c4500; background:#fff5ed; color:#6f2f00; }
.menu-button { display:none; width:44px; height:44px; place-content:center; border:0; border-radius:9px; background:#f0f1f2; color:#191c1d; font-size:19px; cursor:pointer; }
.mobile-action-bar { display:none; padding-bottom:10px; background:#fff; }
.mobile-action-bar__inner { display:grid; width:min(calc(100% - 40px),var(--igf-content-width,1240px)); grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; margin:0 auto; }
.mobile-action-bar :is(.sponsor-button,.donate-button) { min-width:0; min-height:44px; height:auto; padding:10px 8px; line-height:1.25; text-align:center; white-space:normal; overflow-wrap:anywhere; }
.mobile-action-bar__single { grid-column:1/-1; }
.mobile-nav { position:absolute; z-index:60; top:100%; right:0; left:0; display:grid; max-height:min(75vh,calc(100vh - 80px)); max-height:min(75dvh,calc(100dvh - 80px)); overflow:auto; overscroll-behavior:contain; padding:16px 20px 24px; border-bottom:1px solid #ddd; background:#fff; box-shadow:0 16px 30px rgba(0,0,0,.12); }
.mobile-nav__group>a,.mobile-nav__parent,.mobile-nav__parent-row>a { display:flex; width:100%; min-height:48px; align-items:center; justify-content:space-between; padding:11px 8px; border:0; border-bottom:1px solid #eee; background:#fff; color:#292a2d; font:750 15px/1.3 'Hanken Grotesk',Arial,sans-serif; text-align:left; text-decoration:none; cursor:pointer; }
.mobile-nav__parent i,.mobile-nav__parent-row button i { color:#8b3e08; font-size:11px; transition:transform .18s ease; }
.mobile-nav__parent i.is-rotated,.mobile-nav__parent-row button i.is-rotated { transform:rotate(180deg); }
.mobile-nav__parent-row { display:grid; grid-template-columns:1fr 48px; }
.mobile-nav__parent-row button { display:grid; min-width:48px; min-height:48px; place-content:center; border:0; border-bottom:1px solid #eee; background:#fff; cursor:pointer; }
.mobile-nav__submenu { padding:5px 0 8px; background:#faf8f6; }
.mobile-nav__child { display:grid; min-height:44px; gap:3px; padding:12px 18px 12px 28px; border-left:3px solid transparent; color:#5f6065; font-size:14px; font-weight:700; text-decoration:none; }
.mobile-nav__child small { color:#77787c; font-size:12px; font-weight:500; line-height:1.35; }
.mobile-nav__child:hover,.mobile-nav__child.active { border-left-color:#ff7500; background:#fff3e8; color:#8b3e08; }
.mobile-nav>.mobile-nav__sign-in { display:block; padding:13px 8px; border-bottom:1px solid #eee; color:#292a2d; font-weight:700; text-decoration:none; }
.site-nav :is(a,button):focus-visible { outline:3px solid #ff7500; outline-offset:3px; }
@media(max-width:1460px) { .site-nav__actions>.sponsor-button.site-nav__inline-action { display:none; } }
@media(max-width:1180px) { .desktop-nav,.sign-in { display:none; } .menu-button { display:grid; } .mobile-nav { max-height:min(75vh,calc(100vh - 118px)); max-height:min(75dvh,calc(100dvh - 118px)); } }
@media(max-width:767px) { .mobile-nav { max-height:min(75vh,calc(100vh - 80px)); max-height:min(75dvh,calc(100dvh - 80px)); } }
@media(max-width:720px) { .site-nav__actions>.site-nav__inline-action { display:none; } .mobile-action-bar { display:block; } .mobile-nav { max-height:min(75vh,calc(100vh - 134px)); max-height:min(75dvh,calc(100dvh - 134px)); } }
@media(max-width:560px) { .site-nav__inner { width:calc(100% - 28px); height:70px; } .site-brand { width:82px; min-width:82px; height:70px; } .site-brand__logo { width:82px; height:64px; } .mobile-action-bar__inner { width:calc(100% - 28px); } .donate-button { font-size:11px; } .site-nav__actions { gap:8px; } .mobile-nav { max-height:min(75vh,calc(100vh - 124px)); max-height:min(75dvh,calc(100dvh - 124px)); } }
@media(max-width:390px) { .donate-button i { display:none; } }
</style>
