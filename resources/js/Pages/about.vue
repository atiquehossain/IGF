<!-- Rich text is sanitized by ContentSanitizer before it reaches this view. -->
<!-- eslint-disable vue/no-v-html -->
<template>
  <Layout>
    <header v-if="!hasHeroBlock" class="igf-about-hero" aria-labelledby="about-page-title">
      <div class="igf-about-hero__glow igf-about-hero__glow--one" aria-hidden="true" />
      <div class="igf-about-hero__glow igf-about-hero__glow--two" aria-hidden="true" />
      <div class="igf-about-hero__mark" aria-hidden="true">IGF</div>
      <div class="igf-about-hero__inner">
        <div class="igf-about-hero__copy">
          <p v-if="copy.aboutEyebrow" class="igf-about-hero__eyebrow">
            <span aria-hidden="true" />{{ copy.aboutEyebrow }}
          </p>
          <h1 id="about-page-title">{{ about?.name || copy.aboutTitle }}</h1>
          <p v-if="about?.sub_title" class="igf-about-hero__lead">{{ about.sub_title }}</p>
        </div>
        <aside v-if="about?.description" class="igf-about-hero__statement" aria-label="About Ignite Global Foundation">
          <span class="igf-about-hero__statement-icon" aria-hidden="true"><i class="fa-solid fa-hand-holding-heart" /></span>
          <div v-html="about.description" />
        </aside>
      </div>
    </header>
    <div class="igf-about">
      <PageBlocks v-if="about?.visible_blocks?.length" class="igf-page-blocks--about" :blocks="about.visible_blocks" />
      <section v-else class="igf-about__legacy">
        <div class="igf-shell">
          <p v-if="copy.aboutEyebrow" class="igf-eyebrow">{{ copy.aboutEyebrow }}</p>
          <h2>{{ about?.name || copy.aboutTitle }}</h2>
          <article v-if="about?.description" v-html="about.description" />
        </div>
      </section>
      <section v-if="!about?.visible_blocks?.length && foundersLetter?.description" class="igf-founder" aria-labelledby="founder-heading">
        <div class="igf-shell igf-founder__inner">
          <header><p v-if="copy.founderEyebrow" class="igf-eyebrow">{{ copy.founderEyebrow }}</p><h2 id="founder-heading">{{ foundersLetter.name }}</h2></header>
          <article v-html="foundersLetter.description" />
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Layout from '../layouts/App.vue';
import PageBlocks from '../Shared/PageBlocks.vue';
const page = usePage();
const about = computed(() => page.props.data?.about_us || null);
const foundersLetter = computed(() => page.props.data?.founders_letter || null);
const hasHeroBlock = computed(() => (about.value?.visible_blocks || []).some(block => block?.type === 'hero'));
const copy = computed(() => ({
  aboutEyebrow: page.props.siteSettings?.shared_blocks?.about_eyebrow || '',
  aboutTitle: page.props.siteSettings?.shared_blocks?.about_fallback_title || '',
  founderEyebrow: page.props.siteSettings?.shared_blocks?.founder_eyebrow || '',
}));
</script>

