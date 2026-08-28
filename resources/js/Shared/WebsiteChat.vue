<template>
  <div v-if="ready && enabled" class="igf-chat" :class="{ 'is-open': isOpen }">
    <button
      ref="launcher"
      class="igf-chat__launcher"
      type="button"
      :aria-expanded="isOpen ? 'true' : 'false'"
      aria-controls="igf-chat-panel"
      :aria-label="isOpen ? copy.closeChat : `${copy.openChat}: ${title}`"
      :title="isOpen ? copy.closeChat : copy.chatWithUs"
      @click="toggleChat"
    >
      <svg v-if="!isOpen" class="igf-chat__launcher-icon igf-chat__launcher-icon--open" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4.4 4.5A2.4 2.4 0 0 1 6.8 2h10.4a2.4 2.4 0 0 1 2.4 2.5v8.1a2.4 2.4 0 0 1-2.4 2.5H11l-4.7 4.1c-.7.6-1.8.1-1.8-.9v-3.4a2.4 2.4 0 0 1-2.1-2.4v-8Z" />
        <path d="M7.2 7.3h9.6M7.2 10.5h6.4" class="igf-chat__icon-lines" />
      </svg>
      <svg v-else class="igf-chat__launcher-icon igf-chat__launcher-icon--close" viewBox="0 0 24 24" aria-hidden="true">
        <path d="m6.5 6.5 11 11m0-11-11 11" class="igf-chat__icon-close" />
      </svg>
    </button>

    <section
      v-show="isOpen"
      id="igf-chat-panel"
      ref="panel"
      class="igf-chat__panel"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      :aria-describedby="welcomeId"
      @keydown="trapPanelFocus"
    >
      <header class="igf-chat__header">
        <div class="igf-chat__brand-mark">
          <img class="igf-chat__brand-logo" :src="chatBrand.logo" :alt="chatBrand.logoAlt">
        </div>
        <div class="igf-chat__heading">
          <p class="igf-chat__eyebrow">{{ copy.igniteSupport }}</p>
          <h2 :id="titleId">{{ title }}</h2>
          <p class="igf-chat__presence"><span aria-hidden="true" /> {{ copy.presence }}</p>
        </div>
        <button ref="closeButton" class="igf-chat__close" type="button" :aria-label="copy.closeChat" @click="closeChat">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6.5 6.5 11 11m0-11-11 11" /></svg>
        </button>
      </header>

      <div ref="messageArea" class="igf-chat__body">
        <div v-if="loading" class="igf-chat__loading" role="status" aria-live="polite">
          <span aria-hidden="true" />
          <span aria-hidden="true" />
          <span aria-hidden="true" />
          <span class="igf-chat__sr-only">{{ copy.loading }}</span>
        </div>

        <template v-else>
          <div class="igf-chat__welcome">
            <div class="igf-chat__avatar" aria-hidden="true">{{ chatInitials }}</div>
            <div>
              <p :id="welcomeId">{{ welcomeMessage }}</p>
              <small>{{ copy.chooseQuestion }}</small>
            </div>
          </div>

          <div
            v-if="messages.length"
            class="igf-chat__messages"
            role="log"
            aria-live="polite"
            aria-relevant="additions text"
            :aria-label="copy.conversationMessages"
          >
            <article
              v-for="message in messages"
              :key="message.id"
              class="igf-chat__message"
              :class="`is-${messageKind(message)}`"
            >
              <p>{{ message.body }}</p>
              <footer>
                <span>{{ messageSender(message) }}</span>
                <time v-if="message.created_at" :datetime="message.created_at">{{ formatTime(message.created_at) }}</time>
                <span v-if="message.is_automated" class="igf-chat__automated">{{ copy.instantAnswer }}</span>
              </footer>
            </article>
          </div>

          <div
            v-if="localFaqMessages.length"
            class="igf-chat__local-faq"
            role="status"
            aria-live="polite"
            :aria-label="copy.instantAnswers"
          >
            <p class="igf-chat__local-faq-title">{{ copy.instantAnswers }}</p>
            <article
              v-for="message in localFaqMessages"
              :key="message.id"
              class="igf-chat__message"
              :class="`is-${messageKind(message)}`"
            >
              <p>{{ message.body }}</p>
              <footer>
                <span>{{ messageSender(message) }}</span>
                <time :datetime="message.created_at">{{ formatTime(message.created_at) }}</time>
                <span v-if="message.is_automated" class="igf-chat__automated">{{ copy.instantAnswer }}</span>
              </footer>
            </article>
          </div>

          <div v-if="quickQuestions.length && !isClosed" class="igf-chat__quick" aria-labelledby="igf-chat-quick-title">
            <p id="igf-chat-quick-title" class="igf-chat__sr-only">{{ copy.popularQuestions }}</p>
            <div>
              <button
                v-for="question in quickQuestions"
                :key="question.id"
                type="button"
                :disabled="sending"
                @click="askQuickQuestion(question)"
              >
                {{ question.question }}
              </button>
            </div>
          </div>

          <div v-if="conversation && isClosed" class="igf-chat__notice" role="status">
            <span>{{ copy.closedConversation }}</span>
            <button type="button" @click="startNewEnquiry">{{ copy.startNewEnquiry }}</button>
          </div>

          <div v-if="errorMessage" class="igf-chat__error" role="alert">
            {{ errorMessage }}
          </div>
        </template>
      </div>

      <form v-if="!loading && !isClosed" class="igf-chat__composer" @submit.prevent="submitComposer">
        <fieldset v-if="needsGuestDetails" class="igf-chat__contact">
          <legend>{{ copy.tellUsWho }}</legend>
          <p>{{ copy.identityHelp }}</p>
          <label>
            <span>{{ copy.name }} <b aria-hidden="true">*</b></span>
            <input ref="guestNameInput" v-model.trim="guest.name" type="text" name="chat_name" autocomplete="name" maxlength="120" required>
          </label>
          <div class="igf-chat__contact-row">
            <label>
              <span>{{ copy.email }}</span>
              <input v-model.trim="guest.email" type="email" name="chat_email" autocomplete="email" maxlength="150">
            </label>
            <label>
              <span>{{ copy.phone }}</span>
              <input v-model.trim="guest.phone" type="tel" name="chat_phone" autocomplete="tel" maxlength="30">
            </label>
          </div>
          <small id="igf-chat-contact-help">{{ copy.contactHelp }}</small>
        </fieldset>

        <div v-else-if="viewerName" class="igf-chat__identity">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4.2 4.2 0 1 0 0-8.4 4.2 4.2 0 0 0 0 8.4Zm-7.1 8.4c.4-3.6 3.5-6.2 7.1-6.2s6.7 2.6 7.1 6.2" /></svg>
          {{ copy.askingAs }} <strong>{{ viewerName }}</strong>
        </div>

        <label v-if="needsGuestDetails" class="igf-chat__question-label" for="igf-chat-message">{{ copy.yourQuestion }}</label>
        <div class="igf-chat__message-field">
          <textarea
            id="igf-chat-message"
            ref="messageInput"
            v-model="draft"
            name="chat_message"
            rows="2"
            maxlength="2000"
            :placeholder="copy.placeholder"
            :disabled="sending"
            :aria-label="copy.yourQuestion"
            :aria-describedby="composerHelpIds"
            @keydown="handleComposerKeydown"
          />
          <button type="submit" :disabled="!canSend" :aria-label="submitLabel">
            <svg v-if="!sending" viewBox="0 0 24 24" aria-hidden="true"><path d="m4 4 17 8-17 8 2.6-6.1L15 12 6.6 10.1 4 4Z" /></svg>
            <span v-else class="igf-chat__spinner" aria-hidden="true" />
          </button>
        </div>
        <p v-if="isGuestFirstStep" class="igf-chat__continue-hint">{{ copy.continueHint }}</p>
        <div class="igf-chat__composer-meta" :class="{ 'has-privacy': privacyMessage }">
          <span>{{ draft.length }}/2000</span>
          <p v-if="privacyMessage" id="igf-chat-privacy">{{ privacyMessage }}</p>
        </div>
      </form>
    </section>
  </div>
