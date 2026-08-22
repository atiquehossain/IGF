<template>
  <Layout>
    <div class="igf-sponsor">
      <section class="igf-sponsor__hero">
        <div class="igf-sponsor__hero-inner">
          <div class="igf-sponsor__hero-copy">
            <p class="igf-eyebrow">{{ settings.eyebrow }}</p>
            <h1>{{ settings.title }}</h1>
            <p>{{ settings.introduction }}</p>
            <a class="igf-primary-link" href="#sponsorship-form">{{ settings.hero_cta_label }} <span aria-hidden="true">&#8595;</span></a>
          </div>
          <figure class="igf-sponsor__hero-media">
            <picture>
              <source v-if="heroMedia.avifSrcset" type="image/avif" :srcset="heroMedia.avifSrcset" :sizes="heroMedia.sizes">
              <source v-if="heroMedia.webpSrcset" type="image/webp" :srcset="heroMedia.webpSrcset" :sizes="heroMedia.sizes">
              <img :src="heroMedia.src" :alt="settings.hero_image_alt" :width="heroMedia.width" :height="heroMedia.height" loading="eager" fetchpriority="high" decoding="async">
            </picture>
            <figcaption><strong>{{ money(baseContributionAmount) }}</strong><span>{{ settings.monthly_period_label }}</span></figcaption>
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
            <article v-for="benefit in benefits" :key="benefit.label" class="igf-benefit">
              <i :class="benefit.icon" aria-hidden="true" />
              <h3>{{ benefit.label }}</h3>
            </article>
          </div>
        </div>
      </section>

      <section id="sponsorship-form" class="igf-sponsor__form-section" aria-labelledby="sponsor-form-title">
        <div class="igf-shell igf-request-grid">
          <div class="igf-request-copy">
            <p class="igf-eyebrow">{{ settings.form_eyebrow }}</p>
            <h2 id="sponsor-form-title">{{ settings.form_title }}</h2>
            <p>{{ settings.form_body }}</p>
            <div class="igf-total-card" aria-live="polite">
              <span>{{ contributionSummary }}</span>
              <strong>{{ money(calculatedDonationAmount) }}</strong>
              <small v-if="sponsorship.numberOfChildren && sponsorship.contributionInterval">
                {{ sponsorship.numberOfChildren }} {{ childLabel }} &times;
                {{ money(baseContributionAmount) }} &times; {{ intervalMultiplier }} {{ monthLabel }}
              </small>
            </div>
            <ul class="igf-assurances" :aria-label="settings.assurances_label">
              <li v-for="assurance in assurances" :key="assurance.label"><i :class="assurance.icon" aria-hidden="true" /> {{ assurance.label }}</li>
            </ul>
          </div>

          <v-form ref="sponsorForm" v-model="isFormValid" class="igf-request-form" autocomplete="on" @submit.prevent="handleSubmit">
            <div class="igf-form-grid">
              <v-text-field v-model="sponsorship.numberOfChildren" :label="settings.children_field_label" type="number" variant="outlined" min="1" max="100" hide-details="auto" :rules="childRules" required />
              <label class="igf-native-field" for="sponsorship-interval"><span>{{ settings.interval_field_label }}</span><select id="sponsorship-interval" v-model="sponsorship.contributionInterval" :aria-label="settings.interval_field_label" required><option v-for="interval in contributionIntervals" :key="interval.value" :value="interval.value">{{ interval.label }}</option></select></label>
              <v-text-field v-model="sponsorship.name" :label="settings.name_field_label" autocomplete="name" variant="outlined" hide-details="auto" :rules="[required('Name')]" required />
              <v-text-field v-model="sponsorship.email" :label="settings.email_field_label" autocomplete="email" type="email" variant="outlined" hide-details="auto" :rules="emailRules" required />
              <v-text-field v-model="sponsorship.phone" :label="settings.phone_field_label" autocomplete="tel" type="tel" variant="outlined" hide-details="auto" />
              <v-text-field v-model="sponsorship.address" :label="settings.address_field_label" autocomplete="street-address" variant="outlined" hide-details="auto" />
            </div>
            <p class="igf-privacy"><i class="fa-solid fa-shield-halved" aria-hidden="true" /> {{ settings.privacy_note }}</p>
            <button class="igf-submit" type="submit" :disabled="!isFormValid || loading">
              <span>{{ loading ? settings.sending_label : settings.submit_label }}</span><i class="fa-solid fa-arrow-right" aria-hidden="true" />
            </button>
          </v-form>
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
            <button type="button" class="igf-dialog-button" :disabled="loading" @click="confirmSubmit">{{ loading ? settings.confirmation_sending_label : settings.confirmation_submit_label }}</button>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </Layout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
