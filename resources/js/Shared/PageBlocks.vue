<!-- Rich text and custom HTML are sanitized by ContentSanitizer before rendering. -->
<!-- eslint-disable vue/no-v-html -->
<template>
  <div ref="pageBlocksRoot" class="igf-page-blocks">
    <section
      v-for="block in blocks"
      :key="block.uuid"
      class="igf-page-block"
      :class="[
        `igf-page-block--${block.type}`,
        `igf-page-block--${block.content?.variant || 'default'}`,
        visibilityClass(block),
      ]"
      :style="blockStyle(block)"
      :aria-label="block.type === 'hero' ? shared.hero_carousel_label : null"
      :aria-roledescription="block.type === 'hero' && heroSlides(block).length > 1 ? shared.hero_carousel_roledescription : null"
      :tabindex="block.type === 'hero' && heroSlides(block).length > 1 ? 0 : null"
      @mouseenter="pauseHeroInteraction(block, true)"
      @mouseleave="pauseHeroInteraction(block, false)"
      @focusin="pauseHeroInteraction(block, true, true)"
      @focusout="releaseHeroFocus(block, $event)"
      @keydown.left.prevent="previousHero(block)"
      @keydown.right.prevent="nextHero(block)"
      @touchstart.passive="rememberHeroTouch(block, $event)"
      @touchend.passive="finishHeroTouch(block, $event)"
    >
      <template v-if="block.type === 'hero'">
        <div class="igf-hero-carousel__backgrounds" aria-hidden="true">
          <span
            v-for="(slide, slideIndex) in heroSlides(block)"
            :key="`${block.uuid}-background-${slideIndex}`"
            :class="{'is-active': slideIndex === activeHeroIndex(block)}"
            :style="slideIndex === activeHeroIndex(block) ? heroBackgroundStyle(slide) : undefined"
          />
        </div>
        <div class="igf-page-block__overlay" :style="heroOverlayStyle(block)" />
        <div class="igf-page-block__inner igf-page-block__hero-grid">
          <div :key="`${block.uuid}-slide-${activeHeroIndex(block)}`" class="igf-page-block__hero-content igf-hero-carousel__content">
            <p v-if="activeHeroSlide(block).eyebrow" class="igf-page-block__eyebrow igf-page-block__eyebrow--inverse">
              <i class="fa-solid fa-bullhorn" aria-hidden="true" /> {{ activeHeroSlide(block).eyebrow }}
            </p>
            <h1>{{ activeHeroSlide(block).heading }}</h1>
            <p class="igf-page-block__lead">{{ activeHeroSlide(block).body }}</p>
            <div class="igf-page-block__actions">
              <a v-if="activeHeroSlide(block).primary_label" class="igf-button igf-button--primary" :href="safeHref(activeHeroSlide(block).primary_url, '#')" :aria-label="activeHeroSlide(block).primary_label">
                {{ activeHeroSlide(block).primary_label }} <span aria-hidden="true">→</span>
              </a>
              <a v-if="activeHeroSlide(block).secondary_label" class="igf-button igf-button--secondary" :href="safeHref(activeHeroSlide(block).secondary_url, '#')">
                {{ activeHeroSlide(block).secondary_label }}
              </a>
            </div>
            <a v-if="activeHeroSlide(block).report_label" class="igf-report-link" :href="safeHref(activeHeroSlide(block).report_url, '#')" :aria-label="activeHeroSlide(block).report_label">
              <i class="fa-regular fa-file-lines" aria-hidden="true" /> {{ activeHeroSlide(block).report_label }} <span aria-hidden="true">→</span>
            </a>
          </div>
        </div>
        <nav v-if="heroSlides(block).length > 1" class="igf-hero-carousel__controls" :aria-label="shared.hero_controls_label">
          <button type="button" class="igf-hero-carousel__arrow" :aria-label="shared.hero_previous_label" @click.stop="previousHero(block)">
            <i class="fa-solid fa-arrow-left" aria-hidden="true" />
          </button>
          <div class="igf-hero-carousel__dots">
            <button
              v-for="(slide, slideIndex) in heroSlides(block)"
              :key="`${block.uuid}-dot-${slideIndex}`"
              type="button"
              :aria-label="heroDotLabel(slideIndex, heroSlides(block).length)"
              :aria-current="slideIndex === activeHeroIndex(block) ? 'true' : null"
              @click.stop="goToHero(block, slideIndex)"
            ><span /></button>
          </div>
          <button type="button" class="igf-hero-carousel__arrow" :aria-label="shared.hero_next_label" @click.stop="nextHero(block)">
            <i class="fa-solid fa-arrow-right" aria-hidden="true" />
          </button>
          <button
            v-if="heroAutoplayEnabled(block)"
            type="button"
            class="igf-hero-carousel__pause"
            :aria-label="heroUserPaused[block.uuid] ? shared.hero_resume_label : shared.hero_pause_label"
            :aria-pressed="heroUserPaused[block.uuid] ? 'true' : 'false'"
            @click.stop="toggleHeroAutoplay(block)"
          >
            <i :class="heroUserPaused[block.uuid] ? 'fa-solid fa-play' : 'fa-solid fa-pause'" aria-hidden="true" />
          </button>
        </nav>
        <p v-if="heroSlides(block).length > 1" class="sr-only" role="status" aria-live="polite" aria-atomic="true">
          {{ heroStatusLabel(block) }}
        </p>
      </template>

      <div v-else-if="block.type === 'stats'" class="igf-page-block__inner">
        <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
        <h2 v-if="block.content?.heading">{{ block.content.heading }}</h2>
        <div class="igf-stats">
          <article
            v-for="(item, index) in block.content?.items || []"
            :key="index"
            class="igf-stat"
            :class="statAnimationClass(block)"
            :style="statAnimationStyle(block, index)"
            :data-stat-block="block.uuid"
            :data-stat-index="index"
          >
            <i :class="iconClass(item.icon)" aria-hidden="true" />
            <strong class="animated-counter" :aria-label="String(item.value || '')">{{ displayStatValue(block, item, index) }}</strong>
            <span>{{ item.label }}</span>
          </article>
        </div>
      </div>

      <div v-else-if="block.type === 'media_text'" class="igf-page-block__inner igf-media-shell">
        <div
          class="igf-media-text"
          :class="{
            'igf-media-text--reverse': block.content?.image_position === 'right',
            'igf-media-text--without-media': !mediaTextHasMedia(block),
          }"
        >
          <figure v-if="mediaTextHasMedia(block)" class="igf-media-text__figure">
            <div class="igf-media-text__media" :class="`igf-media-text__media--${mediaTextType(block)}`">
              <img
                v-if="mediaTextType(block) === 'image'"
                :src="mediaTextImageUrl(block)"
                :srcset="responsiveImage(mediaTextImageUrl(block), '(max-width: 900px) 100vw, 45vw').webpSrcset || undefined"
                :sizes="responsiveImage(mediaTextImageUrl(block), '(max-width: 900px) 100vw, 45vw').sizes"
                :width="responsiveImage(mediaTextImageUrl(block)).width"
                :height="responsiveImage(mediaTextImageUrl(block)).height"
                :alt="block.content.image_alt || ''"
                :aria-describedby="mediaTextCaptionDescription(block)"
                loading="lazy"
                decoding="async"
              >
              <video
                v-else-if="mediaTextType(block) === 'video'"
                :src="mediaTextVideoUrl(block)"
                :poster="mediaTextPosterUrl(block) || null"
                :aria-label="mediaTextVideoLabel(block)"
                :aria-describedby="mediaTextCaptionDescription(block)"
                controls
                playsinline
                preload="metadata"
              >{{ shared.video_unsupported_message }}</video>
              <iframe
                v-else
                :src="mediaTextYoutubeUrl(block)"
                :title="mediaTextVideoLabel(block)"
                :aria-describedby="mediaTextCaptionDescription(block)"
                loading="lazy"
                referrerpolicy="strict-origin-when-cross-origin"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
              />
            </div>
            <figcaption v-if="mediaTextCaption(block)" :id="mediaTextCaptionId(block)" class="igf-media-text__caption">{{ mediaTextCaption(block) }}</figcaption>
          </figure>
          <div class="igf-media-text__content">
            <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
            <h2>{{ block.content?.heading }}</h2>
            <div class="igf-page-block__copy" v-html="block.content?.body" />
            <a v-if="block.content?.link_label" class="igf-text-link" :href="safeHref(block.content.link_url, '#')">
              {{ block.content.link_label }} <span aria-hidden="true">→</span>
            </a>
          </div>
        </div>
      </div>

      <div
        v-else-if="block.type === 'ways_to_give'"
        class="igf-page-block__inner igf-giving"
        :class="`igf-giving--${block.content?.layout || 'card_grid'}`"
      >
        <header class="igf-giving__heading">
          <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
          <h2>{{ block.content?.heading }}</h2>
          <div v-if="block.content?.body" class="igf-section-lead igf-giving__lead" v-html="block.content.body" />
        </header>
        <div v-if="givingItems(block).length" class="igf-giving__options">
          <a
            v-for="item in givingItems(block)"
            :key="item.key"
            class="igf-giving-card"
            :href="safeHref(item.url, '#')"
            :aria-label="givingAriaLabel(item)"
          >
            <span class="igf-giving-card__media" aria-hidden="true">
              <img v-if="item.image" :src="item.image" :srcset="responsiveImage(item.image, '(max-width: 760px) 100vw, 33vw').webpSrcset || undefined" :sizes="responsiveImage(item.image, '(max-width: 760px) 100vw, 33vw').sizes" :width="responsiveImage(item.image).width" :height="responsiveImage(item.image).height" alt="" loading="lazy" decoding="async">
              <i v-else class="fa-solid fa-hand-holding-heart" />
            </span>
            <span class="igf-giving-card__copy">
              <small v-if="item.destination">{{ item.destination }}</small>
              <strong>{{ item.heading }}</strong>
              <span v-if="item.body" class="igf-giving-card__body" v-html="item.body" />
              <b>{{ item.link_label || block.content?.link_label || 'Give now' }} <span aria-hidden="true">→</span></b>
            </span>
          </a>
        </div>
        <p v-else class="igf-dynamic-empty" role="status">{{ block.content?.empty_state || 'Giving options are being updated. Please check again soon.' }}</p>
      </div>

      <div v-else-if="block.type === 'causes'" class="igf-page-block__inner">
        <template v-if="block.content?.presentation === 'focus_areas'">
          <div class="igf-focus-areas">
            <header
              class="igf-focus-areas__heading igf-focus-area__reveal"
              data-focus-area-reveal
              :style="focusAreaRevealStyle(0)"
            >
              <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
              <h2>{{ block.content?.heading }}</h2>
              <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
              <a v-if="blockLink(block, 'causes')" class="igf-text-link" :href="blockLink(block, 'causes')">{{ blockLabel(block, 'view_all_label', 'causes_view_all_label', 'View all programs') }} →</a>
            </header>
            <a
              v-for="(item, index) in block.content?.items || []"
              :key="item.id || index"
              class="igf-focus-area-card igf-focus-area__reveal"
              :href="safeHref(item.url, '#')"
              data-focus-area-reveal
              :style="focusAreaRevealStyle(index + 1)"
            >
              <span class="igf-focus-area-card__media" aria-hidden="true">
                <img
                  v-if="item.image"
                  :src="item.image"
                  :srcset="responsiveImage(item.image, '(max-width: 767px) 88px, 72px').webpSrcset || undefined"
                  :sizes="responsiveImage(item.image, '(max-width: 767px) 88px, 72px').sizes"
                  :width="responsiveImage(item.image).width"
                  :height="responsiveImage(item.image).height"
                  alt=""
                  loading="lazy"
                  decoding="async"
                >
                <i v-else :class="iconClass(item.icon)" />
              </span>
              <span class="igf-focus-area-card__copy">
                <h3>{{ item.heading }}</h3>
                <p v-if="item.body">{{ item.body }}</p>
                <span class="igf-focus-area-card__link">{{ item.link_label || blockLabel(block, 'item_link_label', 'causes_item_link_label', 'Learn more') }} <span aria-hidden="true">→</span></span>
              </span>
            </a>
          </div>
          <p v-if="!block.content?.items?.length" class="igf-dynamic-empty">{{ blockLabel(block, 'empty_state', 'causes_empty_state', 'Published programs will appear here automatically.') }}</p>
        </template>
        <template v-else>
          <div class="igf-section-heading">
            <div>
              <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
              <h2>{{ block.content?.heading }}</h2>
              <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
            </div>
            <a v-if="blockLink(block, 'causes')" class="igf-text-link" :href="blockLink(block, 'causes')">{{ blockLabel(block, 'view_all_label', 'causes_view_all_label', 'View all programs') }} →</a>
          </div>
          <div v-if="block.content?.items?.length" class="igf-card-grid">
            <a v-for="(item, index) in block.content.items" :key="index" class="igf-card" :href="safeHref(item.url, '#')">
              <div v-if="item.image" class="igf-card__media"><img :src="item.image" :srcset="responsiveImage(item.image, '(max-width: 760px) 100vw, 400px').webpSrcset || undefined" :sizes="responsiveImage(item.image, '(max-width: 760px) 100vw, 400px').sizes" :width="responsiveImage(item.image).width" :height="responsiveImage(item.image).height" :alt="item.image_alt || item.heading || ''" loading="lazy" decoding="async"></div>
              <div class="igf-card__content"><h3>{{ item.heading }}</h3><p v-if="item.body">{{ item.body }}</p><span class="igf-card__link">{{ item.link_label || blockLabel(block, 'item_link_label', 'causes_item_link_label', 'Learn more') }} →</span></div>
            </a>
          </div>
          <p v-else class="igf-dynamic-empty">{{ blockLabel(block, 'empty_state', 'causes_empty_state', 'Published programs will appear here automatically.') }}</p>
        </template>
      </div>

      <div v-else-if="block.type === 'events'" class="igf-page-block__inner">
        <div class="igf-section-heading">
          <div>
            <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
            <h2>{{ block.content?.heading }}</h2>
            <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
          </div>
          <a v-if="blockLink(block, 'events')" class="igf-text-link" :href="blockLink(block, 'events')">{{ blockLabel(block, 'view_all_label', 'events_view_all_label', 'View all events') }} →</a>
        </div>
        <div v-if="block.content?.items?.length" class="igf-event-cards">
          <a v-for="(item, index) in block.content.items" :key="index" :href="safeHref(item.url, '#')">
            <img v-if="item.image" :src="item.image" :srcset="responsiveImage(item.image, '(max-width: 760px) 100vw, 400px').webpSrcset || undefined" :sizes="responsiveImage(item.image, '(max-width: 760px) 100vw, 400px').sizes" :width="responsiveImage(item.image).width" :height="responsiveImage(item.image).height" :alt="item.image_alt || item.heading || ''" loading="lazy" decoding="async">
            <span class="igf-event-cards__date"><strong>{{ eventDay(item) }}</strong><small>{{ eventMonth(item, shared.event_date_fallback) }}</small></span>
            <span class="igf-event-cards__copy"><h3>{{ item.heading }}</h3><p v-if="item.body">{{ item.body }}</p><b>{{ item.link_label || blockLabel(block, 'item_link_label', 'events_item_link_label', 'Read more') }} →</b></span>
          </a>
        </div>
        <p v-else class="igf-dynamic-empty">{{ blockLabel(block, 'empty_state', 'events_empty_state', 'Upcoming events and field updates will appear here automatically.') }}</p>
      </div>

      <div v-else-if="block.type === 'testimonials'" class="igf-page-block__inner igf-testimonials">
        <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
        <h2>{{ block.content?.heading }}</h2>
        <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
        <div v-if="block.content?.items?.length" class="igf-testimonial-card" aria-live="polite">
          <i class="fa-solid fa-quote-left" aria-hidden="true" />
          <blockquote>{{ activeTestimonial(block).quote }}</blockquote>
          <div class="igf-testimonial-person">
            <img v-if="activeTestimonial(block).photo" :src="activeTestimonial(block).photo" :srcset="responsiveImage(activeTestimonial(block).photo, '88px').webpSrcset || undefined" sizes="88px" :width="responsiveImage(activeTestimonial(block).photo).width" :height="responsiveImage(activeTestimonial(block).photo).height" :alt="activeTestimonial(block).name || ''" loading="lazy" decoding="async">
            <span><strong>{{ activeTestimonial(block).name }}</strong><small>{{ activeTestimonial(block).designation }}</small></span>
          </div>
          <nav v-if="block.content.items.length > 1" :aria-label="shared.testimonials_navigation_label">
            <button type="button" :aria-label="shared.testimonials_previous_label" @click="previousTestimonial(block)"><i class="fa-solid fa-arrow-left" aria-hidden="true" /></button>
            <button
              v-for="(item, index) in block.content.items"
              :key="index"
              type="button"
              :aria-label="testimonialDotLabel(index, block.content.items.length)"
              :aria-current="index === activeTestimonialIndex(block) ? 'true' : null"
              class="igf-testimonial-dot"
              @click="goToTestimonial(block, index)"
            ><span /></button>
            <button type="button" :aria-label="shared.testimonials_next_label" @click="nextTestimonial(block)"><i class="fa-solid fa-arrow-right" aria-hidden="true" /></button>
          </nav>
        </div>
        <p v-else class="igf-dynamic-empty">{{ blockLabel(block, 'empty_state', 'testimonials_empty_state', 'Approved community stories will appear here automatically.') }}</p>
      </div>

      <div v-else-if="block.type === 'cards' && block.content?.variant === 'updates'" class="igf-page-block__inner igf-updates">
        <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
        <h2>{{ block.content?.heading }}</h2>
        <div class="igf-update-columns">
          <div>
            <header><h3>{{ block.content?.events_title || shared.updates_events_title }}</h3><a :href="safeHref(block.content?.events_url || shared.updates_events_url, '/events')">{{ block.content?.events_link_label || shared.updates_events_link_label }}</a></header>
            <a v-for="(item, index) in eventItems(block)" :key="index" class="igf-event-row" :href="safeHref(item.url, '#')">
              <span class="igf-date"><small>{{ eventMonth(item, shared.updates_events_date_fallback) }}</small><strong>{{ eventDay(item) }}</strong></span>
              <span><strong>{{ item.heading }}</strong><small>{{ item.body }}</small></span>
            </a>
          </div>
          <div>
            <header><h3>{{ block.content?.news_title || shared.updates_news_title }}</h3><a :href="safeHref(block.content?.news_url || shared.updates_news_url, '/events')">{{ block.content?.news_link_label || shared.updates_news_link_label }}</a></header>
            <a v-for="(item, index) in newsItems(block)" :key="index" class="igf-news-row" :href="safeHref(item.url, '#')">
              <img v-if="item.image" :src="item.image" :srcset="responsiveImage(item.image, '96px').webpSrcset || undefined" sizes="96px" :width="responsiveImage(item.image).width" :height="responsiveImage(item.image).height" :alt="item.image_alt || ''" loading="lazy" decoding="async">
              <span><small>{{ item.date || item.eyebrow }}</small><strong>{{ item.heading }}</strong></span>
            </a>
          </div>
        </div>
      </div>

      <div v-else-if="block.type === 'cards' && block.content?.variant === 'partners'" class="igf-page-block__inner igf-partners">
        <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
        <div v-if="block.content?.heading" class="igf-partner-heading">
          <h2>{{ block.content.heading }}</h2>
          <span class="igf-partner-underline" aria-hidden="true" />
        </div>
        <ul class="igf-partner-list">
          <li v-for="(item, index) in block.content?.items || []" :key="index">
            <a v-if="safeHref(item.url)" class="igf-partner-card" :href="safeHref(item.url)" target="_blank" rel="noopener noreferrer" :aria-label="partnerLinkLabel(item)">
              <img v-if="item.image" :src="item.image" alt="" width="120" height="80" loading="lazy" decoding="async" @error="$event.currentTarget.hidden = true">
              <strong v-if="item.image" class="igf-partner-card__fallback">{{ partnerName(item) }}</strong>
              <strong v-else>{{ item.heading }}</strong>
            </a>
            <div v-else class="igf-partner-card">
              <img v-if="item.image" :src="item.image" :alt="partnerName(item)" width="120" height="80" loading="lazy" decoding="async" @error="$event.currentTarget.hidden = true">
              <strong v-if="item.image" class="igf-partner-card__fallback">{{ partnerName(item) }}</strong>
              <strong v-else>{{ item.heading }}</strong>
            </div>
          </li>
        </ul>
      </div>

      <div v-else-if="block.type === 'cards' && block.content?.variant === 'initiatives'" class="igf-page-block__inner igf-campus-initiatives">
        <header class="igf-campus-section-heading">
          <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
          <h2>{{ block.content?.heading }}</h2>
          <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
        </header>
        <div v-if="block.content?.items?.length" class="igf-campus-initiative-grid">
          <component
            :is="safeHref(item.url) ? 'a' : 'article'"
            v-for="(item, index) in block.content.items"
            :key="index"
            class="igf-campus-initiative-card"
            :href="safeHref(item.url) || null"
          >
            <div v-if="item.image" class="igf-campus-initiative-card__media">
              <img :src="item.image" :alt="item.image_alt || ''" loading="lazy">
            </div>
            <div v-else class="igf-campus-initiative-card__icon" aria-hidden="true">
              <i :class="iconClass(item.icon)" />
            </div>
            <h3>{{ item.heading }}</h3>
            <p v-if="item.body">{{ item.body }}</p>
          </component>
        </div>
        <p v-else class="igf-dynamic-empty">{{ blockLabel(block, 'empty_state', 'cards_empty_state', 'Published items will appear here automatically.') }}</p>
      </div>

      <div v-else-if="block.type === 'cards' && block.content?.variant === 'contributions'" class="igf-page-block__inner igf-campus-contributions">
        <header class="igf-campus-section-heading">
          <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
          <h2>{{ block.content?.heading }}</h2>
          <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
        </header>
        <div v-if="block.content?.items?.length" class="igf-campus-contribution-grid">
          <article v-for="(item, index) in block.content.items" :key="index" class="igf-campus-contribution-card">
            <div v-if="item.image" class="igf-campus-contribution-card__media">
              <img :src="item.image" :alt="item.image_alt || ''" loading="lazy">
            </div>
            <div v-else class="igf-campus-contribution-card__icon" aria-hidden="true">
              <i :class="iconClass(item.icon)" />
            </div>
            <h3>{{ item.heading }}</h3>
            <ul v-if="campusContributionPoints(item).length">
              <li v-for="(point, pointIndex) in campusContributionPoints(item)" :key="pointIndex">
                <i class="fa-regular fa-circle-check" aria-hidden="true" />
                <span>{{ point }}</span>
              </li>
            </ul>
            <a v-if="safeHref(item.url)" class="igf-campus-contribution-card__link" :href="safeHref(item.url)">
              {{ item.link_label || blockLabel(block, 'item_link_label', 'cards_item_link_label', 'Learn more') }}
            </a>
          </article>
        </div>
        <p v-else class="igf-dynamic-empty">{{ blockLabel(block, 'empty_state', 'cards_empty_state', 'Published items will appear here automatically.') }}</p>
      </div>

      <div v-else-if="block.type === 'cards'" class="igf-page-block__inner">
        <div class="igf-section-heading">
          <div>
            <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
            <h2>{{ block.content?.heading }}</h2>
            <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
          </div>
          <a v-if="safeHref(block.content?.view_all_url)" class="igf-text-link" :href="safeHref(block.content.view_all_url)">{{ blockLabel(block, 'view_all_label', 'cards_view_all_label', 'View all') }} →</a>
        </div>
        <div class="igf-card-grid" :class="{ 'igf-card-grid--four': (block.content?.items || []).length === 4 }">
          <component
            :is="safeHref(item.url) ? 'a' : 'article'"
            v-for="(item, index) in block.content?.items || []"
            :key="index"
            class="igf-card"
            :href="safeHref(item.url) || null"
          >
            <div v-if="item.image" class="igf-card__media">
              <img :src="item.image" :srcset="responsiveImage(item.image, '(max-width: 760px) 100vw, 400px').webpSrcset || undefined" :sizes="responsiveImage(item.image, '(max-width: 760px) 100vw, 400px').sizes" :width="responsiveImage(item.image).width" :height="responsiveImage(item.image).height" :alt="item.image_alt || ''" loading="lazy" decoding="async">
              <span v-if="item.status" class="igf-card__status">{{ item.status }}</span>
            </div>
            <div class="igf-card__content">
              <i v-if="item.icon" :class="iconClass(item.icon)" aria-hidden="true" />
              <small v-if="item.location"><i class="fa-solid fa-location-dot" aria-hidden="true" /> {{ item.location }}</small>
              <small v-else-if="item.eyebrow">{{ item.eyebrow }}</small>
              <h3>{{ item.heading }}</h3>
              <p v-if="item.body">{{ item.body }}</p>
              <span v-if="safeHref(item.url)" class="igf-card__link">{{ item.link_label || blockLabel(block, 'item_link_label', 'cards_item_link_label', 'Learn more') }} →</span>
            </div>
          </component>
        </div>
        <p v-if="!(block.content?.items || []).length" class="igf-dynamic-empty">{{ blockLabel(block, 'empty_state', 'cards_empty_state', 'Published items will appear here automatically.') }}</p>
      </div>

      <div v-else-if="block.type === 'team'" class="igf-page-block__inner">
        <div class="igf-section-heading">
          <div>
            <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
            <h2>{{ block.content?.heading }}</h2>
            <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
          </div>
        </div>
        <div
          v-if="hasTeamTabs(block)"
          class="igf-team-tabs"
          role="tablist"
          aria-orientation="horizontal"
          :aria-label="teamTabsLabel(block)"
          @keydown="handleTeamTabKeydown(block, $event)"
        >
          <button
            v-for="group in teamGroups(block)"
            :id="teamTabId(block, group)"
            :key="group.key"
            type="button"
            class="igf-team-tab"
            :class="{'is-active': isActiveTeamGroup(block, group)}"
            role="tab"
            :aria-selected="isActiveTeamGroup(block, group) ? 'true' : 'false'"
            :aria-controls="teamPanelId(block, group)"
            :tabindex="isActiveTeamGroup(block, group) ? 0 : -1"
            @click="selectTeamGroup(block, group, $event.currentTarget)"
          >
            {{ group.name }}
          </button>
        </div>
        <div
          v-if="teamHasItems(block)"
          :id="teamPanelId(block, activeTeamGroup(block))"
          class="igf-team-panel"
          :role="hasTeamTabs(block) ? 'tabpanel' : null"
          :aria-labelledby="hasTeamTabs(block) ? teamTabId(block, activeTeamGroup(block)) : null"
          :aria-describedby="activeTeamGroup(block)?.description ? teamPanelDescriptionId(block, activeTeamGroup(block)) : null"
          :tabindex="hasTeamTabs(block) ? 0 : null"
        >
          <p
            v-if="activeTeamGroup(block)?.description"
            :id="teamPanelDescriptionId(block, activeTeamGroup(block))"
            class="igf-team-panel__description"
          >
            {{ activeTeamGroup(block).description }}
          </p>
          <div class="igf-team-grid">
          <article
            v-for="(item, index) in visibleTeamItems(block)"
            :key="item.id ?? index"
            class="igf-team-card"
            :class="{
              'has-details': teamHasDetails(item),
              'is-open': isTeamCardOpen(block, item, index),
            }"
            :data-team-card-key="teamCardKey(block, item, index)"
            :aria-label="item.heading || teamText('team_member')"
            @pointerenter="previewTeamCard(block, item, index, $event)"
            @pointerleave="stopTeamCardPreview(block, item, index, $event)"
            @click="handleTeamCardClick(block, item, index, $event)"
            @keydown="handleTeamCardKeydown(block, item, index, $event)"
          >
            <button
              v-if="teamHasDetails(item)"
              type="button"
              class="igf-team-card__toggle"
              :aria-expanded="isTeamCardOpen(block, item, index) ? 'true' : 'false'"
              :aria-controls="teamDetailsId(block, item, index)"
              :aria-label="teamToggleLabel(block, item, index)"
              @click="toggleTeamCard(block, item, index)"
            >
              <span class="sr-only">{{ teamToggleText(block, item, index) }}</span>
            </button>

            <div class="igf-team-card__stage">
              <div class="igf-team-card__flipper">
                <div class="igf-team-card__face igf-team-card__front" :aria-hidden="isTeamCardOpen(block, item, index) ? 'true' : null">
                  <div class="igf-team-card__media">
                    <img
                      v-if="teamImageAvailable(block, item, index)"
                      :src="item.image"
                      :alt="item.image_alt || item.heading || ''"
                      @error="markTeamImageFailed(block, item, index)"
                    >
                    <template v-else>
                      <span class="igf-team-card__initials" aria-hidden="true">{{ initials(item.heading) }}</span>
                      <div class="igf-team-card__fallback-copy">
                        <h3>{{ item.heading }}</h3>
                        <p v-if="teamDesignation(item)">{{ teamDesignation(item) }}</p>
                      </div>
                    </template>
                  </div>
                </div>

                <div
                  v-if="teamHasDetails(item)"
                  :id="teamDetailsId(block, item, index)"
                  class="igf-team-card__face igf-team-card__back"
                  :aria-hidden="isTeamCardOpen(block, item, index) ? null : 'true'"
                  :inert="isTeamCardOpen(block, item, index) ? null : true"
                >
                  <div class="igf-team-card__back-content">
                    <div class="igf-team-card__back-heading">
                      <h3>{{ item.heading }}</h3>
                      <p v-if="teamDesignation(item)">{{ teamDesignation(item) }}</p>
                    </div>
                    <p v-if="item.biography" class="igf-team-card__biography">
                      <span class="sr-only">{{ teamText('biography') }}: </span>{{ item.biography }}
                    </p>
                    <p v-if="item.qualification" class="igf-team-card__qualification">
                      <span><span class="sr-only">{{ teamText('qualification') }}: </span>{{ item.qualification }}</span>
                    </p>
                  </div>
                  <ul
                    v-if="teamSocialLinks(item).length"
                    class="igf-team-card__socials"
                    :class="{'is-scrollable': teamSocialLinks(item).length > 1}"
                    :aria-label="teamText('social_links')"
                  >
                    <li v-for="(link, linkIndex) in teamSocialLinks(item)" :key="`${link.platform}-${link.url}-${linkIndex}`">
                      <a
                        class="igf-team-card__social-link"
                        :href="link.url"
                        :target="link.external ? '_blank' : null"
                        :rel="link.external ? 'noopener noreferrer' : null"
                        :tabindex="isTeamCardOpen(block, item, index) ? null : -1"
                        :aria-label="teamSocialAriaLabel(link, item)"
                      >
                        <i :class="teamSocialIcon(link.platform)" aria-hidden="true" />
                        <span>{{ teamSocialCtaLabel(link) }}</span>
                      </a>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </article>
        </div>
        </div>
        <p v-else class="igf-dynamic-empty">{{ blockLabel(block, 'empty_state', 'team_empty_state', 'Published board and team members will appear here automatically.') }}</p>
      </div>

      <div v-else-if="block.type === 'partners'" class="igf-page-block__inner igf-partners">
        <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
        <div v-if="block.content?.heading" class="igf-partner-heading">
          <h2>{{ block.content.heading }}</h2>
          <span class="igf-partner-underline" aria-hidden="true" />
        </div>
        <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
        <ul class="igf-partner-list">
          <li v-for="(item, index) in block.content?.items || []" :key="index">
            <a v-if="safeHref(item.url)" class="igf-partner-card" :href="safeHref(item.url)" target="_blank" rel="noopener noreferrer" :aria-label="partnerLinkLabel(item)">
              <img v-if="item.image" :src="item.image" alt="" width="120" height="80" loading="lazy" decoding="async" @error="$event.currentTarget.hidden = true">
              <strong v-if="item.image" class="igf-partner-card__fallback">{{ partnerName(item) }}</strong>
              <strong v-else>{{ item.heading }}</strong>
            </a>
            <div v-else class="igf-partner-card">
              <img v-if="item.image" :src="item.image" :alt="partnerName(item)" width="120" height="80" loading="lazy" decoding="async" @error="$event.currentTarget.hidden = true">
              <strong v-if="item.image" class="igf-partner-card__fallback">{{ partnerName(item) }}</strong>
              <strong v-else>{{ item.heading }}</strong>
            </div>
          </li>
        </ul>
      </div>

      <div v-else-if="block.type === 'faq'" class="igf-page-block__inner igf-faq">
        <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
        <h2>{{ block.content?.heading }}</h2>
        <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
        <div class="igf-faq__items">
          <details v-for="(item, index) in block.content?.items || []" :key="index">
            <summary>{{ item.heading }} <i class="fa-solid fa-plus" aria-hidden="true" /></summary>
            <div class="igf-page-block__copy" v-html="item.body" />
          </details>
        </div>
      </div>

      <div v-else-if="block.type === 'timeline'" class="igf-page-block__inner igf-timeline">
        <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
        <h2>{{ block.content?.heading }}</h2>
        <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
        <ol>
          <li v-for="(item, index) in block.content?.items || []" :key="index">
            <span>{{ item.eyebrow || String(index + 1).padStart(2, '0') }}</span>
            <div><h3>{{ item.heading }}</h3><div v-if="item.body" class="igf-page-block__copy" v-html="item.body" /></div>
          </li>
        </ol>
      </div>

      <div v-else-if="block.type === 'gallery' && block.content?.variant === 'campus-gallery'" class="igf-page-block__inner igf-gallery igf-campus-gallery">
        <header class="igf-campus-section-heading">
          <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
          <h2>{{ block.content?.heading }}</h2>
          <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
        </header>
        <div
          v-if="gallerySlides(block).length"
          class="igf-campus-gallery__carousel"
          role="region"
          :aria-roledescription="shared.gallery_carousel_roledescription"
          :aria-label="block.content?.heading || shared.gallery_carousel_label"
          tabindex="0"
          @keydown.left.prevent="previousGallery(block)"
          @keydown.right.prevent="nextGallery(block)"
          @touchstart.passive="rememberGalleryTouch(block, $event)"
          @touchend.passive="finishGalleryTouch(block, $event)"
        >
          <div class="igf-campus-gallery__viewport">
            <div class="igf-campus-gallery__track" :style="galleryTrackStyle(block)">
              <div
                v-for="(slide, slideIndex) in gallerySlides(block)"
                :key="`${block.uuid}-gallery-slide-${slideIndex}`"
                class="igf-campus-gallery__slide"
                :class="[
                  `igf-campus-gallery__slide--count-${slide.length}`,
                  { 'is-short': slide.length === 4 },
                ]"
                :aria-hidden="slideIndex === activeGalleryIndex(block) ? null : 'true'"
                :inert="slideIndex === activeGalleryIndex(block) ? null : true"
              >
                <figure v-for="(item, index) in slide" :key="index">
                  <button
                    type="button"
                    class="igf-campus-gallery__lightbox-trigger"
                    :aria-label="galleryOpenImageLabel(item, slideIndex * 5 + index)"
                    :data-gallery-block-uuid="block.uuid"
                    :data-gallery-item-index="slideIndex * 5 + index"
                    :tabindex="slideIndex === activeGalleryIndex(block) ? null : -1"
                    @click="openGalleryLightbox(block, slideIndex * 5 + index, $event)"
                  ><img :src="item.image" :alt="item.image_alt || item.heading || ''" loading="lazy"></button>
                  <figcaption v-if="item.heading" class="sr-only">{{ item.heading }}</figcaption>
                </figure>
              </div>
            </div>
          </div>
          <template v-if="gallerySlides(block).length > 1">
            <button
              type="button"
              class="igf-campus-gallery__arrow igf-campus-gallery__arrow--previous"
              :aria-label="shared.gallery_previous_slide_label"
              :disabled="activeGalleryIndex(block) === 0"
              @click="previousGallery(block)"
            ><i class="fa-solid fa-arrow-left" aria-hidden="true" /></button>
            <button
              type="button"
              class="igf-campus-gallery__arrow igf-campus-gallery__arrow--next"
              :aria-label="shared.gallery_next_slide_label"
              :disabled="activeGalleryIndex(block) === gallerySlides(block).length - 1"
              @click="nextGallery(block)"
            ><i class="fa-solid fa-arrow-right" aria-hidden="true" /></button>
            <nav class="igf-campus-gallery__dots" :aria-label="block.content?.controls_label || shared.gallery_controls_label">
              <button
                v-for="(_, slideIndex) in gallerySlides(block)"
                :key="`${block.uuid}-gallery-dot-${slideIndex}`"
                type="button"
                :aria-label="galleryDotLabel(slideIndex, gallerySlides(block).length)"
                :aria-current="slideIndex === activeGalleryIndex(block) ? 'true' : null"
                @click="goToGallery(block, slideIndex)"
              ><span /></button>
            </nav>
          </template>
        </div>
        <div
          v-if="galleryLightbox?.blockUuid === block.uuid"
          class="igf-campus-lightbox"
          role="presentation"
          @click.self="closeGalleryLightbox"
        >
          <div
            class="igf-campus-lightbox__dialog"
            role="dialog"
            aria-modal="true"
            :aria-label="activeGalleryLightboxItem(block)?.heading || block.content?.heading || shared.gallery_image_label"
            tabindex="-1"
            @keydown.esc.stop.prevent="closeGalleryLightbox"
            @keydown.left.prevent="moveGalleryLightbox(block, -1)"
            @keydown.right.prevent="moveGalleryLightbox(block, 1)"
            @keydown.tab="trapGalleryLightboxFocus"
          >
            <button type="button" class="igf-campus-lightbox__close" :aria-label="shared.gallery_close_image_label" @click="closeGalleryLightbox"><i class="fa-solid fa-xmark" aria-hidden="true" /></button>
            <button
              type="button"
              class="igf-campus-lightbox__nav igf-campus-lightbox__nav--previous"
              :aria-label="shared.gallery_previous_image_label"
              :disabled="galleryLightbox.index === 0"
              @click="moveGalleryLightbox(block, -1)"
            ><i class="fa-solid fa-arrow-left" aria-hidden="true" /></button>
            <figure>
              <img :src="activeGalleryLightboxItem(block)?.image" :alt="activeGalleryLightboxItem(block)?.image_alt || activeGalleryLightboxItem(block)?.heading || ''">
              <figcaption v-if="activeGalleryLightboxItem(block)?.heading">{{ activeGalleryLightboxItem(block).heading }}</figcaption>
            </figure>
            <button
              type="button"
              class="igf-campus-lightbox__nav igf-campus-lightbox__nav--next"
              :aria-label="shared.gallery_next_image_label"
              :disabled="galleryLightbox.index === campusGalleryItems(block).length - 1"
              @click="moveGalleryLightbox(block, 1)"
            ><i class="fa-solid fa-arrow-right" aria-hidden="true" /></button>
          </div>
        </div>
        <p v-if="!gallerySlides(block).length" class="igf-dynamic-empty">{{ blockLabel(block, 'empty_state', 'gallery_empty_state', 'Published gallery photos will appear here automatically.') }}</p>
      </div>

      <div v-else-if="block.type === 'gallery'" class="igf-page-block__inner igf-gallery">
        <div class="igf-section-heading">
          <div>
            <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
            <h2>{{ block.content?.heading }}</h2>
            <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
          </div>
          <a v-if="blockLink(block, 'gallery')" class="igf-text-link" :href="blockLink(block, 'gallery')">{{ blockLabel(block, 'view_all_label', 'gallery_view_all_label') }} →</a>
        </div>
        <div v-if="block.content?.items?.length" class="igf-gallery__grid">
          <figure v-for="(item, index) in block.content.items" :key="index">
            <a v-if="safeHref(item.url)" :href="safeHref(item.url)"><img :src="item.image" :alt="item.image_alt || item.heading || ''" loading="lazy"></a>
            <img v-else :src="item.image" :alt="item.image_alt || item.heading || ''" loading="lazy">
            <figcaption v-if="item.heading">{{ item.heading }}</figcaption>
          </figure>
        </div>
        <p v-else class="igf-dynamic-empty">{{ blockLabel(block, 'empty_state', 'gallery_empty_state', 'Published gallery photos will appear here automatically.') }}</p>
      </div>

      <div v-else-if="block.type === 'video'" class="igf-page-block__inner igf-video">
        <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
        <h2>{{ block.content?.heading }}</h2>
        <p v-if="block.content?.body" class="igf-section-lead">{{ block.content.body }}</p>
        <div v-if="safeMediaHref(block.content?.video_url)" class="igf-video__frame">
          <iframe v-if="videoEmbedUrl(block.content.video_url)" :src="videoEmbedUrl(block.content.video_url)" :title="block.content.heading || shared.video_embed_title" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen />
          <video v-else :src="safeMediaHref(block.content.video_url)" :poster="safeMediaHref(block.content.poster) || null" :aria-label="block.content.heading || shared.video_embed_title" controls preload="metadata">{{ shared.video_unsupported_message }}</video>
        </div>
        <p v-if="block.content?.caption" class="igf-video__caption">{{ block.content.caption }}</p>
      </div>

      <div v-else-if="block.type === 'cta' && block.content?.variant === 'campus-actions'" class="igf-page-block__inner igf-callout igf-campus-actions">
        <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
        <h2>{{ block.content?.heading }}</h2>
        <p class="igf-section-lead">{{ block.content?.body }}</p>
        <div class="igf-page-block__actions">
          <a v-if="block.content?.primary_label" class="igf-button igf-button--outline" :href="safeHref(block.content.primary_url, '#')">{{ block.content.primary_label }}</a>
          <a v-if="block.content?.secondary_label" class="igf-button igf-button--primary" :href="safeHref(block.content.secondary_url, '#')">{{ block.content.secondary_label }}</a>
        </div>
      </div>

      <div v-else-if="block.type === 'cta' && block.content?.variant === 'campaign'" class="igf-page-block__inner">
        <div class="igf-campaign">
          <div class="igf-campaign__story">
            <p class="igf-page-block__eyebrow">{{ block.content?.eyebrow }}</p>
            <h2>{{ block.content?.heading }}</h2>
            <p>{{ block.content?.body }}</p>
          </div>
          <form class="igf-campaign__form" method="get" :action="safeHref(block.content?.primary_url, '/donate')">
            <div class="igf-campaign__form-header">
              <h3>{{ block.content?.form_title || shared.campaign_form_title }}</h3>
              <p>{{ donationSettings.gift_subtitle }}</p>
            </div>
            <fieldset class="igf-campaign-frequency">
              <legend class="sr-only">{{ donationSettings.frequency_accessible_label }}</legend>
              <div class="igf-campaign-frequency__tabs" role="radiogroup" :aria-label="donationSettings.frequency_accessible_label" aria-describedby="campaign-frequency-note">
                <label class="is-selected"><input type="radio" name="frequency" value="one_time" checked><span>{{ donationSettings.frequency_label || 'One-time' }}</span></label>
                <button type="button" role="radio" aria-checked="false" disabled><span>{{ donationSettings.frequency_daily_label || 'Daily' }}</span><small>{{ donationSettings.frequency_coming_soon_label }}</small></button>
                <button type="button" role="radio" aria-checked="false" disabled><span>{{ donationSettings.frequency_weekly_label || 'Weekly' }}</span><small>{{ donationSettings.frequency_coming_soon_label }}</small></button>
                <button type="button" role="radio" aria-checked="false" disabled><span>{{ donationSettings.frequency_monthly_label || 'Monthly' }}</span><small>{{ donationSettings.frequency_coming_soon_label }}</small></button>
              </div>
              <p id="campaign-frequency-note"><i class="fa-solid fa-circle-info" aria-hidden="true" />{{ donationSettings.frequency_help }}</p>
            </fieldset>
            <div class="igf-amounts">
              <label v-for="(option, index) in campaignAmountOptions" :key="option.amount" :class="{ 'is-featured': option.featured }"><input type="radio" name="amount" :value="option.amount" :checked="index === Math.min(1, campaignAmountOptions.length - 1)"><span><strong>{{ money(option.amount) }}</strong><small v-if="option.impact">{{ option.impact }}</small><i class="fa-solid fa-check" aria-hidden="true" /></span></label>
            </div>
            <label v-if="showCampaignCustomAmount" class="igf-custom-amount"><span class="sr-only">{{ block.content?.custom_amount_label || shared.campaign_custom_amount_label }}</span><b>৳</b><input name="custom_amount" min="10" max="500000" step="0.01" type="number" :placeholder="block.content?.custom_amount_placeholder || shared.campaign_custom_amount_placeholder"></label>
            <button class="igf-button igf-button--primary" type="submit">{{ block.content?.primary_label || shared.campaign_submit_label }}</button>
            <p v-if="donationSettings.accountability_label" class="igf-campaign__assurance"><span aria-hidden="true" />{{ donationSettings.accountability_label }}</p>
            <p v-if="donationSettings.gateway_heading" class="igf-campaign__secure"><i class="fa-solid fa-lock" aria-hidden="true" />{{ donationSettings.gateway_heading }}</p>
          </form>
        </div>
      </div>

      <div v-else-if="block.type === 'cta'" class="igf-page-block__inner igf-callout">
        <i class="fa-solid fa-quote-left" aria-hidden="true" />
        <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
        <h2>{{ block.content?.heading }}</h2>
        <p class="igf-section-lead">{{ block.content?.body }}</p>
        <div class="igf-page-block__actions">
          <a v-if="block.content?.primary_label" class="igf-button igf-button--primary" :href="safeHref(block.content.primary_url, '#')">{{ block.content.primary_label }}</a>
          <a v-if="block.content?.secondary_label" class="igf-button igf-button--outline" :href="safeHref(block.content.secondary_url, '#')">{{ block.content.secondary_label }}</a>
        </div>
      </div>

      <div v-else-if="block.type === 'newsletter'" class="igf-page-block__inner igf-newsletter">
        <h2>{{ block.content?.heading }}</h2>
        <p>{{ block.content?.body }}</p>
        <form @submit.prevent="subscribe">
          <label class="sr-only" :for="newsletterEmailId(block)">{{ block.content?.email_label || shared.newsletter_email_label }}</label>
          <input :id="newsletterEmailId(block)" v-model="newsletterEmail" name="email" type="email" autocomplete="email" :placeholder="block.content?.email_placeholder || shared.newsletter_email_placeholder" required :aria-invalid="newsletterFeedbackType === 'error' ? 'true' : undefined" :aria-describedby="newsletterMessage ? newsletterMessageId(block) : undefined">
          <button type="submit" :disabled="newsletterBusy">{{ newsletterBusy ? shared.newsletter_subscribing_label : (block.content?.button_label || shared.newsletter_subscribe_label) }}</button>
          <label class="igf-consent"><input v-model="newsletterConsent" name="consent" type="checkbox" required> <span>{{ block.content?.consent_text || shared.newsletter_consent_prefix }} <a :href="safeHref(block.content?.privacy_url || shared.newsletter_privacy_url, '/page/privacy-policy')">{{ block.content?.privacy_label || shared.newsletter_privacy_label }}</a>.</span></label>
          <p
            v-if="newsletterMessage"
            :id="newsletterMessageId(block)"
            class="igf-newsletter__message"
            :class="`is-${newsletterFeedbackType}`"
            :role="newsletterFeedbackType === 'error' ? 'alert' : 'status'"
          >{{ newsletterMessage }}</p>
        </form>
      </div>

      <div v-else-if="block.type === 'spacer'" aria-hidden="true" />
      <div v-else-if="block.type === 'custom_html'" class="igf-page-block__inner igf-page-block__copy" v-html="block.content?.html" />

      <div v-else class="igf-page-block__inner igf-rich-text">
        <p v-if="block.content?.eyebrow" class="igf-page-block__eyebrow">{{ block.content.eyebrow }}</p>
        <h2 v-if="block.content?.heading">{{ block.content.heading }}</h2>
        <div v-if="block.content?.body" class="igf-page-block__copy" v-html="block.content.body" />
        <div v-if="block.content?.items?.length" class="igf-accountability-grid">
          <a v-for="(item, index) in block.content.items" :key="index" :href="safeHref(item.url, '#')">
            <i :class="iconClass(item.icon)" aria-hidden="true" /><strong>{{ item.heading }}</strong>
          </a>
        </div>
        <a v-if="block.content?.primary_label" class="igf-button igf-button--primary" :href="safeHref(block.content.primary_url, '#')">{{ block.content.primary_label }}</a>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { formatDate, formatMoney, formatNumber, interpolateSetting } from './composables/siteSettings';
