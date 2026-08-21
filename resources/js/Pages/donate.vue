<template>
  <Layout>
    <div class="igf-donate" :class="[`is-layout-${checkoutLayout}`, `is-card-${cardStyle}`]">
      <header v-if="settings.show_hero" class="igf-donate__hero">
        <div class="igf-shell igf-donate__hero-grid">
          <div>
            <p class="igf-eyebrow">{{ settings.eyebrow }}</p>
            <h1>{{ settings.title }}</h1>
            <p class="igf-donate__lead">{{ settings.introduction }}</p>
          </div>
          <ul v-if="settings.show_assurances" class="igf-trust-list" :aria-label="settings.assurances_accessible_label">
            <li><i class="fa-solid fa-lock" aria-hidden="true" /><span><strong>{{ settings.secure_title }}</strong>{{ settings.secure_body }}</span></li>
            <li><i class="fa-solid fa-receipt" aria-hidden="true" /><span><strong>{{ settings.confirmation_title }}</strong>{{ settings.confirmation_body }}</span></li>
            <li><i class="fa-solid fa-chart-line" aria-hidden="true" /><span><strong>{{ settings.impact_title }}</strong>{{ settings.impact_body }}</span></li>
          </ul>
        </div>
      </header>

      <section class="igf-donate__section" aria-labelledby="donation-form-title">
        <div class="igf-shell igf-donate__layout">
          <aside v-if="settings.show_intro_panel || settings.show_help_card" class="igf-donate__aside">
            <div v-if="settings.show_intro_panel" class="igf-donate__aside-copy">
              <p class="igf-eyebrow">{{ settings.aside_eyebrow }}</p>
              <h2>{{ settings.aside_title }}</h2>
              <p>{{ settings.aside_body }}</p>
              <a v-if="settings.show_reports_link" :href="settings.reports_url" class="igf-text-link">{{ settings.reports_label }} <span aria-hidden="true">&rarr;</span></a>
            </div>
            <div v-if="settings.show_help_card" class="igf-help-card">
              <i class="fa-regular fa-circle-question" aria-hidden="true" />
              <div><strong>{{ settings.help_title }}</strong><a :href="`mailto:${contact.email}`">{{ contact.email }}</a><a :href="`tel:${contact.phone_primary}`">{{ contact.phone_primary }}</a></div>
            </div>
          </aside>

          <div class="igf-donation-card">
            <div v-if="settings.show_form_badge" class="igf-donation-card__header">
              <span>{{ settings.form_badge }}</span>
              <i class="fa-solid fa-shield-halved" aria-hidden="true" />
            </div>
            <h2 id="donation-form-title">{{ settings.form_title }}</h2>
            <p v-if="settings.show_required_hint" class="igf-card-intro">{{ settings.required_hint }}</p>

            <v-form ref="form" v-model="isFormValid" @submit.prevent="submitDonation">
              <div class="igf-frequency" :aria-label="settings.frequency_accessible_label">
                <span><i class="fa-solid fa-circle-check" aria-hidden="true" /> {{ settings.frequency_label }}</span>
                <small>{{ settings.frequency_help }}</small>
              </div>

              <fieldset class="igf-fieldset">
                <legend>{{ settings.amount_legend }}</legend>
                <div class="igf-amount-options" :style="{ '--amount-columns': amountColumns }" :aria-label="settings.suggested_amounts_label">
                  <button v-for="amount in suggestedAmounts" :key="amount" type="button"
                    :class="{ 'is-selected': Number(donation.amount) === amount }"
                    :aria-pressed="Number(donation.amount) === amount"
                    @click="donation.amount = amount">
                    {{ money(amount) }}
                  </button>
                </div>
                <v-text-field v-if="showCustomAmount" v-model="donation.amount" type="number" :min="MIN_DONATION_AMOUNT" :max="MAX_DONATION_AMOUNT" step="0.01" :label="settings.other_amount_label"
                  variant="outlined" hide-details="auto" :prefix="currencyPrefix" :suffix="currencySuffix" :rules="amountRules" required />
              </fieldset>

              <fieldset class="igf-fieldset">
                <legend>{{ settings.cause_legend }}</legend>
                <label class="igf-native-field" for="donation-cause"><span>{{ settings.cause_field_label }}</span><select id="donation-cause" v-model="donation.payment_cause" :aria-label="settings.cause_field_label" :disabled="donationTypes.length === 0" required><option disabled value="">{{ settings.cause_placeholder }}</option><option v-for="cause in donationTypes" :key="cause.uuid" :value="cause.uuid">{{ cause.name }}</option></select></label>
                <p v-if="settings.cause_help && donationTypes.length > 0" class="igf-field-help">{{ settings.cause_help }}</p>
                <p v-if="donationTypes.length === 0" class="igf-cause-alert" role="status">{{ settings.causes_unavailable_message }}</p>
                <p v-if="selectionWarning" class="igf-selection-warning" role="alert">{{ selectionWarning }}</p>
                <label v-if="selectedCause?.project_selection === 'optional'" class="igf-native-field igf-project-field" for="donation-project">
                  <span>{{ settings.project_field_label }}</span>
                  <select
                    id="donation-project"
                    v-model="donation.project_uuid"
                    aria-describedby="donation-project-help"
                  >
                    <option value="">{{ settings.project_placeholder }}</option>
                    <option v-for="project in projectOptions" :key="project.uuid" :value="project.uuid">{{ project.name }}</option>
                  </select>
                </label>
                <p v-if="selectedCause?.project_selection === 'optional'" id="donation-project-help" class="igf-field-help">{{ settings.project_help }}</p>
                <div v-if="selectedCause?.project_selection === 'fixed'" class="igf-fixed-project" role="status" aria-live="polite">
                  <span>{{ settings.project_field_label }}</span>
                  <strong>{{ selectedProject?.name || confirmedDestinationName }}</strong>
                  <small>{{ settings.destination_page_explanation }}</small>
                </div>
                <div v-if="selectedCause" id="donation-destination-summary" class="igf-destination-summary" role="status" aria-live="polite">
                  <i class="fa-solid fa-location-dot" aria-hidden="true" />
                  <div>
                    <small>{{ settings.destination_label }}</small>
                    <strong>{{ confirmedDestinationName }}</strong>
                    <p>{{ destinationExplanation }}</p>
                  </div>
                </div>
              </fieldset>

              <fieldset class="igf-fieldset igf-payment-methods" :aria-describedby="paymentMethodDescriptionIds" :aria-invalid="showPaymentMethodError ? 'true' : 'false'">
                <legend>{{ settings.payment_method_legend }}</legend>
                <p id="payment-method-help" class="igf-field-help">{{ settings.payment_method_help }}</p>
                <div v-if="paymentMethods.length" class="igf-payment-method-grid" data-test="payment-method-options">
                  <label v-for="method in paymentMethods" :key="method.key"
                    class="igf-payment-method"
                    :class="{ 'is-selected': donation.payment_method === method.key, 'is-unavailable': !method.available }"
                    :for="paymentMethodDomId(method)">
                    <input :id="paymentMethodDomId(method)" v-model="donation.payment_method" type="radio"
                      name="payment_method" :value="method.key" :disabled="!method.available" required
                      :aria-describedby="paymentMethodDescriptionId(method)"
                      @change="paymentMethodTouched = true">
                    <span v-if="method.logos.length" class="igf-payment-method__logos" :class="{ 'has-multiple': method.logos.length > 1 }" aria-hidden="true">
                      <img v-for="logo in method.logos" :key="logo.src" :src="logo.src" alt="" width="64" height="32">
                    </span>
                    <span v-else class="igf-payment-method__icon" aria-hidden="true"><i :class="paymentMethodIcon(method.key)" /></span>
                    <span class="igf-payment-method__copy">
                      <strong>{{ method.label }}</strong>
                      <small v-if="method.description">{{ method.description }}</small>
                      <small v-if="method.networks" class="igf-payment-method__networks">{{ paymentMethodNetworks(method.networks) }}</small>
                      <small v-if="!method.available && hasAvailablePaymentMethod" :id="`${paymentMethodDomId(method)}-unavailable`" class="igf-payment-method__unavailable">
                        {{ method.unavailable_reason || settings.payment_method_unavailable_label }}
                      </small>
                    </span>
                    <span v-if="method.available" class="igf-payment-method__check" aria-hidden="true"><i class="fa-solid fa-check" /></span>
                  </label>
                </div>
                <p v-if="!hasAvailablePaymentMethod" id="payment-methods-unavailable" class="igf-cause-alert" role="status">{{ settings.payment_methods_unavailable_message }}</p>
                <p v-if="showPaymentMethodError" id="payment-method-error" class="igf-field-error" role="alert">{{ settings.payment_method_required_message }}</p>
              </fieldset>

              <fieldset class="igf-fieldset">
                <legend>{{ settings.details_legend }}</legend>
                <div class="igf-details-grid">
                  <v-text-field v-model="donation.donor_name" :label="settings.name_field_label" autocomplete="name" variant="outlined"
                    hide-details="auto" :rules="[v => !!v || settings.name_required_message]" required />
                  <v-text-field v-model="donation.email" :label="settings.email_field_label" autocomplete="email" type="email"
                    variant="outlined" hide-details="auto" :rules="emailRules" required />
                  <v-text-field v-model="donation.phone" :label="settings.phone_field_label" autocomplete="tel" inputmode="tel"
                    variant="outlined" hide-details="auto" :rules="[v => !!v || settings.phone_required_message]" required />
                  <v-text-field v-model="donation.address" :label="settings.address_field_label" autocomplete="street-address" variant="outlined"
                    hide-details="auto" :rules="[v => !!v || settings.address_required_message]" required />
                </div>
              </fieldset>

              <div v-if="settings.show_gateway_note" class="igf-gateway-note">
                <i class="fa-solid fa-shield-halved" aria-hidden="true" />
                <div><strong>{{ settings.gateway_heading }}</strong><p>{{ settings.gateway_note }}</p></div>
              </div>
              <p class="igf-privacy-note"><i class="fa-solid fa-lock" aria-hidden="true" /> {{ settings.privacy_note }}</p>
              <v-btn type="submit" class="igf-submit" block :disabled="!canAttemptSubmit" :loading="loading">
                {{ settings.submit_label }} <span aria-hidden="true">&rarr;</span>
              </v-btn>
              <p v-if="settings.show_legal_links" class="igf-terms">
                {{ settings.legal_prefix }}
                <a v-if="settings.privacy_link_url && settings.privacy_link_label" :href="settings.privacy_link_url">{{ settings.privacy_link_label }}</a><template v-if="settings.privacy_link_url && settings.refund_link_url"> {{ settings.legal_joiner }} </template><a v-if="settings.refund_link_url && settings.refund_link_label" :href="settings.refund_link_url">{{ settings.refund_link_label }}</a>.
                {{ settings.redirect_note }}
              </p>
            </v-form>
          </div>
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed, ref, onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Layout from '../layouts/App.vue';
import { useGlobal } from '../Shared/composables/global';
import { donationAmountFromUrl, formatMoney, interpolateSetting, regionalSettings } from '../Shared/composables/siteSettings';