import { useGlobal } from '../Shared/composables/global';
import { formatMoney, interpolateSetting } from '../Shared/composables/siteSettings';
import { responsiveImagePresentation } from '../Shared/composables/responsiveImage';

const page = usePage();
const { $toast } = useGlobal();
const sponsorForm = ref(null);
const isFormValid = ref(false);
const loading = ref(false);
const showConfirmDialog = ref(false);
const settings = computed(() => page.props.siteSettings?.sponsor_page || {});
const heroMedia = computed(() => responsiveImagePresentation(settings.value.hero_image, '(max-width: 900px) 100vw, 50vw'));
const regional = computed(() => page.props.siteSettings?.regional || {});
const baseContributionAmount = computed(() => Math.max(1, Number(settings.value.monthly_amount) || 1500));

const contributionIntervals = computed(() => [
  { label: settings.value.monthly_interval_label || 'Monthly', value: 'monthly', multiplier: 1 },
  { label: settings.value.quarterly_interval_label || 'Quarterly', value: 'quarterly', multiplier: 3 },
  { label: settings.value.semi_annual_interval_label || 'Every six months', value: 'semi_annually', multiplier: 6 },
  { label: settings.value.annual_interval_label || 'Annually', value: 'annually', multiplier: 12 },
]);
const sponsorship = ref(emptyForm());
const required = (label) => (value) => !!value || interpolateSetting(settings.value.required_message || '{field} is required', { field: label });
const childRules = computed(() => [required(settings.value.children_field_label || 'Number of children'), value => Number(value) >= 1 || settings.value.minimum_children_message, value => Number(value) <= 100 || settings.value.maximum_children_message]);
const emailRules = computed(() => [required(settings.value.email_field_label || 'Email'), value => /.+@.+\..+/.test(value) || settings.value.invalid_email_message]);
const selectedInterval = computed(() => contributionIntervals.value.find(item => item.value === sponsorship.value.contributionInterval) || contributionIntervals.value[0]);
const intervalMultiplier = computed(() => selectedInterval.value.multiplier);
const intervalLabel = computed(() => selectedInterval.value.label);
const childLabel = computed(() => Number(sponsorship.value.numberOfChildren) === 1 ? settings.value.child_singular : settings.value.child_plural);
const monthLabel = computed(() => intervalMultiplier.value === 1 ? settings.value.month_singular : settings.value.month_plural);
const contributionSummary = computed(() => interpolateSetting(settings.value.contribution_summary_label || 'Your {interval} contribution', { interval: intervalLabel.value.toLowerCase() }));
const confirmationBody = computed(() => interpolateSetting(settings.value.confirmation_body || '', {
  count: sponsorship.value.numberOfChildren,
  children: childLabel.value,
  interval: intervalLabel.value.toLowerCase(),
}));
const assurances = computed(() => [
  { icon: 'fa-solid fa-lock', label: settings.value.assurance_1 },
  { icon: 'fa-solid fa-people-group', label: settings.value.assurance_2 },
  { icon: 'fa-regular fa-clock', label: settings.value.assurance_3 },
].filter(item => item.label));
const calculatedDonationAmount = computed(() => (Number(sponsorship.value.numberOfChildren) || 0) * baseContributionAmount.value * intervalMultiplier.value);
const benefitIcons = ['fa-solid fa-graduation-cap', 'fa-solid fa-chalkboard-user', 'fa-solid fa-shirt', 'fa-solid fa-book-open', 'fa-solid fa-bag-shopping', 'fa-solid fa-futbol', 'fa-solid fa-kit-medical', 'fa-solid fa-bowl-food'];
const benefits = computed(() => benefitIcons.map((icon, index) => ({ icon, label: settings.value[`benefit_${index + 1}`] })).filter(item => item.label));