</template>

<script setup>
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { formatTime as formatRegionalTime } from './composables/siteSettings'

defineOptions({ name: 'WebsiteChat' })

const CHAT_COPY = {
  en: {
    openChat: 'Open chat',
    closeChat: 'Close support chat',
    close: 'Close',
    chatWithUs: 'Chat with us',
    igniteSupport: 'Ignite support',
    presence: 'We usually reply as soon as possible',
    loading: 'Loading chat',
    chooseQuestion: 'Choose a question below or write your own.',
    conversationMessages: 'Conversation messages',
    instantAnswers: 'Instant answers',
    instantAnswer: 'Instant answer',
    popularQuestions: 'Popular questions',
    closedConversation: 'This conversation is closed.',
    startNewEnquiry: 'Start a new enquiry',
    tellUsWho: 'Tell us who you are',
    identityHelp: 'So the team can identify your question and reply to you.',
    name: 'Name',
    email: 'Email',
    phone: 'Phone',
    contactHelp: 'Enter an email address or phone number so we can follow up.',
    askingAs: 'Asking as',
    yourQuestion: 'Your question',
    placeholder: 'Type your question...',
    continue: 'Continue',
    continueHint: 'For a custom question, continue to add your contact details before sending.',
    sending: 'Sending question',
    send: 'Send question',
    identificationError: 'Please enter your name and either an email address or phone number.',
    genericError: 'We could not send your question. Please try again.',
    you: 'You',
    update: 'Update',
    assistant: 'Ignite assistant',
    support: 'Ignite support',
  },
  bn: {
    openChat: 'চ্যাট খুলুন',
    closeChat: 'সহায়তা চ্যাট বন্ধ করুন',
    close: 'বন্ধ করুন',
    chatWithUs: 'আমাদের সঙ্গে চ্যাট করুন',
    igniteSupport: 'ইগনাইট সহায়তা',
    presence: 'আমরা সাধারণত দ্রুত উত্তর দিই',
    loading: 'চ্যাট লোড হচ্ছে',
    chooseQuestion: 'নিচের একটি প্রশ্ন বেছে নিন অথবা নিজের প্রশ্ন লিখুন।',
    conversationMessages: 'কথোপকথনের বার্তা',
    instantAnswers: 'তাৎক্ষণিক উত্তর',
    instantAnswer: 'তাৎক্ষণিক উত্তর',
    popularQuestions: 'জনপ্রিয় প্রশ্ন',
    closedConversation: 'এই কথোপকথনটি বন্ধ হয়েছে।',
    startNewEnquiry: 'নতুন প্রশ্ন শুরু করুন',
    tellUsWho: 'আপনার পরিচয় দিন',
    identityHelp: 'আপনার প্রশ্ন শনাক্ত করে উত্তর দেওয়ার জন্য।',
    name: 'নাম',
    email: 'ইমেইল',
    phone: 'ফোন',
    contactHelp: 'ফলোআপের জন্য ইমেইল ঠিকানা বা ফোন নম্বর দিন।',
    askingAs: 'প্রশ্ন করছেন',
    yourQuestion: 'আপনার প্রশ্ন',
    placeholder: 'আপনার প্রশ্ন লিখুন...',
    continue: 'এগিয়ে যান',
    continueHint: 'নিজের প্রশ্ন পাঠাতে এগিয়ে গিয়ে যোগাযোগের তথ্য দিন।',
    sending: 'প্রশ্ন পাঠানো হচ্ছে',
    send: 'প্রশ্ন পাঠান',
    identificationError: 'আপনার নাম এবং ইমেইল ঠিকানা অথবা ফোন নম্বর দিন।',
    genericError: 'আপনার প্রশ্ন পাঠানো যায়নি। আবার চেষ্টা করুন।',
    you: 'আপনি',
    update: 'আপডেট',
    assistant: 'ইগনাইট সহকারী',
    support: 'ইগনাইট সহায়তা',
  },
}

