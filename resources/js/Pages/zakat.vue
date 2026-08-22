<!-- Rich text is sanitized by ContentSanitizer before it reaches this view. -->
<!-- eslint-disable vue/no-v-html -->
<template>
  <Layout>
    <AppBannerPage v-if="!hasHeroBlock" />
    <div class="igf-zakat">
      <PageBlocks v-if="zakat?.visible_blocks?.length" :blocks="zakat.visible_blocks" />
      <section v-else-if="zakat?.description" class="igf-zakat__content"><div class="igf-shell"><article v-html="zakat.description" /></div></section>

      <section class="igf-zakat-impact" aria-labelledby="zakat-impact-title">
        <div class="igf-shell">
          <header><p class="igf-eyebrow">{{ settings.impact_eyebrow }}</p><h2 id="zakat-impact-title">{{ settings.impact_title }}</h2><p>{{ settings.impact_body }}</p></header>
          <div class="igf-zakat-impact__grid">
            <article v-for="card in impactCards" :key="card.title">
              <img :src="card.image" :alt="card.alt || card.title">
              <div><h3>{{ card.title }}</h3><p>{{ card.body }}</p></div>
            </article>
          </div>
        </div>
      </section>

      <section v-if="settings.enabled" class="igf-calculator" aria-labelledby="zakat-calculator-title">
        <div class="igf-shell">
          <header class="igf-calculator__header"><div><p class="igf-eyebrow">{{ copy.eyebrow }}</p><h2 id="zakat-calculator-title">{{ copy.title }}</h2></div><p>{{ copy.introduction }}</p></header>
          <div class="igf-calculator__layout">
            <form class="igf-calculator__form" @submit.prevent>
              <fieldset class="igf-nisab-choice">
                <legend>{{ copy.nisab_basis_legend }}</legend>
                <p class="igf-fieldset-help">{{ copy.nisab_basis_help }}</p>
                <div class="igf-nisab-choice__options">
                  <label v-for="option in nisabOptions" :key="option.key" class="igf-nisab-option" :class="{ 'is-selected': selectedBasis === option.key }" :for="`nisab-basis-${option.key}`">
                    <span class="igf-nisab-option__title"><input :id="`nisab-basis-${option.key}`" v-model="selectedBasis" type="radio" name="nisab_basis" :value="option.key"><strong>{{ option.label }}</strong></span>
                    <span>{{ option.weight }} {{ copy.grams_label }}</span>
                    <small v-if="option.priceAvailable">{{ copy.price_per_gram_label }}: {{ money(option.price) }} · {{ copy.threshold_label }}: {{ money(option.threshold) }}</small>
                    <small v-else class="igf-setting-warning">{{ copy.price_unavailable_label }}</small>
                  </label>
                </div>
                <p v-if="hasPriceInformation" class="igf-price-source">
                  <span>{{ copy.price_information_label }} </span>
                  <a v-if="safeSourceUrl" :href="safeSourceUrl" target="_blank" rel="noopener noreferrer">{{ sourceLabel }}</a>
                  <span v-else>{{ sourceLabel }}</span>
                  <span v-if="formattedPriceDate"> · {{ copy.price_updated_label }} {{ formattedPriceDate }}</span>
                </p>
                <p v-if="priceNeedsVerification" class="igf-price-warning" role="alert"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true" /> {{ copy.stale_price_notice }}</p>
              </fieldset>

              <fieldset>
                <legend>{{ copy.assets_legend }}</legend>
                <p class="igf-fieldset-help">{{ copy.assets_help }}</p>
                <label v-for="field in assetFields" :key="field.key" :for="`asset-${field.key}`">
                  <span>{{ field.label }}</span>
                  <span class="igf-money-input"><b aria-hidden="true">{{ regional.currency_symbol }}</b><input :id="`asset-${field.key}`" v-model.number="assets[field.key]" type="number" min="0" :max="MAX_AMOUNT" step="0.01" inputmode="decimal" :placeholder="copy.amount_placeholder" :aria-describedby="field.help ? `asset-${field.key}-help` : undefined"></span>
                  <small v-if="field.help" :id="`asset-${field.key}-help`" class="igf-input-help">{{ field.help }}</small>
                </label>
              </fieldset>

              <fieldset>
                <legend>{{ copy.liabilities_legend }}</legend>
                <p class="igf-fieldset-help">{{ copy.liabilities_help }}</p>
                <label v-for="field in liabilityFields" :key="field.key" :for="`liability-${field.key}`">
                  <span>{{ field.label }}</span>
                  <span class="igf-money-input"><b aria-hidden="true">{{ regional.currency_symbol }}</b><input :id="`liability-${field.key}`" v-model.number="liabilities[field.key]" type="number" min="0" :max="MAX_AMOUNT" step="0.01" inputmode="decimal" :placeholder="copy.amount_placeholder" :aria-describedby="field.help ? `liability-${field.key}-help` : undefined"></span>
                  <small v-if="field.help" :id="`liability-${field.key}-help`" class="igf-input-help">{{ field.help }}</small>
                </label>
              </fieldset>

              <fieldset class="igf-haul">
                <legend>{{ copy.haul_legend }}</legend>
                <label for="zakat-haul-confirmation" class="igf-haul__check">
                  <input id="zakat-haul-confirmation" v-model="haulSatisfied" type="checkbox">
                  <span><strong>{{ copy.haul_confirmation_label }}</strong><small>{{ copy.haul_help }}</small><small class="igf-haul__answer">{{ haulSatisfied ? copy.lunar_year_yes_label : copy.lunar_year_no_label }}</small></span>
                </label>
              </fieldset>
              <button type="button" class="igf-clear" @click="clearCalculator">{{ copy.clear_label }}</button>
            </form>
            <aside class="igf-calculator__result" aria-live="polite">
              <p class="igf-eyebrow">{{ copy.estimate_eyebrow }}</p>
              <div><span>{{ copy.total_assets_label }}</span><strong>{{ money(totalAssets) }}</strong></div>
              <div><span>{{ copy.less_liabilities_label }}</span><strong>{{ money(totalLiabilities) }}</strong></div>
              <div><span>{{ copy.net_amount_label }}</span><strong>{{ money(eligibleAmount) }}</strong></div>
              <div><span>{{ copy.selected_basis_label }}</span><strong>{{ selectedBasisLabel }}</strong></div>
              <div><span>{{ copy.zakat_rate_label }}</span><strong>{{ ZAKAT_RATE_PERCENT }}%</strong></div>
              <p class="igf-nisab" :class="{ 'is-unavailable': !nisabUsable }"><i class="fa-solid fa-scale-balanced" aria-hidden="true" /><span><strong>{{ copy.nisab_label }}: {{ nisabAvailable ? money(nisab) : copy.not_available_label }}</strong>{{ nisabExplanation }}</span></p>
              <div class="igf-zakat-due"><span>{{ copy.result_label }}</span><strong>{{ money(zakatDue) }}</strong><small>{{ resultNote }}</small></div>
              <a class="igf-donate" :href="route('frontend.donate.cause', 'zakat')">{{ copy.donate_label }} <i class="fa-solid fa-arrow-right" aria-hidden="true" /></a>
              <p v-if="copy.methodology" class="igf-methodology">{{ copy.methodology }}</p>
              <p class="igf-disclaimer">{{ copy.disclaimer }}</p>
            </aside>
          </div>
        </div>
      </section>
      <nav v-if="category?.url" class="igf-zakat-context" :aria-label="category.name">
        <a :href="category.url"><span aria-hidden="true">←</span> {{ category.name }}</a>
      </nav>
    </div>
  </Layout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
