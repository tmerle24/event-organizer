<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import WhoList from '@/Components/WhoList.vue'

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
  // ohne Teilnehmerliste kein Aufklappen
  participants: { type: Array, default: null },
  showOpen: { type: Boolean, default: false },
  showDeclined: { type: Boolean, default: true },
  // Schalter "Namen zeigen" im Kopf des Panels
  expandAll: { type: Boolean, default: false },
  /*
   * Gesteuert von aussen (Teilnehmerseite): ein "Wer?" klappt alle Termine auf
   * — bei drei Terminen will man ohnehin alle sehen. null = eigener Zustand.
   */
  open: { type: Boolean, default: null },
})

const emit = defineEmits(['toggle'])
const { t } = useI18n()

const localExpanded = ref(props.expandAll)
watch(
  () => props.expandAll,
  (value) => (localExpanded.value = value)
)

const expanded = computed(() => (props.open === null ? localExpanded.value : props.open))

function toggle() {
  if (props.open === null) {
    localExpanded.value = !localExpanded.value

    return
  }

  emit('toggle')
}

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

    <div class="mt-1.5 flex items-start justify-between gap-3">
      <p class="flex flex-wrap gap-x-3 gap-y-0.5 text-[13px]">
        <span
          v-if="option.yes_count"
          class="font-mono-num font-medium"
          :style="{ color: highlighted ? 'var(--od-mint)' : 'var(--od-violet)' }"
        >
          {{ option.yes_count }} {{ t('manage.counts.yes', option.yes_count) }}
        </span>
        <span v-if="option.maybe_count" class="font-mono-num" style="color: var(--od-violet-soft)">
          {{ option.maybe_count }} {{ t('manage.counts.maybe', option.maybe_count) }}
        </span>
        <span v-if="option.no_count" class="font-mono-num" style="color: var(--od-slate)">
          {{ option.no_count }} {{ t('manage.counts.no', option.no_count) }}
        </span>
        <span v-if="option.open_count" class="font-mono-num" style="color: var(--od-slate)">
          {{ option.open_count }} {{ t('manage.counts.open', option.open_count) }}
        </span>
      </p>

      <button
        v-if="participants"
        type="button"
        class="-my-1 shrink-0 rounded-lg px-1.5 py-1 text-[13px] text-[var(--od-slate)] hover:text-[var(--od-violet)]"
        :aria-expanded="expanded"
        @click="toggle"
      >
        {{ t('manage.dates.who') }} <span aria-hidden="true">{{ expanded ? '▴' : '▾' }}</span>
      </button>
    </div>

    <WhoList
      v-if="participants && expanded"
      class="mt-2"
      :option="option"
      :participants="participants"
      :show-open="showOpen"
      :show-declined="showDeclined"
    />
  </div>
</template>
