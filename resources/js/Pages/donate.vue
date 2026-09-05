<template>
  <Layout>
    <div class="igf-donate" :class="[`is-layout-${checkoutLayout}`, `is-card-${cardStyle}`, { 'has-hero': settings.show_hero, 'has-cause-gallery': showCauseGallery, 'is-cause-page': isCausePage }]">
      <header v-if="isCatalogPage && settings.show_hero" class="igf-donate__hero">
        <img v-if="settings.hero_image" class="igf-donate__hero-image" :src="settings.hero_image" alt="" aria-hidden="true" fetchpriority="high">
        <span class="igf-donate__hero-overlay" aria-hidden="true" />
        <div class="igf-shell igf-donate__hero-grid">
          <div class="igf-donate__hero-copy">
            <p class="igf-eyebrow">{{ settings.eyebrow }}</p>
            <h1>{{ settings.title }}</h1>
            <p class="igf-donate__lead">{{ settings.introduction }}</p>
          </div>
          <ul v-if="settings.show_assurances" class="igf-trust-list" :aria-label="settings.assurances_accessible_label">
            <li><i class="fa-solid fa-lock" aria-hidden="true" /><span><strong>{{ settings.secure_title }}</strong>{{ settings.secure_body }}</span></li>
            <li><i class="fa-solid fa-receipt" aria-hidden="true" /><span><strong>{{ settings.confirmation_title }}</strong>{{ settings.confirmation_body }}</span></li>
            <li><i class="fa-solid fa-chart-line" aria-hidden="true" /><span><strong>{{ settings.impact_title }}</strong>{{ settings.impact_body }}</span></li>
          </ul>
        </div>
      </header>

      <header v-if="isCausePage" class="igf-donate__hero igf-donate__hero--cause">
        <img v-if="selectedCause?.image" class="igf-donate__hero-image" :src="selectedCause.image" alt="" aria-hidden="true" fetchpriority="high">
        <span class="igf-donate__hero-overlay" aria-hidden="true" />
        <div class="igf-shell igf-donate__hero-grid">
          <div class="igf-donate__hero-copy">
            <a class="igf-cause-back-link" :href="catalogUrl"><span aria-hidden="true">&larr;</span> {{ settings.cause_catalog_back_label || 'All donation causes' }}</a>
            <p class="igf-eyebrow">{{ settings.form_badge || 'Secure donation' }}</p>
            <h1>{{ selectedCause?.name || settings.title }}</h1>
            <p class="igf-donate__lead">{{ selectedCause?.description || selectionWarning || settings.introduction }}</p>
          </div>
          <ul v-if="settings.show_assurances" class="igf-trust-list" :aria-label="settings.assurances_accessible_label">
            <li><i class="fa-solid fa-lock" aria-hidden="true" /><span><strong>{{ settings.secure_title }}</strong>{{ settings.secure_body }}</span></li>
            <li><i class="fa-solid fa-receipt" aria-hidden="true" /><span><strong>{{ settings.confirmation_title }}</strong>{{ settings.confirmation_body }}</span></li>
            <li><i class="fa-solid fa-chart-line" aria-hidden="true" /><span><strong>{{ settings.impact_title }}</strong>{{ settings.impact_body }}</span></li>
          </ul>
        </div>
      </header>

      <section v-if="isCausePage && causeLandingSections.length" class="igf-cause-content" :aria-label="settings.cause_content_accessible_label" data-test="cause-landing-content">
        <div class="igf-shell igf-cause-content__list">
          <article v-for="section in causeLandingSections" :key="section.uuid" class="igf-cause-content__section" :class="`is-${section.layout}`" data-test="cause-landing-section">
            <div v-if="section.image || section.video" class="igf-cause-content__media">
              <img v-if="section.image" :src="section.image" :alt="section.image_alt" loading="lazy" width="720" height="450">
              <video v-else-if="section.video?.type === 'file'" :src="section.video.url" :aria-label="section.video.title" :aria-describedby="section.video.transcript ? causeVideoTranscriptId(section) : undefined" controls preload="metadata" playsinline />
              <iframe v-else-if="section.video?.type === 'embed'" :src="section.video.url" :title="section.video.title" :aria-describedby="section.video.transcript ? causeVideoTranscriptId(section) : undefined" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-scripts allow-same-origin allow-presentation" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen />
            </div>
            <div class="igf-cause-content__copy">
              <h2 v-if="section.title">{{ section.title }}</h2>
              <!-- eslint-disable-next-line vue/no-v-html -- HTML is sanitized on write and again in the public server contract. -->
              <div v-if="section.body" class="igf-cause-content__rich-text" v-html="section.body" />
              <div v-if="section.video?.transcript" :id="causeVideoTranscriptId(section)" class="igf-cause-content__transcript" data-test="cause-video-transcript">
                <h3>{{ section.video.title }}</h3>
                <p>{{ section.video.transcript }}</p>
              </div>
              <a v-if="section.cta" class="igf-cause-content__cta" :href="section.cta.url">{{ section.cta.label }} <span aria-hidden="true">&rarr;</span></a>
            </div>
          </article>
        </div>
      </section>

      <section v-if="showCauseGallery" class="igf-donate-causes" aria-labelledby="donation-causes-title">
        <div class="igf-shell">
          <header class="igf-donate-causes__header">
            <p class="igf-eyebrow">{{ settings.cause_gallery_eyebrow }}</p>
            <h2 id="donation-causes-title">{{ settings.cause_gallery_title }}</h2>
            <p v-if="settings.cause_gallery_introduction">{{ settings.cause_gallery_introduction }}</p>
          </header>
          <template v-if="donationTypes.length">
            <div
              class="igf-donate-causes__tabs"
              data-test="donation-cause-tablist"
              role="tablist"
              aria-orientation="horizontal"
              :aria-label="donationTabsLabel"
              @keydown="handleDonationTabKeydown"
            >
              <button
                v-for="tab in donationTabs"
                :id="donationTabId(tab)"
                :key="tab.key"
                type="button"
                class="igf-donate-causes__tab"
                :class="{ 'is-active': isActiveDonationTab(tab) }"
                data-test="donation-cause-tab"
                role="tab"
                :aria-selected="isActiveDonationTab(tab) ? 'true' : 'false'"
                :aria-controls="donationPanelId(tab)"
                :tabindex="isActiveDonationTab(tab) ? 0 : -1"
                @click="selectDonationTab(tab, $event.currentTarget)"
              >
                {{ tab.name }}
              </button>
            </div>
            <div
              v-for="tab in donationTabs"
              :id="donationPanelId(tab)"
              :key="`${tab.key}-panel`"
              class="igf-donate-causes__panel"
              data-test="donation-cause-panel"
              role="tabpanel"
              :aria-labelledby="donationTabId(tab)"
              :aria-describedby="tab.description ? donationTabDescriptionId(tab) : undefined"
              tabindex="0"
              :hidden="!isActiveDonationTab(tab)"
            >
              <p
                v-if="tab.description"
                :id="donationTabDescriptionId(tab)"
                class="igf-donate-causes__tab-description"
              >
                {{ tab.description }}
              </p>
              <div v-if="isActiveDonationTab(tab)" class="igf-donate-causes__grid">
                <article v-for="(cause, index) in filteredDonationTypes" :key="cause.uuid" class="igf-donation-cause-card"
                  data-test="donation-cause-card">
                  <div class="igf-donation-cause-card__media">
                    <img v-if="cause.image" :src="cause.image" alt="" width="720" height="450" loading="lazy">
                    <div v-else class="igf-donation-cause-card__placeholder" :data-variant="(index % 6) + 1" aria-hidden="true">
                      <i :class="causeCardIcon(cause)" />
                    </div>
                  </div>
                  <div class="igf-donation-cause-card__body">
                    <h3 :id="causeCardId(cause)">{{ cause.name }}</h3>
                    <p v-if="cause.description">{{ cause.description }}</p>
                    <a data-test="donation-cause-link" :href="causePageUrl(cause)"
                      :aria-label="`${settings.cause_card_cta_label || 'Donate to this cause'}: ${cause.name}`"
                      :aria-describedby="causeCardId(cause)">
                      {{ settings.cause_card_cta_label || 'Donate to this cause' }}
                      <span aria-hidden="true">&rarr;</span>
                    </a>
                  </div>
                </article>
              </div>
            </div>
          </template>
          <p v-else class="igf-donate-causes__empty" data-test="donation-catalog-empty" role="status" aria-live="polite">
            {{ settings.causes_unavailable_message || 'Donation causes are being updated. Please contact us before attempting a payment.' }}
          </p>
        </div>
      </section>

      <section v-if="isCausePage" class="igf-donate__section" aria-labelledby="donation-form-title">
        <div class="igf-shell igf-donate__layout">
          <aside v-if="selectedCause || settings.show_help_card" class="igf-donate__aside">
            <article v-if="selectedCause" class="igf-cause-story" aria-labelledby="selected-cause-story-title">
              <div class="igf-cause-story__media">
                <img v-if="selectedCause.image" :src="selectedCause.image" alt="" width="720" height="450">
                <div v-else class="igf-donation-cause-card__placeholder" aria-hidden="true">
                  <i :class="causeCardIcon(selectedCause)" />
                </div>
              </div>
              <div class="igf-cause-story__body">
                <p class="igf-eyebrow">{{ settings.selected_cause_eyebrow || 'Your selected cause' }}</p>
                <h2 id="selected-cause-story-title">{{ selectedCause.name }}</h2>
                <p v-if="selectedCause.description">{{ selectedCause.description }}</p>
                <div class="igf-cause-story__destination">
                  <i class="fa-solid fa-hand-holding-heart" aria-hidden="true" />
                  <span><small>{{ settings.destination_label || 'Donation destination' }}</small><strong>{{ confirmedDestinationName }}</strong></span>
                </div>
                <a v-if="settings.show_reports_link" :href="settings.reports_url" class="igf-text-link">{{ settings.reports_label }} <span aria-hidden="true">&rarr;</span></a>
              </div>
            </article>
            <div v-if="settings.show_help_card" class="igf-help-card">
              <i class="fa-regular fa-circle-question" aria-hidden="true" />
              <div><strong>{{ settings.help_title }}</strong><a :href="`mailto:${contact.email}`">{{ contact.email }}</a><a :href="`tel:${contact.phone_primary}`">{{ contact.phone_primary }}</a></div>
            </div>
          </aside>

          <div class="igf-donation-card">
            <div v-if="settings.show_form_badge || showLocaleSwitcher" class="igf-donation-card__toolbar">
              <div v-if="settings.show_form_badge" class="igf-donation-card__header">
                <span>{{ settings.form_badge }}</span>
                <i class="fa-solid fa-shield-halved" aria-hidden="true" />
              </div>
              <nav v-if="showLocaleSwitcher" class="igf-donation-languages" :aria-label="settings.language_switcher_accessible_label || 'Language'">
                <a v-for="link in localeLinks" :key="link.locale" :href="link.url" :hreflang="link.locale" :lang="link.locale"
                  :aria-current="link.locale === currentLocale ? 'page' : undefined">
                  {{ languageLabel(link.locale) }}
                </a>
              </nav>
            </div>
            <h2 id="donation-form-title">{{ settings.form_title }}</h2>
            <p v-if="settings.show_required_hint" class="igf-card-intro">{{ settings.required_hint }}</p>

            <v-form ref="form" v-model="isFormValid" @submit.prevent="submitDonation">
              <div class="igf-checkout-grid">
                <div class="igf-checkout-main">
                  <ol class="igf-checkout-steps" :aria-label="settings.checkout_steps_accessible_label">
                    <li class="is-current"><a href="#donation-step-gift"><span>1</span>{{ settings.gift_step_label }}</a></li>
                    <li :class="{ 'is-current': checkoutRevealed }"><a href="#donation-step-checkout" :aria-disabled="!giftSelectionComplete" @click.prevent="giftSelectionComplete && revealCheckout()"><span>2</span>{{ settings.checkout_step_label }}</a></li>
                  </ol>

                  <section id="donation-step-gift" class="igf-checkout-section">
                    <div class="igf-gift-intro">
                      <h3 id="donation-gift-heading" ref="giftHeading" tabindex="-1">{{ settings.frequency_heading }}</h3>
                      <p>{{ settings.gift_subtitle }}</p>
                    </div>
                    <fieldset class="igf-frequency-fieldset">
                      <legend class="sr-only">{{ settings.frequency_accessible_label }}</legend>
                      <div class="igf-frequency-tabs" role="radiogroup" :aria-label="settings.frequency_accessible_label" aria-describedby="donation-frequency-help">
                        <button v-for="option in frequencyOptions" :key="option.key" type="button" role="radio"
                          :class="{ 'is-selected': donation.frequency === option.key, 'is-unavailable': !option.available }"
                          :aria-checked="donation.frequency === option.key" :disabled="!option.available"
                          @click="selectFrequency(option)">
                          <span>{{ option.label }}</span>
                          <small v-if="!option.available">{{ settings.frequency_coming_soon_label }}</small>
                        </button>
                      </div>
                      <p id="donation-frequency-help" class="igf-frequency-help"><i class="fa-solid fa-circle-info" aria-hidden="true" />{{ settings.frequency_help }}</p>
                    </fieldset>

                    <fieldset class="igf-fieldset">
                      <legend>{{ settings.amount_legend }}</legend>
                      <div class="igf-amount-options" :aria-label="settings.suggested_amounts_label">
                        <button v-for="option in suggestedAmountOptions" :key="option.amount" type="button" data-test="suggested-amount"
                          :class="{ 'is-selected': !customAmountActive && Number(donation.amount) === option.amount, 'is-featured': option.featured }"
                          :aria-pressed="!customAmountActive && Number(donation.amount) === option.amount"
                          @click="selectSuggestedAmount(option)">
                          <span>{{ money(option.amount) }}</span>
                          <small v-if="option.impact">{{ option.impact }}</small>
                          <i class="fa-solid fa-check" aria-hidden="true" />
                        </button>
                        <button v-if="showCustomAmount" type="button" class="igf-custom-amount-option" data-test="custom-amount-option"
                          :class="{ 'is-selected': customAmountActive }" :aria-pressed="customAmountActive"
                          @click="activateCustomAmount">
                          <span>{{ settings.other_amount_option_label }}</span>
                          <small>{{ settings.other_amount_option_help }}</small>
                          <i class="fa-solid fa-pencil" aria-hidden="true" />
                        </button>
                      </div>
                      <v-text-field v-if="customAmountActive" id="donation-custom-amount" v-model="donation.amount" type="number"
                        :min="MIN_DONATION_AMOUNT" :max="MAX_DONATION_AMOUNT" step="0.01" :label="settings.other_amount_label"
                        data-test="custom-amount-field" variant="outlined" hide-details="auto" :prefix="currencyPrefix" :suffix="currencySuffix" :rules="amountRules" required />
                    </fieldset>

                    <fieldset class="igf-fieldset">
                      <legend>{{ settings.cause_legend }}</legend>
                      <div v-if="selectedCause" class="igf-locked-cause" data-test="locked-donation-cause" role="status"
                        :aria-label="`${settings.cause_field_label || 'Supporting'}: ${selectedCause.name}`">
                        <i :class="causeCardIcon(selectedCause)" aria-hidden="true" />
                        <span><small>{{ settings.cause_field_label || 'Supporting' }}</small><strong>{{ selectedCause.name }}</strong></span>
                        <i class="fa-solid fa-lock" aria-hidden="true" />
                      </div>
                      <p v-if="settings.cause_help && selectedCause" class="igf-field-help">{{ settings.cause_help }}</p>
                      <p v-if="!selectedCause" class="igf-cause-alert" role="status">{{ settings.causes_unavailable_message }}</p>
                      <p v-if="selectionWarning" class="igf-selection-warning" role="alert">{{ selectionWarning }}</p>
                      <label v-if="selectedCause?.project_selection === 'optional'" class="igf-native-field igf-project-field" for="donation-project">
                        <span>{{ settings.project_field_label }}</span>
                        <select id="donation-project" v-model="donation.project_uuid" aria-describedby="donation-project-help">
                          <option value="">{{ settings.project_placeholder }}</option>
                          <option v-for="project in projectOptions" :key="project.uuid" :value="project.uuid">{{ project.name }}</option>
                        </select>
                      </label>
                      <p v-if="selectedCause?.project_selection === 'optional'" id="donation-project-help" class="igf-field-help">{{ settings.project_help }}</p>
                      <div v-if="selectedCause?.project_selection === 'fixed'" class="igf-fixed-project" role="status" aria-live="polite">
                        <span>{{ settings.project_field_label }}</span>
                        <strong>{{ selectedProject?.name || confirmedDestinationName }}</strong>
                        <small>{{ settings.destination_page_explanation }}</small>
                      </div>
                      <div v-if="selectedCause" id="donation-destination-summary" class="igf-destination-summary" role="status" aria-live="polite">
                        <i class="fa-solid fa-location-dot" aria-hidden="true" />
                        <div>
                          <small>{{ settings.destination_label }}</small>
                          <strong>{{ confirmedDestinationName }}</strong>
                          <p>{{ destinationExplanation }}</p>
                        </div>
                      </div>
                    </fieldset>

                    <button type="button" class="igf-step-continue" :disabled="!giftSelectionComplete" @click="revealCheckout">
                      {{ settings.continue_gift_label }} <span aria-hidden="true">&rarr;</span>
                    </button>
                  </section>

                  <section v-show="checkoutRevealed" id="donation-step-checkout" class="igf-checkout-section igf-checkout-section--details">
                    <div class="igf-checkout-section__heading">
                      <div><span>2</span><h3 id="donation-checkout-heading" ref="checkoutHeading" tabindex="-1">{{ settings.checkout_step_label }}</h3></div>
                      <button type="button" @click="hideCheckout">{{ settings.edit_gift_label }}</button>
                    </div>

                    <fieldset class="igf-fieldset">
                      <legend>{{ settings.details_legend }}</legend>
                      <div class="igf-details-grid">
                        <v-text-field v-model="donation.donor_name" :label="settings.name_field_label" autocomplete="name" variant="outlined"
                          hide-details="auto" :rules="[v => !!v || settings.name_required_message]" required />
                        <v-text-field v-model="donation.email" :label="settings.email_field_label" autocomplete="email" type="email"
                          variant="outlined" hide-details="auto" :rules="emailRules" required />
                        <v-text-field v-model="donation.phone" :label="settings.phone_field_label" autocomplete="tel" inputmode="tel"
                          variant="outlined" hide-details="auto" :rules="[v => !!v || settings.phone_required_message]" required />
                        <v-text-field v-model="donation.address" :label="settings.address_field_label" autocomplete="street-address" variant="outlined"
                          hide-details="auto" :rules="[v => !!v || settings.address_required_message]" required />
                      </div>
                    </fieldset>

                    <fieldset class="igf-fieldset igf-payment-methods" :aria-describedby="paymentMethodDescriptionIds" :aria-invalid="showPaymentMethodError ? 'true' : 'false'">
                      <legend>{{ settings.payment_method_legend }}</legend>
                      <p id="payment-method-help" class="igf-field-help">{{ settings.payment_method_help }}</p>
                      <div v-if="paymentMethods.length" class="igf-payment-method-grid" data-test="payment-method-options">
                        <label v-for="method in paymentMethods" :key="method.key" class="igf-payment-method"
                          :class="{ 'is-selected': donation.payment_method === method.key, 'is-unavailable': !method.available }" :for="paymentMethodDomId(method)">
                          <input :id="paymentMethodDomId(method)" v-model="donation.payment_method" type="radio" name="payment_method"
                            :value="method.key" :disabled="!method.available" required :aria-describedby="paymentMethodDescriptionId(method)"
                            @change="paymentMethodTouched = true">
                          <span v-if="method.logos.length" class="igf-payment-method__logos" :class="{ 'has-multiple': method.logos.length > 1 }" aria-hidden="true">
                            <img v-for="logo in method.logos" :key="logo.src" :src="logo.src" alt="" width="122" height="44">
                          </span>
                          <span v-else class="igf-payment-method__icon" aria-hidden="true"><i :class="paymentMethodIcon(method.key)" /></span>
                          <span class="igf-payment-method__copy">
                            <strong>{{ method.label }}</strong>
                            <small v-if="method.description">{{ method.description }}</small>
                            <small v-if="method.networks" class="igf-payment-method__networks">{{ paymentMethodNetworks(method.networks) }}</small>
                            <small v-if="!method.available && hasAvailablePaymentMethod" :id="`${paymentMethodDomId(method)}-unavailable`" class="igf-payment-method__unavailable">{{ method.unavailable_reason || settings.payment_method_unavailable_label }}</small>
                          </span>
                          <span v-if="method.available" class="igf-payment-method__check" aria-hidden="true"><i class="fa-solid fa-check" /></span>
                        </label>
                      </div>
                      <p v-if="!hasAvailablePaymentMethod" id="payment-methods-unavailable" class="igf-cause-alert" role="status">{{ settings.payment_methods_unavailable_message }}</p>
                      <p v-if="showPaymentMethodError" id="payment-method-error" class="igf-field-error" role="alert">{{ settings.payment_method_required_message }}</p>
                    </fieldset>

                    <div v-if="settings.show_gateway_note" class="igf-gateway-note">
                      <i class="fa-solid fa-shield-halved" aria-hidden="true" />
                      <div><strong>{{ settings.gateway_heading }}</strong><p>{{ settings.gateway_note }}</p></div>
                    </div>
                    <p class="igf-privacy-note"><i class="fa-solid fa-lock" aria-hidden="true" /> {{ settings.privacy_note }}</p>
                  </section>
                </div>

                <aside class="igf-donation-review" aria-live="polite" data-test="donation-review">
                  <div class="igf-donation-review__heading"><span><i class="fa-solid fa-receipt" aria-hidden="true" /></span><div><small>{{ settings.form_badge }}</small><h3>{{ settings.summary_heading }}</h3></div></div>
                  <dl>
                    <div><dt>{{ settings.summary_frequency_label }}</dt><dd>{{ selectedFrequencyLabel }}</dd></div>
                    <div><dt>{{ settings.summary_amount_label }}</dt><dd>{{ summaryAmount }}</dd></div>
                    <div><dt>{{ settings.summary_destination_label }}</dt><dd>{{ confirmedDestinationName || settings.summary_pending_label }}</dd></div>
                    <div><dt>{{ settings.summary_payment_label }}</dt><dd>{{ selectedPaymentMethod?.label || settings.summary_pending_label }}</dd></div>
                  </dl>
                  <p>{{ settings.summary_help }}</p>
                  <v-btn v-show="checkoutRevealed" type="submit" class="igf-submit" block :disabled="!canAttemptSubmit" :loading="loading">
                    {{ submitButtonLabel }} <span aria-hidden="true">&rarr;</span>
                  </v-btn>
                  <p v-if="settings.show_legal_links && legalLinks.length" class="igf-terms">
                    {{ settings.legal_prefix }}
                    <template v-for="(link, index) in legalLinks" :key="link.url">
                      <a :href="link.url">{{ link.label }}</a><template v-if="index < legalLinks.length - 1">{{ index === legalLinks.length - 2 ? ` ${settings.legal_joiner} ` : ', ' }}</template>
                    </template>.
                    {{ settings.redirect_note }}
                  </p>
                </aside>
              </div>
            </v-form>
          </div>
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed, ref, nextTick, onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Layout from '../layouts/App.vue';
import { useGlobal } from '../Shared/composables/global';
import { usePublicLocaleSwitcher } from '../Shared/composables/publicLocaleSwitcher';
import { donationAmountFromUrl, formatMoney, interpolateSetting, regionalSettings } from '../Shared/composables/siteSettings';

