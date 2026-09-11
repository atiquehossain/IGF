<template>
  <Layout>
    <div class="igf-blog">
      <header class="igf-blog__hero">
        <div class="igf-shell">
          <p class="igf-blog__eyebrow">{{ eyebrow }}</p>
          <h1>{{ archiveTitle }}</h1>
          <p v-if="introduction" class="igf-blog__introduction">{{ introduction }}</p>
        </div>
      </header>

      <section class="igf-blog__content" :aria-labelledby="listingHeadingId">
        <div class="igf-shell">
          <div class="igf-blog__listing-head">
            <div>
              <p class="igf-blog__section-kicker">{{ latestLabel }}</p>
              <h2 :id="listingHeadingId">{{ listingLabel }}</h2>
            </div>
            <p v-if="totalCount" class="igf-blog__count" role="status">
              {{ formattedTotalCount }} {{ totalCountLabel }}
            </p>
          </div>

          <template v-if="items.length">
            <article class="igf-blog-featured">
              <a
                class="igf-blog-featured__link"
                :href="featuredPost.public_url"
                :aria-label="actionLabel(readLabel, featuredPost.name)"
              >
                <div class="igf-blog-featured__media">
                  <img
                    v-if="featuredPost.thumbnail"
                    :src="featuredPost.thumbnail"
                    :alt="featuredPost.thumbnail_alt || featuredPost.name || ''"
                    loading="eager"
                    fetchpriority="high"
                    decoding="async"
                  >
                  <span v-else aria-hidden="true"><i class="fa-regular fa-newspaper" /></span>
                </div>
                <div class="igf-blog-featured__copy">
                  <p class="igf-blog-featured__label">{{ featuredLabel }}</p>
                  <time
                    v-if="postDate(featuredPost)"
                    :datetime="dateTimeValue(featuredPost.published_at)"
                  >{{ postDate(featuredPost) }}</time>
                  <h2>{{ featuredPost.name }}</h2>
                  <p v-if="featuredPost.excerpt || featuredPost.sub_title" class="igf-blog-featured__excerpt">
                    {{ featuredPost.excerpt || featuredPost.sub_title }}
                  </p>
                  <span class="igf-blog-featured__action">
                    {{ readLabel }} <span aria-hidden="true">&rarr;</span>
                  </span>
                </div>
              </a>
            </article>

            <ul v-if="remainingPosts.length" class="igf-blog-grid" role="list">
              <li v-for="item in remainingPosts" :key="item.uuid || item.id || item.slug">
                <CategoryItemCard
                  :title="item.name"
                  :subtitle="item.excerpt || item.sub_title"
                  :thumbnail="item.thumbnail"
                  :image-alt="item.thumbnail_alt || item.name || ''"
                  :eyebrow="''"
                  :link-label="readLabel"
                  :link="item.public_url"
                >
                  <template v-if="postDate(item)" #meta>
                    <time :datetime="dateTimeValue(item.published_at)">{{ postDate(item) }}</time>
                  </template>
                </CategoryItemCard>
              </li>
            </ul>
          </template>

          <div v-else class="igf-blog__empty" role="status" aria-live="polite">
            <span aria-hidden="true"><i class="fa-regular fa-newspaper" /></span>
            <h2>{{ emptyTitle }}</h2>
            <p>{{ emptyBody }}</p>
          </div>

          <v-pagination
            v-if="totalPages > 1"
            :model-value="currentPageNumber"
            :length="totalPages"
            class="igf-blog__pagination"
            :aria-label="paginationLabel"
            :page-aria-label="paginationPageLabel"
            :current-page-aria-label="paginationCurrentLabel"
            :previous-aria-label="paginationPreviousLabel"
            :next-aria-label="paginationNextLabel"
            @update:model-value="onPageChange"
          />
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

import Layout from '../layouts/App';
import CategoryItemCard from '../Shared/category-item-card.vue';
import { formatDate, formatNumber } from '../Shared/composables/siteSettings';