import { responsiveBackgroundPresentation, responsiveImagePresentation } from './composables/responsiveImage';

const props = defineProps({ blocks: { type: Array, default: () => [] } });
const page = usePage();
const shared = computed(() => page.props.siteSettings?.shared_blocks || {});
const regional = computed(() => page.props.siteSettings?.regional || {});
const donationSettings = computed(() => page.props.siteSettings?.donation_page || {});
const campaignAmountOptions = computed(() => {
  const count = Math.min(5, Math.max(2, Number(donationSettings.value.amount_button_count) || 5));
  return [1, 2, 3, 4, 5]
    .map(index => ({
      amount: Number(donationSettings.value[`amount_${index}`]),
      impact: String(donationSettings.value[`amount_${index}_impact`] || ''),
      featured: Number(donationSettings.value.featured_amount_index || 4) === index,
    }))
    .filter(option => Number.isFinite(option.amount) && option.amount > 0)
    .slice(0, count);
});
const showCampaignCustomAmount = computed(() => donationSettings.value.show_custom_amount !== false);

const pageBlocksRoot = ref(null);
const statDisplayValues = ref({});
const newsletterEmail = ref('');
const newsletterConsent = ref(false);
const newsletterBusy = ref(false);
const newsletterMessage = ref('');
const newsletterFeedbackType = ref('');
const heroIndexes = ref({});
const heroUserPaused = ref({});
const heroInteractionPaused = ref({});
const testimonialIndexes = ref({});
const galleryIndexes = ref({});
const galleryLightbox = ref(null);
const openTeamCardKey = ref('');
const hoverTeamCardKey = ref('');
const viewportTeamCardKey = ref('');
const failedTeamImageKeys = ref(new Set());
const activeTeamGroupKeys = ref({});
const heroTouchStarts = new Map();
const galleryTouchStarts = new Map();
const heroLastAdvanced = new Map();
let heroClock = null;
let statObserver = null;
let teamObserver = null;
let focusAreaObserver = null;
const statAnimationFrames = new Set();