function emptyForm() { return { numberOfChildren: 1, contributionInterval: 'monthly', name: '', email: '', phone: '', address: '' }; }
function money(amount) { return formatMoney(amount, regional.value); }
async function resetForm() { sponsorship.value = emptyForm(); await sponsorForm.value?.resetValidation(); }
async function handleSubmit() {
  const result = await sponsorForm.value?.validate();
  if (!result?.valid) return;
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
      number_of_children: sponsorship.value.numberOfChildren,
      contribution_interval: sponsorship.value.contributionInterval,
      sponsorshipAmount: calculatedDonationAmount.value,
    });
    if (!response.data?.status) throw new Error('Request was not accepted');
    $toast.success(response.data.message || settings.value.success_message || 'Sponsorship request submitted.');
    showConfirmDialog.value = false;
    await resetForm();
  } catch (error) {
    $toast.error(error.response?.data?.message || settings.value.error_message || 'We could not send your request. Please try again.');
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.igf-sponsor{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#5f6065;--surface:#f8f9fa;--line:#dedbd7;overflow:hidden;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell,.igf-sponsor__hero-inner{width:min(100% - 40px,1240px);margin:0 auto}.igf-sponsor :is(h1,h2,h3){margin-top:0;color:inherit;font-family:'Literata',Georgia,serif;letter-spacing:-.03em}.igf-sponsor :is(h1,h2,h3)::after{display:none!important}.igf-eyebrow{margin:0 0 16px!important;color:#ffb070!important;font-size:12px!important;font-weight:800!important;letter-spacing:.1em;text-transform:uppercase}.igf-sponsor__hero{padding:clamp(96px,12vw,150px) 0 clamp(70px,9vw,110px);background:#202223;color:#fff}.igf-sponsor__hero-inner{display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.82fr);align-items:center;gap:clamp(48px,7vw,100px)}.igf-sponsor__hero-copy h1{max-width:760px;margin-bottom:24px;font-size:clamp(44px,6vw,76px);font-weight:650;line-height:1.03}.igf-sponsor__hero-copy>p:not(.igf-eyebrow){max-width:650px;color:#dadbdc;font-size:20px;line-height:1.65}.igf-primary-link{display:inline-flex;min-height:52px;align-items:center;gap:12px;margin-top:20px;padding:0 25px;border-radius:999px;background:var(--orange);color:#fff;font-size:13px;font-weight:800;letter-spacing:.04em;text-decoration:none;text-transform:uppercase}.igf-sponsor__hero-media{position:relative;overflow:hidden;margin:0;border:1px solid rgba(255,255,255,.16);border-radius:24px;background:#363839}.igf-sponsor__hero-media img{display:block;width:100%;aspect-ratio:4/5;object-fit:cover}.igf-sponsor__hero-media figcaption{position:absolute;right:18px;bottom:18px;left:18px;display:flex;align-items:end;justify-content:space-between;gap:20px;padding:20px;border-radius:14px;background:rgba(25,28,29,.92);color:#fff}.igf-sponsor__hero-media strong{font:650 29px/1 'Literata',Georgia,serif}.igf-sponsor__hero-media span{color:#ddd;font-size:12px;font-weight:800;text-transform:uppercase}.igf-sponsor__impact{padding:clamp(75px,9vw,120px) 0;background:#fff}.igf-section-heading{display:grid;grid-template-columns:1fr 1fr;align-items:end;gap:60px;margin-bottom:48px}.igf-section-heading .igf-eyebrow,.igf-request-copy .igf-eyebrow{color:var(--brown)!important}.igf-section-heading h2,.igf-request-copy h2{margin-bottom:0;font-size:clamp(36px,4.4vw,54px);font-weight:620;line-height:1.12}.igf-section-heading>p{margin:0;color:var(--muted);font-size:18px;line-height:1.65}.igf-benefits{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.igf-benefit{min-height:170px;padding:28px 24px;border:1px solid var(--line);border-top:4px solid var(--orange);border-radius:15px;background:#fff}.igf-benefit:nth-child(even){border-top-color:var(--brown)}.igf-benefit i{margin-bottom:30px;color:var(--orange);font-size:29px}.igf-benefit:nth-child(even) i{color:var(--brown)}.igf-benefit h3{margin:0;font-size:18px;line-height:1.3}.igf-sponsor__form-section{padding:clamp(75px,9vw,120px) 0;background:var(--surface)}.igf-request-grid{display:grid;grid-template-columns:minmax(0,.8fr) minmax(520px,1.2fr);align-items:start;gap:clamp(45px,7vw,90px)}.igf-request-copy>p:not(.igf-eyebrow){margin:20px 0;color:var(--muted);font-size:18px;line-height:1.65}.igf-total-card{display:grid;gap:5px;margin-top:34px;padding:24px;border:1px solid #e5d1c1;border-radius:14px;background:#fff7f1}.igf-total-card>span{color:var(--brown);font-size:12px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}.igf-total-card strong{font:650 34px/1.2 'Literata',Georgia,serif}.igf-total-card small{color:var(--muted)}.igf-assurances{display:grid;gap:12px;margin:26px 0 0;padding:0;list-style:none;color:var(--muted);font-size:14px}.igf-assurances i{width:20px;color:var(--brown);text-align:center}.igf-request-form{padding:clamp(24px,4vw,42px);border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 12px 40px rgba(25,28,29,.07)}.igf-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px 16px}.igf-request-form :deep(.v-field){border-radius:9px;background:#fff}.igf-request-form :deep(.v-label){color:#4e5052;opacity:1}.igf-privacy{display:flex;align-items:flex-start;gap:10px;margin:23px 0!important;color:var(--muted)!important;font-size:13px!important;line-height:1.5}.igf-privacy i{margin-top:3px;color:var(--brown)}.igf-submit{display:flex;width:100%;min-height:54px;align-items:center;justify-content:center;gap:12px;border:0;border-radius:999px;background:var(--orange);color:#fff;font-size:13px;font-weight:800;letter-spacing:.045em;text-transform:uppercase}.igf-submit:disabled{cursor:not-allowed;opacity:.55}.igf-confirm-card{overflow:hidden;border-radius:18px!important;font-family:'Hanken Grotesk',Arial,sans-serif}.igf-confirm-card :deep(.v-card-title){padding:28px 28px 14px;font:650 28px/1.2 'Literata',Georgia,serif}.igf-confirm-card :deep(.v-card-text){padding:12px 28px 20px;color:#555;line-height:1.6}.igf-confirm-total{display:flex;align-items:center;justify-content:space-between;gap:20px;margin:22px 0;padding:18px;border-radius:10px;background:#fff3e9;color:var(--brown)}.igf-confirm-total strong{font:650 23px 'Literata',Georgia,serif}.igf-confirm-note{font-size:13px}.igf-confirm-card :deep(.v-card-actions){gap:10px;justify-content:flex-end;padding:16px 28px 28px}.igf-dialog-button{min-height:44px;padding:0 20px;border:1px solid var(--orange);border-radius:999px;background:var(--orange);color:#fff;font-weight:800}.igf-dialog-button--quiet{border-color:#bbb;background:#fff;color:var(--ink)}
.igf-native-field{display:grid;gap:7px;color:var(--ink);font-size:12px;font-weight:700}.igf-native-field select{width:100%;min-height:56px;border:1px solid #79747e;border-radius:9px;padding:0 15px;background:#fff;color:var(--ink);font:500 16px 'Hanken Grotesk',Arial,sans-serif}.igf-native-field select:focus{border:2px solid var(--brown);outline:2px solid transparent}
@media(max-width:960px){.igf-sponsor__hero-inner{grid-template-columns:1fr 330px;gap:35px}.igf-benefits{grid-template-columns:repeat(2,1fr)}.igf-request-grid{grid-template-columns:1fr}.igf-request-copy{max-width:700px}.igf-assurances{grid-template-columns:repeat(3,1fr)}}
@media(max-width:700px){.igf-shell,.igf-sponsor__hero-inner{width:min(100% - 40px,1240px)}.igf-sponsor__hero{padding-top:72px}.igf-sponsor__hero-inner,.igf-section-heading{grid-template-columns:1fr}.igf-sponsor__hero-copy h1{font-size:44px}.igf-sponsor__hero-media img{aspect-ratio:4/3}.igf-section-heading{gap:14px}.igf-benefits{grid-template-columns:1fr 1fr;gap:10px}.igf-benefit{min-height:145px;padding:22px 18px}.igf-benefit i{margin-bottom:23px}.igf-request-grid{gap:36px}.igf-assurances{grid-template-columns:1fr}.igf-form-grid{grid-template-columns:1fr}.igf-request-form{padding:24px 18px}.igf-sponsor__hero-media figcaption{align-items:start;flex-direction:column;gap:5px}.igf-confirm-total{align-items:flex-start;flex-direction:column}}
@media(max-width:390px){.igf-benefits{grid-template-columns:1fr}.igf-primary-link{width:100%;justify-content:center}}
@media(prefers-reduced-motion:reduce){.igf-sponsor *{scroll-behavior:auto!important}}
</style>