const CHAT_SETTING_KEYS = {
  openChat: 'open_chat', closeChat: 'close_chat', close: 'close', chatWithUs: 'chat_with_us',
  igniteSupport: 'support_eyebrow', presence: 'presence', loading: 'loading', chooseQuestion: 'choose_question',
  conversationMessages: 'conversation_messages', instantAnswers: 'instant_answers', instantAnswer: 'instant_answer',
  popularQuestions: 'popular_questions', closedConversation: 'closed_conversation', startNewEnquiry: 'start_new_enquiry',
  tellUsWho: 'tell_us_who', identityHelp: 'identity_help', name: 'name', email: 'email', phone: 'phone',
  contactHelp: 'contact_help', askingAs: 'asking_as', yourQuestion: 'your_question', placeholder: 'placeholder',
  continue: 'continue', continueHint: 'continue_hint', sending: 'sending', send: 'send',
  identificationError: 'identification_error', genericError: 'generic_error', you: 'you', update: 'update',
  assistant: 'assistant', support: 'support',
}

const page = usePage()
const regional = computed(() => page.props.siteSettings?.regional || {})
const ready = ref(false)
const enabled = ref(false)
const loading = ref(true)
const sending = ref(false)
const polling = ref(false)
const isOpen = ref(false)
const errorMessage = ref('')
const title = ref('Chat with us')
const welcomeMessage = ref('Hello! How can we help you today?')
const privacyMessage = ref('')
const locale = ref(typeof document !== 'undefined' && String(document.documentElement.lang).toLowerCase().startsWith('bn') ? 'bn' : 'en')
const viewer = ref(null)
const quickQuestions = ref([])
const conversation = ref(null)
const localFaqMessages = ref([])
const draft = ref('')
const showGuestDetails = ref(false)
const launcher = ref(null)
const panel = ref(null)
const closeButton = ref(null)
const messageArea = ref(null)
const messageInput = ref(null)
const guestNameInput = ref(null)
const guest = reactive({ name: '', email: '', phone: '' })
const titleId = 'igf-chat-title'
const welcomeId = 'igf-chat-welcome'
let pollTimer = null

