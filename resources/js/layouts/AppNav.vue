<template>
  <header ref="navRoot" class="site-nav">
    <div class="site-nav__inner">
      <Link :href="route('frontend.home')" class="site-brand" :aria-label="branding.homeLabel">
        <img class="site-brand__logo" :src="branding.logo" :alt="branding.logoAlt" width="100" height="80">
      </Link>

      <nav class="desktop-nav" :aria-label="header.primaryNavigationLabel">
        <ul class="desktop-nav__list">
          <NavigationTreeItem v-for="navItem in navigation" :key="navItem._navKey" :item="navItem" mode="desktop" :depth="1" />
        </ul>
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

    <nav id="mobile-navigation" ref="mobileNavigation" class="mobile-nav" :hidden="!drawer" :aria-label="header.mobileNavigationLabel" @keydown="handleMobileNavigationKeydown">
      <ul class="mobile-nav__list">
        <NavigationTreeItem v-for="navItem in navigation" :key="`mobile-${navItem._navKey}`" :item="navItem" mode="mobile" :depth="1" />
      </ul>
      <a class="mobile-nav__sign-in" data-mobile-nav-control :href="header.signInUrl">{{ header.signInLabel }}</a>
    </nav>
  </header>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { interpolateSetting } from '../Shared/composables/siteSettings';

defineOptions({ name: 'AppNav' });

const MAX_NAVIGATION_DEPTH = 3;
const drawer = ref(false);
const openDesktop = ref(new Set());
const openMobile = ref(new Set());
const finePointerHover = ref(false);
const navRoot = ref(null);
const menuButton = ref(null);
const mobileNavigation = ref(null);
const inertiaPage = usePage();
let finePointerQuery = null;
let mobileBreakpointQuery = null;
const hoverOpenedDesktop = new Set();

const fallbackNavigation = [
  { name: 'Home', href: '/' },
  { name: 'About Us', href: '#', children: [
    { name: 'Who We Are', href: '/about-us' },
    { name: 'Awards & Recognition', href: '/category/awards-&-recognition' },
    { name: 'Photo Gallery', href: '/gallery' },
    { name: 'Annual Reports', href: '/annual-report' },
    { name: 'Contact Us', href: '/contact-us' },
  ] },
  { name: 'Our Work', href: '#', children: [
    { name: 'Program Overview', href: '/category/our-causes' },
    { name: 'Inclusive Education', href: '/page/education' },
    { name: 'Visit Ignite School', href: '/category/visit-ignite-school' },
    { name: 'Youth Development', href: '/page/youth-development', children: [
      { name: 'Workshop', href: '/workshops' },
    ] },
    { name: 'Disaster Resilience', href: '/page/disaster-response-and-resilience' },
    { name: 'Current Projects', href: '/projects/current-project' },
    { name: 'Completed Projects', href: '/projects/completed-project' },
  ] },
  { name: 'Get Involved', href: '#', children: [
    { name: 'Volunteer', href: '/volunteer/register' },
    { name: 'Careers', href: '/careers' },
    { name: 'Sponsor a Child', href: '/sponsor-child' },
  ] },
  { name: 'News & Stories', href: '#', children: [
    { name: 'Stories', href: '/category/stories' },
    { name: 'Events & News', href: '/events' },
  ] },
  { name: 'Donate', href: '#', children: [
    { name: 'Make a Donation', href: '/donate' },
    { name: 'Give Zakat', href: '/zakat' },
  ] },
];

const navigation = computed(() => {
  const hasSharedMenus = Object.prototype.hasOwnProperty.call(inertiaPage.props, 'appMenus');
  const source = hasSharedMenus
    ? (Array.isArray(inertiaPage.props.appMenus) ? inertiaPage.props.appMenus : [])
    : fallbackNavigation;

  return normalizeNavigation(source);
});
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
  closeSubmenuLabel: inertiaPage.props.siteSettings?.header?.close_submenu_label || 'Close {item} submenu',
}));
const hasSponsorAction = computed(() => Boolean(
  String(header.value.sponsorLabel || '').trim() && String(header.value.sponsorUrl || '').trim()
));

