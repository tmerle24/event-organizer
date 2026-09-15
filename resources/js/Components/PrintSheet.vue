<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { currentLocale } from '@/i18n'
import { formatFull } from '@/composables/useDateFormat'

/**
 * Druck-Zettel fuer den Tag selbst: wer kommt, wer bringt was, was ist noch frei.
 * Auf dem Bildschirm unsichtbar, im Druck das einzige sichtbare Element.
 */
const props = defineProps({
  event: { type: Object, required: true },
})

const { t } = useI18n()

const hasPolling = computed(() => ['dates', 'both'].includes(props.event.mode))

const decided = computed(() => props.event.date_options.find((o) => o.id === props.event.decided_option_id) ?? null)

// Antwort zum festgelegten Termin; ohne Termin zaehlt jeder Eintrag als dabei
function answerOf(participant) {
  if (!hasPolling.value || !decided.value) return 'yes'
  return decided.value.votes?.[participant.id] ?? 'open'
}

const byName = (a, b) => a.display_name.localeCompare(b.display_name, currentLocale(), { sensitivity: 'base' })

const rows = computed(() =>
  props.event.participants
    .map((participant) => ({
      ...participant,
      answer: answerOf(participant),
      tasks: props.event.tasks.filter((task) => task.assignee_participant_id === participant.id).map((task) => task.title),
    }))
    // wer abgesagt hat, steht nicht an der Tuer
    .filter((row) => row.answer !== 'no')
    .sort(byName)
)

const coming = computed(() => rows.value.filter((row) => row.answer === 'yes').length)
const maybe = computed(() => rows.value.filter((row) => row.answer === 'maybe').length)
const withoutDecision = computed(() => !hasPolling.value || !decided.value)

const freeTasks = computed(() =>
  props.event.tasks.filter((task) => !task.assignee_participant_id && task.status !== 'done').map((task) => task.title)
)

const asOf = computed(() =>
  new Intl.DateTimeFormat(currentLocale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date())
)
</script>

<template>
  <Teleport to="body">
    <div id="od-report" aria-hidden="true">
      <header class="od-print-head">
        <h1 class="od-print-title">{{ event.title }}</h1>
        <p v-if="decided || event.location" class="od-print-meta">
          <span v-if="decided">{{ formatFull(decided) }}</span>
          <span v-if="decided && event.location"> · </span>
          <span v-if="event.location">{{ event.location }}</span>
        </p>
        <p class="od-print-counts">
          <template v-if="withoutDecision">{{ t('manage.print.present', rows.length) }}</template>
          <template v-else>
            {{ t('manage.print.coming', coming) }}<template v-if="maybe"> · {{ t('manage.print.maybe', maybe) }}</template>
          </template>
        </p>
      </header>

      <ul class="od-print-list">
        <li v-for="row in rows" :key="row.id" class="od-print-row">
          <span class="od-print-box" />
          <span class="od-print-name">
            {{ row.display_name }}
            <span v-if="row.answer === 'maybe'" class="od-print-note">({{ t('manage.counts.maybe', 1) }})</span>
            <span v-else-if="row.answer === 'open'" class="od-print-note">({{ t('manage.counts.open', 1) }})</span>
          </span>
          <span class="od-print-tasks">{{ row.tasks.join(', ') }}</span>
        </li>
      </ul>

      <section v-if="freeTasks.length" class="od-print-free">
        <h2 class="od-print-subtitle">{{ t('manage.print.free') }}</h2>
        <p>{{ freeTasks.join(', ') }}</p>
      </section>

      <p class="od-print-footer">{{ t('manage.print.as_of', { date: asOf }) }}</p>
    </div>
  </Teleport>
</template>
