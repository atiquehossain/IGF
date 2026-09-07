<template>
  <Layout>
    <div class="igf-sponsor">
      <section class="igf-sponsor__hero" aria-labelledby="sponsor-page-title">
        <div class="igf-sponsor__hero-inner">
          <div ref="choiceCard" class="igf-sponsor__choice-card">
            <p class="igf-eyebrow">{{ settings.eyebrow }}</p>
            <h1 id="sponsor-page-title">{{ settings.title }}</h1>
            <p class="igf-sponsor__introduction">{{ settings.introduction }}</p>

            <template v-if="currentStep === 1">
              <div class="igf-sponsor__progress-heading">
                <span data-test="sponsor-progress">{{ settings.selection_step_label || 'Step 1 of 2' }}</span>
                <span class="igf-sponsor__progress-track" role="progressbar" aria-valuemin="1" aria-valuemax="2" aria-valuenow="1" :aria-valuetext="settings.selection_step_label || 'Step 1 of 2'"><span /></span>
              </div>

              <div class="igf-sponsor__choice-heading">
                <h2 ref="choiceHeading" tabindex="-1">{{ settings.selection_title || 'Choose your sponsorship plan' }}</h2>
                <p>{{ settings.selection_body || 'Choose a contribution schedule and the number of children you would like to support.' }}</p>
              </div>

              <div class="igf-sponsor__intervals" role="radiogroup" :aria-label="settings.interval_field_label">
                <button
                  v-for="(interval, index) in contributionIntervals"
                  :key="interval.value"
                  type="button"
                  role="radio"
                  class="igf-sponsor__interval"
                  :class="{ 'is-selected': sponsorship.contributionInterval === interval.value }"
                  :aria-checked="sponsorship.contributionInterval === interval.value"
                  :tabindex="sponsorship.contributionInterval === interval.value ? 0 : -1"
                  :aria-label="intervalChoiceLabel(interval)"
                  :data-test="`interval-${interval.value}`"
                  @click="selectInterval(interval.value)"
                  @keydown="handleIntervalKeydown($event, index)"
                >
                  <span>{{ interval.label }}</span>
                  <small>{{ money(baseContributionAmount * interval.multiplier) }}</small>
                </button>
              </div>

              <div class="igf-sponsor__selection-grid">
                <div class="igf-sponsor__children-choice">
                  <label for="sponsor-children-count">{{ settings.children_field_label }}</label>
                  <div class="igf-sponsor__stepper">
                    <button type="button" data-test="child-decrease" :aria-label="settings.decrease_children_label || 'Sponsor one fewer child'" :disabled="normalisedChildCount <= 1" @click="adjustChildren(-1)">
                      <i class="fa-solid fa-minus" aria-hidden="true" />
                    </button>
                    <input
                      id="sponsor-children-count"
                      v-model.number="sponsorship.numberOfChildren"
                      data-test="child-count"
                      type="number"
                      min="1"
                      max="100"
                      inputmode="numeric"
                      :aria-describedby="settings.minimum_children_message ? 'sponsor-children-help' : undefined"
                      @change="normaliseChildren"
                      @blur="normaliseChildren"
                    >
                    <button type="button" data-test="child-increase" :aria-label="settings.increase_children_label || 'Sponsor one more child'" :disabled="normalisedChildCount >= 100" @click="adjustChildren(1)">
                      <i class="fa-solid fa-plus" aria-hidden="true" />
                    </button>
                  </div>
                  <small v-if="settings.minimum_children_message" id="sponsor-children-help">{{ settings.minimum_children_message }}</small>
                </div>

                <div class="igf-sponsor__choice-total" data-test="contribution-total" aria-live="polite" aria-atomic="true">
                  <span>{{ contributionSummary }}</span>
                  <strong>{{ money(calculatedDonationAmount) }}</strong>
                  <small>{{ normalisedChildCount }} {{ childLabel }} &times; {{ money(baseContributionAmount) }} &times; {{ intervalMultiplier }} {{ monthLabel }}</small>
                </div>
              </div>

              <p class="igf-sponsor__impact-message">{{ selectionImpact }}</p>
              <button type="button" class="igf-sponsor__continue" data-test="continue-details" @click="continueToDetails">
                <span>{{ settings.hero_cta_label }}</span><i class="fa-solid fa-arrow-right" aria-hidden="true" />
              </button>
              <p class="igf-sponsor__request-note"><i class="fa-solid fa-shield-halved" aria-hidden="true" /> {{ settings.selection_note || settings.confirmation_note }}</p>

              <ul class="igf-sponsor__trust-list" :aria-label="settings.assurances_label">
                <li v-for="assurance in assurances" :key="assurance.label"><i :class="assurance.icon" aria-hidden="true" /> {{ assurance.label }}</li>
              </ul>
            </template>

            <template v-else-if="currentStep === 2">
              <div class="igf-request-form__heading">
                <span>{{ settings.details_step_label || 'Step 2 of 2' }}</span>
                <span class="igf-sponsor__progress-track" role="progressbar" aria-valuemin="1" aria-valuemax="2" aria-valuenow="2" :aria-valuetext="settings.details_step_label || 'Step 2 of 2'"><span /></span>
              </div>
              <div class="igf-step-two__header">
                <div>
                  <p class="igf-eyebrow">{{ settings.form_eyebrow }}</p>
                  <h2 id="sponsor-form-title" ref="detailsHeading" tabindex="-1">{{ settings.form_title }}</h2>
                  <p>{{ settings.form_body }}</p>
                </div>
                <div class="igf-total-card">
                  <span>{{ contributionSummary }}</span>
                  <strong>{{ money(calculatedDonationAmount) }}</strong>
                  <small>{{ normalisedChildCount }} {{ childLabel }} &times; {{ money(baseContributionAmount) }} &times; {{ intervalMultiplier }} {{ monthLabel }}</small>
                  <button type="button" data-test="change-sponsorship-choice" @click="returnToChoices"><i class="fa-solid fa-pen" aria-hidden="true" /> {{ settings.edit_selection_label || 'Change sponsorship choice' }}</button>
                </div>
              </div>

              <v-form ref="sponsorForm" v-model="isFormValid" class="igf-request-form" data-test="sponsorship-details" autocomplete="on" @submit.prevent="handleSubmit">
                <div class="igf-form-grid">
                  <v-text-field id="sponsor-name" v-model="sponsorship.name" :label="settings.name_field_label" autocomplete="name" variant="outlined" hide-details="auto" :rules="[required(settings.name_field_label || 'Name')]" required />
                  <v-text-field v-model="sponsorship.email" :label="settings.email_field_label" autocomplete="email" type="email" variant="outlined" hide-details="auto" :rules="emailRules" required />
                  <v-text-field v-model="sponsorship.phone" :label="settings.phone_field_label" autocomplete="tel" type="tel" variant="outlined" hide-details="auto" />
                  <v-text-field v-model="sponsorship.address" :label="settings.address_field_label" autocomplete="street-address" variant="outlined" hide-details="auto" />
                </div>
                <p class="igf-privacy"><i class="fa-solid fa-shield-halved" aria-hidden="true" /> {{ settings.privacy_note }}</p>
                <button class="igf-submit" data-test="submit-sponsorship" type="submit" :disabled="!isFormValid || loading">
                  <span>{{ loading ? settings.sending_label : settings.submit_label }}</span><i class="fa-solid fa-arrow-right" aria-hidden="true" />
                </button>
              </v-form>
            </template>

            <div v-else class="igf-sponsor__success" data-test="sponsorship-success" aria-labelledby="sponsor-success-title">
              <div class="igf-sponsor__success-announcement" role="status" aria-live="polite" aria-atomic="true">
                <span class="igf-sponsor__success-icon"><i class="fa-solid fa-check" aria-hidden="true" /></span>
                <p class="igf-eyebrow">{{ settings.success_eyebrow || 'Request sent' }}</p>
                <h2 id="sponsor-success-title" ref="successHeading" tabindex="-1">{{ settings.success_title || 'Your sponsorship request is received.' }}</h2>
                <p>{{ settings.success_body || successMessage }}</p>
              </div>
              <div v-if="submittedRequest" class="igf-total-card igf-total-card--success">
                <span>{{ submittedRequest.summary }}</span>
                <strong>{{ money(submittedRequest.amount) }}</strong>
                <small>{{ submittedRequest.count }} {{ submittedRequest.children }} &times; {{ money(baseContributionAmount) }} &times; {{ submittedRequest.multiplier }} {{ submittedRequest.months }}</small>
              </div>
              <div class="igf-sponsor__next-step">
                <strong>{{ settings.success_next_title || 'What happens next?' }}</strong>
                <p>{{ settings.success_next_body || 'Our team will review your request and contact you to explain the secure next steps. No payment has been taken.' }}</p>
              </div>
              <button type="button" class="igf-sponsor__continue igf-sponsor__continue--secondary" data-test="start-another-sponsorship" @click="startAnotherRequest">
                <span>{{ settings.success_restart_label || 'Send another request' }}</span><i class="fa-solid fa-rotate-right" aria-hidden="true" />
              </button>
            </div>
          </div>

          <figure class="igf-sponsor__hero-media">
            <picture>
              <source v-if="heroMedia.avifSrcset" type="image/avif" :srcset="heroMedia.avifSrcset" :sizes="heroMedia.sizes">
              <source v-if="heroMedia.webpSrcset" type="image/webp" :srcset="heroMedia.webpSrcset" :sizes="heroMedia.sizes">
              <img :src="heroMedia.src" :alt="settings.hero_image_alt" :width="heroMedia.width" :height="heroMedia.height" loading="eager" fetchpriority="high" decoding="async">
            </picture>
            <figcaption><span>{{ settings.monthly_period_label }}</span><strong>{{ money(baseContributionAmount) }}</strong></figcaption>
          </figure>
        </div>
      </section>

      <section class="igf-sponsor__impact" aria-labelledby="impact-heading">
        <div class="igf-shell">
          <header class="igf-section-heading">
            <div><p class="igf-eyebrow">{{ settings.impact_eyebrow }}</p><h2 id="impact-heading">{{ settings.impact_title }}</h2></div>
            <p>{{ settings.impact_body }}</p>
          </header>
          <div class="igf-benefits">
            <article v-for="(benefit, index) in benefits" :key="`${benefit.label}-${index}`" class="igf-benefit">
              <span class="igf-benefit__icon"><i :class="benefit.icon" aria-hidden="true" /></span>
              <h3>{{ benefit.label }}</h3>
            </article>
          </div>
        </div>
      </section>

      <v-dialog v-model="showConfirmDialog" max-width="520" persistent>
        <v-card class="igf-confirm-card">
          <v-card-title>{{ settings.confirmation_title }}</v-card-title>
          <v-card-text>
            <p>{{ confirmationBody }}</p>
            <div class="igf-confirm-total"><span>{{ settings.confirmation_total_label }}</span><strong>{{ money(calculatedDonationAmount) }}</strong></div>
            <p class="igf-confirm-note">{{ settings.confirmation_note }}</p>
          </v-card-text>
          <v-card-actions>
            <button type="button" class="igf-dialog-button igf-dialog-button--quiet" :disabled="loading" @click="showConfirmDialog = false">{{ settings.confirmation_back_label }}</button>
            <button type="button" class="igf-dialog-button" data-test="confirm-sponsorship" :disabled="loading" @click="confirmSubmit">{{ loading ? settings.confirmation_sending_label : settings.confirmation_submit_label }}</button>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </Layout>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
