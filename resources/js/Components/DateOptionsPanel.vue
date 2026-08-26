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
const readOnly = computed(() => ['closed', 'cancelled'].includes(props.event.status))

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
  <section class="pl-card p-4 sm:p-5">
    <header class="flex items-center justify-between gap-3">
      <h2 class="font-display font-semibold">{{ t('manage.dates.title') }}</h2>
      <span v-if="event.answers_needed > 0 && event.participant_count > 0" class="text-xs text-[var(--color-pl-muted)]">
        {{ t('manage.dates.need_more', { count: event.answers_needed }) }}
      </span>
    </header>

    <!-- Bestaetigter Termin steht ueber allem anderen -->
    <div
      v-if="decided"
      class="mt-4 rounded-xl p-4"
      style="background: var(--color-pl-accent-soft); border: 1px solid var(--color-pl-accent)"
    >
      <p class="text-xs font-semibold tracking-wide uppercase" style="color: var(--color-pl-accent-dark)">
        {{ t('manage.dates.confirmed') }}
      </p>
      <p class="font-display mt-1 text-lg font-bold">{{ localizedFull(decided) }}</p>
      <div class="mt-3 flex flex-wrap gap-2">
        <a :href="`${baseUrl}/event.ics`" class="pl-btn pl-btn-ghost text-sm">{{ t('public.add_to_calendar') }}</a>
        <button v-if="!readOnly" type="button" class="pl-btn pl-btn-ghost text-sm" :disabled="busy" @click="undecide">
          {{ t('manage.dates.undecide') }}
        </button>
      </div>
    </div>

    <p v-if="!event.date_options.length" class="mt-4 text-sm text-[var(--color-pl-muted)]">
      {{ t('manage.dates.empty') }}
    </p>

    <ul v-else class="mt-4 space-y-2">
      <li
        v-for="option in ordered"
        :key="option.id"
        class="rounded-xl border p-3"
        :style="{
          borderColor:
            option.id === event.best_match_id && !decided ? 'var(--color-pl-accent)' : 'var(--color-pl-line)',
          opacity: option.blocked ? 0.75 : 1,
        }"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <p
              v-if="option.id === event.decided_option_id"
              class="text-xs font-semibold tracking-wide uppercase"
              style="color: var(--color-pl-accent-dark)"
            >
              ✓ {{ t('manage.dates.confirmed') }}
            </p>
            <p
              v-else-if="option.id === event.best_match_id && !decided"
              class="text-xs font-semibold tracking-wide uppercase"
              style="color: var(--color-pl-accent-dark)"
            >
              ★ {{ t('manage.dates.best') }}
            </p>
            <p class="font-display font-semibold">{{ localizedFull(option) }}</p>
            <p v-if="note(option)" class="text-xs text-[var(--color-pl-muted)]">
              {{ t('public.your_time', note(option)) }}
            </p>
            <p v-if="option.blocked" class="mt-0.5 text-xs" style="color: var(--color-pl-no)">
              ⚠ {{ t('manage.dates.blocked') }}
            </p>
          </div>

          <div class="flex shrink-0 gap-1">
            <button
              v-if="!readOnly && option.id !== event.decided_option_id"
              type="button"
              class="pl-btn pl-btn-accent px-3 py-1.5 text-xs"
              :disabled="busy"
              @click="decide(option)"
            >
              {{ t('manage.dates.confirm') }}
            </button>
            <button
              v-if="!readOnly"
              type="button"
              class="rounded-lg px-2 py-1.5 text-xs text-[var(--color-pl-muted)] hover:text-[var(--color-pl-no)]"
              :disabled="busy"
              :aria-label="t('common.delete')"
              @click="removeOption(option)"
            >
              ✕
            </button>
          </div>
        </div>

        <CountBar class="mt-2" :option="option" />
      </li>
    </ul>

    <p
      v-if="event.date_options.length && event.best_match_id === null && !decided"
      class="mt-3 text-xs text-[var(--color-pl-muted)]"
    >
      {{ t('manage.dates.not_enough') }}
    </p>

    <!-- Termine ergaenzen -->
    <div v-if="!readOnly" class="mt-5 border-t border-[var(--color-pl-line)] pt-4">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
        <div class="flex-1">
          <label class="text-xs font-semibold text-[var(--color-pl-muted)]" for="d-day">{{ t('manage.dates.day') }}</label>
          <input
            id="d-day"
            v-model="newDate.day"
            type="date"
            class="pl-input mt-1"
            @focus="emit('focus-change', true)"
            @blur="emit('focus-change', false)"
          />
        </div>
        <div v-if="!newDate.all_day" class="w-full sm:w-32">
          <label class="text-xs font-semibold text-[var(--color-pl-muted)]" for="d-time">{{ t('manage.dates.time') }}</label>
          <input
            id="d-time"
            v-model="newDate.time"
            type="time"
            class="pl-input mt-1"
            @focus="emit('focus-change', true)"
            @blur="emit('focus-change', false)"
          />
        </div>
        <button type="button" class="pl-btn pl-btn-primary" :disabled="busy || !newDate.day" @click="addOption">
          {{ t('manage.dates.add') }}
        </button>
      </div>

      <label class="mt-2 flex items-center gap-2 text-xs text-[var(--color-pl-muted)]">
        <input v-model="newDate.all_day" type="checkbox" />
        {{ t('manage.dates.all_day') }}
      </label>

      <button
        type="button"
        class="mt-3 text-sm text-[var(--color-pl-accent)] hover:underline"
        @click="showGenerator = !showGenerator"
      >
        {{ t('manage.dates.generate') }}
      </button>

      <div v-if="showGenerator" class="mt-3 rounded-xl border border-[var(--color-pl-line)] p-3">
        <div class="grid gap-3 sm:grid-cols-3">
          <div>
            <label class="text-xs font-semibold text-[var(--color-pl-muted)]" for="g-from">{{ t('manage.dates.from') }}</label>
            <input id="g-from" v-model="generator.from" type="date" class="pl-input mt-1" />
          </div>
          <div>
            <label class="text-xs font-semibold text-[var(--color-pl-muted)]" for="g-to">{{ t('manage.dates.to') }}</label>
            <input id="g-to" v-model="generator.to" type="date" class="pl-input mt-1" />
          </div>
          <div>
            <label class="text-xs font-semibold text-[var(--color-pl-muted)]" for="g-time">
              {{ t('manage.dates.time_of_day') }}
            </label>
            <select id="g-time" v-model="generator.time_of_day" class="pl-input mt-1">
              <option v-for="key in TIMES" :key="key" :value="key">{{ t(`manage.times.${key}`) }}</option>
            </select>
          </div>
        </div>

        <p class="mt-3 text-xs font-semibold text-[var(--color-pl-muted)]">{{ t('manage.dates.weekdays') }}</p>
        <div class="mt-1.5 flex flex-wrap gap-1.5">
          <button
            v-for="day in WEEKDAYS"
            :key="day"
            type="button"
            class="rounded-lg border px-2.5 py-1 text-xs"
            :style="
              generator.preferred_days.includes(day)
                ? { background: 'var(--color-pl-accent)', borderColor: 'var(--color-pl-accent)', color: '#fff' }
                : { borderColor: 'var(--color-pl-line)' }
            "
            @click="toggleWeekday(day)"
          >
            {{ weekdayLabel(day) }}
          </button>
        </div>

        <button type="button" class="pl-btn pl-btn-primary mt-3 text-sm" :disabled="busy" @click="generate">
          {{ t('manage.dates.generate') }}
        </button>
      </div>

      <label v-if="!decided" class="mt-4 flex items-center gap-2 text-xs text-[var(--color-pl-muted)]">
        <input v-model="notify" type="checkbox" />
        {{ t('manage.dates.notify') }}
      </label>
    </div>
  </section>
</template>
