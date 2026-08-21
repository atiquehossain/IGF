<template>
  <GuestLayout>
    <div class="igf-register">
      <section class="igf-register__story">
        <a :href="route('frontend.home')"><img :src="branding.footer_logo || branding.logo" :alt="branding.logo_alt || branding.site_name"></a>
        <div><p>{{ content.story_eyebrow }}</p><h1>{{ content.story_title }}</h1><span>{{ content.story_body }}</span></div>
      </section>
      <section class="igf-register__panel" aria-labelledby="registration-title">
        <div class="igf-register__card">
          <a class="igf-register__back" :href="route('showLogin')"><span aria-hidden="true">&larr;</span> {{ content.registration_login_label }}</a>
          <p class="igf-eyebrow">{{ content.form_eyebrow }}</p>
          <h2 id="registration-title">{{ content.registration_title }}</h2>
          <p class="igf-register__intro">{{ content.registration_introduction }}</p>
          <form @submit.prevent="submit">
            <label for="registration-name">{{ content.registration_name_label }}<input id="registration-name" v-model="form.name" type="text" autocomplete="name" maxlength="50" required></label>
            <p v-if="form.errors.name" class="igf-error" role="alert">{{ form.errors.name }}</p>
            <label for="registration-phone">{{ content.phone_label }}<input id="registration-phone" v-model="form.phone_no" type="tel" inputmode="numeric" autocomplete="tel" maxlength="11" :placeholder="content.phone_placeholder" required></label>
            <p v-if="form.errors.phone_no" class="igf-error" role="alert">{{ form.errors.phone_no }}</p>
            <label for="registration-email">{{ content.registration_email_label }}<input id="registration-email" v-model="form.email" type="email" autocomplete="email" maxlength="50" required></label>
            <p v-if="form.errors.email" class="igf-error" role="alert">{{ form.errors.email }}</p>
            <label for="registration-organization">{{ content.registration_organization_label }}<input id="registration-organization" v-model="form.org" type="text" autocomplete="organization" maxlength="150" required></label>
            <p v-if="form.errors.org" class="igf-error" role="alert">{{ form.errors.org }}</p>
            <label for="registration-designation">{{ content.registration_designation_label }}<input id="registration-designation" v-model="form.designation" type="text" autocomplete="organization-title" maxlength="150" required></label>
            <p v-if="form.errors.designation" class="igf-error" role="alert">{{ form.errors.designation }}</p>
            <label for="registration-password">{{ content.password_label }}<span class="igf-password"><input id="registration-password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" minlength="8" required><button type="button" :aria-label="showPassword ? content.hide_password_label : content.show_password_label" @click="showPassword = !showPassword"><i :class="showPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'" aria-hidden="true" /></button></span></label>
            <p v-if="form.errors.password" class="igf-error" role="alert">{{ form.errors.password }}</p>
            <p class="igf-register__approval"><i class="fa-solid fa-user-shield" aria-hidden="true" /> {{ content.registration_approval_note }}</p>
            <button class="igf-submit" type="submit" :disabled="form.processing">{{ form.processing ? content.registration_sending_label : content.registration_submit_label }} <i class="fa-solid fa-arrow-right" aria-hidden="true" /></button>
          </form>
        </div>
      </section>
    </div>
  </GuestLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '../../layouts/GuestLayout.vue';

const page = usePage();
const showPassword = ref(false);
const content = computed(() => page.props.siteSettings?.member_area || {});
const branding = computed(() => page.props.siteSettings?.branding || {});
const form = useForm({ name: '', phone_no: '', email: '', org: '', designation: '', password: '' });

function submit() {
  form.phone_no = String(form.phone_no || '').replace(/\D/g, '').slice(0, 11);
  form.post(route('register'), { preserveScroll: true, onFinish: () => form.reset('password') });
}
</script>

<style scoped>
.igf-register{--orange:var(--igf-primary,#ff7500);--brown:var(--igf-accent,#9c4500);--ink:var(--igf-ink,#191c1d);--muted:#5f6065;--line:#dedbd7;display:grid;min-height:100vh;grid-template-columns:minmax(330px,.8fr) minmax(560px,1.2fr);background:#fff;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-register__story{display:flex;padding:50px clamp(35px,6vw,80px);flex-direction:column;justify-content:space-between;background:#202223;color:#fff}.igf-register__story img{width:145px;max-height:90px;object-fit:contain}.igf-register__story p{margin:0 0 15px;color:#ffb070;font-size:11px;font-weight:800;letter-spacing:.11em;text-transform:uppercase}.igf-register__story h1{max-width:570px;margin:0;color:#fff;font:650 clamp(42px,5vw,66px)/1.05 'Literata',Georgia,serif;letter-spacing:-.035em}.igf-register__story h1::after,.igf-register h2::after{display:none!important}.igf-register__story span{display:block;max-width:540px;margin-top:23px;color:#d5d6d7;font-size:18px;line-height:1.65}.igf-register__panel{display:grid;padding:45px 30px;place-items:center}.igf-register__card{width:min(100%,560px)}.igf-register__back{display:inline-flex;gap:8px;margin-bottom:35px;color:var(--muted);font-size:13px;font-weight:800;text-decoration:none}.igf-eyebrow{margin:0 0 11px;color:var(--brown);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-register h2{margin:0;color:var(--ink);font:650 clamp(36px,5vw,48px)/1.1 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-register__intro{margin:14px 0 25px;color:var(--muted);line-height:1.6}.igf-register form{display:grid;grid-template-columns:1fr 1fr;gap:7px 14px}.igf-register form>label{display:grid;gap:7px;margin-top:8px;color:var(--ink);font-size:12px;font-weight:800}.igf-register form>label:first-of-type,.igf-register form>label:last-of-type,.igf-register__approval,.igf-submit{grid-column:1/-1}.igf-register input{width:100%;height:48px;padding:0 13px;border:1px solid #c8c4c0;border-radius:8px;color:var(--ink);font:15px 'Hanken Grotesk',Arial,sans-serif}.igf-register input:focus{border-color:var(--orange);outline:3px solid rgba(255,117,0,.2)}.igf-password{position:relative;display:flex;align-items:center}.igf-password input{padding-right:48px}.igf-password button{position:absolute;right:4px;display:grid;width:40px;height:40px;place-items:center;border:0;background:transparent;color:var(--muted)}.igf-error{margin:0;color:#9b2c25;font-size:11px}.igf-register__approval{display:flex;align-items:flex-start;gap:9px;margin:15px 0 8px;padding:12px;border-radius:8px;background:#fff5e9;color:#6d4c2d;font-size:11px;line-height:1.5}.igf-register__approval i{margin-top:2px;color:var(--brown)}.igf-submit{display:flex;min-height:52px;align-items:center;justify-content:center;gap:11px;border:0;border-radius:999px;background:var(--orange);color:#fff;font-weight:800}.igf-submit:disabled{opacity:.55}@media(max-width:900px){.igf-register{grid-template-columns:1fr}.igf-register__story{min-height:350px}.igf-register__panel{padding:50px 22px}}@media(max-width:560px){.igf-register form{grid-template-columns:1fr}.igf-register form>label,.igf-error{grid-column:1}.igf-register__story{padding:35px 24px}.igf-register__story h1{font-size:40px}}
</style>