import { useGlobal } from '../Shared/composables/global';
import { formatMoney, interpolateSetting } from '../Shared/composables/siteSettings';
import { responsiveImagePresentation } from '../Shared/composables/responsiveImage';

const page = usePage();
const { $toast } = useGlobal();
const sponsorForm = ref(null);
const choiceCard = ref(null);
const choiceHeading = ref(null);
const detailsHeading = ref(null);
const successHeading = ref(null);
const isFormValid = ref(false);
const loading = ref(false);
const showConfirmDialog = ref(false);
const currentStep = ref(1);
const submittedRequest = ref(null);
const successMessage = ref('');
const settings = computed(() => page.props.siteSettings?.sponsor_page || {});
const heroMedia = computed(() => responsiveImagePresentation(settings.value.hero_image, '(max-width: 960px) 100vw, 45vw'));
const regional = computed(() => page.props.siteSettings?.regional || {});
const baseContributionAmount = computed(() => Math.max(1, Number(settings.value.monthly_amount) || 1500));

const contributionIntervals = computed(() => [
  { label: settings.value.monthly_interval_label || 'Monthly', value: 'monthly', multiplier: 1 },
  { label: settings.value.quarterly_interval_label || 'Quarterly', value: 'quarterly', multiplier: 3 },
  { label: settings.value.semi_annual_interval_label || 'Every six months', value: 'semi_annually', multiplier: 6 },
  { label: settings.value.annual_interval_label || 'Annually', value: 'annually', multiplier: 12 },
]);
const sponsorship = ref(emptyForm());
const required = (label) => (value) => hasMeaningfulValue(value) || interpolateSetting(settings.value.required_message || '{field} is required', { field: label });
const emailRules = computed(() => [required(settings.value.email_field_label || 'Email'), value => /.+@.+\..+/.test(String(value || '').trim()) || settings.value.invalid_email_message]);
const selectedInterval = computed(() => contributionIntervals.value.find(item => item.value === sponsorship.value.contributionInterval) || contributionIntervals.value[0]);
const intervalMultiplier = computed(() => selectedInterval.value.multiplier);
const intervalLabel = computed(() => selectedInterval.value.label);
const normalisedChildCount = computed(() => Math.min(100, Math.max(1, Math.trunc(Number(sponsorship.value.numberOfChildren) || 1))));
const childLabel = computed(() => normalisedChildCount.value === 1 ? settings.value.child_singular : settings.value.child_plural);
const monthLabel = computed(() => intervalMultiplier.value === 1 ? settings.value.month_singular : settings.value.month_plural);
const contributionSummary = computed(() => interpolateSetting(settings.value.contribution_summary_label || 'Your {interval} contribution', { interval: intervalLabel.value.toLowerCase() }));
const confirmationBody = computed(() => interpolateSetting(settings.value.confirmation_body || '', {
  count: normalisedChildCount.value,
  children: childLabel.value,
  interval: intervalLabel.value.toLowerCase(),
}));
const assurances = computed(() => [
  { icon: 'fa-solid fa-lock', label: settings.value.assurance_1 },
  { icon: 'fa-solid fa-people-group', label: settings.value.assurance_2 },
  { icon: 'fa-solid fa-user-check', label: settings.value.assurance_3 },
].filter(item => item.label));
const calculatedDonationAmount = computed(() => normalisedChildCount.value * baseContributionAmount.value * intervalMultiplier.value);
const selectionImpact = computed(() => interpolateSetting(
  settings.value.selection_impact || 'Your selected plan helps organize dependable education and essential support for {count} {children}.',
  { amount: money(calculatedDonationAmount.value), count: normalisedChildCount.value, children: childLabel.value, interval: intervalLabel.value.toLowerCase() },
));
const benefitIcons = ['fa-solid fa-graduation-cap', 'fa-solid fa-chalkboard-user', 'fa-solid fa-shirt', 'fa-solid fa-book-open', 'fa-solid fa-bag-shopping', 'fa-solid fa-futbol', 'fa-solid fa-kit-medical', 'fa-solid fa-bowl-food'];
const benefits = computed(() => benefitIcons.map((icon, index) => ({ icon, label: settings.value[`benefit_${index + 1}`] })).filter(item => item.label));

