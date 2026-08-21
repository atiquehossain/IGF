<template>
  <Layout>
    <div class="igf-contact">
      <header class="igf-contact__hero">
        <div class="igf-shell">
          <p class="igf-eyebrow">{{ content.eyebrow }}</p>
          <h1>{{ content.title }}</h1>
          <p>{{ content.introduction }}</p>
        </div>
      </header>

      <section class="igf-contact__body">
        <div class="igf-shell igf-contact__grid">
          <aside class="igf-contact__details" :aria-label="content.details_accessible_label">
            <p class="igf-eyebrow">{{ content.details_eyebrow }}</p>
            <h2>{{ content.details_title }}</h2>
            <a v-if="contact.email" :href="`mailto:${contact.email}`"><i class="fa-regular fa-envelope" aria-hidden="true" /><span><small>{{ content.email_label }}</small>{{ contact.email }}</span></a>
            <a v-if="contact.phone_primary" :href="phoneHref(contact.phone_primary)"><i class="fa-solid fa-phone" aria-hidden="true" /><span><small>{{ content.phone_label }}</small>{{ contact.phone_primary }}</span></a>
            <a v-if="contact.phone_secondary" :href="phoneHref(contact.phone_secondary)"><i class="fa-solid fa-phone" aria-hidden="true" /><span><small>{{ content.phone_label }}</small>{{ contact.phone_secondary }}</span></a>
            <div v-if="contact.address" class="igf-contact__address"><i class="fa-solid fa-location-dot" aria-hidden="true" /><span><small>{{ content.address_label }}</small>{{ contact.address }}</span></div>
            <p class="igf-contact__response"><i class="fa-regular fa-clock" aria-hidden="true" /> {{ content.response_note }}</p>
          </aside>

          <section class="igf-contact__form-card" aria-labelledby="contact-form-title">
            <p class="igf-eyebrow">{{ content.form_eyebrow }}</p>
            <h2 id="contact-form-title">{{ content.form_title }}</h2>
            <form @submit.prevent="sendMessage" novalidate>
              <label for="contact-name">{{ content.name_field_label }} <span aria-hidden="true">*</span></label>
              <input id="contact-name" v-model="form.first_name" type="text" autocomplete="name" required :aria-invalid="!!form.errors.first_name" :aria-describedby="form.errors.first_name ? 'contact-name-error' : undefined">
              <small v-if="form.errors.first_name" id="contact-name-error" class="igf-error">{{ form.errors.first_name }}</small>

              <label for="contact-email">{{ content.email_field_label }} <span aria-hidden="true">*</span></label>
              <input id="contact-email" v-model="form.email" type="email" autocomplete="email" required :aria-invalid="!!form.errors.email" :aria-describedby="form.errors.email ? 'contact-email-error' : undefined">
              <small v-if="form.errors.email" id="contact-email-error" class="igf-error">{{ form.errors.email }}</small>

              <label for="contact-phone">{{ content.phone_field_label }} <span aria-hidden="true">*</span></label>
              <input id="contact-phone" v-model="form.phone" type="tel" autocomplete="tel" required :aria-invalid="!!form.errors.phone" :aria-describedby="form.errors.phone ? 'contact-phone-error' : undefined">
              <small v-if="form.errors.phone" id="contact-phone-error" class="igf-error">{{ form.errors.phone }}</small>

              <label for="contact-message">{{ content.message_field_label }} <span aria-hidden="true">*</span></label>
              <textarea id="contact-message" v-model="form.message" rows="6" required :aria-invalid="!!form.errors.message" :aria-describedby="form.errors.message ? 'contact-message-error' : undefined" />
              <small v-if="form.errors.message" id="contact-message-error" class="igf-error">{{ form.errors.message }}</small>

              <button type="submit" :disabled="form.processing">{{ form.processing ? content.sending_label : content.submit_label }} <span aria-hidden="true">&rarr;</span></button>
              <p v-if="submitError" class="igf-error" role="alert">{{ submitError }}</p>
              <p v-if="showSuccess" class="igf-success" role="status" aria-live="polite"><i class="fa-solid fa-circle-check" aria-hidden="true" /> {{ content.success_message }}</p>
            </form>
          </section>
        </div>
      </section>

      <section v-if="faqItems.length" class="igf-contact__faq" aria-labelledby="faq-title">
        <div class="igf-shell igf-contact__faq-grid">
          <div><p class="igf-eyebrow">{{ content.faq_eyebrow }}</p><h2 id="faq-title">{{ content.faq_title }}</h2></div>
          <div class="igf-accordion">
            <div v-for="(item,index) in faqItems" :key="index" class="igf-accordion__item">
              <h3>
                <button type="button" :id="`faq-button-${index}`" :aria-expanded="openItems.includes(index)" :aria-controls="`faq-panel-${index}`" @click="toggleAccordion(index)">
                  {{ item.question }} <i :class="openItems.includes(index) ? 'fa-solid fa-minus' : 'fa-solid fa-plus'" aria-hidden="true" />
                </button>
              </h3>
              <div v-show="openItems.includes(index)" :id="`faq-panel-${index}`" role="region" :aria-labelledby="`faq-button-${index}`"><p>{{ item.answer }}</p></div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App';