const inertiaPage = usePage();
const { $toast } = useGlobal();
const settings = computed(() => inertiaPage.props.siteSettings?.donation_page || {});
const contact = computed(() => inertiaPage.props.siteSettings?.contact || {});
const regional = computed(() => regionalSettings(inertiaPage.props.siteSettings?.regional));
const currencyPrefix = computed(() => regional.value.currency_position === 'before' ? regional.value.currency_symbol : '');
const currencySuffix = computed(() => regional.value.currency_position === 'after' ? regional.value.currency_symbol : '');
const checkoutLayout = computed(() => ['centered', 'split'].includes(settings.value.checkout_layout) ? settings.value.checkout_layout : 'centered');
const cardStyle = computed(() => ['soft', 'outlined', 'elevated'].includes(settings.value.card_style) ? settings.value.card_style : 'soft');
const MIN_DONATION_AMOUNT = 10;
const MAX_DONATION_AMOUNT = 500000;
const SAFE_PAYMENT_METHOD_LOGOS = Object.freeze({
  bkash: ['/image/payment-methods/bkash.png'],
  nagad: ['/image/payment-methods/nagad.png'],
  card: ['/image/payment-methods/visa.png', '/image/payment-methods/amex.png'],
});
const amountButtonCount = computed(() => Math.min(5, Math.max(2, Number(settings.value.amount_button_count) || 5)));
const suggestedAmounts = computed(() => [settings.value.amount_1, settings.value.amount_2, settings.value.amount_3, settings.value.amount_4, settings.value.amount_5]
  .map(Number).filter(isAllowedDonationAmount).slice(0, amountButtonCount.value));
