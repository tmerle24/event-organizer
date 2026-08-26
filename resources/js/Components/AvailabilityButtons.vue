<script setup>
import { useI18n } from 'vue-i18n'

/**
 * Drei Zustände plus "offen". Offen ist der Default und wird nie als Ablehnung
 * dargestellt (Spec Abschnitt 4, Schritt 2).
 *
 * Farblogik nach Brand Guide Abschnitt 4: die Antworten laufen komplett in der
 * Violett-Familie, "kann nicht" in Slate. Kein Rot im System — es gibt hier
 * nichts, was man falsch machen kann. Mint bleibt dem gefundenen Termin
 * vorbehalten und taucht in den Antwort-Buttons bewusst nicht auf.
 */
defineProps({
  value: { type: String, default: null },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:value'])
const { t } = useI18n()

const OPTIONS = [
  { key: 'yes', icon: '✓', color: 'var(--od-violet)' },
  { key: 'maybe', icon: '~', color: 'var(--od-violet-soft)' },
  { key: 'no', icon: '✕', color: 'var(--od-slate)' },
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
      class="flex h-11 w-11 items-center justify-center border text-base font-medium transition disabled:opacity-40"
      :style="
        value === option.key
          ? { background: option.color, borderColor: option.color, color: '#fff', borderRadius: 'var(--od-radius-sm)' }
          : {
              background: 'var(--od-white)',
              borderColor: 'var(--od-line)',
              color: 'var(--od-slate)',
              borderRadius: 'var(--od-radius-sm)',
            }
      "
      @click="pick(option.key, value)"
    >
      {{ option.icon }}
    </button>
  </div>
</template>