import AppBannerPage from '../component/banner.vue';
import PageBlocks from '../Shared/PageBlocks.vue';
import { formatDate, formatMoney, regionalSettings } from '../Shared/composables/siteSettings';

const NISAB_WEIGHTS = Object.freeze({ gold: 87.48, silver: 612.36 });
const ZAKAT_RATE = 0.025;
const ZAKAT_RATE_PERCENT = 2.5;
const MAX_AMOUNT = 999999999999.99;
const DEFAULT_COPY = Object.freeze({
  eyebrow: 'Personal Zakat estimate',
  title: 'Estimate your Zakat clearly',
  introduction: 'Choose a Nisab basis, enter only Zakat-eligible wealth and current liabilities, then confirm the lunar-year condition.',
  nisab_basis_legend: '1. Choose your Nisab basis',
  nisab_basis_help: 'Gold and silver produce different thresholds. Choose the basis you follow or ask a qualified scholar.',
  gold_basis_label: 'Gold Nisab',
  silver_basis_label: 'Silver Nisab',
  grams_label: 'grams',
  price_per_gram_label: 'Price per gram',
  threshold_label: 'Nisab threshold',
  price_unavailable_label: 'The administrator needs to add a current price for this basis.',
  price_information_label: 'Price source:',
  price_updated_label: 'updated',
  stale_price_notice: 'The displayed metal prices may be older than seven days. Verify current prices before relying on this estimate.',
  assets_legend: '2. Eligible assets',
  assets_help: 'Enter the value you own on your Zakat date. Do not include your home, personal belongings or the capital value of rental property.',
  cash_label: 'Cash and bank balances',
  receivables_label: 'Money owed to you that you expect to receive',
  investments_label: 'Zakatable shares and investments',
  precious_metals_label: 'Gold and silver you own',
  trade_goods_label: 'Business stock and trade goods',
  resale_property_label: 'Property or land held for resale',
  resale_property_help: 'Include market value only when the property is held for resale. Do not include your home or rental-property capital.',
  retained_rental_income_label: 'Net rental income still held',
  retained_rental_income_help: 'Include rent that remains in your possession on your Zakat date, not the property’s capital value. Do not enter the same retained rental income again under cash and bank balances.',
  other_assets_label: 'Other Zakat-eligible assets',
  liabilities_legend: '3. Current liabilities',
  liabilities_help: 'Deduct only genuine obligations that are due now or within the allowed current period—not an entire long-term loan.',
  debts_due_label: 'Debt payments currently due',
  debts_due_help: 'Enter only the instalments or amount currently due, not all future long-term repayments.',
  bills_due_label: 'Unpaid bills currently due',
  immediate_expenses_label: 'Other immediate eligible obligations',
  amount_placeholder: '0.00',
  haul_legend: '4. Confirm the lunar year',
  haul_confirmation_label: 'Yes, one lunar year has passed since I first reached Nisab, or this is my established annual Zakat date.',
  lunar_year_yes_label: 'Yes—the lunar-year condition is confirmed.',
  lunar_year_no_label: 'No—the lunar-year condition is not confirmed.',
  haul_help: 'Leave this unchecked if neither condition applies. Your estimated Zakat due will remain zero.',
  clear_label: 'Clear calculator',
  estimate_eyebrow: 'Your transparent estimate',
  total_assets_label: 'Eligible assets',
  less_liabilities_label: 'Less current liabilities',
  net_amount_label: 'Net eligible wealth',
  selected_basis_label: 'Selected Nisab basis',
  zakat_rate_label: 'Protected Zakat rate',
  nisab_label: 'Current Nisab',
  not_available_label: 'Price unavailable',
  nisab_formula_label: 'Calculated as {weight} grams × {price} per gram.',
  nisab_unavailable_note: 'A current per-gram price is required before eligibility can be estimated.',
  result_label: 'Estimated Zakat due',
  eligible_result_note: 'Your net eligible wealth reaches Nisab and the lunar-year condition is confirmed.',
  below_nisab_note: 'Your net eligible wealth is below the selected Nisab threshold.',
  haul_not_met_result_note: 'Your wealth reaches Nisab, but the lunar-year condition is not yet confirmed.',
  price_unavailable_result_note: 'Zakat due cannot be estimated until a current price is available for the selected basis.',
  stale_price_result_note: 'Zakat due remains zero until the metal price and its checked date are current and verified.',
  donate_label: 'Give your Zakat',
  methodology: 'Your estimate is calculated in this browser as (eligible assets − current liabilities) × 2.5%. No calculator amounts are submitted or saved.',
  disclaimer: 'This calculator provides an estimate, not a religious ruling. For personal circumstances, consult a qualified scholar.',
});