const inertiaPage = usePage();
const { $toast } = useGlobal();
const {
  currentLocale,
  enabled: showLocaleSwitcher,
  languageLabel,
  links: localeLinks,
} = usePublicLocaleSwitcher();
const settings = computed(() => inertiaPage.props.siteSettings?.donation_page || {});
const contact = computed(() => inertiaPage.props.siteSettings?.contact || {});
const regional = computed(() => regionalSettings(inertiaPage.props.siteSettings?.regional));
const currencyPrefix = computed(() => regional.value.currency_position === 'before' ? regional.value.currency_symbol : '');
const currencySuffix = computed(() => regional.value.currency_position === 'after' ? regional.value.currency_symbol : '');
const checkoutLayout = computed(() => ['centered', 'split'].includes(settings.value.checkout_layout) ? settings.value.checkout_layout : 'centered');
const cardStyle = computed(() => ['soft', 'outlined', 'elevated'].includes(settings.value.card_style) ? settings.value.card_style : 'soft');
// Checkout is opt-in: an absent or unfamiliar server mode must never expose a
// partially configured payment form.
const pageMode = computed(() => inertiaPage.props.data?.pageMode === 'detail' ? 'detail' : 'catalog');
const isCatalogPage = computed(() => pageMode.value === 'catalog');
const isCausePage = computed(() => pageMode.value === 'detail');
const catalogUrl = computed(() => String(inertiaPage.props.data?.catalogUrl || '/donate'));
const MIN_DONATION_AMOUNT = 10;
const MAX_DONATION_AMOUNT = 500000;
const SAFE_PAYMENT_METHOD_LOGOS = Object.freeze({
  bkash: ['/image/payment-methods/bkash-reference.svg'],
  nagad: ['/image/payment-methods/nagad.png'],
  card: ['/image/payment-methods/visa-reference.svg', '/image/payment-methods/amex.png'],
});
const CAUSE_CARD_ICONS = Object.freeze({
  'hands-heart': 'fa-solid fa-hands-holding-heart',
  'graduation-cap': 'fa-solid fa-graduation-cap',
  moon: 'fa-solid fa-moon',
  'hand-heart': 'fa-solid fa-hand-holding-heart',
  food: 'fa-solid fa-bowl-food',
  emergency: 'fa-solid fa-truck-medical',
  children: 'fa-solid fa-children',
  stationery: 'fa-solid fa-book-open',
  uniform: 'fa-solid fa-shirt',
  meals: 'fa-solid fa-utensils',
  school: 'fa-solid fa-school',
  qurbani: 'fa-solid fa-cow',
  water: 'fa-solid fa-droplet',
  women: 'fa-solid fa-person-dress',
  youth: 'fa-solid fa-people-group',
  'street-education': 'fa-solid fa-book-open-reader',
});
const amountButtonCount = computed(() => Math.min(5, Math.max(2, Number(settings.value.amount_button_count) || 5)));
const globalSuggestedAmountOptions = computed(() => [1, 2, 3, 4, 5]
  .map(index => ({
    amount: Number(settings.value[`amount_${index}`]),
    impact: String(settings.value[`amount_${index}_impact`] || ''),
    featured: Number(settings.value.featured_amount_index || 4) === index,
  }))
  .filter(option => isAllowedDonationAmount(option.amount))
  .slice(0, amountButtonCount.value));
