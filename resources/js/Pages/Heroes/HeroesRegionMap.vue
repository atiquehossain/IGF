<template>
  <figure class="igf-heroes-map" :class="[`is-${normalizedLevel}`, { 'is-minimal': minimal }]">
    <div class="igf-heroes-map__heading">
      <p v-if="eyebrow" class="igf-heroes-map__eyebrow">{{ eyebrow }}</p>
      <h2 :id="headingId">{{ heading }}</h2>
      <p v-if="help" :id="helpId">{{ help }}</p>
    </div>
    <div class="igf-heroes-map__stage">
      <svg
        class="igf-heroes-map__svg"
        :viewBox="mapViewBox"
        role="group"
        :aria-labelledby="headingId"
        :aria-describedby="help ? helpId : null"
      >
        <title>{{ heading }}</title>
        <template v-for="shape in shapes" :key="shape.key">
          <a
            v-if="shape.available"
            class="igf-heroes-map__link"
            :class="{ 'is-active': activeKey === shape.key }"
            :href="shape.href"
            :aria-label="linkLabel(shape)"
            :data-region-key="shape.key"
            @mouseenter="activeKey = shape.key"
            @mouseleave="activeKey = ''"
            @focus="activeKey = shape.key"
            @blur="activeKey = ''"
          >
            <path :d="shape.path" fill-rule="evenodd" />
            <circle class="igf-heroes-map__hit-area" :cx="shape.labelX" :cy="shape.labelY" :r="hitRadius" />
          </a>
          <g v-else class="igf-heroes-map__link is-unavailable" :data-region-key="shape.key" aria-hidden="true">
            <path :d="shape.path" fill-rule="evenodd" />
          </g>
        </template>
      </svg>
      <Transition name="igf-heroes-map-tooltip">
        <div
          v-if="activeShape"
          class="igf-heroes-map__tooltip"
          :style="tooltipStyle(activeShape)"
          aria-hidden="true"
        >{{ activeShape.name }}</div>
      </Transition>
    </div>
    <nav v-if="availableShapes.length" class="igf-heroes-map__list" :aria-label="listLabel">
      <ul>
        <li v-for="shape in availableShapes" :key="`list-${shape.key}`">
          <a :href="shape.href">{{ shape.name }}<i class="fa-solid fa-arrow-right" aria-hidden="true" /></a>
        </li>
      </ul>
    </nav>
    <figcaption class="igf-heroes-map__attribution">
      <details>
        <summary :aria-label="fullAttributionLabel" :title="fullAttributionLabel">
          <span aria-hidden="true">ⓘ</span>
        </summary>
        <span class="igf-heroes-map__attribution-details">
          {{ attributionPrefix }}
          <a :href="metadata.sourceUrl" target="_blank" rel="noopener noreferrer">{{ metadata.source }}</a>
          <template v-if="metadata.sourceOrganization"> ({{ metadata.sourceOrganization }})</template>
          ·
          <a :href="licenseUrl" target="_blank" rel="noopener noreferrer">{{ metadata.license }}</a>.
        </span>
      </details>
    </figcaption>
  </figure>
</template>

<script setup>
import { computed, ref } from 'vue';
import divisionMapData from '../../../data/bangladesh-divisions.json';
import districtMapData from '../../../data/bangladesh-districts.json';

const props = defineProps({
  level: { type: String, default: 'division' },
  parentSlug: { type: String, default: '' },
  regions: { type: Array, default: () => [] },
  locale: { type: String, default: 'en' },
  heading: { type: String, required: true },
  eyebrow: { type: String, default: '' },
  help: { type: String, default: '' },
  idPrefix: { type: String, default: 'heroes-region-map' },
  linkPrefix: { type: String, default: '' },
  linkLabelTemplate: { type: String, default: 'Explore heroes in {name}' },
  listLabel: { type: String, default: 'Places shown on the map' },
  attributionPrefix: { type: String, default: 'Boundary data:' },
  minimal: { type: Boolean, default: false },
});

