<template>
  <Layout>
    <div class="igf-volunteer">
      <section class="igf-volunteer__hero">
        <div class="igf-volunteer__hero-inner">
          <div class="igf-volunteer__hero-copy">
            <p class="igf-eyebrow">{{ settings.eyebrow }}</p>
            <h1>{{ settings.title }}</h1>
            <p>{{ settings.introduction }}</p>
            <a class="igf-primary-link" href="#volunteer-form">{{ settings.hero_cta_label }} <span aria-hidden="true">&#8595;</span></a>
          </div>
          <figure class="igf-volunteer__hero-media">
            <picture>
              <source v-if="heroMedia.avifSrcset" type="image/avif" :srcset="heroMedia.avifSrcset" :sizes="heroMedia.sizes">
              <source v-if="heroMedia.webpSrcset" type="image/webp" :srcset="heroMedia.webpSrcset" :sizes="heroMedia.sizes">
              <img :src="heroMedia.src" :alt="settings.hero_image_alt" :width="heroMedia.width" :height="heroMedia.height" loading="eager" fetchpriority="high" decoding="async">
            </picture>
          </figure>
        </div>
      </section>

      <section class="igf-process" aria-labelledby="process-title">
        <div class="igf-shell">
          <header><p class="igf-eyebrow">{{ settings.process_eyebrow }}</p><h2 id="process-title">{{ settings.process_title }}</h2></header>
          <ol>
            <li v-for="(step, index) in steps" :key="step.title">
              <span aria-hidden="true">0{{ index + 1 }}</span><div><h3>{{ step.title }}</h3><p>{{ step.body }}</p></div>
            </li>
          </ol>
        </div>
      </section>

      <section id="volunteer-form" class="igf-volunteer__form-section" aria-labelledby="volunteer-form-title">
        <div class="igf-shell igf-form-layout">
          <aside>
            <p class="igf-eyebrow">{{ settings.form_eyebrow }}</p>
            <h2 id="volunteer-form-title">{{ settings.form_title }}</h2>
            <p>{{ settings.form_body }}</p>
            <div class="igf-contact-note"><i class="fa-regular fa-clock" aria-hidden="true" /><div><strong>{{ settings.next_title }}</strong><span>{{ settings.next_body }}</span></div></div>
          </aside>

          <v-form ref="form" v-model="isFormValid" class="igf-registration-form" autocomplete="on" @submit.prevent="submitRegistration">
            <p v-if="!formOptionsAvailable" class="igf-form-alert igf-form-alert--top" role="status">{{ settings.options_unavailable }}</p>
            <fieldset class="igf-form-group">
              <legend>{{ settings.personal_section_title }}</legend>
              <div class="igf-field-grid">
                <v-text-field v-model="registration.name" :label="settings.name_field_label" :placeholder="settings.name_field_placeholder" autocomplete="name" variant="outlined" hide-details="auto" :rules="[required(settings.name_field_label)]" required />
                <v-text-field v-model="registration.email" :label="settings.email_field_label" :placeholder="settings.email_field_placeholder" autocomplete="email" type="email" variant="outlined" hide-details="auto" :rules="emailRules" required />
                <v-text-field v-model="registration.phone" :label="settings.phone_field_label" :placeholder="settings.phone_field_placeholder" autocomplete="tel" type="tel" variant="outlined" hide-details="auto" :rules="[required(settings.phone_field_label)]" required />
                <v-select v-model="registration.sex" data-test="sex-select" :items="sexOptions" item-title="label" item-value="value" :label="settings.sex_field_label" :placeholder="settings.sex_placeholder" :no-data-text="settings.options_unavailable" :disabled="sexOptions.length === 0" variant="outlined" hide-details="auto" :rules="[required(settings.sex_field_label)]" required />
                <v-text-field v-model="registration.date_of_birth" :label="settings.date_of_birth_field_label" :placeholder="settings.date_of_birth_placeholder" type="date" autocomplete="bday" :max="maxDateOfBirth" variant="outlined" hide-details="auto" :rules="[required(settings.date_of_birth_field_label)]" required />
              </div>
            </fieldset>

            <fieldset class="igf-form-group">
              <legend>{{ settings.location_section_title }}</legend>
              <div class="igf-field-grid">
                <v-textarea v-model="registration.address" class="igf-field--wide" :label="settings.address_field_label" :placeholder="settings.address_field_placeholder" autocomplete="street-address" variant="outlined" rows="2" auto-grow hide-details="auto" :rules="[required(settings.address_field_label)]" required />
              </div>
              <div class="igf-field-grid igf-field-grid--location">
                <v-select v-model="registration.division_id" data-test="division-select" :items="divisions" item-title="label" item-value="id" :label="settings.division_field_label" :placeholder="settings.division_placeholder" :no-data-text="settings.locations_unavailable" :disabled="divisions.length === 0" variant="outlined" hide-details="auto" :rules="[required(settings.division_field_label)]" required />
                <v-select v-model="registration.district_id" data-test="district-select" :items="districts" item-title="label" item-value="id" :label="settings.district_field_label" :placeholder="settings.district_placeholder" :no-data-text="settings.locations_unavailable" :disabled="!registration.division_id || districts.length === 0" variant="outlined" hide-details="auto" :rules="[required(settings.district_field_label)]" required />
                <v-select v-model="registration.upazila_id" data-test="upazila-select" :items="upazilas" item-title="label" item-value="id" :label="settings.upazila_field_label" :placeholder="settings.upazila_placeholder" :no-data-text="settings.locations_unavailable" :disabled="!registration.district_id || upazilas.length === 0" variant="outlined" hide-details="auto" :rules="[required(settings.upazila_field_label)]" required />
              </div>
              <p v-if="!locationsAvailable" class="igf-form-alert" role="status">{{ settings.locations_unavailable }}</p>
            </fieldset>

            <fieldset class="igf-form-group">
              <legend>{{ settings.background_section_title }}</legend>
              <div class="igf-field-grid">
                <v-select v-model="registration.occupation" :items="occupationOptions" item-title="label" item-value="value" :label="settings.occupation_field_label" :placeholder="settings.occupation_placeholder" :no-data-text="settings.options_unavailable" :disabled="occupationOptions.length === 0" variant="outlined" hide-details="auto" :rules="[required(settings.occupation_field_label)]" required />
                <v-text-field v-if="registration.occupation === 'other'" v-model="registration.occupation_other" :label="settings.occupation_other_field_label" :placeholder="settings.occupation_other_placeholder" variant="outlined" hide-details="auto" :rules="[required(settings.occupation_other_field_label)]" required />
                <v-select v-model="registration.education_level" :items="educationOptions" item-title="label" item-value="value" :label="settings.education_level_field_label" :placeholder="settings.education_level_placeholder" :no-data-text="settings.options_unavailable" :disabled="educationOptions.length === 0" variant="outlined" hide-details="auto" :rules="[required(settings.education_level_field_label)]" required />
                <v-text-field v-model="registration.institution" :label="settings.institution_field_label" :placeholder="settings.institution_field_placeholder" autocomplete="organization" variant="outlined" hide-details="auto" :rules="[required(settings.institution_field_label)]" required />
              </div>
            </fieldset>

            <fieldset class="igf-form-group">
              <legend>{{ settings.interests_section_title }}</legend>
              <div class="igf-field-grid">
                <v-select v-model="registration.blood_group" :items="bloodGroupOptions" item-title="label" item-value="value" :label="settings.blood_group_field_label" :placeholder="settings.blood_group_placeholder" :no-data-text="settings.options_unavailable" :disabled="bloodGroupOptions.length === 0" variant="outlined" hide-details="auto" :rules="[required(settings.blood_group_field_label)]" required />
                <v-select v-model="registration.emergency_response_training" :items="emergencyResponseOptions" item-title="label" item-value="value" :label="settings.emergency_response_training_field_label" :placeholder="settings.emergency_response_training_placeholder" :no-data-text="settings.options_unavailable" :disabled="emergencyResponseOptions.length === 0" clearable variant="outlined" hide-details="auto" />
                <v-select v-model="registration.skill" :items="skillOptions" item-title="label" item-value="value" :label="settings.skill_field_label" :placeholder="settings.skill_placeholder" :no-data-text="settings.options_unavailable" :disabled="skillOptions.length === 0" variant="outlined" hide-details="auto" :rules="[required(settings.skill_field_label)]" required />
                <v-text-field v-if="registration.skill === 'other'" v-model="registration.skill_other" :label="settings.skill_other_field_label" :placeholder="settings.skill_other_placeholder" variant="outlined" hide-details="auto" :rules="[required(settings.skill_other_field_label)]" required />
                <label class="igf-field--wide igf-native-field" for="volunteer-cause"><span>{{ settings.cause_field_label }}</span><select id="volunteer-cause" v-model="registration.cause_id" :aria-label="settings.cause_field_label" :disabled="causes.length === 0" required><option :value="null" disabled>{{ settings.cause_placeholder }}</option><option v-for="cause in causes" :key="cause.id" :value="cause.id">{{ cause.name }}</option></select></label>
              </div>
              <p v-if="causes.length === 0" class="igf-form-alert" role="status">{{ settings.causes_unavailable }}</p>
            </fieldset>

            <fieldset class="igf-form-group igf-form-group--consent">
              <legend>{{ settings.consent_section_title }}</legend>
              <p class="igf-privacy"><i class="fa-solid fa-shield-halved" aria-hidden="true" /> {{ settings.privacy_note }}</p>
              <v-checkbox v-model="registration.consent" class="igf-consent" color="#9c4500" hide-details="auto" :rules="[consentRequired]" required>
                <template #label>
                  <span>{{ settings.consent_label }} <template v-if="privacyUrl">(<a :href="privacyUrl" @click.stop>{{ settings.privacy_link_label }}</a>)</template></span>
                </template>
              </v-checkbox>
            </fieldset>

            <button class="igf-submit" type="submit" :disabled="!isFormValid || !hasRequiredSelections || loading || causes.length === 0">
              <span>{{ loading ? settings.sending_label : settings.submit_label }}</span><i class="fa-solid fa-arrow-right" aria-hidden="true" />
            </button>
          </v-form>
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
import { useGlobal } from '../Shared/composables/global';
import { interpolateSetting } from '../Shared/composables/siteSettings';
import { responsiveImagePresentation } from '../Shared/composables/responsiveImage';