const showCustomAmount = computed(() => settings.value.show_custom_amount !== false);
const frequencyOptions = computed(() => {
  const labels = {
    one_time: settings.value.frequency_label || 'One-time',
    daily: settings.value.frequency_daily_label || 'Daily',
    weekly: settings.value.frequency_weekly_label || 'Weekly',
    monthly: settings.value.frequency_monthly_label || 'Monthly',
  };
  const provided = Array.isArray(inertiaPage.props.data?.donationFrequencies)
    ? inertiaPage.props.data.donationFrequencies
    : [
      { key: 'one_time', available: true },
      { key: 'daily', available: false },
      { key: 'weekly', available: false },
      { key: 'monthly', available: false },
    ];

  return provided
    .filter(option => option && Object.prototype.hasOwnProperty.call(labels, option.key))
    .map(option => ({
      key: option.key,
      label: labels[option.key],
      available: option.key === 'one_time' && option.available !== false,
    }));
});
const paymentMethods = computed(() => {
  const methods = Array.isArray(inertiaPage.props.data?.paymentMethods)
    ? inertiaPage.props.data.paymentMethods
    : [];

  return methods
    .filter(method => method && method.enabled !== false)
    .map(method => ({
      key: String(method.key || ''),
      label: String(method.label || method.key || ''),
      description: String(method.description || ''),
      networks: method.networks || '',
      logos: safePaymentMethodLogos(method.key, method.logos),
      available: method.available !== false,
      unavailable_reason: String(method.unavailable_reason || ''),
    }))
    .filter(method => method.key !== '');
});
const donationTypes = ref([]);
const activeDonationTabKey = ref('all');
const form = ref(null);
const isFormValid = ref(false);
const loading = ref(false);
const giftHeading = ref(null);
const checkoutHeading = ref(null);
const checkoutRevealed = ref(false);
const customAmountActive = ref(false);
const customAmountDraft = ref('');
const paymentMethodTouched = ref(false);
const selectionWarning = ref(String(inertiaPage.props.data?.selection_warning || ''));
const donation = ref({ amount: '', donor_name: '', email: '', phone: '', address: '', payment_cause: '', project_uuid: '', payment_method: '', frequency: 'one_time', checkout_key: '' });
const submittedPayloadFingerprint = ref(null);
const checkoutKeyNeedsRefresh = ref(false);
const selectedCause = computed(() => donationTypes.value.find(cause => [cause.uuid, cause.slug].includes(donation.value.payment_cause)) || null);
const causeAmountOptions = computed(() => {
  if (!Array.isArray(selectedCause.value?.amount_options)) return [];

  const seen = new Set();
  return selectedCause.value.amount_options.reduce((options, option) => {
    const amount = Number(option?.amount);
    if (!isAllowedDonationAmount(amount) || seen.has(amount)) return options;
    seen.add(amount);
    options.push({ amount, impact: String(option?.impact || ''), featured: false });
    return options;
  }, []).slice(0, 12);
});
const suggestedAmountOptions = computed(() => causeAmountOptions.value.length
  ? causeAmountOptions.value
  : globalSuggestedAmountOptions.value);
const suggestedAmounts = computed(() => suggestedAmountOptions.value.map(option => option.amount));
const causeLandingSections = computed(() => {
  const sections = Array.isArray(selectedCause.value?.landing_sections)
    ? selectedCause.value.landing_sections
    : [];

  return sections.slice(0, 12).map((section, index) => {
    const image = safeContentUrl(section?.image);
    const video = safeCauseVideo(section?.video);
    const ctaUrl = safeContentUrl(section?.cta?.url);
    const ctaLabel = String(section?.cta?.label || '').trim();

    return {
      uuid: String(section?.uuid || `cause-section-${index}`),
      layout: ['text', 'media-left', 'media-right', 'highlight'].includes(section?.layout) ? section.layout : 'text',
      title: String(section?.title || '').trim(),
      body: String(section?.body || '').trim(),
      image,
      image_alt: String(section?.image_alt || '').trim(),
      video: image ? null : video,
      cta: ctaUrl && ctaLabel ? { label: ctaLabel, url: ctaUrl } : null,
    };
  }).filter(section => section.title || section.body || section.image || section.video || section.cta);
});
// The catalog is the only route into a checkout and therefore always remains
// visible on the catalog page; there is deliberately no misleading hide toggle.
const showCauseGallery = computed(() => isCatalogPage.value);
const donationGroupSource = computed(() => {
  const groups = inertiaPage.props.data?.donationGroups;
  if (Array.isArray(groups)) return groups;

  const transitionalGroups = inertiaPage.props.data?.donationCauseGroups;
  return Array.isArray(transitionalGroups) ? transitionalGroups : [];
});
const donationTabsLabel = computed(() => String(
  settings.value.cause_tabs_accessible_label
    || settings.value.cause_gallery_title
    || 'Donation cause categories',
).trim());
const donationTabs = computed(() => {
  if (!donationTypes.value.length) return [];

  const tabs = [{
    key: 'all',
    name: String(settings.value.cause_tabs_all_label || 'All causes').trim(),
    description: '',
    groupUuid: null,
  }];
  const seenGroupUuids = new Set();

  donationGroupSource.value.forEach((group, index) => {
    if (!group || typeof group !== 'object' || Array.isArray(group)) return;
    const groupUuid = String(group.uuid || '').trim();
    if (!groupUuid || seenGroupUuids.has(groupUuid)) return;

    const groupCauses = donationTypes.value.filter(cause => String(cause?.group_uuid || '').trim() === groupUuid);
    if (!groupCauses.length) return;

    seenGroupUuids.add(groupUuid);
    tabs.push({
      key: `group-${groupUuid}`,
      name: String(group.name || group.slug || `Group ${index + 1}`).trim(),
      description: String(group.description || '').trim(),
      groupUuid,
    });
  });

  return tabs;
});
const activeDonationTab = computed(() => donationTabs.value.find(tab => tab.key === activeDonationTabKey.value)
  || donationTabs.value[0]
  || null);
