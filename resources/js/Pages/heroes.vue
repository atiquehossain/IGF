<template>
  <Layout>
    <div class="igf-heroes-page" :class="`is-${scope}`">
      <section v-if="scope === 'national'" class="igf-heroes-page__showcase" aria-labelledby="heroes-page-title">
        <div class="igf-heroes-page__shell igf-heroes-page__split">
          <div class="igf-heroes-page__people">
            <header class="igf-heroes-page__header">
              <p class="igf-heroes-page__eyebrow"><span aria-hidden="true"><i /><i /></span>{{ copy.national_eyebrow }}</p>
              <h1 id="heroes-page-title">
                <span v-if="headingParts(nationalTitle).prefix">{{ headingParts(nationalTitle).prefix }}</span>{{ ' ' }}<strong>{{ headingParts(nationalTitle).emphasis }}</strong>
              </h1>
              <p v-if="nationalIntroduction">{{ nationalIntroduction }}</p>
            </header>
            <HeroesProfileCarousel
              :members="members"
              :copy="carouselCopy"
              :autoplay="autoplay"
              :animation-enabled="animationEnabled"
              id-prefix="national-heroes"
            />
          </div>
          <aside class="igf-heroes-page__map-column">
            <HeroesRegionMap
              level="division"
              :regions="divisions"
              :locale="locale"
              :eyebrow="copy.map_eyebrow"
              :heading="copy.division_map_heading"
              :help="copy.division_map_help"
              :link-label-template="copy.division_link_label"
              :list-label="copy.division_list_label"
              :attribution-prefix="copy.map_attribution"
              minimal
              id-prefix="national-division-map"
            />
          </aside>
        </div>
      </section>

      <template v-else-if="scope === 'division'">
        <section class="igf-heroes-page__showcase igf-heroes-page__showcase--division" aria-labelledby="heroes-page-title">
          <div class="igf-heroes-page__shell igf-heroes-page__split">
            <div class="igf-heroes-page__people">
              <header class="igf-heroes-page__header">
                <p class="igf-heroes-page__eyebrow"><span aria-hidden="true"><i /><i /></span>{{ copy.division_eyebrow }}</p>
                <h1 id="heroes-page-title">
                  <component
                    :is="segment.emphasis ? 'strong' : 'span'"
                    v-for="(segment, index) in placeHeadingSegments(divisionTitle, divisionName)"
                    :key="`division-heading-${index}`"
                    :class="{ 'is-before-place': segment.before }"
                  >{{ segment.text }}</component>
                </h1>
                <p v-if="divisionIntroduction">{{ divisionIntroduction }}</p>
              </header>
              <HeroesProfileCarousel
                :members="members"
                :copy="carouselCopy"
                :autoplay="autoplay"
                :animation-enabled="animationEnabled"
                :id-prefix="`${divisionSlug || 'division'}-heroes`"
              />
            </div>
            <aside class="igf-heroes-page__map-column">
              <HeroesRegionMap
                level="district"
                :parent-slug="divisionSlug"
                :regions="districts"
                :locale="locale"
                :eyebrow="copy.district_map_eyebrow"
                :heading="interpolate(copy.district_map_heading, { division: divisionName })"
                :help="copy.district_map_help"
                :link-label-template="copy.district_link_label"
                :list-label="copy.district_list_label"
                :attribution-prefix="copy.map_attribution"
                minimal
                :id-prefix="`${divisionSlug || 'division'}-district-map`"
              />
            </aside>
          </div>
        </section>

        <section v-if="aboutParagraphs.length" class="igf-heroes-page__about" aria-labelledby="heroes-about-title">
          <div class="igf-heroes-page__shell igf-heroes-page__about-grid">
            <header>
              <h2 id="heroes-about-title">
                <component
                  :is="segment.emphasis ? 'strong' : 'span'"
                  v-for="(segment, index) in placeHeadingSegments(aboutTitle, divisionName)"
                  :key="`about-heading-${index}`"
                >{{ segment.text }}</component>
              </h2>
            </header>
            <div class="igf-heroes-page__narrative">
              <p v-for="(paragraph, index) in aboutParagraphs" :key="`division-about-${index}`">{{ paragraph }}</p>
            </div>
          </div>
        </section>

        <ActivitiesSection :activities="activities" :copy="activityCopy" :locale="locale" :place="divisionName" />
      </template>

      <template v-else>
        <section class="igf-heroes-page__district-hero" :class="{ 'has-about': districtAboutParagraphs.length }" aria-labelledby="heroes-page-title">
          <div class="igf-heroes-page__shell">
            <header class="igf-heroes-page__district-heading">
              <p class="igf-heroes-page__eyebrow"><span aria-hidden="true"><i /><i /></span>{{ copy.district_eyebrow }}</p>
              <h1 id="heroes-page-title">
                <component
                  :is="segment.emphasis ? 'strong' : 'span'"
                  v-for="(segment, index) in placeHeadingSegments(districtTitle, districtName)"
                  :key="`district-heading-${index}`"
                  :class="{ 'is-before-place': segment.before }"
                >{{ segment.text }}</component>
              </h1>
              <p v-if="districtIntroduction">{{ districtIntroduction }}</p>
            </header>
            <figure class="igf-heroes-page__group-figure">
              <img
                v-if="districtGroupImage && !groupImageFailed"
                :src="districtGroupImage"
                :alt="districtGroupImageAlt"
                width="1280"
                height="720"
                decoding="async"
                @error="groupImageFailed = true"
              >
              <div v-else class="igf-heroes-page__group-fallback" role="img" :aria-label="copy.group_image_fallback_label">
                <i class="fa-solid fa-people-group" aria-hidden="true" />
                <strong>{{ districtName }}</strong>
                <span>{{ copy.group_image_fallback }}</span>
              </div>
              <figcaption v-if="districtGroupCaption">{{ districtGroupCaption }}</figcaption>
            </figure>
          </div>
        </section>

        <section v-if="districtAboutParagraphs.length" class="igf-heroes-page__about igf-heroes-page__about--district" aria-labelledby="heroes-about-title">
          <div class="igf-heroes-page__shell igf-heroes-page__about-grid">
            <header>
              <h2 id="heroes-about-title">
                <component
                  :is="segment.emphasis ? 'strong' : 'span'"
                  v-for="(segment, index) in placeHeadingSegments(districtAboutTitle, districtName)"
                  :key="`district-about-heading-${index}`"
                >{{ segment.text }}</component>
              </h2>
            </header>
            <div class="igf-heroes-page__narrative">
              <p v-for="(paragraph, index) in districtAboutParagraphs" :key="`district-about-${index}`">{{ paragraph }}</p>
            </div>
          </div>
        </section>

        <ActivitiesSection :activities="activities" :copy="activityCopy" :locale="locale" :place="districtName" />
      </template>
    </div>
  </Layout>
