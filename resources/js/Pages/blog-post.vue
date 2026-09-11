<!-- Rich text is sanitized by ContentSanitizer before it reaches this view. -->
<!-- eslint-disable vue/no-v-html -->
<template>
  <Layout>
    <article v-if="post" class="igf-blog-post">
      <header class="igf-blog-post__hero">
        <div class="igf-shell igf-blog-post__hero-inner">
          <a class="igf-blog-post__back" :href="archiveUrl">
            <span aria-hidden="true">&larr;</span> {{ backLabel }}
          </a>
          <p class="igf-blog-post__eyebrow">{{ detailEyebrow }}</p>
          <h1>{{ post.name }}</h1>
          <p v-if="post.sub_title" class="igf-blog-post__lead">{{ post.sub_title }}</p>
          <div v-if="publishedDate" class="igf-blog-post__meta">
            <i class="fa-regular fa-calendar" aria-hidden="true" />
            <time :datetime="dateTimeValue(post.published_at)">{{ publishedDate }}</time>
          </div>
        </div>
      </header>

      <figure v-if="post.thumbnail" class="igf-blog-post__image">
        <img :src="post.thumbnail" :alt="post.thumbnail_alt || post.name || ''" decoding="async">
      </figure>

      <div v-if="visibleBlocks.length" class="igf-blog-post__blocks">
        <PageBlocks :blocks="visibleBlocks" />
      </div>
      <section v-else class="igf-blog-post__body" :aria-label="post.name">
        <div v-if="post.description" class="igf-blog-post__prose" v-html="post.description" />
      </section>

      <footer class="igf-blog-post__footer">
        <div class="igf-shell">
          <a :href="archiveUrl"><span aria-hidden="true">&larr;</span> {{ footerLabel }}</a>
        </div>
      </footer>
    </article>
  </Layout>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

import Layout from '../layouts/App.vue';
import PageBlocks from '../Shared/PageBlocks.vue';
import { formatDate } from '../Shared/composables/siteSettings';

const inertiaPage = usePage();
const post = computed(() => inertiaPage.props.data?.page || null);
const presentation = computed(() => inertiaPage.props.archive_presentation || {});
const regional = computed(() => inertiaPage.props.siteSettings?.regional || {});
const locale = computed(() => String(inertiaPage.props.locale || 'en').toLowerCase());
const isBangla = computed(() => locale.value.startsWith('bn'));
const visibleBlocks = computed(() => Array.isArray(post.value?.visible_blocks) ? post.value.visible_blocks : []);
const archiveUrl = computed(() => inertiaPage.props.data?.archive_url || '/blog');
const backLabel = computed(() => presentation.value.back_label || presentation.value.title || (isBangla.value ? 'ব্লগ' : 'Blog'));
const detailEyebrow = computed(() => presentation.value.detail_eyebrow || presentation.value.eyebrow || (isBangla.value ? 'মাঠপর্যায়ের কথা' : 'From the field'));
const footerLabel = computed(() => presentation.value.footer_label || backLabel.value);
const publishedDate = computed(() => formatDate(post.value?.published_at, regional.value, {
  year: 'numeric',
  month: 'long',
  day: 'numeric',
}));

function dateTimeValue(value) {
  return String(value || '').trim().replace(' ', 'T');
}
</script>

<style scoped>
.igf-blog-post {
  --orange: #ff7500;
  --brown: #9c4500;
  --brown-dark: #783300;
  --ink: #191c1d;
  --muted: #5f6065;
  --surface: #f8f9fa;
  --line: #dedbd7;
  color: var(--ink);
  font-family: 'Hanken Grotesk', Arial, sans-serif;
}

.igf-shell {
  width: min(calc(100% - 40px), 1120px);
  margin-inline: auto;
}

.igf-blog-post__hero {
  position: relative;
  overflow: hidden;
  padding: clamp(90px, 11vw, 138px) 0 clamp(82px, 10vw, 122px);
  background: radial-gradient(circle at 87% 18%, rgba(255, 117, 0, .2), transparent 27%), #202223;
  color: #fff;
}

.igf-blog-post__hero::after {
  position: absolute;
  right: -105px;
  bottom: -250px;
  width: 460px;
  aspect-ratio: 1;
  border: 1px solid rgba(255, 117, 0, .2);
  border-radius: 50%;
  content: '';
  pointer-events: none;
}

.igf-blog-post__hero-inner {
  position: relative;
  z-index: 1;
  max-width: 980px;
}

.igf-blog-post__back,
.igf-blog-post__footer a {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  color: #ffb070;
  font-size: 13px;
  font-weight: 800;
  text-decoration: none;
}

.igf-blog-post__back {
  margin-bottom: 43px;
}

.igf-blog-post__back:focus-visible {
  border-radius: 3px;
  outline: 3px solid #fff;
  outline-offset: 4px;
}

.igf-blog-post__eyebrow {
  margin: 0 0 14px;
  color: #ffb070;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
}

.igf-blog-post h1 {
  max-width: 920px;
  margin: 0;
  color: #fff;
  font: 650 clamp(42px, 6vw, 68px)/1.07 'Literata', Georgia, serif;
  letter-spacing: -.038em;
  overflow-wrap: anywhere;
}

.igf-blog-post h1::after,
.igf-blog-post :deep(h2)::after,
.igf-blog-post :deep(h3)::after {
  display: none !important;
}