const inertiaPage = usePage();
const presentation = computed(() => inertiaPage.props.archive_presentation || {});
const regional = computed(() => inertiaPage.props.siteSettings?.regional || {});
const items = computed(() => Array.isArray(inertiaPage.props.data?.items) ? inertiaPage.props.data.items : []);
const properties = computed(() => inertiaPage.props.properties || {});
const locale = computed(() => String(inertiaPage.props.locale || 'en').toLowerCase());
const isBangla = computed(() => locale.value.startsWith('bn'));

const archiveTitle = computed(() => presentation.value.title || inertiaPage.props.title || (isBangla.value ? 'ব্লগ' : 'Blog'));
const eyebrow = computed(() => presentation.value.eyebrow || (isBangla.value ? 'মাঠপর্যায়ের কথা' : 'From the field'));
const introduction = computed(() => presentation.value.introduction || '');
const listingLabel = computed(() => presentation.value.listing_label || (isBangla.value ? 'সর্বশেষ ব্লগ পোস্ট' : 'Latest blog posts'));
const latestLabel = computed(() => isBangla.value ? 'সাম্প্রতিক লেখা' : 'Latest writing');
const featuredLabel = computed(() => presentation.value.featured_label || (isBangla.value ? 'নির্বাচিত লেখা' : 'Featured post'));
const readLabel = computed(() => presentation.value.read_label || (isBangla.value ? 'লেখাটি পড়ুন' : 'Read the post'));
const emptyTitle = computed(() => presentation.value.empty_title || (isBangla.value ? 'এখনও কোনো লেখা প্রকাশিত হয়নি' : 'No posts published yet'));
const emptyBody = computed(() => presentation.value.empty_body || (isBangla.value ? 'নতুন লেখা প্রকাশিত হলে এখানে দেখা যাবে।' : 'New posts will appear here after they are published.'));
const featuredPost = computed(() => items.value[0] || {});
const remainingPosts = computed(() => items.value.slice(1));
const currentPageNumber = computed(() => Math.max(1, Number(properties.value.page) || 1));
const totalPages = computed(() => Math.max(1, Number(properties.value.total_page) || 1));
const totalCount = computed(() => Math.max(0, Number(properties.value.total_count) || 0));
const formattedTotalCount = computed(() => formatNumber(totalCount.value, regional.value));
const totalCountLabel = computed(() => {
  if (isBangla.value) return 'টি প্রকাশিত লেখা';
  return totalCount.value === 1 ? 'published post' : 'published posts';
});
const listingHeadingId = 'blog-listing-heading';
const archiveRoute = computed(() => inertiaPage.props.archive_route === 'frontend.blog'
  ? inertiaPage.props.archive_route
  : 'frontend.blog');
const localeQueryParameter = computed(() => {
  const candidate = String(inertiaPage.props.seoLocale?.query_parameter || 'lang');
  return /^[A-Za-z][A-Za-z0-9_-]{0,31}$/.test(candidate) ? candidate : 'lang';
});

const paginationLabel = computed(() => presentation.value.pagination_label || (isBangla.value ? 'ব্লগের পাতাসমূহ' : 'Blog pages'));
const paginationPageLabel = computed(() => presentation.value.pagination_page_label || (isBangla.value ? 'পৃষ্ঠা {0}-এ যান' : 'Go to page {0}'));
const paginationCurrentLabel = computed(() => presentation.value.pagination_current_label || (isBangla.value ? 'বর্তমান পৃষ্ঠা, পৃষ্ঠা {0}' : 'Current page, page {0}'));
const paginationPreviousLabel = computed(() => presentation.value.pagination_previous_label || (isBangla.value ? 'আগের পৃষ্ঠা' : 'Previous page'));
const paginationNextLabel = computed(() => presentation.value.pagination_next_label || (isBangla.value ? 'পরের পৃষ্ঠা' : 'Next page'));

