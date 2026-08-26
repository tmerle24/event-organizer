<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { mapsLink } from '@/composables/useMapsLink'
import { formatFull } from '@/composables/useDateFormat'

/**
 * Die editierbare Feldzeile aus Spec Abschnitt 4, Schritt 1: das Ergebnis der
 * Extraktion steht direkt ueber den Terminvorschlaegen und ist per Klick
 * aenderbar — kein Chat-Turn, kein Formularprozess.
 */
const props = defineProps({
  event: { type: Object, required: true },
  baseUrl: { type: String, required: true },
})

const emit = defineEmits(['updated', 'focus-change'])
const { t } = useI18n()

const open = ref(false)
const saving = ref(false)

const form = ref({
  title: props.event.title,
  description: props.event.description || '',
  location: props.event.location || '',
  participant_count_hint: props.event.participant_count_hint || null,
  planning_template: props.event.planning_template,
  fixed: splitOption(props.event.date_options.find((o) => o.id === props.event.decided_option_id) || null),
})

watch(
  () => props.event,
  (event) => {
    if (saving.value) return
    form.value = {
      title: event.title,
      description: event.description || '',
      location: event.location || '',
      participant_count_hint: event.participant_count_hint || null,
      planning_template: event.planning_template,
      fixed: splitOption(event.date_options.find((o) => o.id === event.decided_option_id) || null),
    }
  }
)

const TEMPLATES = ['barbecue', 'dinner', 'party', 'trip', 'meeting', 'generic']

/*
 * Fester Termin einer Organisationsliste. Er liegt als einzelne bestätigte
 * Terminoption vor; hier wird er für die beiden Eingabefelder zerlegt.
 */
const isList = computed(() => props.event.mode === 'list')

const decidedOption = computed(
  () => props.event.date_options.find((o) => o.id === props.event.decided_option_id) || null
)

function splitOption(option) {
  if (!option) return { date: '', time: '' }

  const start = new Date(option.starts_at_utc)
  const inZone = new Date(start.toLocaleString('en-US', { timeZone: props.event.timezone }))
  const pad = (n) => String(n).padStart(2, '0')

  return {
    date: option.day || `${inZone.getFullYear()}-${pad(inZone.getMonth() + 1)}-${pad(inZone.getDate())}`,
    time: option.all_day ? '' : `${pad(inZone.getHours())}:${pad(inZone.getMinutes())}`,
  }
}

const confidence = computed(() => props.event.ai_meta?.confidence || {})

/** Felder mit niedriger Konfidenz werden markiert (Spec Abschnitt 7). */
function guessed(field) {
  return confidence.value[field] === 'low'
}

async function save() {
  saving.value = true

  try {
    const { data } = await window.axios.patch(props.baseUrl, {
      title: form.value.title,
      description: form.value.description || null,
      location: form.value.location || null,
      participant_count_hint: form.value.participant_count_hint || null,
      planning_template: form.value.planning_template,
      ...(isList.value
        ? {
            fixed_date: form.value.fixed.date || null,
            fixed_time: form.value.fixed.date ? form.value.fixed.time || null : null,
          }
        : {}),
    })
    emit('updated', data.event)
    open.value = false
  } finally {
    saving.value = false
    emit('focus-change', false)
  }
}
</script>

<template>
  <div class="od-card p-4 sm:p-5">
    <div v-if="!open" class="flex flex-wrap items-center gap-x-3 gap-y-1">
      <h1 class="od-h1 flex-1">{{ event.title }}</h1>

      <button type="button" class="text-sm text-[var(--od-violet)] hover:underline" @click="open = true">
        {{ t('common.edit') }}
      </button>

      <!-- Fester Termin einer Liste, wie auf der Teilnehmer-Seite über der
           Beschreibung. -->
      <p v-if="isList && decidedOption" class="od-h3 w-full">{{ formatFull(decidedOption) }}</p>

      <div class="flex w-full flex-wrap items-center gap-x-2 gap-y-1 text-sm text-[var(--od-slate)]">
        <span v-if="event.participant_count_hint">
          {{ event.participant_count_hint }} · {{ t('manage.fields.participants_hint') }}
          <span v-if="guessed('participant_count')" class="text-xs italic">({{ t('manage.fields.guessed') }})</span>
        </span>
        <span v-if="event.location">
          📍
          <a
            :href="mapsLink(event.location)"
            target="_blank"
            rel="noopener noreferrer"
            class="text-inherit hover:underline"
          >{{ event.location }}</a>
        </span>
      </div>

      <p v-if="event.description" class="w-full text-sm text-[var(--od-slate)]">{{ event.description }}</p>
    </div>

    <form v-else class="space-y-3" @submit.prevent="save">
      <div>
        <label class="text-xs font-semibold text-[var(--od-slate)]" for="f-title">{{ t('manage.fields.title') }}</label>
        <input
          id="f-title"
          v-model="form.title"
          class="od-input font-display mt-1 font-semibold"
          maxlength="120"
          @focus="emit('focus-change', true)"
        />
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="text-xs font-semibold text-[var(--od-slate)]" for="f-location">{{ t('manage.fields.location') }}</label>
          <input id="f-location" v-model="form.location" class="od-input mt-1" maxlength="200" @focus="emit('focus-change', true)" />
        </div>
        <div>
          <label class="text-xs font-semibold text-[var(--od-slate)]" for="f-count">
            {{ t('manage.fields.participants_hint') }}
          </label>
          <input
            id="f-count"
            v-model.number="form.participant_count_hint"
            type="number"
            min="1"
            max="500"
            class="od-input mt-1"
            @focus="emit('focus-change', true)"
          />
        </div>
      </div>

      <div>
        <label class="text-xs font-semibold text-[var(--od-slate)]" for="f-desc">{{ t('manage.fields.description') }}</label>
        <textarea
          id="f-desc"
          v-model="form.description"
          rows="2"
          maxlength="2000"
          class="od-input mt-1"
          @focus="emit('focus-change', true)"
        />
      </div>

      <div v-if="isList">
        <label class="text-xs font-semibold text-[var(--od-slate)]" for="f-fixed-date">
          {{ t('manage.fields.fixed_date') }} <span class="font-normal">({{ t('common.optional') }})</span>
        </label>
        <div class="mt-1 flex flex-col gap-2 sm:flex-row">
          <input
            id="f-fixed-date"
            v-model="form.fixed.date"
            type="date"
            class="od-input"
            @focus="emit('focus-change', true)"
          />
          <input
            v-if="form.fixed.date"
            v-model="form.fixed.time"
            type="time"
            class="od-input sm:w-36"
            :aria-label="t('manage.dates.time')"
            @focus="emit('focus-change', true)"
          />
        </div>
      </div>

      <div>
        <label class="text-xs font-semibold text-[var(--od-slate)]" for="f-template">{{ t('manage.fields.template') }}</label>
        <select id="f-template" v-model="form.planning_template" class="od-input mt-1" @focus="emit('focus-change', true)">
          <option v-for="key in TEMPLATES" :key="key" :value="key">{{ t(`manage.templates.${key}`) }}</option>
        </select>
      </div>

      <div class="flex justify-end gap-2 pt-1">
        <button type="button" class="od-btn od-btn-ghost" @click="open = false; emit('focus-change', false)">
          {{ t('common.cancel') }}
        </button>
        <button type="submit" class="od-btn od-btn-ghost" :disabled="saving">{{ t('common.save') }}</button>
      </div>
    </form>
  </div>
</template>
