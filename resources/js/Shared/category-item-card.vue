<template>
  <a :href="link" class="igf-content-card">
    <div class="igf-content-card__media">
      <img v-if="thumbnail" :src="thumbnail" :alt="imageAlt || title">
      <span v-else aria-hidden="true"><i class="fa-solid fa-hand-holding-heart" /></span>
    </div>
    <div class="igf-content-card__body">
      <p v-if="eyebrow" class="igf-content-card__eyebrow">{{ eyebrow }}</p>
      <h2>{{ title }}</h2>
      <p>{{ truncatedSubtitle }}</p>
      <span class="igf-content-card__link">{{ linkLabel }} <span aria-hidden="true">&rarr;</span></span>
    </div>
  </a>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({
  title:{type:String,default:''},
  subtitle:{type:String,default:''},
  thumbnail:{type:String,default:''},
  imageAlt:{type:String,default:''},
  link:{type:String,default:'#'},
  eyebrow:{type:String,default:'Community impact'},
  linkLabel:{type:String,default:'Read the story'},
});
const truncatedSubtitle = computed(() => props.subtitle?.length > 145 ? `${props.subtitle.slice(0,145)}...` : props.subtitle);
</script>

<style scoped lang="scss">
.igf-content-card { display:flex; width:100%; height:100%; min-height:430px; flex-direction:column; overflow:hidden; border:1px solid var(--igf-card-border,#e5e0dc); border-radius:var(--igf-card-radius,16px); background:#fff; box-shadow:var(--igf-card-shadow,0 5px 18px rgba(25,28,29,.05)); color:#191c1d; font-family:'Hanken Grotesk',Arial,sans-serif; text-decoration:none; transition:.2s ease; }
.igf-content-card:hover { border-color:#ffb68a; box-shadow:0 13px 30px rgba(25,28,29,.1); color:#191c1d; transform:translateY(-4px); }
.igf-content-card__media { display:grid; height:var(--igf-card-media-height,230px); overflow:hidden; place-items:center; background:#efeae5; color:#9c4500; font-size:42px; }
.igf-content-card__media img { width:100%; height:100%; object-fit:cover; transition:transform .35s ease; }
.igf-content-card:hover img { transform:scale(1.035); }
.igf-content-card__body { display:flex; flex:1; flex-direction:column; padding:25px; }
.igf-content-card__eyebrow { margin:0 0 10px; color:#9c4500; font-size:10px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
.igf-content-card h2 { margin:0; color:#191c1d; font:650 var(--igf-heading-3,23px)/1.22 'Literata',Georgia,serif; letter-spacing:-.02em; }
.igf-content-card h2::after { display:none!important; }
.igf-content-card__body>p:not(.igf-content-card__eyebrow) { margin:13px 0 20px; color:#5e5d66; font-size:var(--igf-body-size,17px); line-height:1.6; }
.igf-content-card__link { margin-top:auto; color:#9c4500; font-size:13px; font-weight:800; }
</style>
