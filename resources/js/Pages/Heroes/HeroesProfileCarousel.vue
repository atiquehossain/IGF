<template>
  <section
    class="igf-heroes-carousel"
    :class="{ 'is-motion-enabled': canAnimate }"
    :aria-label="text('carousel_label')"
    @mouseenter="interactionPaused = true"
    @mouseleave="interactionPaused = false"
    @focusin="interactionPaused = true"
    @focusout="releaseFocusPause"
  >
    <div
      v-if="normalizedMembers.length"
      ref="rail"
      class="igf-heroes-carousel__rail"
      role="group"
      :aria-label="text('people_label')"
      @pointerdown="startDrag"
      @pointermove="moveDrag"
      @pointerup="endDrag"
      @pointercancel="endDrag"
      @lostpointercapture="endDrag"
    >
      <button
        v-for="(member, index) in normalizedMembers"
        :id="memberControlId(member, index)"
        :key="member.key"
        :ref="element => setMemberControl(element, index)"
        type="button"
        class="igf-heroes-carousel__person"
        :class="{ 'is-active': index === activeIndex }"
        :aria-pressed="index === activeIndex ? 'true' : 'false'"
        :aria-controls="profileId"
        :tabindex="index === activeIndex ? 0 : -1"
        @click="selectFromPointer(index, $event)"
        @keydown="handlePersonKeydown(index, $event)"
      >
        <span class="igf-heroes-carousel__portrait">
          <img
            v-if="member.image && !failedImages.has(member.key)"
            :src="member.image"
            alt=""
            width="150"
            height="150"
            loading="lazy"
            decoding="async"
            @error="markImageFailed(member.key)"
          >
          <span v-else aria-hidden="true">{{ initials(member.name) }}</span>
        </span>
        <strong>{{ member.name }}</strong>
        <small v-if="member.role">{{ member.role }}</small>
      </button>
    </div>

    <div v-if="normalizedMembers.length > 1" class="igf-heroes-carousel__navigation" :aria-label="text('navigation_label')">
      <button type="button" class="igf-heroes-carousel__arrow" :aria-label="text('previous_person')" @click="move(-1, true)">
        <i class="fa-solid fa-arrow-left" aria-hidden="true" />
      </button>
      <div class="igf-heroes-carousel__dots" role="group" :aria-label="text('person_picker_label')">
        <button
          v-for="(member, index) in normalizedMembers"
          :key="`dot-${member.key}`"
          type="button"
          :class="{ 'is-active': index === activeIndex }"
          :aria-current="index === activeIndex ? 'true' : null"
          :aria-label="personPickerLabel(member, index)"
          @click="select(index)"
        ><span aria-hidden="true" /></button>
      </div>
      <button
        v-if="autoplay && canAnimate"
        type="button"
        class="igf-heroes-carousel__autoplay"
        :aria-label="userPaused ? text('play') : text('pause')"
        :aria-pressed="userPaused ? 'false' : 'true'"
        @click="userPaused = !userPaused"
      >
        <i :class="userPaused ? 'fa-solid fa-play' : 'fa-solid fa-pause'" aria-hidden="true" />
      </button>
      <button type="button" class="igf-heroes-carousel__arrow" :aria-label="text('next_person')" @click="move(1, true)">
        <i class="fa-solid fa-arrow-right" aria-hidden="true" />
      </button>
    </div>

    <article
      v-if="activeMember"
      :id="profileId"
      :key="activeMember.key"
      class="igf-heroes-carousel__profile"
      role="region"
      :aria-labelledby="`${profileId}-heading`"
    >
      <div class="igf-heroes-carousel__profile-heading">
        <p v-if="activeMember.role" class="igf-heroes-carousel__role">{{ activeMember.role }}</p>
        <h2 :id="`${profileId}-heading`">{{ activeMember.name }}</h2>
        <ul v-if="activeMember.socials.length" class="igf-heroes-carousel__socials" :aria-label="text('social_links')">
          <li v-for="link in activeMember.socials" :key="`${link.platform}-${link.url}`">
            <a
              :href="link.url"
              target="_blank"
              rel="noopener noreferrer"
              :aria-label="`${link.label} — ${activeMember.name} (${text('opens_new_tab')})`"
            >
              <i :class="socialIcon(link.platform)" aria-hidden="true" />
              <span class="sr-only">{{ link.label }}</span>
            </a>
          </li>
        </ul>
      </div>
      <div v-if="activeMember.biography.length" class="igf-heroes-carousel__biography">
        <p v-for="(paragraph, index) in activeMember.biography" :key="`${activeMember.key}-bio-${index}`">{{ paragraph }}</p>
      </div>
      <p v-if="activeMember.qualification" class="igf-heroes-carousel__qualification">
        <strong>{{ text('qualification') }}:</strong> {{ activeMember.qualification }}
      </p>
    </article>

    <p v-else class="igf-heroes-carousel__empty" role="status">{{ text('empty_people') }}</p>
    <p class="sr-only" role="status" :aria-live="announceChanges ? 'polite' : 'off'" aria-atomic="true">{{ statusText }}</p>
  </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  members: { type: Array, default: () => [] },
  copy: { type: Object, default: () => ({}) },
  autoplay: { type: Boolean, default: false },
  animationEnabled: { type: Boolean, default: true },
  idPrefix: { type: String, default: 'heroes' },
});