const messages = computed(() => Array.isArray(conversation.value?.messages) ? conversation.value.messages : [])
const chatBrand = computed(() => {
  const branding = page.props.siteSettings?.branding || {}
  const siteName = branding.site_name || page.props.appName || 'Ignite Global Foundation'
  return {
    siteName,
    logo: branding.logo || '/image/logo.png',
    logoAlt: branding.logo_alt || siteName,
  }
})
const chatInitials = computed(() => {
  const label = String(chatBrand.value.siteName || title.value || '').trim()
  const words = label.match(/[\p{L}\p{N}]+/gu) || []
  return words.slice(0, 2)
    .map(word => Array.from(word)[0] || '')
    .join('')
    .toLocaleUpperCase(locale.value || 'en') || '?'
})
const copy = computed(() => {
  const defaults = CHAT_COPY[locale.value] || CHAT_COPY.en
  const configured = page.props.siteSettings?.chat_widget || {}
  return Object.fromEntries(Object.entries(defaults).map(([key, fallback]) => {
    const value = configured[CHAT_SETTING_KEYS[key]]
    return [key, typeof value === 'string' && value.trim() ? value.trim() : fallback]
  }))
})
const isAuthenticated = computed(() => Boolean(viewer.value?.authenticated ?? viewer.value?.is_authenticated ?? viewer.value?.id))
const viewerName = computed(() => viewer.value?.name || conversation.value?.visitor_name || guest.name || '')
const isGuestFirstStep = computed(() => !conversation.value && !isAuthenticated.value && !showGuestDetails.value)
const needsGuestDetails = computed(() => !conversation.value && !isAuthenticated.value && showGuestDetails.value)
const isClosed = computed(() => String(conversation.value?.status || '').toLowerCase() === 'closed')
const hasGuestContact = computed(() => Boolean(guest.email.trim() || guest.phone.trim()))
const composerHelpIds = computed(() => {
  const ids = []
  if (needsGuestDetails.value) ids.push('igf-chat-contact-help')
  if (privacyMessage.value) ids.push('igf-chat-privacy')
  return ids.join(' ') || undefined
})
const canSend = computed(() => {
  return !sending.value && !isClosed.value && Boolean(draft.value.trim())
})
const submitLabel = computed(() => {
  if (sending.value) return copy.value.sending
  return isGuestFirstStep.value ? copy.value.continue : copy.value.send
})

onMounted(loadBootstrap)
onBeforeUnmount(() => {
  stopPolling()
  document.removeEventListener('keydown', onDocumentKeydown)
})

watch(isOpen, async open => {
  document.removeEventListener('keydown', onDocumentKeydown)
  if (!open) {
    stopPolling()
    return
  }

  document.addEventListener('keydown', onDocumentKeydown)
  if (conversation.value) startPolling()
  await nextTick()
  scrollToLatest(false)
  const focusTarget = messageInput.value || closeButton.value
  focusTarget?.focus({ preventScroll: true })
})

async function loadBootstrap() {
  loading.value = true
  try {
    const response = await axios.get(route('chat.bootstrap'))
    applyBootstrap(response.data)
  } catch {
    enabled.value = false
  } finally {
    loading.value = false
    ready.value = true
  }
}

function applyBootstrap(payload = {}) {
  const data = payload.data && typeof payload.data === 'object' ? payload.data : payload
  enabled.value = data.enabled !== false
  title.value = data.title || title.value
  welcomeMessage.value = data.welcome_message || welcomeMessage.value
  privacyMessage.value = data.privacy_message || ''
  viewer.value = data.viewer || null
  quickQuestions.value = normalizeQuickQuestions(data.quick_questions)
  conversation.value = normalizeConversation(data.conversation)
  showGuestDetails.value = false

  guest.name = conversation.value?.visitor_name || ''
  guest.email = conversation.value?.visitor_email || ''
  guest.phone = conversation.value?.visitor_phone || ''
}

function normalizeQuickQuestions(items) {
  if (!Array.isArray(items)) return []
  return items
    .map((item, index) => typeof item === 'string'
      ? { id: `question-${index}`, question: item }
      : { ...item, id: item.id ?? item.uuid ?? `question-${index}`, question: item.question ?? item.label ?? '' })
    .filter(item => item.question)
}

function normalizeConversation(payload) {
  if (!payload || typeof payload !== 'object') return null
  const unwrapped = payload.data && typeof payload.data === 'object' ? payload.data : payload
  const data = unwrapped.conversation && typeof unwrapped.conversation === 'object' ? unwrapped.conversation : unwrapped
  if (!data.id && !data.uuid) return null
  return { ...data, id: data.id ?? data.uuid, messages: Array.isArray(data.messages) ? data.messages : [] }
}

function toggleChat() {
  if (isOpen.value) closeChat()
  else isOpen.value = true
}

function closeChat() {
  isOpen.value = false
  nextTick(() => launcher.value?.focus({ preventScroll: true }))
}

async function startNewEnquiry() {
  stopPolling()
  conversation.value = null
  localFaqMessages.value = []
  draft.value = ''
  showGuestDetails.value = false
  errorMessage.value = ''
  await nextTick()
  messageInput.value?.focus({ preventScroll: true })
}

