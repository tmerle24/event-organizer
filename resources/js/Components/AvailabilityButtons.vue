<script setup>
import { useI18n } from 'vue-i18n'

/**
 * Drei Zustaende plus "offen". Offen ist der Default und wird nie als
 * Ablehnung dargestellt (Spec Abschnitt 4, Schritt 2).
 */
defineProps({
  value: { type: String, default: null },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:value'])
const { t } = useI18n()

const OPTIONS = [
  { key: 'yes', icon: '✓', color: 'var(--color-pl-accent)', soft: 'var(--color-pl-accent-soft)' },
  { key: 'maybe', icon: '?', color: 'var(--color-pl-maybe)', soft: 'var(--color-pl-maybe-soft)' },
  { key: 'no', icon: '✕', color: 'var(--color-pl-no)', soft: 'var(--color-pl-no-soft)' },
]

function pick(key, current) {
  emit('update:value', current === key ? null : key)
}
</script>

<template>
  <div class="flex gap-1.5" role="group">
    <button
      v-for="option in OPTIONS"
      :key="option.key"
      type="button"
      :disabled="disabled"
      :aria-pressed="value === option.key"
      :title="t(`manage.counts.${option.key}`)"
      class="flex h-10 w-10 items-center justify-center rounded-xl border text-base font-semibold transition disabled:opacity-40"
      :style="
        value === option.key
          ? { background: option.color, borderColor: option.color, color: '#fff' }
          : { background: 'var(--color-pl-surface)', borderColor: 'var(--color-pl-line)', color: 'var(--color-pl-muted)' }
      "
      @click="pick(option.key, value)"
    >
      {{ option.icon }}
    </button>
  </div>
</template>