const defaultCopy = Object.freeze({
  carousel_label: 'Meet the heroes profile carousel',
  people_label: 'Choose a hero',
  navigation_label: 'Hero profile controls',
  person_picker_label: 'Choose a hero profile',
  previous_person: 'Show the previous hero',
  next_person: 'Show the next hero',
  show_person: 'Show {name}, profile {current} of {total}',
  pause: 'Pause automatic profile changes',
  play: 'Play automatic profile changes',
  social_links: 'Social links',
  opens_new_tab: 'opens in a new tab',
  qualification: 'Qualification',
  empty_people: 'No published heroes are available yet.',
  selected_person: 'Selected profile: {name}, {current} of {total}.',
});

const rail = ref(null);
const memberControls = ref([]);
const activeIndex = ref(0);
const failedImages = ref(new Set());
const prefersReducedMotion = ref(false);
const interactionPaused = ref(false);
const userPaused = ref(false);
const announceChanges = ref(false);
let motionQuery = null;
let autoplayTimer = null;
let dragState = null;
let suppressClick = false;

const profileId = computed(() => `${domToken(props.idPrefix, 'heroes')}-profile`);
const canAnimate = computed(() => props.animationEnabled && !prefersReducedMotion.value);
const normalizedMembers = computed(() => props.members.map((member, index) => normalizeMember(member, index)).filter(member => member.name));
const activeMember = computed(() => normalizedMembers.value[activeIndex.value] || null);
const statusText = computed(() => activeMember.value
  ? interpolate(text('selected_person'), {
    name: activeMember.value.name,
    current: activeIndex.value + 1,
    total: normalizedMembers.value.length,
  })
  : text('empty_people'));

