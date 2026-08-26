<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import CountBar from '@/Components/CountBar.vue'
import { formatFull, isoDay, timezoneNote } from '@/composables/useDateFormat'

const props = defineProps({
  event: { type: Object, required: true },
  baseUrl: { type: String, required: true },
})

const emit = defineEmits(['updated', 'focus-change', 'error'])
const { t } = useI18n()

const busy = ref(false)
const notify = ref(true)
const showGenerator = ref(false)
/*
 * Sobald der Termin steht, ist die Auswahl erledigt und die Planung ist das,
 * was zählt. Die anderen Termine bleiben erreichbar — die Entscheidung lässt
 * sich zurücknehmen — aber sie stehen nicht mehr im Weg.
 */
const showOptions = ref(false)
const newDate = ref({ day: '', time: '18:00', all_day: false })

const generator = ref({
  from: isoDay(new Date()),
  to: isoDay(new Date(Date.now() + 21 * 86400000)),
  time_of_day: 'evening',
  preferred_days: [],
})

const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']
const TIMES = ['morning', 'midday', 'afternoon', 'evening']

/** Reihenfolge kommt vom Server (Spec Abschnitt 5), nie clientseitig neu sortieren. */
const ordered = computed(() => {
  const byId = new Map(props.event.date_options.map((option) => [option.id, option]))
  return props.event.ranking.map((id) => byId.get(id)).filter(Boolean)
})

const decided = computed(() => props.event.date_options.find((o) => o.id === props.event.decided_option_id) || null)

/** Alles außer dem bestätigten Termin — nur darauf bezieht sich der Umschalter. */
const otherCount = computed(
  () => props.event.date_options.filter((o) => o.id !== props.event.decided_option_id).length
)

const optionsVisible = computed(() => !decided.value || showOptions.value)
const readOnly = computed(() => ['closed', 'cancelled'].includes(props.event.status))

/* Nur ein starkes Signal pro Zeile: Mint fuer "passt allen", Apricot fuer den
   bestaetigten Termin, sonst die neutrale Linie. */
function isPrimaryChoice(option) {
  // Ohne Empfehlung (zu wenige Rückmeldungen) gibt es keinen hervorgehobenen
  // Vorschlag — dann sind alle Termine gleichwertig leise.
  return option.id === props.event.best_match_id && !decided.value
}

function borderFor(option) {
  if (option.id === props.event.decided_option_id) return 'var(--od-apricot)'
  if (option.id === props.event.best_match_id && !decided.value) return 'var(--od-mint)'
  return 'var(--od-line)'
}

function weekdayLabel(day) {
  const index = WEEKDAYS.indexOf(day)
  const reference = new Date(Date.UTC(2024, 0, 1 + index)) // 2024-01-01 war ein Montag
  return new Intl.DateTimeFormat(undefined, { weekday: 'short', timeZone: 'UTC' }).format(reference)
}

function localizedFull(option) {
  return formatFull(option)
}

function note(option) {
  return timezoneNote(option, props.event.timezone)
}

async function call(method, url, payload) {
  busy.value = true
  try {
    const { data } = await window.axios[method](url, payload)
    emit('updated', data.event)
    return data
  } catch (e) {
    emit('error')
  } finally {
    busy.value = false
  }
}

async function generate() {
  await call('post', `${props.baseUrl}/options/suggest`, generator.value)
  showGenerator.value = false
}

async function addOption() {
  if (!newDate.value.day) return
  await call('post', `${props.baseUrl}/options`, {
    day: newDate.value.day,
    time: newDate.value.all_day ? null : newDate.value.time,
    all_day: newDate.value.all_day,
  })
  newDate.value = { day: '', time: newDate.value.time, all_day: newDate.value.all_day }
}

async function removeOption(option) {
  await call('delete', `${props.baseUrl}/options/${option.id}`)
}

async function decide(option) {
  await call('post', `${props.baseUrl}/decide`, { date_option_id: option.id, notify: notify.value })
}

async function undecide() {
  await call('post', `${props.baseUrl}/undecide`)
}

function toggleWeekday(day) {
  const list = generator.value.preferred_days
  const index = list.indexOf(day)
  index === -1 ? list.push(day) : list.splice(index, 1)
}
</script>

