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
            <div class="igf-field-grid">
              <v-text-field v-model="registration.name" :label="settings.name_field_label" autocomplete="name" variant="outlined" hide-details="auto" :rules="[required(settings.name_field_label)]" required />
              <v-text-field v-model="registration.institution" :label="settings.institution_field_label" autocomplete="organization" variant="outlined" hide-details="auto" :rules="[required(settings.institution_field_label)]" required />
              <v-text-field v-model="registration.email" :label="settings.email_field_label" autocomplete="email" type="email" variant="outlined" hide-details="auto" :rules="emailRules" required />
              <v-text-field v-model="registration.phone" :label="settings.phone_field_label" autocomplete="tel" type="tel" variant="outlined" hide-details="auto" :rules="[required(settings.phone_field_label)]" required />
              <v-textarea v-model="registration.address" class="igf-field--wide" :label="settings.address_field_label" autocomplete="street-address" variant="outlined" rows="2" auto-grow hide-details="auto" :rules="[required(settings.address_field_label)]" required />
              <label class="igf-field--wide igf-native-field" for="volunteer-cause"><span>{{ settings.cause_field_label }}</span><select id="volunteer-cause" v-model="registration.cause_id" :aria-label="settings.cause_field_label" :disabled="causes.length === 0" required><option :value="null" disabled>{{ settings.cause_placeholder }}</option><option v-for="cause in causes" :key="cause.id" :value="cause.id">{{ cause.name }}</option></select></label>
            </div>
            <p v-if="causes.length === 0" class="igf-form-alert" role="status">{{ settings.causes_unavailable }}</p>
            <p class="igf-privacy"><i class="fa-solid fa-shield-halved" aria-hidden="true" /> {{ settings.privacy_note }}</p>
            <button class="igf-submit" type="submit" :disabled="!isFormValid || !registration.cause_id || loading || causes.length === 0">
              <span>{{ loading ? settings.sending_label : settings.submit_label }}</span><i class="fa-solid fa-arrow-right" aria-hidden="true" />
            </button>
          </v-form>
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed, ref } from 'vue';
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
const required = (label) => (value) => !!value || interpolateSetting(settings.value.required_message || '{field} is required', { field: label });
const emailRules = computed(() => [required(settings.value.email_field_label || 'Email'), value => /.+@.+\..+/.test(value) || settings.value.invalid_email_message]);