const filteredDonationTypes = computed(() => {
  const active = activeDonationTab.value;
  if (!active || active.key === 'all') return donationTypes.value;
  return donationTypes.value.filter(cause => String(cause?.group_uuid || '').trim() === active.groupUuid);
});
const projectOptions = computed(() => Array.isArray(selectedCause.value?.projects) ? selectedCause.value.projects : []);
const selectedProject = computed(() => projectOptions.value.find(project => project.uuid === donation.value.project_uuid) || null);
const projectSelectionSatisfied = computed(() => selectedCause.value?.project_selection !== 'fixed' || !!selectedProject.value);
const confirmedDestinationName = computed(() => selectedProject.value?.name
  || (selectedCause.value?.destination_type === 'unrestricted' ? selectedCause.value?.name : selectedCause.value?.destination_name)
  || selectedCause.value?.name
  || '');
const destinationExplanation = computed(() => {
  if (selectedCause.value?.destination_type === 'page') return settings.value.destination_page_explanation;
  if (selectedCause.value?.destination_type === 'category') return settings.value.destination_category_explanation;
  return settings.value.destination_unrestricted_explanation;
});
const selectedPaymentMethodAvailable = computed(() => paymentMethods.value.some(method => method.available && method.key === donation.value.payment_method));
const hasAvailablePaymentMethod = computed(() => paymentMethods.value.some(method => method.available));
const showPaymentMethodError = computed(() => paymentMethodTouched.value && !selectedPaymentMethodAvailable.value);
const paymentMethodDescriptionIds = computed(() => showPaymentMethodError.value ? 'payment-method-help payment-method-error' : 'payment-method-help');
const selectedPaymentMethod = computed(() => paymentMethods.value.find(method => method.key === donation.value.payment_method) || null);
const selectedFrequencyLabel = computed(() => frequencyOptions.value.find(option => option.key === donation.value.frequency)?.label
  || settings.value.frequency_label
  || 'One-time');
const giftSelectionComplete = computed(() => isAllowedDonationAmount(donation.value.amount)
  && !!donation.value.payment_cause
  && donation.value.frequency === 'one_time'
  && projectSelectionSatisfied.value
  && donationTypes.value.length > 0);
const summaryAmount = computed(() => isAllowedDonationAmount(donation.value.amount)
  ? money(donation.value.amount)
  : settings.value.summary_pending_label);
const submitButtonLabel = computed(() => isAllowedDonationAmount(donation.value.amount)
  ? interpolateSetting(settings.value.submit_with_amount_label || 'Continue securely with {amount}', { amount: money(donation.value.amount) })
  : settings.value.submit_label);
const legalLinks = computed(() => [
  { label: settings.value.terms_link_label, url: settings.value.terms_link_url },
  { label: settings.value.privacy_link_label, url: settings.value.privacy_link_url },
  { label: settings.value.refund_link_label, url: settings.value.refund_link_url },
].filter(link => String(link.label || '').trim() !== '' && String(link.url || '').trim() !== ''));
const canAttemptSubmit = computed(() => isFormValid.value
  && isAllowedDonationAmount(donation.value.amount)
  && !!donation.value.payment_cause
  && donation.value.frequency === 'one_time'
  && projectSelectionSatisfied.value
  && !loading.value
  && donationTypes.value.length > 0
  && hasAvailablePaymentMethod.value);
const canSubmit = computed(() => canAttemptSubmit.value && selectedPaymentMethodAvailable.value);

const emailRules = computed(() => [
  value => !!value || settings.value.email_required_message,
  value => /.+@.+\..+/.test(value) || settings.value.invalid_email_message,
]);
const amountRules = computed(() => [
  value => !!value || settings.value.amount_required_message,
  value => Number(value) >= MIN_DONATION_AMOUNT || interpolateSetting(settings.value.minimum_amount_message, { currency: regional.value.currency_code }),
  value => Number(value) <= MAX_DONATION_AMOUNT || interpolateSetting(settings.value.maximum_amount_message, { currency: regional.value.currency_code }),
  value => hasSupportedCurrencyPrecision(value) || settings.value.amount_precision_message,
]);
const money = amount => formatMoney(amount, regional.value);

const materialPayloadFingerprint = computed(() => JSON.stringify({
  amount: isAllowedDonationAmount(donation.value.amount) ? Number(donation.value.amount).toFixed(2) : String(donation.value.amount || '').trim(),
  donor_name: String(donation.value.donor_name || '').trim(),
  email: String(donation.value.email || '').trim().toLowerCase(),
  phone: String(donation.value.phone || '').trim(),
  address: String(donation.value.address || '').trim(),
  payment_cause: String(donation.value.payment_cause || ''),
  project_uuid: String(donation.value.project_uuid || ''),
  payment_method: String(donation.value.payment_method || ''),
  frequency: String(donation.value.frequency || ''),
}));

watch(materialPayloadFingerprint, fingerprint => {
  checkoutKeyNeedsRefresh.value = submittedPayloadFingerprint.value !== null
    && fingerprint !== submittedPayloadFingerprint.value;
});

function isAllowedDonationAmount(value) {
  const amount = Number(value);
  return Number.isFinite(amount)
    && amount >= MIN_DONATION_AMOUNT
    && amount <= MAX_DONATION_AMOUNT
    && hasSupportedCurrencyPrecision(value);
}

function hasSupportedCurrencyPrecision(value) {
  return /^(?:0|[1-9]\d{0,5})(?:\.\d{1,2})?$/.test(String(value ?? '').trim());
}

function selectFrequency(option) {
  if (option?.available) donation.value.frequency = option.key;
}

function selectSuggestedAmount(option) {
  if (customAmountActive.value && String(donation.value.amount ?? '').trim() !== '') {
    customAmountDraft.value = String(donation.value.amount);
  }

  customAmountActive.value = false;
  donation.value.amount = option.amount;
}

async function activateCustomAmount() {
  if (!showCustomAmount.value) return;

  const currentAmount = String(donation.value.amount ?? '').trim();
  const currentIsSuggested = suggestedAmounts.value.includes(Number(currentAmount));
  if (!currentIsSuggested && currentAmount !== '') {
    customAmountDraft.value = currentAmount;
  }

  customAmountActive.value = true;
  donation.value.amount = customAmountDraft.value;
  await nextTick();
  document.getElementById('donation-custom-amount')?.focus();
}

function causeCardId(cause) {
  return `donation-cause-${String(cause?.uuid || cause?.slug || 'option').replace(/[^a-z0-9_-]/gi, '-')}`;
}

function donationTabToken(value) {
  return String(value || 'tab')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9_-]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'tab';
}

function donationTabId(tab) {
  return `donation-cause-tab-${donationTabToken(tab?.key)}`;
}

function donationPanelId(tab) {
  return `donation-cause-panel-${donationTabToken(tab?.key)}`;
}

function donationTabDescriptionId(tab) {
  return `${donationPanelId(tab)}-description`;
}

function isActiveDonationTab(tab) {
  return activeDonationTab.value?.key === tab?.key;
}

function selectDonationTab(tab, tabElement = null) {
  const selected = donationTabs.value.find(candidate => candidate.key === tab?.key);
  if (!selected) return;

  activeDonationTabKey.value = selected.key;
  nextTick(() => {
    tabElement?.focus?.();
    tabElement?.scrollIntoView?.({ block: 'nearest', inline: 'center' });
  });
}

function handleDonationTabKeydown(event) {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key) || donationTabs.value.length < 2) return;

  const activeIndex = Math.max(0, donationTabs.value.findIndex(tab => isActiveDonationTab(tab)));
  let nextIndex = activeIndex;
  if (event.key === 'Home') nextIndex = 0;
  else if (event.key === 'End') nextIndex = donationTabs.value.length - 1;
  else if (event.key === 'ArrowRight') nextIndex = (activeIndex + 1) % donationTabs.value.length;
  else nextIndex = (activeIndex - 1 + donationTabs.value.length) % donationTabs.value.length;

  event.preventDefault();
  const tabElements = [...(event.currentTarget?.querySelectorAll('[role="tab"]') || [])];
  selectDonationTab(donationTabs.value[nextIndex], tabElements[nextIndex] || null);
}

function causeCardIcon(cause) {
  const managedIcon = CAUSE_CARD_ICONS[String(cause?.icon_key || '')];
  if (managedIcon) return managedIcon;

  return {
    unrestricted: 'fa-solid fa-hands-holding-heart',
    category: 'fa-solid fa-layer-group',
    page: 'fa-solid fa-bullseye',
  }[String(cause?.destination_type || '')] || 'fa-solid fa-heart';
}

function causePageUrl(cause) {
  const href = String(cause?.url || `/donate/${encodeURIComponent(String(cause?.slug || cause?.uuid || ''))}`);
  if (typeof window === 'undefined') return href;

  try {
    const current = new URL(window.location.href);
    const target = new URL(href, current.origin);
    ['amount', 'custom_amount'].forEach(key => {
      if (current.searchParams.has(key) && !target.searchParams.has(key)) {
        target.searchParams.set(key, current.searchParams.get(key));
      }
    });

    return target.origin === current.origin
      ? `${target.pathname}${target.search}${target.hash}`
      : target.toString();
  } catch {
    return href;
  }
}

async function revealCheckout() {
  if (!giftSelectionComplete.value) return;

  checkoutRevealed.value = true;
  await nextTick();
  checkoutHeading.value?.focus();
}

async function hideCheckout() {
  checkoutRevealed.value = false;
  await nextTick();
  giftHeading.value?.focus();
  document.getElementById('donation-step-gift')?.scrollIntoView?.({ block: 'start' });
}

function paymentMethodDomId(method) {
  return `payment-method-${String(method.key).replace(/[^a-z0-9_-]/gi, '-')}`;
}

function paymentMethodIcon(key) {
  return key === 'card' ? 'fa-regular fa-credit-card' : 'fa-solid fa-mobile-screen-button';
}

function paymentMethodDescriptionId(method) {
  if (method.available) {
    return 'payment-method-help';
  }

  return hasAvailablePaymentMethod.value
    ? `${paymentMethodDomId(method)}-unavailable`
    : 'payment-methods-unavailable';
}

function safePaymentMethodLogos(key, logos) {
  const allowedPaths = SAFE_PAYMENT_METHOD_LOGOS[String(key)] || [];

  if (!Array.isArray(logos) || allowedPaths.length === 0) {
    return [];
  }

  const seenPaths = new Set();

  return logos.reduce((safeLogos, logo) => {
    const src = logo && typeof logo === 'object' ? String(logo.src || '') : '';

    if (!allowedPaths.includes(src) || seenPaths.has(src)) {
      return safeLogos;
    }

    seenPaths.add(src);
    safeLogos.push({ src });

    return safeLogos;
  }, []);
}

function paymentMethodNetworks(networks) {
  return Array.isArray(networks) ? networks.filter(Boolean).join(' · ') : String(networks || '');
}

function safeContentUrl(value) {
  const url = String(value || '').trim();
  const hasUnsafeCharacter = Array.from(url).some((character) => {
    const code = character.charCodeAt(0);
    return code <= 31 || code === 127 || character === '\\';
  });
  if (!url || hasUnsafeCharacter || url.startsWith('//')) return '';
  if (url.startsWith('/') || url.startsWith('#')) return url;

  const scheme = url.match(/^([a-z][a-z0-9+.-]*):/i)?.[1]?.toLowerCase();
  if (!scheme) return url;
  return ['http', 'https', 'mailto', 'tel'].includes(scheme) ? url : '';
}