.igf-blog-post__lead {
  max-width: 790px;
  margin: 24px 0 0;
  color: #d7d5d2;
  font: 500 clamp(18px, 2.2vw, 22px)/1.6 'Hanken Grotesk', Arial, sans-serif;
}

.igf-blog-post__meta {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-top: 26px;
  color: #d4d5d5;
  font-size: 13px;
  font-weight: 650;
}

.igf-blog-post__meta i {
  color: #ffb070;
}

.igf-blog-post__image {
  position: relative;
  z-index: 2;
  width: min(calc(100% - 40px), 1120px);
  overflow: hidden;
  margin: -48px auto 0;
  border: 8px solid #fff;
  border-radius: 20px;
  background: #e5dfd8;
  box-shadow: 0 17px 45px rgba(25, 28, 29, .14);
}

.igf-blog-post__image img {
  display: block;
  width: 100%;
  max-height: 620px;
  object-fit: cover;
}

.igf-blog-post__body {
  padding: clamp(62px, 8vw, 100px) 0;
  background: #fff;
}

.igf-blog-post__prose {
  width: min(calc(100% - 40px), 820px);
  margin-inline: auto;
  color: var(--muted);
  font-size: 17px;
  line-height: 1.82;
  overflow-wrap: anywhere;
}

.igf-blog-post__prose :deep(> :first-child) {
  margin-top: 0;
}

.igf-blog-post__prose :deep(> :last-child) {
  margin-bottom: 0;
}

.igf-blog-post__prose :deep(p),
.igf-blog-post__prose :deep(ul),
.igf-blog-post__prose :deep(ol),
.igf-blog-post__prose :deep(blockquote) {
  margin: 0 0 1.35em;
}

.igf-blog-post__prose :deep(h2),
.igf-blog-post__prose :deep(h3),
.igf-blog-post__prose :deep(h4) {
  margin: 1.5em 0 .58em;
  color: var(--ink);
  font-family: 'Literata', Georgia, serif;
  font-weight: 650;
  letter-spacing: -.025em;
  line-height: 1.2;
}

.igf-blog-post__prose :deep(h2) {
  font-size: clamp(29px, 4vw, 40px);
}

.igf-blog-post__prose :deep(h3) {
  font-size: clamp(24px, 3vw, 31px);
}

.igf-blog-post__prose :deep(h4) {
  font-size: 21px;
}

.igf-blog-post__prose :deep(a) {
  color: var(--brown);
  font-weight: 800;
  text-decoration-thickness: 1px;
  text-underline-offset: 4px;
}

.igf-blog-post__prose :deep(a:hover) {
  color: var(--brown-dark);
}

.igf-blog-post__prose :deep(a:focus-visible) {
  border-radius: 3px;
  outline: 3px solid rgba(156, 69, 0, .28);
  outline-offset: 3px;
}

.igf-blog-post__prose :deep(img) {
  display: block;
  max-width: 100%;
  height: auto;
  margin: 2em auto;
  border-radius: 14px;
}

.igf-blog-post__prose :deep(blockquote) {
  border-left: 4px solid var(--orange);
  padding: 5px 0 5px 24px;
  color: var(--ink);
  font: 550 clamp(20px, 3vw, 26px)/1.55 'Literata', Georgia, serif;
}

.igf-blog-post__prose :deep(li + li) {
  margin-top: .45em;
}

.igf-blog-post__prose :deep(table) {
  display: block;
  width: 100%;
  max-width: 100%;
  margin: 2em 0;
  overflow-x: auto;
  border-collapse: collapse;
}

.igf-blog-post__prose :deep(th),
.igf-blog-post__prose :deep(td) {
  min-width: 140px;
  border: 1px solid var(--line);
  padding: 11px 13px;
  text-align: left;
}

.igf-blog-post__prose :deep(th) {
  background: var(--surface);
  color: var(--ink);
}

.igf-blog-post__blocks {
  background: #fff;
}

.igf-blog-post__footer {
  border-top: 1px solid var(--line);
  padding: 34px 0 70px;
  background: var(--surface);
}

.igf-blog-post__footer a {
  min-height: 44px;
  color: var(--brown);
}

.igf-blog-post__footer a:hover {
  color: var(--brown-dark);
  text-decoration: underline;
  text-underline-offset: 4px;
}

.igf-blog-post__footer a:focus-visible {
  border-radius: 3px;
  outline: 3px solid rgba(156, 69, 0, .28);
  outline-offset: 3px;
}

@media (max-width: 640px) {
  .igf-shell,
  .igf-blog-post__prose {
    width: min(calc(100% - 28px), 1120px);
  }

  .igf-blog-post__hero {
    padding: 70px 0 76px;
  }

  .igf-blog-post__back {
    margin-bottom: 32px;
  }

  .igf-blog-post__lead {
    font-size: 17px;
  }

  .igf-blog-post__image {
    width: min(calc(100% - 20px), 1120px);
    margin-top: -34px;
    border-width: 5px;
    border-radius: 16px;
  }

  .igf-blog-post__body {
    padding: 52px 0 66px;
  }

  .igf-blog-post__prose {
    font-size: 16px;
    line-height: 1.75;
  }

  .igf-blog-post__prose :deep(blockquote) {
    padding-left: 18px;
  }

  .igf-blog-post__footer {
    padding: 28px 0 52px;
  }
}
</style>
