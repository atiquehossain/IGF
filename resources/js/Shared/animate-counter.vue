<template>
  <span class="text-h3 font-weight-bold">
    {{ displayNumber.toLocaleString() }}
  </span>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'

const props = defineProps({
  to: {
    type: Number,
    required: true
  },
  duration: {
    type: Number,
    default: 2000 // in milliseconds
  }
})

const displayNumber = ref(0)

const animate = () => {
  const startTime = performance.now()
  const startValue = 0

  const update = (currentTime) => {
    const elapsed = currentTime - startTime
    const progress = Math.min(elapsed / props.duration, 1)
    displayNumber.value = Math.floor(startValue + progress * props.to)

    if (progress < 1) {
      requestAnimationFrame(update)
    }
  }

  requestAnimationFrame(update)
}

// Run on mount and when `to` value changes
onMounted(animate)
watch(() => props.to, animate)
</script>