function text(key) {
  return String(props.copy?.[key] || defaultCopy[key] || '');
}
function interpolate(value, replacements) {
  return Object.entries(replacements).reduce((result, [key, replacement]) => result.replaceAll(`{${key}}`, String(replacement)), String(value));
}
function domToken(value, fallback) {
  return String(value || fallback).toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || fallback;
}
function safeMediaUrl(value) {
  const url = String(value || '').trim();
  if (!url) return '';
  if (url.startsWith('/') && !url.startsWith('//')) return url;
  try {
    const parsed = new URL(url);
    return ['http:', 'https:'].includes(parsed.protocol) ? url : '';
  } catch {
    return '';
  }
}
function safeExternalUrl(value) {
  const url = safeMediaUrl(value);
  return /^https?:\/\//i.test(url) ? url : '';
}
function biographyParagraphs(value) {
  if (Array.isArray(value)) return value.map(paragraph => String(paragraph || '').trim()).filter(Boolean);
  return String(value || '').trim().split(/\n\s*\n+/).map(paragraph => paragraph.trim()).filter(Boolean);
}
function normalizeMember(member, index) {
  const name = String(member?.name || member?.heading || '').trim();
  const rawSocials = Array.isArray(member?.social_links) ? member.social_links : [];
  const socials = rawSocials.map(link => {
    const url = safeExternalUrl(link?.url);
    const platform = socialPlatform(link);
    return { url, platform, label: String(link?.label || platformLabel(platform)).trim() };
  }).filter(link => link.url);
  return {
    key: domToken(member?.id ?? member?.uuid ?? member?.slug ?? `${name}-${index}`, `person-${index + 1}`),
    name,
    role: String(member?.designation || member?.role || member?.subheading || '').trim(),
    image: safeMediaUrl(member?.image_url || member?.image || member?.path),
    biography: biographyParagraphs(member?.biography || member?.description),
    qualification: String(member?.qualification || '').trim(),
    socials,
  };
}
function socialPlatform(link) {
  const requested = String(link?.platform || '').trim().toLowerCase();
  if (requested) return requested === 'twitter' ? 'x' : requested;
  const url = String(link?.url || '').toLowerCase();
  return ['linkedin', 'facebook', 'instagram', 'youtube', 'github'].find(platform => url.includes(platform))
    || (url.includes('x.com') || url.includes('twitter.com') ? 'x' : 'website');
}
function platformLabel(platform) {
  return { linkedin: 'LinkedIn', facebook: 'Facebook', instagram: 'Instagram', youtube: 'YouTube', github: 'GitHub', x: 'X', website: 'Website' }[platform] || 'Website';
}
function socialIcon(platform) {
  return {
    linkedin: 'fa-brands fa-linkedin-in', facebook: 'fa-brands fa-facebook-f', instagram: 'fa-brands fa-instagram',
    youtube: 'fa-brands fa-youtube', github: 'fa-brands fa-github', x: 'fa-brands fa-x-twitter', website: 'fa-solid fa-arrow-up-right-from-square',
  }[platform] || 'fa-solid fa-link';
}
function initials(name) {
  return String(name || '').split(/\s+/).filter(Boolean).slice(0, 2).map(part => part.charAt(0)).join('').toUpperCase() || 'IGF';
}
function memberControlId(member, index) {
  return `${domToken(props.idPrefix, 'heroes')}-person-${member.key}-${index + 1}`;
}
function setMemberControl(element, index) {
  if (element) memberControls.value[index] = element;
}
function personPickerLabel(member, index) {
  return interpolate(text('show_person'), { name: member.name, current: index + 1, total: normalizedMembers.value.length });
}
function select(index, focus = false, announce = true, revealControl = true) {
  if (!normalizedMembers.value.length) return;
  announceChanges.value = announce;
  activeIndex.value = Math.min(normalizedMembers.value.length - 1, Math.max(0, index));
  if (!revealControl && !focus) return;
  nextTick(() => {
    const control = memberControls.value[activeIndex.value];
    control?.scrollIntoView?.({ behavior: canAnimate.value ? 'smooth' : 'auto', block: 'nearest', inline: 'center' });
    if (focus) control?.focus?.();
  });
}
function move(direction, focus = false, announce = true, revealControl = true) {
  const total = normalizedMembers.value.length;
  if (total < 2) return;
  select((activeIndex.value + direction + total) % total, focus, announce, revealControl);
}
function selectFromPointer(index, event) {
  if (suppressClick) {
    event.preventDefault();
    return;
  }
  select(index);
}
function handlePersonKeydown(index, event) {
  const total = normalizedMembers.value.length;
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key) || total < 2) return;
  event.preventDefault();
  if (event.key === 'Home') select(0, true);
  else if (event.key === 'End') select(total - 1, true);
  else select((index + (event.key === 'ArrowRight' ? 1 : -1) + total) % total, true);
}
function markImageFailed(key) {
  failedImages.value = new Set([...failedImages.value, key]);
}
function startDrag(event) {
  if (event.pointerType === 'mouse' && event.button !== 0) return;
  dragState = { pointerId: event.pointerId, startX: event.clientX, startScrollLeft: rail.value?.scrollLeft || 0, moved: false };
  event.currentTarget.setPointerCapture?.(event.pointerId);
  event.currentTarget.classList.add('is-dragging');
  interactionPaused.value = true;
}
function moveDrag(event) {
  if (!dragState || dragState.pointerId !== event.pointerId) return;
  const distance = event.clientX - dragState.startX;
  if (Math.abs(distance) > 6) dragState.moved = true;
  if (!dragState.moved) return;
  event.preventDefault();
  event.currentTarget.scrollLeft = dragState.startScrollLeft - distance;
}
function endDrag(event) {
  if (!dragState || dragState.pointerId !== event.pointerId) return;
  if (dragState.moved) {
    suppressClick = true;
    window.setTimeout(() => { suppressClick = false; }, 0);
  }
  event.currentTarget.classList.remove('is-dragging');
  if (event.currentTarget.hasPointerCapture?.(event.pointerId)) event.currentTarget.releasePointerCapture(event.pointerId);
  dragState = null;
  interactionPaused.value = false;
}
function releaseFocusPause(event) {
  if (!event.currentTarget.contains(event.relatedTarget)) interactionPaused.value = false;
}
function syncMotionPreference(event) {
  prefersReducedMotion.value = Boolean(event?.matches ?? motionQuery?.matches);
}
function startAutoplay() {
  stopAutoplay();
  autoplayTimer = window.setInterval(() => {
    if (props.autoplay && canAnimate.value && !userPaused.value && !interactionPaused.value && !document.hidden) move(1, false, false, false);
  }, 5000);
}
function stopAutoplay() {
  if (autoplayTimer !== null) window.clearInterval(autoplayTimer);
  autoplayTimer = null;
}

