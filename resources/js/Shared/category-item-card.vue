<template>
  <a :href="link" class="igf-content-card" :class="{ 'igf-content-card--award': variant === 'award' }"
    :aria-label="linkLabel && title ? `${linkLabel}: ${title}` : title">
    <div class="igf-content-card__media">
      <img v-if="thumbnail" :src="thumbnail" :alt="imageAlt" loading="lazy" decoding="async">
      <span v-else class="igf-content-card__placeholder" aria-hidden="true"><i class="fa-solid fa-hand-holding-heart" /></span>
      <span v-if="variant === 'award' && ordinal" class="igf-content-card__number" aria-hidden="true">{{ String(ordinal).padStart(2, '0') }}</span>
    </div>
    <div class="igf-content-card__body">
      <p v-if="eyebrow" class="igf-content-card__eyebrow">{{ eyebrow }}</p>
      <h2>{{ title }}</h2>
      <p v-if="subtitle">{{ subtitle }}</p>
      <div v-if="$slots.meta" class="igf-content-card__meta"><slot name="meta" /></div>
      <span v-if="showLink && linkLabel" class="igf-content-card__link">
        {{ linkLabel }}
        <span class="igf-content-card__arrow" aria-hidden="true">&rarr;</span>
      </span>
    </div>
  </a>
</template>

<script setup>
defineProps({
  title:{type:String,default:''},
  subtitle:{type:String,default:''},
  thumbnail:{type:String,default:''},
  imageAlt:{type:String,default:''},
  link:{type:String,default:'#'},
  eyebrow:{type:String,default:'Community impact'},
  linkLabel:{type:String,default:'Read the story'},
  showLink:{type:Boolean,default:true},
  variant:{type:String,default:'default'},
  ordinal:{type:Number,default:0},
});
</script>

<style scoped lang="scss">
.igf-content-card {
  display:flex;
  width:100%;
  height:100%;
  min-height:0;
  flex-direction:column;
  overflow:hidden;
  border:1px solid var(--igf-card-border,#e5e0dc);
  border-radius:var(--igf-card-radius,18px);
  background:#fff;
  box-shadow:var(--igf-card-shadow,0 7px 24px rgba(25,28,29,.06));
  color:#191c1d;
  font-family:'Hanken Grotesk',Arial,sans-serif;
  text-decoration:none;
  transition:border-color .2s ease,box-shadow .2s ease,transform .2s ease;
}
.igf-content-card:focus-visible { outline:3px solid #773400; outline-offset:4px; }
.igf-content-card__media {
  position:relative;
  display:grid;
  width:100%;
  min-height:min(180px,var(--igf-card-media-height,230px));
  aspect-ratio:var(--igf-card-media-aspect,var(--igf-image-aspect,16 / 10));
  overflow:hidden;
  place-items:center;
  background:linear-gradient(145deg,#f8f1ea,#ede5dd);
  color:#9c4500;
  font-size:42px;
}
.igf-content-card__media img { width:100%; height:100%; object-fit:cover; transition:transform .4s ease; }
.igf-content-card__placeholder {
  display:grid;
  width:74px;
  aspect-ratio:1;
  border:1px solid rgba(156,69,0,.16);
  border-radius:50%;
  background:rgba(255,255,255,.64);
  place-items:center;
}
.igf-content-card__number { position:absolute; right:16px; bottom:16px; display:grid; min-width:42px; height:42px; padding:0 10px; border:1px solid rgba(255,255,255,.55); border-radius:999px; background:rgba(25,28,29,.82); color:#fff; font-size:12px; font-weight:800; letter-spacing:.08em; place-items:center; backdrop-filter:blur(8px); }
.igf-content-card__body { display:flex; flex:1; flex-direction:column; padding:clamp(22px,2.4vw,28px); }
.igf-content-card__eyebrow { margin:0 0 10px; color:#8b3e08; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
.igf-content-card h2 { margin:0; color:#191c1d; font:650 var(--igf-heading-3,23px)/1.22 'Literata',Georgia,serif; letter-spacing:-.02em; }
.igf-content-card h2::after { display:none!important; }
.igf-content-card__body>p:not(.igf-content-card__eyebrow) { display:-webkit-box; margin:13px 0 22px; overflow:hidden; color:#5e5d66; font-size:var(--igf-body-size,17px); line-height:1.58; -webkit-box-orient:vertical; -webkit-line-clamp:3; }
.igf-content-card__meta { margin:0 0 22px; }
.igf-content-card__link { display:inline-flex; align-items:center; gap:10px; align-self:flex-start; margin-top:auto; color:#8b3e08; font-size:14px; font-weight:800; }
.igf-content-card__arrow { display:grid; width:32px; aspect-ratio:1; border-radius:50%; background:#fff1e6; place-items:center; transition:background-color .2s ease,color .2s ease,transform .2s ease; }
.igf-content-card--award { border-color:#e1d8cf; border-radius:22px; box-shadow:0 16px 38px rgba(44,31,20,.08); }
.igf-content-card--award .igf-content-card__media { background:linear-gradient(145deg,#f7f1e9,#ece5dd); }
.igf-content-card--award .igf-content-card__body { padding:27px 28px 29px; }
.igf-content-card--award .igf-content-card__link { color:#a54800; letter-spacing:.01em; }
@media(hover:hover){
  .igf-content-card:hover { border-color:#ffb68a; box-shadow:0 15px 34px rgba(25,28,29,.11); color:#191c1d; transform:translateY(-4px); }
  .igf-content-card:hover img { transform:scale(1.04); }
  .igf-content-card:hover .igf-content-card__arrow { background:#9c4500; color:#fff; transform:translateX(3px); }
}
@media(max-width:600px){.igf-content-card__body{padding:22px}.igf-content-card__media{min-height:0}}
@media(prefers-reduced-motion:reduce){.igf-content-card,.igf-content-card__media img,.igf-content-card__arrow{transition:none}.igf-content-card:hover{transform:none}.igf-content-card:hover img,.igf-content-card:hover .igf-content-card__arrow{transform:none}}
</style>
