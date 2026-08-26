<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

/**
 * Inline-SVG statt Bilddatei: der Brand-Name kommt aus APP_NAME, damit der
 * endgueltige Name spaeter an genau einer Stelle geaendert wird.
 */
const props = defineProps({
  size: { type: String, default: 'md' },
  light: { type: Boolean, default: false },
})

const name = computed(() => usePage().props.appName || 'Plandu')
const textClass = computed(() => (props.size === 'lg' ? 'text-2xl' : 'text-lg'))
const box = computed(() => (props.size === 'lg' ? 32 : 26))
</script>

<template>
  <span class="inline-flex items-center gap-2 font-display font-bold" :class="[textClass, light ? 'text-white' : 'text-[var(--color-pl-ink)]']">
    <svg :width="box" :height="box" viewBox="0 0 32 32" fill="none" aria-hidden="true">
      <rect x="2" y="5" width="28" height="25" rx="7" :fill="light ? '#ffffff' : 'var(--color-pl-ink)'" />
      <path d="M9 2v6M23 2v6" :stroke="light ? '#ffffff' : 'var(--color-pl-ink)'" stroke-width="3" stroke-linecap="round" />
      <path d="M10.5 19.5l4 4 7.5-8.5" stroke="var(--color-pl-accent)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <span>{{ name }}</span>
  </span>
</template>
