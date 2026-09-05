<template>
  <GuestLayout>
    <div class="igf-verify">
      <section class="igf-verify__card" aria-labelledby="verify-title">
        <a class="igf-logo" :href="route('frontend.home')"><img :src="branding.logo" :alt="branding.logoAlt"></a>
        <div class="igf-verify__icon"><i class="fa-solid fa-shield-halved" aria-hidden="true" /></div>
        <p class="igf-eyebrow">{{ content.verification_eyebrow }}</p><h1 id="verify-title">{{ enrollmentRequired ? content.verification_setup_title : content.verification_code_title }}</h1>
        <p>{{ enrollmentRequired ? content.verification_setup_body : content.verification_code_body }}</p>
        <figure v-if="qrImage" class="igf-qr"><img :src="qrImage" :alt="content.verification_qr_alt"><figcaption>{{ content.verification_qr_warning }}</figcaption></figure>
        <form @submit.prevent="verify"><label for="verification-code">{{ content.verification_code_label }}<input id="verification-code" v-model="form.code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" :placeholder="content.verification_code_placeholder" required :aria-invalid="form.errors.code ? 'true' : undefined" :aria-describedby="form.errors.code ? 'verification-code-error' : undefined"></label><p v-if="form.errors.code" id="verification-code-error" class="igf-error" role="alert">{{ form.errors.code }}</p><button type="submit" :disabled="form.processing || form.code.length !== 6">{{ form.processing ? content.verification_sending_label : content.verification_submit_label }}</button></form>
        <a class="igf-start-over" :href="route('login2fa')">{{ content.verification_restart_label }}</a>
      </section>
    </div>
  </GuestLayout>
</template>
<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '../../layouts/GuestLayout.vue';
const page = usePage();
const content = computed(() => page.props.siteSettings?.member_area || {});
const branding = computed(() => {
  const values = page.props.siteSettings?.branding || {};
  const siteName = values.site_name || page.props.appName || 'Ignite Global Foundation';
  return { logo: values.logo || '/image/logo.png', logoAlt: values.logo_alt || siteName };
});
const qrImage = computed(() => page.props.qr_image || null);
const enrollmentRequired = computed(() => Boolean(page.props.enrollment_required));
const form = useForm({ access_token: page.props.access_token, code: '' });
function verify() { form.code = form.code.replace(/\D/g, '').slice(0, 6); form.post(route('login2fa.verify.perform'), { preserveScroll: true, onFinish: () => form.reset('code') }); }
</script>
<style scoped>
.igf-verify{--surface:var(--igf-surface,#f8f9fa);--orange:var(--igf-primary,#ff7500);--brown:var(--igf-accent,#9c4500);--ink:var(--igf-ink,#191c1d);--muted:color-mix(in srgb,var(--ink) 68%,var(--surface));--line:color-mix(in srgb,var(--ink) 14%,var(--surface));display:grid;min-height:100vh;padding:50px 20px;place-items:center;background:var(--surface);color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-verify__card{width:min(100%,570px);padding:clamp(28px,6vw,55px);border:1px solid var(--line);border-top:5px solid var(--orange);border-radius:20px;background:#fff;box-shadow:0 14px 40px rgba(25,28,29,.09);text-align:center}.igf-logo img{width:125px;height:auto;margin-bottom:35px}.igf-verify__icon{display:grid;width:66px;height:66px;margin:0 auto 21px;place-items:center;border-radius:50%;background:#fff3e9;color:var(--brown);font-size:27px}.igf-eyebrow{margin:0 0 10px;color:var(--brown);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-verify h1{margin:0;color:var(--ink);font:650 clamp(32px,5vw,43px)/1.12 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-verify h1::after{display:none!important}.igf-verify__card>p:not(.igf-eyebrow){max-width:470px;margin:17px auto 25px;color:var(--muted);line-height:1.65}.igf-qr{margin:0 auto 25px;padding:18px;border:1px solid var(--line);border-radius:14px;background:var(--surface)}.igf-qr img{display:block;width:220px;max-width:100%;margin:auto}.igf-qr figcaption{margin-top:12px;color:var(--muted);font-size:11px;line-height:1.5}.igf-verify form{display:grid;gap:9px;text-align:left}.igf-verify label{display:grid;gap:8px;color:var(--ink);font-size:12px;font-weight:800}.igf-verify input{width:100%;height:58px;border:1px solid #c8c4c0;border-radius:9px;color:var(--ink);font:650 25px 'Hanken Grotesk',Arial,sans-serif;letter-spacing:.35em;text-align:center}.igf-verify input:focus{outline:3px solid color-mix(in srgb,var(--orange) 20%,transparent);border-color:var(--orange)}.igf-error{margin:0;color:#9b2c25;font-size:12px}.igf-verify form button{min-height:52px;margin-top:10px;border:0;border-radius:999px;background:var(--orange);color:var(--igf-on-primary,#000);font-weight:800}.igf-verify form button:disabled{opacity:.55}.igf-start-over{display:inline-block;margin-top:22px;color:var(--brown);font-size:12px;font-weight:800}
</style>