function normalizeNavigation(source) {
  return normalizeSiblings(Array.isArray(source) ? source : [], 1, null, []);
}

function normalizeSiblings(source, depth, parentKey, ancestorBranchKeys) {
  const occurrences = new Map();

  return source
    .filter(item => item && typeof item === 'object')
    .map((item) => {
      const normalizedItem = normalizeKnownDestination(item);
      const identity = String(item.uuid || item.id || `${item.name || 'item'}|${rawDestination(item)}`);
      const occurrence = occurrences.get(identity) || 0;
      occurrences.set(identity, occurrence + 1);
      const navKey = `${parentKey || 'root'}/${identity}:${occurrence}`;
      const rawChildren = depth < MAX_NAVIGATION_DEPTH && Array.isArray(item.children) ? item.children : [];
      const nextAncestors = rawChildren.length ? [...ancestorBranchKeys, navKey] : ancestorBranchKeys;

      return {
        ...normalizedItem,
        _navKey: navKey,
        _domId: stableDomToken(navKey),
        _parentKey: parentKey,
        _ancestorBranchKeys: ancestorBranchKeys,
        _depth: depth,
        children: normalizeSiblings(rawChildren, depth + 1, navKey, nextAncestors),
      };
    });
}

function stableDomToken(value) {
  let hash = 2166136261;
  for (let index = 0; index < value.length; index += 1) {
    hash ^= value.charCodeAt(index);
    hash = Math.imul(hash, 16777619);
  }
  return `item-${(hash >>> 0).toString(36)}`;
}

function rawDestination(item) {
  if (item?.href) return String(item.href).trim().replace(/\/$/, '') || '/';
  if (item?.link === 'custom') return String(item.slug || '').trim().replace(/\/$/, '') || '#';
  if (item?.link === 'frontend.jobs.index') return '/careers';
  if (item?.link === 'frontend.workshops.index') return '/workshops';
  return `${item?.link || ''}|${item?.slug || ''}`;
}

function normalizeKnownDestination(item) {
  if (isLegacyCareerDestination(item) || item?.link === 'frontend.jobs.index') {
    return { ...item, href: '/careers', link: 'custom', slug: '/careers' };
  }
  if (item?.link === 'frontend.workshops.index') {
    return { ...item, href: '/workshops', link: 'custom', slug: '/workshops' };
  }
  return { ...item };
}

