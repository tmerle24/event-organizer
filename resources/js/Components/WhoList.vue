<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { currentLocale } from '@/i18n'
import { ANSWERS } from '@/composables/useMatch'

/**
 * Wer hat zu einem Termin wie geantwortet. Pflicht-Personen zuerst, damit bei
 * blockierten Terminen sofort sichtbar ist, woran es liegt.
 * "offen" nur fuer den Organisator — oeffentlich waere das eine Mahnung.
 */
const props = defineProps({
  option: { type: Object, required: true },
  participants: { type: Array, required: true },
  showOpen: { type: Boolean, default: false },
})

const { t } = useI18n()

const GROUPS = ['yes', 'maybe', 'no', 'open'].map((key) => ({ key, ...ANSWERS[key] }))

function byImportance(a, b) {
  if (a.is_required !== b.is_required) return a.is_required ? -1 : 1
  return a.display_name.localeCompare(b.display_name, currentLocale(), { sensitivity: 'base' })
}

const groups = computed(() => {
  const votes = props.option.votes ?? {}
  const sorted = [...props.participants].sort(byImportance)

  return GROUPS.filter((group) => group.key !== 'open' || props.showOpen)
    .map((group) => ({
      ...group,
      people: sorted.filter((p) => (votes[p.id] ?? 'open') === group.key),
    }))
    .filter((group) => group.people.length)
})
</script>

<template>
  <ul v-if="groups.length" class="space-y-1 text-[13px]">
    <li v-for="group in groups" :key="group.key" class="flex gap-2">
      <span
        class="w-3.5 shrink-0 text-center font-medium"
        :style="{ color: group.color }"
        :title="t(`manage.counts.${group.key}`, group.people.length)"
        aria-hidden="true"
      >{{ group.icon }}</span>
      <span class="sr-only">{{ t(`manage.counts.${group.key}`, group.people.length) }}:</span>
      <span :style="{ color: group.key === 'yes' || group.key === 'maybe' ? 'var(--od-ink)' : 'var(--od-slate)' }">
        <template v-if="group.key === 'open'">{{ t('manage.dates.still_open') }}: </template>
        <template v-for="(person, index) in group.people" :key="person.id">
          <span class="whitespace-nowrap">{{ person.display_name }}<template v-if="person.is_required"> ★</template></span><template v-if="index < group.people.length - 1"> · </template>
        </template>
      </span>
    </li>
  </ul>
</template>