const iconMap = {
  people: 'fa-solid fa-people-group', map: 'fa-solid fa-map-location-dot', heart: 'fa-solid fa-hand-holding-heart',
  school: 'fa-solid fa-graduation-cap', health: 'fa-solid fa-heart-pulse', water: 'fa-solid fa-droplet',
  leaf: 'fa-solid fa-seedling', relief: 'fa-solid fa-kit-medical', child: 'fa-solid fa-child-reaching',
  report: 'fa-regular fa-file-lines', financials: 'fa-solid fa-landmark', security: 'fa-solid fa-shield-halved', policy: 'fa-solid fa-scale-balanced',
};
const teamSettingKeys = Object.freeze({
  biography: 'team_biography_label',
  hide_details: 'team_hide_details_label',
  hide_details_for: 'team_hide_details_for_label',
  keep_details_open: 'team_keep_details_open_label',
  keep_details_open_for: 'team_keep_details_open_for_label',
  opens_new_tab: 'team_external_link_suffix',
  qualification: 'team_qualification_label',
  social_links: 'team_social_links_label',
  team_member: 'team_member_label',
  view_details: 'team_view_details_label',
  view_details_for: 'team_view_details_for_label',
  view_profile: 'team_item_link_label',
  linkedin: 'team_linkedin_label',
  website: 'team_website_label',
});

function iconClass(icon) { return iconMap[icon] || 'fa-solid fa-circle-dot'; }
function initials(name = '') { return String(name).split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase(); }
function blockLabel(block, contentKey, settingsKey, fallback = '') {
  return block.content?.[contentKey] || shared.value[settingsKey] || fallback;
}
function blockLink(block, type) {
  const configured = block.content?.view_all_url || shared.value[`${type}_view_all_url`];
  return safeHref(configured);
}
function partnerName(item = {}) {
  return String(item.image_alt || item.heading || 'Partner organization').trim();
}
function partnerLinkLabel(item) {
  return interpolateSetting(
    shared.value.partner_external_link_label || '{name}, opens in a new tab',
    { name: partnerName(item) },
  );
}
function teamText(key) {
  return shared.value[teamSettingKeys[key]] || '';
}
function heroDotLabel(index, total) {
  return interpolateSetting(shared.value.hero_show_slide_label, { current: index + 1, total });
}
function heroStatusLabel(block) {
  return interpolateSetting(shared.value.hero_status_label, {
    current: activeHeroIndex(block) + 1,
    total: heroSlides(block).length,
    heading: activeHeroSlide(block).heading,
  });
}
function galleryOpenImageLabel(item, index) {
  return interpolateSetting(shared.value.gallery_open_image_label, {
    name: item?.heading || item?.image_alt || index + 1,
  });
}
function galleryDotLabel(index, total) {
  return interpolateSetting(shared.value.gallery_show_slide_label, { current: index + 1, total });
}
function testimonialDotLabel(index, total) {
  return interpolateSetting(shared.value.testimonials_show_label || 'Show story {current} of {total}', { current: index + 1, total });
}
function safeHref(value, fallback = '') {
  if (typeof value !== 'string') return fallback;
  const href = value.trim().replace(/[\p{Cc}\p{Cf}\s]+/gu, '');
  if (!href) return fallback;
  if (href.startsWith('//')) return `https:${href}`;
  if (href.startsWith('#') || href.startsWith('/')) return href;

  const scheme = href.match(/^([a-z][a-z0-9+.-]*):/i)?.[1]?.toLowerCase();
  if (scheme && !['http', 'https', 'mailto', 'tel'].includes(scheme)) return fallback;

  return href;
}

function givingItems(block) {
  const items = Array.isArray(block?.content?.items) ? block.content.items : [];
  return block?.content?.layout === 'single_cta' ? items.slice(0, 1) : items;
}