const page = usePage();
const { $toast } = useGlobal();
const form = ref(null);
const isFormValid = ref(false);
const loading = ref(false);
const settings = computed(() => page.props.siteSettings?.volunteer_page || {});
const heroMedia = computed(() => responsiveImagePresentation(settings.value.hero_image, '(max-width: 900px) 100vw, 50vw'));
const causes = computed(() => page.props.data?.causes || []);
const steps = computed(() => [1, 2, 3].map(index => ({ title: settings.value[`step_${index}_title`], body: settings.value[`step_${index}_body`] })));
const registration = ref(emptyForm());
const suppliedOptions = computed(() => page.props.data?.options || {});
const suppliedLocations = computed(() => page.props.data?.locations || {});
const sexOptions = computed(() => optionItems('sex'));
const occupationOptions = computed(() => optionItems('occupations'));
const educationOptions = computed(() => optionItems('education_levels'));
const bloodGroupOptions = computed(() => optionItems('blood_groups'));
const emergencyResponseOptions = computed(() => optionItems('emergency_response_training'));
const skillOptions = computed(() => optionItems('skills'));
const formOptionsAvailable = computed(() => [sexOptions, occupationOptions, educationOptions, bloodGroupOptions, skillOptions].every(items => items.value.length > 0));
const divisions = computed(() => locationItems('divisions'));
const districts = computed(() => locationItems('districts').filter(item => sameId(item.parent_id, registration.value.division_id)));
const upazilas = computed(() => locationItems('upazilas').filter(item => sameId(item.parent_id, registration.value.district_id)));
const locationsAvailable = computed(() => divisions.value.length > 0 && locationItems('districts').length > 0 && locationItems('upazilas').length > 0);
const privacyUrl = computed(() => safeSiteUrl(settings.value.privacy_link_url) || safeSiteUrl(page.props.siteSettings?.shared_blocks?.newsletter_privacy_url));
const maxDateOfBirth = localIsoDate(-1);
const required = (label) => (value) => !!value || interpolateSetting(settings.value.required_message || '{field} is required', { field: label });
const consentRequired = value => value === true || settings.value.consent_required_message || 'Consent is required';
const emailRules = computed(() => [required(settings.value.email_field_label || 'Email'), value => /.+@.+\..+/.test(value) || settings.value.invalid_email_message]);
const hasRequiredSelections = computed(() => {
  const values = [
    registration.value.sex,
    registration.value.date_of_birth,
    registration.value.division_id,
    registration.value.district_id,
    registration.value.upazila_id,
    registration.value.occupation,
    registration.value.education_level,
    registration.value.blood_group,
    registration.value.skill,
    registration.value.cause_id,
  ];

  if (!values.every(hasValue) || registration.value.consent !== true) return false;
  if (registration.value.occupation === 'other' && !hasValue(registration.value.occupation_other)) return false;
  return registration.value.skill !== 'other' || hasValue(registration.value.skill_other);
});