const page = usePage();
const settings = computed(() => page.props.siteSettings || {});
const contact = computed(() => settings.value.contact || {});
const content = computed(() => settings.value.contact_page || {});
const faqItems = computed(() => Array.from({length:5},(_,index) => ({
  question: content.value[`faq_${index + 1}_question`], answer: content.value[`faq_${index + 1}_answer`],
})).filter(item => item.question));
const openItems = ref([0]);
const showSuccess = ref(false);
const submitError = ref('');
const form = useForm({ first_name:'', email:'', phone:'', message:'' });
const phoneHref = value => `tel:${String(value || '').replace(/[^+\d]/g,'')}`;
function toggleAccordion(index) { openItems.value = openItems.value.includes(index) ? openItems.value.filter(item => item !== index) : [...openItems.value,index]; }
function sendMessage() {
  submitError.value = '';
  form.post(route('frontend.send.sms'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      showSuccess.value = true;
      window.setTimeout(() => { showSuccess.value = false; }, 5000);
    },
    onError: errors => {
      submitError.value = Object.values(errors || {})[0] || content.value.error_message || '';
    },
  });
}
</script>

<style scoped lang="scss">
.igf-contact{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#5e5d66;--surface:#f8f9fa;--line:#e5e0dc;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell{width:min(calc(100% - 40px),1200px);margin-inline:auto}.igf-eyebrow{margin:0 0 14px;color:var(--brown);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-contact h1,.igf-contact h2{font-family:'Literata',Georgia,serif;letter-spacing:-.035em}.igf-contact h1::after,.igf-contact h2::after{display:none!important}.igf-contact__hero{padding:clamp(80px,10vw,130px) 0;background:#242220;color:#fff}.igf-contact__hero .igf-eyebrow{color:#ffad72}.igf-contact__hero h1{max-width:850px;margin:0;color:#fff;font-size:clamp(46px,6vw,74px);font-weight:650;line-height:1.04}.igf-contact__hero>div>p:last-child{max-width:720px;margin:23px 0 0;color:#d7d3cf;font-size:19px;line-height:1.65}.igf-contact__body{padding:clamp(70px,9vw,120px) 0;background:var(--surface)}.igf-contact__grid{display:grid;grid-template-columns:minmax(270px,.75fr) minmax(0,1.25fr);gap:clamp(50px,9vw,110px)}.igf-contact__details{padding-top:26px}.igf-contact__details h2,.igf-contact__form-card h2,.igf-contact__faq h2{margin:0 0 26px;font-size:clamp(32px,4vw,46px);font-weight:620;line-height:1.12}.igf-contact__details>a,.igf-contact__address{display:grid;grid-template-columns:40px 1fr;gap:13px;margin-bottom:22px;color:var(--ink);text-decoration:none}.igf-contact__details>a>i,.igf-contact__address>i{display:grid;width:40px;height:40px;place-items:center;border:1px solid #efc7a9;border-radius:50%;color:var(--brown)}.igf-contact__details span{white-space:pre-line}.igf-contact__details small{display:block;margin-bottom:3px;color:var(--muted);font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.igf-contact__response{display:flex;gap:9px;margin-top:35px;border-top:1px solid var(--line);padding-top:22px;color:var(--muted);font-size:12px}.igf-contact__form-card{border:1px solid var(--line);border-top:5px solid var(--orange);border-radius:18px;padding:clamp(28px,5vw,50px);background:#fff;box-shadow:0 12px 34px rgba(25,28,29,.07)}.igf-contact__form-card form{display:grid}.igf-contact__form-card label{margin:16px 0 7px;font-size:12px;font-weight:800}.igf-contact__form-card input,.igf-contact__form-card textarea{width:100%;border:1px solid #d2cdc8;border-radius:8px;padding:12px 13px;background:#fff;color:var(--ink);font:14px/1.5 inherit;outline:0}.igf-contact__form-card input:focus,.igf-contact__form-card textarea:focus{border-color:var(--brown);box-shadow:0 0 0 3px rgba(156,69,0,.09)}.igf-contact__form-card button{min-height:52px;margin-top:25px;border:1px solid var(--orange);border-radius:999px;background:var(--orange);color:#fff;font-weight:800;cursor:pointer}.igf-contact__form-card button:disabled{opacity:.58;cursor:wait}.igf-error{margin-top:5px;color:#b42318}.igf-success{display:flex;gap:8px;margin:14px 0 0;color:#247044;font-size:13px;font-weight:800}.igf-contact__faq{padding:clamp(70px,9vw,120px) 0;background:#fff}.igf-contact__faq-grid{display:grid;grid-template-columns:.75fr 1.25fr;gap:clamp(50px,9vw,110px)}.igf-accordion__item{border-top:1px solid var(--line)}.igf-accordion__item:last-child{border-bottom:1px solid var(--line)}.igf-accordion h3{margin:0}.igf-accordion button{display:flex;width:100%;min-height:65px;align-items:center;justify-content:space-between;gap:20px;border:0;padding:16px 2px;background:transparent;color:var(--ink);font:650 17px/1.35 'Literata',Georgia,serif;text-align:left;cursor:pointer}.igf-accordion button i{color:var(--orange);font-size:13px}.igf-accordion__item>div{padding:0 35px 20px 2px}.igf-accordion__item p{margin:0;color:var(--muted);line-height:1.7}@media(max-width:850px){.igf-contact__grid,.igf-contact__faq-grid{grid-template-columns:1fr}.igf-contact__details{padding-top:0}}@media(max-width:600px){.igf-shell{width:min(calc(100% - 28px),1200px)}.igf-contact__form-card{border-radius:13px}.igf-contact__hero{padding-block:72px}}
</style>