function emptyForm() { return { numberOfChildren: 1, contributionInterval: 'monthly', name: '', email: '', phone: '', address: '' }; }
function hasMeaningfulValue(value) { return typeof value === 'string' ? value.trim().length > 0 : !!value; }
function money(amount) { return formatMoney(amount, regional.value); }
function intervalChoiceLabel(interval) { return `${interval.label}: ${money(baseContributionAmount.value * interval.multiplier)}`; }
function selectInterval(value) { sponsorship.value.contributionInterval = value; }
async function handleIntervalKeydown(event, index) {
  const keys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
  if (!keys.includes(event.key)) return;
  event.preventDefault();
  const lastIndex = contributionIntervals.value.length - 1;
  let targetIndex = index;
  if (event.key === 'Home') targetIndex = 0;
  else if (event.key === 'End') targetIndex = lastIndex;
  else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') targetIndex = index === 0 ? lastIndex : index - 1;
  else targetIndex = index === lastIndex ? 0 : index + 1;
  const target = contributionIntervals.value[targetIndex];
  selectInterval(target.value);
  await nextTick();
  event.currentTarget.parentElement?.querySelector(`[data-test="interval-${target.value}"]`)?.focus();
}
function normaliseChildren() { sponsorship.value.numberOfChildren = normalisedChildCount.value; }
function adjustChildren(change) { sponsorship.value.numberOfChildren = Math.min(100, Math.max(1, normalisedChildCount.value + change)); }
function preferredScrollBehavior() { return typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'; }
async function continueToDetails() {
  normaliseChildren();
  currentStep.value = 2;
  await nextTick();
  choiceCard.value?.scrollIntoView({ behavior: preferredScrollBehavior(), block: 'start' });
  detailsHeading.value?.focus();
}
async function returnToChoices() {
  currentStep.value = 1;
  await nextTick();
  choiceCard.value?.scrollIntoView({ behavior: preferredScrollBehavior(), block: 'start' });
  choiceHeading.value?.focus();
}
async function startAnotherRequest() {
  submittedRequest.value = null;
  successMessage.value = '';
  await resetForm();
  await returnToChoices();
}
async function resetForm() { sponsorship.value = emptyForm(); await sponsorForm.value?.resetValidation(); }
async function handleSubmit() {
  normaliseChildren();
  sponsorship.value.name = String(sponsorship.value.name || '').trim();
  sponsorship.value.email = String(sponsorship.value.email || '').trim();
  sponsorship.value.phone = String(sponsorship.value.phone || '').trim();
  sponsorship.value.address = String(sponsorship.value.address || '').trim();
  const result = await sponsorForm.value?.validate();
  if (!result?.valid || !hasMeaningfulValue(sponsorship.value.name) || !/.+@.+\..+/.test(sponsorship.value.email)) return;
  showConfirmDialog.value = true;
}
async function confirmSubmit() {
  loading.value = true;
  try {
    const response = await axios.post(route('frontend.sponsorship.store'), {
      name: sponsorship.value.name,
      email: sponsorship.value.email,
      phone: sponsorship.value.phone,
      address: sponsorship.value.address,
      number_of_children: normalisedChildCount.value,
      contribution_interval: sponsorship.value.contributionInterval,
      sponsorshipAmount: calculatedDonationAmount.value,
    });
    if (!response.data?.status) throw new Error('Request was not accepted');
    submittedRequest.value = {
      amount: calculatedDonationAmount.value,
      summary: contributionSummary.value,
      count: normalisedChildCount.value,
      children: childLabel.value,
      multiplier: intervalMultiplier.value,
      months: monthLabel.value,
    };
    successMessage.value = response.data.message || settings.value.success_message || 'Sponsorship request submitted.';
    $toast.success(successMessage.value);
    showConfirmDialog.value = false;
    currentStep.value = 3;
    await resetForm();
    await nextTick();
    choiceCard.value?.scrollIntoView({ behavior: preferredScrollBehavior(), block: 'start' });
    successHeading.value?.focus();
  } catch (error) {
    $toast.error(error.response?.data?.message || settings.value.error_message || 'We could not send your request. Please try again.');
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.igf-sponsor { --orange:#ff7500; --brown:#9c4500; --ink:#191c1d; --muted:#5f6065; --surface:#f7f5f2; --peach:#fff0e4; --line:#dedbd7; width:100%; max-width:100%; overflow:hidden; color:var(--ink); font-family:'Hanken Grotesk',Arial,sans-serif; }
.igf-sponsor,.igf-sponsor *,.igf-sponsor *::before,.igf-sponsor *::after { box-sizing:border-box; }
.igf-shell,.igf-sponsor__hero-inner { width:min(calc(100% - 40px),1240px); margin:0 auto; }
.igf-sponsor :is(h1,h2,h3) { margin-top:0; color:inherit; font-family:'Literata',Georgia,serif; letter-spacing:-.03em; }
.igf-sponsor :is(h1,h2,h3)::after { display:none!important; }
.igf-eyebrow { margin:0 0 14px!important; color:var(--brown)!important; font-size:12px!important; font-weight:850!important; letter-spacing:.11em; text-transform:uppercase; }
.igf-sponsor__hero { padding:clamp(24px,3.4vw,44px) 0; background:#222526; }
.igf-sponsor__hero-inner { display:grid; grid-template-columns:minmax(0,1.18fr) minmax(340px,.82fr); align-items:stretch; gap:18px; }
.igf-sponsor__choice-card { position:relative; z-index:1; min-width:0; padding:clamp(30px,3.3vw,42px); border-radius:26px; background:#fff; box-shadow:0 24px 70px rgba(0,0,0,.18); scroll-margin-top:100px; }
.igf-sponsor__choice-card h1 { max-width:720px; margin-bottom:12px; font-size:clamp(38px,4.2vw,54px); font-weight:650; line-height:1.04; }
.igf-sponsor__introduction { max-width:680px; margin:0; color:var(--muted); font-size:clamp(16px,1.3vw,18px); line-height:1.45; }
.igf-sponsor__progress-heading,.igf-request-form__heading { display:grid; grid-template-columns:auto 1fr; align-items:center; gap:16px; margin-top:22px; color:var(--brown); font-size:11px; font-weight:850; letter-spacing:.08em; text-transform:uppercase; }
.igf-sponsor__progress-track { display:block; height:5px; overflow:hidden; border-radius:99px; background:#ece7e2; }
.igf-sponsor__progress-track>span { display:block; width:50%; height:100%; border-radius:inherit; background:var(--orange); }
.igf-request-form__heading .igf-sponsor__progress-track>span { width:100%; }
.igf-sponsor__choice-heading { margin-top:14px; }
.igf-sponsor__choice-heading h2 { margin-bottom:5px; font-size:clamp(23px,2.1vw,30px); line-height:1.16; }
.igf-sponsor__choice-heading p { margin:0; color:var(--muted); font-size:14px; line-height:1.42; }
.igf-sponsor__intervals { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:9px; margin-top:12px; }
.igf-sponsor__interval { display:grid; min-width:0; min-height:64px; place-content:center; gap:3px; padding:8px; border:1px solid var(--line); border-radius:13px; background:#fff; color:var(--ink); text-align:center; cursor:pointer; transition:border-color .18s ease,background-color .18s ease,box-shadow .18s ease,transform .18s ease; }
.igf-sponsor__interval span { font-size:12px; font-weight:850; }
.igf-sponsor__interval small { color:var(--muted); font-size:11px; }
.igf-sponsor__interval:hover { border-color:#eea66b; transform:translateY(-2px); }
.igf-sponsor__interval.is-selected { border-color:var(--orange); background:var(--peach); box-shadow:inset 0 0 0 1px var(--orange); }
.igf-sponsor__interval.is-selected small { color:var(--brown); }
.igf-sponsor__interval:focus-visible,.igf-sponsor__stepper button:focus-visible,.igf-sponsor__continue:focus-visible,.igf-total-card button:focus-visible,.igf-submit:focus-visible { outline:3px solid rgba(255,117,0,.42); outline-offset:3px; }
.igf-sponsor__selection-grid { display:grid; grid-template-columns:minmax(190px,.8fr) minmax(0,1.2fr); gap:10px; margin-top:10px; }
.igf-sponsor__children-choice,.igf-sponsor__choice-total { min-height:98px; padding:13px 15px; border:1px solid var(--line); border-radius:14px; }
.igf-sponsor__children-choice { display:grid; align-content:center; gap:8px; }
.igf-sponsor__children-choice>label { font-size:12px; font-weight:850; }
.igf-sponsor__children-choice>small { color:var(--muted); font-size:10px; }
.igf-sponsor__stepper { display:grid; grid-template-columns:42px minmax(54px,1fr) 42px; overflow:hidden; border:1px solid #c8c2bc; border-radius:10px; }
.igf-sponsor__stepper button { min-height:42px; border:0; background:#f6f4f1; color:var(--brown); cursor:pointer; }
.igf-sponsor__stepper button:disabled { color:#aaa; cursor:not-allowed; }
.igf-sponsor__stepper input { width:100%; border:0; border-right:1px solid #c8c2bc; border-left:1px solid #c8c2bc; background:#fff; color:var(--ink); font:800 17px 'Hanken Grotesk',Arial,sans-serif; text-align:center; }
.igf-sponsor__stepper input:focus { position:relative; outline:2px solid var(--orange); outline-offset:-2px; }
.igf-sponsor__choice-total { display:grid; align-content:center; gap:3px; background:#202324; color:#fff; }
.igf-sponsor__choice-total>span { color:#ffc89b; font-size:11px; font-weight:850; letter-spacing:.06em; text-transform:uppercase; }
.igf-sponsor__choice-total strong { font:650 30px/1.1 'Literata',Georgia,serif; }
.igf-sponsor__choice-total small { color:#d5d7d7; font-size:11px; }
.igf-sponsor__impact-message { margin:10px 0 0; padding:10px 13px; border-left:4px solid var(--orange); background:#fff8f2; color:#5c3820; font-size:12px; line-height:1.42; }
.igf-sponsor__continue,.igf-submit { display:flex; width:100%; min-height:52px; align-items:center; justify-content:space-between; margin-top:12px; padding:0 22px; border:0; border-radius:14px; background:var(--orange); color:#1c1e20; font-size:13px; font-weight:900; letter-spacing:.045em; text-transform:uppercase; cursor:pointer; box-shadow:0 10px 24px rgba(255,117,0,.24); transition:background-color .18s ease,transform .18s ease,box-shadow .18s ease; }
.igf-sponsor__continue:hover,.igf-submit:hover:not(:disabled) { background:#ff8a2b; transform:translateY(-2px); box-shadow:0 14px 30px rgba(255,117,0,.3); }
.igf-sponsor__request-note { display:flex; align-items:flex-start; gap:9px; margin:10px 0 0; color:var(--muted); font-size:12px; line-height:1.4; }
.igf-sponsor__request-note i { margin-top:2px; color:var(--brown); }
.igf-sponsor__trust-list { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:9px; margin:12px 0 0; padding:12px 0 0; border-top:1px solid var(--line); list-style:none; color:#55595a; font-size:11px; }
.igf-sponsor__trust-list li { display:flex; align-items:flex-start; gap:7px; line-height:1.35; }
.igf-sponsor__trust-list i { margin-top:2px; color:var(--brown); }
.igf-sponsor__hero-media { position:relative; min-height:100%; overflow:hidden; margin:0; border-radius:26px; background:#393c3d; }
.igf-sponsor__hero-media picture,.igf-sponsor__hero-media img { display:block; width:100%; height:100%; }
.igf-sponsor__hero-media img { min-height:620px; object-fit:cover; }
.igf-sponsor__hero-media::after { position:absolute; inset:0; background:linear-gradient(180deg,transparent 54%,rgba(20,22,23,.84)); content:''; }
.igf-sponsor__hero-media figcaption { position:absolute; z-index:1; right:24px; bottom:24px; left:24px; display:flex; align-items:flex-end; justify-content:space-between; gap:18px; padding:20px; border:1px solid rgba(255,255,255,.22); border-radius:14px; background:rgba(24,27,28,.88); color:#fff; backdrop-filter:blur(10px); }
.igf-sponsor__hero-media figcaption span { max-width:150px; color:#e4e5e5; font-size:11px; font-weight:800; line-height:1.4; letter-spacing:.06em; text-transform:uppercase; }
.igf-sponsor__hero-media figcaption strong { flex:0 0 auto; font:650 29px/1 'Literata',Georgia,serif; }
.igf-sponsor__impact { padding:clamp(76px,9vw,118px) 0; background:#fff; }
.igf-section-heading { display:grid; grid-template-columns:1fr 1fr; align-items:end; gap:60px; margin-bottom:46px; }
.igf-section-heading h2,.igf-request-copy h2 { margin-bottom:0; font-size:clamp(35px,4.3vw,54px); font-weight:620; line-height:1.12; }
.igf-section-heading>p { margin:0; color:var(--muted); font-size:18px; line-height:1.65; }
.igf-benefits { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
.igf-benefit { display:flex; min-height:138px; align-items:center; gap:16px; padding:22px; border:1px solid var(--line); border-radius:15px; background:#fff; transition:border-color .18s ease,transform .18s ease,box-shadow .18s ease; }
.igf-benefit:hover { border-color:#ecc19d; transform:translateY(-3px); box-shadow:0 13px 30px rgba(39,29,22,.08); }
.igf-benefit__icon { display:grid; width:50px; height:50px; flex:0 0 50px; place-items:center; border-radius:14px; background:var(--peach); color:var(--brown); font-size:21px; }
.igf-benefit h3 { margin:0; font:750 16px/1.32 'Hanken Grotesk',Arial,sans-serif; letter-spacing:-.01em; }
.igf-sponsor__form-section { padding:clamp(76px,9vw,118px) 0; background:var(--surface); scroll-margin-top:110px; }
.igf-request-grid { display:grid; grid-template-columns:minmax(0,.8fr) minmax(520px,1.2fr); align-items:start; gap:clamp(44px,7vw,90px); }
.igf-request-copy { position:sticky; top:110px; }
.igf-request-copy>p:not(.igf-eyebrow) { margin:20px 0; color:var(--muted); font-size:18px; line-height:1.65; }
.igf-step-two__header { display:grid; grid-template-columns:minmax(0,1fr) minmax(220px,.72fr); align-items:end; gap:20px; margin-top:18px; }
.igf-step-two__header h2,.igf-sponsor__success h2 { margin-bottom:8px; font-size:clamp(27px,2.8vw,38px); line-height:1.12; }
.igf-step-two__header>div:first-child>p:last-child,.igf-sponsor__success-announcement>p:last-child { margin:0; color:var(--muted); font-size:14px; line-height:1.5; }
.igf-total-card { display:grid; gap:5px; margin-top:32px; padding:24px; border:1px solid #e5d1c1; border-radius:14px; background:#fff7f1; }
.igf-total-card>span { color:var(--brown); font-size:12px; font-weight:850; letter-spacing:.05em; text-transform:uppercase; }
.igf-total-card strong { font:650 34px/1.2 'Literata',Georgia,serif; }
.igf-total-card small { color:var(--muted); }
.igf-total-card button { justify-self:start; margin-top:10px; padding:0; border:0; border-bottom:1px solid currentColor; background:transparent; color:var(--brown); font:800 12px 'Hanken Grotesk',Arial,sans-serif; cursor:pointer; }
.igf-step-two__header .igf-total-card { margin-top:0; padding:16px; }
.igf-step-two__header .igf-total-card strong { font-size:28px; }
.igf-request-form { margin-top:20px; padding:24px; border:1px solid var(--line); border-radius:18px; background:#fff; box-shadow:0 12px 32px rgba(25,28,29,.07); }
.igf-request-form__heading { margin:0 0 26px; }
.igf-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px 16px; }
.igf-request-form :deep(.v-field) { border-radius:10px; background:#fff; }
.igf-request-form :deep(.v-label) { color:#4e5052; opacity:1; }
.igf-privacy { display:flex; align-items:flex-start; gap:10px; margin:23px 0!important; color:var(--muted)!important; font-size:13px!important; line-height:1.5; }
.igf-privacy i { margin-top:3px; color:var(--brown); }
.igf-submit { justify-content:center; gap:12px; margin-top:0; }
.igf-submit:disabled { cursor:not-allowed; box-shadow:none; opacity:.55; }
.igf-confirm-card { overflow:hidden; border-radius:18px!important; font-family:'Hanken Grotesk',Arial,sans-serif; }
.igf-confirm-card :deep(.v-card-title) { padding:28px 28px 14px; font:650 28px/1.2 'Literata',Georgia,serif; }
.igf-confirm-card :deep(.v-card-text) { padding:12px 28px 20px; color:#555; line-height:1.6; }
.igf-confirm-total { display:flex; align-items:center; justify-content:space-between; gap:20px; margin:22px 0; padding:18px; border-radius:10px; background:#fff3e9; color:var(--brown); }
.igf-confirm-total strong { font:650 23px 'Literata',Georgia,serif; }
.igf-confirm-note { font-size:13px; }
.igf-confirm-card :deep(.v-card-actions) { gap:10px; justify-content:flex-end; padding:16px 28px 28px; }
.igf-dialog-button { min-height:44px; padding:0 20px; border:1px solid var(--orange); border-radius:12px; background:var(--orange); color:var(--ink); font-weight:850; }
.igf-dialog-button--quiet { border-color:#bbb; background:#fff; color:var(--ink); }
.igf-sponsor__success { display:grid; align-content:center; min-height:520px; }
.igf-sponsor__success-icon { display:grid; width:58px; height:58px; place-items:center; margin-bottom:20px; border-radius:50%; background:var(--peach); color:var(--brown); font-size:24px; }
.igf-sponsor__success .igf-eyebrow { margin-bottom:10px!important; }
.igf-total-card--success { width:min(100%,420px); margin-top:22px; }
.igf-sponsor__next-step { width:min(100%,520px); margin-top:16px; padding:16px 18px; border-left:4px solid var(--orange); background:#fff8f2; }
.igf-sponsor__next-step strong { font-size:14px; }
.igf-sponsor__next-step p { margin:5px 0 0; color:var(--muted); font-size:13px; line-height:1.48; }
.igf-sponsor__continue--secondary { width:min(100%,420px); background:#242728; color:#fff; box-shadow:none; }
.igf-sponsor__continue--secondary:hover { background:#3a3d3e; box-shadow:none; }
@media(max-height:760px) and (min-width:961px){.igf-sponsor__hero{padding:16px 0}.igf-sponsor__choice-card{padding:20px 28px}.igf-eyebrow{margin-bottom:6px!important}.igf-sponsor__choice-card h1{margin-bottom:6px;font-size:36px;line-height:1.02}.igf-sponsor__introduction{font-size:14px;line-height:1.32}.igf-sponsor__progress-heading{margin-top:10px}.igf-sponsor__choice-heading{margin-top:7px}.igf-sponsor__choice-heading h2{font-size:21px}.igf-sponsor__choice-heading p{display:none}.igf-sponsor__intervals{margin-top:7px}.igf-sponsor__interval{min-height:50px}.igf-sponsor__children-choice,.igf-sponsor__choice-total{min-height:76px;padding:7px 10px}.igf-sponsor__children-choice{gap:4px}.igf-sponsor__stepper button{min-height:36px}.igf-sponsor__impact-message{margin-top:6px;padding:6px 10px}.igf-sponsor__continue{min-height:46px;margin-top:6px}.igf-sponsor__request-note{margin-top:6px;font-size:10px}.igf-sponsor__trust-list{margin-top:6px;padding-top:6px}.igf-sponsor__hero-media img{min-height:550px}}
@media(max-width:1060px){.igf-sponsor__intervals{grid-template-columns:repeat(2,minmax(0,1fr))}.igf-benefits{grid-template-columns:repeat(2,minmax(0,1fr))}.igf-request-grid{grid-template-columns:1fr}.igf-request-copy{position:static;max-width:720px}}
@media(max-width:960px){.igf-sponsor__hero{background:#f3f1ee}.igf-sponsor__hero-inner{grid-template-columns:minmax(0,1fr)}.igf-sponsor__hero-media{display:block;order:-1;width:calc(100% - 24px);height:230px;min-height:0;justify-self:center;margin:0 0 -42px;border-radius:22px 22px 10px 10px}.igf-sponsor__hero-media picture,.igf-sponsor__hero-media img{height:100%;min-height:0}.igf-sponsor__hero-media img{object-position:center 28%}.igf-sponsor__hero-media figcaption{display:none}.igf-sponsor__choice-card{width:min(100%,760px);margin:0 auto}.igf-sponsor__trust-list{grid-template-columns:repeat(3,minmax(0,1fr))}.igf-sponsor__continue:not(.igf-sponsor__continue--secondary),.igf-submit{width:calc(100% - 72px)}}
@media(max-width:760px){.igf-shell,.igf-sponsor__hero-inner{width:min(calc(100% - 28px),1240px)}.igf-sponsor__hero{padding:14px 0 30px}.igf-sponsor__choice-card{padding:26px 20px;border-radius:20px;box-shadow:0 14px 40px rgba(25,28,29,.1)}.igf-sponsor__choice-card h1{font-size:clamp(34px,9.5vw,44px)}.igf-sponsor__selection-grid,.igf-section-heading,.igf-step-two__header{grid-template-columns:1fr}.igf-sponsor__trust-list{grid-template-columns:1fr}.igf-section-heading{gap:14px}.igf-benefits{grid-template-columns:1fr 1fr;gap:10px}.igf-benefit{min-height:118px;align-items:flex-start;flex-direction:column;padding:18px}.igf-benefit__icon{width:44px;height:44px;flex-basis:44px}.igf-request-grid{gap:34px}.igf-form-grid{grid-template-columns:1fr}.igf-request-form{padding:20px 16px}.igf-confirm-total{align-items:flex-start;flex-direction:column}.igf-step-two__header{align-items:start}.igf-sponsor__success{min-height:460px}}
@media(max-width:430px){.igf-sponsor__hero-media{width:calc(100% - 16px);height:150px;margin-bottom:-30px;border-radius:18px 18px 8px 8px}.igf-sponsor__hero-media img{object-position:center 34%}.igf-sponsor__choice-card{padding:22px 16px}.igf-sponsor__choice-card h1{font-size:34px}.igf-sponsor__introduction,.igf-sponsor__impact-message{display:none}.igf-sponsor__progress-heading{margin-top:15px}.igf-sponsor__choice-heading{margin-top:10px}.igf-sponsor__choice-heading h2{font-size:22px}.igf-sponsor__choice-heading p{font-size:12px}.igf-sponsor__intervals{grid-template-columns:1fr 1fr;gap:7px;margin-top:9px}.igf-sponsor__interval{min-height:58px}.igf-sponsor__selection-grid{grid-template-columns:1fr;gap:7px;margin-top:7px}.igf-sponsor__children-choice,.igf-sponsor__choice-total{min-height:84px;padding:10px 12px}.igf-sponsor__continue{min-height:50px;margin-top:9px}.igf-sponsor__request-note{font-size:11px}.igf-sponsor__trust-list{margin-top:9px;padding-top:9px}.igf-benefits{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){.igf-sponsor *{scroll-behavior:auto!important;transition:none!important}}
</style>