watch(() => registration.value.division_id, () => {
  registration.value.district_id = null;
  registration.value.upazila_id = null;
});
watch(() => registration.value.district_id, () => {
  registration.value.upazila_id = null;
});
watch(() => registration.value.occupation, value => {
  if (value !== 'other') registration.value.occupation_other = '';
});
watch(() => registration.value.skill, value => {
  if (value !== 'other') registration.value.skill_other = '';
});

function optionItems(key) {
  const items = suppliedOptions.value?.[key];
  return Array.isArray(items)
    ? items.filter(item => item && Object.prototype.hasOwnProperty.call(item, 'value') && item.label)
    : [];
}
function locationItems(key) {
  const items = suppliedLocations.value?.[key];
  return Array.isArray(items)
    ? items
      .filter(item => item?.id != null && (item.label || item.name))
      .map(item => ({ ...item, label: item.label || item.name }))
    : [];
}
function sameId(left, right) {
  return left != null && right != null && String(left) === String(right);
}
function hasValue(value) {
  return value !== null && value !== undefined && String(value).trim() !== '';
}
function localIsoDate(dayOffset = 0) {
  const value = new Date();
  value.setDate(value.getDate() + dayOffset);
  const month = String(value.getMonth() + 1).padStart(2, '0');
  const day = String(value.getDate()).padStart(2, '0');
  return `${value.getFullYear()}-${month}-${day}`;
}
function safeSiteUrl(value) {
  const candidate = String(value || '').trim();
  if (/^\/(?!\/)/.test(candidate)) return candidate;
  try {
    const parsed = new URL(candidate);
    return ['http:', 'https:'].includes(parsed.protocol) ? candidate : '';
  } catch {
    return '';
  }
}
function emptyForm() {
  return {
    name: '',
    institution: '',
    email: '',
    phone: '',
    address: '',
    cause_id: null,
    sex: null,
    date_of_birth: '',
    division_id: null,
    district_id: null,
    upazila_id: null,
    occupation: null,
    occupation_other: '',
    education_level: null,
    blood_group: null,
    emergency_response_training: null,
    skill: null,
    skill_other: '',
    consent: false,
  };
}
async function submitRegistration() {
  const result = await form.value?.validate();
  if (!result?.valid || !hasRequiredSelections.value) return;
  loading.value = true;
  router.post(route('frontend.volunteer_registration.store'), registration.value, {
    preserveScroll: true,
    onSuccess: async responsePage => {
      $toast.success(settings.value.success_message || responsePage.props?.flash?.success || '');
      registration.value = emptyForm();
      await form.value?.resetValidation();
    },
    onError: errors => {
      const firstError = Object.values(errors || {})[0];
      $toast.error(firstError || settings.value.error_message || '');
    },
    onFinish: () => { loading.value = false; },
  });
}
</script>