function isLegacyCareerDestination(item) {
  return (item?.link === 'frontend.category' && String(item?.slug || '').replace(/^\//, '') === 'career')
    || (item?.link === 'custom' && String(item?.slug || '').replace(/\/$/, '') === '/category/career')
    || String(item?.href || '').replace(/\/$/, '') === '/category/career';
}

function menuHref(item) {
  if (item.href) return safeCustomHref(item.href);
  if (item.link === 'custom') return safeCustomHref(item.slug);
  try {
    if (item.link && window.route().has(item.link)) return window.route(item.link, item.slug ? [item.slug] : []);
  } catch { /* fall through to a safe local URL */ }
  return item.slug ? safeCustomHref(`/page/${String(item.slug).replace(/^\//, '')}`) : '#';
}

function safeCustomHref(value) {
  const href = String(value || '').trim();
  if (/^\/(?![\\/])/.test(href) || /^(https?:|mailto:|tel:)/i.test(href)) return href;
  return '#';
}

function isPlaceholderMenu(item) {
  return menuHref(item) === '#';
}

function menuDescription(item) {
  return String(item?.description || '').trim();
}

function submenuLabel(item, expanded) {
  return interpolateSetting(
    expanded ? header.value.closeSubmenuLabel : header.value.openSubmenuLabel,
    { item: item?.name || '' }
  );
}

function normalizedPath(value) {
  const path = String(value || '/').replace(/\/$/, '');
  return path || '/';
}

function menuPath(item) {
  const href = menuHref(item);
  if (href === '#') return null;
  try {
    const url = new URL(href, window.location.origin);
    if (url.origin !== window.location.origin) return null;
    return normalizedPath(url.pathname);
  } catch {
    return null;
  }
}

function isOwnActive(item) {
  const target = menuPath(item);
  if (!target) return false;
  const current = normalizedPath(window.location.pathname);
  return target === '/' ? current === '/' : current === target || current.startsWith(`${target}/`);
}

function isCurrentPage(item) {
  const target = menuPath(item);
  return Boolean(target && normalizedPath(window.location.pathname) === target);
}

function isActive(item) {
  return isOwnActive(item) || Boolean(item.children?.some(child => isActive(child)));
}

function expandedState(mode) {
  return mode === 'desktop' ? openDesktop : openMobile;
}

function isBranchExpanded(mode, item) {
  return expandedState(mode).value.has(item._navKey);
}

function allNavigationItems(items = navigation.value) {
  return items.flatMap(item => [item, ...allNavigationItems(item.children || [])]);
}

function descendantBranchKeys(item) {
  return (item.children || []).flatMap(child => [
    ...(child.children?.length ? [child._navKey] : []),
    ...descendantBranchKeys(child),
  ]);
}

function closeBranch(mode, item) {
  const state = expandedState(mode);
  const next = new Set(state.value);
  next.delete(item._navKey);
  descendantBranchKeys(item).forEach(key => next.delete(key));
  state.value = next;
}

function openBranch(mode, item) {
  const state = expandedState(mode);
  const next = new Set(state.value);
  const siblings = allNavigationItems().filter(candidate => (
    candidate.children?.length
      && candidate._parentKey === item._parentKey
      && candidate._navKey !== item._navKey
  ));

  siblings.forEach((sibling) => {
    next.delete(sibling._navKey);
    descendantBranchKeys(sibling).forEach(key => next.delete(key));
  });
  item._ancestorBranchKeys.forEach(key => next.add(key));
  next.add(item._navKey);
  state.value = next;
}

function toggleBranch(mode, item) {
  if (isBranchExpanded(mode, item)) closeBranch(mode, item);
  else openBranch(mode, item);
}

function handleDisclosureClick(event, mode, item) {
  if (mode === 'desktop' && event.detail > 0 && hoverOpenedDesktop.delete(item._navKey)) return;
  toggleBranch(mode, item);
}

function navigationToggleId(mode, item) {
  return `${mode}-nav-toggle-${item._domId}`;
}

function navigationPanelId(mode, item) {
  return `${mode}-nav-panel-${item._domId}`;
}

function navigationLabelId(mode, item) {
  return `${mode}-nav-label-${item._domId}`;
}

function restoreBranchToggleFocus(mode, item) {
  nextTick(() => document.getElementById(navigationToggleId(mode, item))?.focus());
}

function handleBranchKeydown(event, mode, item) {
  if (event.key !== 'Escape' || !isBranchExpanded(mode, item)) return;
  event.preventDefault();
  event.stopPropagation();
  closeBranch(mode, item);
  restoreBranchToggleFocus(mode, item);
}

function handleDesktopPointerEnter(event, item) {
  if (!finePointerHover.value || (event.pointerType && event.pointerType !== 'mouse')) return;
  if (!isBranchExpanded('desktop', item)) hoverOpenedDesktop.add(item._navKey);
  openBranch('desktop', item);
}

function handleDesktopPointerLeave(event, item) {
  if (!finePointerHover.value || (event.pointerType && event.pointerType !== 'mouse')) return;
  if (event.currentTarget.contains(document.activeElement)) return;
  hoverOpenedDesktop.delete(item._navKey);
  closeBranch('desktop', item);
}

function handleDesktopFocusOut(event, item) {
  if (event.relatedTarget && event.currentTarget.contains(event.relatedTarget)) return;
  hoverOpenedDesktop.delete(item._navKey);
  closeBranch('desktop', item);
}

function activeBranchKeys(items = navigation.value) {
  return items.flatMap(item => (
    item.children?.length && isActive(item)
      ? [item._navKey, ...activeBranchKeys(item.children)]
      : []
  ));
}

async function toggleDrawer() {
  if (drawer.value) {
    closeMenusAndRestoreFocus();
    return;
  }

  drawer.value = true;
  openMobile.value = new Set(activeBranchKeys());
  await nextTick();
  mobileNavigation.value?.querySelector('[data-mobile-nav-control]')?.focus();
}

function closeMenus() {
  hoverOpenedDesktop.clear();
  openDesktop.value = new Set();
  openMobile.value = new Set();
  drawer.value = false;
}

function closeMenusAndRestoreFocus() {
  const wasOpen = drawer.value;
  closeMenus();
  if (wasOpen) nextTick(() => menuButton.value?.focus());
}

function handleMobileNavigationKeydown(event) {
  if (event.key !== 'Escape') return;
  event.preventDefault();
  event.stopPropagation();
  closeMenusAndRestoreFocus();
}

function closeFromOutside(event) {
  if (navRoot.value && !navRoot.value.contains(event.target)) closeMenus();
}

function updateFinePointer(event) {
  finePointerHover.value = Boolean(event.matches);
}

function ownNavigationControl(item) {
  if (!item) return null;
  return [...item.querySelectorAll('a,button')]
    .find(control => control.closest('[data-nav-key]') === item) || null;
}

function desktopFocusTarget(previousFocus) {
  if (previousFocus?.closest?.('.mobile-nav__sign-in')) {
    return navRoot.value?.querySelector('.site-nav__actions .sign-in') || null;
  }

  const mobileGroup = previousFocus?.closest?.('.mobile-nav__group[data-nav-key]');
  if (mobileGroup) {
    const groupKey = mobileGroup.getAttribute('data-nav-key');
    const desktopGroup = [...(navRoot.value?.querySelectorAll('.desktop-nav__item[data-nav-key]') || [])]
      .find(item => item.getAttribute('data-nav-key') === groupKey);
    const matchingControl = ownNavigationControl(desktopGroup);
    if (matchingControl) return matchingControl;
  }

  return navRoot.value?.querySelector('.desktop-nav a, .desktop-nav button, .site-brand') || null;
}

function handleBreakpointChange(event) {
  const previousFocus = document.activeElement;

  if (event.matches) {
    const desktopFocusWillHide = Boolean(previousFocus?.closest?.('.desktop-nav, .site-nav__actions .sign-in'));
    openDesktop.value = new Set();
    if (desktopFocusWillHide) nextTick(() => menuButton.value?.focus());
    return;
  }

  const mobileFocusWillHide = previousFocus === menuButton.value
    || Boolean(mobileNavigation.value?.contains(previousFocus));
  const nextFocus = mobileFocusWillHide ? desktopFocusTarget(previousFocus) : null;
  drawer.value = false;
  openMobile.value = new Set();
  if (nextFocus) nextTick(() => nextFocus.focus());
}

function addMediaListener(query, handler) {
  if (typeof query?.addEventListener === 'function') query.addEventListener('change', handler);
  else query?.addListener?.(handler);
}

function removeMediaListener(query, handler) {
  if (typeof query?.removeEventListener === 'function') query.removeEventListener('change', handler);
  else query?.removeListener?.(handler);
}

function renderItemLabel(item, nested) {
  if (!nested) return item.name;
  const children = [h('strong', item.name)];
  if (menuDescription(item)) children.push(h('small', menuDescription(item)));
  return children;
}

function renderDisclosureIcon(expanded) {
  return h('i', {
    class: ['fa-solid', 'fa-chevron-down', { 'is-rotated': expanded }],
    'aria-hidden': 'true',
  });
}

const NavigationTreeItem = defineComponent({
  name: 'NavigationTreeItem',
  props: {
    item: { type: Object, required: true },
    mode: { type: String, required: true },
    depth: { type: Number, required: true },
  },
  setup(props) {
    return () => {
      const item = props.item;
      const desktop = props.mode === 'desktop';
      const topLevel = props.depth === 1;
      const hasChildren = Boolean(item.children?.length);
      const expanded = hasChildren && isBranchExpanded(props.mode, item);
      const active = isActive(item);
      const currentPage = isCurrentPage(item);
      const toggleId = hasChildren ? navigationToggleId(props.mode, item) : undefined;
      const panelId = hasChildren ? navigationPanelId(props.mode, item) : undefined;
      const labelId = hasChildren ? navigationLabelId(props.mode, item) : undefined;
      const itemClasses = desktop
        ? [topLevel ? 'desktop-nav__item' : 'desktop-nav__entry', `desktop-nav__entry--depth-${props.depth}`, { 'is-open': expanded, 'is-active': active }]
        : [topLevel ? 'mobile-nav__group' : 'mobile-nav__entry', `mobile-nav__entry--depth-${props.depth}`, { 'is-open': expanded, 'is-active': active }];
      const itemAttributes = {
        class: itemClasses,
        'data-nav-key': item._navKey,
        'data-nav-depth': String(props.depth),
      };

      if (hasChildren) {
        itemAttributes.onKeydown = event => handleBranchKeydown(event, props.mode, item);
        if (desktop) {
          itemAttributes.onPointerenter = event => handleDesktopPointerEnter(event, item);
          itemAttributes.onPointerleave = event => handleDesktopPointerLeave(event, item);
          itemAttributes.onFocusout = event => handleDesktopFocusOut(event, item);
        }
      }

      const nested = !topLevel;
      const linkClasses = [
        desktop ? (nested ? 'desktop-nav__child' : 'desktop-nav__link') : (nested ? 'mobile-nav__child' : 'mobile-nav__link'),
        { active },
      ];
      const linkAttributes = {
        href: menuHref(item),
        class: linkClasses,
        'aria-current': currentPage ? 'page' : undefined,
        ...(desktop ? {} : { 'data-mobile-nav-control': '' }),
        onClick: closeMenus,
      };
      const children = [];

      if (!hasChildren) {
        children.push(h('a', linkAttributes, renderItemLabel(item, nested)));
        return h('li', itemAttributes, children);
      }

      const toggleAttributes = {
        id: toggleId,
        type: 'button',
        'aria-expanded': String(expanded),
        'aria-controls': panelId,
        ...(desktop ? {} : { 'data-mobile-nav-control': '' }),
        onClick: event => handleDisclosureClick(event, props.mode, item),
      };

      if (isPlaceholderMenu(item)) {
        children.push(h('button', {
          ...toggleAttributes,
          class: [desktop ? (topLevel ? 'desktop-nav__trigger' : 'desktop-nav__branch') : 'mobile-nav__parent', { active }],
        }, [
          h('span', { id: labelId, class: `${props.mode}-nav__label` }, renderItemLabel(item, nested)),
          renderDisclosureIcon(expanded),
        ]));
      } else {
        linkAttributes.id = labelId;
        children.push(h('div', {
          class: [desktop ? 'desktop-nav__parent-row' : 'mobile-nav__parent-row', { 'is-nested': nested }],
        }, [
          h('a', linkAttributes, renderItemLabel(item, nested)),
          h('button', {
            ...toggleAttributes,
            class: desktop ? 'desktop-nav__toggle' : 'mobile-nav__toggle',
            'aria-label': submenuLabel(item, expanded),
          }, [renderDisclosureIcon(expanded)]),
        ]));
      }

      children.push(h('ul', {
        id: panelId,
        class: [
          desktop ? 'desktop-nav__dropdown' : 'mobile-nav__submenu',
          { 'desktop-nav__dropdown--nested': desktop && nested, 'mobile-nav__submenu--nested': !desktop && nested },
        ],
        hidden: !expanded,
        'aria-labelledby': labelId,
      }, item.children.map(child => h(NavigationTreeItem, {
        key: `${props.mode}-${child._navKey}`,
        item: child,
        mode: props.mode,
        depth: props.depth + 1,
      }))));

      return h('li', itemAttributes, children);
    };
  },
});

watch(navigation, () => {
  const validBranchKeys = new Set(allNavigationItems().filter(item => item.children?.length).map(item => item._navKey));
  openDesktop.value = new Set([...openDesktop.value].filter(key => validBranchKeys.has(key)));
  openMobile.value = new Set([...openMobile.value].filter(key => validBranchKeys.has(key)));
});

onMounted(() => {
  document.addEventListener('click', closeFromOutside);
  if (typeof window.matchMedia === 'function') {
    finePointerQuery = window.matchMedia('(hover: hover) and (pointer: fine)');
    mobileBreakpointQuery = window.matchMedia('(max-width: 1180px)');
    finePointerHover.value = finePointerQuery.matches;
    addMediaListener(finePointerQuery, updateFinePointer);
    addMediaListener(mobileBreakpointQuery, handleBreakpointChange);
  }
});

onBeforeUnmount(() => {
  document.removeEventListener('click', closeFromOutside);
  removeMediaListener(finePointerQuery, updateFinePointer);
  removeMediaListener(mobileBreakpointQuery, handleBreakpointChange);
});
</script>

<style scoped>
.site-nav { position:relative; z-index:50; border-bottom:1px solid #e5e0dc; background:#fff; color:#191c1d; font-family:'Hanken Grotesk',Arial,sans-serif; }
.site-nav__inner { display:flex; width:min(calc(100% - 40px),var(--igf-content-width,1240px)); height:80px; align-items:center; justify-content:space-between; gap:24px; margin:0 auto; }
.site-brand { display:inline-flex; width:calc(var(--igf-brand-font-size,20px) * 5); min-width:calc(var(--igf-brand-font-size,20px) * 5); height:80px; align-items:center; justify-content:center; text-decoration:none; }
.site-brand__logo { display:block; width:100%; height:80px; object-fit:contain; }
.desktop-nav { display:flex; align-self:stretch; align-items:center; justify-content:center; }
.site-nav :deep(ul),.site-nav :deep(li) { padding:0; margin:0; list-style:none; }
.desktop-nav__list { display:flex; height:100%; align-items:center; justify-content:center; gap:21px; }
.site-nav :deep(.desktop-nav__item) { position:relative; display:flex; height:100%; align-items:center; }
.site-nav :deep(.desktop-nav__entry) { position:relative; }
.site-nav :deep(.desktop-nav__item>a),.site-nav :deep(.desktop-nav__trigger),.site-nav :deep(.desktop-nav__parent-row>a),.sign-in { position:relative; border:0; background:transparent; color:#56575b; font:800 12px/1.2 'Hanken Grotesk',Arial,sans-serif; letter-spacing:.025em; text-decoration:none; text-transform:uppercase; white-space:nowrap; cursor:pointer; }
.site-nav :deep(.desktop-nav__item>a),.site-nav :deep(.desktop-nav__trigger),.site-nav :deep(.desktop-nav__parent-row>a) { display:inline-flex; min-height:44px; align-items:center; padding:9px 0; }
.site-nav :deep(.desktop-nav__trigger),.site-nav :deep(.desktop-nav__branch) { justify-content:space-between; gap:7px; }
.site-nav :deep(.desktop-nav__trigger i),.site-nav :deep(.desktop-nav__toggle i),.site-nav :deep(.desktop-nav__branch i) { font-size:9px; transition:transform .18s ease; }
.site-nav :deep(.desktop-nav :is(.desktop-nav__trigger,.desktop-nav__toggle,.desktop-nav__branch) i.is-rotated) { transform:rotate(180deg); }
.site-nav :deep(.desktop-nav__item>a::after),.site-nav :deep(.desktop-nav__trigger::after),.site-nav :deep(.desktop-nav__item>.desktop-nav__parent-row>a::after) { position:absolute; right:0; bottom:0; left:0; height:2px; transform:scaleX(0); background:#ff7500; content:''; transition:.18s; }
.site-nav :deep(.desktop-nav__item>a:hover),.site-nav :deep(.desktop-nav__item>a.active),.site-nav :deep(.desktop-nav__trigger:hover),.site-nav :deep(.desktop-nav__trigger.active),.site-nav :deep(.desktop-nav__item>.desktop-nav__parent-row>a:hover),.site-nav :deep(.desktop-nav__item>.desktop-nav__parent-row>a.active),.sign-in:hover { color:#9c4500; }
.site-nav :deep(.desktop-nav__item>a:hover::after),.site-nav :deep(.desktop-nav__item>a.active::after),.site-nav :deep(.desktop-nav__trigger:hover::after),.site-nav :deep(.desktop-nav__trigger.active::after),.site-nav :deep(.desktop-nav__item>.desktop-nav__parent-row>a:hover::after),.site-nav :deep(.desktop-nav__item>.desktop-nav__parent-row>a.active::after) { transform:scaleX(1); }
.site-nav :deep(.desktop-nav__parent-row) { display:grid; grid-template-columns:minmax(0,1fr) 44px; align-items:center; }
.site-nav :deep(.desktop-nav__item>.desktop-nav__parent-row) { display:flex; gap:2px; }
.site-nav :deep(.desktop-nav__toggle) { display:grid; width:44px; min-width:44px; height:44px; place-content:center; border:0; border-radius:7px; background:transparent; color:#56575b; cursor:pointer; }
.site-nav :deep(.desktop-nav__toggle:hover) { background:#f6f3f0; color:#9c4500; }
.site-nav :deep(.desktop-nav__dropdown) { position:absolute; z-index:60; top:calc(100% - 12px); left:-16px; display:grid; min-width:250px; padding:10px; border:1px solid #e5e0dc; border-radius:10px; background:#fff; box-shadow:0 14px 34px rgba(25,28,29,.16); }
.site-nav :deep(.desktop-nav__dropdown[hidden]),.site-nav :deep(.mobile-nav__submenu[hidden]) { display:none; }
.site-nav :deep(.desktop-nav__dropdown--nested) { top:-11px; left:calc(100% - 4px); }
.site-nav :deep(.desktop-nav__item:nth-last-child(-n+2) .desktop-nav__dropdown--nested) { right:calc(100% - 4px); left:auto; }
.site-nav :deep(.desktop-nav__child),.site-nav :deep(.desktop-nav__branch) { display:grid; width:100%; min-height:44px; gap:3px; align-items:center; padding:9px 12px; border:0; border-radius:7px; background:#fff; color:#45464a; font:inherit; text-align:left; text-decoration:none; text-transform:none; cursor:pointer; }
.site-nav :deep(.desktop-nav__branch) { grid-template-columns:minmax(0,1fr) 44px; }
.site-nav :deep(.desktop-nav__branch>.desktop-nav__label),.site-nav :deep(.mobile-nav__entry .mobile-nav__label) { display:grid; min-width:0; gap:3px; }
.site-nav :deep(.desktop-nav__branch>i) { justify-self:center; }
.site-nav :deep(.desktop-nav__parent-row.is-nested>a) { display:grid; min-height:44px; gap:3px; align-content:center; padding:9px 12px; border-radius:7px; color:#45464a; text-decoration:none; text-transform:none; }
.site-nav :deep(.desktop-nav__dropdown strong) { font-size:13px; line-height:1.25; }
.site-nav :deep(.desktop-nav__dropdown small) { color:#66676b; font-size:11px; font-weight:500; line-height:1.35; }
.site-nav :deep(.desktop-nav__entry:is(.is-active,.is-open)>.desktop-nav__branch),.site-nav :deep(.desktop-nav__child:hover),.site-nav :deep(.desktop-nav__child.active),.site-nav :deep(.desktop-nav__parent-row.is-nested>a:hover),.site-nav :deep(.desktop-nav__parent-row.is-nested>a.active),.site-nav :deep(.desktop-nav__branch:hover) { background:#fff3e8; color:#9c4500; }
.site-nav :deep(.desktop-nav__entry:is(.is-active,.is-open)>:is(.desktop-nav__branch,.desktop-nav__parent-row) small),.site-nav :deep(.desktop-nav__child:hover small),.site-nav :deep(.desktop-nav__child.active small) { color:#7b3a08; }
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
.mobile-nav[hidden] { display:none; }
.site-nav :deep(.mobile-nav__group>a),.site-nav :deep(.mobile-nav__parent),.site-nav :deep(.mobile-nav__parent-row>a) { display:flex; width:100%; min-height:48px; align-items:center; justify-content:space-between; padding:11px 8px; border:0; border-bottom:1px solid #eee; background:#fff; color:#292a2d; font:750 15px/1.3 'Hanken Grotesk',Arial,sans-serif; text-align:left; text-decoration:none; cursor:pointer; }
.site-nav :deep(.mobile-nav__entry :is(.mobile-nav__parent,.mobile-nav__child,.mobile-nav__parent-row>a)) { border-bottom:0; }
.site-nav :deep(.mobile-nav__parent i),.site-nav :deep(.mobile-nav__toggle i) { color:#8b3e08; font-size:11px; transition:transform .18s ease; }
.site-nav :deep(.mobile-nav :is(.mobile-nav__parent,.mobile-nav__toggle) i.is-rotated) { transform:rotate(180deg); }
.site-nav :deep(.mobile-nav__parent-row) { display:grid; grid-template-columns:minmax(0,1fr) 48px; }
.site-nav :deep(.mobile-nav__toggle) { display:grid; min-width:48px; min-height:48px; place-content:center; border:0; border-bottom:1px solid #eee; background:#fff; cursor:pointer; }
.site-nav :deep(.mobile-nav__submenu) { padding:5px 0 8px; background:#faf8f6; }
.site-nav :deep(.mobile-nav__submenu--nested) { margin-left:18px; border-left:2px solid #eadfd6; }
.site-nav :deep(.mobile-nav__child) { display:grid; min-height:44px; gap:3px; align-content:center; padding:10px 18px 10px 28px; border-left:3px solid transparent; color:#5f6065; font-size:14px; font-weight:700; text-decoration:none; }
.site-nav :deep(.mobile-nav__entry--depth-3>.mobile-nav__child) { padding-left:36px; }
.site-nav :deep(.mobile-nav__child small) { color:#66676b; font-size:12px; font-weight:500; line-height:1.35; }
.site-nav :deep(.mobile-nav__group.is-active>:is(.mobile-nav__link,.mobile-nav__parent)),.site-nav :deep(.mobile-nav__group.is-active>.mobile-nav__parent-row>a),.site-nav :deep(.mobile-nav__entry.is-active>.mobile-nav__parent),.site-nav :deep(.mobile-nav__child:hover),.site-nav :deep(.mobile-nav__child.active) { border-left-color:#ff7500; background:#fff3e8; color:#8b3e08; }
.site-nav :deep(.mobile-nav__group.is-active>:is(.mobile-nav__link,.mobile-nav__parent)),.site-nav :deep(.mobile-nav__group.is-active>.mobile-nav__parent-row>a) { box-shadow:inset 3px 0 #ff7500; }
.mobile-nav>.mobile-nav__sign-in { display:block; min-height:44px; padding:13px 8px; border-bottom:1px solid #eee; color:#292a2d; font-weight:700; text-decoration:none; }
.site-nav :deep(a:focus-visible),.site-nav :deep(button:focus-visible) { outline:3px solid #ff7500; outline-offset:3px; }
@media(max-width:1460px) { .site-nav__actions>.sponsor-button.site-nav__inline-action { display:none; } }
@media(max-width:1180px) { .desktop-nav,.sign-in { display:none; } .menu-button { display:grid; } .mobile-nav { display:grid; max-height:min(75vh,calc(100vh - 118px)); max-height:min(75dvh,calc(100dvh - 118px)); } }
@media(min-width:1181px) { .mobile-nav { display:none; } }
@media(max-width:767px) { .mobile-nav { max-height:min(75vh,calc(100vh - 80px)); max-height:min(75dvh,calc(100dvh - 80px)); } }
@media(max-width:720px) { .site-nav__actions>.site-nav__inline-action { display:none; } .mobile-action-bar { display:block; } .mobile-nav { max-height:min(75vh,calc(100vh - 134px)); max-height:min(75dvh,calc(100dvh - 134px)); } }
@media(max-width:560px) { .site-nav__inner { width:calc(100% - 28px); height:70px; } .site-brand { width:82px; min-width:82px; height:70px; } .site-brand__logo { width:82px; height:64px; } .mobile-action-bar__inner { width:calc(100% - 28px); } .donate-button { font-size:11px; } .site-nav__actions { gap:8px; } .mobile-nav { max-height:min(75vh,calc(100vh - 124px)); max-height:min(75dvh,calc(100dvh - 124px)); } }
@media(max-width:390px) { .donate-button i { display:none; } }
</style>