const showCustomAmount = computed(() => settings.value.show_custom_amount !== false);
const amountColumns = computed(() => String(Math.min(suggestedAmounts.value.length || 2, 5)));
const paymentMethods = computed(() => {
  const methods = Array.isArray(inertiaPage.props.data?.paymentMethods)
    ? inertiaPage.props.data.paymentMethods
    : [];

  return methods
    .filter(method => method && method.enabled !== false)
    .map(method => ({
      key: String(method.key || ''),
      label: String(method.label || method.key || ''),
      description: String(method.description || ''),
      networks: method.networks || '',
      logos: safePaymentMethodLogos(method.key, method.logos),
      available: method.available !== false,
      unavailable_reason: String(method.unavailable_reason || ''),
    }))
    .filter(method => method.key !== '');
});
const donationTypes = ref([]);
const form = ref(null);
const isFormValid = ref(false);
const loading = ref(false);
const paymentMethodTouched = ref(false);
const selectionWarning = ref(String(inertiaPage.props.data?.selection_warning || ''));
const donation = ref({ amount: '', donor_name: '', email: '', phone: '', address: '', payment_cause: '', project_uuid: '', payment_method: '', checkout_key: '' });
const submittedPayloadFingerprint = ref(null);
const checkoutKeyNeedsRefresh = ref(false);
const selectedCause = computed(() => donationTypes.value.find(cause => [cause.uuid, cause.slug].includes(donation.value.payment_cause)) || null);
const projectOptions = computed(() => Array.isArray(selectedCause.value?.projects) ? selectedCause.value.projects : []);
const selectedProject = computed(() => projectOptions.value.find(project => project.uuid === donation.value.project_uuid) || null);
const projectSelectionSatisfied = computed(() => selectedCause.value?.project_selection !== 'fixed' || !!selectedProject.value);
const confirmedDestinationName = computed(() => selectedProject.value?.name
  || (selectedCause.value?.destination_type === 'unrestricted' ? selectedCause.value?.name : selectedCause.value?.destination_name)
  || selectedCause.value?.name
  || '');