function onDocumentKeydown(event) {
  if (event.key === 'Escape' && isOpen.value) closeChat()
}

function trapPanelFocus(event) {
  if (event.key !== 'Tab' || !panel.value) return
  const focusable = [...panel.value.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')]
    .filter(element => element.getClientRects().length > 0)
  if (!focusable.length) return

  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

function handleComposerKeydown(event) {
  if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return
  event.preventDefault()
  submitComposer()
}

async function askQuickQuestion(question) {
  const askedAt = new Date()
  const answeredAt = new Date(askedAt.getTime() + 1)
  localFaqMessages.value = [
    ...localFaqMessages.value,
    {
      id: `local-question-${question.id}-${askedAt.getTime()}`,
      sender_type: 'visitor',
      body: question.question,
      is_automated: false,
      created_at: askedAt.toISOString(),
    },
    {
      id: `local-answer-${question.id}-${answeredAt.getTime()}`,
      sender_type: 'automation',
      body: question.answer || '',
      is_automated: true,
      created_at: answeredAt.toISOString(),
    },
  ]
  errorMessage.value = ''
  await nextTick()
  scrollToLatest()

  axios.post(route('chat.faqs.click'), { faq_id: question.id }).catch(() => {})
}

async function submitComposer() {
  if (!canSend.value) {
    return
  }

  if (isGuestFirstStep.value) {
    showGuestDetails.value = true
    errorMessage.value = ''
    await nextTick()
    guestNameInput.value?.focus({ preventScroll: true })
    return
  }

  if (needsGuestDetails.value && (!guest.name.trim() || !hasGuestContact.value)) {
    errorMessage.value = copy.value.identificationError
    await nextTick()
    if (!guest.name.trim()) guestNameInput.value?.focus({ preventScroll: true })
    else panel.value?.querySelector('[name="chat_email"]')?.focus({ preventScroll: true })
    return
  }

  await sendMessage()
}

async function sendMessage() {
  if (!canSend.value) return

  sending.value = true
  errorMessage.value = ''
  const body = draft.value.trim()

  try {
    let response
    if (!conversation.value) {
      const payload = {
        body,
        page_url: currentPageUrl(),
      }
      if (!isAuthenticated.value) {
        payload.name = guest.name.trim()
        payload.email = guest.email.trim() || null
        payload.phone = guest.phone.trim() || null
      }
      response = await axios.post(route('chat.conversations.store'), payload)
    } else {
      response = await axios.post(route('chat.messages.store', { conversation: conversation.value.id }), {
        body,
      })
    }

    const updated = normalizeConversation(response.data)
    if (updated) conversation.value = updated
    draft.value = ''
    showGuestDetails.value = false
    startPolling()
    await nextTick()
    scrollToLatest()
    messageInput.value?.focus({ preventScroll: true })
  } catch (error) {
    errorMessage.value = firstErrorMessage(error)
  } finally {
    sending.value = false
  }
}

function firstErrorMessage(error) {
  if (locale.value === 'bn') return copy.value.genericError
  const errors = error.response?.data?.errors
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors).flat().find(Boolean)
    if (first) return String(first)
  }
  return error.response?.data?.message || copy.value.genericError
}

function startPolling() {
  if (pollTimer || !isOpen.value || !conversation.value) return
  pollTimer = window.setInterval(refreshConversation, 8000)
}

function stopPolling() {
  if (!pollTimer) return
  window.clearInterval(pollTimer)
  pollTimer = null
}

async function refreshConversation() {
  if (polling.value || sending.value || !isOpen.value || !conversation.value || document.visibilityState === 'hidden') return
  polling.value = true
  const previousCount = messages.value.length
  try {
    const response = await axios.get(route('chat.conversations.show', { conversation: conversation.value.id }))
    const updated = normalizeConversation(response.data)
    if (updated) conversation.value = updated
    if (messages.value.length > previousCount) {
      await nextTick()
      scrollToLatest()
    }
  } catch (error) {
    if (error.response?.status === 404 || error.response?.status === 403) stopPolling()
  } finally {
    polling.value = false
  }
}

function currentPageUrl() {
  return `${window.location.origin}${window.location.pathname}`
}

function scrollToLatest(smooth = true) {
  if (!messageArea.value) return
  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
  messageArea.value.scrollTo({
    top: messageArea.value.scrollHeight,
    behavior: smooth && !reducedMotion ? 'smooth' : 'auto',
  })
}

function messageKind(message) {
  const sender = String(message.sender_type ?? message.sender ?? message.role ?? '').toLowerCase()
  if (['visitor', 'user', 'donor', 'customer'].includes(sender)) return 'visitor'
  if (['system'].includes(sender)) return 'system'
  return 'support'
}

function messageSender(message) {
  const kind = messageKind(message)
  if (kind === 'visitor') return copy.value.you
  if (kind === 'system') return copy.value.update
  return message.is_automated ? copy.value.assistant : (message.sender_name || copy.value.support)
}