function emptyForm() { return { name: '', institution: '', email: '', phone: '', address: '', cause_id: null }; }
async function submitRegistration() {
  const result = await form.value?.validate();
  if (!result?.valid || !registration.value.cause_id) return;
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
.igf-volunteer{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#5f6065;--surface:#f8f9fa;--line:#dedbd7;overflow:hidden;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell,.igf-volunteer__hero-inner{width:min(100% - 40px,1240px);margin:0 auto}.igf-volunteer :is(h1,h2,h3){margin-top:0;color:inherit;font-family:'Literata',Georgia,serif;letter-spacing:-.03em}.igf-volunteer :is(h1,h2,h3)::after{display:none!important}.igf-eyebrow{margin:0 0 16px!important;color:var(--brown)!important;font-size:12px!important;font-weight:800!important;letter-spacing:.1em;text-transform:uppercase}.igf-volunteer__hero{padding:clamp(96px,12vw,150px) 0 clamp(70px,9vw,110px);background:#202223;color:#fff}.igf-volunteer__hero-inner{display:grid;grid-template-columns:minmax(0,1fr) minmax(380px,.9fr);align-items:center;gap:clamp(48px,7vw,100px)}.igf-volunteer__hero .igf-eyebrow{color:#ffb070!important}.igf-volunteer__hero-copy h1{max-width:720px;margin-bottom:24px;font-size:clamp(44px,6vw,76px);font-weight:650;line-height:1.03}.igf-volunteer__hero-copy>p:not(.igf-eyebrow){max-width:650px;color:#dadbdc;font-size:20px;line-height:1.65}.igf-primary-link{display:inline-flex;min-height:52px;align-items:center;gap:12px;margin-top:20px;padding:0 25px;border-radius:999px;background:var(--orange);color:#fff;font-size:13px;font-weight:800;letter-spacing:.04em;text-decoration:none;text-transform:uppercase}.igf-volunteer__hero-media{overflow:hidden;margin:0;border:1px solid rgba(255,255,255,.16);border-radius:24px;background:#343637}.igf-volunteer__hero-media img{display:block;width:100%;aspect-ratio:4/5;object-fit:cover}.igf-process{padding:clamp(75px,9vw,115px) 0;background:#fff}.igf-process header{max-width:640px;margin-bottom:45px}.igf-process h2,.igf-form-layout aside h2{margin-bottom:0;font-size:clamp(36px,4.4vw,54px);font-weight:620;line-height:1.12}.igf-process ol{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin:0;padding:0;list-style:none}.igf-process li{display:flex;min-height:210px;gap:20px;padding:30px 26px;border:1px solid var(--line);border-top:4px solid var(--orange);border-radius:16px;background:#fff}.igf-process li>span{color:var(--brown);font-size:13px;font-weight:900}.igf-process h3{margin-bottom:13px;font-size:22px;line-height:1.25}.igf-process li p{margin:0;color:var(--muted);font-size:15px;line-height:1.65}.igf-volunteer__form-section{padding:clamp(75px,9vw,120px) 0;background:var(--surface)}.igf-form-layout{display:grid;grid-template-columns:minmax(0,.75fr) minmax(540px,1.25fr);align-items:start;gap:clamp(45px,7vw,90px)}.igf-form-layout aside>p:not(.igf-eyebrow){margin:21px 0;color:var(--muted);font-size:18px;line-height:1.65}.igf-contact-note{display:flex;gap:15px;margin-top:32px;padding:22px;border:1px solid #e5d1c1;border-radius:14px;background:#fff7f1}.igf-contact-note>i{margin-top:3px;color:var(--brown);font-size:22px}.igf-contact-note div{display:grid;gap:6px}.igf-contact-note span{color:var(--muted);font-size:14px;line-height:1.55}.igf-registration-form{padding:clamp(24px,4vw,42px);border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 12px 40px rgba(25,28,29,.07)}.igf-field-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px 16px}.igf-field--wide{grid-column:1/-1}.igf-registration-form :deep(.v-field){border-radius:9px;background:#fff}.igf-registration-form :deep(.v-label){color:#4e5052;opacity:1}.igf-privacy{display:flex;align-items:flex-start;gap:10px;margin:23px 0!important;color:var(--muted)!important;font-size:13px!important;line-height:1.5}.igf-privacy i{margin-top:3px;color:var(--brown)}.igf-form-alert{margin:18px 0 0!important;padding:13px;border-radius:8px;background:#fff3e9;color:var(--brown)!important;font-size:13px!important}.igf-submit{display:flex;width:100%;min-height:54px;align-items:center;justify-content:center;gap:12px;border:0;border-radius:999px;background:var(--orange);color:#fff;font-size:13px;font-weight:800;letter-spacing:.045em;text-transform:uppercase}.igf-submit:disabled{cursor:not-allowed;opacity:.55}
.igf-native-field{display:grid;gap:7px;color:var(--ink);font-size:12px;font-weight:700}.igf-native-field select{width:100%;min-height:56px;border:1px solid #79747e;border-radius:9px;padding:0 15px;background:#fff;color:var(--ink);font:500 16px 'Hanken Grotesk',Arial,sans-serif}.igf-native-field select:focus{border:2px solid var(--brown);outline:2px solid transparent}
@media(max-width:960px){.igf-volunteer__hero-inner{grid-template-columns:1fr 330px;gap:35px}.igf-form-layout{grid-template-columns:1fr}.igf-form-layout aside{max-width:700px}}
@media(max-width:720px){.igf-volunteer__hero{padding-top:72px}.igf-volunteer__hero-inner{grid-template-columns:1fr}.igf-volunteer__hero-copy h1{font-size:44px}.igf-volunteer__hero-media img{aspect-ratio:4/3}.igf-process ol{grid-template-columns:1fr}.igf-process li{min-height:0}.igf-form-layout{gap:36px}.igf-field-grid{grid-template-columns:1fr}.igf-field--wide{grid-column:auto}.igf-registration-form{padding:24px 18px}}
@media(max-width:390px){.igf-primary-link{width:100%;justify-content:center}}
@media(prefers-reduced-motion:reduce){.igf-volunteer *{scroll-behavior:auto!important}}
</style>