const destinationExplanation = computed(() => {
  if (selectedCause.value?.destination_type === 'page') return settings.value.destination_page_explanation;
  if (selectedCause.value?.destination_type === 'category') return settings.value.destination_category_explanation;
  return settings.value.destination_unrestricted_explanation;
});
const selectedPaymentMethodAvailable = computed(() => paymentMethods.value.some(method => method.available && method.key === donation.value.payment_method));
const hasAvailablePaymentMethod = computed(() => paymentMethods.value.some(method => method.available));
const showPaymentMethodError = computed(() => paymentMethodTouched.value && !selectedPaymentMethodAvailable.value);
const paymentMethodDescriptionIds = computed(() => showPaymentMethodError.value ? 'payment-method-help payment-method-error' : 'payment-method-help');
const canAttemptSubmit = computed(() => isFormValid.value
  && isAllowedDonationAmount(donation.value.amount)
  && !!donation.value.payment_cause
  && projectSelectionSatisfied.value
  && !loading.value
  && donationTypes.value.length > 0
  && hasAvailablePaymentMethod.value);
const canSubmit = computed(() => canAttemptSubmit.value && selectedPaymentMethodAvailable.value);

const emailRules = computed(() => [
  value => !!value || settings.value.email_required_message,
  value => /.+@.+\..+/.test(value) || settings.value.invalid_email_message,
]);
const amountRules = computed(() => [
  value => !!value || settings.value.amount_required_message,
  value => Number(value) >= MIN_DONATION_AMOUNT || interpolateSetting(settings.value.minimum_amount_message, { currency: regional.value.currency_code }),
  value => Number(value) <= MAX_DONATION_AMOUNT || interpolateSetting(settings.value.maximum_amount_message, { currency: regional.value.currency_code }),
  value => hasSupportedCurrencyPrecision(value) || settings.value.amount_precision_message,
]);
const money = amount => formatMoney(amount, regional.value);

const materialPayloadFingerprint = computed(() => JSON.stringify({
  amount: isAllowedDonationAmount(donation.value.amount) ? Number(donation.value.amount).toFixed(2) : String(donation.value.amount || '').trim(),
  donor_name: String(donation.value.donor_name || '').trim(),
  email: String(donation.value.email || '').trim().toLowerCase(),
  phone: String(donation.value.phone || '').trim(),
  address: String(donation.value.address || '').trim(),
  payment_cause: String(donation.value.payment_cause || ''),
  project_uuid: String(donation.value.project_uuid || ''),
  payment_method: String(donation.value.payment_method || ''),
}));

watch(materialPayloadFingerprint, fingerprint => {
  checkoutKeyNeedsRefresh.value = submittedPayloadFingerprint.value !== null
    && fingerprint !== submittedPayloadFingerprint.value;
});

function isAllowedDonationAmount(value) {
  const amount = Number(value);
  return Number.isFinite(amount)
    && amount >= MIN_DONATION_AMOUNT
    && amount <= MAX_DONATION_AMOUNT
    && hasSupportedCurrencyPrecision(value);
}

function hasSupportedCurrencyPrecision(value) {
  return /^(?:0|[1-9]\d{0,5})(?:\.\d{1,2})?$/.test(String(value ?? '').trim());
}

function paymentMethodDomId(method) {
  return `payment-method-${String(method.key).replace(/[^a-z0-9_-]/gi, '-')}`;
}

function paymentMethodIcon(key) {
  return key === 'card' ? 'fa-regular fa-credit-card' : 'fa-solid fa-mobile-screen-button';
}

function paymentMethodDescriptionId(method) {
  if (method.available) {
    return 'payment-method-help';
  }

  return hasAvailablePaymentMethod.value
    ? `${paymentMethodDomId(method)}-unavailable`
    : 'payment-methods-unavailable';
}