function givingAriaLabel(item) {
  const destination = String(item?.destination || '').trim();
  const heading = String(item?.heading || 'Giving option').trim();
  return destination ? `${heading}. ${destination}` : heading;
}
function campusContributionPoints(item) {
  const values = Array.isArray(item?.features)
    ? item.features
    : String(item?.body || '').split(/\r?\n/);
  return values
    .map(value => String(value || '').replace(/^\s*[-•✓]+\s*/, '').trim())
    .filter(Boolean);
}
function teamDomToken(value, fallback) {
  const token = String(value ?? '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9_-]+/g, '-')
    .replace(/^-+|-+$/g, '');
  return token || fallback;
}
function teamBlockStateKey(block) {
  return String(block?.uuid ?? block?.id ?? teamDomToken(block?.content?.heading, 'team'));
}
function teamBlockDomToken(block) {
  return teamDomToken(block?.uuid ?? block?.id ?? block?.content?.heading, 'team');
}
function teamGroups(block) {
  const rawGroups = Array.isArray(block?.content?.groups) ? block.content.groups : [];
  const populatedGroups = rawGroups.map((group, index) => {
    const items = (Array.isArray(group?.items) ? group.items : [])
      .filter(item => item && typeof item === 'object' && !Array.isArray(item));
    const name = String(group?.name || group?.label || group?.slug || '').trim()
      || `${teamText('team_member') || 'Team'} ${index + 1}`;
    const baseKey = teamDomToken(group?.slug ?? group?.id ?? name, 'group');
    return {
      key: `${baseKey}-${index + 1}`,
      name,
      description: String(group?.description || '').trim(),
      items,
    };
  }).filter(group => group.items.length);

  if (populatedGroups.length) return populatedGroups;

  const legacyItems = (Array.isArray(block?.content?.items) ? block.content.items : [])
    .filter(item => item && typeof item === 'object' && !Array.isArray(item));
  if (!legacyItems.length) return [];

  return [{
    key: 'all-1',
    name: String(block?.content?.heading || teamText('team_member') || 'Team').trim(),
    description: '',
    items: legacyItems,
  }];
}
function activeTeamGroup(block) {
  const groups = teamGroups(block);
  const selectedKey = activeTeamGroupKeys.value[teamBlockStateKey(block)];
  return groups.find(group => group.key === selectedKey) || groups[0] || null;
}
function visibleTeamItems(block) {
  return activeTeamGroup(block)?.items || [];
}
function teamHasItems(block) {
  return teamGroups(block).some(group => group.items.length);
}
function hasTeamTabs(block) {
  return teamGroups(block).length > 1;
}
function isActiveTeamGroup(block, group) {
  return activeTeamGroup(block)?.key === group?.key;
}
function teamTabsLabel(block) {
  return String(block?.content?.tabs_label || block?.content?.heading || teamText('team_member') || 'Team').trim();
}
function teamTabId(block, group) {
  return `team-tab-${teamBlockDomToken(block)}-${teamDomToken(group?.key, 'group')}`;
}
function teamPanelId(block, group) {
  return `team-panel-${teamBlockDomToken(block)}-${teamDomToken(group?.key, 'group')}`;
}
function teamPanelDescriptionId(block, group) {
  return `${teamPanelId(block, group)}-description`;
}
function selectTeamGroup(block, group, tabElement = null) {
  const selected = teamGroups(block).find(candidate => candidate.key === group?.key);
  if (!selected) return;

  teamObserver?.disconnect();
  teamObserver = null;
  openTeamCardKey.value = '';
  hoverTeamCardKey.value = '';
  viewportTeamCardKey.value = '';
  activeTeamGroupKeys.value = {
    ...activeTeamGroupKeys.value,
    [teamBlockStateKey(block)]: selected.key,
  };

  nextTick(() => {
    tabElement?.focus?.();
    tabElement?.scrollIntoView?.({ block: 'nearest', inline: 'center' });
    setupTeamCardViewportAnimations();
  });
}
function handleTeamTabKeydown(block, event) {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
  const groups = teamGroups(block);
  if (groups.length < 2) return;

  const activeIndex = Math.max(0, groups.findIndex(group => isActiveTeamGroup(block, group)));
  let nextIndex = activeIndex;
  if (event.key === 'Home') nextIndex = 0;
  else if (event.key === 'End') nextIndex = groups.length - 1;
  else if (event.key === 'ArrowRight') nextIndex = (activeIndex + 1) % groups.length;
  else nextIndex = (activeIndex - 1 + groups.length) % groups.length;

  event.preventDefault();
  const tabs = [...(event.currentTarget?.querySelectorAll('.igf-team-tab') || [])];
  selectTeamGroup(block, groups[nextIndex], tabs[nextIndex] || null);
}
function teamCardKey(block, item, index) {
  return `${String(block.uuid || 'team')}:${String(item.id ?? index)}:${index}`;
}
function teamImageKey(block, item, index) {
  return `${teamCardKey(block, item, index)}:${String(item.image || '').trim()}`;
}
function teamImageAvailable(block, item, index) {
  return Boolean(String(item.image || '').trim()) && !failedTeamImageKeys.value.has(teamImageKey(block, item, index));
}
function markTeamImageFailed(block, item, index) {
  failedTeamImageKeys.value = new Set([...failedTeamImageKeys.value, teamImageKey(block, item, index)]);
}
function teamDetailsId(block, item, index) {
  const token = `${String(block.uuid || 'team')}-${String(item.id ?? index)}-${index}`
    .toLowerCase()
    .replace(/[^a-z0-9_-]+/g, '-')
    .replace(/^-+|-+$/g, '');
  return `team-member-details-${token || index}`;
}
function isTeamCardPinned(block, item, index) {
  return openTeamCardKey.value === teamCardKey(block, item, index);
}
function isTeamCardHovered(block, item, index) {
  return hoverTeamCardKey.value === teamCardKey(block, item, index);
}
function isTeamCardOpen(block, item, index) {
  if (!teamHasDetails(item)) return false;
  const activeKey = hoverTeamCardKey.value || openTeamCardKey.value || viewportTeamCardKey.value;
  return activeKey === teamCardKey(block, item, index);
}
function focusedTeamCardKey() {
  if (typeof document === 'undefined') return '';
  return document.activeElement?.closest?.('[data-team-card-key]')?.dataset.teamCardKey || '';
}
function preserveFocusedTeamCard(nextKey) {
  const activeKey = hoverTeamCardKey.value || openTeamCardKey.value || viewportTeamCardKey.value;
  if (!activeKey || activeKey === nextKey || focusedTeamCardKey() !== activeKey) return false;
  openTeamCardKey.value = activeKey;
  hoverTeamCardKey.value = '';
  viewportTeamCardKey.value = '';
  return true;
}
function previewTeamCard(block, item, index, event) {
  if (!teamHasDetails(item) || (event.pointerType && event.pointerType !== 'mouse')) return;
  const key = teamCardKey(block, item, index);
  if (preserveFocusedTeamCard(key)) return;
  if (openTeamCardKey.value && openTeamCardKey.value !== key) openTeamCardKey.value = '';
  viewportTeamCardKey.value = '';
  hoverTeamCardKey.value = key;
}
function stopTeamCardPreview(block, item, index, event) {
  const key = teamCardKey(block, item, index);
  if (hoverTeamCardKey.value !== key) return;
  if (focusedTeamCardKey() === key && event?.currentTarget?.contains(document.activeElement)) openTeamCardKey.value = key;
  hoverTeamCardKey.value = '';
}
function toggleTeamCard(block, item, index) {
  if (!teamHasDetails(item)) return;
  const key = teamCardKey(block, item, index);
  openTeamCardKey.value = openTeamCardKey.value === key ? '' : key;
  hoverTeamCardKey.value = '';
  viewportTeamCardKey.value = '';
}
function handleTeamCardClick(block, item, index, event) {
  if (!teamHasDetails(item) || event.target?.closest?.('a, button')) return;
  toggleTeamCard(block, item, index);
}
function closeTeamCard(block, item, index, event) {
  if (!isTeamCardOpen(block, item, index)) return;
  const card = event?.currentTarget;
  const key = teamCardKey(block, item, index);
  if (openTeamCardKey.value === key) openTeamCardKey.value = '';
  if (hoverTeamCardKey.value === key) hoverTeamCardKey.value = '';
  if (viewportTeamCardKey.value === key) viewportTeamCardKey.value = '';
  nextTick(() => card?.querySelector('.igf-team-card__toggle')?.focus());
}
function handleTeamCardKeydown(block, item, index, event) {
  if (!['Escape', 'Esc'].includes(event.key) || !isTeamCardOpen(block, item, index)) return;
  event.preventDefault();
  event.stopPropagation();
  closeTeamCard(block, item, index, event);
}
function teamToggleText(block, item, index) {
  if (isTeamCardPinned(block, item, index)) return teamText('hide_details');
  if (isTeamCardHovered(block, item, index) || viewportTeamCardKey.value === teamCardKey(block, item, index)) return teamText('keep_details_open');
  return teamText('view_details');
}
function teamToggleLabel(block, item, index) {
  let key = 'view_details_for';
  if (isTeamCardPinned(block, item, index)) key = 'hide_details_for';
  else if (isTeamCardHovered(block, item, index) || viewportTeamCardKey.value === teamCardKey(block, item, index)) key = 'keep_details_open_for';
  return interpolateSetting(teamText(key), { name: item.heading || teamText('team_member') });
}
function teamDesignation(item) {
  return item.designation || item.body || '';
}
function teamHasDetails(item) {
  const hasProfileHeading = Boolean(String(item.heading || '').trim() && String(teamDesignation(item)).trim());
  return Boolean(hasProfileHeading || String(item.biography || '').trim() || String(item.qualification || '').trim() || teamSocialLinks(item).length);
}
function safeExternalHref(value) {
  const href = safeHref(value);
  return /^https?:\/\//i.test(href) ? href : '';
}
function teamSocialPlatform(link) {
  const requested = String(link?.platform || '').trim().toLowerCase();
  const aliases = { twitter: 'x', personal: 'website', web: 'website' };
  if (requested) return aliases[requested] || requested;

  const url = String(link?.url || '').toLowerCase();
  return ['linkedin', 'facebook', 'instagram', 'youtube', 'github'].find(platform => url.includes(platform))
    || (url.includes('x.com') || url.includes('twitter.com') ? 'x' : 'website');
}
function teamSocialLabel(link, platform) {
  const labels = { linkedin: 'LinkedIn', facebook: 'Facebook', instagram: 'Instagram', youtube: 'YouTube', github: 'GitHub', x: 'X', website: teamText('website') };
  return String(link?.label || '').trim() || labels[platform] || `${platform.charAt(0).toUpperCase()}${platform.slice(1)}`;
}
function teamSocialLinks(item) {
  const links = Array.isArray(item.social_links) ? item.social_links : [];
  const socials = links.map(link => {
    const platform = teamSocialPlatform(link);
    return {
      platform,
      url: safeExternalHref(link?.url),
      label: teamSocialLabel(link, platform),
      external: true,
      profile: false,
    };
  }).filter(link => link.url);
  if (socials.length) return socials;

  const legacyUrl = safeHref(item.url);
  if (!legacyUrl) return [];
  return [{
    platform: 'website',
    url: legacyUrl,
    label: String(item.link_label || '').trim() || teamText('view_profile'),
    external: /^https?:\/\//i.test(legacyUrl),
    profile: true,
  }];
}
function teamSocialAriaLabel(link, item) {
  const label = link.profile
    ? interpolateSetting(shared.value.team_profile_accessible_label, { name: item?.heading || teamText('team_member') })
    : link.label;
  return link.external ? `${label} (${teamText('opens_new_tab')})` : label;
}
function teamSocialCtaLabel(link) {
  return link.platform === 'linkedin' ? teamText('linkedin') : link.label;
}
function teamSocialIcon(platform) {
  const icons = {
    linkedin: 'fa-brands fa-linkedin-in', facebook: 'fa-brands fa-facebook-f', instagram: 'fa-brands fa-instagram',
    youtube: 'fa-brands fa-youtube', github: 'fa-brands fa-github', x: 'fa-brands fa-x-twitter',
    website: 'fa-solid fa-arrow-up-right-from-square',
  };
  return icons[platform] || 'fa-solid fa-link';
}
function parsedExternalHttpUrl(value = '') {
  const raw = String(value || '').trim();
  if (!raw) return null;
  const candidate = raw.startsWith('//')
    ? `https:${raw}`
    : (/^https?:\/\//i.test(raw) ? raw : `https://${raw}`);
  try {
    const parsed = new URL(candidate);
    return ['http:', 'https:'].includes(parsed.protocol) ? parsed : null;
  } catch {
    return null;
  }
}
function youtubeVideoId(value = '') {
  const parsed = parsedExternalHttpUrl(value);
  if (!parsed
    || parsed.protocol !== 'https:'
    || parsed.username
    || parsed.password
    || (parsed.port && parsed.port !== '443')) return '';
  const host = parsed.hostname.toLowerCase().replace(/\.$/, '');
  let id = '';
  if (host === 'youtu.be') {
    const match = parsed.pathname.match(/^\/([A-Za-z0-9_-]{11})\/?$/);
    id = match?.[1] || '';
  } else if (['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'].includes(host)) {
    if (parsed.pathname.replace(/\/+$/, '') === '/watch') id = parsed.searchParams.get('v') || '';
    else id = parsed.pathname.match(/^\/(?:embed|shorts|live)\/([A-Za-z0-9_-]{11})\/?$/)?.[1] || '';
  } else if (['youtube-nocookie.com', 'www.youtube-nocookie.com'].includes(host)) {
    id = parsed.pathname.match(/^\/embed\/([A-Za-z0-9_-]{11})\/?$/)?.[1] || '';
  }
  return /^[A-Za-z0-9_-]{11}$/.test(id) ? id : '';
}
function youtubeEmbedUrl(value = '') {
  const id = youtubeVideoId(value);
  return id ? `https://www.youtube-nocookie.com/embed/${id}` : '';
}
function vimeoEmbedUrl(value = '') {
  const parsed = parsedExternalHttpUrl(value);
  if (!parsed) return '';
  const host = parsed.hostname.toLowerCase();
  const pathParts = parsed.pathname.split('/').filter(Boolean);
  let id = '';
  if (['vimeo.com', 'www.vimeo.com'].includes(host)) id = pathParts[0] || '';
  else if (host === 'player.vimeo.com' && pathParts[0] === 'video') id = pathParts[1] || '';
  return /^[0-9]{6,}$/.test(id) ? `https://player.vimeo.com/video/${id}` : '';
}
function videoEmbedUrl(value = '') {
  return youtubeEmbedUrl(value) || vimeoEmbedUrl(value);
}
function safeMediaHref(value) {
  const href = safeHref(value);
  if (!href || href.startsWith('#')) return '';
  const scheme = href.match(/^([a-z][a-z0-9+.-]*):/i)?.[1]?.toLowerCase();
  return scheme && !['http', 'https'].includes(scheme) ? '' : href;
}
function mediaTextType(block) {
  const type = String(block.content?.media_type || 'image').trim().toLowerCase();
  return ['image', 'video', 'youtube'].includes(type) ? type : 'image';
}
function mediaTextImageUrl(block) {
  return mediaTextType(block) === 'image' ? safeMediaHref(block.content?.image) : '';
}
function mediaTextVideoUrl(block) {
  return mediaTextType(block) === 'video' ? safeMediaHref(block.content?.video_url) : '';
}
function mediaTextYoutubeUrl(block) {
  return mediaTextType(block) === 'youtube' ? youtubeEmbedUrl(block.content?.youtube_url) : '';
}
function mediaTextPosterUrl(block) {
  return mediaTextType(block) === 'video' ? safeMediaHref(block.content?.poster) : '';
}
function mediaTextHasMedia(block) {
  return Boolean(mediaTextImageUrl(block) || mediaTextVideoUrl(block) || mediaTextYoutubeUrl(block));
}
function mediaTextVideoLabel(block) {
  return String(block.content?.heading || shared.value.video_embed_title || 'Embedded video').trim();
}
function mediaTextCaption(block) {
  return mediaTextType(block) === 'image' ? '' : String(block.content?.caption || '').trim();
}
function mediaTextCaptionId(block) {
  const token = String(block.uuid || 'block').replace(/[^a-zA-Z0-9_-]/g, '-');
  return `igf-media-text-caption-${token}`;
}
function mediaTextCaptionDescription(block) {
  return mediaTextCaption(block) ? mediaTextCaptionId(block) : null;
}
function visibilityClass(block) {
  return { 'igf-page-block--desktop-hidden': !block.show_on_desktop, 'igf-page-block--mobile-hidden': !block.show_on_mobile };
}
function blockStyle(block) {
  if (block.type === 'spacer') return { minHeight: ({ small: '24px', medium: '56px', large: '96px' }[block.content?.size] || '56px'), padding: 0 };
  return {};
}
function statAnimationEnabled(block) { return block.content?.animation_enabled !== false; }
function statAnimationType(block) {
  const type = String(block.content?.animation_type || 'count_up');
  return ['count_up', 'fade_up', 'pop'].includes(type) ? type : 'count_up';
}
function statAnimationDuration(block) { return Math.min(5000, Math.max(300, Number(block.content?.animation_duration || 1600))); }
function statAnimationDelay(block) { return Math.min(1000, Math.max(0, Number(block.content?.animation_delay ?? 120))); }
function statKey(block, index) { return `${block.uuid}:${index}`; }
function statAnimationClass(block) {
  if (!statAnimationEnabled(block)) return '';
  return `igf-stat--animated igf-stat--${statAnimationType(block).replace('_', '-')}`;
}
function statAnimationStyle(block, index) {
  if (!statAnimationEnabled(block)) return {};
  return {
    '--stat-animation-duration': `${statAnimationDuration(block)}ms`,
    '--stat-animation-delay': `${statAnimationDelay(block) * index}ms`,
  };
}
function displayStatValue(block, item, index) {
  const activeValue = statDisplayValues.value[statKey(block, index)];
  if (activeValue !== undefined) return activeValue;
  const parsed = parsedStatValue(item.value);
  return parsed ? formattedStatValue(parsed, parsed.number) : item.value;
}
function parsedStatValue(value) {
  const raw = String(value ?? '');
  const match = raw.match(/-?[\d,]+(?:\.\d+)?/);
  if (!match) return null;
  const number = Number(match[0].replaceAll(',', ''));
  if (!Number.isFinite(number)) return null;
  const decimals = (match[0].split('.')[1] || '').length;
  return {
    number,
    prefix: raw.slice(0, match.index),
    suffix: raw.slice((match.index || 0) + match[0].length),
    decimals,
    grouping: match[0].includes(','),
  };
}
function formattedStatValue(parsed, value) {
  const formatted = formatNumber(value, regional.value, {
    useGrouping: parsed.grouping,
    minimumFractionDigits: parsed.decimals,
    maximumFractionDigits: parsed.decimals,
  });
  return `${parsed.prefix}${formatted}${parsed.suffix}`;
}
function focusAreaRevealStyle(index) {
  return { '--igf-focus-delay': `${Math.min(5, Math.max(0, Number(index) || 0)) * 100}ms` };
}
function setupFocusAreaReveals() {
  const elements = [...(pageBlocksRoot.value?.querySelectorAll('[data-focus-area-reveal]') || [])];
  if (!elements.length) return;
  elements.forEach(element => element.classList.add('is-reveal-ready'));
  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  if (reducedMotion || typeof window.IntersectionObserver !== 'function') {
    elements.forEach(element => element.classList.add('is-visible'));
    return;
  }

  focusAreaObserver = new window.IntersectionObserver(entries => {
    entries.forEach(entry => entry.target.classList.toggle('is-visible', entry.isIntersecting));
  }, { threshold: 0, rootMargin: '0px 0px -120px 0px' });
  elements.forEach(element => focusAreaObserver.observe(element));
}
function setStatDisplay(key, value) { statDisplayValues.value = { ...statDisplayValues.value, [key]: value }; }
function animateStatCounter(block, item, index) {
  const parsed = parsedStatValue(item.value);
  if (!parsed) return;
  const key = statKey(block, index);
  const duration = statAnimationDuration(block);
  const delay = statAnimationDelay(block) * index;
  setStatDisplay(key, formattedStatValue(parsed, 0));
  const startCounter = () => {
    const startedAt = performance.now();
    const tick = now => {
      const progress = Math.min(1, Math.max(0, (now - startedAt) / duration));
      const eased = 1 - ((1 - progress) ** 3);
      const current = parsed.number * eased;
      setStatDisplay(key, formattedStatValue(parsed, current));
      if (progress < 1) {
        const frame = window.requestAnimationFrame(tick);
        statAnimationFrames.add(frame);
      } else {
        setStatDisplay(key, formattedStatValue(parsed, parsed.number));
      }
    };
    const frame = window.requestAnimationFrame(tick);
    statAnimationFrames.add(frame);
  };
  if (delay) window.setTimeout(startCounter, delay); else startCounter();
}
function setupStatAnimations() {
  const elements = [...(pageBlocksRoot.value?.querySelectorAll('[data-stat-block][data-stat-index]') || [])];
  if (!elements.length) return;
  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  if (reducedMotion || typeof window.IntersectionObserver !== 'function') {
    elements.forEach(element => element.classList.add('is-visible'));
    return;
  }
  statObserver = new window.IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const element = entry.target;
      const block = props.blocks.find(item => item.uuid === element.dataset.statBlock);
      const index = Number(element.dataset.statIndex);
      const item = block?.content?.items?.[index];
      element.classList.add('is-visible');
      if (block && item && statAnimationEnabled(block) && statAnimationType(block) === 'count_up') animateStatCounter(block, item, index);
      statObserver?.unobserve(element);
    });
  }, { threshold: 0.25 });
  elements.forEach(element => {
    const block = props.blocks.find(item => item.uuid === element.dataset.statBlock);
    if (!block || !statAnimationEnabled(block)) element.classList.add('is-visible');
    else statObserver.observe(element);
  });
}
function setupTeamCardViewportAnimations() {
  teamObserver?.disconnect();
  teamObserver = null;
  const cards = [...(pageBlocksRoot.value?.querySelectorAll('.igf-team-panel .igf-team-card.has-details') || [])];
  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const touchPointer = window.matchMedia?.('(hover: none), (pointer: coarse)').matches;
  if (!cards.length || reducedMotion || !touchPointer || typeof window.IntersectionObserver !== 'function') return;

  teamObserver = new window.IntersectionObserver(entries => {
    entries.forEach(entry => {
      const key = entry.target?.dataset?.teamCardKey || '';
      if (!key) return;
      const meetsThreshold = entry.isIntersecting && (entry.intersectionRatio == null || entry.intersectionRatio >= 0.55);

      if (meetsThreshold) {
        if (openTeamCardKey.value || (focusedTeamCardKey() && focusedTeamCardKey() !== key)) return;
        hoverTeamCardKey.value = '';
        viewportTeamCardKey.value = key;
        return;
      }

      if (viewportTeamCardKey.value === key) {
        if (focusedTeamCardKey() === key) openTeamCardKey.value = key;
        viewportTeamCardKey.value = '';
      }
      if (openTeamCardKey.value === key && focusedTeamCardKey() !== key) openTeamCardKey.value = '';
    });
  }, { threshold: [0, 0.55] });
  cards.forEach(card => teamObserver.observe(card));
}
function heroSlides(block) {
  const content = block.content || {};
  const fallback = {
    eyebrow: content.eyebrow || '', heading: content.heading || '', body: content.body || '',
    primary_label: content.primary_label || '', primary_url: content.primary_url || '',
    secondary_label: content.secondary_label || '', secondary_url: content.secondary_url || '',
    report_label: content.report_label || '', report_url: content.report_url || '',
    image: content.image || '', overlay_opacity: content.overlay_opacity ?? 64,
  };
  return Array.isArray(content.slides) && content.slides.length
    ? content.slides.map(slide => ({ ...fallback, ...(slide || {}) }))
    : [fallback];
}
function activeHeroIndex(block) {
  const length = heroSlides(block).length;
  const current = Number(heroIndexes.value[block.uuid] || 0);
  return length ? Math.min(length - 1, Math.max(0, current)) : 0;
}
function activeHeroSlide(block) { return heroSlides(block)[activeHeroIndex(block)] || {}; }
function heroBackgroundStyle(slide) {
  const image = safeHref(slide?.image);
  return responsiveBackgroundPresentation(image);
}
function responsiveImage(value, sizes = '100vw') {
  return responsiveImagePresentation(safeHref(value), sizes);
}
function heroOverlayStyle(block) {
  const opacity = Math.min(100, Math.max(0, Number(activeHeroSlide(block).overlay_opacity ?? 64))) / 100;
  return { '--overlay-opacity': opacity };
}
function heroAutoplayEnabled(block) { return block.content?.autoplay !== false && heroSlides(block).length > 1; }
function heroInterval(block) { return Math.min(20000, Math.max(3000, Number(block.content?.interval || 6000))); }
function goToHero(block, index) {
  const length = heroSlides(block).length;
  if (!length) return;
  heroIndexes.value = { ...heroIndexes.value, [block.uuid]: (index + length) % length };
  heroLastAdvanced.set(block.uuid, Date.now());
}
function nextHero(block) { goToHero(block, activeHeroIndex(block) + 1); }
function previousHero(block) { goToHero(block, activeHeroIndex(block) - 1); }
function toggleHeroAutoplay(block) {
  heroUserPaused.value = { ...heroUserPaused.value, [block.uuid]: !heroUserPaused.value[block.uuid] };
  heroLastAdvanced.set(block.uuid, Date.now());
}
function pauseHeroInteraction(block, paused, force = false) {
  if (block.type !== 'hero' || (!force && block.content?.pause_on_hover === false)) return;
  heroInteractionPaused.value = { ...heroInteractionPaused.value, [block.uuid]: paused };
}
function releaseHeroFocus(block, event) {
  if (block.type !== 'hero' || event.currentTarget?.contains(event.relatedTarget)) return;
  pauseHeroInteraction(block, false, true);
}
function rememberHeroTouch(block, event) {
  if (block.type === 'hero' && event.touches?.[0]) heroTouchStarts.set(block.uuid, event.touches[0].clientX);
}
function finishHeroTouch(block, event) {
  const start = heroTouchStarts.get(block.uuid); const end = event.changedTouches?.[0]?.clientX;
  heroTouchStarts.delete(block.uuid);
  if (start == null || end == null || Math.abs(end - start) < 48) return;
  if (end < start) nextHero(block); else previousHero(block);
}
function campusGalleryItems(block) {
  return Array.isArray(block.content?.items) ? block.content.items.filter(item => item?.image) : [];
}
function gallerySlides(block) {
  const items = campusGalleryItems(block);
  const slides = [];
  for (let index = 0; index < items.length; index += 5) slides.push(items.slice(index, index + 5));
  return slides;
}
function activeGalleryIndex(block) {
  const length = gallerySlides(block).length;
  const current = Number(galleryIndexes.value[block.uuid] || 0);
  return length ? Math.min(length - 1, Math.max(0, current)) : 0;
}
function goToGallery(block, index) {
  const lastIndex = gallerySlides(block).length - 1;
  if (lastIndex < 0) return;
  galleryIndexes.value = { ...galleryIndexes.value, [block.uuid]: Math.min(lastIndex, Math.max(0, index)) };
}
function previousGallery(block) { goToGallery(block, activeGalleryIndex(block) - 1); }
function nextGallery(block) { goToGallery(block, activeGalleryIndex(block) + 1); }
function galleryTrackStyle(block) { return { transform: `translateX(-${activeGalleryIndex(block) * 100}%)` }; }
function rememberGalleryTouch(block, event) {
  if (event.touches?.[0]) galleryTouchStarts.set(block.uuid, event.touches[0].clientX);
}
function finishGalleryTouch(block, event) {
  const start = galleryTouchStarts.get(block.uuid); const end = event.changedTouches?.[0]?.clientX;
  galleryTouchStarts.delete(block.uuid);
  if (start == null || end == null || Math.abs(end - start) < 48) return;
  if (end < start) nextGallery(block); else previousGallery(block);
}
function activeGalleryLightboxItem(block) {
  return campusGalleryItems(block)[galleryLightbox.value?.index ?? -1] || null;
}
function openGalleryLightbox(block, index, event) {
  galleryLightbox.value = { blockUuid: block.uuid, index, returnFocus: event?.currentTarget || null };
  nextTick(() => pageBlocksRoot.value?.querySelector('.igf-campus-lightbox__close')?.focus());
}
function galleryLightboxTrigger(blockUuid, index) {
  const triggers = pageBlocksRoot.value?.querySelectorAll('.igf-campus-gallery__lightbox-trigger') || [];
  return [...triggers].find(trigger => (
    trigger.dataset.galleryBlockUuid === String(blockUuid)
    && Number(trigger.dataset.galleryItemIndex) === Number(index)
  )) || null;
}
function closeGalleryLightbox() {
  const current = galleryLightbox.value;
  const returnFocus = current
    ? galleryLightboxTrigger(current.blockUuid, current.index) || current.returnFocus
    : null;
  galleryLightbox.value = null;
  nextTick(() => returnFocus?.focus?.());
}
function moveGalleryLightbox(block, direction) {
  if (galleryLightbox.value?.blockUuid !== block.uuid) return;
  const lastIndex = campusGalleryItems(block).length - 1;
  const index = Math.min(lastIndex, Math.max(0, galleryLightbox.value.index + direction));
  galleryLightbox.value = { ...galleryLightbox.value, index };
  goToGallery(block, Math.floor(index / 5));
}
function trapGalleryLightboxFocus(event) {
  const controls = [...(event.currentTarget?.querySelectorAll('button:not(:disabled)') || [])];
  if (!controls.length) return;
  const first = controls[0]; const last = controls[controls.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault(); last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault(); first.focus();
  }
}
function activeTestimonialIndex(block) {
  const length = block.content?.items?.length || 0;
  const current = Number(testimonialIndexes.value[block.uuid] || 0);
  return length ? Math.min(length - 1, Math.max(0, current)) : 0;
}
function activeTestimonial(block) { return block.content?.items?.[activeTestimonialIndex(block)] || {}; }
function goToTestimonial(block, index) {
  const length = block.content?.items?.length || 0;
  if (!length) return;
  testimonialIndexes.value = { ...testimonialIndexes.value, [block.uuid]: (index + length) % length };
}
function previousTestimonial(block) { goToTestimonial(block, activeTestimonialIndex(block) - 1); }
function nextTestimonial(block) { goToTestimonial(block, activeTestimonialIndex(block) + 1); }
onMounted(() => {
  setupStatAnimations();
  setupTeamCardViewportAnimations();
  setupFocusAreaReveals();
  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  heroClock = window.setInterval(() => {
    if (reducedMotion || document.visibilityState === 'hidden') return;
    const now = Date.now();
    for (const block of props.blocks.filter(item => item.type === 'hero' && heroAutoplayEnabled(item))) {
      const last = heroLastAdvanced.get(block.uuid) || now;
      if (!heroLastAdvanced.has(block.uuid)) heroLastAdvanced.set(block.uuid, now);
      if (heroUserPaused.value[block.uuid] || heroInteractionPaused.value[block.uuid] || now - last < heroInterval(block)) continue;
      nextHero(block);
    }
  }, 500);
});
onBeforeUnmount(() => {
  if (heroClock !== null) window.clearInterval(heroClock);
  statObserver?.disconnect();
  teamObserver?.disconnect();
  focusAreaObserver?.disconnect();
  statAnimationFrames.forEach(frame => window.cancelAnimationFrame(frame));
  statAnimationFrames.clear();
});
function eventItems(block) { return (block.content?.items || []).filter(item => String(item.eyebrow || '').toLowerCase().includes('event')); }
function newsItems(block) { return (block.content?.items || []).filter(item => !String(item.eyebrow || '').toLowerCase().includes('event')); }
function eventDay(item) {
  return formatDate(item?.published_at, regional.value, { day: '2-digit' }) || item?.day || '•';
}
function eventMonth(item, fallback = '') {
  return formatDate(item?.published_at, regional.value, { month: 'short' }) || item?.month || fallback;
}
function money(value) { return formatMoney(value, regional.value); }
function newsletterDomToken(block) { return String(block?.uuid || 'block').replace(/[^a-zA-Z0-9_-]/g, '-'); }
function newsletterEmailId(block) { return `igf-newsletter-email-${newsletterDomToken(block)}`; }
function newsletterMessageId(block) { return `igf-newsletter-message-${newsletterDomToken(block)}`; }
function subscribe() {
  if (!newsletterConsent.value) return;
  newsletterBusy.value = true; newsletterMessage.value = ''; newsletterFeedbackType.value = '';
  router.post(route('frontend.subscribe'), { email: newsletterEmail.value, consent: newsletterConsent.value }, {
    preserveScroll: true,
    onSuccess: () => { newsletterEmail.value = ''; newsletterConsent.value = false; newsletterMessage.value = shared.value.newsletter_success_message || 'Thank you for subscribing.'; newsletterFeedbackType.value = 'success'; },
    onError: errors => { newsletterMessage.value = errors.email || shared.value.newsletter_error_message || 'Please check your email and try again.'; newsletterFeedbackType.value = 'error'; },
    onFinish: () => { newsletterBusy.value = false; },
  });
}
</script>

