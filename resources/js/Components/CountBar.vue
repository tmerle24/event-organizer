<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Zeigt nie eine Quote wie "8/8 verfügbar", solange Antworten fehlen —
 * offene Antworten stehen als eigene Zahl daneben (Spec Abschnitt 5).
 *
 * Tonalität nach Brand Guide Abschnitt 6: positiv zählen. Die Ja-Stimmen
 * stehen vorne, "offen" ist ein neutraler Hinweis, keine Mahnung.
 */
const props = defineProps({
  option: { type: Object, required: true },
  // true = dieser Termin passt allen bzw. steht fest → einziges Mint-Signal
  highlighted: { type: Boolean, default: false },
})

const { t } = useI18n()

const total = computed(
  () => props.option.yes_count + props.option.maybe_count + props.option.no_count + props.option.open_count
)

function width(count) {
  return total.value === 0 ? 0 : (count / total.value) * 100
}

const segments = computed(() => [
  { key: 'yes', count: props.option.yes_count, color: props.highlighted ? 'var(--od-mint)' : 'var(--od-violet)' },
  { key: 'maybe', count: props.option.maybe_count, color: 'var(--od-violet-soft)' },
  { key: 'no', count: props.option.no_count, color: 'var(--od-slate)' },
  { key: 'open', count: props.option.open_count, color: 'var(--od-line)' },
])
</script>

<template>
  <!-- Ohne eine einzige Rueckmeldung gibt es nichts zu zeigen: ein leerer
       Balken liest sich sonst wie ein Ergebnis. -->
  <div v-if="total > 0">
    <div class="od-bar flex h-2 overflow-hidden rounded-full" style="background: var(--od-line)">
      <span
        v-for="segment in segments"
        :key="segment.key"
        class="block h-full"
        :style="{ width: width(segment.count) + '%', background: segment.color }"
      />
    </div>

    <p class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-[13px]">
      <span
        v-if="option.yes_count"
        class="font-mono-num font-medium"
        :style="{ color: highlighted ? 'var(--od-mint)' : 'var(--od-violet)' }"
      >
        {{ option.yes_count }} {{ t('manage.counts.yes') }}
      </span>
      <span v-if="option.maybe_count" class="font-mono-num" style="color: var(--od-violet-soft)">
        {{ option.maybe_count }} {{ t('manage.counts.maybe') }}
      </span>
      <span v-if="option.no_count" class="font-mono-num" style="color: var(--od-slate)">
        {{ option.no_count }} {{ t('manage.counts.no') }}
      </span>
      <span v-if="option.open_count" class="font-mono-num" style="color: var(--od-slate)">
        {{ option.open_count }} {{ t('manage.counts.open') }}
      </span>
    </p>
  </div>
</template>