</template>

<script setup>
import { computed, defineComponent, h, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
import HeroesProfileCarousel from './Heroes/HeroesProfileCarousel.vue';
import HeroesRegionMap from './Heroes/HeroesRegionMap.vue';

const page = usePage();
const groupImageFailed = ref(false);

const defaults = Object.freeze({
  en: Object.freeze({
    national_eyebrow: 'Heroes',
    national_title: 'Meet the Heroes',
    national_introduction: 'Meet the people turning local knowledge, care, and courage into lasting change.',
    division_eyebrow: 'Team',
    division_title: 'Meet the Heroes from {division} Division',
    division_introduction: 'Meet the people creating change across {division}.',
    district_eyebrow: 'Team',
    district_title: 'Meet the Heroes from {district} District',
    district_introduction: '',
    map_eyebrow: 'Explore by place',
    division_map_heading: 'Explore heroes by division',
    division_map_help: 'Choose a division on the map to meet its team and explore local activities.',
    division_link_label: 'Explore heroes in {name} Division',
    division_list_label: 'Bangladesh divisions',
    district_map_eyebrow: 'Explore the division',
    district_map_heading: 'Districts in {division}',
    district_map_help: 'Choose a district to see its heroes and community activities.',
    district_link_label: 'Explore heroes in {name} District',
    district_list_label: 'Districts shown on the map',
    map_attribution: 'Boundary data:',
    all_bangladesh: 'All Bangladesh heroes',
    back_to_division: 'Back to {division}',
    about_eyebrow: 'About the division',
    about_title: 'About {division}',
    activities_eyebrow: 'Community in action',
    activities_title: 'Activities at {place}',
    activities_empty: 'No published activities are available for this area yet.',
    activity_label: 'Activity',
    activity_read_more: 'Read activity',
    activity_image_fallback: 'Activity image unavailable',
    group_image_fallback: 'A group portrait will appear here when it is published.',
    group_image_fallback_label: 'Group portrait placeholder',
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
    empty_people: 'No published heroes are available for this area yet.',
    selected_person: 'Selected profile: {name}, {current} of {total}.',
  }),
  bn: Object.freeze({
    national_eyebrow: 'হিরো',
    national_title: 'পরিবর্তনের নায়কদের সঙ্গে পরিচিত হোন',
    national_introduction: 'স্থানীয় জ্ঞান, যত্ন ও সাহস দিয়ে যারা দীর্ঘস্থায়ী পরিবর্তন আনছেন, তাঁদের জানুন।',
    division_eyebrow: 'টিম',
    division_title: '{division} বিভাগের নায়কেরা',
    division_introduction: '{division} জুড়ে পরিবর্তন আনছেন এমন মানুষদের জানুন।',
    district_eyebrow: 'টিম',
    district_title: '{district} জেলার নায়কেরা',
    district_introduction: '',
    map_eyebrow: 'স্থান অনুযায়ী খুঁজুন',
    division_map_heading: 'বিভাগ অনুযায়ী নায়কদের দেখুন',
    division_map_help: 'মানচিত্রে একটি বিভাগ বেছে নিয়ে সেই এলাকার দল ও স্থানীয় কার্যক্রম দেখুন।',
    division_link_label: '{name} বিভাগের নায়কদের দেখুন',
    division_list_label: 'বাংলাদেশের বিভাগসমূহ',
    district_map_eyebrow: 'বিভাগটি ঘুরে দেখুন',
    district_map_heading: '{division} বিভাগের জেলাসমূহ',
    district_map_help: 'সেখানকার নায়ক ও কমিউনিটি কার্যক্রম দেখতে একটি জেলা বেছে নিন।',
    district_link_label: '{name} জেলার নায়কদের দেখুন',
    district_list_label: 'মানচিত্রে দেখানো জেলাসমূহ',
    map_attribution: 'সীমানার তথ্য:',
    all_bangladesh: 'সারা বাংলাদেশের নায়কেরা',
    back_to_division: '{division} বিভাগে ফিরে যান',
    about_eyebrow: 'বিভাগ সম্পর্কে',
    about_title: '{division} সম্পর্কে',
    activities_eyebrow: 'কমিউনিটির কার্যক্রম',
    activities_title: '{place}-এর কার্যক্রম',
    activities_empty: 'এই এলাকার কোনো প্রকাশিত কার্যক্রম এখনো নেই।',
    activity_label: 'কার্যক্রম',
    activity_read_more: 'কার্যক্রম পড়ুন',
    activity_image_fallback: 'কার্যক্রমের ছবি পাওয়া যায়নি',
    group_image_fallback: 'প্রকাশিত হলে দলের ছবি এখানে দেখা যাবে।',
    group_image_fallback_label: 'দলের ছবির স্থানধারক',
    carousel_label: 'নায়কদের প্রোফাইল ক্যারোসেল',
    people_label: 'একজন নায়ক বেছে নিন',
    navigation_label: 'নায়কের প্রোফাইল নিয়ন্ত্রণ',
    person_picker_label: 'একটি নায়কের প্রোফাইল বেছে নিন',
    previous_person: 'আগের নায়ক দেখুন',
    next_person: 'পরের নায়ক দেখুন',
    show_person: '{name} দেখুন, {total}টির মধ্যে {current} নম্বর প্রোফাইল',
    pause: 'স্বয়ংক্রিয় প্রোফাইল পরিবর্তন থামান',
    play: 'স্বয়ংক্রিয় প্রোফাইল পরিবর্তন চালু করুন',
    social_links: 'সামাজিক যোগাযোগের লিংক',
    opens_new_tab: 'নতুন ট্যাবে খুলবে',
    qualification: 'যোগ্যতা',
    empty_people: 'এই এলাকার কোনো প্রকাশিত নায়ক এখনো নেই।',
    selected_person: 'নির্বাচিত প্রোফাইল: {name}, {total}টির মধ্যে {current}।',
  }),
});

const data = computed(() => page.props.data || {});
const locale = computed(() => String(page.props.locale || 'en').toLowerCase().startsWith('bn') ? 'bn' : 'en');
const scope = computed(() => ['division', 'district'].includes(String(page.props.scope || data.value.scope || '').toLowerCase())
  ? String(page.props.scope || data.value.scope).toLowerCase()
  : 'national');
const presentation = computed(() => {
  const localized = source => source && typeof source?.[locale.value] === 'object' ? source[locale.value] : (source || {});
  return {
    ...localized(data.value.presentation),
    ...localized(data.value.copy),
    ...localized(page.props.presentation),
  };
});
const copy = computed(() => {
  const source = presentation.value || {};
  const normalized = { ...defaults[locale.value], ...source };
  if (source.eyebrow) normalized[`${scope.value}_eyebrow`] = source.eyebrow;
  if (source.introduction) normalized[`${scope.value}_introduction`] = source.introduction;
  if (source.map_heading) normalized[scope.value === 'division' ? 'district_map_heading' : 'division_map_heading'] = source.map_heading;
  if (source.map_help) normalized[scope.value === 'division' ? 'district_map_help' : 'division_map_help'] = source.map_help;
  if (source.about_heading) normalized.about_title = source.about_heading;
  if (source.activities_heading) normalized.activities_title = source.activities_heading;
  if (source.activities_empty) normalized.activities_empty = source.activities_empty;
  return normalized;
});
const members = computed(() => Array.isArray(data.value.members) ? data.value.members : []);
const divisions = computed(() => Array.isArray(data.value.divisions) ? data.value.divisions : []);
const districts = computed(() => Array.isArray(data.value.districts) ? data.value.districts : []);
const activities = computed(() => Array.isArray(data.value.activities) ? data.value.activities : []);
const currentDivision = computed(() => data.value.current_division || {});
const currentDistrict = computed(() => data.value.current_district || {});
const divisionSlug = computed(() => normalizedSlug(currentDivision.value.slug || currentDistrict.value.division_slug || currentDistrict.value.division?.slug || data.value.division_slug));
const divisionName = computed(() => localizedValue(currentDivision.value.label || currentDivision.value.name || currentDistrict.value.division_label || currentDistrict.value.division_name || currentDistrict.value.division?.name) || titleCase(divisionSlug.value));
const districtName = computed(() => localizedValue(currentDistrict.value.label || currentDistrict.value.name) || titleCase(currentDistrict.value.slug));
const nationalTitle = computed(() => localizedValue(presentation.value.title || presentation.value.heading) || String(page.props.title || copy.value.national_title));
const nationalIntroduction = computed(() => Object.prototype.hasOwnProperty.call(presentation.value, 'introduction')
  ? localizedValue(presentation.value.introduction)
  : localizedValue(data.value.introduction));
const divisionTitle = computed(() => localizedValue(presentation.value.title || presentation.value.heading)
  || localizedValue(currentDivision.value.title)
  || String(page.props.title || interpolate(copy.value.division_title, { division: divisionName.value })));
const districtTitle = computed(() => localizedValue(presentation.value.title || presentation.value.heading)
  || localizedValue(currentDistrict.value.title)
  || String(page.props.title || interpolate(copy.value.district_title, { district: districtName.value })));
const divisionIntroduction = computed(() => Object.prototype.hasOwnProperty.call(presentation.value, 'introduction')
  ? localizedValue(presentation.value.introduction)
  : (localizedValue(currentDivision.value.introduction) || interpolate(copy.value.division_introduction, { division: divisionName.value })));
const districtIntroduction = computed(() => Object.prototype.hasOwnProperty.call(presentation.value, 'introduction')
  ? localizedValue(presentation.value.introduction)
  : (localizedValue(currentDistrict.value.introduction) || copy.value.district_introduction));
const aboutTitle = computed(() => localizedValue(presentation.value.about_heading)
  || localizedValue(currentDivision.value.about_title)
  || localizedValue(data.value.about_title)
  || interpolate(copy.value.about_title, { division: divisionName.value }));
const aboutParagraphs = computed(() => normalizeParagraphs(presentation.value.about_body
  || currentDivision.value.about_paragraphs
  || currentDivision.value.about
  || currentDivision.value.description
  || data.value.about_paragraphs
  || data.value.about));
const districtAboutTitle = computed(() => localizedValue(presentation.value.about_heading)
  || interpolate(copy.value.about_title, { division: districtName.value }));
const districtAboutParagraphs = computed(() => normalizeParagraphs(presentation.value.about_body
  || currentDistrict.value.description));
const settings = computed(() => data.value.settings || {});
const autoplay = computed(() => {
  const requested = settings.value.autoplay ?? presentation.value.autoplay;
  return requested === undefined || requested === null || requested === ''
    ? true
    : [true, 1, '1', 'true'].includes(requested);
});
const animationEnabled = computed(() => ![false, 0, '0', 'false'].includes(settings.value.animation_enabled ?? presentation.value.animation_enabled));
const carouselCopy = computed(() => Object.fromEntries(Object.keys(defaults.en).filter(key => [
  'carousel_label', 'people_label', 'navigation_label', 'person_picker_label', 'previous_person', 'next_person', 'show_person',
  'pause', 'play', 'social_links', 'opens_new_tab', 'qualification', 'empty_people', 'selected_person',
].includes(key)).map(key => [key, copy.value[key]])));
const activityCopy = computed(() => ({
  eyebrow: copy.value.activities_eyebrow,
  title: copy.value.activities_title,
  empty: copy.value.activities_empty,
  label: copy.value.activity_label,
  read_more: copy.value.activity_read_more,
  image_fallback: copy.value.activity_image_fallback,
}));
const districtGroupImage = computed(() => safeMediaUrl(currentDistrict.value.group_image_url
  || currentDistrict.value.group_image
  || currentDistrict.value.hero_image_url
  || currentDistrict.value.hero_image
  || currentDistrict.value.image_url
  || currentDistrict.value.image
  || data.value.group_image));
const districtGroupImageAlt = computed(() => localizedValue(currentDistrict.value.group_image_alt)
  || localizedValue(currentDistrict.value.hero_image_alt)
  || localizedValue(currentDistrict.value.image_alt)
  || interpolate(copy.value.district_title, { district: districtName.value }));
const districtGroupCaption = computed(() => localizedValue(currentDistrict.value.group_image_caption || currentDistrict.value.image_caption));

function localizedValue(value) {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    return String(value[locale.value] || value.en || value.bn || '').trim();
  }
  return String(value || '').trim();
}
function interpolate(value, replacements) {
  return Object.entries(replacements).reduce((result, [key, replacement]) => result.replaceAll(`{${key}}`, String(replacement || '')), String(value || ''));
}
function normalizedSlug(value) {
  return String(value || '').trim().toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}
