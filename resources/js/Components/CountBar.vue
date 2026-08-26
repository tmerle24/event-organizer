<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Zeigt nie eine Quote wie "8/8 verfuegbar", solange Antworten fehlen —
 * offene Antworten stehen als eigene Zahl daneben (Spec Abschnitt 5).
 */
const props = defineProps({
  option: { type: Object, required: true },
})

const { t } = useI18n()

const total = computed(
  () => props.option.yes_count + props.option.maybe_count + props.option.no_count + props.option.open_count
)

function width(count) {
  return total.value === 0 ? 0 : (count / total.value) * 100
}

const segments = computed(() => [
  { key: 'yes', count: props.option.yes_count, color: 'var(--color-pl-accent)' },
  { key: 'maybe', count: props.option.maybe_count, color: 'var(--color-pl-maybe)' },
  { key: 'no', count: props.option.no_count, color: 'var(--color-pl-no)' },
  { key: 'open', count: props.option.open_count, color: 'var(--color-pl-line)' },
])
</script>

<template>
  <div>
    <div class="pl-bar flex h-2 overflow-hidden rounded-full bg-[var(--color-pl-line)]">
      <span
        v-for="segment in segments"
        :key="segment.key"
        class="block h-full"
        :style="{ width: width(segment.count) + '%', background: segment.color }"
      />
    </div>

    <p class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-[var(--color-pl-muted)]">
      <span v-if="option.yes_count" class="font-mono-num" style="color: var(--color-pl-accent)">
        {{ option.yes_count }} {{ t('manage.counts.yes') }}
      </span>
      <span v-if="option.maybe_count" class="font-mono-num" style="color: var(--color-pl-maybe)">
        {{ option.maybe_count }} {{ t('manage.counts.maybe') }}
      </span>
      <span v-if="option.no_count" class="font-mono-num" style="color: var(--color-pl-no)">
        {{ option.no_count }} {{ t('manage.counts.no') }}
      </span>
      <span v-if="option.open_count" class="font-mono-num">
        {{ option.open_count }} {{ t('manage.counts.open') }}
      </span>
    </p>
  </div>
</template>