<template>
  <section class="od-card p-4 sm:p-5">
    <header class="flex items-center justify-between gap-3">
      <h2 class="od-h3">{{ decided ? t('manage.dates.title_decided') : t('manage.dates.title') }}</h2>
      <span v-if="event.answered_count > 0 && !decided" class="od-meta">
        {{ t('manage.dates.need_more', event.answered_count) }}
      </span>
    </header>

    <!-- Bestaetigter Termin steht ueber allem anderen -->
    <div
      v-if="decided"
      class="od-settle mt-4 p-4"
      style="background: var(--od-sand); border: 1px solid var(--od-apricot); border-radius: var(--od-radius-lg)"
    >
      <p class="od-h3">{{ t('manage.dates.confirmed') }}</p>
      <p class="od-h2 mt-1">{{ localizedFull(decided) }}</p>
      <div class="mt-3 flex flex-wrap gap-2">
        <a :href="`${baseUrl}/event.ics`" class="od-btn od-btn-ghost text-sm">{{ t('public.add_to_calendar') }}</a>
        <button v-if="!readOnly" type="button" class="od-btn od-btn-ghost text-sm" :disabled="busy" @click="undecide">
          {{ t('manage.dates.undecide') }}
        </button>
      </div>
    </div>

    <button
      v-if="decided && otherCount"
      type="button"
      class="od-btn od-btn-quiet mt-4 px-0 text-[13px]"
      :aria-expanded="showOptions"
      @click="showOptions = !showOptions"
    >
      {{ t('manage.dates.other_options', otherCount) }} ·
      {{ showOptions ? t('manage.dates.hide') : t('manage.dates.show') }}
    </button>

    <p v-if="!event.date_options.length" class="mt-4 text-sm text-[var(--od-slate)]">
      {{ t('manage.dates.empty') }}
    </p>

    <ul v-else-if="optionsVisible" class="mt-4 space-y-2">
      <li
        v-for="option in ordered"
        :key="option.id"
        class="border p-3.5"
        :style="{
          borderColor: borderFor(option),
          borderRadius: 'var(--od-radius-md)',
          background: 'var(--od-white)',
          opacity: option.blocked ? 0.7 : 1,
        }"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <p
              v-if="option.id === event.best_match_id && !decided"
              class="flex items-center gap-1.5 text-[13px] font-medium"
              style="color: var(--od-mint)"
            >
              <span class="inline-block h-2 w-2 rounded-full" style="background: var(--od-mint)" />
              {{ t('manage.dates.best') }}
            </p>
            <p class="od-h3 flex items-center gap-2">
              <span
                v-if="option.id === event.decided_option_id"
                class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                style="background: var(--od-apricot)"
                :aria-label="t('manage.dates.confirmed')"
              />
              {{ localizedFull(option) }}
            </p>
            <p v-if="note(option)" class="od-meta">
              {{ t('public.your_time', note(option)) }}
            </p>
            <p v-if="option.blocked" class="od-meta mt-0.5">
              {{ t('manage.dates.blocked') }}
            </p>
          </div>

          <div class="flex shrink-0 gap-1">
            <!--
              Eine Primäraktion pro Screen (Brand Guide Abschnitt 7): nur die
              Zeile, die allen passt, bekommt den gefüllten Button. Alle anderen
              Termine bleiben als leise Aktion wählbar.
            -->
            <button
              v-if="!readOnly && option.id !== event.decided_option_id"
              type="button"
              class="od-btn px-3 py-1.5 text-[13px]"
              :class="isPrimaryChoice(option) ? 'od-btn-primary' : 'od-btn-quiet'"
              :disabled="busy"
              @click="decide(option)"
            >
              {{ t('manage.dates.confirm') }}
            </button>
            <button
              v-if="!readOnly"
              type="button"
              class="rounded-lg px-2 py-1.5 text-xs text-[var(--od-slate)] hover:text-[var(--od-slate)]"
              :disabled="busy"
              :aria-label="t('common.delete')"
              @click="removeOption(option)"
            >
              ✕
            </button>
          </div>
        </div>

        <CountBar class="mt-2" :option="option" :highlighted="option.id === event.best_match_id && !decided" />
      </li>
    </ul>

    <p
      v-if="event.date_options.length && event.best_match_id === null && !decided"
      class="od-meta mt-3"
    >
      {{ t('manage.dates.not_enough') }}
    </p>

    <!-- Termine ergaenzen — nach der Entscheidung nur noch auf Wunsch sichtbar -->
    <div v-if="!readOnly && optionsVisible" class="mt-5 border-t border-[var(--od-line)] pt-4">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
        <div class="flex-1">
          <label class="text-xs font-semibold text-[var(--od-slate)]" for="d-day">{{ t('manage.dates.day') }}</label>
          <input
            id="d-day"
            v-model="newDate.day"
            type="date"
            class="od-input mt-1"
            @focus="emit('focus-change', true)"
            @blur="emit('focus-change', false)"
          />
        </div>
        <div v-if="!newDate.all_day" class="w-full sm:w-32">
          <label class="text-xs font-semibold text-[var(--od-slate)]" for="d-time">{{ t('manage.dates.time') }}</label>
          <input
            id="d-time"
            v-model="newDate.time"
            type="time"
            class="od-input mt-1"
            @focus="emit('focus-change', true)"
            @blur="emit('focus-change', false)"
          />
        </div>
        <button type="button" class="od-btn od-btn-ghost" :disabled="busy || !newDate.day" @click="addOption">
          {{ t('manage.dates.add') }}
        </button>
      </div>

      <label class="mt-2 flex items-center gap-2 text-xs text-[var(--od-slate)]">
        <input v-model="newDate.all_day" type="checkbox" />
        {{ t('manage.dates.all_day') }}
      </label>

      <button
        type="button"
        class="mt-3 text-sm text-[var(--od-violet)] hover:underline"
        @click="showGenerator = !showGenerator"
      >
        {{ t('manage.dates.generate') }}
      </button>

      <div v-if="showGenerator" class="mt-3 rounded-xl border border-[var(--od-line)] p-3">
        <div class="grid gap-3 sm:grid-cols-3">
          <div>
            <label class="text-xs font-semibold text-[var(--od-slate)]" for="g-from">{{ t('manage.dates.from') }}</label>
            <input id="g-from" v-model="generator.from" type="date" class="od-input mt-1" />
          </div>
          <div>
            <label class="text-xs font-semibold text-[var(--od-slate)]" for="g-to">{{ t('manage.dates.to') }}</label>
            <input id="g-to" v-model="generator.to" type="date" class="od-input mt-1" />
          </div>
          <div>
            <label class="text-xs font-semibold text-[var(--od-slate)]" for="g-time">
              {{ t('manage.dates.time_of_day') }}
            </label>
            <select id="g-time" v-model="generator.time_of_day" class="od-input mt-1">
              <option v-for="key in TIMES" :key="key" :value="key">{{ t(`manage.times.${key}`) }}</option>
            </select>
          </div>
        </div>

        <p class="mt-3 text-xs font-semibold text-[var(--od-slate)]">{{ t('manage.dates.weekdays') }}</p>
        <div class="mt-1.5 flex flex-wrap gap-1.5">
          <button
            v-for="day in WEEKDAYS"
            :key="day"
            type="button"
            class="rounded-lg border px-2.5 py-1 text-xs"
            :style="
              generator.preferred_days.includes(day)
                ? { background: 'var(--od-violet)', borderColor: 'var(--od-violet)', color: '#fff' }
                : { borderColor: 'var(--od-line)' }
            "
            @click="toggleWeekday(day)"
          >
            {{ weekdayLabel(day) }}
          </button>
        </div>

        <button type="button" class="od-btn od-btn-ghost mt-3 text-sm" :disabled="busy" @click="generate">
          {{ t('manage.dates.generate') }}
        </button>
      </div>

      <label v-if="!decided" class="mt-4 flex items-center gap-2 text-xs text-[var(--od-slate)]">
        <input v-model="notify" type="checkbox" />
        {{ t('manage.dates.notify') }}
      </label>
    </div>
  </section>
</template>