function postDate(post) {
  return formatDate(post?.published_at, regional.value, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

function dateTimeValue(value) {
  return String(value || '').trim().replace(' ', 'T');
}

function actionLabel(label, title) {
  return title ? `${label}: ${title}` : label;
}

function onPageChange(number) {
  const query = new URLSearchParams(String(inertiaPage.url || '').split('?')[1] || '');
  const data = { page: number };
  const activeLocale = query.get(localeQueryParameter.value);
  if (activeLocale) data[localeQueryParameter.value] = activeLocale;

  router.get(route(archiveRoute.value), data, {
    preserveState: true,
    preserveScroll: true,
  });
}
</script>

<style scoped lang="scss">
.igf-blog {
  --orange: #ff7500;
  --brown: #9c4500;
  --brown-dark: #783300;
  --ink: #191c1d;
  --muted: #5e5d66;
  --surface: #f7f8f9;
  --line: #e1dcd7;
  color: var(--ink);
  font-family: 'Hanken Grotesk', Arial, sans-serif;
}

.igf-shell {
  width: min(calc(100% - 40px), var(--igf-content-width, 1200px));
  margin-inline: auto;
}

.igf-blog__hero {
  position: relative;
  overflow: hidden;
  padding: var(--igf-section-block, clamp(82px, 10vw, 132px)) 0;
  background: radial-gradient(circle at 86% 16%, rgba(255, 117, 0, .21), transparent 28%), #242220;
  color: #fff;
}

.igf-blog__hero::after {
  position: absolute;
  right: -120px;
  bottom: -250px;
  width: 480px;
  aspect-ratio: 1;
  border: 1px solid rgba(255, 117, 0, .2);
  border-radius: 50%;
  content: '';
  pointer-events: none;
}

.igf-blog__hero .igf-shell {
  position: relative;
  z-index: 1;
}

.igf-blog__eyebrow,
.igf-blog__section-kicker,
.igf-blog-featured__label {
  margin: 0 0 14px;
  color: #ffad72;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
}

.igf-blog__hero h1 {
  max-width: 880px;
  margin: 0;
  color: #fff;
  font: 650 var(--igf-heading-1, clamp(46px, 6vw, 74px))/1.04 'Literata', Georgia, serif;
  letter-spacing: -.038em;
}

.igf-blog__hero h1::after,
.igf-blog h2::after {
  display: none !important;
}

.igf-blog__introduction {
  max-width: 720px;
  margin: 23px 0 0;
  color: #d7d3cf;
  font-size: var(--igf-lead-size, 19px);
  line-height: 1.66;
}

.igf-blog__content {
  min-height: 480px;
  padding: clamp(64px, 8vw, 104px) 0;
  background: var(--surface);
}

.igf-blog__listing-head {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 28px;
  margin-bottom: 32px;
}

.igf-blog__section-kicker {
  color: var(--brown);
}

.igf-blog__listing-head h2 {
  margin: 0;
  font: 650 clamp(31px, 4vw, 46px)/1.1 'Literata', Georgia, serif;
  letter-spacing: -.03em;
}

.igf-blog__count {
  flex: 0 0 auto;
  margin: 0 0 5px;
  color: var(--muted);
  font-size: 13px;
  font-weight: 750;
}

.igf-blog-featured {
  margin: 0 0 clamp(34px, 5vw, 54px);
}

.igf-blog-featured__link {
  display: grid;
  min-height: 440px;
  grid-template-columns: minmax(0, 1.18fr) minmax(330px, .82fr);
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 24px;
  background: #fff;
  box-shadow: 0 18px 48px rgba(34, 30, 27, .1);
  color: var(--ink);
  text-decoration: none;
  transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
}

.igf-blog-featured__media {
  display: grid;
  min-height: 440px;
  overflow: hidden;
  place-items: center;
  background: linear-gradient(145deg, #f1eae4, #e6ddd5);
  color: var(--brown);
  font-size: 58px;
}

.igf-blog-featured__media img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform .4s ease;
}

.igf-blog-featured__copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  padding: clamp(30px, 4vw, 52px);
}

.igf-blog-featured__label {
  color: var(--brown);
}

.igf-blog-featured time,
.igf-blog-grid time {
  color: var(--muted);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .02em;
}

.igf-blog-featured h2 {
  margin: 13px 0 0;
  color: var(--ink);
  font: 650 clamp(29px, 3.3vw, 43px)/1.12 'Literata', Georgia, serif;
  letter-spacing: -.032em;
  overflow-wrap: anywhere;
}

.igf-blog-featured__excerpt {
  display: -webkit-box;
  margin: 18px 0 0;
  overflow: hidden;
  color: var(--muted);
  font-size: 16px;
  line-height: 1.65;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 4;
}

.igf-blog-featured__action {
  display: inline-flex;
  align-items: center;
  gap: 11px;
  margin-top: 27px;
  color: var(--brown);
  font-size: 14px;
  font-weight: 800;
}

.igf-blog-featured__action > span {
  display: grid;
  width: 34px;
  aspect-ratio: 1;
  border-radius: 50%;
  background: #fff1e6;
  place-items: center;
  transition: background-color .2s ease, color .2s ease, transform .2s ease;
}

.igf-blog-featured__link:focus-visible {
  outline: 3px solid var(--brown-dark);
  outline-offset: 5px;
}

.igf-blog-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: clamp(22px, 2.5vw, 30px);
  margin: 0;
  padding: 0;
  list-style: none;
}

