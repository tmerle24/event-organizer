<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import CountBar from '@/Components/CountBar.vue'
import IconTip from '@/Components/IconTip.vue'
import WhoList from '@/Components/WhoList.vue'
import { formatCompact, formatFull, isoDay, timezoneNote } from '@/composables/useDateFormat'
import { fitsEveryone } from '@/composables/useMatch'

const props = defineProps({
  event: { type: Object, required: true },
  baseUrl: { type: String, required: true },
})

const emit = defineEmits(['updated', 'focus-change', 'error'])
const { t } = useI18n()

const busy = ref(false)
const notify = ref(true)
const showGenerator = ref(false)
const expandAll = ref(false)
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

/** Markierung nur bei eindeutiger Empfehlung — bei Gleichstand liefert der
 *  Server keinen best_match_id. */
function isBest(option) {
  return option.id === props.event.best_match_id && !decided.value
}

/** Mint nur, wenn der empfohlene Termin wirklich allen passt */
function isMint(option) {
  return isBest(option) && fitsEveryone(option)
}

/** Markiert = bestaetigt oder bester Termin; nur die bekommen einen Rahmen */
function marked(option) {
  return option.id === props.event.decided_option_id || isBest(option)
}

function borderFor(option) {
  if (option.id === props.event.decided_option_id) return 'var(--od-apricot)'
  if (isMint(option)) return 'var(--od-mint)'
  if (isBest(option)) return 'var(--od-violet-soft)'
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

// ohne Antworten geht nichts verloren, dann ohne Nachfrage
const removingOption = ref(null)

function askRemoveOption(option) {
  if (Object.keys(option.votes ?? {}).length) removingOption.value = option
  else removeOption(option)
}

async function removeOption(option) {
  removingOption.value = null
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
    <header class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <h2 class="od-h3">{{ decided ? t('manage.dates.title_decided') : t('manage.dates.title') }}</h2>
        <p v-if="event.answered_count > 0 && !decided" class="od-meta">
          {{ t('manage.dates.need_more', event.answered_count) }}
        </p>
      </div>
      <button
        v-if="event.answered_count > 0 && optionsVisible"
        type="button"
        class="od-meta mt-1 shrink-0 whitespace-nowrap hover:text-[var(--od-violet)]!"
        :aria-pressed="expandAll"
        @click="expandAll = !expandAll"
      >
        {{ expandAll ? t('manage.dates.names_hide') : t('manage.dates.names_show') }}
      </button>
    </header>

    <!-- Bestaetigter Termin steht ueber allem anderen -->
    <div
      v-if="decided"
      class="od-settle mt-4 p-4"
      style="background: var(--od-sand); border: 1px solid var(--od-apricot); border-radius: var(--od-radius-lg)"
    >
      <p class="od-h3">{{ t('manage.dates.confirmed') }}</p>
      <p class="od-h2 mt-1">{{ localizedFull(decided) }}</p>
      <WhoList class="mt-3" :option="decided" :participants="event.participants" show-open />
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
      class="od-btn od-btn-quiet mt-4 px-0 text-[13px] hover:bg-transparent"
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
      <!-- Wie auf der Teilnehmerseite: Rahmen nur um den markierten Termin -->
      <li
        v-for="(option, index) in ordered"
        :key="option.id"
        class="border border-transparent px-3.5 py-3.5"
        :class="marked(option) || marked(ordered[index + 1] ?? {}) ? '' : 'border-b-[var(--od-line)]! last:border-b-transparent!'"
        :style="{
          ...(marked(option)
            ? { borderColor: borderFor(option), borderRadius: 'var(--od-radius-md)', background: 'var(--od-white)' }
            : {}),
          opacity: option.blocked ? 0.7 : 1,
        }"
      >
        <!-- Aktionen rechts oben, Datum darunter in voller Breite (sonst am Handy gequetscht) -->
        <div class="-mt-1 flex min-h-8 items-center justify-between gap-3">
          <p
            v-if="isBest(option)"
            class="flex min-w-0 items-center gap-1.5 whitespace-nowrap text-[13px] font-medium"
            :style="{ color: isMint(option) ? 'var(--od-mint)' : 'var(--od-violet)' }"
          >
            <span
              class="inline-block h-2 w-2 shrink-0 rounded-full"
              :style="{ background: isMint(option) ? 'var(--od-mint)' : 'var(--od-violet)' }"
            />
            {{ isMint(option) ? t('manage.dates.best') : t('manage.dates.best_partial') }}
          </p>
          <span v-else />

          <div class="-mr-1.5 flex shrink-0 gap-1">
            <!--
              Eine Primäraktion pro Screen (Brand Guide Abschnitt 7): nur der
              beste Termin bekommt den gefüllten Button, alle anderen bleiben
              als leise Aktion wählbar.
            -->
            <button
              v-if="!readOnly && option.id !== event.decided_option_id"
              type="button"
              class="od-btn whitespace-nowrap px-3 py-1.5 text-[13px]"
              :class="isPrimaryChoice(option) ? 'od-btn-primary' : 'od-btn-quiet'"
              :disabled="busy"
              @click="decide(option)"
            >
              <!-- kurze Beschriftung am Handy, sonst bricht das Label daneben um -->
              <span class="sm:hidden">{{ t('manage.dates.confirm_short') }}</span>
              <span class="hidden sm:inline">{{ t('manage.dates.confirm') }}</span>
            </button>
            <IconTip v-if="!readOnly" :text="t('manage.tips.remove_date')" align="end">
              <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs text-[var(--od-slate)] hover:text-[var(--od-ink)]"
                :disabled="busy"
                :aria-label="t('manage.tips.remove_date')"
                @click="askRemoveOption(option)"
              >
                ✕
              </button>
            </IconTip>
          </div>
        </div>

        <p class="od-h3 mt-1.5 flex items-center gap-2">
          <!-- Handy: kurzes Format -->
          <span
            v-if="option.id === event.decided_option_id"
            class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
            style="background: var(--od-apricot)"
            :aria-label="t('manage.dates.confirmed')"
          />
          <span class="sm:hidden">{{ formatCompact(option) }}</span>
          <span class="hidden sm:inline">{{ localizedFull(option) }}</span>
        </p>
        <p v-if="note(option)" class="od-meta">
          {{ t('public.your_time', note(option)) }}
        </p>
        <p v-if="option.blocked" class="od-meta mt-0.5">
          {{ t('manage.dates.blocked') }}
        </p>

        <CountBar
          class="mt-2"
          :option="option"
          :highlighted="isMint(option)"
          :participants="event.participants"
          show-open
          :expand-all="expandAll"
        />
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

    <ConfirmModal
      :open="!!removingOption"
      :message="t('manage.dates.remove_confirm')"
      danger
      @confirm="removeOption(removingOption)"
      @cancel="removingOption = null"
    />
  </section>
</template>