<style scoped>
.igf-about{--brown:#9c4500;--ink:#191c1d;--muted:#5f6065;--surface:#f8f9fa;color:var(--ink);font-family:'Hanken Grotesk',Arial,sans-serif}.igf-shell{width:min(100% - 40px,1040px);margin:0 auto}.igf-eyebrow{margin:0 0 14px;color:var(--brown);font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.igf-about__legacy{padding:clamp(75px,9vw,120px) 0;background:#fff}.igf-about h1,.igf-about h2{margin:0;color:var(--ink);font:650 clamp(40px,5vw,58px)/1.1 'Literata',Georgia,serif;letter-spacing:-.03em}.igf-about :is(h1,h2)::after{display:none!important}.igf-about__legacy article{max-width:880px;margin-top:30px;color:var(--muted);font-size:17px;line-height:1.8}.igf-about__legacy :deep(article img),.igf-founder :deep(article img){max-width:100%;height:auto;border-radius:16px}.igf-about__legacy :deep(article h2),.igf-about__legacy :deep(article h3),.igf-founder :deep(article h2),.igf-founder :deep(article h3){margin:1.4em 0 .5em;color:var(--ink);font-family:'Literata',Georgia,serif}.igf-about__legacy :deep(article a),.igf-founder :deep(article a){color:var(--brown);font-weight:800}.igf-founder{padding:clamp(75px,9vw,120px) 0;background:var(--surface)}.igf-founder__inner{display:grid;grid-template-columns:minmax(260px,.7fr) minmax(0,1.3fr);gap:clamp(40px,7vw,90px)}.igf-founder h2{font-size:clamp(34px,4vw,48px)}.igf-founder article{color:var(--muted);font-size:17px;line-height:1.8}
.igf-about-hero{position:relative;isolation:isolate;overflow:hidden;padding:clamp(96px,11vw,150px) clamp(20px,5vw,48px);background:#1d1e1e;color:#fff;font-family:'Hanken Grotesk',Arial,sans-serif}
.igf-about-hero::after{position:absolute;z-index:-1;right:clamp(20px,7vw,100px);bottom:-210px;width:440px;height:440px;border:1px solid rgba(255,117,0,.26);border-radius:50%;content:''}
.igf-about-hero__inner{position:relative;z-index:2;display:grid;width:min(100%,1240px);grid-template-columns:minmax(0,1.18fr) minmax(320px,.82fr);align-items:end;gap:clamp(52px,8vw,120px);margin:0 auto}
.igf-about-hero__eyebrow{display:flex;align-items:center;gap:12px;margin:0 0 22px;color:#ffad72;font-size:12px;font-weight:850;letter-spacing:.12em;text-transform:uppercase}
.igf-about-hero__eyebrow span{display:block;width:42px;height:2px;background:#ff7500}
.igf-about-hero h1{max-width:790px;margin:0;color:#fff;font:650 clamp(50px,6.8vw,88px)/.98 'Literata',Georgia,serif;letter-spacing:-.055em}
.igf-about-hero h1::after{display:none!important}
.igf-about-hero__lead{max-width:720px;margin:28px 0 0;color:#d5d5d3;font-size:clamp(19px,2vw,24px);line-height:1.55}
.igf-about-hero__statement{position:relative;padding:34px 34px 34px 38px;border:1px solid rgba(255,255,255,.14);border-left:4px solid #ff7500;border-radius:0 24px 24px 0;background:rgba(255,255,255,.06);box-shadow:0 22px 60px rgba(0,0,0,.18);backdrop-filter:blur(8px)}
.igf-about-hero__statement-icon{display:grid;width:50px;height:50px;place-items:center;margin-bottom:24px;border-radius:15px;background:#ff7500;color:#fff;font-size:21px}
.igf-about-hero__statement :deep(p){margin:0;color:#f1ede9;font-size:16px;line-height:1.75}
.igf-about-hero__glow{position:absolute;z-index:-2;border-radius:50%;filter:blur(2px);pointer-events:none}
.igf-about-hero__glow--one{top:-200px;right:20%;width:520px;height:520px;background:radial-gradient(circle,rgba(255,117,0,.18),transparent 67%)}
.igf-about-hero__glow--two{bottom:-260px;left:-120px;width:620px;height:620px;background:radial-gradient(circle,rgba(156,69,0,.22),transparent 68%)}
.igf-about-hero__mark{position:absolute;z-index:-1;top:50%;right:-.04em;color:rgba(255,255,255,.025);font:800 clamp(190px,31vw,480px)/.75 'Hanken Grotesk',Arial,sans-serif;letter-spacing:-.12em;transform:translateY(-50%);user-select:none}
:deep(.igf-page-blocks--about){--igf-content-width:1180px;--igf-card-radius:22px;--igf-card-border:#e4d8cd;--igf-card-shadow:0 18px 48px rgba(48,35,25,.08);background:#fff}
:deep(.igf-page-blocks--about .igf-page-block__eyebrow){display:flex;align-items:center;gap:10px;color:#a44906;letter-spacing:.13em}
:deep(.igf-page-blocks--about .igf-page-block__eyebrow::before){display:block;width:30px;height:2px;background:#ff7500;content:''}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars){padding-top:clamp(82px,9vw,120px);padding-bottom:clamp(88px,10vw,132px);background:#f5efe8}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-section-heading){display:grid;grid-template-columns:minmax(250px,.72fr) minmax(0,1.28fr);align-items:end;gap:40px;margin-bottom:48px}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-section-heading h2){margin:0;font-size:clamp(40px,4.6vw,60px)}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-section-lead){justify-self:end;margin:0;color:#685f57;font-size:18px}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card-grid){grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card){position:relative;min-height:405px;overflow:hidden;border:1px solid rgba(71,51,38,.13);background:#fff;box-shadow:0 20px 52px rgba(69,49,34,.09)}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars a.igf-card:focus-visible){outline:3px solid #ff7500;outline-offset:5px}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card::after){position:absolute;right:-75px;bottom:-105px;width:230px;height:230px;border:34px solid rgba(255,117,0,.07);border-radius:50%;content:''}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card:nth-child(2)){background:#242526;color:#fff}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card__content){position:relative;z-index:1;padding:clamp(34px,4vw,52px)}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card__content>i){display:grid;width:64px;height:64px;place-items:center;margin-bottom:auto;border-radius:20px;background:#fff0e4;color:#d85d00;font-size:27px}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card:nth-child(2) .igf-card__content>i){background:rgba(255,117,0,.16);color:#ff9a4b}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card__content small){margin-top:42px;color:#a44906;font-size:11px;letter-spacing:.14em}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card:nth-child(2) .igf-card__content small){color:#ffad72}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card h3){max-width:470px;margin-bottom:16px;font-size:clamp(28px,3vw,40px);line-height:1.12}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card:nth-child(2) h3){color:#fff}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card p){max-width:510px;margin:0 0 28px;color:#645d57;font-size:16px;line-height:1.7}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card:nth-child(2) p){color:#d4d1cd}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card__link){display:inline-flex;min-height:44px;align-items:center;justify-content:center;margin-top:auto;padding:0 20px;border-radius:999px;background:#a44906;color:#fff;font-size:12px;font-weight:850;letter-spacing:.04em;text-transform:uppercase;box-shadow:0 9px 22px rgba(164,73,6,.18);transition:background-color .2s ease,box-shadow .2s ease,transform .2s ease}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card:nth-child(2) .igf-card__link){background:#ff7500;color:#1f2021;box-shadow:0 9px 24px rgba(255,117,0,.2)}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars a.igf-card:hover .igf-card__link){background:#843a03;box-shadow:0 12px 28px rgba(164,73,6,.25);transform:translateY(-2px)}
:deep(.igf-page-blocks--about .igf-page-block--about-pillars a.igf-card:nth-child(2):hover .igf-card__link){background:#ff9140}
:deep(.igf-page-blocks--about .igf-page-block--timeline){position:relative;overflow:hidden;padding-top:clamp(92px,10vw,136px);padding-bottom:clamp(104px,11vw,152px);background:radial-gradient(circle at 92% 12%,rgba(255,117,0,.1),transparent 26%),linear-gradient(180deg,#fff 0%,#fffaf5 100%)}
:deep(.igf-page-blocks--about .igf-timeline){display:block;max-width:1180px}
:deep(.igf-page-blocks--about .igf-timeline>h2){max-width:860px;margin-bottom:22px;font-size:clamp(42px,4.8vw,62px)}
:deep(.igf-page-blocks--about .igf-timeline>.igf-section-lead){max-width:720px;margin:0;color:#6a625c;font-size:18px}
:deep(.igf-page-blocks--about .igf-timeline>ol){position:relative;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:clamp(20px,3vw,38px);margin:84px 0 0;padding:58px 0 38px}
:deep(.igf-page-blocks--about .igf-timeline>ol::before){position:absolute;top:27px;right:26px;left:26px;height:6px;border-radius:999px;background:linear-gradient(90deg,#ff7500 0%,#e95d00 55%,#9f4200 100%);box-shadow:0 0 0 7px rgba(255,117,0,.08),0 8px 22px rgba(164,73,6,.14);content:''}
:deep(.igf-page-blocks--about .igf-timeline>ol::after){position:absolute;top:18px;right:8px;width:20px;height:20px;border-top:6px solid #9f4200;border-right:6px solid #9f4200;content:'';transform:rotate(45deg)}
:deep(.igf-page-blocks--about .igf-timeline li){position:relative;display:block;min-width:0;padding:0}
:deep(.igf-page-blocks--about .igf-timeline li>span){position:absolute;z-index:2;top:-58px;left:50%;width:58px;height:58px;border:4px solid #fff;background:#ff7500;box-shadow:0 0 0 2px #ff7500,0 10px 24px rgba(164,73,6,.24);color:#fff;font-size:12px;letter-spacing:.04em;transform:translateX(-50%)}
:deep(.igf-page-blocks--about .igf-timeline li>div){position:relative;min-height:250px;padding:34px 32px;border:1px solid #eadfd5;border-radius:24px;background:#fff;box-shadow:0 22px 56px rgba(65,45,31,.09)}
:deep(.igf-page-blocks--about .igf-timeline li>div::before){position:absolute;top:-10px;left:50%;width:20px;height:20px;border-top:1px solid #eadfd5;border-left:1px solid #eadfd5;background:#fff;content:'';transform:translateX(-50%) rotate(45deg)}
:deep(.igf-page-blocks--about .igf-timeline li:nth-child(even)>div){transform:translateY(34px)}
:deep(.igf-page-blocks--about .igf-timeline li:first-child>span){background:#202122;box-shadow:0 0 0 2px #202122,0 10px 24px rgba(32,33,34,.22)}
:deep(.igf-page-blocks--about .igf-timeline li:last-child>span){background:#a44906;box-shadow:0 0 0 2px #a44906,0 10px 28px rgba(164,73,6,.3)}
:deep(.igf-page-blocks--about .igf-timeline li:last-child>div){border-color:#f2b888;background:#fff8f1}
:deep(.igf-page-blocks--about .igf-timeline li:last-child>div::before){border-color:#f2b888;background:#fff8f1}
:deep(.igf-page-blocks--about .igf-timeline h3){position:relative;margin-bottom:14px;font-size:clamp(24px,2.2vw,31px)}
:deep(.igf-page-blocks--about .igf-timeline .igf-page-block__copy){color:#625b55;line-height:1.7}
:deep(.igf-page-blocks--about .igf-page-block--media_text){padding-top:clamp(86px,9vw,124px);padding-bottom:clamp(86px,9vw,124px);background:#f4f1ed}
:deep(.igf-page-blocks--about .igf-page-block--media_text .igf-media-text){grid-template-columns:minmax(310px,.82fr) minmax(0,1.18fr);gap:clamp(50px,9vw,116px)}
:deep(.igf-page-blocks--about .igf-media-text__figure){position:relative;padding:0 0 24px 24px}
:deep(.igf-page-blocks--about .igf-media-text__figure::before){position:absolute;bottom:0;left:0;width:72%;height:72%;border-radius:28px;background:#ff7500;content:''}
:deep(.igf-page-blocks--about .igf-media-text__media){position:relative;z-index:1;border-radius:28px 28px 92px 28px;background:#e7e2dc;box-shadow:0 26px 62px rgba(49,37,29,.17)}
:deep(.igf-page-blocks--about .igf-media-text__content h2){max-width:680px;margin-bottom:25px;font-size:clamp(42px,5vw,66px);line-height:1.05}
:deep(.igf-page-blocks--about .igf-media-text__content .igf-page-block__copy){max-width:680px;color:#5e5853;font-size:17px;line-height:1.8}
:deep(.igf-page-blocks--about .igf-media-text__content .igf-text-link){margin-top:18px;padding-bottom:7px;border-bottom:2px solid #ff7500;color:#8f3f03;font-size:14px}
:deep(.igf-page-blocks--about .igf-page-block--stats){z-index:auto;margin-top:0;padding-top:clamp(84px,8vw,112px);padding-bottom:clamp(88px,9vw,120px);border-top:5px solid #ff7500;background:#202122;color:#fff}
:deep(.igf-page-blocks--about .igf-page-block--stats .igf-page-block__eyebrow){color:#ffad72}
:deep(.igf-page-blocks--about .igf-page-block--stats h2){max-width:760px;margin-bottom:46px;color:#fff;font-size:clamp(42px,4.8vw,62px)}
:deep(.igf-page-blocks--about .igf-page-block--stats .igf-stats){gap:14px}
:deep(.igf-page-blocks--about .igf-page-block--stats .igf-stat){min-height:235px;padding:30px;border:1px solid rgba(255,255,255,.12);border-top:1px solid rgba(255,255,255,.12);border-radius:20px;background:#292a2b;box-shadow:none}
:deep(.igf-page-blocks--about .igf-page-block--stats .igf-stat:nth-child(even)){border-top-color:rgba(255,255,255,.12)}
:deep(.igf-page-blocks--about .igf-page-block--stats .igf-stat>i){display:grid;width:54px;height:54px;place-items:center;margin-bottom:42px;border-radius:16px;background:rgba(255,117,0,.14);color:#ff9140;font-size:22px}
:deep(.igf-page-blocks--about .igf-page-block--stats .igf-stat:nth-child(even)>i){color:#ff9140}
:deep(.igf-page-blocks--about .igf-page-block--stats .igf-stat strong){margin-bottom:10px;color:#fff;font-size:clamp(38px,4vw,52px)}
:deep(.igf-page-blocks--about .igf-page-block--stats .igf-stat span){color:#bfc0c0;font-size:11px;letter-spacing:.1em}
:deep(.igf-page-blocks--about .igf-page-block--team){padding-top:clamp(92px,10vw,136px);padding-bottom:clamp(96px,10vw,142px);background:#f5efe8}
:deep(.igf-page-blocks--about .igf-page-block--team .igf-section-heading){justify-content:center;margin-bottom:54px;text-align:center}
:deep(.igf-page-blocks--about .igf-page-block--team .igf-section-heading>div){max-width:760px}
:deep(.igf-page-blocks--about .igf-page-block--team .igf-page-block__eyebrow){justify-content:center}
:deep(.igf-page-blocks--about .igf-page-block--team h2){margin-right:auto;margin-left:auto;font-size:clamp(42px,5vw,64px)}
:deep(.igf-page-blocks--about .igf-page-block--team .igf-section-lead){margin:0 auto;color:#6d635b}
:deep(.igf-page-blocks--about .igf-team-grid){gap:24px}
:deep(.igf-page-blocks--about .igf-team-card){border-radius:20px;box-shadow:0 18px 44px rgba(57,42,31,.11)}
:deep(.igf-page-blocks--about .igf-team-card__face){border-radius:20px;background:#fff}
:deep(.igf-page-blocks--about .igf-team-card__toggle){border-radius:20px}
:deep(.igf-page-blocks--about .igf-page-block--partners){padding-top:clamp(84px,9vw,122px);padding-bottom:clamp(88px,9vw,126px);background:#fff}
:deep(.igf-page-blocks--about .igf-partners>.igf-page-block__eyebrow){justify-content:center;color:#a44906}
:deep(.igf-page-blocks--about .igf-partners h2){font-family:'Literata',Georgia,serif;font-size:clamp(42px,5vw,62px);font-weight:650}
:deep(.igf-page-blocks--about .igf-partner-underline){height:12px;margin:12px auto 0;border-top-width:3px}
:deep(.igf-page-blocks--about .igf-partner-list){gap:14px;margin-top:38px}
:deep(.igf-page-blocks--about .igf-partner-card){min-height:112px;border-color:#e8e2dc;border-radius:14px;background:#faf9f7;box-shadow:0 5px 16px rgba(38,34,31,.06)}
:deep(.igf-page-blocks--about .igf-page-block--rich_text){padding-top:clamp(70px,8vw,104px);padding-bottom:clamp(70px,8vw,104px);background:#1f2021;color:#fff}
:deep(.igf-page-blocks--about .igf-page-block--rich_text .igf-rich-text){padding:clamp(36px,6vw,70px);border:1px solid rgba(255,255,255,.13);border-radius:28px;background:linear-gradient(135deg,rgba(255,117,0,.11),rgba(255,255,255,.035));box-shadow:0 24px 70px rgba(0,0,0,.18)}
:deep(.igf-page-blocks--about .igf-page-block--rich_text .igf-page-block__eyebrow){justify-content:center;color:#ffad72}
:deep(.igf-page-blocks--about .igf-page-block--rich_text h2){margin-right:auto;margin-left:auto;color:#fff;font-size:clamp(40px,4.6vw,58px)}
:deep(.igf-page-blocks--about .igf-page-block--rich_text .igf-page-block__copy){color:#d2d2d0;font-size:17px}
:deep(.igf-page-blocks--about .igf-page-block--rich_text .igf-page-block__copy strong){color:#ffad72}
@media(max-width:960px){.igf-about-hero__inner{grid-template-columns:1fr;align-items:start;gap:42px}.igf-about-hero__statement{max-width:680px}:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-section-heading){grid-template-columns:1fr;gap:18px}:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-section-lead){justify-self:start}:deep(.igf-page-blocks--about .igf-timeline>ol){grid-template-columns:1fr;gap:26px;margin-top:58px;padding:0 0 34px 76px}:deep(.igf-page-blocks--about .igf-timeline>ol::before){top:28px;right:auto;bottom:24px;left:27px;width:6px;height:auto;background:linear-gradient(180deg,#ff7500 0%,#e95d00 55%,#9f4200 100%)}:deep(.igf-page-blocks--about .igf-timeline>ol::after){top:auto;right:auto;bottom:2px;left:19px;width:0;height:0;border:0;border-top:14px solid #9f4200;border-right:10px solid transparent;border-left:10px solid transparent;transform:none}:deep(.igf-page-blocks--about .igf-timeline li>span){top:23px;left:-76px;transform:none}:deep(.igf-page-blocks--about .igf-timeline li>div){min-height:0;padding:30px 32px}:deep(.igf-page-blocks--about .igf-timeline li>div::before){top:39px;left:-10px;transform:rotate(-45deg)}:deep(.igf-page-blocks--about .igf-timeline li:nth-child(even)>div){transform:none}:deep(.igf-page-blocks--about .igf-page-block--media_text .igf-media-text){grid-template-columns:minmax(260px,.8fr) minmax(0,1.2fr);gap:46px}:deep(.igf-page-blocks--about .igf-page-block--stats .igf-stats){grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767px){.igf-about-hero{padding:78px 20px 86px}.igf-about-hero h1{font-size:clamp(43px,13vw,62px)}.igf-about-hero__lead{margin-top:22px;font-size:18px}.igf-about-hero__statement{padding:28px 24px 28px 27px;border-radius:0 18px 18px 0}.igf-about-hero__mark{font-size:210px}:deep(.igf-page-blocks--about .igf-page-block--about-pillars){padding-top:72px;padding-bottom:78px}:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card-grid){grid-template-columns:1fr}:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card){min-height:330px}:deep(.igf-page-blocks--about .igf-page-block--timeline){padding-top:78px;padding-bottom:92px}:deep(.igf-page-blocks--about .igf-timeline>ol){gap:22px;padding-left:64px}:deep(.igf-page-blocks--about .igf-timeline>ol::before){left:23px}:deep(.igf-page-blocks--about .igf-timeline>ol::after){left:15px}:deep(.igf-page-blocks--about .igf-timeline li>span){top:22px;left:-64px;width:50px;height:50px;border-width:3px;font-size:10px}:deep(.igf-page-blocks--about .igf-timeline li>div){padding:28px 24px;border-radius:20px}:deep(.igf-page-blocks--about .igf-timeline li>div::before){top:36px}:deep(.igf-page-blocks--about .igf-page-block--media_text .igf-media-text){grid-template-columns:1fr;gap:48px}:deep(.igf-page-blocks--about .igf-media-text__figure){width:min(100%,480px);padding:0 0 18px 18px}:deep(.igf-page-blocks--about .igf-page-block--stats){margin-top:0;padding-top:72px;padding-bottom:78px}:deep(.igf-page-blocks--about .igf-page-block--stats .igf-stat){min-height:210px}:deep(.igf-page-blocks--about .igf-page-block--team){padding-top:78px;padding-bottom:82px}:deep(.igf-page-blocks--about .igf-page-block--partners){padding-top:72px;padding-bottom:78px}}
@media(max-width:520px){:deep(.igf-page-blocks--about .igf-page-block--stats .igf-stats){grid-template-columns:1fr}:deep(.igf-page-blocks--about .igf-page-block--stats .igf-stat){min-height:190px}:deep(.igf-page-blocks--about .igf-page-block--about-pillars .igf-card__content){padding:30px 26px}}
@media(prefers-reduced-motion:reduce){.igf-about-hero *{animation:none!important;transition:none!important}.igf-about-hero__glow{filter:none}}
@media(max-width:720px){.igf-shell{width:min(100% - 32px,1040px)}.igf-founder__inner{grid-template-columns:1fr;gap:24px}}
</style>