watch(normalizedMembers, members => {
  if (activeIndex.value >= members.length) activeIndex.value = 0;
  memberControls.value = [];
});
watch(() => props.autoplay, startAutoplay);

onMounted(() => {
  motionQuery = window.matchMedia?.('(prefers-reduced-motion: reduce)') || null;
  syncMotionPreference();
  motionQuery?.addEventListener?.('change', syncMotionPreference);
  startAutoplay();
});
onBeforeUnmount(() => {
  stopAutoplay();
  motionQuery?.removeEventListener?.('change', syncMotionPreference);
});
</script>

<style scoped>
.igf-heroes-carousel{min-width:0;color:#202122;font-family:'Hanken Grotesk',Arial,sans-serif}.igf-heroes-carousel__rail{display:flex;max-width:100%;gap:clamp(18px,2.5vw,34px);overflow-x:auto;overscroll-behavior-inline:contain;padding:8px 4px 14px;scrollbar-width:thin;scroll-snap-type:x proximity;touch-action:pan-x;user-select:none}.igf-heroes-carousel__rail.is-dragging{cursor:grabbing;scroll-snap-type:none}.igf-heroes-carousel__person{display:flex;width:150px;min-width:150px;align-items:center;padding:0;flex-direction:column;border:0;background:transparent;color:#202122;text-align:center;cursor:pointer;scroll-snap-align:center}.igf-heroes-carousel__portrait{display:grid;width:150px;height:150px;place-items:center;overflow:hidden;border:4px solid transparent;border-radius:50%;background:#f2d7c0;color:#7c3500;font-size:32px;font-weight:850;box-shadow:0 8px 22px rgba(74,45,24,.12)}.igf-heroes-carousel__portrait img{width:100%;height:100%;object-fit:cover;object-position:center 20%;pointer-events:none}.igf-heroes-carousel__person strong{width:100%;margin-top:13px;font-size:14px;line-height:1.25}.igf-heroes-carousel__person small{width:100%;margin-top:4px;color:#665d56;font-size:11px;line-height:1.3}.igf-heroes-carousel__person:hover .igf-heroes-carousel__portrait,.igf-heroes-carousel__person.is-active .igf-heroes-carousel__portrait{border-color:#ff7500;box-shadow:0 0 0 5px rgba(255,117,0,.13),0 12px 28px rgba(74,45,24,.16)}.igf-heroes-carousel__person:focus-visible{outline:3px solid #1769aa;outline-offset:5px;border-radius:10px}.is-motion-enabled .igf-heroes-carousel__portrait{transition:border-color .22s ease,box-shadow .22s ease,transform .22s ease}.is-motion-enabled .igf-heroes-carousel__person:hover .igf-heroes-carousel__portrait{transform:translateY(-3px)}.igf-heroes-carousel__navigation{display:flex;min-height:44px;align-items:center;gap:10px;margin:8px 0 18px}.igf-heroes-carousel__navigation>button{display:grid;width:44px;height:44px;place-items:center;flex:0 0 auto;border:1px solid #cbb7a7;border-radius:50%;background:#fff;color:#713100;cursor:pointer}.igf-heroes-carousel__navigation>button:hover{border-color:#ff7500;background:#fff2e7}.igf-heroes-carousel__navigation button:focus-visible,.igf-heroes-carousel__socials a:focus-visible{outline:3px solid #1769aa;outline-offset:3px}.igf-heroes-carousel__dots{display:flex;min-width:0;align-items:center;justify-content:center;gap:2px}.igf-heroes-carousel__dots button{display:grid;width:32px;height:44px;place-items:center;padding:0;border:0;background:transparent;cursor:pointer}.igf-heroes-carousel__dots span{display:block;width:8px;height:8px;border-radius:50%;background:#c6b8ae}.igf-heroes-carousel__dots button.is-active span{width:24px;border-radius:999px;background:#a44906}.is-motion-enabled .igf-heroes-carousel__dots span{transition:width .2s ease,background-color .2s ease}.igf-heroes-carousel__profile{padding-top:24px;border-top:1px solid #ded4cb}.is-motion-enabled .igf-heroes-carousel__profile{animation:igf-heroes-profile-in .32s ease both}.igf-heroes-carousel__profile-heading{display:flex;align-items:flex-end;flex-wrap:wrap;gap:8px 18px}.igf-heroes-carousel__role{width:100%;margin:0;color:#a44906;font-size:12px;font-weight:850;letter-spacing:.11em;text-transform:uppercase}.igf-heroes-carousel__profile h2{margin:0;color:#202122;font:650 clamp(30px,3.2vw,44px)/1.08 'Literata',Georgia,serif;letter-spacing:-.035em}.igf-heroes-carousel__socials{display:flex;gap:8px;margin:0;padding:0;list-style:none}.igf-heroes-carousel__socials a{display:grid;width:44px;height:44px;place-items:center;border:1px solid #cdb6a3;border-radius:50%;background:#fff;color:#743300;text-decoration:none}.igf-heroes-carousel__socials a:hover{border-color:#ff7500;background:#ff7500;color:#202122}.igf-heroes-carousel__biography{display:grid;gap:12px;margin-top:19px;color:#514a45;font-size:16px;line-height:1.72}.igf-heroes-carousel__biography p{margin:0}.igf-heroes-carousel__qualification{margin:19px 0 0;padding:13px 16px;border-left:4px solid #ff7500;background:#fff4ea;color:#514a45;font-size:14px;line-height:1.55}.igf-heroes-carousel__empty{margin:20px 0 0;padding:24px;border:1px dashed #cfad91;border-radius:16px;background:#fff9f4;color:#5e554f}.sr-only{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;margin:-1px!important;padding:0!important;border:0!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important}@keyframes igf-heroes-profile-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}@media(max-width:600px){.igf-heroes-carousel__rail{gap:16px;margin-right:-20px;padding-right:20px}.igf-heroes-carousel__person{width:126px;min-width:126px}.igf-heroes-carousel__portrait{width:126px;height:126px}.igf-heroes-carousel__dots{display:none}.igf-heroes-carousel__navigation{justify-content:space-between}.igf-heroes-carousel__profile-heading{align-items:flex-start;flex-direction:column}.igf-heroes-carousel__profile h2{font-size:31px}}@media(prefers-reduced-motion:reduce){.igf-heroes-carousel *{scroll-behavior:auto!important;animation:none!important;transition:none!important}}@media(forced-colors:active){.igf-heroes-carousel__portrait,.igf-heroes-carousel__navigation>button,.igf-heroes-carousel__socials a{forced-color-adjust:auto}.igf-heroes-carousel__person.is-active .igf-heroes-carousel__portrait{outline:3px solid Highlight}}
.igf-heroes-carousel{position:relative}.igf-heroes-carousel__rail{gap:10px}.igf-heroes-carousel__person{box-sizing:border-box;width:196px;min-width:196px;padding:24px 12px;border:1px solid transparent;border-radius:8px}.igf-heroes-carousel__portrait{width:150px;height:150px}.igf-heroes-carousel__person.is-active{border-color:#ecd4c0;background:#f6e8dc;box-shadow:0 10px 15px rgba(74,45,24,.1)}.igf-heroes-carousel__person.is-active .igf-heroes-carousel__portrait{border-color:transparent;box-shadow:none}.igf-heroes-carousel.is-motion-enabled .igf-heroes-carousel__person{transition:border-color .5s ease,background-color .5s ease,box-shadow .5s ease}.igf-heroes-carousel.is-motion-enabled .igf-heroes-carousel__profile{animation-duration:.5s}.igf-heroes-carousel__navigation{position:absolute;width:1px;height:1px;overflow:hidden;margin:-1px;padding:0;clip:rect(0,0,0,0);white-space:nowrap}.igf-heroes-carousel__navigation:focus-within{position:static;width:auto;height:auto;overflow:visible;margin:10px 0 18px;padding:4px;clip:auto;white-space:normal}.igf-heroes-carousel__navigation:focus-within .igf-heroes-carousel__dots{display:flex}.igf-heroes-carousel__profile{padding-top:26px;border-top:0}.igf-heroes-carousel__profile-heading{align-items:center}.igf-heroes-carousel__role{color:#6a625c;font-size:13px;font-weight:600;letter-spacing:0;text-transform:none}.igf-heroes-carousel__profile h2{font:800 30px/1.12 'Hanken Grotesk',Arial,sans-serif;letter-spacing:-.025em}.igf-heroes-carousel__socials{gap:2px}.igf-heroes-carousel__socials a{width:44px;height:44px;border:0;background:transparent;color:#fff}.igf-heroes-carousel__socials a i{display:grid;width:30px;height:30px;place-items:center;border-radius:50%;background:#29231f;font-size:12px}.igf-heroes-carousel__socials a:hover{border:0;background:transparent;color:#fff}.igf-heroes-carousel__socials a:hover i{background:#a44906}.igf-heroes-carousel__biography{margin-top:17px}.igf-heroes-carousel__qualification{margin:16px 0 0;padding:0;border:0;background:transparent}@media(max-width:600px){.igf-heroes-carousel__person{width:146px;min-width:146px;padding:14px 7px}.igf-heroes-carousel__portrait{width:126px;height:126px}}
.igf-heroes-carousel__navigation{position:absolute;z-index:4;top:4px;right:4px;width:auto;height:auto;overflow:visible;margin:0;padding:0;clip:auto;white-space:normal}.igf-heroes-carousel__arrow,.igf-heroes-carousel__dots{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;margin:-1px!important;padding:0!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important}.igf-heroes-carousel__autoplay{display:grid!important;width:44px!important;height:44px!important;place-items:center;border:1px solid #cbb7a7!important;border-radius:50%;opacity:0;background:rgba(255,255,255,.94)!important;color:#713100!important;box-shadow:0 5px 15px rgba(74,45,24,.1);pointer-events:none;transition:opacity .2s ease}.igf-heroes-carousel:hover .igf-heroes-carousel__autoplay,.igf-heroes-carousel__navigation:focus-within .igf-heroes-carousel__autoplay{opacity:1;pointer-events:auto}.igf-heroes-carousel__navigation:focus-within{position:absolute;top:4px;right:4px;display:flex;width:auto;height:auto;overflow:visible;margin:0;padding:5px;clip:auto;border-radius:999px;background:rgba(255,255,255,.97);box-shadow:0 8px 22px rgba(55,35,21,.13);white-space:normal}.igf-heroes-carousel__navigation:focus-within .igf-heroes-carousel__arrow{position:static!important;width:44px!important;height:44px!important;overflow:visible!important;margin:0!important;padding:0!important;clip:auto!important;white-space:normal!important}.igf-heroes-carousel__navigation:focus-within .igf-heroes-carousel__dots{position:static!important;width:auto!important;height:auto!important;overflow:auto!important;margin:0!important;padding:0!important;clip:auto!important;white-space:normal!important}
.igf-heroes-carousel__rail{-ms-overflow-style:none;scrollbar-width:none}.igf-heroes-carousel__rail::-webkit-scrollbar{display:none}
.igf-heroes-carousel__person{min-height:266px;padding:24px 0}.igf-heroes-carousel__person.is-active{box-shadow:0 10px 15px -3px rgba(74,45,24,.1),0 4px 6px -4px rgba(74,45,24,.1)}@media(max-width:600px){.igf-heroes-carousel__person{min-height:0;padding:14px 0}}
.igf-heroes-carousel__profile{margin:0}
@media(min-width:981px){.igf-heroes-carousel__rail{padding:40px 0}}
@media(hover:none),(pointer:coarse){.igf-heroes-carousel__rail{touch-action:pan-y}.igf-heroes-carousel__navigation{position:static;display:flex;width:100%;height:auto;overflow:visible;align-items:center;justify-content:center;gap:6px;margin:10px 0 18px;padding:0;clip:auto;background:transparent;box-shadow:none;white-space:normal}.igf-heroes-carousel__navigation .igf-heroes-carousel__arrow{position:static!important;display:grid!important;width:44px!important;height:44px!important;overflow:visible!important;margin:0!important;padding:0!important;clip:auto!important;white-space:normal!important}.igf-heroes-carousel__navigation .igf-heroes-carousel__dots{position:static!important;display:flex!important;width:auto!important;max-width:calc(100% - 138px);height:auto!important;overflow-x:auto!important;margin:0!important;padding:0!important;clip:auto!important;white-space:normal!important}.igf-heroes-carousel__autoplay{position:static!important;opacity:1;pointer-events:auto}}
.igf-heroes-carousel{font-family:'Jost',Arial,sans-serif}.igf-heroes-carousel__profile h2{font-family:'Jost',Arial,sans-serif;font-weight:700;letter-spacing:normal}
.igf-heroes-carousel button,.igf-heroes-carousel p,.igf-heroes-carousel a{font-family:'Jost',Arial,sans-serif}.igf-heroes-carousel__person strong{margin-top:13px;font:400 16px/24px 'Jost',Arial,sans-serif}.igf-heroes-carousel__person small{margin-top:4px;font:400 14px/20px 'Jost',Arial,sans-serif}.igf-heroes-carousel__role{font:400 16px/24px 'Jost',Arial,sans-serif}.igf-heroes-carousel__profile h2{font:700 24px/32px 'Jost',Arial,sans-serif}.igf-heroes-carousel__socials{min-height:48px;align-items:center}.igf-heroes-carousel__biography{font:400 16px/24px 'Jost',Arial,sans-serif}
.igf-heroes-carousel__person{height:266px;min-height:266px;border:0}.igf-heroes-carousel__person strong{margin-top:0;padding-top:24px}.igf-heroes-carousel__person small{overflow:hidden;margin-top:0;text-overflow:ellipsis;white-space:nowrap}.igf-heroes-carousel__portrait,.igf-heroes-carousel__person:hover .igf-heroes-carousel__portrait,.igf-heroes-carousel__person.is-active .igf-heroes-carousel__portrait{box-sizing:border-box;width:150px;height:150px;border:0;box-shadow:none}.igf-heroes-carousel__portrait img{width:150px;height:150px;object-position:50% 50%}.igf-heroes-carousel.is-motion-enabled .igf-heroes-carousel__person:hover .igf-heroes-carousel__portrait{transform:none}@media(max-width:600px){.igf-heroes-carousel__person{height:auto;min-height:0}.igf-heroes-carousel__person strong{padding-top:12px}.igf-heroes-carousel__person small{white-space:normal}.igf-heroes-carousel__portrait,.igf-heroes-carousel__person:hover .igf-heroes-carousel__portrait,.igf-heroes-carousel__person.is-active .igf-heroes-carousel__portrait,.igf-heroes-carousel__portrait img{width:126px;height:126px}}
.igf-heroes-carousel__person{height:266px;min-height:266px}.igf-heroes-carousel__person strong,.igf-heroes-carousel__person small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.igf-heroes-carousel__profile{padding-top:0}.igf-heroes-carousel__profile h2::before,.igf-heroes-carousel__profile h2::after{display:none!important;content:none!important}.igf-heroes-carousel__biography p{font:inherit;line-height:24px}@media(max-width:600px){.igf-heroes-carousel__person{height:auto;min-height:0}.igf-heroes-carousel__person strong,.igf-heroes-carousel__person small{overflow:visible;text-overflow:clip;white-space:normal}}
</style>