function formatTime(value) {
  return formatRegionalTime(value, regional.value)
}
</script>

<style scoped>
.igf-chat {
  --chat-orange: var(--igf-primary, #ff7500);
  --chat-action: #9b3f00;
  --chat-brown: var(--igf-accent, #9c4500);
  --chat-ink: var(--igf-ink, #191c1d);
  --chat-muted: #62656a;
  --chat-line: #e5dfda;
  position: fixed;
  z-index: 1450;
  right: calc(22px + env(safe-area-inset-right, 0px));
  bottom: calc(22px + env(safe-area-inset-bottom, 0px));
  color: var(--chat-ink);
  font-family: 'Hanken Grotesk', Arial, sans-serif;
}
.igf-chat *, .igf-chat *::before, .igf-chat *::after { box-sizing: border-box; }
.igf-chat button, .igf-chat input, .igf-chat textarea { font: inherit; }
.igf-chat button { cursor: pointer; }
.igf-chat button:focus-visible, .igf-chat input:focus-visible, .igf-chat textarea:focus-visible {
  outline: 3px solid color-mix(in srgb, var(--chat-orange) 45%, white);
  outline-offset: 2px;
}
.igf-chat__launcher {
  position: relative;
  display: inline-flex;
  width: 56px;
  min-width: 56px;
  height: 56px;
  align-items: center;
  justify-content: center;
  float: right;
  border: 0;
  border-radius: 50%;
  padding: 0;
  background: var(--chat-action);
  color: #fff;
  box-shadow: 0 13px 34px rgba(72, 32, 0, .28);
  font-size: 14px;
  font-weight: 850;
  letter-spacing: .01em;
  transition: transform .2s ease, box-shadow .2s ease, background-color .2s ease;
}
.igf-chat__launcher:hover { background: #7b3200; transform: translateY(-2px); box-shadow: 0 17px 38px rgba(72, 32, 0, .32); }
.igf-chat__launcher:focus-visible { outline: 2px solid #fff; outline-offset: 2px; box-shadow: 0 0 0 5px #211f1e, 0 13px 34px rgba(72, 32, 0, .28); }
.igf-chat__launcher svg { width: 24px; height: 24px; overflow: visible; fill: currentColor; }
.igf-chat__launcher .igf-chat__icon-lines, .igf-chat__launcher .igf-chat__icon-close { fill: none; stroke: currentColor; stroke-linecap: round; stroke-width: 1.7; }
.igf-chat__launcher .igf-chat__icon-close { stroke-width: 2; }
.igf-chat.is-open .igf-chat__launcher { display: none; }
.igf-chat__panel {
  position: absolute;
  right: 0;
  bottom: 76px;
  display: grid;
  width: min(410px, calc(100vw - 36px));
  height: min(680px, calc(100vh - 118px));
  grid-template-rows: auto minmax(0, 1fr) auto;
  overflow: hidden;
  border: 1px solid rgba(93, 62, 39, .16);
  border-radius: 22px;
  background: #fff;
  box-shadow: 0 24px 70px rgba(25, 28, 29, .25);
  animation: igf-chat-in .22s ease-out both;
}
.igf-chat.is-open .igf-chat__panel { bottom: 0; }
.igf-chat__header {
  position: relative;
  display: grid;
  grid-template-columns: 54px minmax(0, 1fr) 36px;
  gap: 12px;
  align-items: center;
  padding: 19px 18px;
  background: linear-gradient(135deg, #211f1e 0%, #32302f 100%);
  color: #fff;
}
.igf-chat__brand-mark { display: grid; width: 54px; height: 50px; place-items: center; overflow: hidden; border-radius: 10px; padding: 4px 5px; background: #fff; box-shadow: inset 0 0 0 1px rgba(255,255,255,.28), 0 3px 12px rgba(0,0,0,.2); }
.igf-chat__brand-logo { display: block; width: 100%; height: 100%; object-fit: contain; }
.igf-chat__heading { min-width: 0; }
.igf-chat__eyebrow { margin: 0 0 2px; color: #ffbd8d; font-size: 9px; font-weight: 850; letter-spacing: .12em; text-transform: uppercase; }
.igf-chat__heading h2 { overflow: hidden; margin: 0; color: #fff; font-family: 'Literata', Georgia, serif; font-size: 20px; font-weight: 650; line-height: 1.2; text-overflow: ellipsis; white-space: nowrap; }
.igf-chat__presence { display: flex; align-items: center; gap: 6px; margin: 5px 0 0; color: #d8d4d1; font-size: 10px; }
.igf-chat__presence span { width: 7px; height: 7px; border-radius: 50%; background: #42c76b; box-shadow: 0 0 0 3px rgba(66, 199, 107, .14); }
.igf-chat__close { display: grid; width: 36px; height: 36px; place-items: center; border: 1px solid rgba(255,255,255,.18); border-radius: 50%; background: rgba(255,255,255,.07); color: #fff; }
.igf-chat__close:hover { background: rgba(255,255,255,.16); }
.igf-chat__close svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-width: 2; }
.igf-chat__body { overflow-x: hidden; overflow-y: auto; padding: 18px; background: #f7f5f3; overscroll-behavior: contain; scrollbar-width: thin; scrollbar-color: #c9c2bd transparent; }
.igf-chat__welcome { display: grid; grid-template-columns: 34px minmax(0, 1fr); gap: 10px; align-items: start; margin-bottom: 16px; }
.igf-chat__avatar { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 11px; background: #211f1e; color: #fff; font-size: 10px; font-weight: 850; letter-spacing: .05em; }
.igf-chat__welcome > div:last-child { border: 1px solid var(--chat-line); border-radius: 4px 14px 14px; padding: 11px 13px; background: #fff; box-shadow: 0 4px 12px rgba(25,28,29,.04); }
.igf-chat__welcome p { margin: 0; color: var(--chat-ink); font-size: 13px; line-height: 1.5; white-space: pre-line; }
.igf-chat__welcome small { display: block; margin-top: 5px; color: var(--chat-muted); font-size: 10px; line-height: 1.4; }
.igf-chat__messages { display: grid; gap: 10px; }
.igf-chat__local-faq { display: grid; gap: 10px; margin-top: 16px; border-top: 1px solid #ded8d3; padding-top: 13px; }
.igf-chat__local-faq-title { margin: 0; color: #5f5a56; font-size: 10px; font-weight: 850; letter-spacing: .08em; text-transform: uppercase; }
.igf-chat__message { width: fit-content; max-width: 87%; }
.igf-chat__message > p { margin: 0; border: 1px solid var(--chat-line); border-radius: 4px 14px 14px; padding: 10px 12px; background: #fff; color: var(--chat-ink); font-size: 13px; line-height: 1.5; overflow-wrap: anywhere; white-space: pre-wrap; }
.igf-chat__message footer { display: flex; flex-wrap: wrap; gap: 4px 7px; align-items: center; margin: 4px 4px 0; color: #777278; font-size: 9px; }
.igf-chat__message footer time::before { content: '\00b7'; margin-right: 7px; }
.igf-chat__message.is-visitor { justify-self: end; }
.igf-chat__message.is-visitor > p { border-color: var(--chat-action); border-radius: 14px 4px 14px 14px; background: var(--chat-action); color: #fff; }
.igf-chat__message.is-visitor footer { justify-content: flex-end; }
.igf-chat__message.is-system { justify-self: center; max-width: 100%; text-align: center; }
.igf-chat__message.is-system > p { border: 0; border-radius: 999px; padding: 6px 11px; background: #e9e5e1; color: #625d59; font-size: 10px; }
.igf-chat__message.is-system footer { display: none; }
.igf-chat__automated { border-radius: 999px; padding: 2px 5px; background: #fff0e4; color: var(--chat-brown); font-weight: 800; }
.igf-chat__quick { margin-top: 10px; }
.igf-chat__quick > div { display: flex; flex-wrap: wrap; gap: 6px; align-items: flex-start; }
.igf-chat__quick button { display: block; flex: 0 1 auto; max-width: 100%; border: 1px solid color-mix(in srgb, var(--chat-action) 32%, white); border-radius: 999px; padding: 6px 11px; background: #fff; color: var(--chat-action); font-size: 12.5px; font-weight: 400; line-height: 1.5; overflow-wrap: anywhere; text-align: center; transition: border-color .15s ease, background-color .15s ease, color .15s ease; }
.igf-chat__quick button:hover { border-color: var(--chat-orange); background: #fff8f2; color: #7b3200; }
.igf-chat__quick button:disabled { cursor: wait; opacity: .6; }
.igf-chat__notice, .igf-chat__error { margin-top: 14px; border-radius: 10px; padding: 10px 12px; font-size: 11px; line-height: 1.45; }
.igf-chat__notice { border: 1px solid #dcd6d1; background: #fff; color: #605b57; }
.igf-chat__notice button { display: block; min-height: 38px; margin-top: 9px; border: 1px solid var(--chat-action); border-radius: 8px; padding: 7px 11px; background: var(--chat-action); color: #fff; font-weight: 800; }
.igf-chat__notice button:hover { background: #7b3200; }
.igf-chat__error { border: 1px solid #edb8ae; background: #fff0ed; color: #922c1d; }
.igf-chat__composer { border-top: 1px solid var(--chat-line); padding: 13px 15px 12px; background: #fff; }
.igf-chat__contact { display: grid; gap: 8px; margin: 0 0 10px; border: 0; padding: 0; }
.igf-chat__contact legend { padding: 0; color: var(--chat-ink); font-size: 11px; font-weight: 850; }
.igf-chat__contact > p { margin: -5px 0 1px; color: var(--chat-muted); font-size: 9px; }
.igf-chat__contact label { display: grid; gap: 4px; color: #595458; font-size: 9px; font-weight: 750; }
.igf-chat__contact label b { color: #bb2e1c; }
.igf-chat__contact input { width: 100%; height: 34px; border: 1px solid #cdc5bf; border-radius: 8px; padding: 0 9px; background: #fff; color: var(--chat-ink); font-size: 11px; }
.igf-chat__contact-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.igf-chat__contact > small { color: #5f5a56; font-size: 10px; line-height: 1.4; }
.igf-chat__identity { display: flex; align-items: center; gap: 5px; margin-bottom: 8px; color: var(--chat-muted); font-size: 9px; }
.igf-chat__identity svg { width: 14px; height: 14px; fill: none; stroke: var(--chat-orange); stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.7; }
.igf-chat__identity strong { color: var(--chat-ink); }
.igf-chat__question-label { display: block; margin: 0 0 6px; color: var(--chat-ink); font-size: 10px; font-weight: 850; }
.igf-chat__message-field { display: grid; grid-template-columns: minmax(0, 1fr) 42px; gap: 8px; align-items: end; border: 1px solid #cbc3bd; border-radius: 13px; padding: 6px; background: #fff; transition: border-color .15s ease, box-shadow .15s ease; }
.igf-chat__message-field:focus-within { border-color: var(--chat-orange); box-shadow: 0 0 0 3px color-mix(in srgb, var(--chat-orange) 13%, white); }
.igf-chat__message-field textarea { width: 100%; max-height: 90px; resize: none; border: 0; padding: 7px 5px; background: transparent; color: var(--chat-ink); font-size: 12px; line-height: 1.4; outline: 0; }
.igf-chat__message-field textarea::placeholder { color: #938c87; }
.igf-chat__message-field button { display: grid; width: 42px; height: 42px; place-items: center; border: 0; border-radius: 10px; background: var(--chat-action); color: #fff; }
.igf-chat__message-field button:hover:not(:disabled) { background: #7b3200; }
.igf-chat__message-field button:disabled { cursor: not-allowed; opacity: .45; }
.igf-chat__message-field button svg { width: 21px; height: 21px; fill: currentColor; }
.igf-chat__continue-hint { margin: 6px 2px 0; color: #5f5a56; font-size: 10px; line-height: 1.4; }
.igf-chat__composer-meta { display: flex; justify-content: flex-end; margin-top: 6px; color: #5f5a56; font-size: 10px; }
.igf-chat__composer-meta.has-privacy { justify-content: space-between; gap: 10px; }
.igf-chat__composer-meta > span { flex: 0 0 auto; }
.igf-chat__composer-meta p { max-width: 250px; margin: 0; line-height: 1.4; text-align: right; }
.igf-chat__loading { display: flex; min-height: 180px; align-items: center; justify-content: center; gap: 5px; }
.igf-chat__loading > span:not(.igf-chat__sr-only) { width: 7px; height: 7px; border-radius: 50%; background: var(--chat-orange); animation: igf-chat-dot 1s infinite ease-in-out alternate; }
.igf-chat__loading > span:nth-child(2) { animation-delay: .18s; }
.igf-chat__loading > span:nth-child(3) { animation-delay: .36s; }
.igf-chat__spinner { width: 17px; height: 17px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: igf-chat-spin .7s linear infinite; }
.igf-chat__sr-only { position: absolute !important; width: 1px !important; height: 1px !important; overflow: hidden !important; clip: rect(0, 0, 0, 0) !important; white-space: nowrap !important; }
@keyframes igf-chat-in { from { opacity: 0; transform: translateY(12px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes igf-chat-dot { to { opacity: .28; transform: translateY(-4px); } }
@keyframes igf-chat-spin { to { transform: rotate(360deg); } }
@media (max-width: 620px) {
  .igf-chat { right: calc(14px + env(safe-area-inset-right, 0px)); bottom: calc(14px + env(safe-area-inset-bottom, 0px)); }
  .igf-chat.is-open { inset: 0; }
  .igf-chat__panel { position: fixed; inset: 0; width: 100%; height: 100dvh; border: 0; border-radius: 0; padding-bottom: env(safe-area-inset-bottom); }
  .igf-chat__header { padding-top: max(17px, env(safe-area-inset-top)); }
  .igf-chat__body { padding: 16px; }
  .igf-chat__composer { padding-right: 12px; padding-left: 12px; }
}
@media (max-width: 390px) {
  .igf-chat__contact-row { grid-template-columns: 1fr; }
}
@media (prefers-reduced-motion: reduce) {
  .igf-chat__panel, .igf-chat__launcher, .igf-chat__loading > span, .igf-chat__spinner { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
}
</style>