function safeCauseVideo(video) {
  if (!video || typeof video !== 'object') return null;
  const url = safeContentUrl(video.url);
  const title = String(video.title || '').trim();
  const transcript = String(video.transcript || '').trim();
  if (!url || !title) return null;
  if (video.type === 'file') {
    const fileUrlIsSafe = url.startsWith('/') || /^https?:\/\//iu.test(url);
    return fileUrlIsSafe && transcript.length >= 20 ? { type: 'file', url, title, transcript } : null;
  }
  if (video.type !== 'embed') return null;

  return /^https:\/\/www\.youtube-nocookie\.com\/embed\/[A-Za-z0-9_-]{11}$/u.test(url)
    || /^https:\/\/player\.vimeo\.com\/video\/[0-9]{6,12}$/u.test(url)
    ? { type: 'embed', url, title, transcript }
    : null;
}

function causeVideoTranscriptId(section) {
  const token = String(section?.uuid || 'section').replace(/[^A-Za-z0-9_-]/gu, '-');
  return `cause-video-transcript-${token}`;
}

let initializingCause = true;
watch(() => donation.value.payment_cause, () => {
  if (initializingCause) return;
  selectionWarning.value = '';
  syncProjectForCause();
}, { flush: 'sync' });

watch(donationTabs, tabs => {
  if (!tabs.some(tab => tab.key === activeDonationTabKey.value)) {
    activeDonationTabKey.value = tabs[0]?.key || 'all';
  }
});

function syncProjectForCause(preferredProjectUuid = '') {
  const cause = selectedCause.value;
  if (!cause || cause.project_selection === 'none') {
    donation.value.project_uuid = '';
    return;
  }

  const projects = projectOptions.value;
  if (cause.project_selection === 'fixed') {
    donation.value.project_uuid = projects[0]?.uuid || '';
    return;
  }

  donation.value.project_uuid = projects.some(project => project.uuid === preferredProjectUuid)
    ? preferredProjectUuid
    : '';
}

onMounted(() => {
  donationTypes.value = Array.isArray(inertiaPage.props.data?.donationTypes) ? inertiaPage.props.data.donationTypes : [];
  donation.value.payment_cause = inertiaPage.props.data?.selectedUUID || '';
  syncProjectForCause(String(inertiaPage.props.data?.selectedProjectUUID || ''));
  initializingCause = false;
  donation.value.checkout_key = String(inertiaPage.props.data?.checkout_key || '');
  const requestedAmount = donationAmountFromUrl(inertiaPage.url, {
    allowCustomAmount: showCustomAmount.value,
    visibleSuggestedAmounts: suggestedAmounts.value,
  });
  if (requestedAmount !== null && isAllowedDonationAmount(requestedAmount)) {
    donation.value.amount = requestedAmount;
    customAmountActive.value = showCustomAmount.value && !suggestedAmounts.value.includes(Number(requestedAmount));
    customAmountDraft.value = customAmountActive.value ? String(requestedAmount) : '';
  } else if (suggestedAmounts.value.length > 0) {
    donation.value.amount = suggestedAmountOptions.value.find(option => option.featured)?.amount
      || suggestedAmounts.value[0];
    customAmountActive.value = false;
    customAmountDraft.value = '';
  }
});

async function submitDonation() {
  paymentMethodTouched.value = true;
  const { valid } = await form.value.validate();
  if (!valid || !canSubmit.value) return;
  loading.value = true;
  try {
    if (checkoutKeyNeedsRefresh.value || !donation.value.checkout_key) {
      const refreshed = await refreshCheckoutKey();
      if (!refreshed) return;
    }

    submittedPayloadFingerprint.value = materialPayloadFingerprint.value;
    checkoutKeyNeedsRefresh.value = false;
    const response = await axios.post(route('frontend.donate.store'), donation.value);
    acceptReplacementCheckoutKey(response.data?.replacement_checkout_key);
    if (response.data.status && response.data.payment_url) {
      window.location.assign(response.data.payment_url);
      return;
    }
    $toast.error(response.data.message || settings.value.initialization_error_message || 'Payment initialization failed.');
  } catch (error) {
    acceptReplacementCheckoutKey(error.response?.data?.replacement_checkout_key);
    const errors = error.response?.data?.errors;
    const message = errors ? Object.values(errors).flat()[0] : error.response?.data?.message;
    $toast.error(message || settings.value.initialization_error_message || 'We could not start the payment. Please try again.');
  } finally {
    loading.value = false;
  }
}

async function refreshCheckoutKey() {
  try {
    const response = await axios.get(route('frontend.donate.checkout-key'), {
      headers: { Accept: 'application/json' },
    });
    const key = String(response.data?.checkout_key || '');
    if (!response.data?.status || key === '') {
      throw new Error('The server did not provide a checkout key.');
    }
    donation.value.checkout_key = key;
    submittedPayloadFingerprint.value = null;
    checkoutKeyNeedsRefresh.value = false;
    return true;
  } catch {
    $toast.error(settings.value.initialization_error_message || 'We could not prepare a new payment attempt. Please try again.');
    return false;
  }
}

function acceptReplacementCheckoutKey(value) {
  const key = String(value || '');
  if (key === '') return;
  donation.value.checkout_key = key;
  submittedPayloadFingerprint.value = null;
  checkoutKeyNeedsRefresh.value = false;
}
</script>