const activeKey = ref('');
const normalizedLevel = computed(() => props.level === 'district' ? 'district' : 'division');
const source = computed(() => normalizedLevel.value === 'district' ? districtMapData : divisionMapData);
const metadata = computed(() => source.value.metadata || {});
const licenseUrl = computed(() => String(metadata.value.license || '').includes('3.0 IGO')
  ? 'https://creativecommons.org/licenses/by/3.0/igo/'
  : 'https://creativecommons.org/licenses/by/4.0/');
const fullAttributionLabel = computed(() => [
  String(props.attributionPrefix || '').trim(),
  String(metadata.value.source || '').trim(),
  metadata.value.sourceOrganization ? `(${metadata.value.sourceOrganization})` : '',
  String(metadata.value.license || '').trim(),
].filter(Boolean).join(' '));
const headingId = computed(() => `${domToken(props.idPrefix, 'heroes-map')}-heading`);
const helpId = computed(() => `${domToken(props.idPrefix, 'heroes-map')}-help`);
const regionIndex = computed(() => new Map(props.regions.map(region => [normalizedSlug(region?.slug || region?.key || region?.name), region])));
const shapes = computed(() => {
  const candidates = normalizedLevel.value === 'district'
    ? (districtMapData.districts || []).filter(district => district.division === normalizedSlug(props.parentSlug))
    : (divisionMapData.divisions || []);
  return candidates.map(shape => {
    const available = regionIndex.value.has(shape.key);
    const region = regionIndex.value.get(shape.key) || {};
    return {
      ...shape,
      available,
      name: localizedName(region?.label || region?.name || region?.names || shape.names),
      href: available ? regionHref(shape.key, region?.url) : '',
    };
  });
});
const availableShapes = computed(() => shapes.value.filter(shape => shape.available));
const mapViewBox = computed(() => normalizedLevel.value === 'district'
  ? (districtMapData.divisionViewBoxes?.[normalizedSlug(props.parentSlug)] || districtMapData.viewBox)
  : divisionMapData.viewBox);
const viewBoxParts = computed(() => String(mapViewBox.value).split(/\s+/).map(Number));
const hitRadius = computed(() => normalizedLevel.value === 'district' ? 12 : 18);
const activeShape = computed(() => shapes.value.find(shape => shape.key === activeKey.value) || null);

function domToken(value, fallback) {
  return String(value || fallback).toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || fallback;
}
function normalizedSlug(value) {
  return String(value || '').trim().toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}
function localizedName(value) {
  if (value && typeof value === 'object') {
    return String(value[props.locale] || value[props.locale?.split('-')[0]] || value.en || value.bn || '').trim();
  }
  return String(value || '').trim();
}
function expectedPrefix() {
  if (props.linkPrefix) return props.linkPrefix.replace(/\/$/, '');
  return normalizedLevel.value === 'district' ? '/meet-the-heroes/district' : '/meet-the-heroes/division';
}
function regionHref(slug, suppliedUrl) {
  const prefix = expectedPrefix();
  const canonical = `${prefix}/${encodeURIComponent(slug)}`;
  const supplied = String(suppliedUrl || '').trim();
  try {
    const parsed = new URL(supplied, 'https://igf.invalid');
    return parsed.pathname === canonical ? `${canonical}${parsed.search}${parsed.hash}` : canonical;
  } catch {
    return canonical;
  }
}
function linkLabel(shape) {
  return String(props.linkLabelTemplate || '{name}').replaceAll('{name}', shape.name);
}
function tooltipStyle(shape) {
  const [x, y, width, height] = viewBoxParts.value;
  return {
    left: `${Math.min(97, Math.max(3, ((Number(shape.labelX) - x) / width) * 100))}%`,
    top: `${Math.min(97, Math.max(3, ((Number(shape.labelY) - y) / height) * 100))}%`,
  };
}
</script>