function safePaymentMethodLogos(key, logos) {
  const allowedPaths = SAFE_PAYMENT_METHOD_LOGOS[String(key)] || [];

  if (!Array.isArray(logos) || allowedPaths.length === 0) {
    return [];
  }

  const seenPaths = new Set();

  return logos.reduce((safeLogos, logo) => {
    const src = logo && typeof logo === 'object' ? String(logo.src || '') : '';

    if (!allowedPaths.includes(src) || seenPaths.has(src)) {
      return safeLogos;
    }

    seenPaths.add(src);
    safeLogos.push({ src });

    return safeLogos;
  }, []);
}

function paymentMethodNetworks(networks) {
  return Array.isArray(networks) ? networks.filter(Boolean).join(' · ') : String(networks || '');
}

let initializingCause = true;
watch(() => donation.value.payment_cause, () => {
  if (initializingCause) return;
  selectionWarning.value = '';
  syncProjectForCause();
}, { flush: 'sync' });

function syncProjectForCause(preferredProjectUuid = '') {
  const cause = selectedCause.value;
  if (!cause || cause.project_selection === 'none') {
    donation.value.project_uuid = '';
    return;
  }

  const projects = projectOptions.value;
  if (cause.project_selection === 'fixed') {
    donation.value.project_uuid = projects[0]?.uuid || '';
    return;
  }

  donation.value.project_uuid = projects.some(project => project.uuid === preferredProjectUuid)
    ? preferredProjectUuid
    : '';
}

onMounted(() => {
  donationTypes.value = Array.isArray(inertiaPage.props.data?.donationTypes) ? inertiaPage.props.data.donationTypes : [];
  donation.value.payment_cause = inertiaPage.props.data?.selectedUUID || '';
  syncProjectForCause(String(inertiaPage.props.data?.selectedProjectUUID || ''));
  initializingCause = false;
  donation.value.checkout_key = String(inertiaPage.props.data?.checkout_key || '');
  const requestedAmount = donationAmountFromUrl(inertiaPage.url, {
    allowCustomAmount: showCustomAmount.value,
    visibleSuggestedAmounts: suggestedAmounts.value,
  });
  if (requestedAmount !== null && isAllowedDonationAmount(requestedAmount)) {
    donation.value.amount = requestedAmount;
  } else if (!showCustomAmount.value && suggestedAmounts.value.length > 0) {
    donation.value.amount = suggestedAmounts.value[0];
  }
});

async function submitDonation() {
  paymentMethodTouched.value = true;
  const { valid } = await form.value.validate();
  if (!valid || !canSubmit.value) return;
  loading.value = true;
  try {
    if (checkoutKeyNeedsRefresh.value || !donation.value.checkout_key) {
      const refreshed = await refreshCheckoutKey();
      if (!refreshed) return;
    }

    submittedPayloadFingerprint.value = materialPayloadFingerprint.value;
    checkoutKeyNeedsRefresh.value = false;
    const response = await axios.post(route('frontend.donate.store'), donation.value);
    acceptReplacementCheckoutKey(response.data?.replacement_checkout_key);
    if (response.data.status && response.data.payment_url) {
      window.location.assign(response.data.payment_url);
      return;
    }
    $toast.error(response.data.message || settings.value.initialization_error_message || 'Payment initialization failed.');
  } catch (error) {
    acceptReplacementCheckoutKey(error.response?.data?.replacement_checkout_key);
    const errors = error.response?.data?.errors;
    const message = errors ? Object.values(errors).flat()[0] : error.response?.data?.message;
    $toast.error(message || settings.value.initialization_error_message || 'We could not start the payment. Please try again.');
  } finally {
    loading.value = false;
  }
}

async function refreshCheckoutKey() {
  try {
    const response = await axios.get(route('frontend.donate.checkout-key'), {
      headers: { Accept: 'application/json' },
    });
    const key = String(response.data?.checkout_key || '');
    if (!response.data?.status || key === '') {
      throw new Error('The server did not provide a checkout key.');
    }
    donation.value.checkout_key = key;
    submittedPayloadFingerprint.value = null;
    checkoutKeyNeedsRefresh.value = false;
    return true;
  } catch {
    $toast.error(settings.value.initialization_error_message || 'We could not prepare a new payment attempt. Please try again.');
    return false;
  }
}

function acceptReplacementCheckoutKey(value) {
  const key = String(value || '');
  if (key === '') return;
  donation.value.checkout_key = key;
  submittedPayloadFingerprint.value = null;
  checkoutKeyNeedsRefresh.value = false;
}
</script>

