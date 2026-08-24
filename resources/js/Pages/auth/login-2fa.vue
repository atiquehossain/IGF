<template>
  <GuestLayout>
    <div class="igf-auth">
      <section class="igf-auth__story"><a :href="route('frontend.home')"><img :src="branding.footerLogo" :alt="branding.footerLogoAlt"></a><div><p>{{ content.two_factor_story_eyebrow }}</p><h1>{{ content.two_factor_story_title }}</h1><span>{{ content.two_factor_story_body }}</span></div></section>
      <section class="igf-auth__panel" aria-labelledby="secure-login-title"><div class="igf-auth__card"><a class="igf-auth__back" :href="route('frontend.home')"><span aria-hidden="true">&larr;</span> {{ content.back_label }}</a><p class="igf-eyebrow">{{ content.two_factor_eyebrow }}</p><h2 id="secure-login-title">{{ content.two_factor_title }}</h2><p class="igf-auth__intro">{{ content.two_factor_introduction }}</p>
        <form @submit.prevent="login"><label for="secure-email">{{ content.two_factor_email_label }}<input id="secure-email" v-model="form.email" type="email" autocomplete="username" required :aria-invalid="form.errors.email ? 'true' : undefined" :aria-describedby="form.errors.email ? 'secure-email-error' : undefined"></label><p v-if="form.errors.email" id="secure-email-error" class="igf-error" role="alert">{{ form.errors.email }}</p><label for="secure-password">{{ content.two_factor_password_label }}<span class="igf-password"><input id="secure-password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required :aria-invalid="form.errors.password ? 'true' : undefined" :aria-describedby="form.errors.password ? 'secure-password-error' : undefined"><button type="button" :aria-label="showPassword ? content.hide_password_label : content.show_password_label" @click="showPassword = !showPassword"><i :class="showPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'" aria-hidden="true" /></button></span></label><p v-if="form.errors.password" id="secure-password-error" class="igf-error" role="alert">{{ form.errors.password }}</p><button class="igf-submit" type="submit" :disabled="form.processing">{{ form.processing ? content.two_factor_sending_label : content.two_factor_submit_label }} <i class="fa-solid fa-arrow-right" aria-hidden="true" /></button></form>
        <p class="igf-auth__secure"><i class="fa-solid fa-lock" aria-hidden="true" /> {{ content.two_factor_security_note }}</p>
      </div></section>
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
const branding = computed(() => {
  const values = page.props.siteSettings?.branding || {};
  const siteName = values.site_name || page.props.appName || 'Ignite Global Foundation';
  return {
    footerLogo: values.footer_logo || values.logo || '/image/logo-footer.png',
    footerLogoAlt: values.footer_logo_alt || values.logo_alt || siteName,
  };
});
const form = useForm({ email: '', password: '' });
function login() { form.post(route('login2fa.perform'), { preserveScroll: true, onFinish: () => form.reset('password') }); }
</script>
<style scoped>
.igf-auth{--orange:#ff7500;--brown:#9c4500;--ink:#191c1d;--muted:#5f6065;--line:#dedbd7;display:grid;min-height:100vh;grid-template-columns:minmax(360px,.9fr) minmax(520px,1.1fr);background:#fff;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-auth__story{display:flex;padding:50px clamp(35px,6vw,80px);flex-direction:column;justify-content:space-between;background:#202223;color:#fff}.igf-auth__story img{width:145px}.igf-auth__story p{margin:0 0 15px;color:#ffb070;font-size:11px;font-weight:800;letter-spacing:.11em;text-transform:uppercase}.igf-auth__story h1{max-width:570px;margin:0;color:#fff;font:650 clamp(44px,5vw,68px)/1.05 'Literata',Georgia,serif;letter-spacing:-.035em}.igf-auth__story h1::after,.igf-auth h2::after{display:none!important}.igf-auth__story span{display:block;max-width:540px;margin-top:23px;color:#d5d6d7;font-size:18px;line-height:1.65}.igf-auth__panel{display:grid;padding:45px 30px;place-items:center}.igf-auth__card{width:min(100%,500px)}.igf-auth__back{display:inline-flex;gap:8px;margin-bottom:55px;color:var(--muted);font-size:13px;font-weight:800;text-decoration:none}.igf-eyebrow{margin:0 0 11px;color:var(--brown);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-auth h2{margin:0;color:var(--ink);font:650 44px/1.1 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-auth__intro{margin:14px 0 28px;color:var(--muted);line-height:1.6}.igf-auth form{display:grid;gap:8px}.igf-auth form>label{display:grid;gap:8px;margin-top:9px;color:var(--ink);font-size:12px;font-weight:800}.igf-auth input{width:100%;height:50px;padding:0 14px;border:1px solid #c8c4c0;border-radius:8px;color:var(--ink);font:16px 'Hanken Grotesk',Arial,sans-serif}.igf-auth input:focus{outline:3px solid rgba(255,117,0,.2);border-color:var(--orange)}.igf-password{position:relative;display:flex;align-items:center}.igf-password input{padding-right:48px}.igf-password button{position:absolute;right:4px;display:grid;width:42px;height:42px;place-items:center;border:0;background:transparent;color:var(--muted)}.igf-error{margin:0;color:#9b2c25;font-size:12px}.igf-submit{display:flex;min-height:52px;align-items:center;justify-content:center;gap:11px;margin-top:19px;border:0;border-radius:999px;background:var(--orange);color:#fff;font-weight:800}.igf-submit:disabled{opacity:.55}.igf-auth__secure{display:flex;align-items:flex-start;gap:9px;margin:25px 0 0;color:var(--muted);font-size:11px;line-height:1.5}.igf-auth__secure i{margin-top:2px;color:var(--brown)}@media(max-width:850px){.igf-auth{grid-template-columns:1fr}.igf-auth__story{min-height:390px}.igf-auth__panel{padding:55px 22px}.igf-auth__back{margin-bottom:40px}}@media(max-width:450px){.igf-auth__story{padding:35px 24px}.igf-auth__story h1{font-size:42px}}
@media(max-width:900px){.igf-auth{grid-template-columns:1fr}.igf-auth__story{min-height:390px}.igf-auth__panel{padding:55px 22px}.igf-auth__back{margin-bottom:40px}}
</style>