.igf-blog-grid > li {
  min-width: 0;
}

.igf-blog-grid :deep(.igf-content-card__meta) {
  margin-top: 16px;
}

.igf-blog__empty {
  display: grid;
  min-height: 320px;
  align-content: center;
  justify-items: center;
  border: 1px dashed #d5cdc6;
  border-radius: 22px;
  padding: 58px 24px;
  background: #fff;
  text-align: center;
}

.igf-blog__empty > span {
  display: grid;
  width: 72px;
  aspect-ratio: 1;
  border-radius: 50%;
  background: #fff0e4;
  color: var(--brown);
  font-size: 30px;
  place-items: center;
}

.igf-blog__empty h2 {
  margin: 20px 0 8px;
  font: 650 30px/1.2 'Literata', Georgia, serif;
}

.igf-blog__empty p {
  max-width: 520px;
  margin: 0;
  color: var(--muted);
  line-height: 1.65;
}

.igf-blog__pagination {
  margin-top: 48px;
}

@media (hover: hover) {
  .igf-blog-featured__link:hover {
    border-color: #ffb68a;
    box-shadow: 0 24px 58px rgba(34, 30, 27, .14);
    color: var(--ink);
    transform: translateY(-4px);
  }

  .igf-blog-featured__link:hover img {
    transform: scale(1.035);
  }

  .igf-blog-featured__link:hover .igf-blog-featured__action > span {
    background: var(--brown);
    color: #fff;
    transform: translateX(3px);
  }
}

@media (max-width: 1000px) {
  .igf-blog-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 820px) {
  .igf-blog-featured__link {
    min-height: 0;
    grid-template-columns: 1fr;
  }

  .igf-blog-featured__media {
    min-height: 0;
    aspect-ratio: 16 / 10;
  }

  .igf-blog-featured__copy {
    padding: 32px 28px 36px;
  }
}

@media (max-width: 640px) {
  .igf-shell {
    width: min(calc(100% - 28px), 1200px);
  }

  .igf-blog__hero {
    padding: 66px 0;
  }

  .igf-blog__hero h1 {
    font-size: clamp(42px, 13vw, 56px);
  }

  .igf-blog__introduction {
    font-size: 16px;
    line-height: 1.62;
  }

  .igf-blog__content {
    padding: 50px 0 64px;
  }

  .igf-blog__listing-head {
    display: grid;
    gap: 10px;
    margin-bottom: 24px;
  }

  .igf-blog__count {
    margin: 0;
  }

  .igf-blog-featured__link {
    border-radius: 19px;
  }

  .igf-blog-featured__copy {
    padding: 26px 23px 30px;
  }

  .igf-blog-featured__excerpt {
    font-size: 15px;
    -webkit-line-clamp: 3;
  }

  .igf-blog-grid {
    grid-template-columns: 1fr;
  }
}

@media (prefers-reduced-motion: reduce) {
  .igf-blog-featured__link,
  .igf-blog-featured__media img,
  .igf-blog-featured__action > span {
    transition: none;
  }

  .igf-blog-featured__link:hover,
  .igf-blog-featured__link:hover img,
  .igf-blog-featured__link:hover .igf-blog-featured__action > span {
    transform: none;
  }
}
</style>
