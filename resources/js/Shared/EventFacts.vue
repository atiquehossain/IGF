<template>
  <dl v-if="facts.length" class="igf-event-facts" :class="{ 'igf-event-facts--compact': compact }" :aria-label="factsLabel">
    <div v-for="fact in facts" :key="fact.key" :class="`igf-event-facts__item--${fact.key}`">
      <dt>{{ fact.label }}</dt>
      <dd>
        <time v-if="fact.datetime" :datetime="fact.datetime">{{ fact.value }}</time>
        <span v-else>{{ fact.value }}</span>
      </dd>
    </div>
  </dl>
</template>

<script setup>
import { computed } from 'vue'
import { formatDateTime } from './composables/siteSettings'

const props = defineProps({
  event: { type: Object, required: true },
  settings: { type: Object, default: () => ({}) },
  regional: { type: Object, default: () => ({}) },
  compact: { type: Boolean, default: false },
})

const statusLabels = Object.freeze({
  scheduled: ['event_status_scheduled_label', 'Scheduled'],
  postponed: ['event_status_postponed_label', 'Postponed'],
  rescheduled: ['event_status_rescheduled_label', 'Rescheduled'],
  'moved-online': ['event_status_moved_online_label', 'Moved online'],
  cancelled: ['event_status_cancelled_label', 'Cancelled'],
})
const attendanceLabels = Object.freeze({
  offline: ['event_attendance_offline_label', 'In person'],
  online: ['event_attendance_online_label', 'Online'],
  mixed: ['event_attendance_mixed_label', 'In person and online'],
})

const label = (key, fallback) => String(props.settings?.[key] || fallback)
const managedLabel = (map, key) => {
  const definition = map[String(key || '')]
  return definition ? label(definition[0], definition[1]) : ''
}

const factsLabel = computed(() => label('event_facts_label', 'Event schedule and attendance'))
const facts = computed(() => {
  if (props.event?.content_kind !== 'event') return []

  const start = formatDateTime(props.event.event_start_at, props.regional)
  const end = formatDateTime(props.event.event_end_at, props.regional)
  const status = managedLabel(statusLabels, props.event.event_status)
  const attendance = managedLabel(attendanceLabels, props.event.event_attendance_mode)

  return [
    { key: 'start', label: label('event_start_label', 'Starts'), value: start, datetime: props.event.event_start_at },
    { key: 'end', label: label('event_end_label', 'Ends'), value: end, datetime: props.event.event_end_at },
    { key: 'status', label: label('event_status_label', 'Status'), value: status },
    { key: 'attendance', label: label('event_attendance_label', 'Attendance'), value: attendance },
  ].filter(fact => fact.value)
})
</script>

<style scoped>
.igf-event-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:26px 0 0}.igf-event-facts>div{min-width:0;padding:14px 16px;border:1px solid #e2ddd8;border-radius:11px;background:#fff}.igf-event-facts dt{margin:0 0 4px;color:#856047;font-size:10px;font-weight:900;letter-spacing:.07em;text-transform:uppercase}.igf-event-facts dd{margin:0;color:#292725;font-size:14px;font-weight:750;line-height:1.45}.igf-event-facts__item--status dd{color:#974000}.igf-event-facts--compact{grid-template-columns:1fr;gap:7px;margin:0}.igf-event-facts--compact>div{display:grid;grid-template-columns:minmax(72px,.55fr) minmax(0,1fr);gap:8px;padding:8px 10px;border:0;border-radius:8px;background:#f7f3ef}.igf-event-facts--compact dt{margin:0}.igf-event-facts--compact dd{font-size:12px}@media(max-width:560px){.igf-event-facts{grid-template-columns:1fr}}
</style>