<style scoped lang="scss">
.igf-page-blocks { --orange:#ff7500; --brown:#9c4500; --ink:#191c1d; --muted:#5f6065; --surface:#f8f9fa; --line:#e5e0dc; --campus-green:#609966; --campus-green-dark:#40513b; --campus-green-mid:#9dc08b; --campus-green-light:#d0f5b8; --campus-green-lighter:#e7fddc; --campus-green-soft:#cff5b9; --campus-white:#fbfff9; overflow:hidden; background:#fff; color:var(--ink); font-family:'Hanken Grotesk',Arial,sans-serif; }
.igf-page-block { position:relative; padding:var(--igf-section-block,clamp(72px,9vw,120px)) clamp(20px,5vw,48px); background:#fff; color:var(--ink); }
.igf-page-block__inner { position:relative; z-index:2; width:min(100%,var(--igf-content-width,1240px)); margin:0 auto; }
.igf-page-block__eyebrow { margin:0 0 14px; color:var(--brown); font:800 12px/1.25 'Hanken Grotesk',Arial,sans-serif; letter-spacing:.09em; text-transform:uppercase; }
.igf-page-block__eyebrow--inverse { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid rgba(255,117,0,.34); border-radius:12px; background:rgba(255,117,0,.1); color:#ffb070; }
.igf-page-blocks :is(h1,h2,h3) { color:inherit; font-family:'Literata',Georgia,serif; letter-spacing:-.025em; }
.igf-page-blocks :is(h1,h2,h3)::after { display:none!important; content:none!important; }
.igf-page-blocks h1 { max-width:760px; margin:0 0 22px; font-size:var(--igf-heading-1,clamp(42px,6vw,76px)); font-weight:650; line-height:1.03; }
.igf-page-blocks h2 { display:block; max-width:820px; margin:0 0 20px; font-size:var(--igf-heading-2,clamp(34px,4vw,52px)); font-weight:620; line-height:1.12; }
.igf-page-blocks h3 { margin:0; font-size:var(--igf-heading-3,22px); line-height:1.25; }
.igf-page-blocks p { color:var(--muted); font-family:inherit; }
.igf-page-block__lead,.igf-section-lead { max-width:680px; font-size:var(--igf-lead-size,clamp(18px,2vw,21px)); line-height:1.6; }
.igf-page-block--hero { display:flex; min-height:var(--igf-hero-height,min(780px,86vh)); align-items:center; overflow:hidden; padding-top:clamp(90px,11vw,140px); padding-bottom:clamp(100px,13vw,160px); background:#202124; color:#fff; }
.igf-hero-carousel__backgrounds,.igf-hero-carousel__backgrounds>span { position:absolute; inset:0; }
.igf-hero-carousel__backgrounds { z-index:0; overflow:hidden; background:#202124; }
.igf-hero-carousel__backgrounds>span { background-color:transparent; background-image:var(--igf-hero-image); background-image:var(--igf-hero-image-set-small,var(--igf-hero-image)); background-position:center; background-size:cover; background-repeat:no-repeat; opacity:0; transform:scale(1.025); transition:opacity .65s ease,transform 6s ease; }
.igf-hero-carousel__backgrounds>span.is-active { opacity:1; transform:scale(1); }
@media(min-width:700px) { .igf-hero-carousel__backgrounds>span { background-image:var(--igf-hero-image); background-image:var(--igf-hero-image-set-medium,var(--igf-hero-image)); } }
@media(min-width:1200px) { .igf-hero-carousel__backgrounds>span { background-image:var(--igf-hero-image); background-image:var(--igf-hero-image-set-large,var(--igf-hero-image)); } }
.igf-page-block__overlay { position:absolute; z-index:1; inset:0; background:rgba(21,22,23,var(--overlay-opacity,.64)); transition:background-color .35s ease; }
.igf-page-block__hero-content { max-width:680px; padding:clamp(28px,5vw,48px); border:1px solid rgba(255,255,255,.16); border-radius:20px; background:rgba(31,32,34,.92); }
.igf-page-block--hero.igf-page-block--campus .igf-page-block__hero-grid { display:grid; place-items:center; }
.igf-page-block--hero.igf-page-block--campus .igf-page-block__hero-content { max-width:880px; border-color:rgba(255,255,255,.22); background:rgba(24,25,26,.58); text-align:center; backdrop-filter:blur(3px); }
.igf-page-block--hero.igf-page-block--campus .igf-page-block__actions { justify-content:center; }
.igf-hero-carousel__content { animation:igf-hero-content-in .5s ease both; }
.igf-hero-carousel__controls { position:absolute; z-index:4; right:clamp(20px,5vw,48px); bottom:96px; display:flex; align-items:center; gap:8px; }
.igf-hero-carousel__controls button { display:grid; min-width:44px; min-height:44px; place-items:center; border:1px solid rgba(255,255,255,.42); border-radius:999px; background:rgba(25,28,29,.78); color:#fff; cursor:pointer; }
.igf-hero-carousel__controls button:focus-visible { outline:3px solid #fff; outline-offset:3px; }
.igf-hero-carousel__controls button:hover { border-color:#ffb070; background:#9c4500; }
.igf-hero-carousel__dots { display:flex; align-items:center; gap:3px; padding:0 5px; }
.igf-hero-carousel__dots button { min-width:30px; border-color:transparent; background:transparent; }
.igf-hero-carousel__dots button:hover { background:rgba(255,255,255,.08); }
.igf-hero-carousel__dots span { width:8px; height:8px; border-radius:50%; background:rgba(255,255,255,.52); transition:width .2s ease,background .2s ease; }
.igf-hero-carousel__dots button[aria-current="true"] span { width:24px; border-radius:999px; background:#ff7500; }
.igf-hero-carousel__pause { margin-left:3px; }
@keyframes igf-hero-content-in { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }
.igf-page-block--hero p { color:#dedede; }
.igf-page-block__actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:28px; }
.igf-button { display:inline-flex; min-height:50px; align-items:center; justify-content:center; gap:10px; padding:0 26px; border:2px solid transparent; border-radius:var(--igf-button-radius,999px); font:800 13px/1 'Hanken Grotesk',Arial,sans-serif; letter-spacing:.04em; text-decoration:none; text-transform:uppercase; transition:.18s ease; }
.igf-button:hover { transform:translateY(-2px); }
.igf-button--primary { border-color:var(--orange); background:var(--orange); color:#fff; }
.igf-button--secondary { border-color:rgba(255,255,255,.45); background:rgba(255,255,255,.04); color:#fff; }
.igf-button--outline { border-color:#8c7163; background:#fff; color:var(--ink); }
.igf-report-link { display:flex; width:max-content; align-items:center; gap:9px; margin-top:28px; padding-top:24px; border-top:1px solid rgba(255,255,255,.18); color:#ffb070; font-size:13px; font-weight:800; text-decoration:none; }
.igf-page-block--stats { z-index:3; margin-top:-64px; padding-top:0; padding-bottom:100px; background:transparent; }
.igf-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; }
.igf-stat { margin:0; padding:30px; border:1px solid var(--igf-card-border,var(--line)); border-top:4px solid var(--orange); border-radius:var(--igf-card-radius,16px); background:#fff; box-shadow:var(--igf-card-shadow,0 7px 22px rgba(25,28,29,.08)); }
.igf-stat:nth-child(even) { border-top-color:var(--brown); }
.igf-stat>i { display:block; margin-bottom:17px; color:var(--orange); font-size:30px; }
.igf-stat:nth-child(even)>i { color:var(--brown); }
.igf-stat strong { display:block; margin-bottom:7px; color:var(--ink); font:650 clamp(30px,4vw,44px)/1 'Literata',Georgia,serif; }
.igf-stat span { display:block; color:var(--muted); font-size:12px; font-weight:800; letter-spacing:.055em; text-transform:uppercase; }
.igf-stat--animated { opacity:0; will-change:opacity,transform; }
.igf-stat--animated.is-visible { animation-delay:var(--stat-animation-delay,0ms); animation-duration:var(--stat-animation-duration,1600ms); animation-fill-mode:both; animation-timing-function:cubic-bezier(.22,1,.36,1); }
.igf-stat--count-up.is-visible,.igf-stat--fade-up.is-visible { animation-name:igf-stat-fade-up; }
.igf-stat--pop.is-visible { animation-name:igf-stat-pop; }
@keyframes igf-stat-fade-up { from { opacity:0; transform:translateY(28px); } to { opacity:1; transform:translateY(0); } }
@keyframes igf-stat-pop { 0% { opacity:0; transform:scale(.82); } 68% { opacity:1; transform:scale(1.045); } 100% { opacity:1; transform:scale(1); } }
.igf-media-text { display:grid; grid-template-columns:5fr 6fr; align-items:center; gap:clamp(40px,8vw,100px); }
.igf-media-text--reverse { grid-template-columns:1fr 1fr; }
.igf-media-text--without-media { grid-template-columns:minmax(0,1fr); }
.igf-media-text__figure { min-width:0; margin:0; }
.igf-media-text--reverse .igf-media-text__figure { order:2; }
.igf-media-text__media { --igf-media-default-aspect:var(--igf-image-aspect,4 / 5); overflow:hidden; aspect-ratio:var(--igf-media-aspect,var(--igf-media-default-aspect)); border-radius:var(--igf-card-radius,22px); background:#eee; }
.igf-media-text--reverse .igf-media-text__media { --igf-media-default-aspect:1; }
.igf-media-text__media--video,.igf-media-text__media--youtube { --igf-media-aspect:16 / 9; background:#171717; }
.igf-media-text__media :is(img,video,iframe) { display:block; width:100%; height:100%; border:0; }
.igf-media-text__media img { object-fit:cover; }
.igf-media-text__media video { object-fit:contain; }
.igf-media-text__caption { margin:10px 0 0; color:var(--muted); font-size:13px; line-height:1.5; text-align:center; }
.igf-page-block__copy { color:var(--muted); font-size:var(--igf-body-size,17px); line-height:1.75; }
.igf-page-block__copy :deep(p) { margin:0 0 18px; color:inherit; font:inherit; }
.igf-page-block__copy :deep(a) { color:var(--brown); font-weight:800; }
.igf-text-link { display:inline-flex; align-items:center; gap:6px; margin-top:10px; color:var(--brown); font-size:14px; font-weight:800; text-decoration:none; }
.igf-page-block--cards:nth-of-type(even),.igf-page-block--updates { background:var(--surface); }
.igf-section-heading { display:flex; align-items:end; justify-content:space-between; gap:30px; margin-bottom:44px; }
.igf-section-heading h2 { margin-bottom:13px; }
.igf-section-heading .igf-section-lead { margin:0; }
.igf-card-grid { display:grid; grid-template-columns:repeat(var(--igf-card-columns,3),minmax(0,1fr)); gap:24px; }
.igf-card-grid--four { grid-template-columns:repeat(var(--igf-card-columns,3),minmax(0,1fr)); }
.igf-card { display:flex; overflow:hidden; min-height:250px; flex-direction:column; border:1px solid var(--igf-card-border,var(--line)); border-radius:var(--igf-card-radius,16px); background:#fff; color:var(--ink); text-decoration:none; box-shadow:var(--igf-card-shadow,0 3px 10px rgba(25,28,29,.035)); transition:.2s ease; }
.igf-card:hover { transform:translateY(-4px); border-color:#d9c8bb; box-shadow:0 12px 28px rgba(25,28,29,.09); }
.igf-card:not(a):hover { transform:none; border-color:var(--igf-card-border,var(--line)); box-shadow:var(--igf-card-shadow,0 3px 10px rgba(25,28,29,.035)); }
.igf-page-block--initiatives { background:var(--surface); }
.igf-page-block--initiatives .igf-card { border-top:4px solid var(--orange); }
.igf-page-block--initiatives .igf-card__media { display:grid; height:120px; place-items:center; background:#fff8f2; }
.igf-page-block--initiatives .igf-card__media img { width:64px; height:64px; object-fit:contain; }
.igf-page-block--initiatives .igf-card__content>i { display:grid; width:58px; height:58px; place-items:center; border-radius:50%; background:#fff2e8; }
.igf-page-block--contributions .igf-card-grid { grid-template-columns:repeat(4,minmax(0,1fr)); }
.igf-page-block--contributions .igf-card { min-height:310px; border-color:#e6d1c1; }
.igf-page-block--contributions .igf-card__content>i { color:var(--brown); font-size:38px; }
.igf-card__media { position:relative; overflow:hidden; height:var(--igf-card-media-height,230px); background:#ececec; }
.igf-card__media img { width:100%; height:100%; object-fit:cover; transition:.3s ease; }
.igf-card:hover .igf-card__media img { transform:scale(1.025); }
.igf-card__status { position:absolute; top:14px; left:14px; padding:6px 10px; border:1px solid rgba(0,0,0,.08); border-radius:999px; background:rgba(255,255,255,.92); color:var(--ink); font-size:11px; font-weight:800; }
.igf-card__content { display:flex; padding:26px; flex:1; flex-direction:column; align-items:flex-start; }
.igf-card__content>i { margin-bottom:23px; color:var(--orange); font-size:32px; }
.igf-card__content small { margin-bottom:10px; color:var(--muted); font-size:12px; font-weight:700; text-transform:uppercase; }
.igf-card__content h3 { margin-bottom:12px; }
.igf-card__content p { margin:0 0 22px; font-size:var(--igf-body-size,17px); line-height:1.6; }
.igf-card__link { margin-top:auto; color:var(--brown); font-size:13px; font-weight:800; }
.igf-focus-areas { position:relative; display:grid; isolation:isolate; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; align-items:stretch; }
.igf-focus-areas::before { position:absolute; z-index:-1; top:50%; left:50%; width:min(900px,100%); height:600px; background:radial-gradient(circle,rgba(255,117,0,.18) 0,rgba(255,117,0,0) 70%); content:''; pointer-events:none; transform:translate(-50%,-50%); }
.igf-focus-area__reveal.is-reveal-ready { opacity:0; transform:translateY(100px); transition:opacity 500ms ease-out,transform 500ms ease-out; transition-delay:var(--igf-focus-delay,0ms); }
.igf-focus-area__reveal.is-reveal-ready.is-visible { opacity:1; transform:translateY(0); }
.igf-focus-areas__heading { display:flex; min-width:0; min-height:390px; justify-content:center; padding:clamp(30px,4vw,52px); flex-direction:column; border-radius:var(--igf-card-radius,16px); background:var(--orange); color:#fff; }
.igf-focus-areas__heading .igf-page-block__eyebrow { color:#572500; }
.igf-focus-areas__heading h2 { margin:0; font-size:clamp(38px,4.5vw,56px); line-height:1.08; }
.igf-focus-areas__heading .igf-section-lead { margin:18px 0 0; color:rgba(255,255,255,.9); }
.igf-focus-areas__heading .igf-text-link { width:fit-content; margin-top:28px; color:#fff; }
.igf-focus-area-card { position:relative; isolation:isolate; display:flex; min-width:0; min-height:390px; overflow:hidden; padding:clamp(26px,3vw,38px); flex-direction:column; border:1px solid var(--igf-card-border,var(--line)); border-radius:var(--igf-card-radius,16px); background:#fff; box-shadow:var(--igf-card-shadow,0 8px 22px rgba(25,28,29,.08)); color:var(--ink); text-decoration:none; transition:color 300ms ease-out,border-color 300ms ease-out,box-shadow 300ms ease-out,opacity 500ms ease-out,transform 500ms ease-out; transition-delay:0ms,0ms,0ms,var(--igf-focus-delay,0ms),var(--igf-focus-delay,0ms); }
.igf-focus-area-card::before { position:absolute; z-index:-1; inset:0; background:var(--orange); content:''; transform:scaleX(0); transform-origin:left center; transition:transform 500ms ease-out; }
.igf-focus-area-card:hover,.igf-focus-area-card:focus-visible,.igf-focus-area-card:focus-within { border-color:var(--orange); box-shadow:0 14px 32px rgba(156,69,0,.2); color:#fff; }
.igf-focus-area-card:hover::before,.igf-focus-area-card:focus-visible::before,.igf-focus-area-card:focus-within::before { transform:scaleX(1); }
.igf-focus-area-card:focus-visible { outline:3px solid color-mix(in srgb,var(--orange) 42%,white); outline-offset:4px; }
.igf-focus-area-card__media { display:grid; width:72px; height:72px; flex:0 0 auto; place-items:center; overflow:hidden; margin-bottom:28px; border-radius:16px; background:#fff2e8; color:var(--brown); transition:background-color 300ms ease-out,color 300ms ease-out; }
.igf-focus-area-card__media img { width:100%; height:100%; object-fit:cover; }
.igf-focus-area-card__media i { font-size:34px; }
.igf-focus-area-card:hover .igf-focus-area-card__media,.igf-focus-area-card:focus-visible .igf-focus-area-card__media,.igf-focus-area-card:focus-within .igf-focus-area-card__media { background:rgba(255,255,255,.2); color:#fff; }
.igf-focus-area-card__copy { display:flex; min-width:0; flex:1; flex-direction:column; }
.igf-focus-area-card__copy h3 { margin:0 0 16px; font-size:clamp(24px,2.3vw,31px); line-height:1.22; }
.igf-focus-area-card__copy p { margin:0 0 24px; color:var(--muted); font-size:16px; line-height:1.65; transition:color 300ms ease-out; }
.igf-focus-area-card:hover .igf-focus-area-card__copy p,.igf-focus-area-card:focus-visible .igf-focus-area-card__copy p,.igf-focus-area-card:focus-within .igf-focus-area-card__copy p { color:rgba(255,255,255,.92); }
.igf-focus-area-card__link { width:fit-content; margin-top:auto; padding:8px 14px; border:1px dashed currentColor; border-radius:999px; font-size:13px; font-weight:800; }
.igf-page-block--ways_to_give { background:var(--surface); }
.igf-giving__heading { max-width:760px; margin:0 auto 38px; text-align:center; }
.igf-giving__heading h2 { margin-bottom:14px; }
.igf-giving__lead { margin:0 auto; }
.igf-giving__options { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:22px; }
.igf-giving-card { display:flex; min-width:0; min-height:100%; overflow:hidden; flex-direction:column; border:1px solid var(--igf-card-border,var(--line)); border-radius:var(--igf-card-radius,16px); background:#fff; color:var(--ink); text-decoration:none; box-shadow:var(--igf-card-shadow,0 6px 20px rgba(25,28,29,.06)); transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease; }
.igf-giving-card:hover { border-color:rgba(255,117,0,.52); box-shadow:0 12px 30px rgba(25,28,29,.1); transform:translateY(-3px); }
.igf-giving-card:focus-visible { outline:3px solid #b95000; outline-offset:4px; }
.igf-giving-card__media { display:grid; height:190px; flex:0 0 auto; place-items:center; overflow:hidden; background:#fff2e7; color:var(--brown); }
.igf-giving-card__media img { width:100%; height:100%; object-fit:cover; }
.igf-giving-card__media i { font-size:48px; }
.igf-giving-card__copy { display:flex; min-width:0; flex:1; flex-direction:column; align-items:flex-start; padding:24px; }
.igf-giving-card__copy small { margin-bottom:8px; color:var(--brown); font-size:11px; font-weight:850; letter-spacing:.055em; line-height:1.4; text-transform:uppercase; }
.igf-giving-card__copy strong { margin-bottom:10px; font:700 23px/1.2 'Literata',Georgia,serif; }
.igf-giving-card__body { margin:0 0 20px; color:var(--muted); font-size:15px; line-height:1.6; }
.igf-giving-card__body :deep(p) { margin:0; }
.igf-giving-card__copy b { display:flex; min-height:44px; align-items:center; gap:7px; margin-top:auto; color:var(--brown); font-size:13px; }
.igf-giving--single_cta .igf-giving__options { max-width:820px; grid-template-columns:1fr; margin:0 auto; }
.igf-giving--single_cta .igf-giving-card { min-height:280px; flex-direction:row; }
.igf-giving--single_cta .igf-giving-card__media { width:42%; height:auto; min-height:280px; }
.igf-giving--single_cta .igf-giving-card__copy { justify-content:center; padding:clamp(26px,5vw,54px); }
.igf-giving--banner { padding:clamp(28px,5vw,52px); border-radius:24px; background:#2c2723; color:#fff; }
.igf-giving--banner .igf-giving__heading { margin-bottom:28px; text-align:left; }
.igf-giving--banner .igf-giving__heading h2,.igf-giving--banner .igf-giving__lead { color:#fff; }
.igf-giving--banner .igf-giving__options { grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; }
.igf-giving--banner .igf-giving-card { min-height:112px; flex-direction:row; align-items:stretch; border-color:rgba(255,255,255,.16); background:#fff; }
.igf-giving--banner .igf-giving-card__media { width:94px; height:auto; }
.igf-giving--banner .igf-giving-card__copy { padding:18px; }
.igf-giving--banner .igf-giving-card__body { display:none; }
.igf-giving--banner .igf-giving-card__copy strong { font-size:18px; }
.igf-dynamic-empty { padding:32px; border:1px dashed var(--line); border-radius:14px; background:var(--surface); text-align:center; }
.igf-team-tabs { display:flex; width:100%; align-items:center; gap:10px; overflow-x:auto; margin:0 auto 36px; padding:4px 2px 8px; overscroll-behavior-inline:contain; scroll-padding-inline:2px; scroll-snap-type:x proximity; -webkit-overflow-scrolling:touch; }
.igf-team-tab { display:inline-flex; min-height:44px; flex:0 0 auto; align-items:center; justify-content:center; border:1px solid #d7d0c8; border-radius:999px; padding:10px 20px; background:#fff; color:var(--ink); font:750 14px/1.2 'Poppins','Hanken Grotesk',Arial,sans-serif; cursor:pointer; scroll-snap-align:start; transition:background-color .2s ease,border-color .2s ease,color .2s ease; }
.igf-team-tab:hover { border-color:var(--orange); }
.igf-team-tab.is-active { border-color:var(--brown); background:var(--brown); color:#fff; }
.igf-team-tab:focus-visible { outline:3px solid #1b6fdc; outline-offset:3px; }
.igf-team-panel { min-width:0; }
.igf-team-panel:focus-visible { outline:3px solid rgba(27,111,220,.55); outline-offset:8px; }
.igf-team-panel__description { max-width:760px; margin:-12px auto 34px!important; color:var(--muted); font-size:16px!important; line-height:1.65; text-align:center; }
.igf-team-grid { display:grid; grid-template-columns:repeat(auto-fit,300px); align-items:start; justify-content:center; gap:20px; }
.igf-team-card { --igf-team-card-height:440px; position:relative; width:300px; min-width:0; height:var(--igf-team-card-height); margin:0; border-radius:12px; font-family:'Poppins','Hanken Grotesk',Arial,sans-serif; perspective:1000px; }
.igf-team-card__stage { height:var(--igf-team-card-height); }
.igf-team-card__flipper { position:relative; width:100%; height:100%; transform-style:preserve-3d; transition:transform 600ms ease-in-out; }
.igf-team-card__face { position:absolute; inset:0; display:flex; overflow:hidden; flex-direction:column; border:0; border-radius:12px; background:#f5f5ed; backface-visibility:hidden; -webkit-backface-visibility:hidden; }
.igf-team-card__front { transform:rotateY(0); }
.igf-team-card__back { padding:28px; transform:rotateY(180deg); background:#f5f5ed; overflow:hidden; }
.igf-team-card.is-open .igf-team-card__flipper { transform:rotateY(180deg); }
.igf-team-card__media { position:relative; width:100%; height:100%; overflow:hidden; background:#e7e4db; }
.igf-team-card__media img { display:block; width:100%; height:100%; object-fit:cover; object-position:center 20%; }
.igf-team-card__initials { display:grid; width:100%; height:100%; place-items:center; background:#e7e4db; color:#1d1d1d; font:600 64px/1 'Poppins','Hanken Grotesk',Arial,sans-serif; letter-spacing:.04em; }
.igf-team-card__fallback-copy { position:absolute; right:0; bottom:0; left:0; padding:46px 22px 22px; background:linear-gradient(transparent,rgba(0,0,0,.78)); color:#fff; }
.igf-team-card__fallback-copy h3 { margin:0 0 5px; color:inherit; font:700 19px/1.2 'Poppins','Hanken Grotesk',Arial,sans-serif; letter-spacing:0; }
.igf-team-card__fallback-copy p { margin:0; color:rgba(255,255,255,.82); font-size:13px; }
.igf-team-card__back-content { min-height:0; flex:1; overflow-y:auto; overscroll-behavior:contain; }
.igf-team-card__back-heading h3 { margin:0 0 6px; color:#1d1d1d; font:700 22px/1.14 'Poppins','Hanken Grotesk',Arial,sans-serif; letter-spacing:.01em; text-transform:uppercase; }
.igf-team-card__back-heading p { margin:0; color:#737373; font-size:13px; line-height:1.4; }
.igf-team-card__biography { margin:24px 0 0!important; color:#1d1d1d; font-size:14px!important; line-height:1.65; }
.igf-team-card__qualification { margin:18px 0 0!important; color:#666; font-size:13px!important; font-style:italic; line-height:1.5; }
.igf-team-card__socials { display:grid; width:100%; flex:0 0 auto; gap:8px; margin:0; padding:22px 0 0; list-style:none; }
.igf-team-card__socials.is-scrollable { max-height:174px; overflow-y:auto; overscroll-behavior:contain; padding-right:4px; scrollbar-gutter:stable; }
.igf-team-card__socials li { width:100%; }
.igf-team-card__social-link { display:flex; width:100%; align-items:center; justify-content:center; gap:9px; padding:13px 24px; border:1.5px solid #d4d6de; border-radius:14px; background:#f5f5ed; color:#2d3a4e; font:600 14px/1.2 'Poppins','Hanken Grotesk',Arial,sans-serif; text-decoration:none; transition:background-color .2s ease,border-color .2s ease; }
.igf-team-card__social-link:hover { border-color:#b0b4bf; background:#ebebdf; }
.igf-team-card__toggle { position:absolute; z-index:4; inset:0; display:block; width:100%; height:100%; padding:0; border:0; border-radius:12px; background:transparent; cursor:pointer; }
.igf-team-card.is-open .igf-team-card__toggle { pointer-events:none; }
.igf-team-card__toggle:focus-visible { outline:3px solid #1b6fdc; outline-offset:4px; }
.igf-team-card__social-link:focus-visible { outline:3px solid #1b6fdc; outline-offset:3px; }
@media (hover:hover) and (pointer:fine) {
  a.igf-partner-card:hover { border-color:rgba(255,117,0,.58); box-shadow:0 8px 20px rgba(42,52,65,.18); transform:translateY(-2px); }
}
.igf-faq { max-width:980px; }
.igf-faq__items { display:grid; gap:12px; margin-top:38px; }
.igf-faq details { border:1px solid var(--line); border-radius:14px; background:#fff; }
.igf-faq summary { display:flex; min-height:64px; align-items:center; justify-content:space-between; gap:20px; padding:18px 22px; color:var(--ink); font-weight:800; cursor:pointer; }
.igf-faq summary::-webkit-details-marker { display:none; }
.igf-faq details[open] summary i { transform:rotate(45deg); }
.igf-faq details .igf-page-block__copy { padding:0 22px 20px; }
.igf-timeline { max-width:980px; }
.igf-timeline ol { margin:40px 0 0; padding:0; list-style:none; }
.igf-timeline li { display:grid; grid-template-columns:76px 1fr; gap:24px; padding:0 0 38px; }
.igf-timeline li>span { display:grid; width:56px; height:56px; place-items:center; border-radius:50%; background:var(--orange); color:#fff; font-weight:900; }
.igf-timeline li>div { padding:7px 0 30px; border-bottom:1px solid var(--line); }
.igf-timeline h3 { margin-bottom:10px; }
.igf-gallery__grid { display:grid; grid-template-columns:repeat(var(--igf-card-columns,3),minmax(0,1fr)); gap:16px; margin-top:38px; }
.igf-gallery figure { position:relative; overflow:hidden; margin:0; border-radius:var(--igf-card-radius,15px); background:#ececec; }
.igf-gallery figure img { display:block; width:100%; aspect-ratio:var(--igf-image-aspect,4 / 3); object-fit:cover; transition:transform .25s ease; }
.igf-gallery figure:hover img { transform:scale(1.025); }
.igf-gallery figcaption { position:absolute; right:0; bottom:0; left:0; padding:32px 16px 14px; background:linear-gradient(transparent,rgba(0,0,0,.75)); color:#fff; font-weight:800; }
.igf-page-block--campus-gallery .igf-gallery__grid { grid-template-columns:2fr 1fr 1fr; grid-auto-rows:minmax(170px,1fr); }
.igf-page-block--campus-gallery .igf-gallery figure:first-child { grid-row:span 2; }
.igf-page-block--campus-gallery .igf-gallery figure:last-child { grid-column:span 2; }
.igf-page-block--campus-gallery .igf-gallery figure img { height:100%; aspect-ratio:auto; }
.igf-page-block--hero.igf-page-block--campus { min-height:430px; padding:160px 5% 112px; border-bottom:5px solid var(--campus-green-light); background:var(--campus-white); color:#1d1d1d; }
.igf-page-block--hero.igf-page-block--campus .igf-hero-carousel__backgrounds { background:var(--campus-white); }
.igf-page-block--hero.igf-page-block--campus .igf-hero-carousel__backgrounds>span { filter:blur(5px); transform:scale(1.04); }
.igf-page-block--hero.igf-page-block--campus .igf-hero-carousel__backgrounds>span.is-active { transform:scale(1.04); }
.igf-page-block--hero.igf-page-block--campus .igf-page-block__overlay { background:rgba(255,255,255,.9); }
.igf-page-block--hero.igf-page-block--campus .igf-page-block__hero-content { max-width:960px; padding:0; border:0; border-radius:0; background:transparent; box-shadow:none; color:#1d1d1d; backdrop-filter:none; }
.igf-page-block--hero.igf-page-block--campus .igf-hero-carousel__content { animation:none; }
.igf-page-block--hero.igf-page-block--campus :is(.igf-page-block__eyebrow,.igf-page-block__actions,.igf-report-link) { display:none; }
.igf-page-block--hero.igf-page-block--campus h1 { max-width:960px; margin:0 auto 14px; color:var(--campus-green-dark); font-family:'Literata',Georgia,serif; font-size:clamp(42px,5vw,64px); font-weight:700; letter-spacing:-.055em; line-height:1; }
.igf-page-block--hero.igf-page-block--campus .igf-page-block__lead { max-width:760px; margin:0 auto; color:var(--campus-green-dark); font-size:20px; font-weight:600; line-height:1.5; }
.igf-page-block--campus-intro { padding:48px 5%; background:var(--campus-green-lighter); }
.igf-page-block--campus-intro .igf-media-text { display:block; }
.igf-page-block--campus-intro .igf-media-text__figure,.igf-page-block--campus-intro :is(.igf-page-block__eyebrow,h2,.igf-text-link) { display:none; }
.igf-page-block--campus-intro .igf-media-text__content { max-width:1120px; margin:0 auto; text-align:center; }
.igf-page-block--campus-intro .igf-page-block__copy { color:#1d1d1d; font-size:20px; font-weight:600; line-height:1.65; }
.igf-page-block--campus-intro .igf-page-block__copy :deep(p) { margin:0; }
.igf-page-block--stats.igf-page-block--campus-stats { margin-top:0; padding:112px 5%; background:var(--campus-white); }
.igf-page-block--campus-stats .igf-page-block__eyebrow { display:none; }
.igf-page-block--campus-stats h2 { max-width:960px; margin:0 auto 80px; color:#1d1d1d; font-family:'Hanken Grotesk',Arial,sans-serif; font-size:36px; font-weight:700; text-align:center; }
.igf-page-block--campus-stats .igf-stats { grid-template-columns:repeat(3,minmax(0,1fr)); gap:32px; }
.igf-page-block--campus-stats .igf-stat { display:flex; min-height:168px; align-items:center; justify-content:center; padding:28px 18px; flex-direction:column; border:3px dashed var(--campus-green); border-radius:10px; background:transparent; box-shadow:none; text-align:center; }
.igf-page-block--campus-stats .igf-stat:nth-child(even) { border-top-color:var(--campus-green); }
.igf-page-block--campus-stats .igf-stat>i { display:none; }
.igf-page-block--campus-stats .igf-stat strong { margin-bottom:14px; color:#1d1d1d; font-family:'Hanken Grotesk',Arial,sans-serif; font-size:48px; font-weight:700; }
.igf-page-block--campus-stats .igf-stat span { max-width:230px; color:var(--campus-green); font-size:20px; font-weight:700; letter-spacing:-.02em; line-height:1.2; text-transform:none; }
.igf-campus-section-heading { max-width:900px; margin:0 auto 48px; text-align:center; }
.igf-campus-section-heading .igf-page-block__eyebrow { display:none; }
.igf-campus-section-heading h2 { margin-right:auto; margin-left:auto; color:#1d1d1d; font-family:'Hanken Grotesk',Arial,sans-serif; font-size:clamp(34px,4vw,48px); font-weight:700; }
.igf-campus-section-heading .igf-section-lead { margin-right:auto; margin-left:auto; color:var(--campus-green-dark); }
.igf-page-block--cards.igf-page-block--initiatives { padding:48px 5%; background:var(--campus-green-soft); }
.igf-page-block--initiatives .igf-campus-section-heading .igf-section-lead { display:none; }
.igf-campus-initiative-grid { display:grid; max-width:1020px; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; margin:0 auto; }
.igf-campus-initiative-card { display:flex; min-width:0; min-height:330px; align-items:center; padding:16px; flex-direction:column; border:0; border-radius:100px 100px 10px 10px; background:var(--campus-green-lighter); color:#1d1d1d; text-align:center; text-decoration:none; }
.igf-campus-initiative-card__media { width:100%; height:166px; overflow:hidden; border-radius:90px 90px 8px 8px; background:var(--campus-white); }
.igf-campus-initiative-card__media img { width:100%; height:100%; object-fit:cover; }
.igf-campus-initiative-card__icon { display:grid; width:132px; height:132px; place-items:center; border-radius:50%; background:var(--campus-white); color:var(--campus-green); font-size:48px; }
.igf-campus-initiative-card h3 { margin:24px 0 10px; color:#1d1d1d; font-family:'Hanken Grotesk',Arial,sans-serif; font-size:24px; font-weight:700; line-height:1.15; }
.igf-campus-initiative-card p { margin:0; color:var(--campus-green-dark); font-size:16px; line-height:1.5; }
.igf-page-block--cards.igf-page-block--contributions { padding:112px 5% 48px; background:var(--campus-green-lighter); }
.igf-page-block--contributions .igf-campus-section-heading .igf-section-lead { display:none; }
.igf-campus-contribution-grid { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:8px; }
.igf-campus-contribution-card { display:flex; min-width:0; min-height:480px; align-items:stretch; padding:16px 12px; flex-direction:column; border:1px dashed var(--campus-green); border-radius:10px; background:transparent; color:#1d1d1d; }
.igf-campus-contribution-card__media { width:100%; height:150px; overflow:hidden; border-radius:8px; background:var(--campus-white); }
.igf-campus-contribution-card__media img { width:100%; height:100%; object-fit:cover; }
.igf-campus-contribution-card__icon { display:grid; width:100%; height:150px; place-items:center; border-radius:8px; background:var(--campus-white); color:var(--campus-green); font-size:52px; }
.igf-campus-contribution-card h3 { display:flex; min-height:70px; align-items:center; justify-content:center; margin:16px 0 14px; padding:12px 8px; border-radius:10px; background:var(--campus-green); color:var(--campus-white); font-family:'Hanken Grotesk',Arial,sans-serif; font-size:16px; font-weight:600; line-height:1.25; text-align:center; }
.igf-campus-contribution-card ul { display:grid; gap:9px; margin:0 0 18px; padding:0; list-style:none; }
.igf-campus-contribution-card li { display:flex; align-items:flex-start; gap:8px; color:var(--campus-green-dark); font-size:14px; line-height:1.35; }
.igf-campus-contribution-card li i { margin-top:3px; color:var(--campus-green); }
.igf-campus-contribution-card__link { display:inline-flex; min-height:40px; align-items:center; justify-content:center; margin-top:auto; padding:9px 12px; border:1px solid var(--campus-green); border-radius:100px; color:var(--campus-green-dark); font-size:13px; font-weight:800; text-align:center; text-decoration:none; }
.igf-campus-contribution-card__link:hover,.igf-campus-contribution-card__link:focus-visible { background:var(--campus-green); color:var(--campus-white); }
.igf-page-block--campus-actions { padding:0 5% 112px; background:var(--campus-green-lighter); }
.igf-campus-actions { display:grid; max-width:1120px; grid-template-areas:'heading actions' 'lead actions'; grid-template-columns:minmax(0,1.4fr) minmax(360px,1fr); align-items:center; gap:6px 64px; padding:32px 0 0; border:0; border-top:1px solid var(--campus-green-mid); border-radius:0; background:transparent; text-align:left; }
.igf-campus-actions>.igf-page-block__eyebrow { display:none; }
.igf-campus-actions h2 { grid-area:heading; margin:0; color:#1d1d1d; font-family:'Hanken Grotesk',Arial,sans-serif; font-size:36px; font-weight:700; }
.igf-campus-actions>.igf-section-lead { grid-area:lead; margin:0; color:var(--campus-green-dark); font-size:16px; }
.igf-campus-actions .igf-page-block__actions { display:grid; grid-area:actions; grid-template-columns:1fr 1fr; gap:12px; margin:0; }
.igf-campus-actions .igf-button { min-height:52px; border-color:var(--campus-green); border-radius:100px; font-family:'Hanken Grotesk',Arial,sans-serif; font-size:15px; letter-spacing:0; text-transform:none; }
.igf-campus-actions .igf-button--primary { background:var(--campus-green); color:var(--campus-white); }
.igf-campus-actions .igf-button--outline { background:transparent; color:var(--campus-green-dark); }
.igf-page-block--campus-gallery { padding:48px 5%; background:var(--campus-white); }
.igf-page-block--campus-gallery .igf-campus-section-heading { margin-bottom:48px; }
.igf-page-block--campus-gallery .igf-campus-section-heading .igf-section-lead { display:none; }
.igf-campus-gallery__carousel { position:relative; outline:none; }
.igf-campus-gallery__carousel:focus-visible { outline:3px solid var(--campus-green); outline-offset:8px; }
.igf-campus-gallery__viewport { overflow:hidden; }
.igf-campus-gallery__track { display:flex; align-items:stretch; transition:transform 500ms ease; }
.igf-campus-gallery__slide { display:grid; min-width:100%; grid-template-columns:2fr 1fr 1fr; grid-template-rows:repeat(2,220px); gap:16px; }
.igf-campus-gallery__slide figure { min-width:0; min-height:0; border-radius:0; background:var(--campus-green-lighter); }
.igf-campus-gallery__slide figure:first-child { grid-row:span 2; }
.igf-campus-gallery__slide.is-short figure:last-child { grid-column:span 2; }
.igf-campus-gallery__slide--count-1 figure:first-child { grid-column:1/-1; grid-row:1/-1; }
.igf-campus-gallery__slide--count-2 { grid-template-columns:repeat(2,minmax(0,1fr)); }
.igf-campus-gallery__slide--count-2 figure { grid-row:1/-1; }
.igf-campus-gallery__slide--count-3 { grid-template-columns:repeat(2,minmax(0,1fr)); }
.igf-campus-gallery__slide--count-3 figure:first-child { grid-column:1; grid-row:1/-1; }
.igf-campus-gallery__slide--count-3 figure:nth-child(2) { grid-column:2; grid-row:1; }
.igf-campus-gallery__slide--count-3 figure:nth-child(3) { grid-column:2; grid-row:2/-1; }
.igf-campus-gallery__lightbox-trigger { display:block; width:100%; height:100%; padding:0; border:0; background:transparent; cursor:zoom-in; }
.igf-campus-gallery__slide figure :is(.igf-campus-gallery__lightbox-trigger,img) { display:block; width:100%; height:100%; }
.igf-campus-gallery__slide figure img { aspect-ratio:auto; object-fit:cover; }
.igf-campus-gallery__slide figure:hover img { transform:none; }
.igf-campus-gallery__lightbox-trigger:focus-visible { outline:4px solid var(--campus-green-dark); outline-offset:-4px; }
.igf-campus-gallery__arrow { position:absolute; z-index:3; top:50%; display:grid; width:56px; height:56px; place-items:center; border:0; border-radius:50%; background:var(--campus-green-light); color:var(--campus-green-dark); box-shadow:0 8px 24px rgba(64,81,59,.18); cursor:pointer; transform:translateY(-50%); }
.igf-campus-gallery__arrow--previous { left:-28px; }
.igf-campus-gallery__arrow--next { right:-28px; }
.igf-campus-gallery__arrow:disabled { opacity:.42; cursor:not-allowed; }
.igf-campus-gallery__arrow:focus-visible,.igf-campus-gallery__dots button:focus-visible { outline:3px solid var(--campus-green-dark); outline-offset:3px; }
.igf-campus-gallery__dots { display:flex; align-items:center; justify-content:center; gap:6px; height:28px; margin-top:14px; }
.igf-campus-gallery__dots button { display:grid; width:28px; height:28px; place-items:center; border:0; background:transparent; cursor:pointer; }
.igf-campus-gallery__dots span { width:8px; height:8px; border-radius:50%; background:var(--campus-green-mid); }
.igf-campus-gallery__dots button[aria-current='true'] span { width:20px; border-radius:100px; background:var(--campus-green-dark); }
.igf-campus-lightbox { position:fixed; z-index:100000; inset:0; display:grid; place-items:center; padding:24px; background:rgba(10,17,9,.88); }
.igf-campus-lightbox__dialog { position:relative; display:grid; width:min(1100px,100%); height:min(82vh,760px); grid-template-columns:64px minmax(0,1fr) 64px; align-items:center; outline:none; }
.igf-campus-lightbox__dialog figure { display:flex; width:100%; height:100%; align-items:center; justify-content:center; margin:0; flex-direction:column; background:transparent; }
.igf-campus-lightbox__dialog figure img { width:100%; height:calc(100% - 44px); max-height:700px; object-fit:contain; }
.igf-campus-lightbox__dialog figcaption { position:static; width:100%; padding:12px 16px 0; background:none; color:#fff; text-align:center; }
.igf-campus-lightbox__close,.igf-campus-lightbox__nav { display:grid; width:48px; height:48px; place-items:center; border:0; border-radius:50%; background:var(--campus-green-light); color:var(--campus-green-dark); cursor:pointer; }
.igf-campus-lightbox__close { position:absolute; z-index:2; top:0; right:0; }
.igf-campus-lightbox__nav { justify-self:center; }
.igf-campus-lightbox__nav:disabled { opacity:.35; cursor:not-allowed; }
.igf-campus-lightbox__close:focus-visible,.igf-campus-lightbox__nav:focus-visible { outline:3px solid #fff; outline-offset:3px; }
.igf-video { max-width:1040px; }
.igf-video__frame { overflow:hidden; aspect-ratio:16/9; margin-top:34px; border-radius:20px; background:#171717; box-shadow:0 18px 44px rgba(25,28,29,.16); }
.igf-video__frame :is(iframe,video) { width:100%; height:100%; border:0; object-fit:contain; }
.igf-video__caption { margin-top:12px; font-size:13px; text-align:center; }
.igf-event-cards { display:grid; grid-template-columns:repeat(var(--igf-card-columns,3),minmax(0,1fr)); gap:24px; }
.igf-event-cards>a { position:relative; display:flex; overflow:hidden; min-height:430px; flex-direction:column; border:1px solid var(--igf-card-border,var(--line)); border-radius:var(--igf-card-radius,17px); background:#fff; color:var(--ink); text-decoration:none; box-shadow:var(--igf-card-shadow,0 4px 14px rgba(25,28,29,.05)); }
.igf-event-cards>a>img { width:100%; height:var(--igf-card-media-height,230px); object-fit:cover; }
.igf-event-cards__date { position:absolute; top:16px; left:16px; display:grid; min-width:58px; min-height:58px; place-content:center; border-radius:10px; background:#fff; box-shadow:0 5px 16px rgba(0,0,0,.14); text-align:center; }
.igf-event-cards__date strong { font:650 22px/1 'Literata',Georgia,serif; }
.igf-event-cards__date small { margin-top:3px; color:var(--brown); font-size:10px; font-weight:900; text-transform:uppercase; }
.igf-event-cards__copy { display:flex; padding:25px; flex:1; flex-direction:column; }
.igf-event-cards__copy h3 { margin-bottom:11px; }
.igf-event-cards__copy p { margin:0 0 20px; font-size:14px; line-height:1.6; }
.igf-event-cards__copy b { margin-top:auto; color:var(--brown); font-size:13px; }
.igf-page-block--testimonials { overflow:hidden; background:#242220; color:#fff; }
.igf-testimonials>h2 { color:#fff; }
.igf-testimonial-card { position:relative; max-width:920px; margin:42px auto 0; padding:clamp(30px,6vw,65px); border:1px solid var(--igf-card-border,rgba(255,255,255,.15)); border-radius:var(--igf-card-radius,24px); background:#30302f; box-shadow:var(--igf-card-shadow,none); text-align:center; }
.igf-testimonial-card>i { color:var(--orange); font-size:42px; }
.igf-testimonial-card blockquote { max-width:760px; margin:24px auto 30px; color:#f1efec; font:500 var(--igf-testimonial-text-size,clamp(22px,3vw,33px))/1.5 'Literata',Georgia,serif; }
.igf-testimonial-person { display:flex; align-items:center; justify-content:center; gap:14px; }
.igf-testimonial-person img { width:64px; height:64px; border:3px solid #fff; border-radius:50%; object-fit:cover; }
.igf-testimonial-person span { display:grid; gap:3px; text-align:left; }
.igf-testimonial-person strong { color:#fff; }
.igf-testimonial-person small { color:#bbb; }
.igf-testimonial-card nav { display:flex; align-items:center; justify-content:center; gap:7px; margin-top:32px; }
.igf-testimonial-card nav button { display:grid; min-width:42px; min-height:42px; place-items:center; border:1px solid rgba(255,255,255,.3); border-radius:50%; background:transparent; color:#fff; }
.igf-testimonial-card nav button:not(.igf-testimonial-dot) { border-radius:var(--igf-button-radius,50%); }
.igf-testimonial-card nav button:hover,.igf-testimonial-card nav button:focus-visible { border-color:var(--orange); background:var(--brown); }
.igf-testimonial-card nav .igf-testimonial-dot { min-width:28px; border-color:transparent; }
.igf-testimonial-dot span { width:8px; height:8px; border-radius:50%; background:#777; }
.igf-testimonial-dot[aria-current="true"] span { width:18px; border-radius:99px; background:var(--orange); }
.igf-page-block--programs { background:var(--surface); }
.igf-page-block--programs .igf-card { min-height:230px; }
.igf-campaign { display:grid; overflow:hidden; grid-template-columns:minmax(0,.82fr) minmax(420px,1.18fr); border:1px solid #e3d8d0; border-radius:26px; background:#fff; box-shadow:0 24px 60px rgba(41,31,23,.13); }
.igf-campaign__story,.igf-campaign__form { padding:clamp(30px,5vw,52px); }
.igf-campaign__story { display:flex; justify-content:center; flex-direction:column; background:linear-gradient(145deg,#292522 0%,#4d2c18 100%); color:#fff; }
.igf-campaign__story :is(h2,p) { color:#fff; }
.igf-campaign__story .igf-page-block__eyebrow { color:#ffbc8a; }
.igf-campaign__story p:not(.igf-page-block__eyebrow) { font-size:18px; line-height:1.65; }
.igf-progress-meta { display:flex; justify-content:space-between; gap:20px; margin-top:34px; font-size:13px; }
.igf-progress-meta span { color:var(--muted); }
.igf-progress { overflow:hidden; height:12px; margin-top:12px; border-radius:999px; background:#dcdcdc; }
.igf-progress span { display:block; height:100%; border-radius:inherit; background:var(--orange); }
.igf-progress-label { display:block; margin-top:7px; color:var(--brown); font-size:13px; text-align:right; }
.igf-campaign__form-header h3 { margin:0; font-size:30px; }
.igf-campaign__form-header p { margin:5px 0 0; color:var(--muted); font-size:13px; line-height:1.5; }
.igf-campaign-frequency { min-width:0; margin:19px 0 0; border:0; padding:0; }
.igf-campaign-frequency__tabs { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:5px; border:1px solid #e5d9d0; border-radius:13px; padding:5px; background:#f4eee9; }
.igf-campaign-frequency__tabs label,.igf-campaign-frequency__tabs button { display:flex; min-width:0; min-height:54px; align-items:center; justify-content:center; gap:1px; flex-direction:column; border:0; border-radius:9px; padding:6px 2px; background:transparent; color:#66625f; font:800 12px 'Hanken Grotesk',Arial,sans-serif; text-align:center; }
.igf-campaign-frequency__tabs label.is-selected { background:var(--brown); color:#fff; box-shadow:0 5px 13px rgba(104,45,8,.22); }
.igf-campaign-frequency__tabs button { cursor:not-allowed; }
.igf-campaign-frequency__tabs button small { color:#895126; font-size:7px; font-weight:900; letter-spacing:.04em; line-height:1; text-transform:uppercase; }
.igf-campaign-frequency__tabs input,.igf-amounts input { position:absolute; opacity:0; pointer-events:none; }
.igf-campaign-frequency__tabs label:focus-within { outline:3px solid rgba(156,69,0,.28); outline-offset:2px; }
.igf-campaign-frequency>p { display:flex; gap:7px; margin:8px 2px 0; color:#716a65; font-size:10px; line-height:1.4; }
.igf-campaign-frequency>p i { margin-top:2px; color:var(--brown); }
.igf-amounts { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin:19px 0 14px; }
.igf-amounts label { position:relative; min-width:0; cursor:pointer; }
.igf-amounts label>span { position:relative; display:grid; min-height:92px; align-content:center; gap:4px; border:1.5px solid #ddd1c8; border-radius:13px; padding:13px 40px 13px 14px; background:#fff; text-align:left; transition:border-color .16s,background-color .16s,transform .16s; }
.igf-amounts label:hover>span { border-color:#cf8652; transform:translateY(-1px); }
.igf-amounts label.is-featured>span { background:#fff7f0; }
.igf-amounts strong { color:var(--brown); font:650 21px/1.1 'Literata',Georgia,serif; }
.igf-amounts small { color:var(--muted); font-size:10px; font-weight:500; line-height:1.35; }
.igf-amounts i { position:absolute; top:11px; right:11px; display:grid; width:20px; height:20px; place-items:center; border:1px solid #d8cec6; border-radius:50%; background:#fff; color:transparent; font-size:9px; }
.igf-amounts input:checked+span { border-color:var(--orange); background:#ffe3cf; box-shadow:inset 0 0 0 1px var(--orange); }
.igf-amounts input:checked+span i { border-color:var(--brown); background:var(--brown); color:#fff; }
.igf-amounts label:focus-within>span { outline:3px solid rgba(156,69,0,.26); outline-offset:3px; }
.igf-custom-amount { position:relative; display:flex; align-items:center; margin-bottom:20px; }
.igf-custom-amount b { position:absolute; left:14px; }
.igf-custom-amount input { width:100%; padding:14px 14px 14px 38px; border:1px solid #c9c5c1; border-radius:12px; }
.igf-custom-amount input:focus { border-color:var(--brown); outline:3px solid rgba(156,69,0,.2); }
.igf-campaign__form .igf-button { width:100%; min-height:56px; border-radius:13px; font-size:15px; letter-spacing:0; text-transform:none; }
.igf-campaign__assurance,.igf-campaign__secure { display:flex; align-items:center; justify-content:center; gap:7px; margin:12px 0 0; color:var(--muted); font-size:11px; text-align:center; }
.igf-campaign__assurance>span { width:7px; height:7px; border-radius:50%; background:var(--orange); }
.igf-campaign__secure { margin-top:5px; }
.igf-campaign__secure i { color:var(--brown); }
.igf-page-block--story .igf-media-shell { overflow:hidden; border-radius:24px; background:#eceeef; }
.igf-page-block--story .igf-media-text { gap:0; }
.igf-page-block--story .igf-media-text__content { padding:clamp(32px,5vw,62px); }
.igf-updates>h2 { margin-bottom:42px; }
.igf-update-columns { display:grid; grid-template-columns:1fr 1fr; gap:70px; }
.igf-update-columns>div>header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; padding-bottom:16px; border-bottom:1px solid var(--line); }
.igf-update-columns header h3 { font-size:24px; }
.igf-update-columns header a { color:var(--brown); font-size:12px; font-weight:800; text-transform:uppercase; }
.igf-event-row,.igf-news-row { display:flex; align-items:center; gap:20px; padding:14px; border-radius:12px; color:var(--ink); text-decoration:none; }
.igf-event-row:hover,.igf-news-row:hover { background:#eceeef; }
.igf-date { display:grid; width:70px; height:70px; flex:0 0 70px; place-content:center; border:1px solid var(--line); border-radius:9px; background:#fff; text-align:center; }
.igf-date small { color:var(--brown); font-size:10px; font-weight:800; }
.igf-date strong { font-size:20px; }
.igf-event-row>span:last-child,.igf-news-row>span { display:grid; gap:5px; }
.igf-event-row>span:last-child>small { color:var(--muted); font-size:13px; line-height:1.4; }
.igf-news-row img { width:90px; height:90px; flex:0 0 90px; border-radius:9px; object-fit:cover; }
.igf-news-row span small { color:var(--muted); font-size:11px; font-weight:800; text-transform:uppercase; }
.igf-rich-text { text-align:center; }
.igf-rich-text h2,.igf-rich-text .igf-page-block__copy { margin-right:auto; margin-left:auto; }
.igf-rich-text .igf-page-block__copy { max-width:780px; }
.igf-accountability-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:18px; margin-top:42px; }
.igf-accountability-grid a { display:flex; min-height:150px; align-items:center; justify-content:center; gap:14px; flex-direction:column; border:1px solid var(--line); border-radius:16px; color:var(--ink); text-decoration:none; }
.igf-accountability-grid i { color:var(--muted); font-size:30px; }
.igf-accountability-grid a:hover { border-color:var(--orange); color:var(--brown); }
.igf-page-block--partners { padding-top:clamp(48px,6vw,76px); padding-bottom:clamp(54px,7vw,86px); border:0; background:#eef3fb; text-align:center; }
.igf-partners>.igf-page-block__eyebrow { margin-bottom:10px; color:#4d535c; }
.igf-partner-heading { width:fit-content; max-width:100%; margin:0 auto; }
.igf-partners h2 { margin:0; color:#22262c; font-family:'Hanken Grotesk',Arial,sans-serif; font-size:clamp(36px,5vw,48px); font-weight:750; letter-spacing:-.035em; line-height:1.12; }
.igf-partner-underline { display:block; width:clamp(130px,24vw,165px); height:14px; margin:10px 0 0; border-top:4px solid var(--orange); border-radius:50%; transform:rotate(1.5deg); }
.igf-partners>.igf-section-lead { max-width:760px; margin:16px auto 0; color:#4d5664; font-size:clamp(16px,2vw,20px); line-height:1.55; }
.igf-partner-list { display:grid; width:min(100%,1120px); grid-template-columns:repeat(5,minmax(0,1fr)); gap:16px; margin:18px auto 0; padding:0; list-style:none; }
.igf-partner-list li { min-width:0; margin:0; }
.igf-partner-card { display:flex; width:100%; min-height:104px; align-items:center; justify-content:center; overflow:hidden; border:1px solid #dce2ea; border-radius:9px; padding:13px 16px; background:rgba(255,255,255,.72); box-shadow:0 2px 4px rgba(42,52,65,.22); color:var(--ink); text-decoration:none; transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease; }
.igf-partner-card img { display:block; width:100%; height:66px; object-fit:contain; filter:none; opacity:1; }
.igf-partner-card strong { font-size:14px; line-height:1.35; }
.igf-partner-card__fallback { display:none; }
.igf-partner-card img[hidden]+.igf-partner-card__fallback { display:block; }
.igf-partner-card:focus-visible { outline:3px solid color-mix(in srgb,var(--orange) 42%,white); outline-offset:3px; }
.igf-callout { padding:clamp(38px,7vw,70px); border:1px solid rgba(255,117,0,.24); border-radius:24px; background:#fff7f1; text-align:center; }
.igf-callout>i { margin-bottom:18px; color:var(--orange); font-size:38px; }
.igf-callout h2,.igf-callout .igf-section-lead { margin-right:auto; margin-left:auto; }
.igf-callout .igf-page-block__actions { justify-content:center; }
.igf-page-block--newsletter { padding-top:78px; padding-bottom:78px; background:#e4e5e6; }
.igf-newsletter { max-width:760px; text-align:center; }
.igf-newsletter h2 { margin-right:auto; margin-left:auto; font-size:38px; }
.igf-newsletter>p { margin-bottom:28px; }
.igf-newsletter form { display:grid; grid-template-columns:1fr auto; gap:12px; max-width:560px; margin:0 auto; text-align:left; }
.igf-newsletter form>input { min-width:0; padding:13px 15px; border:1px solid #c5c5c5; border-radius:8px; background:#fff; }
.igf-newsletter form>button { padding:13px 24px; border:0; border-radius:8px; background:var(--ink); color:#fff; font-weight:800; }
.igf-consent,.igf-newsletter__message { grid-column:1/-1; }
.igf-consent { display:flex; align-items:flex-start; gap:9px; color:var(--muted); font-size:12px; }
.igf-consent input { margin-top:2px; }
.igf-consent a { color:inherit; text-decoration:underline; }
.igf-newsletter__message { margin:0!important; padding:9px 12px; border:1px solid transparent; border-radius:7px; font-size:13px!important; }
.igf-newsletter__message.is-success { border-color:#8fc89f; background:#eef9f1; color:#245c34; }
.igf-newsletter__message.is-error { border-color:#d59b95; background:#fff1ef; color:#8d271f; }
.igf-newsletter form>input[aria-invalid="true"] { border-color:#a52b1f; outline:3px solid rgba(165,43,31,.16); }
.sr-only { position:absolute!important; width:1px!important; height:1px!important; overflow:hidden!important; clip:rect(0,0,0,0)!important; white-space:nowrap!important; }
.igf-page-block--desktop-hidden { display:none; }
@media (max-width:991px) {
  .igf-page-block--campus-stats .igf-stats { grid-template-columns:1fr; gap:48px; }
  .igf-page-block--campus-stats .igf-stat strong { font-size:64px; }
  .igf-campus-initiative-grid,.igf-campus-contribution-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:24px; }
  .igf-campus-contribution-card { min-height:430px; }
  .igf-campus-actions { grid-template-columns:1fr 1fr; gap:24px 40px; }
  .igf-campus-actions .igf-page-block__actions { grid-template-columns:1fr; }
}
@media (max-width:960px) {
  .igf-stats { grid-template-columns:repeat(2,1fr); }
  .igf-card-grid { grid-template-columns:repeat(2,1fr); }
  .igf-focus-areas { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .igf-giving__options { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .igf-event-cards { grid-template-columns:repeat(2,1fr); }
  .igf-gallery__grid { grid-template-columns:repeat(2,1fr); }
  .igf-partner-list { width:min(calc(100% - 28px),700px); grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
  .igf-partner-card { min-height:82px; padding:10px 12px; }
  .igf-partner-card img { height:58px; }
  .igf-campaign,.igf-update-columns { grid-template-columns:1fr; }
  .igf-update-columns { gap:48px; }
  .igf-accountability-grid { grid-template-columns:repeat(2,1fr); }
  .igf-page-block--contributions .igf-card-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .igf-page-block--campus-gallery .igf-gallery__grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .igf-page-block--campus-gallery .igf-gallery figure:first-child { grid-row:auto; }
  .igf-page-block--campus-gallery .igf-gallery figure:last-child { grid-column:auto; }
}
@media (max-width:767px) {
  .igf-page-block { padding:var(--igf-section-mobile,68px) 20px; }
  .igf-page-block--partners { padding-top:28px; padding-bottom:68px; }
  .igf-page-block--partners .igf-partners h2 { font-size:clamp(38px,6vw,44px); }
  .igf-page-block--hero { min-height:var(--igf-hero-height-mobile,680px); padding:54px 20px 105px; align-items:end; }
  .igf-page-block__hero-content { padding:27px 23px; }
  .igf-hero-carousel__controls { right:18px; bottom:68px; left:18px; justify-content:center; }
  .igf-hero-carousel__dots { order:2; }
  .igf-hero-carousel__controls>.igf-hero-carousel__arrow:first-child { order:1; }
  .igf-hero-carousel__controls>.igf-hero-carousel__arrow:nth-of-type(2) { order:3; }
  .igf-hero-carousel__pause { order:4; }
  .igf-page-blocks h1 { font-size:var(--igf-heading-1-mobile,42px); }
  .igf-page-blocks h2 { font-size:var(--igf-heading-2-mobile,34px); }
  .igf-page-block__actions { align-items:stretch; flex-direction:column; }
  .igf-button { width:100%; }
  .igf-page-block--stats { margin-top:-50px; padding-top:0; padding-bottom:72px; }
  .igf-stats,.igf-card-grid,.igf-media-text,.igf-media-text--reverse { grid-template-columns:1fr; }
  .igf-focus-areas { grid-template-columns:1fr; }
  .igf-focus-areas__heading,.igf-focus-area-card { min-height:320px; }
  .igf-giving__heading { margin-bottom:26px; text-align:left; }
  .igf-giving__options { grid-template-columns:1fr; gap:14px; }
  .igf-giving--single_cta .igf-giving-card { flex-direction:column; }
  .igf-giving--single_cta .igf-giving-card__media { width:100%; height:190px; min-height:0; }
  .igf-giving--banner { padding:26px 20px; border-radius:18px; }
  .igf-giving--banner .igf-giving__options { grid-template-columns:1fr; }
  .igf-event-cards { grid-template-columns:1fr; }
  .igf-team-grid,.igf-gallery__grid { grid-template-columns:1fr; }
  .igf-page-block--contributions .igf-card-grid,.igf-page-block--campus-gallery .igf-gallery__grid { grid-template-columns:1fr; }
  .igf-team-card { justify-self:center; }
  .igf-stat { padding:24px; }
  .igf-media-text--reverse .igf-media-text__figure { order:0; }
  .igf-media-text__media { --igf-media-default-aspect:var(--igf-image-aspect,4 / 3); }
  .igf-section-heading { display:block; margin-bottom:30px; }
  .igf-section-heading>.igf-text-link { margin-top:12px; }
  .igf-card-grid { gap:16px; }
  .igf-campaign__story,.igf-campaign__form { padding:28px 22px; }
  .igf-progress-meta { align-items:flex-start; flex-direction:column; gap:4px; }
  .igf-update-columns { gap:40px; }
  .igf-event-row,.igf-news-row { padding:10px 0; }
  .igf-accountability-grid { grid-template-columns:1fr 1fr; gap:10px; }
  .igf-accountability-grid a { min-height:125px; padding:14px; font-size:13px; }
  .igf-newsletter form { grid-template-columns:1fr; }
  .igf-consent,.igf-newsletter__message { grid-column:1; }
  .igf-page-block--desktop-hidden { display:block; }
  .igf-page-block--mobile-hidden { display:none; }
  .igf-page-block--hero.igf-page-block--campus { min-height:340px; padding:128px 5% 64px; align-items:center; }
  .igf-page-block--hero.igf-page-block--campus h1 { font-size:40px; }
  .igf-page-block--hero.igf-page-block--campus .igf-page-block__lead { font-size:18px; }
  .igf-page-block--campus-intro { padding:48px 5%; }
  .igf-page-block--campus-intro .igf-page-block__copy { font-size:16px; }
  .igf-page-block--stats.igf-page-block--campus-stats { padding:64px 5%; }
  .igf-page-block--campus-stats h2 { margin-bottom:48px; font-size:32px; }
  .igf-page-block--campus-stats .igf-stat strong { font-size:56px; }
  .igf-page-block--cards.igf-page-block--initiatives { padding:48px 5%; }
  .igf-campus-section-heading { margin-bottom:40px; }
  .igf-campus-initiative-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .igf-page-block--cards.igf-page-block--contributions { padding:64px 5% 48px; }
  .igf-campus-contribution-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .igf-page-block--campus-actions { padding:0 5% 64px; }
  .igf-campus-actions { grid-template-columns:1fr 1fr; gap:22px 28px; }
  .igf-campus-actions h2 { font-size:30px; }
  .igf-campus-actions .igf-page-block__actions { align-items:stretch; grid-template-columns:1fr; }
  .igf-page-block--campus-gallery { padding:48px 5%; }
  .igf-campus-gallery__slide { height:588px; grid-template-columns:repeat(2,minmax(0,1fr)); grid-template-rows:repeat(3,minmax(0,1fr)); gap:24px; }
  .igf-campus-gallery__slide figure:first-child { grid-row:span 2; }
  .igf-campus-gallery__slide.is-short figure:last-child { grid-column:span 2; }
  .igf-campus-gallery__slide--count-1 { grid-template-columns:1fr; grid-template-rows:minmax(0,1fr); }
  .igf-campus-gallery__slide--count-1 figure:first-child { grid-column:1; grid-row:1; }
  .igf-campus-gallery__slide--count-2 { grid-template-columns:repeat(2,minmax(0,1fr)); grid-template-rows:minmax(0,1fr); }
  .igf-campus-gallery__slide--count-2 figure,.igf-campus-gallery__slide--count-2 figure:first-child { grid-row:1; }
  .igf-campus-gallery__slide--count-3 { grid-template-columns:repeat(2,minmax(0,1fr)); grid-template-rows:repeat(2,minmax(0,1fr)); }
  .igf-campus-gallery__slide--count-3 figure:first-child { grid-column:1; grid-row:1/-1; }
  .igf-campus-gallery__slide--count-3 figure:nth-child(2) { grid-column:2; grid-row:1; }
  .igf-campus-gallery__slide--count-3 figure:nth-child(3) { grid-column:2; grid-row:2; }
  .igf-campus-gallery__arrow--previous { left:-16px; }
  .igf-campus-gallery__arrow--next { right:-16px; }
  .igf-campus-lightbox { padding:14px; }
  .igf-campus-lightbox__dialog { height:88vh; grid-template-columns:48px minmax(0,1fr) 48px; }
  .igf-campus-lightbox__close,.igf-campus-lightbox__nav { width:42px; height:42px; }
}
@media (max-width:560px) {
  .igf-partner-list { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .igf-partner-card { min-height:92px; }
  .igf-partner-card img { height:64px; }
}
@media (max-width:680px) {
  .igf-team-grid { grid-template-columns:280px; }
  .igf-team-card { --igf-team-card-height:420px; width:280px; height:var(--igf-team-card-height); }
}
@media (max-width:360px) {
  .igf-team-grid { grid-template-columns:260px; }
  .igf-team-card { width:260px; }
}
@media (max-width:420px) {
  .igf-stats { grid-template-columns:1fr; }
  .igf-page-block__eyebrow--inverse { font-size:10px; }
  .igf-card-grid { grid-template-columns:1fr; }
  .igf-campaign-frequency__tabs { grid-template-columns:1fr 1fr; }
  .igf-amounts { grid-template-columns:1fr; }
}
@media (max-width:479px) {
  .igf-campus-initiative-grid,.igf-campus-contribution-grid { grid-template-columns:1fr; }
  .igf-campus-initiative-card { width:min(100%,340px); justify-self:center; }
  .igf-campus-contribution-card { min-height:0; padding:32px 16px; }
  .igf-campus-actions { grid-template-areas:'heading' 'lead' 'actions'; grid-template-columns:1fr; text-align:center; }
  .igf-campus-actions>.igf-section-lead { margin-right:auto; margin-left:auto; }
  .igf-campus-actions .igf-page-block__actions { width:100%; grid-template-columns:1fr; }
  .igf-campus-gallery__slide { height:auto; grid-template-columns:repeat(2,minmax(0,1fr)); grid-auto-rows:160px; grid-template-rows:none; gap:16px; }
  .igf-campus-gallery__slide figure:first-child { grid-column:1/-1; grid-row:auto; }
  .igf-campus-gallery__slide.is-short figure:last-child { grid-column:auto; }
  .igf-campus-gallery__slide--count-1 { grid-template-columns:1fr; }
  .igf-campus-gallery__slide--count-1 figure:first-child { grid-column:auto; grid-row:auto; }
  .igf-campus-gallery__slide--count-2 figure { grid-column:auto; grid-row:auto; }
  .igf-campus-gallery__slide--count-3 figure:first-child { grid-column:1/-1; grid-row:auto; }
  .igf-campus-gallery__slide--count-3 figure:nth-child(2),.igf-campus-gallery__slide--count-3 figure:nth-child(3) { grid-column:auto; grid-row:auto; }
  .igf-campus-gallery__arrow { display:none; }
  .igf-campus-lightbox__dialog { grid-template-columns:1fr; }
  .igf-campus-lightbox__nav { position:absolute; z-index:2; bottom:4px; }
  .igf-campus-lightbox__nav--previous { left:8px; }
  .igf-campus-lightbox__nav--next { right:8px; }
}
@media (prefers-reduced-motion:reduce) {
  .igf-page-blocks * { scroll-behavior:auto!important; transition:none!important; animation:none!important; }
  .igf-stat--animated { opacity:1!important; transform:none!important; }
  .igf-focus-area__reveal { opacity:1!important; transform:none!important; }
  .igf-team-card { height:auto; min-height:var(--igf-team-card-height); perspective:none; }
  .igf-team-card__stage,.igf-team-card__flipper { height:auto; }
  .igf-team-card__flipper { transform:none!important; }
  .igf-team-card__face { position:relative; inset:auto; transform:none; backface-visibility:visible; -webkit-backface-visibility:visible; }
  .igf-team-card__front { height:var(--igf-team-card-height); }
  .igf-team-card__back { display:none; overflow:visible; border-radius:0 0 12px 12px; }
  .igf-team-card__back-content { overflow:visible; }
  .igf-team-card__toggle { top:0; right:0; bottom:auto; left:0; height:var(--igf-team-card-height); }
  .igf-team-card.is-open .igf-team-card__front { border-radius:12px 12px 0 0; }
  .igf-team-card.is-open .igf-team-card__back { display:flex; }
}
</style>