function titleCase(value) {
  return String(value || '').split('-').filter(Boolean).map(part => `${part.charAt(0).toUpperCase()}${part.slice(1)}`).join(' ');
}
function headingParts(value) {
  const words = String(value || '').trim().split(/\s+/).filter(Boolean);
  return { prefix: words.slice(0, -1).join(' '), emphasis: words.at(-1) || '' };
}
function placeHeadingSegments(value, place) {
  const heading = String(value || '');
  const location = String(place || '').trim();
  const index = location ? heading.toLocaleLowerCase().indexOf(location.toLocaleLowerCase()) : -1;
  if (index >= 0) {
    return [
      { text: heading.slice(0, index), emphasis: false, before: true },
      { text: heading.slice(index, index + location.length), emphasis: true, before: false },
      { text: heading.slice(index + location.length), emphasis: false, before: false },
    ].filter(segment => segment.text);
  }
  const parts = headingParts(heading);
  return [
    { text: parts.prefix ? `${parts.prefix} ` : '', emphasis: false, before: true },
    { text: parts.emphasis, emphasis: true, before: false },
  ].filter(segment => segment.text);
}
function normalizeParagraphs(value) {
  if (Array.isArray(value)) return value.map(localizedValue).filter(Boolean);
  return localizedValue(value).split(/\n\s*\n+/).map(paragraph => paragraph.trim()).filter(Boolean);
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

const ActivitiesSection = defineComponent({
  name: 'ActivitiesSection',
  props: {
    activities: { type: Array, default: () => [] },
    copy: { type: Object, required: true },
    locale: { type: String, default: 'en' },
    place: { type: String, default: '' },
  },
  setup(props) {
    const failed = ref(new Set());
    const sectionId = computed(() => `heroes-activities-${normalizedSlug(props.place) || 'place'}`);
    const normalized = computed(() => props.activities.map((activity, index) => ({
      key: String(activity?.id ?? activity?.uuid ?? activity?.slug ?? index),
      title: localizedActivityValue(activity?.title || activity?.name),
      excerpt: localizedActivityValue(activity?.excerpt || activity?.summary || activity?.description),
      eyebrow: localizedActivityValue(activity?.category?.name || activity?.type) || props.copy.label,
      image: safeMediaUrl(activity?.image_url || activity?.image_path || activity?.cover_image_path || activity?.image),
      imageAlt: localizedActivityValue(activity?.image_alt),
      url: safeContentLink(activity?.url),
      external: /^https?:\/\//i.test(safeContentLink(activity?.url)),
      date: formatDate(activity?.published_at || activity?.date),
      dateTime: String(activity?.published_at || activity?.date || ''),
    })).filter(activity => activity.title));
    function localizedActivityValue(value) {
      if (value && typeof value === 'object' && !Array.isArray(value)) return String(value[props.locale] || value.en || value.bn || '').trim();
      return String(value || '').trim();
    }
    function safeContentLink(value) {
      const url = String(value || '').trim();
      if (url.startsWith('/') && !url.startsWith('//')) return url;
      try {
        const parsed = new URL(url);
        if (parsed.pathname.startsWith('/event/')) return `${parsed.pathname}${parsed.search}${parsed.hash}`;
        return ['http:', 'https:'].includes(parsed.protocol) ? url : '';
      } catch {
        return '';
      }
    }
    function formatDate(value) {
      if (!value) return '';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return '';
      if (props.locale === 'bn') {
        return new Intl.DateTimeFormat('bn-BD', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(date);
      }
      const day = date.getUTCDate();
      const suffix = day % 100 >= 11 && day % 100 <= 13 ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[day % 10] || 'th');
      const month = new Intl.DateTimeFormat('en-GB', { month: 'short', timeZone: 'UTC' }).format(date);
      return `${month} ${day}${suffix} ${date.getUTCFullYear()}`;
    }
    function markFailed(key) {
      failed.value = new Set([...failed.value, key]);
    }
    return () => h('section', { class: 'igf-heroes-activities', 'aria-labelledby': sectionId.value }, [
      h('div', { class: 'igf-heroes-page__shell' }, [
        h('header', { class: 'igf-heroes-activities__header' }, [
          h('h2', { id: sectionId.value }, placeHeadingSegments(interpolate(props.copy.title, { place: props.place }), props.place)
            .map((segment, index) => h(segment.emphasis ? 'strong' : 'span', { key: `activity-heading-${index}` }, segment.text))),
        ]),
        normalized.value.length
          ? h('div', { class: 'igf-heroes-activities__grid' }, normalized.value.map(activity => {
            const tag = activity.url ? 'a' : 'article';
            return h(tag, {
              key: activity.key,
              class: 'igf-heroes-activities__card',
              href: activity.url || null,
              target: activity.external ? '_blank' : null,
              rel: activity.external ? 'noopener noreferrer' : null,
            }, [
              h('div', { class: 'igf-heroes-activities__media' }, [
                activity.image && !failed.value.has(activity.key)
                  ? h('img', { src: activity.image, alt: activity.imageAlt || '', loading: 'lazy', decoding: 'async', width: '640', height: '400', onError: () => markFailed(activity.key) })
                  : h('span', { role: 'img', 'aria-label': props.copy.image_fallback }, [h('i', { class: 'fa-solid fa-hands-holding-circle', 'aria-hidden': 'true' })]),
              ]),
              h('div', { class: 'igf-heroes-activities__copy' }, [
                activity.date ? h('time', { class: 'igf-heroes-activities__date', datetime: activity.dateTime }, activity.date) : null,
                h('h3', activity.title),
              ]),
            ]);
          }))
          : h('p', { class: 'igf-heroes-activities__empty', role: 'status' }, props.copy.empty),
      ]),
    ]);
  },
});
</script>

<style scoped>
.igf-heroes-page{--orange:#ff7500;--brown:#a44906;--ink:#202122;--muted:#5d5650;--cream:#f5efe8;--line:#dfd2c7;color:var(--ink);background:#fff;font-family:'Hanken Grotesk',Arial,sans-serif}.igf-heroes-page__shell{width:min(100% - 48px,1240px);margin:0 auto}.igf-heroes-page__showcase{overflow:hidden;padding:clamp(86px,9vw,130px) 0;background:linear-gradient(135deg,#fff 0%,#fffaf5 52%,#f2e6da 100%)}.igf-heroes-page__showcase--division{background:#fffaf5}.igf-heroes-page__split{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);align-items:start;gap:clamp(56px,8vw,110px)}.igf-heroes-page__people,.igf-heroes-page__map-column{min-width:0}.igf-heroes-page__header{margin-bottom:clamp(34px,5vw,54px)}.igf-heroes-page__eyebrow{display:flex;align-items:center;gap:11px;margin:0 0 17px;color:var(--brown);font-size:12px;font-weight:850;letter-spacing:.12em;text-transform:uppercase}.igf-heroes-page__eyebrow>span{display:inline-flex;align-items:center;gap:4px}.igf-heroes-page__eyebrow>span i:first-child{display:block;width:25px;height:2px;background:var(--orange)}.igf-heroes-page__eyebrow>span i:last-child{display:block;width:7px;height:7px;border-radius:50%;background:var(--orange)}.igf-heroes-page__header h1,.igf-heroes-page__district-heading h1{max-width:720px;margin:0;color:var(--ink);font:650 clamp(46px,5.5vw,72px)/1.01 'Literata',Georgia,serif;letter-spacing:-.055em}.igf-heroes-page__header h1 strong{color:var(--brown);font-weight:650}.igf-heroes-page__header>p:last-child,.igf-heroes-page__district-heading>p:last-child{max-width:690px;margin:23px 0 0;color:var(--muted);font-size:18px;line-height:1.65}.igf-heroes-page__map-column{padding-top:8px}.igf-heroes-page__back-link{display:inline-flex;min-height:44px;align-items:center;gap:9px;margin-bottom:28px;padding:8px 0;color:#713100;font-size:13px;font-weight:850;text-decoration:none}.igf-heroes-page__back-link:hover{text-decoration:underline;text-underline-offset:5px}.igf-heroes-page__back-link:focus-visible{outline:3px solid #1769aa;outline-offset:4px}.igf-heroes-page__about{padding:clamp(80px,9vw,124px) 0;background:#202122;color:#fff}.igf-heroes-page__about-grid{display:grid;grid-template-columns:minmax(250px,.72fr) minmax(0,1.28fr);gap:clamp(42px,8vw,110px)}.igf-heroes-page__about .igf-heroes-page__eyebrow{color:#ffad72}.igf-heroes-page__about h2{margin:0;color:#fff;font:650 clamp(38px,4.8vw,60px)/1.08 'Literata',Georgia,serif;letter-spacing:-.045em}.igf-heroes-page__narrative{display:grid;gap:20px;color:#e0ddd9;font-size:18px;line-height:1.78}.igf-heroes-page__narrative p{margin:0}.igf-heroes-page__district-hero{padding:clamp(72px,8vw,112px) 0 clamp(80px,9vw,126px);background:linear-gradient(180deg,#fffaf5,#fff)}.igf-heroes-page__district-heading{max-width:1020px;margin-bottom:clamp(40px,6vw,66px)}.igf-heroes-page__district-heading h1{max-width:1000px}.igf-heroes-page__group-figure{margin:0}.igf-heroes-page__group-figure img,.igf-heroes-page__group-fallback{display:block;width:100%;min-height:clamp(310px,48vw,620px);max-height:680px;border-radius:24px;background:#ead9ca;box-shadow:0 24px 62px rgba(55,35,21,.16);object-fit:cover}.igf-heroes-page__group-fallback{display:flex;align-items:center;justify-content:center;padding:48px;flex-direction:column;background:radial-gradient(circle at 75% 25%,rgba(255,117,0,.22),transparent 26%),linear-gradient(135deg,#352b24,#1f2021);color:#fff;text-align:center}.igf-heroes-page__group-fallback>i{display:grid;width:90px;height:90px;place-items:center;margin-bottom:22px;border-radius:50%;background:var(--orange);color:#202122;font-size:36px}.igf-heroes-page__group-fallback strong{font:650 clamp(32px,5vw,58px)/1.08 'Literata',Georgia,serif}.igf-heroes-page__group-fallback span{margin-top:12px;color:#dedbd8}.igf-heroes-page__group-figure figcaption{margin-top:12px;color:#6d655f;font-size:13px;text-align:center}:deep(.igf-heroes-activities){padding:clamp(80px,9vw,124px) 0;background:#f5efe8}:deep(.igf-heroes-activities__header){max-width:780px;margin-bottom:42px}:deep(.igf-heroes-activities__header h2){margin:0;color:#202122;font:650 clamp(38px,4.8vw,58px)/1.08 'Literata',Georgia,serif;letter-spacing:-.045em}:deep(.igf-heroes-activities__grid){display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}:deep(.igf-heroes-activities__card){display:flex;min-width:0;overflow:hidden;flex-direction:column;border:1px solid #dfd2c7;border-radius:20px;background:#fff;box-shadow:0 14px 38px rgba(62,39,24,.08);color:#202122;text-decoration:none}:deep(a.igf-heroes-activities__card:hover){border-color:#ff7500;box-shadow:0 20px 48px rgba(62,39,24,.14);transform:translateY(-3px)}:deep(a.igf-heroes-activities__card:focus-visible){outline:3px solid #1769aa;outline-offset:5px}:deep(.igf-heroes-activities__media){height:220px;overflow:hidden;background:#e9ddd2}:deep(.igf-heroes-activities__media img){width:100%;height:100%;object-fit:cover}:deep(.igf-heroes-activities__media>span){display:grid;width:100%;height:100%;place-items:center;color:#9b4a10;font-size:42px}:deep(.igf-heroes-activities__copy){display:flex;min-height:250px;padding:26px;flex-direction:column}:deep(.igf-heroes-activities__eyebrow){margin:0 0 10px;color:#a44906;font-size:11px;font-weight:850;letter-spacing:.09em;text-transform:uppercase}:deep(.igf-heroes-activities__copy h3){margin:0;color:#202122;font:650 25px/1.17 'Literata',Georgia,serif}:deep(.igf-heroes-activities__copy>p:not(.igf-heroes-activities__eyebrow)){margin:14px 0 0;color:#615952;font-size:14px;line-height:1.62}:deep(.igf-heroes-activities__link){display:flex;align-items:center;gap:9px;margin-top:auto;padding-top:22px;color:#713100;font-size:13px;font-weight:850}:deep(.igf-heroes-activities__empty){margin:0;padding:30px;border:1px dashed #c8a98f;border-radius:16px;background:#fff9f4;color:#5f554d}:deep(a.igf-heroes-activities__card){transition:border-color .2s ease,box-shadow .2s ease,transform .2s ease}@media(max-width:980px){.igf-heroes-page__split{grid-template-columns:1fr;gap:70px}.igf-heroes-page__map-column{width:min(100%,680px);padding-top:0}.igf-heroes-page__about-grid{grid-template-columns:1fr;gap:32px}:deep(.igf-heroes-activities__grid){grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.igf-heroes-page__shell{width:min(100% - 40px,1240px)}.igf-heroes-page__showcase{padding:68px 0 78px}.igf-heroes-page__split{gap:58px}.igf-heroes-page__header h1,.igf-heroes-page__district-heading h1{font-size:clamp(39px,12vw,52px)}.igf-heroes-page__header>p:last-child,.igf-heroes-page__district-heading>p:last-child{font-size:16px}.igf-heroes-page__about,.igf-heroes-page__district-hero,:deep(.igf-heroes-activities){padding-top:68px;padding-bottom:74px}.igf-heroes-page__narrative{font-size:16px}.igf-heroes-page__group-figure img,.igf-heroes-page__group-fallback{min-height:280px;border-radius:16px}.igf-heroes-page__group-fallback{padding:28px 20px}:deep(.igf-heroes-activities__grid){grid-template-columns:1fr}:deep(.igf-heroes-activities__media){height:210px}:deep(.igf-heroes-activities__copy){min-height:220px;padding:23px}}@media(prefers-reduced-motion:reduce){.igf-heroes-page *{scroll-behavior:auto!important;animation:none!important;transition:none!important}}@media(forced-colors:active){.igf-heroes-page__eyebrow>span{forced-color-adjust:auto}.igf-heroes-page__group-fallback{border:2px solid CanvasText;background:Canvas;color:CanvasText}}
.igf-heroes-page__eyebrow>span i:first-child,.igf-heroes-page__eyebrow>span i:last-child{display:block;width:7px;height:7px;border-radius:50%;background:var(--orange)}.igf-heroes-page__about{padding:clamp(82px,9vw,122px) 0;background:#fff;color:var(--ink)}.igf-heroes-page__about-grid{display:block}.igf-heroes-page__about h2{margin:0;color:var(--ink);font:450 clamp(42px,5.2vw,66px)/1.06 'Literata',Georgia,serif;letter-spacing:-.05em}.igf-heroes-page__about h2 strong{color:var(--brown);font-weight:750}.igf-heroes-page__narrative{display:block;max-width:1100px;margin-top:34px;color:var(--muted);font-size:18px;line-height:1.75}.igf-heroes-page__narrative p{margin:0 0 18px}.igf-heroes-page__district-hero{padding:clamp(72px,8vw,108px) 0 clamp(80px,9vw,118px);background:#fff}.igf-heroes-page__district-heading{margin-bottom:clamp(38px,5vw,58px)}.igf-heroes-page__district-heading h1{font-weight:450}.igf-heroes-page__district-heading h1 strong{color:var(--brown);font-weight:750}.igf-heroes-page__district-heading h1 .is-before-place{display:block}.igf-heroes-page__group-figure{width:min(70%,900px);margin-right:auto;margin-left:auto}.igf-heroes-page__group-figure img,.igf-heroes-page__group-fallback{min-height:0;aspect-ratio:2 / 1;border-radius:0;box-shadow:none}.igf-heroes-page__group-fallback{border:1px solid #ddcaba;background:#f5efe8;color:var(--ink)}.igf-heroes-page__group-fallback span{color:var(--muted)}:deep(.igf-heroes-activities){background:#f7f3ef}:deep(.igf-heroes-activities__header){max-width:980px;margin-bottom:38px}:deep(.igf-heroes-activities__header h2){font-weight:450}:deep(.igf-heroes-activities__header h2 strong){color:var(--brown);font-weight:750}:deep(.igf-heroes-activities__grid){grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}:deep(.igf-heroes-activities__card){border-radius:8px;box-shadow:0 8px 24px rgba(62,39,24,.07)}:deep(.igf-heroes-activities__media){height:auto;aspect-ratio:1.45 / 1}:deep(.igf-heroes-activities__copy){min-height:150px;padding:18px}:deep(.igf-heroes-activities__date){align-self:flex-start;margin:0 0 14px;padding:6px 11px;border-radius:999px;background:#fff0e4;color:#8b3d05;font-size:11px;font-weight:850;line-height:1.2}:deep(.igf-heroes-activities__copy h3){font-size:clamp(21px,2vw,27px);line-height:1.13}@media(max-width:980px){:deep(.igf-heroes-activities__grid){grid-template-columns:repeat(2,minmax(0,1fr))}.igf-heroes-page__group-figure{width:min(86%,900px)}}@media(max-width:600px){.igf-heroes-page__group-figure{width:100%}:deep(.igf-heroes-activities__grid){grid-template-columns:1fr}:deep(.igf-heroes-activities__copy){min-height:138px}}
.igf-heroes-page__shell{width:min(100% - 48px,1360px)}.igf-heroes-page__split{gap:clamp(50px,5.5vw,80px)}@media(max-width:600px){.igf-heroes-page__shell{width:min(100% - 40px,1360px)}}
:deep(.igf-heroes-activities__header),:deep(.igf-heroes-activities__grid){width:min(100%,1200px);margin-right:auto;margin-left:auto}
.igf-heroes-page__header h1,.igf-heroes-page__district-heading h1{font-size:clamp(40px,4vw,48px);line-height:1;letter-spacing:-.045em}.igf-heroes-page__header h1{font-weight:450}.igf-heroes-page__header h1 strong{font-weight:750}@media(max-width:600px){.igf-heroes-page__header h1,.igf-heroes-page__district-heading h1{font-size:clamp(36px,9.5vw,44px)}}
@media(max-width:600px){.igf-heroes-page__group-fallback{padding:12px}.igf-heroes-page__group-fallback>i{width:46px;height:46px;flex:0 0 46px;margin-bottom:8px;font-size:20px}.igf-heroes-page__group-fallback strong{font-size:28px}.igf-heroes-page__group-fallback span{margin-top:6px;font-size:13px;line-height:1.35}}
.igf-heroes-page__group-figure{width:min(73%,896px)}.igf-heroes-page__group-figure img{height:auto}.igf-heroes-page__group-figure img,.igf-heroes-page__group-fallback{aspect-ratio:16 / 9}@media(max-width:980px){.igf-heroes-page__group-figure{width:min(86%,896px)}}@media(max-width:600px){.igf-heroes-page__group-figure{width:100%}}
.igf-heroes-page__header{margin-bottom:0}.igf-heroes-page__eyebrow{gap:10px;margin:0;color:var(--brown);font-size:20px;font-weight:400;line-height:28px;letter-spacing:0;text-transform:none}.igf-heroes-page__eyebrow>span{gap:5px}.igf-heroes-page__eyebrow>span i:first-child,.igf-heroes-page__eyebrow>span i:last-child{box-sizing:border-box;width:7px;height:7px;border:2px solid var(--orange);background:transparent}@media(min-width:981px){.igf-heroes-page__shell{width:min(calc(100% - 32px),1360px)}.igf-heroes-page__showcase{padding:80px 0 40px}.igf-heroes-page__split{gap:16px}.igf-heroes-page__map-column{padding-top:0}.igf-heroes-page__map-column :deep(.igf-heroes-map__stage){margin:0}.igf-heroes-page__showcase--division :deep(.igf-heroes-map.is-district .igf-heroes-map__stage){width:min(100%,600px);height:600px;aspect-ratio:1}.igf-heroes-page__showcase--division :deep(.igf-heroes-map.is-district .igf-heroes-map__svg){width:100%;height:100%;max-height:none}}
.igf-heroes-page__showcase,.igf-heroes-page__showcase--division{background:#fff}.igf-heroes-page__about h2,:deep(.igf-heroes-activities__header h2){font-size:48px;line-height:48px}.igf-heroes-page__narrative{margin-top:20px}:deep(.igf-heroes-activities__header){width:100%;max-width:none;margin-bottom:40px}:deep(.igf-heroes-activities__grid){width:100%;grid-template-columns:repeat(4,minmax(0,1fr));align-items:center;gap:24px}:deep(.igf-heroes-activities__card){border-radius:8px;box-shadow:0 10px 15px -3px rgba(62,39,24,.1),0 4px 6px -4px rgba(62,39,24,.1)}:deep(.igf-heroes-activities__media){aspect-ratio:3 / 2}:deep(.igf-heroes-activities__copy){min-height:0;padding:20px}:deep(.igf-heroes-activities__copy h3){font:700 24px/32px 'Hanken Grotesk',Arial,sans-serif;letter-spacing:0}@media(min-width:981px){.igf-heroes-page__showcase--division{padding-bottom:0}.igf-heroes-page__about{padding:40px 0 72px}:deep(.igf-heroes-activities){padding:0 0 112px}}@media(max-width:980px){.igf-heroes-page__about h2,:deep(.igf-heroes-activities__header h2){font-size:clamp(38px,6vw,48px);line-height:1}.igf-heroes-page :deep(.igf-heroes-activities__grid){grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.igf-heroes-page :deep(.igf-heroes-activities__grid){grid-template-columns:1fr}}
.igf-heroes-page__about h2,:deep(.igf-heroes-activities__header h2){display:block}.igf-heroes-page :deep(.igf-heroes-activities>.igf-heroes-page__shell){width:min(100% - 48px,1360px);margin-right:auto;margin-left:auto}@media(min-width:981px){.igf-heroes-page :deep(.igf-heroes-activities>.igf-heroes-page__shell){width:min(calc(100% - 32px),1360px)}}@media(max-width:600px){.igf-heroes-page :deep(.igf-heroes-activities>.igf-heroes-page__shell){width:min(100% - 40px,1360px)}}
@media(min-width:981px){.igf-heroes-page__showcase--division :deep(.igf-heroes-carousel__rail){padding:40px 0}.igf-heroes-page__showcase--division :deep(.igf-heroes-carousel__profile){padding-top:0}}
.igf-heroes-page{font-family:'Jost',Arial,sans-serif}.igf-heroes-page__header h1,.igf-heroes-page__district-heading h1,.igf-heroes-page__about h2,:deep(.igf-heroes-activities__header h2){font-family:'Jost',Arial,sans-serif;font-weight:400;letter-spacing:normal}.igf-heroes-page__header h1 strong,.igf-heroes-page__district-heading h1 strong,.igf-heroes-page__about h2 strong,:deep(.igf-heroes-activities__header h2 strong){font-weight:700}:deep(.igf-heroes-activities__copy h3){font-family:'Jost',Arial,sans-serif}
.igf-heroes-page__eyebrow,.igf-heroes-page__narrative,.igf-heroes-page__group-fallback strong,.igf-heroes-page :deep(p),.igf-heroes-page :deep(button),.igf-heroes-page :deep(a),.igf-heroes-page :deep(time){font-family:'Jost',Arial,sans-serif}
@media(min-width:981px){.igf-heroes-page__district-hero{padding:80px 0 40px}.igf-heroes-page__district-heading{margin-bottom:20px}}
.igf-heroes-page__header h1 .is-before-place{display:block}
.igf-heroes-page__district-hero.has-about{padding-bottom:0}.igf-heroes-page__about--district{padding-top:40px}
.igf-heroes-page__narrative{max-width:none;font:400 16px/24px 'Jost',Arial,sans-serif}.igf-heroes-page__narrative p{font:inherit;line-height:24px}.igf-heroes-page__about h2::before,.igf-heroes-page__about h2::after,.igf-heroes-page :deep(.igf-heroes-activities__header h2)::before,.igf-heroes-page :deep(.igf-heroes-activities__header h2)::after{display:none!important;content:none!important}
</style>