<style scoped>
.igf-heroes-map{min-width:0;margin:0;color:#202122;font-family:'Hanken Grotesk',Arial,sans-serif}.igf-heroes-map__heading{max-width:560px;margin-bottom:20px}.igf-heroes-map__eyebrow{margin:0 0 8px;color:#a44906;font-size:12px;font-weight:850;letter-spacing:.12em;text-transform:uppercase}.igf-heroes-map__heading h2{margin:0;color:#202122;font:650 clamp(28px,3.5vw,44px)/1.08 'Literata',Georgia,serif;letter-spacing:-.035em}.igf-heroes-map__heading>p:last-child{margin:12px 0 0;color:#5d5650;font-size:15px;line-height:1.55}.igf-heroes-map__stage{position:relative;width:min(100%,520px);margin:0 auto}.igf-heroes-map__svg{display:block;width:100%;height:auto;max-height:620px;overflow:visible;filter:drop-shadow(0 15px 20px rgba(61,39,22,.14))}.igf-heroes-map__link{cursor:pointer;outline:none}.igf-heroes-map__link path{fill:#b65a17;stroke:#fff8f1;stroke-width:2.2;stroke-linejoin:round;vector-effect:non-scaling-stroke}.igf-heroes-map__link:hover path,.igf-heroes-map__link.is-active path{fill:#ff7500;stroke:#6d2d00;stroke-width:4}.igf-heroes-map__link:focus-visible path{fill:#ff8f36;stroke:#1769aa;stroke-width:5;filter:drop-shadow(0 0 5px rgba(23,105,170,.42))}.igf-heroes-map__hit-area{fill:transparent;stroke:transparent;pointer-events:all;vector-effect:non-scaling-stroke}.igf-heroes-map__tooltip{position:absolute;z-index:2;max-width:190px;padding:8px 11px;border-radius:8px;background:#202122;box-shadow:0 8px 20px rgba(0,0,0,.2);color:#fff;font-size:13px;font-weight:800;line-height:1.2;text-align:center;transform:translate(-50%,calc(-100% - 12px));pointer-events:none}.igf-heroes-map__tooltip::after{position:absolute;top:100%;left:50%;border:6px solid transparent;border-top-color:#202122;content:'';transform:translateX(-50%)}.igf-heroes-map-tooltip-enter-active,.igf-heroes-map-tooltip-leave-active{transition:opacity .15s ease,transform .15s ease}.igf-heroes-map-tooltip-enter-from,.igf-heroes-map-tooltip-leave-to{opacity:0;transform:translate(-50%,calc(-100% - 5px))}.igf-heroes-map__list{margin-top:24px}.igf-heroes-map__list ul{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:0;padding:0;list-style:none}.igf-heroes-map__list a{display:flex;min-height:44px;align-items:center;justify-content:space-between;gap:10px;padding:9px 12px;border:1px solid #ddcaba;border-radius:10px;background:#fff;color:#713100;font-size:13px;font-weight:800;text-decoration:none}.igf-heroes-map__list a:hover{border-color:#ff7500;background:#fff3e9}.igf-heroes-map__list a:focus-visible{outline:3px solid #1769aa;outline-offset:2px}.igf-heroes-map__list i{font-size:10px}.igf-heroes-map__attribution{display:flex;justify-content:flex-end;margin-top:8px;color:#716961;font-size:10px;line-height:1.35}.igf-heroes-map__attribution details{max-width:min(100%,310px)}.igf-heroes-map__attribution summary{display:inline-flex;min-height:28px;align-items:center;padding:4px 9px;border:1px solid rgba(113,105,97,.28);border-radius:999px;background:rgba(255,255,255,.88);color:#635d57;cursor:pointer;list-style:none;white-space:nowrap}.igf-heroes-map__attribution summary::-webkit-details-marker{display:none}.igf-heroes-map__attribution summary::before{margin-right:5px;content:'ⓘ';font-size:11px}.igf-heroes-map__attribution summary:hover{border-color:#a44906;color:#713100}.igf-heroes-map__attribution summary:focus-visible{outline:3px solid #1769aa;outline-offset:2px}.igf-heroes-map__attribution-details{display:block;margin-top:6px;padding:8px 10px;border:1px solid #dfd2c7;border-radius:8px;background:#fffaf5;box-shadow:0 8px 22px rgba(61,39,22,.1);white-space:normal}.igf-heroes-map__attribution a{color:#713100;text-decoration:underline;text-underline-offset:2px}.igf-heroes-map.is-district .igf-heroes-map__svg{max-height:560px}.igf-heroes-map.is-district .igf-heroes-map__link path{fill:#f3c7a4;stroke:#8b3d05;stroke-width:1.5}.igf-heroes-map.is-district .igf-heroes-map__link:hover path,.igf-heroes-map.is-district .igf-heroes-map__link.is-active path{fill:#ff7500;stroke:#5e2700;stroke-width:3}.igf-heroes-map.is-district .igf-heroes-map__link:focus-visible path{fill:#ff9a4b;stroke:#1769aa;stroke-width:4}@media(max-width:600px){.igf-heroes-map__heading h2{font-size:30px}.igf-heroes-map__stage{width:min(100%,390px)}.igf-heroes-map__list ul{grid-template-columns:1fr}.igf-heroes-map__list a{min-height:48px;font-size:14px}.igf-heroes-map__attribution{justify-content:flex-start}.igf-heroes-map__attribution details{max-width:100%}}@media(prefers-reduced-motion:reduce){.igf-heroes-map *{animation:none!important;transition:none!important}}@media(forced-colors:active){.igf-heroes-map__link path{fill:Canvas;stroke:CanvasText}.igf-heroes-map__link:is(:hover,:focus-visible,.is-active) path{fill:Highlight;stroke:HighlightText}.igf-heroes-map__tooltip{forced-color-adjust:auto}.igf-heroes-map__attribution summary,.igf-heroes-map__attribution-details{border:1px solid CanvasText;background:Canvas;color:CanvasText}}
.igf-heroes-map.is-minimal .igf-heroes-map__heading,.igf-heroes-map.is-minimal .igf-heroes-map__list{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;margin:-1px!important;padding:0!important;border:0!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important}.igf-heroes-map.is-minimal .igf-heroes-map__list:focus-within{position:static!important;width:auto!important;height:auto!important;overflow:visible!important;margin-top:22px!important;padding:0!important;clip:auto!important;white-space:normal!important}@media(max-width:600px){.igf-heroes-map.is-minimal .igf-heroes-map__list{position:static!important;width:auto!important;height:auto!important;overflow:visible!important;margin-top:22px!important;padding:0!important;clip:auto!important;white-space:normal!important}}
.igf-heroes-map__stage{width:min(100%,560px)}.igf-heroes-map.is-division .igf-heroes-map__stage{aspect-ratio:4 / 5}.igf-heroes-map.is-division .igf-heroes-map__svg{width:100%;height:100%;max-height:none}@media(max-width:600px){.igf-heroes-map__stage{width:min(100%,390px)}}
.igf-heroes-map__link.is-unavailable{pointer-events:none}.igf-heroes-map__link.is-unavailable path{fill:#d8c9bc;stroke:#fff8f1}.igf-heroes-map.is-district .igf-heroes-map__link.is-unavailable path{fill:#eadfd5;stroke:#bda895}
.igf-heroes-map{font-family:'Jost',Arial,sans-serif}.igf-heroes-map__heading h2{font-family:'Jost',Arial,sans-serif}
.igf-heroes-map p,.igf-heroes-map a,.igf-heroes-map figcaption,.igf-heroes-map__tooltip{font-family:'Jost',Arial,sans-serif}.igf-heroes-map__svg{overflow:hidden;filter:none}
.igf-heroes-map__heading h2::before,.igf-heroes-map__heading h2::after{display:none!important;content:none!important}.igf-heroes-map.is-minimal{position:relative}
.igf-heroes-map__attribution summary{width:28px;justify-content:center;padding:0}.igf-heroes-map__attribution summary::before{display:none;content:none}
</style>