<style scoped lang="scss">
.igf-donate { --orange:var(--igf-primary,#ff7500); --action-orange:var(--igf-accent,#9c4500); --action-orange-hover:color-mix(in srgb,var(--igf-accent,#9c4500) 80%,#000); --brown:var(--igf-accent,#9c4500); --ink:var(--igf-ink,#191c1d); --surface:var(--igf-surface,#f8f9fa); --muted:color-mix(in srgb,var(--ink) 68%,var(--surface)); --line:color-mix(in srgb,var(--ink) 14%,var(--surface)); --brand-on-dark:color-mix(in srgb,var(--orange) 58%,#fff); --brand-soft:color-mix(in srgb,var(--orange) 12%,var(--surface)); --brand-soft-strong:color-mix(in srgb,var(--orange) 22%,var(--surface)); --brand-border:color-mix(in srgb,var(--brown) 52%,var(--surface)); --brand-shadow:color-mix(in srgb,var(--brown) 24%,transparent); overflow:hidden; background:var(--surface); color:var(--ink); font-family:'Hanken Grotesk',Arial,sans-serif; }
.igf-shell { width:min(calc(100% - 40px),1200px); margin-inline:auto; }
.igf-donate__hero { position:relative; isolation:isolate; display:grid; min-height:clamp(410px,49vw,550px); place-items:center; overflow:hidden; padding:clamp(62px,8vw,96px) 0; background:#211f1e; color:#fff; }
.igf-donate__hero-image,.igf-donate__hero-overlay { position:absolute; inset:0; width:100%; height:100%; }
.igf-donate__hero-image { z-index:-2; object-fit:cover; object-position:center; }
.igf-donate__hero-overlay { z-index:-1; background:linear-gradient(180deg,rgba(17,22,22,.78),rgba(26,23,21,.72)),linear-gradient(90deg,rgba(92,38,0,.26),transparent 58%); }
.igf-donate__hero-grid { display:grid; gap:clamp(32px,5vw,52px); }
.igf-donate__hero-copy { max-width:900px; margin-inline:auto; text-align:center; }
.igf-eyebrow { margin:0 0 15px; color:var(--brand-on-dark); font-size:12px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
.igf-donate h1,.igf-donate h2 { font-family:'Literata',Georgia,serif; letter-spacing:-.03em; }
.igf-donate h1 { max-width:900px; margin:0 auto; font-size:clamp(42px,5.5vw,68px); font-weight:650; line-height:1.04; text-wrap:balance; }
.igf-donate__lead { max-width:720px; margin:20px auto 0; color:#f1edeb; font-size:clamp(17px,1.8vw,20px); line-height:1.6; }
.igf-donate__hero--cause { min-height:clamp(390px,45vw,520px); }
.igf-donate__hero--cause .igf-donate__hero-overlay { background:linear-gradient(180deg,rgba(14,31,31,.72),rgba(23,30,29,.82)),linear-gradient(90deg,rgba(110,48,7,.3),transparent 62%); }
.igf-cause-back-link { display:inline-flex; min-height:44px; align-items:center; gap:9px; margin-bottom:24px; border:1px solid rgba(255,255,255,.34); border-radius:999px; padding:9px 15px; color:#fff; font-size:13px; font-weight:800; text-decoration:none; }
.igf-cause-back-link:hover { background:rgba(255,255,255,.12); }
.igf-cause-back-link:focus-visible { outline:3px solid color-mix(in srgb,var(--orange) 70%,transparent); outline-offset:4px; }
.igf-trust-list { display:grid; max-width:1000px; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin:0 auto; padding:0; list-style:none; }
.igf-trust-list li { display:grid; grid-template-columns:38px 1fr; gap:13px; align-items:start; border:1px solid rgba(255,255,255,.18); border-radius:14px; padding:15px; background:rgba(18,18,18,.38); text-align:left; }
.igf-trust-list i { display:grid; width:38px; height:38px; place-items:center; border:1px solid color-mix(in srgb,var(--brand-on-dark) 36%,transparent); border-radius:50%; color:var(--brand-on-dark); }
.igf-cause-content { padding:clamp(54px,7vw,88px) 0; background:color-mix(in srgb,var(--surface) 84%,#fff); }
.igf-cause-content__list { display:grid; gap:clamp(22px,4vw,38px); }
.igf-cause-content__section { display:grid; grid-template-columns:minmax(0,1fr); overflow:hidden; border:1px solid var(--line); border-radius:20px; background:#fff; box-shadow:0 12px 32px rgba(25,28,29,.08); }
.igf-cause-content__section.is-media-left,.igf-cause-content__section.is-media-right { grid-template-columns:minmax(260px,.9fr) minmax(0,1.1fr); align-items:stretch; }
.igf-cause-content__section.is-media-right .igf-cause-content__media { order:2; }
.igf-cause-content__section.is-highlight { border-left:6px solid var(--orange); background:var(--brand-soft); }
.igf-cause-content__media { min-height:260px; background:color-mix(in srgb,var(--ink) 92%,#fff); }
.igf-cause-content__media img,.igf-cause-content__media video,.igf-cause-content__media iframe { display:block; width:100%; height:100%; min-height:260px; border:0; object-fit:cover; }
.igf-cause-content__media iframe { aspect-ratio:16/9; }
.igf-cause-content__copy { align-self:center; padding:clamp(25px,4vw,48px); }
.igf-cause-content__copy h2 { margin:0 0 16px; color:var(--ink); font-size:clamp(28px,3vw,42px); font-weight:650; line-height:1.15; text-wrap:balance; }
.igf-cause-content__rich-text { color:var(--muted); font-size:16px; line-height:1.75; overflow-wrap:anywhere; }
.igf-cause-content__rich-text :deep(:first-child) { margin-top:0; }
.igf-cause-content__rich-text :deep(:last-child) { margin-bottom:0; }
.igf-cause-content__rich-text :deep(img) { max-width:100%; height:auto; }
.igf-cause-content__rich-text :deep(a) { color:var(--brown); }
.igf-cause-content__transcript { margin-top:22px; border-left:4px solid var(--orange); padding:16px 18px; background:color-mix(in srgb,var(--brand-soft) 72%,#fff); color:var(--muted); }
.igf-cause-content__transcript h3 { margin:0 0 8px; color:var(--ink); font-family:'Literata',Georgia,serif; font-size:18px; line-height:1.35; }
.igf-cause-content__transcript p { margin:0; line-height:1.7; white-space:pre-wrap; }
.igf-cause-content__cta { display:inline-flex; min-height:48px; align-items:center; justify-content:center; gap:9px; margin-top:22px; border-radius:var(--igf-button-radius,11px); padding:12px 19px; background:var(--action-orange); color:var(--igf-on-accent,#fff); font-size:14px; font-weight:800; text-decoration:none; }
.igf-cause-content__cta:hover { background:var(--action-orange-hover); color:var(--igf-on-accent,#fff); }
.igf-cause-content__cta:focus-visible { outline:3px solid color-mix(in srgb,var(--orange) 38%,transparent); outline-offset:4px; }
.igf-trust-list strong,.igf-trust-list span { display:block; }
.igf-trust-list strong { margin-bottom:3px; color:#fff; font-size:14px; }
.igf-trust-list span { color:#e0dcda; font-size:12px; line-height:1.5; }
.igf-donate-causes { padding:clamp(66px,8vw,100px) 0; background:#f3f5f6; }
.igf-donate-causes__empty { margin:0; border:1px solid #dedad6; border-radius:16px; background:#fff; padding:24px; color:var(--muted); text-align:center; }
.igf-donate-causes__header { max-width:760px; margin:0 auto clamp(32px,5vw,50px); text-align:center; }
.igf-donate-causes__header .igf-eyebrow { color:var(--brown); }
.igf-donate-causes__header h2 { margin:0; font-size:clamp(34px,4vw,48px); font-weight:650; line-height:1.12; text-wrap:balance; }
.igf-donate-causes__header>p:last-child:not(.igf-eyebrow) { margin:15px auto 0; color:var(--muted); font-size:17px; line-height:1.65; }
.igf-donate-causes__tabs { display:flex; width:100%; align-items:center; gap:10px; overflow-x:auto; margin:0 auto 34px; padding:7px; overscroll-behavior-inline:contain; scroll-padding-inline:7px; scroll-snap-type:x proximity; -webkit-overflow-scrolling:touch; }
.igf-donate-causes__tab { display:inline-flex; min-height:44px; flex:0 0 auto; align-items:center; justify-content:center; border:1px solid #d7d0c8; border-radius:999px; padding:10px 20px; background:#fff; color:var(--ink); font:750 14px/1.2 'Hanken Grotesk',Arial,sans-serif; white-space:nowrap; cursor:pointer; scroll-snap-align:start; transition:background-color .2s ease,border-color .2s ease,color .2s ease; }
.igf-donate-causes__tab:hover { border-color:var(--orange); }
.igf-donate-causes__tab.is-active { border-color:var(--brown); background:var(--brown); color:var(--igf-on-accent,#fff); }
.igf-donate-causes__tab:focus-visible { outline:3px solid #1b6fdc; outline-offset:3px; }
.igf-donate-causes__panel { min-width:0; }
.igf-donate-causes__panel:focus-visible { outline:3px solid rgba(27,111,220,.55); outline-offset:8px; }
.igf-donate-causes__tab-description { max-width:760px; margin:-10px auto 30px; color:var(--muted); font-size:16px; line-height:1.65; text-align:center; }
.igf-donate-causes__grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:24px; }
.igf-donation-cause-card { display:flex; min-width:0; flex-direction:column; overflow:hidden; border:1px solid #dedad6; border-radius:20px; background:#fff; box-shadow:0 14px 34px rgba(37,31,26,.08); transition:border-color .2s,box-shadow .2s,transform .2s; }
.igf-donation-cause-card:hover { border-color:var(--brand-border); box-shadow:0 19px 42px var(--brand-shadow); transform:translateY(-4px); }
.igf-donation-cause-card.is-selected { border-color:var(--orange); box-shadow:0 0 0 2px var(--orange),0 18px 40px var(--brand-shadow); }
.igf-donation-cause-card__media { position:relative; overflow:hidden; aspect-ratio:16/10; background:#24211f; }
.igf-donation-cause-card__media>img { display:block; width:100%; height:100%; object-fit:cover; transition:transform .35s ease; }
.igf-donation-cause-card:hover .igf-donation-cause-card__media>img { transform:scale(1.035); }
.igf-donation-cause-card__placeholder { position:relative; display:grid; width:100%; height:100%; place-items:center; overflow:hidden; background:linear-gradient(135deg,color-mix(in srgb,var(--ink) 88%,#fff),var(--brown)); color:var(--brand-on-dark); font-size:52px; }
.igf-donation-cause-card__placeholder::before { position:absolute; right:-14%; bottom:-48%; width:78%; aspect-ratio:1; border:1px solid rgba(255,255,255,.15); border-radius:50%; content:''; box-shadow:0 0 0 32px rgba(255,255,255,.04),0 0 0 64px rgba(255,255,255,.025); }
.igf-donation-cause-card__placeholder[data-variant="2"] { background:linear-gradient(135deg,color-mix(in srgb,var(--ink) 90%,#184b4b),color-mix(in srgb,var(--brown) 62%,#315f5d)); color:var(--brand-on-dark); }
.igf-donation-cause-card__placeholder[data-variant="3"] { background:linear-gradient(135deg,color-mix(in srgb,var(--ink) 84%,var(--brown)),var(--brown)); color:var(--brand-on-dark); }
.igf-donation-cause-card__placeholder[data-variant="4"] { background:linear-gradient(135deg,color-mix(in srgb,var(--ink) 88%,#315f42),color-mix(in srgb,var(--brown) 48%,#527553)); color:var(--brand-on-dark); }
.igf-donation-cause-card__placeholder[data-variant="5"] { background:linear-gradient(135deg,color-mix(in srgb,var(--ink) 88%,#3d3866),color-mix(in srgb,var(--brown) 44%,#665b96)); color:var(--brand-on-dark); }
.igf-donation-cause-card__placeholder[data-variant="6"] { background:linear-gradient(135deg,color-mix(in srgb,var(--ink) 84%,var(--brown)),color-mix(in srgb,var(--brown) 82%,var(--orange))); color:var(--brand-on-dark); }
.igf-donation-cause-card__placeholder>i { position:relative; z-index:1; }
.igf-donation-cause-card__selected { position:absolute; top:13px; right:13px; display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:7px 10px; background:#fff; color:var(--brown); font-size:10px; font-weight:850; letter-spacing:.04em; text-transform:uppercase; box-shadow:0 5px 15px rgba(0,0,0,.16); }
.igf-donation-cause-card__body { display:flex; flex:1; flex-direction:column; padding:24px; }
.igf-donation-cause-card__body h3 { margin:0; font-family:'Literata',Georgia,serif; font-size:clamp(23px,2.2vw,28px); font-weight:650; letter-spacing:-.025em; line-height:1.18; }
.igf-donation-cause-card__body>p { margin:12px 0 22px; color:var(--muted); font-size:15px; line-height:1.6; }
.igf-donation-cause-card__body>a { display:flex; min-height:50px; align-items:center; justify-content:center; gap:9px; margin-top:auto; border:0; border-radius:11px; padding:12px 18px; background:var(--action-orange); color:var(--igf-on-accent,#fff); font:800 14px/1.2 'Hanken Grotesk',Arial,sans-serif; text-decoration:none; transition:background-color .16s,transform .16s; }
.igf-donation-cause-card__body>a:hover { background:var(--action-orange-hover); }
.igf-donation-cause-card__body>a:focus-visible { outline:3px solid color-mix(in srgb,var(--brown) 34%,transparent); outline-offset:4px; }
.igf-donate__section { padding:clamp(54px,7vw,88px) 0; background:var(--surface); }
.has-cause-gallery .igf-donate__section { border-top:1px solid #e4e1de; }
.igf-donate__layout { display:grid; grid-template-columns:minmax(260px,.72fr) minmax(0,1.28fr); align-items:start; gap:clamp(45px,8vw,105px); }
.igf-donate__aside { position:sticky; top:120px; padding-top:30px; }
.igf-donate__aside .igf-eyebrow { color:var(--brown); }
.igf-donate__aside h2 { margin:0 0 20px; font-size:clamp(32px,4vw,46px); font-weight:620; line-height:1.12; }
.igf-donate__aside-copy>p:not(.igf-eyebrow) { color:var(--muted); font-size:16px; line-height:1.7; }
.igf-text-link { display:inline-flex; gap:8px; margin-top:12px; color:var(--brown); font-weight:800; text-decoration:none; }
.igf-help-card { display:flex; gap:13px; margin-top:40px; border-top:1px solid var(--line); padding-top:24px; }
.igf-help-card>i { color:var(--orange); font-size:21px; }
.igf-help-card strong,.igf-help-card a { display:block; }
.igf-help-card a { margin-top:4px; color:var(--muted); font-size:13px; text-decoration:none; }
.igf-cause-story { overflow:hidden; border:1px solid #ded7d1; border-radius:22px; background:#fff; box-shadow:0 18px 42px rgba(44,34,27,.1); }
.igf-cause-story__media { overflow:hidden; aspect-ratio:16/10; background:#2b2927; }
.igf-cause-story__media>img,.igf-cause-story__media>.igf-donation-cause-card__placeholder { display:block; width:100%; height:100%; object-fit:cover; }
.igf-cause-story__body { padding:clamp(22px,3vw,32px); }
.igf-cause-story__body>p:not(.igf-eyebrow) { margin:0; color:var(--muted); font-size:16px; line-height:1.72; }
.igf-cause-story__destination { display:grid; grid-template-columns:42px minmax(0,1fr); align-items:center; gap:12px; margin-top:24px; border-radius:13px; padding:15px; background:#eef5f2; color:#18372f; }
.igf-cause-story__destination>i { display:grid; width:42px; height:42px; place-items:center; border-radius:50%; background:#fff; color:var(--brown); }
.igf-cause-story__destination small,.igf-cause-story__destination strong { display:block; }
.igf-cause-story__destination small { margin-bottom:2px; font-size:10px; font-weight:850; letter-spacing:.06em; text-transform:uppercase; }
.igf-cause-story__destination strong { font-size:14px; line-height:1.35; overflow-wrap:anywhere; }
.igf-donation-card { border:1px solid #e6d9cf; border-top:5px solid var(--orange); border-radius:24px; padding:clamp(25px,5vw,52px); background:#fff; box-shadow:0 24px 60px rgba(41,31,23,.12); }
.is-card-outlined .igf-donation-card { border-width:2px; border-top-width:5px; box-shadow:none; }
.is-card-elevated .igf-donation-card { border-color:transparent; box-shadow:0 22px 55px rgba(25,28,29,.16); }
.is-card-soft .igf-donation-card { background:linear-gradient(145deg,#fff 0%,#fffaf6 100%); }
.is-layout-centered .igf-donate__layout { width:min(calc(100% - 40px),1100px); grid-template-columns:1fr; gap:32px; }
.is-layout-centered .igf-donate__aside { position:static; display:grid; grid-template-columns:minmax(0,1fr) minmax(220px,.52fr); align-items:end; gap:30px; padding-top:0; }
.is-layout-centered .igf-help-card { margin-top:0; border:1px solid var(--line); border-radius:12px; padding:20px; background:#fff; }
.is-layout-centered .igf-donation-card { order:1; }
.is-layout-centered .igf-donate__aside { order:2; }
.is-cause-page .igf-donate__layout { width:min(calc(100% - 40px),1200px); grid-template-columns:minmax(290px,.72fr) minmax(0,1.28fr); gap:clamp(30px,5vw,64px); }
.is-cause-page .igf-donate__aside { position:sticky; top:112px; display:block; order:0; padding-top:0; }
.is-cause-page .igf-donation-card { order:0; padding:clamp(25px,3.5vw,42px); }
.is-cause-page .igf-checkout-grid { grid-template-columns:1fr; }
.is-cause-page .igf-donation-review { position:static; margin-top:8px; }
.is-cause-page .igf-help-card { margin-top:24px; border:1px solid var(--line); border-radius:12px; padding:18px; background:#fff; }
.igf-donation-card__toolbar { display:flex; min-height:31px; align-items:center; justify-content:space-between; gap:18px; margin-bottom:8px; }
.igf-donation-card__header { display:flex; align-items:center; gap:8px; color:var(--brown); font-size:11px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
.igf-donation-languages { display:inline-flex; gap:4px; border:1px solid #e3d5ca; border-radius:999px; padding:3px; background:#f7f1ec; }
.igf-donation-languages a { min-width:42px; border-radius:999px; padding:6px 10px; color:#665f5a; font-size:11px; font-weight:800; line-height:1; text-align:center; text-decoration:none; }
.igf-donation-languages a[aria-current="page"] { background:var(--action-orange); color:var(--igf-on-accent,#fff); }
.igf-donation-card h2 { margin:0; font-size:clamp(34px,4vw,46px); font-weight:650; }
.igf-card-intro { margin:10px 0 24px; color:var(--muted); font-size:13px; }
.igf-checkout-grid { display:grid; gap:28px; }
.is-layout-centered .igf-checkout-grid { grid-template-columns:minmax(0,1fr) minmax(245px,.43fr); align-items:start; }
.is-layout-centered.is-cause-page .igf-checkout-grid { grid-template-columns:1fr; }
.igf-checkout-main { min-width:0; }
.igf-checkout-steps { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; margin:18px 0 22px; padding:0; list-style:none; }
.igf-checkout-steps a { display:flex; min-height:48px; align-items:center; gap:9px; border:1px solid #dfd5cd; border-radius:11px; padding:8px 11px; background:#f7f3ef; color:#68635f; font-size:11px; font-weight:800; line-height:1.2; text-decoration:none; }
.igf-checkout-steps a>span { display:grid; width:26px; height:26px; flex:0 0 26px; place-items:center; border:1px solid #cfc1b7; border-radius:50%; background:#fff; color:var(--brown); font-size:11px; }
.igf-checkout-steps li.is-current a { border-color:var(--brand-border); background:var(--brand-soft); color:var(--brown); }
.igf-checkout-steps li.is-current a>span { border-color:var(--action-orange); background:var(--action-orange); color:var(--igf-on-accent,#fff); }
.igf-checkout-steps a[aria-disabled="true"] { cursor:not-allowed; opacity:.68; }
.igf-checkout-section { min-width:0; scroll-margin-top:130px; }
.igf-checkout-section--details { border-top:1px solid var(--line); padding-top:26px; }
.igf-checkout-section__heading { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:8px; }
.igf-checkout-section__heading>div { display:flex; align-items:center; gap:10px; }
.igf-checkout-section__heading>div>span { display:grid; width:30px; height:30px; place-items:center; border-radius:50%; background:var(--action-orange); color:var(--igf-on-accent,#fff); font-size:12px; font-weight:850; }
.igf-checkout-section__heading h3 { margin:0; font-family:'Literata',Georgia,serif; font-size:21px; font-weight:650; letter-spacing:-.02em; }
.igf-gift-intro h3:focus-visible,.igf-checkout-section__heading h3:focus-visible { border-radius:4px; outline:3px solid color-mix(in srgb,var(--brown) 30%,transparent); outline-offset:4px; }
.igf-checkout-section__heading>button { border:0; border-bottom:1px solid currentColor; padding:3px 0; background:transparent; color:var(--brown); font-size:11px; font-weight:800; cursor:pointer; }
.igf-gift-intro { margin:26px 0 15px; }
.igf-gift-intro h3 { margin:0; color:var(--ink); font-family:'Literata',Georgia,serif; font-size:21px; font-weight:650; letter-spacing:-.02em; }
.igf-gift-intro p { margin:4px 0 0; color:var(--muted); font-size:13px; line-height:1.5; }
.igf-frequency-fieldset { min-width:0; margin:0 0 25px; border:0; padding:0; }
.igf-frequency-tabs { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:5px; border:1px solid #e7d9ce; border-radius:13px; padding:5px; background:#f4eee9; }
.igf-frequency-tabs button { display:flex; min-width:0; min-height:56px; flex-direction:column; align-items:center; justify-content:center; gap:1px; border:0; border-radius:9px; padding:7px 3px; background:transparent; color:#5f625f; font:800 12px 'Hanken Grotesk',Arial,sans-serif; cursor:pointer; }
.igf-frequency-tabs button.is-selected { background:var(--action-orange); color:var(--igf-on-accent,#fff); box-shadow:0 5px 13px var(--brand-shadow); }
.igf-frequency-tabs button.is-unavailable { color:#77726e; cursor:not-allowed; }
.igf-frequency-tabs button small { color:color-mix(in srgb,var(--brown) 82%,#000); font-size:7px; font-weight:900; letter-spacing:.04em; line-height:1.05; text-transform:uppercase; }
.igf-frequency-tabs button.is-selected small { color:var(--brand-on-dark); }
.igf-frequency-help { display:flex; gap:7px; margin:9px 2px 0; color:#716a65; font-size:10.5px; line-height:1.45; }
.igf-frequency-help i { margin-top:2px; color:var(--brown); }
.igf-step-continue { display:flex; width:100%; min-height:54px; align-items:center; justify-content:center; gap:8px; border:0; border-radius:var(--igf-button-radius,12px); padding:13px 18px; background:var(--action-orange); color:var(--igf-on-accent,#fff); font:800 15px/1.2 'Hanken Grotesk',Arial,sans-serif; cursor:pointer; box-shadow:0 8px 18px var(--brand-shadow); }
.igf-step-continue:hover:not(:disabled) { background:var(--action-orange-hover); }
.igf-step-continue:focus-visible { outline:3px solid color-mix(in srgb,var(--brown) 30%,transparent); outline-offset:3px; }
.igf-step-continue:disabled { background:#d2c7be; color:#6d6661; cursor:not-allowed; box-shadow:none; }
.igf-fieldset { min-width:0; margin:0 0 30px; border:0; border-top:1px solid var(--line); padding:26px 0 0; }
.igf-fieldset legend { width:auto; margin:0 0 16px; padding:0; color:var(--ink); font-size:14px; font-weight:800; }
.igf-amount-options { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:11px; margin-bottom:15px; }
.igf-amount-options button { position:relative; display:grid; min-width:0; min-height:98px; align-content:center; gap:5px; border:1.5px solid #ddd1c8; border-radius:14px; padding:15px 42px 15px 16px; background:#fff; color:var(--ink); text-align:left; cursor:pointer; transition:border-color .16s,background-color .16s,box-shadow .16s,transform .16s; }
.igf-amount-options button:hover { border-color:var(--brand-border); transform:translateY(-1px); }
.igf-amount-options button>span { color:var(--brown); font-family:'Literata',Georgia,serif; font-size:24px; font-weight:650; letter-spacing:-.02em; line-height:1.1; }
.igf-amount-options button>small { color:var(--muted); font:500 11px/1.35 'Hanken Grotesk',Arial,sans-serif; overflow-wrap:anywhere; }
.igf-amount-options button>i { position:absolute; top:13px; right:13px; display:grid; width:22px; height:22px; place-items:center; border:1px solid #d8cec6; border-radius:50%; background:#fff; color:transparent; font-size:10px; }
.igf-amount-options button.is-featured { background:var(--brand-soft); }
.igf-amount-options button.is-selected { border-color:var(--orange); background:var(--brand-soft-strong); box-shadow:inset 0 0 0 1px var(--orange); }
.igf-amount-options button.is-selected>span { color:var(--brown); }
.igf-amount-options button.is-selected>i { border-color:var(--action-orange); background:var(--action-orange); color:var(--igf-on-accent,#fff); }
.igf-amount-options .igf-custom-amount-option { border-style:dashed; background:#faf8f6; }
.igf-amount-options .igf-custom-amount-option>i { color:var(--brown); }
.igf-amount-options .igf-custom-amount-option.is-selected { border-style:solid; background:var(--brand-soft-strong); }
.igf-amount-options .igf-custom-amount-option.is-selected>i { color:#fff; }
.igf-details-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.igf-donation-card :deep(.v-field) { border-radius:9px; background:#fff; }
.igf-donation-card :deep(.v-field--focused) { color:var(--brown); }
.igf-native-field{display:grid;gap:7px;color:var(--ink);font-size:12px;font-weight:700}.igf-native-field select{width:100%;min-height:56px;border:1px solid #79747e;border-radius:9px;padding:0 15px;background:#fff;color:var(--ink);font:500 16px 'Hanken Grotesk',Arial,sans-serif}.igf-native-field select:focus{border:2px solid var(--brown);outline:2px solid transparent}
.igf-locked-cause { display:grid; grid-template-columns:44px minmax(0,1fr) 28px; align-items:center; gap:12px; min-height:72px; border:1px solid var(--brand-border); border-radius:13px; padding:13px 15px; background:var(--brand-soft); color:var(--ink); }
.igf-locked-cause>i:first-child { display:grid; width:44px; height:44px; place-items:center; border-radius:50%; background:#fff; color:var(--brown); font-size:18px; }
.igf-locked-cause>i:last-child { color:var(--brown); text-align:center; }
.igf-locked-cause small,.igf-locked-cause strong { display:block; }
.igf-locked-cause small { margin-bottom:2px; color:var(--brown); font-size:10px; font-weight:850; letter-spacing:.05em; text-transform:uppercase; }
.igf-locked-cause strong { font-size:15px; line-height:1.35; overflow-wrap:anywhere; }
.igf-field-help { margin:9px 0 0; color:var(--muted); font-size:12px; line-height:1.5; }
.igf-cause-alert { margin:12px 0 0!important; padding:12px 13px; border-radius:8px; background:var(--brand-soft); color:var(--brown)!important; font-size:12px!important; line-height:1.5; }
.igf-selection-warning { margin:12px 0 0; padding:12px 13px; border-left:4px solid #a52b1a; border-radius:8px; background:#fff1ef; color:#842516; font-size:12px; font-weight:700; line-height:1.5; }
.igf-project-field { margin-top:18px; }
.igf-fixed-project { display:grid; gap:4px; margin-top:18px; padding:14px 15px; border:1px solid #d7d0ca; border-radius:9px; background:#f7f5f3; color:var(--ink); }
.igf-fixed-project span { color:var(--brown); font-size:10px; font-weight:850; letter-spacing:.05em; text-transform:uppercase; }.igf-fixed-project strong { font-size:15px; line-height:1.35; }.igf-fixed-project small { color:var(--muted); font-size:11px; line-height:1.45; }
.igf-destination-summary { display:grid; grid-template-columns:38px minmax(0,1fr); align-items:start; gap:12px; margin-top:18px; padding:15px; border:1px solid var(--brand-border); border-radius:11px; background:var(--brand-soft); }
.igf-destination-summary>i { display:grid; width:38px; height:38px; place-items:center; border-radius:50%; background:#fff; color:var(--brown); }.igf-destination-summary small,.igf-destination-summary strong { display:block; }.igf-destination-summary small { margin-bottom:3px; color:var(--brown); font-size:10px; font-weight:850; letter-spacing:.06em; text-transform:uppercase; }.igf-destination-summary strong { color:var(--ink); font-size:15px; line-height:1.35; }.igf-destination-summary p { margin:5px 0 0; color:var(--muted); font-size:11px; line-height:1.5; }
.igf-payment-methods>.igf-field-help { margin:-7px 0 15px; }
.igf-payment-method-grid { display:grid; grid-template-columns:1fr; gap:11px; }
.igf-payment-method { position:relative; display:grid; grid-template-columns:132px minmax(0,1fr) 24px; align-items:center; gap:14px; min-height:102px; margin:0; border:1px solid #d2cbc5; border-radius:12px; padding:16px 18px; background:#fff; color:var(--ink); cursor:pointer; transition:border-color .16s,background-color .16s,box-shadow .16s,transform .16s; }
.igf-payment-method:hover { border-color:var(--brand-border); transform:translateY(-1px); }
.igf-payment-method:focus-within { border-color:var(--orange); outline:3px solid color-mix(in srgb,var(--orange) 23%,transparent); outline-offset:3px; }
.igf-payment-method.is-selected { border-color:var(--orange); background:var(--brand-soft); box-shadow:inset 0 0 0 1px var(--orange); }
.igf-payment-method.is-unavailable { grid-template-columns:132px minmax(0,1fr); border-style:dashed; background:#f4f2f0; color:#6f6965; cursor:not-allowed; }
.igf-payment-method.is-unavailable:hover { border-color:#d2cbc5; transform:none; }
.igf-payment-method>input { position:absolute; width:1px; height:1px; margin:0; opacity:0; pointer-events:none; }
.igf-payment-method__icon { display:grid; width:42px; height:42px; place-items:center; border-radius:11px; background:var(--brand-soft); color:var(--brown); font-size:18px; }
.igf-payment-method.is-unavailable .igf-payment-method__icon { background:#e7e3df; color:#756e69; }
.igf-payment-method__logos { display:flex; width:132px; height:58px; align-items:center; justify-content:flex-start; gap:7px; border-radius:10px; padding:7px 5px; background:#fff; }
.igf-payment-method__logos img { display:block; width:auto; max-width:122px; height:auto; max-height:44px; object-fit:contain; }
.igf-payment-method__logos.has-multiple img { max-width:52px; max-height:36px; }
.igf-payment-method__copy { display:grid; align-content:start; gap:4px; min-width:0; }
.igf-payment-method__copy strong { font-size:16px; line-height:1.3; }
.igf-payment-method__copy small { color:var(--muted); font-size:12px; font-weight:500; line-height:1.42; }
.igf-payment-method__copy .igf-payment-method__networks { color:var(--brown); font-weight:800; letter-spacing:.02em; }
.igf-payment-method__copy .igf-payment-method__unavailable { color:#7a3d19; font-weight:750; }
.igf-payment-method__check { display:grid; width:22px; height:22px; place-items:center; border:1px solid #cfc7c1; border-radius:50%; background:#fff; color:transparent; font-size:10px; }
.igf-payment-method.is-selected .igf-payment-method__check { border-color:var(--action-orange); background:var(--action-orange); color:var(--igf-on-accent,#fff); }
.igf-field-error { margin:10px 0 0; color:#a52b1a; font-size:12px; font-weight:750; line-height:1.5; }
.igf-payment-methods[aria-invalid="true"] .igf-payment-method-grid { border-radius:14px; outline:2px solid rgba(165,43,26,.2); outline-offset:4px; }
.igf-gateway-note { display:grid; grid-template-columns:38px 1fr; gap:12px; margin-bottom:18px; border:1px solid #eadfd6; border-radius:10px; padding:15px; background:#f8f5f2; }
.igf-gateway-note>i { display:grid; width:38px; height:38px; place-items:center; border-radius:50%; background:#fff; color:var(--brown); }
.igf-gateway-note strong { font-size:13px; }
.igf-gateway-note p { margin:3px 0 0; color:var(--muted); font-size:11px; line-height:1.55; }
.igf-privacy-note { display:flex; gap:9px; margin:0 0 18px; color:var(--muted); font-size:12px; line-height:1.55; }
.igf-privacy-note i { margin-top:2px; color:var(--brown); }
.igf-donation-review { position:sticky; top:118px; display:grid; gap:16px; border:1px solid var(--brand-border); border-radius:17px; padding:20px; background:var(--brand-soft); box-shadow:0 12px 30px rgba(67,47,31,.08); }
.igf-donation-review__heading { display:flex; align-items:center; gap:11px; }
.igf-donation-review__heading>span { display:grid; width:38px; height:38px; flex:0 0 38px; place-items:center; border-radius:50%; background:#fff; color:var(--brown); box-shadow:0 4px 12px rgba(67,47,31,.08); }
.igf-donation-review__heading small { display:block; color:var(--brown); font-size:8px; font-weight:850; letter-spacing:.08em; line-height:1.2; text-transform:uppercase; }
.igf-donation-review__heading h3 { margin:2px 0 0; font-family:'Literata',Georgia,serif; font-size:20px; font-weight:650; letter-spacing:-.02em; }
.igf-donation-review dl { display:grid; gap:0; margin:0; }
.igf-donation-review dl>div { display:grid; grid-template-columns:minmax(72px,.7fr) minmax(0,1.3fr); gap:10px; border-top:1px solid #eadfd6; padding:11px 0; }
.igf-donation-review dt { color:#766f69; font-size:10px; font-weight:750; }
.igf-donation-review dd { margin:0; color:var(--ink); font-size:12px; font-weight:800; line-height:1.35; text-align:right; overflow-wrap:anywhere; }
.igf-donation-review>p:not(.igf-terms) { margin:0; color:var(--muted); font-size:10.5px; line-height:1.5; }
.igf-submit { min-height:58px!important; border-radius:var(--igf-button-radius,13px)!important; background:var(--action-orange)!important; color:var(--igf-on-accent,#fff)!important; font-size:16px!important; font-weight:800!important; letter-spacing:0!important; text-transform:none!important; box-shadow:0 9px 20px var(--brand-shadow)!important; }
.igf-submit:hover { background:var(--action-orange-hover)!important; }
.igf-submit:focus-visible { outline:3px solid color-mix(in srgb,var(--brown) 30%,transparent)!important; outline-offset:3px!important; }
.igf-terms { margin:15px 0 0; color:#777277; font-size:11px; line-height:1.5; text-align:center; }
.igf-terms a { color:var(--brown); }
.sr-only { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; }
@media (max-width:1000px) { .igf-donate-causes__grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .is-layout-centered .igf-checkout-grid { grid-template-columns:1fr; } .igf-donation-review { position:static; } }
@media (max-width:900px) { .igf-donate__layout,.is-cause-page .igf-donate__layout { grid-template-columns:1fr; } .igf-trust-list { grid-template-columns:repeat(3,1fr); } .igf-donate__aside,.is-layout-centered .igf-donate__aside,.is-cause-page .igf-donate__aside { position:static; grid-template-columns:1fr; padding-top:0; } .is-layout-centered .igf-help-card { margin-top:0; } .igf-cause-content__section.is-media-left,.igf-cause-content__section.is-media-right { grid-template-columns:1fr; } .igf-cause-content__section.is-media-right .igf-cause-content__media { order:0; } }
@media (max-width:700px) { .igf-donate-causes__grid,.igf-trust-list { grid-template-columns:1fr; } .igf-amount-options { grid-template-columns:1fr 1fr; } .igf-frequency-tabs { grid-template-columns:1fr 1fr; } }
@media (max-width:620px) { .igf-payment-method { grid-template-columns:132px minmax(0,1fr) 22px; gap:11px; padding-inline:14px; } .igf-payment-method.is-unavailable { grid-template-columns:132px minmax(0,1fr); } }
@media (max-width:640px) { .igf-shell,.is-layout-centered .igf-donate__layout,.is-cause-page .igf-donate__layout { width:min(calc(100% - 28px),1200px); } .igf-details-grid { grid-template-columns:1fr; } .igf-donate__hero { min-height:auto; padding:58px 0; } .igf-donate-causes { padding:54px 0; } .igf-donation-cause-card__body { padding:21px; } .igf-donation-card { border-radius:17px; padding:24px 20px; } .igf-checkout-steps a { min-height:44px; padding-inline:8px; } .igf-cause-story { border-radius:17px; } }
@media (max-width:480px) { .igf-payment-method { grid-template-columns:96px minmax(0,1fr) 22px; gap:9px; padding:13px 11px; } .igf-payment-method.is-unavailable { grid-template-columns:96px minmax(0,1fr); } .igf-payment-method__logos { width:96px; padding-inline:2px; } .igf-payment-method__logos img { max-width:92px; } .igf-payment-method__logos.has-multiple { gap:4px; } .igf-payment-method__logos.has-multiple img { max-width:41px; } .igf-payment-method__copy strong { font-size:14px; } .igf-payment-method__copy small { font-size:11px; } .igf-amount-options { gap:8px; } .igf-amount-options button { min-height:84px; padding:12px 34px 12px 12px; } .igf-amount-options button>span { font-size:20px; } .igf-amount-options button>small { font-size:10px; } .igf-checkout-grid { gap:20px; } .igf-donation-review { padding:17px; } }
@media (max-width:420px) { .igf-amount-options { grid-template-columns:1fr; } }
@media (prefers-reduced-motion:reduce) { .igf-donation-cause-card,.igf-donation-cause-card__media>img,.igf-donation-cause-card__body>a { transition:none; } .igf-donation-cause-card:hover { transform:none; } .igf-donation-cause-card:hover .igf-donation-cause-card__media>img { transform:none; } }
</style>