const page = usePage();
const zakat = computed(() => page.props.data?.zakat || null);
const category = computed(() => page.props.data?.category || null);
const settings = computed(() => page.props.siteSettings?.zakat_calculator || {});
const copy = computed(() => {
  const configured = settings.value;
  return {
    ...DEFAULT_COPY,
    ...configured,
    nisab_basis_legend: configured.nisab_method_label || configured.nisab_basis_legend || DEFAULT_COPY.nisab_basis_legend,
    gold_basis_label: configured.gold_method_label || configured.gold_basis_label || DEFAULT_COPY.gold_basis_label,
    silver_basis_label: configured.silver_method_label || configured.silver_basis_label || DEFAULT_COPY.silver_basis_label,
    nisab_label: configured.calculated_nisab_label || configured.nisab_label || DEFAULT_COPY.nisab_label,
    threshold_label: configured.calculated_nisab_label || configured.threshold_label || DEFAULT_COPY.threshold_label,
    haul_legend: configured.lunar_year_question || configured.haul_legend || DEFAULT_COPY.haul_legend,
    haul_confirmation_label: configured.lunar_year_yes_label || configured.haul_confirmation_label || DEFAULT_COPY.haul_confirmation_label,
    haul_help: configured.lunar_year_help || configured.haul_help || DEFAULT_COPY.haul_help,
    resale_property_label: configured.property_for_resale_label || configured.resale_property_label || configured.investment_property_label || DEFAULT_COPY.resale_property_label,
    retained_rental_income_label: configured.net_rental_income_label || configured.retained_rental_income_label || DEFAULT_COPY.retained_rental_income_label,
    assets_help: configured.exclusions_note || configured.assets_help || DEFAULT_COPY.assets_help,
    debts_due_label: configured.immediate_debt_label || configured.debts_due_label || configured.debts_label || DEFAULT_COPY.debts_due_label,
    debts_due_help: configured.immediate_debt_help || configured.debts_due_help || DEFAULT_COPY.debts_due_help,
    bills_due_label: configured.bills_label || configured.bills_due_label || DEFAULT_COPY.bills_due_label,
    immediate_expenses_label: configured.expenses_label || configured.immediate_expenses_label || DEFAULT_COPY.immediate_expenses_label,
  };
});
const regional = computed(() => regionalSettings(page.props.siteSettings?.regional));
const hasHeroBlock = computed(() => (zakat.value?.visible_blocks || []).some(block => block?.type === 'hero'));
const impactCards = computed(() => [
  { title: settings.value.food_title, body: settings.value.food_body, image: settings.value.food_image, alt: settings.value.food_image_alt },
  { title: settings.value.livelihood_title, body: settings.value.livelihood_body, image: settings.value.livelihood_image, alt: settings.value.livelihood_image_alt },
  { title: settings.value.education_title, body: settings.value.education_body, image: settings.value.education_image, alt: settings.value.education_image_alt },
].filter(card => card.title));
const normaliseBasis = value => value === 'gold' ? 'gold' : 'silver';
const selectedBasis = ref(normaliseBasis(settings.value.nisab_default_basis));
const assetKeys = ['cash', 'receivables', 'investments', 'preciousMetals', 'tradeGoods', 'resaleProperty', 'retainedRentalIncome', 'other'];
const liabilityKeys = ['debtsDue', 'billsDue', 'immediateExpenses'];
const assetFields = computed(() => [
  { key: 'cash', label: copy.value.cash_label },
  { key: 'receivables', label: copy.value.receivables_label },
  { key: 'investments', label: copy.value.investments_label },
  { key: 'preciousMetals', label: copy.value.precious_metals_label },
  { key: 'tradeGoods', label: copy.value.trade_goods_label },
  { key: 'resaleProperty', label: copy.value.resale_property_label, help: copy.value.resale_property_help },
  { key: 'retainedRentalIncome', label: copy.value.retained_rental_income_label, help: copy.value.retained_rental_income_help },
  { key: 'other', label: copy.value.other_assets_label },
]);
const liabilityFields = computed(() => [
  { key: 'debtsDue', label: copy.value.debts_due_label, help: copy.value.debts_due_help },
  { key: 'billsDue', label: copy.value.bills_due_label },
  { key: 'immediateExpenses', label: copy.value.immediate_expenses_label },
]);
const assets = reactive(Object.fromEntries(assetKeys.map(key => [key, ''])));
const liabilities = reactive(Object.fromEntries(liabilityKeys.map(key => [key, ''])));
const haulSatisfied = ref(false);
const safeNumber = value => {
  const amount = Number(value);
  if (!Number.isFinite(amount) || amount <= 0) return 0;
  return Math.min(amount, MAX_AMOUNT);
};
const positivePrice = value => {
  const price = Number(value);
  return Number.isFinite(price) && price > 0 ? Math.min(price, MAX_AMOUNT) : 0;
};
const prices = computed(() => ({
  gold: positivePrice(settings.value.gold_price_per_gram),
  silver: positivePrice(settings.value.silver_price_per_gram),
}));
const thresholdFor = basis => prices.value[basis] * NISAB_WEIGHTS[basis];
const nisabOptions = computed(() => ['gold', 'silver'].map(key => ({
  key,
  label: key === 'gold' ? copy.value.gold_basis_label : copy.value.silver_basis_label,
  weight: NISAB_WEIGHTS[key],
  price: prices.value[key],
  priceAvailable: prices.value[key] > 0,
  threshold: thresholdFor(key),
})));
const priceNeedsVerification = computed(() => {
  const updatedAt = new Date(settings.value.nisab_price_updated_at || '');
  const age = Date.now() - updatedAt.getTime();
  return Number.isNaN(updatedAt.getTime()) || age < 0 || age > 7 * 24 * 60 * 60 * 1000;
});
const totalAssets = computed(() => Object.values(assets).reduce((sum, value) => sum + safeNumber(value), 0));
const totalLiabilities = computed(() => Object.values(liabilities).reduce((sum, value) => sum + safeNumber(value), 0));
const eligibleAmount = computed(() => Math.max(0, totalAssets.value - totalLiabilities.value));
const selectedPrice = computed(() => prices.value[selectedBasis.value]);
const nisab = computed(() => thresholdFor(selectedBasis.value));
const nisabAvailable = computed(() => selectedPrice.value > 0);
const nisabUsable = computed(() => nisabAvailable.value && !priceNeedsVerification.value);
const meetsNisab = computed(() => nisabUsable.value && eligibleAmount.value >= nisab.value);
const zakatDue = computed(() => meetsNisab.value && haulSatisfied.value ? eligibleAmount.value * ZAKAT_RATE : 0);
const selectedBasisLabel = computed(() => selectedBasis.value === 'gold' ? copy.value.gold_basis_label : copy.value.silver_basis_label);
const nisabExplanation = computed(() => {
  if (!nisabAvailable.value) return copy.value.nisab_unavailable_note;
  const formula = String(copy.value.nisab_formula_label)
    .replace('{weight}', NISAB_WEIGHTS[selectedBasis.value])
    .replace('{price}', money(selectedPrice.value));
  const legacyNote = String(settings.value.nisab_note || '').trim();
  return legacyNote ? `${formula} ${legacyNote}` : formula;
});
const resultNote = computed(() => {
  if (!nisabAvailable.value) return copy.value.price_unavailable_result_note;
  if (priceNeedsVerification.value) return copy.value.stale_price_result_note;
  if (!meetsNisab.value) return copy.value.below_nisab_note;
  if (!haulSatisfied.value) return copy.value.haul_not_met_result_note;
  return copy.value.eligible_result_note;
});
const safeSourceUrl = computed(() => {
  const value = String(settings.value.nisab_source_url || '').trim();
  if (!value) return '';
  try {
    const parsed = new URL(value);
    return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : '';
  } catch {
    return '';
  }
});
const sourceLabel = computed(() => String(settings.value.nisab_source_label || safeSourceUrl.value || '').trim());
const formattedPriceDate = computed(() => formatDate(settings.value.nisab_price_updated_at, regional.value));
const hasPriceInformation = computed(() => Boolean(sourceLabel.value || formattedPriceDate.value));
const money = value => formatMoney(value, regional.value, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function clearCalculator() {
  Object.keys(assets).forEach(key => { assets[key] = ''; });
  Object.keys(liabilities).forEach(key => { liabilities[key] = ''; });
  haulSatisfied.value = false;
}
</script>

<style scoped>
.igf-zakat{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#5f6065;--surface:#f8f9fa;--line:#dedbd7;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell{width:min(100% - 40px,1180px);margin:0 auto}.igf-zakat__content{padding:clamp(70px,9vw,110px) 0;background:#fff}.igf-zakat__content article{max-width:880px;margin:auto;color:var(--muted);font-size:17px;line-height:1.8}.igf-zakat__content :deep(img){max-width:100%;height:auto;border-radius:16px}.igf-zakat-impact{padding:clamp(72px,9vw,110px) 0;background:#fff}.igf-zakat-impact header{max-width:800px;margin:0 0 42px}.igf-zakat-impact h2,.igf-calculator h2{margin:0;color:var(--ink);font:650 clamp(38px,5vw,55px)/1.1 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-zakat-impact h2::after,.igf-calculator h2::after{display:none!important}.igf-zakat-impact header>p:last-child{color:var(--muted);font-size:17px;line-height:1.7}.igf-zakat-impact__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}.igf-zakat-impact__grid article{overflow:hidden;border:1px solid var(--line);border-radius:17px;background:#fff;box-shadow:0 6px 20px rgba(25,28,29,.06)}.igf-zakat-impact__grid img{width:100%;aspect-ratio:16/10;object-fit:cover}.igf-zakat-impact__grid article>div{padding:25px}.igf-zakat-impact h3{margin:0 0 10px;color:var(--ink);font:650 24px 'Literata',Georgia,serif}.igf-zakat-impact__grid p{margin:0;color:var(--muted);line-height:1.65}.igf-calculator{padding:clamp(75px,9vw,120px) 0;background:var(--surface)}.igf-calculator__header{display:grid;grid-template-columns:1fr 1fr;align-items:end;gap:60px;margin-bottom:45px}.igf-eyebrow{margin:0 0 14px!important;color:var(--brown)!important;font-size:11px!important;font-weight:800!important;letter-spacing:.1em;text-transform:uppercase}.igf-calculator__header>p{margin:0;color:var(--muted);font-size:17px;line-height:1.7}.igf-calculator__layout{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(360px,.8fr);align-items:start;gap:24px}.igf-calculator__form{display:grid;grid-template-columns:1fr 1fr;gap:18px;padding:30px;border:1px solid var(--line);border-radius:18px;background:#fff}.igf-calculator fieldset{display:grid;align-content:start;gap:16px;margin:0;padding:0;border:0}.igf-calculator legend{margin-bottom:8px;color:var(--ink);font:650 23px 'Literata',Georgia,serif}.igf-calculator label{display:grid;gap:7px;color:#44474a;font-size:12px;font-weight:800}.igf-money-input{position:relative;display:flex;align-items:center}.igf-money-input b{position:absolute;left:13px;color:var(--brown)}.igf-money-input input{width:100%;height:45px;padding:0 12px 0 34px;border:1px solid #c8c4c0;border-radius:8px;color:var(--ink);font:15px 'Hanken Grotesk',Arial,sans-serif}.igf-money-input input:focus{outline:3px solid rgba(255,117,0,.22);border-color:var(--orange)}.igf-clear{grid-column:1/-1;justify-self:start;padding:0;border:0;background:transparent;color:var(--brown);font-weight:800;text-decoration:underline}.igf-calculator__result{position:sticky;top:25px;padding:32px;border-radius:18px;background:#202223;color:#fff}.igf-calculator__result>.igf-eyebrow{color:#ffb070!important}.igf-calculator__result>div:not(.igf-zakat-due){display:flex;justify-content:space-between;gap:20px;padding:13px 0;border-bottom:1px solid rgba(255,255,255,.13)}.igf-calculator__result>div span{color:#ccc;font-size:13px}.igf-calculator__result>div strong{font-size:15px}.igf-nisab{display:flex;gap:12px;margin:20px 0;padding:17px;border:1px solid rgba(255,117,0,.3);border-radius:10px;background:rgba(255,117,0,.09);color:#ddd}.igf-nisab i{margin-top:3px;color:#ffb070}.igf-nisab span{display:grid;gap:4px;font-size:12px;line-height:1.5}.igf-nisab strong{color:#fff}.igf-zakat-due{display:grid;gap:4px;margin:22px 0}.igf-zakat-due>span{color:#ffb070!important;font-size:11px!important;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.igf-zakat-due>strong{font:650 40px 'Literata',Georgia,serif!important}.igf-zakat-due small{color:#ccc}.igf-donate{display:flex;min-height:52px;align-items:center;justify-content:center;gap:11px;border-radius:999px;background:var(--orange);color:#fff;font-size:13px;font-weight:800;text-decoration:none;text-transform:uppercase}.igf-disclaimer{margin:16px 0 0;color:#aaa;font-size:11px;line-height:1.5;text-align:center}
.igf-calculator__form{gap:26px 18px}.igf-calculator legend{margin-bottom:2px}.igf-fieldset-help{margin:-8px 0 1px;color:var(--muted);font-size:13px;line-height:1.55}.igf-nisab-choice,.igf-haul{grid-column:1/-1}.igf-nisab-choice__options{display:grid;grid-template-columns:1fr 1fr;gap:12px}.igf-calculator .igf-nisab-option{gap:7px;padding:16px;border:1px solid #c8c4c0;border-radius:11px;background:#fff;cursor:pointer}.igf-calculator .igf-nisab-option.is-selected{border-color:var(--brown);box-shadow:0 0 0 2px rgba(156,69,0,.13)}.igf-nisab-option__title{display:flex;align-items:center;gap:9px;font-size:15px}.igf-nisab-option input,.igf-haul__check input{width:18px;height:18px;margin:0;accent-color:var(--brown)}.igf-nisab-option small{color:var(--muted);font-size:11px;font-weight:600;line-height:1.5}.igf-nisab-option .igf-setting-warning{color:#8a3c00}.igf-price-source{margin:0;color:var(--muted);font-size:12px;line-height:1.5}.igf-price-source a{color:var(--brown);font-weight:800}.igf-price-warning{display:flex;gap:9px;margin:0;padding:12px;border:1px solid #d49a4b;border-radius:9px;background:#fff5df;color:#693a00;font-size:12px;font-weight:700;line-height:1.5}.igf-nisab-option input:focus-visible,.igf-haul__check input:focus-visible,.igf-clear:focus-visible{outline:3px solid rgba(255,117,0,.25);outline-offset:2px}.igf-input-help{color:var(--muted);font-size:11px;font-weight:500;line-height:1.45}.igf-calculator .igf-haul__check{display:flex;align-items:flex-start;gap:11px;padding:15px;border:1px solid #c8c4c0;border-radius:10px;cursor:pointer}.igf-haul__check input{flex:0 0 auto;margin-top:2px}.igf-haul__check span{display:grid;gap:5px}.igf-haul__check strong{font-size:13px;line-height:1.45}.igf-haul__check small{color:var(--muted);font-size:11px;font-weight:500;line-height:1.5}.igf-haul__check .igf-haul__answer{color:var(--brown);font-weight:800}.igf-clear{padding:3px;cursor:pointer}.igf-calculator__result>div strong{text-align:right}.igf-nisab.is-unavailable{border-color:rgba(255,190,98,.5)}.igf-methodology,.igf-disclaimer{margin:16px 0 0;color:#aaa;font-size:11px;line-height:1.5;text-align:center}.igf-disclaimer{margin-top:9px}
.igf-zakat-context{width:min(100% - 40px,1180px);margin:0 auto;padding:34px 0 72px}.igf-zakat-context a{display:inline-flex;min-height:44px;align-items:center;gap:8px;color:var(--brown);font-weight:800;text-decoration:none}.igf-zakat-context a:hover,.igf-zakat-context a:focus-visible{color:#592900;text-decoration:underline;text-underline-offset:4px}
@media(max-width:920px){.igf-calculator__layout{grid-template-columns:1fr}.igf-calculator__result{position:static}}
@media(max-width:700px){.igf-shell{width:min(100% - 28px,1180px)}.igf-zakat-impact__grid,.igf-calculator__header,.igf-calculator__form,.igf-nisab-choice__options{grid-template-columns:1fr}.igf-calculator__header{gap:16px}.igf-calculator__form{padding:22px 18px}.igf-nisab-choice,.igf-haul,.igf-clear{grid-column:auto}.igf-calculator__result{padding:26px 20px}}
</style>
