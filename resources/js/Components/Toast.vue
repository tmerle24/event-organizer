<script setup>
defineProps({
  message: { type: String, default: '' },
  tone: { type: String, default: 'ok' },
})
</script>

<template>
  <transition
    enter-active-class="transition duration-200"
    enter-from-class="opacity-0 translate-y-2"
    leave-active-class="transition duration-200"
    leave-to-class="opacity-0 translate-y-2"
  >
    <div
      v-if="message"
      class="fixed bottom-5 left-1/2 z-50 -translate-x-1/2 rounded-xl px-4 py-2.5 text-sm font-medium text-white shadow-lg"
      :style="{ background: tone === 'error' ? 'var(--color-pl-no)' : 'var(--color-pl-ink)' }"
      role="status"
    >
      {{ message }}
    </div>
  </transition>
</template>