<style scoped>
.igf-volunteer{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#5f6065;--surface:#f8f9fa;--line:#dedbd7;overflow:hidden;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell,.igf-volunteer__hero-inner{width:min(100% - 40px,1240px);margin:0 auto}.igf-volunteer :is(h1,h2,h3){margin-top:0;color:inherit;font-family:'Literata',Georgia,serif;letter-spacing:-.03em}.igf-volunteer :is(h1,h2,h3)::after{display:none!important}.igf-eyebrow{margin:0 0 16px!important;color:var(--brown)!important;font-size:12px!important;font-weight:800!important;letter-spacing:.1em;text-transform:uppercase}.igf-volunteer__hero{padding:clamp(96px,12vw,150px) 0 clamp(70px,9vw,110px);background:#202223;color:#fff}.igf-volunteer__hero-inner{display:grid;grid-template-columns:minmax(0,1fr) minmax(380px,.9fr);align-items:center;gap:clamp(48px,7vw,100px)}.igf-volunteer__hero .igf-eyebrow{color:#ffb070!important}.igf-volunteer__hero-copy h1{max-width:720px;margin-bottom:24px;font-size:clamp(44px,6vw,76px);font-weight:650;line-height:1.03}.igf-volunteer__hero-copy>p:not(.igf-eyebrow){max-width:650px;color:#dadbdc;font-size:20px;line-height:1.65}.igf-primary-link{display:inline-flex;min-height:52px;align-items:center;gap:12px;margin-top:20px;padding:0 25px;border-radius:999px;background:var(--orange);color:#fff;font-size:13px;font-weight:800;letter-spacing:.04em;text-decoration:none;text-transform:uppercase}.igf-volunteer__hero-media{overflow:hidden;margin:0;border:1px solid rgba(255,255,255,.16);border-radius:24px;background:#343637}.igf-volunteer__hero-media img{display:block;width:100%;aspect-ratio:4/5;object-fit:cover}.igf-process{padding:clamp(75px,9vw,115px) 0;background:#fff}.igf-process header{max-width:640px;margin-bottom:45px}.igf-process h2,.igf-form-layout aside h2{margin-bottom:0;font-size:clamp(36px,4.4vw,54px);font-weight:620;line-height:1.12}.igf-process ol{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin:0;padding:0;list-style:none}.igf-process li{display:flex;min-height:210px;gap:20px;padding:30px 26px;border:1px solid var(--line);border-top:4px solid var(--orange);border-radius:16px;background:#fff}.igf-process li>span{color:var(--brown);font-size:13px;font-weight:900}.igf-process h3{margin-bottom:13px;font-size:22px;line-height:1.25}.igf-process li p{margin:0;color:var(--muted);font-size:15px;line-height:1.65}.igf-volunteer__form-section{padding:clamp(75px,9vw,120px) 0;background:var(--surface)}.igf-form-layout{display:grid;grid-template-columns:minmax(0,.75fr) minmax(540px,1.25fr);align-items:start;gap:clamp(45px,7vw,90px)}.igf-form-layout aside>p:not(.igf-eyebrow){margin:21px 0;color:var(--muted);font-size:18px;line-height:1.65}.igf-contact-note{display:flex;gap:15px;margin-top:32px;padding:22px;border:1px solid #e5d1c1;border-radius:14px;background:#fff7f1}.igf-contact-note>i{margin-top:3px;color:var(--brown);font-size:22px}.igf-contact-note div{display:grid;gap:6px}.igf-contact-note span{color:var(--muted);font-size:14px;line-height:1.55}.igf-registration-form{padding:clamp(24px,4vw,42px);border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 12px 40px rgba(25,28,29,.07)}.igf-form-group{min-width:0;margin:0;padding:0;border:0}.igf-form-group+.igf-form-group{margin-top:34px;padding-top:30px;border-top:1px solid var(--line)}.igf-form-group legend{width:100%;margin:0 0 20px;color:var(--ink);font:700 21px/1.3 'Literata',Georgia,serif;letter-spacing:-.015em}.igf-field-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px 16px}.igf-field-grid+.igf-field-grid{margin-top:20px}.igf-field-grid--location{grid-template-columns:repeat(3,minmax(0,1fr))}.igf-field--wide{grid-column:1/-1}.igf-registration-form :deep(.v-field){border-radius:9px;background:#fff}.igf-registration-form :deep(.v-label){color:#4e5052;opacity:1}.igf-privacy{display:flex;align-items:flex-start;gap:10px;margin:0 0 10px!important;color:var(--muted)!important;font-size:13px!important;line-height:1.5}.igf-privacy i{margin-top:3px;color:var(--brown)}.igf-consent{margin:0}.igf-consent :deep(.v-label){align-items:flex-start;color:var(--ink);font-size:14px;line-height:1.55;opacity:1}.igf-consent a{color:var(--brown);font-weight:800;text-underline-offset:3px}.igf-form-alert{margin:18px 0 0!important;padding:13px;border-radius:8px;background:#fff3e9;color:var(--brown)!important;font-size:13px!important}.igf-form-alert--top{margin:0 0 24px!important}.igf-submit{display:flex;width:100%;min-height:54px;align-items:center;justify-content:center;gap:12px;margin-top:30px;border:0;border-radius:999px;background:var(--orange);color:#fff;font-size:13px;font-weight:800;letter-spacing:.045em;text-transform:uppercase}.igf-submit:disabled{cursor:not-allowed;opacity:.55}
.igf-native-field{display:grid;gap:7px;color:var(--ink);font-size:12px;font-weight:700}.igf-native-field select{width:100%;min-height:56px;border:1px solid #79747e;border-radius:9px;padding:0 15px;background:#fff;color:var(--ink);font:500 16px 'Hanken Grotesk',Arial,sans-serif}.igf-native-field select:focus{border:2px solid var(--brown);outline:2px solid transparent}
@media(max-width:960px){.igf-volunteer__hero-inner{grid-template-columns:1fr 330px;gap:35px}.igf-form-layout{grid-template-columns:1fr}.igf-form-layout aside{max-width:700px}}
@media(max-width:720px){.igf-volunteer__hero{padding-top:72px}.igf-volunteer__hero-inner{grid-template-columns:1fr}.igf-volunteer__hero-copy h1{font-size:44px}.igf-volunteer__hero-media img{aspect-ratio:4/3}.igf-process ol{grid-template-columns:1fr}.igf-process li{min-height:0}.igf-form-layout{gap:36px}.igf-field-grid,.igf-field-grid--location{grid-template-columns:1fr}.igf-field--wide{grid-column:auto}.igf-registration-form{padding:24px 18px}.igf-form-group+.igf-form-group{margin-top:28px;padding-top:25px}.igf-form-group legend{font-size:19px}}
@media(max-width:390px){.igf-primary-link{width:100%;justify-content:center}}
@media(prefers-reduced-motion:reduce){.igf-volunteer *{scroll-behavior:auto!important}}
</style>
