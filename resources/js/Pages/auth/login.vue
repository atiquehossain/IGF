<template>
  <GuestLayout>
    <div class="igf-auth">
      <section class="igf-auth__story"><a :href="route('frontend.home')"><img :src="branding.footer_logo" :alt="branding.logo_alt"></a><div><p>{{ content.story_eyebrow }}</p><h1>{{ content.story_title }}</h1><span>{{ content.story_body }}</span></div></section>
      <section class="igf-auth__panel" aria-labelledby="login-title">
        <div class="igf-auth__card">
          <a class="igf-auth__back" :href="route('frontend.home')"><span aria-hidden="true">&larr;</span> {{ content.back_label }}</a>
          <p class="igf-eyebrow">{{ content.form_eyebrow }}</p><h2 id="login-title">{{ content.title }}</h2><p class="igf-auth__intro">{{ content.introduction }}</p>
          <form @submit.prevent="login">
            <label for="login-phone">{{ content.phone_label }}<input id="login-phone" v-model="form.phone_no" type="tel" inputmode="numeric" autocomplete="tel" maxlength="11" :placeholder="content.phone_placeholder" required></label><p v-if="form.errors.phone_no" class="igf-error" role="alert">{{ form.errors.phone_no }}</p>
            <label for="login-password">{{ content.password_label }}<span class="igf-password"><input id="login-password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required><button type="button" :aria-label="showPassword ? content.hide_password_label : content.show_password_label" @click="showPassword = !showPassword"><i :class="showPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'" aria-hidden="true" /></button></span></label><p v-if="form.errors.password" class="igf-error" role="alert">{{ form.errors.password }}</p>
            <label class="igf-remember"><input v-model="form.remember" type="checkbox"> <span>{{ content.remember_label }}</span></label>
            <button class="igf-submit" type="submit" :disabled="form.processing">{{ form.processing ? content.sending_label : content.submit_label }} <i class="fa-solid fa-arrow-right" aria-hidden="true" /></button>
          </form>
          <div class="igf-divider"><span>{{ content.social_divider }}</span></div>
          <div class="igf-social"><a :href="route('login.google')"><i class="fa-brands fa-google" aria-hidden="true" /> {{ content.google_login_label }}</a><a :href="route('login.facebook')"><i class="fa-brands fa-facebook-f" aria-hidden="true" /> {{ content.facebook_login_label }}</a></div>
          <p v-if="content.registration_enabled" class="igf-auth__register">{{ content.registration_prompt }} <a :href="route('register.form')">{{ content.registration_link_label }}</a></p>
          <p class="igf-auth__secure"><i class="fa-solid fa-lock" aria-hidden="true" /> {{ content.security_note }}</p>
        </div>
      </section>
    </div>
  </GuestLayout>
</template>
<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '../../layouts/GuestLayout.vue';
const showPassword = ref(false);
const page = usePage();
const content = computed(() => page.props.siteSettings?.member_area || {});
const branding = computed(() => page.props.siteSettings?.branding || {});
const form = useForm({ phone_no: '', password: '', remember: false });
function login() { form.post(route('login'), { preserveScroll: true, onFinish: () => form.reset('password') }); }
</script>
<style scoped>
.igf-auth{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#5f6065;--line:#dedbd7;display:grid;min-height:100vh;grid-template-columns:minmax(360px,.9fr) minmax(520px,1.1fr);background:#fff;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-auth__story{display:flex;padding:50px clamp(35px,6vw,80px);flex-direction:column;justify-content:space-between;background:#202223;color:#fff}.igf-auth__story img{width:145px;height:auto}.igf-auth__story p{margin:0 0 15px;color:#ffb070;font-size:11px;font-weight:800;letter-spacing:.11em;text-transform:uppercase}.igf-auth__story h1{max-width:570px;margin:0;color:#fff;font:650 clamp(44px,5vw,68px)/1.05 'Literata',Georgia,serif;letter-spacing:-.035em}.igf-auth__story h1::after,.igf-auth h2::after{display:none!important}.igf-auth__story span{display:block;max-width:540px;margin-top:23px;color:#d5d6d7;font-size:18px;line-height:1.65}.igf-auth__panel{display:grid;padding:45px 30px;place-items:center}.igf-auth__card{width:min(100%,500px)}.igf-auth__back{display:inline-flex;gap:8px;margin-bottom:55px;color:var(--muted);font-size:13px;font-weight:800;text-decoration:none}.igf-eyebrow{margin:0 0 11px;color:var(--brown);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-auth h2{margin:0;color:var(--ink);font:650 44px/1.1 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-auth__intro{margin:14px 0 28px;color:var(--muted);line-height:1.6}.igf-auth form{display:grid;gap:8px}.igf-auth form>label:not(.igf-remember){display:grid;gap:8px;margin-top:9px;color:var(--ink);font-size:12px;font-weight:800}.igf-auth input:not([type=checkbox]){width:100%;height:50px;padding:0 14px;border:1px solid #c8c4c0;border-radius:8px;color:var(--ink);font:16px 'Hanken Grotesk',Arial,sans-serif}.igf-auth input:focus{outline:3px solid rgba(255,117,0,.2);border-color:var(--orange)}.igf-password{position:relative;display:flex;align-items:center}.igf-password input{padding-right:48px!important}.igf-password button{position:absolute;right:4px;display:grid;width:42px;height:42px;place-items:center;border:0;background:transparent;color:var(--muted)}.igf-error{margin:0;color:#9b2c25;font-size:12px}.igf-remember{display:flex;align-items:flex-start;gap:9px;margin:14px 0;color:var(--muted);font-size:13px}.igf-remember input{margin-top:2px}.igf-submit{display:flex;min-height:52px;align-items:center;justify-content:center;gap:11px;border:0;border-radius:999px;background:var(--orange);color:#fff;font-weight:800}.igf-submit:disabled{opacity:.55}.igf-divider{position:relative;margin:28px 0;text-align:center}.igf-divider::before{position:absolute;top:50%;right:0;left:0;height:1px;background:var(--line);content:''}.igf-divider span{position:relative;padding:0 12px;background:#fff;color:var(--muted);font-size:11px;text-transform:uppercase}.igf-social{display:grid;grid-template-columns:1fr 1fr;gap:10px}.igf-social a{display:flex;min-height:48px;align-items:center;justify-content:center;gap:9px;border:1px solid var(--line);border-radius:999px;color:var(--ink);font-size:13px;font-weight:800;text-decoration:none}.igf-auth__secure{display:flex;align-items:flex-start;gap:9px;margin:25px 0 0;color:var(--muted);font-size:11px;line-height:1.5}.igf-auth__secure i{margin-top:2px;color:var(--brown)}
.igf-auth__register{margin:20px 0 0;color:var(--muted);font-size:13px;text-align:center}.igf-auth__register a{color:var(--brown);font-weight:850}
@media(max-width:900px){.igf-auth{grid-template-columns:1fr}.igf-auth__story{min-height:390px}.igf-auth__panel{padding:55px 22px}.igf-auth__back{margin-bottom:40px}}@media(max-width:450px){.igf-social{grid-template-columns:1fr}.igf-auth__story{padding:35px 24px}.igf-auth__story h1{font-size:42px}}
</style>