<style scoped lang="scss">
.igf-donate { --orange:#ff7500; --action-orange:#9c4500; --action-orange-hover:#783300; --brown:#9c4500; --ink:#191c1d; --muted:#5e5d66; --surface:#f8f9fa; --line:#e4ded9; overflow:hidden; background:#fff; color:var(--ink); font-family:'Hanken Grotesk',Arial,sans-serif; }
.igf-shell { width:min(calc(100% - 40px),1200px); margin-inline:auto; }
.igf-donate__hero { padding:clamp(80px,10vw,130px) 0 clamp(70px,8vw,105px); background:#211f1e; color:#fff; }
.igf-donate__hero-grid { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(300px,.75fr); align-items:end; gap:clamp(40px,8vw,100px); }
.igf-eyebrow { margin:0 0 15px; color:#ffad72; font-size:12px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
.igf-donate h1,.igf-donate h2 { font-family:'Literata',Georgia,serif; letter-spacing:-.03em; }
.igf-donate h1 { max-width:780px; margin:0; font-size:clamp(44px,6vw,72px); font-weight:650; line-height:1.05; }
.igf-donate__lead { max-width:700px; margin:24px 0 0; color:#ddd9d6; font-size:clamp(18px,2vw,21px); line-height:1.65; }
.igf-trust-list { display:grid; gap:18px; margin:0; padding:0; list-style:none; }
.igf-trust-list li { display:grid; grid-template-columns:38px 1fr; gap:13px; align-items:start; }
.igf-trust-list i { display:grid; width:38px; height:38px; place-items:center; border:1px solid rgba(255,173,114,.36); border-radius:50%; color:#ffad72; }
.igf-trust-list strong,.igf-trust-list span { display:block; }
.igf-trust-list strong { margin-bottom:3px; color:#fff; font-size:14px; }
.igf-trust-list span { color:#bdb9b6; font-size:12px; line-height:1.5; }
.igf-donate__section { padding:clamp(70px,9vw,120px) 0; background:var(--surface); }
.igf-donate__layout { display:grid; grid-template-columns:minmax(260px,.72fr) minmax(0,1.28fr); align-items:start; gap:clamp(45px,8vw,105px); }
.igf-donate__aside { position:sticky; top:120px; padding-top:30px; }
.igf-donate__aside .igf-eyebrow { color:var(--brown); }
.igf-donate__aside h2 { margin:0 0 20px; font-size:clamp(32px,4vw,46px); font-weight:620; line-height:1.12; }
.igf-donate__aside-copy>p:not(.igf-eyebrow) { color:var(--muted); font-size:16px; line-height:1.7; }
.igf-text-link { display:inline-flex; gap:8px; margin-top:12px; color:var(--brown); font-weight:800; text-decoration:none; }
.igf-help-card { display:flex; gap:13px; margin-top:40px; border-top:1px solid var(--line); padding-top:24px; }
.igf-help-card>i { color:var(--orange); font-size:21px; }
.igf-help-card strong,.igf-help-card a { display:block; }
.igf-help-card a { margin-top:4px; color:var(--muted); font-size:13px; text-decoration:none; }
.igf-donation-card { border:1px solid var(--line); border-top:5px solid var(--orange); border-radius:18px; padding:clamp(25px,5vw,52px); background:#fff; box-shadow:0 12px 34px rgba(25,28,29,.07); }
.is-card-outlined .igf-donation-card { border-width:2px; border-top-width:5px; box-shadow:none; }
.is-card-elevated .igf-donation-card { border-color:transparent; box-shadow:0 22px 55px rgba(25,28,29,.16); }
.is-card-soft .igf-donation-card { background:linear-gradient(145deg,#fff 0%,#fffaf6 100%); }
.is-layout-centered .igf-donate__layout { width:min(calc(100% - 40px),930px); grid-template-columns:1fr; gap:32px; }
.is-layout-centered .igf-donate__aside { position:static; display:grid; grid-template-columns:minmax(0,1fr) minmax(220px,.52fr); align-items:end; gap:30px; padding-top:0; }
.is-layout-centered .igf-help-card { margin-top:0; border:1px solid var(--line); border-radius:12px; padding:20px; background:#fff; }
.igf-donation-card__header { display:flex; justify-content:space-between; margin-bottom:8px; color:var(--brown); font-size:11px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
.igf-donation-card h2 { margin:0; font-size:clamp(34px,4vw,46px); font-weight:650; }
.igf-card-intro { margin:10px 0 24px; color:var(--muted); font-size:13px; }
.igf-frequency { display:grid; grid-template-columns:auto 1fr; align-items:center; gap:14px; margin:0 0 26px; border:1px solid #f0c4a4; border-radius:10px; padding:13px 15px; background:#fff6ef; }
.igf-frequency span { color:var(--brown); font-size:13px; font-weight:850; white-space:nowrap; }
.igf-frequency i { margin-right:5px; color:var(--orange); }
.igf-frequency small { color:#786c64; font-size:11px; line-height:1.45; }
.igf-fieldset { min-width:0; margin:0 0 30px; border:0; border-top:1px solid var(--line); padding:26px 0 0; }
.igf-fieldset legend { width:auto; margin:0 0 16px; padding:0; color:var(--ink); font-size:14px; font-weight:800; }
.igf-amount-options { display:grid; grid-template-columns:repeat(var(--amount-columns,5),minmax(0,1fr)); gap:9px; margin-bottom:14px; }
.igf-amount-options button { min-height:48px; border:1px solid #cfc7c1; border-radius:8px; background:#fff; color:var(--ink); font-weight:800; cursor:pointer; }
.igf-amount-options button:hover,.igf-amount-options button.is-selected { border-color:var(--orange); background:#fff3e9; color:var(--brown); box-shadow:inset 0 0 0 1px var(--orange); }
.igf-details-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.igf-donation-card :deep(.v-field) { border-radius:9px; background:#fff; }
.igf-donation-card :deep(.v-field--focused) { color:var(--brown); }
.igf-native-field{display:grid;gap:7px;color:var(--ink);font-size:12px;font-weight:700}.igf-native-field select{width:100%;min-height:56px;border:1px solid #79747e;border-radius:9px;padding:0 15px;background:#fff;color:var(--ink);font:500 16px 'Hanken Grotesk',Arial,sans-serif}.igf-native-field select:focus{border:2px solid var(--brown);outline:2px solid transparent}
.igf-field-help { margin:9px 0 0; color:var(--muted); font-size:12px; line-height:1.5; }
.igf-cause-alert { margin:12px 0 0!important; padding:12px 13px; border-radius:8px; background:#fff3e9; color:var(--brown)!important; font-size:12px!important; line-height:1.5; }
.igf-selection-warning { margin:12px 0 0; padding:12px 13px; border-left:4px solid #a52b1a; border-radius:8px; background:#fff1ef; color:#842516; font-size:12px; font-weight:700; line-height:1.5; }
.igf-project-field { margin-top:18px; }
.igf-fixed-project { display:grid; gap:4px; margin-top:18px; padding:14px 15px; border:1px solid #d7d0ca; border-radius:9px; background:#f7f5f3; color:var(--ink); }
.igf-fixed-project span { color:var(--brown); font-size:10px; font-weight:850; letter-spacing:.05em; text-transform:uppercase; }.igf-fixed-project strong { font-size:15px; line-height:1.35; }.igf-fixed-project small { color:var(--muted); font-size:11px; line-height:1.45; }
.igf-destination-summary { display:grid; grid-template-columns:38px minmax(0,1fr); align-items:start; gap:12px; margin-top:18px; padding:15px; border:1px solid #e8c8b0; border-radius:11px; background:#fff7f0; }
.igf-destination-summary>i { display:grid; width:38px; height:38px; place-items:center; border-radius:50%; background:#fff; color:var(--brown); }.igf-destination-summary small,.igf-destination-summary strong { display:block; }.igf-destination-summary small { margin-bottom:3px; color:var(--brown); font-size:10px; font-weight:850; letter-spacing:.06em; text-transform:uppercase; }.igf-destination-summary strong { color:var(--ink); font-size:15px; line-height:1.35; }.igf-destination-summary p { margin:5px 0 0; color:var(--muted); font-size:11px; line-height:1.5; }
.igf-payment-methods>.igf-field-help { margin:-7px 0 15px; }
.igf-payment-method-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:11px; }
.igf-payment-method { position:relative; display:grid; grid-template-columns:58px minmax(0,1fr) 24px; align-items:start; gap:9px; min-height:122px; margin:0; border:1px solid #d2cbc5; border-radius:12px; padding:17px 12px; background:#fff; color:var(--ink); cursor:pointer; transition:border-color .16s,background-color .16s,box-shadow .16s,transform .16s; }
.igf-payment-method:hover { border-color:#dd9461; transform:translateY(-1px); }
.igf-payment-method:focus-within { border-color:var(--orange); outline:3px solid rgba(255,117,0,.23); outline-offset:3px; }
.igf-payment-method.is-selected { border-color:var(--orange); background:#fff5ec; box-shadow:inset 0 0 0 1px var(--orange); }
.igf-payment-method.is-unavailable { grid-template-columns:58px minmax(0,1fr); border-style:dashed; background:#f4f2f0; color:#6f6965; cursor:not-allowed; }
.igf-payment-method.is-unavailable:hover { border-color:#d2cbc5; transform:none; }
.igf-payment-method>input { position:absolute; width:1px; height:1px; margin:0; opacity:0; pointer-events:none; }
.igf-payment-method__icon { display:grid; width:42px; height:42px; place-items:center; border-radius:11px; background:#fff0e4; color:var(--brown); font-size:18px; }
.igf-payment-method.is-unavailable .igf-payment-method__icon { background:#e7e3df; color:#756e69; }
.igf-payment-method__logos { display:flex; width:58px; height:42px; align-items:center; justify-content:center; gap:5px; border-radius:9px; padding:5px; background:#fff; }
.igf-payment-method__logos img { display:block; width:auto; max-width:48px; height:auto; max-height:30px; object-fit:contain; }
.igf-payment-method__logos.has-multiple img { max-width:22px; max-height:24px; }
.igf-payment-method__copy { display:grid; align-content:start; gap:4px; min-width:0; }
.igf-payment-method__copy strong { font-size:14px; line-height:1.3; }
.igf-payment-method__copy small { color:var(--muted); font-size:10px; font-weight:500; line-height:1.42; }
.igf-payment-method__copy .igf-payment-method__networks { color:var(--brown); font-weight:800; letter-spacing:.02em; }
.igf-payment-method__copy .igf-payment-method__unavailable { color:#7a3d19; font-weight:750; }
.igf-payment-method__check { display:grid; width:22px; height:22px; place-items:center; border:1px solid #cfc7c1; border-radius:50%; background:#fff; color:transparent; font-size:10px; }
.igf-payment-method.is-selected .igf-payment-method__check { border-color:var(--action-orange); background:var(--action-orange); color:#fff; }
.igf-field-error { margin:10px 0 0; color:#a52b1a; font-size:12px; font-weight:750; line-height:1.5; }
.igf-payment-methods[aria-invalid="true"] .igf-payment-method-grid { border-radius:14px; outline:2px solid rgba(165,43,26,.2); outline-offset:4px; }
.igf-gateway-note { display:grid; grid-template-columns:38px 1fr; gap:12px; margin-bottom:18px; border:1px solid #eadfd6; border-radius:10px; padding:15px; background:#f8f5f2; }
.igf-gateway-note>i { display:grid; width:38px; height:38px; place-items:center; border-radius:50%; background:#fff; color:var(--brown); }
.igf-gateway-note strong { font-size:13px; }
.igf-gateway-note p { margin:3px 0 0; color:var(--muted); font-size:11px; line-height:1.55; }
.igf-privacy-note { display:flex; gap:9px; margin:0 0 18px; color:var(--muted); font-size:12px; line-height:1.55; }
.igf-privacy-note i { margin-top:2px; color:var(--brown); }
.igf-submit { min-height:54px!important; border-radius:999px!important; background:var(--action-orange)!important; color:#fff!important; font-size:13px!important; font-weight:800!important; letter-spacing:.035em!important; text-transform:uppercase!important; box-shadow:0 7px 18px rgba(156,69,0,.24)!important; }
.igf-submit:hover { background:var(--action-orange-hover)!important; }
.igf-submit:focus-visible { outline:3px solid rgba(156,69,0,.3)!important; outline-offset:3px!important; }
.igf-terms { margin:15px 0 0; color:#777277; font-size:11px; line-height:1.5; text-align:center; }
.igf-terms a { color:var(--brown); }
.sr-only { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; }
@media (max-width:900px) { .igf-donate__hero-grid,.igf-donate__layout { grid-template-columns:1fr; } .igf-trust-list { grid-template-columns:repeat(3,1fr); } .igf-donate__aside,.is-layout-centered .igf-donate__aside { position:static; grid-template-columns:1fr; padding-top:0; } .is-layout-centered .igf-help-card { margin-top:0; } }
@media (max-width:700px) { .igf-amount-options { grid-template-columns:1fr 1fr; } .igf-frequency { grid-template-columns:1fr; } }
@media (max-width:620px) { .igf-payment-method-grid { grid-template-columns:1fr; } .igf-payment-method { grid-template-columns:70px minmax(0,1fr) 24px; padding-inline:15px; } .igf-payment-method.is-unavailable { grid-template-columns:70px minmax(0,1fr); } .igf-payment-method__logos { width:70px; } .igf-payment-method__logos img { max-width:60px; } .igf-payment-method__logos.has-multiple img { max-width:27px; } }
@media (max-width:640px) { .igf-shell,.is-layout-centered .igf-donate__layout { width:min(calc(100% - 28px),1200px); } .igf-trust-list,.igf-details-grid { grid-template-columns:1fr; } .igf-donate__hero { padding-block:70px; } .igf-donation-card { border-radius:13px; padding-inline:20px; } }
</style>
