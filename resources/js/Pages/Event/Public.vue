<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Logo from '@/Components/Logo.vue'
import Footer from '@/Components/Footer.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'
import AvailabilityButtons from '@/Components/AvailabilityButtons.vue'
import CountBar from '@/Components/CountBar.vue'
import WhoList from '@/Components/WhoList.vue'
import PlanPanel from '@/Components/PlanPanel.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import NameModal from '@/Components/NameModal.vue'
import Toast from '@/Components/Toast.vue'
import { useParticipantToken } from '@/composables/useDeviceToken'
import { formatCompact, formatFull, timezoneNote } from '@/composables/useDateFormat'
import { fitsEveryone } from '@/composables/useMatch'
import { mapsLink } from '@/composables/useMapsLink'
import { rememberName, rememberedName } from '@/composables/useRememberedName'

const props = defineProps({
  event: { type: Object, required: true },
})

const { t } = useI18n()

const event = ref(props.event)
const token = useParticipantToken(props.event.public_token)
const me = ref(props.event.me)

const answers = ref({})
const busy = ref(false)
/*
 * Der Geraete-Token liegt im LocalStorage und kann beim Server-Rendering
 * nicht mitgeschickt werden. Bis der erste /state-Aufruf ihn aufgeloest hat,
 * bleibt das Eintragen-Formular verborgen — sonst sieht ein wiederkehrender
 * Teilnehmer kurz das Formular und legt sich womoeglich doppelt an.
 */
const resolving = ref(!props.event.me)
const editing = ref(false)
const toast = ref('')
const toastTone = ref('ok')
const confirmLeave = ref(false)
const saved = ref(false)
// ein "Wer?" klappt alle Termine auf
const showNames = ref(false)
const askName = ref(false)
let saveTimer = null
// was beim ersten Klick gemeint war — wird nach dem Eintragen nachgeholt
let pending = null
/*
 * Steht der Termin, ist die Abstimmung erledigt: die Liste klappt zu, damit
 * das, was noch zu tun ist, nicht unter sechs abgehakten Terminen liegt.
 */
const showDateList = ref(false)

const baseUrl = computed(() => `/t/${event.value.public_token}`)
const readOnly = computed(() => ['closed', 'cancelled'].includes(event.value.status))
const showDates = computed(() => ['dates', 'both'].includes(event.value.mode))
const showPlan = computed(
  () => event.value.mode === 'list' || ['decided', 'planning', 'closed'].includes(event.value.status)
)

const decided = computed(
  () => event.value.date_options.find((option) => option.id === event.value.decided_option_id) || null
)

/*
 * Reihenfolge kommt vom Server (Spec Abschnitt 5), wird aber eingefroren,
 * solange die Seite offen ist: sonst rutscht der gerade angetippte Termin
 * nach oben und die Liste springt unter dem Finger weg. Neu aufgebaut wird
 * sie nur, wenn Termine dazukommen oder wegfallen.
 */
const orderIds = ref([...props.event.ranking])

watch(
  () => event.value.date_options.map((option) => option.id).join(','),
  () => (orderIds.value = [...event.value.ranking]),
  { immediate: true }
)

const ordered = computed(() => {
  const byId = new Map(event.value.date_options.map((option) => [option.id, option]))
  const known = orderIds.value.map((id) => byId.get(id)).filter(Boolean)
  const rest = event.value.date_options.filter((option) => !orderIds.value.includes(option.id))

  return [...known, ...rest]
})

/** Nur Optionen, bei denen sich die eigene Antwort geaendert hat, werden gesendet. */
const dirty = computed(() => Object.keys(answers.value).length > 0)

const otherCount = computed(
  () => event.value.date_options.filter((o) => o.id !== event.value.decided_option_id).length
)

const dateListVisible = computed(() => !decided.value || showDateList.value)

let poller = null

onMounted(async () => {
  window.axios.defaults.headers.common['X-Participant-Token'] = token
  syncFromServer()
  await refresh(true)
  resolving.value = false
  poller = setInterval(refresh, 6000)
})

onBeforeUnmount(() => {
  clearInterval(poller)
  clearTimeout(saveTimer)
})

watch(() => event.value.decided_option_id, syncFromServer)

function myVotes() {
  if (!me.value) return {}
  return Object.fromEntries(
    event.value.date_options
      .map((option) => [option.id, option.votes?.[me.value.id] ?? null])
      .filter(([, value]) => value !== null)
  )
}

function syncFromServer() {
  answers.value = {}
}

function currentValue(optionId) {
  if (optionId in answers.value) return answers.value[optionId]
  return myVotes()[optionId] ?? null
}

function setValue(optionId, value) {
  if (!me.value) {
    // Erst der Name, dann zaehlt der Klick — der Klick wird danach nachgeholt.
    pending = () => setValue(optionId, value)
    askName.value = true

    return
  }

  answers.value = { ...answers.value, [optionId]: value }

  // Kein Speichern-Button: jeder Tipp geht raus, mehrere kurz hintereinander
  // als eine Anfrage — so kann nichts verloren gehen.
  clearTimeout(saveTimer)
  saveTimer = setTimeout(saveAnswers, 600)
}

/** Aufgabe oder Antwort ohne Namen: erst fragen, dann nachholen */
function requestName(action) {
  pending = action
  askName.value = true
}

function cancelName() {
  askName.value = false
  pending = null
}

async function joinFromModal(payload) {
  await join(payload)

  if (me.value) {
    askName.value = false
    const action = pending
    pending = null
    // erst rendern lassen, sonst sieht PlanPanel den Teilnehmer noch nicht
    await nextTick()
    action?.()
  }
}

/**
 * force = true fuer die erste Aufloesung beim Mount: sie muss auch dann
 * laufen, wenn der Tab gerade im Hintergrund liegt — sonst sieht ein
 * wiederkehrender Teilnehmer das Eintragen-Formular.
 */
async function refresh(force = false) {
  if (!force && (busy.value || editing.value || dirty.value || document.hidden)) return

  try {
    const { data } = await window.axios.get(`${baseUrl.value}/state`)
    event.value = data.event
    me.value = data.event.me
  } catch (e) {
    // Hintergrund-Refresh scheitert still.
  }
}

function flash(message, tone = 'ok') {
  toastTone.value = tone
  toast.value = message
  setTimeout(() => (toast.value = ''), 2600)
}

async function join({ display_name, email, website }) {
  if (!display_name) return
  busy.value = true

  try {
    const { data } = await window.axios.post(`${baseUrl.value}/join`, {
      display_name,
      email,
      token,
      website,
    })
    event.value = data.event
    me.value = data.event.me
    rememberName(display_name)
  } catch (e) {
    flash(t('common.error'), 'error')
  } finally {
    busy.value = false
  }
}

/*
 * Nur die Werte verwerfen, die noch so dastehen wie abgeschickt. Wer waehrend
 * des Speicherns denselben Termin nochmal antippt, verliert den Klick sonst,
 * sobald die alte Antwort zurueckkommt.
 */
function dropSent(sending) {
  answers.value = Object.fromEntries(
    Object.entries(answers.value).filter(([optionId, value]) => !(optionId in sending) || value !== sending[optionId])
  )
}

async function saveAnswers() {
  if (!me.value || !dirty.value) return
  clearTimeout(saveTimer)

  const sending = answers.value
  busy.value = true

  try {
    const { data } = await window.axios.post(`${baseUrl.value}/availability`, {
      token,
      answers: sending,
    })
    event.value = data.event
    me.value = data.event.me
    dropSent(sending)
    saved.value = true
    setTimeout(() => (saved.value = false), 2000)
  } catch (e) {
    // Antwort faellt auf den Serverstand zurueck
    dropSent(sending)
    flash(t('common.error'), 'error')
  } finally {
    busy.value = false
  }
}

async function leave() {
  confirmLeave.value = false
  busy.value = true

  try {
    const { data } = await window.axios.post(`${baseUrl.value}/leave`, { token })
    event.value = data.event
    me.value = null
  } catch (e) {
    flash(t('common.error'), 'error')
  } finally {
    busy.value = false
  }
}

/** Steht > Passt allen > Passt am besten — gleiche Logik wie auf der Verwaltungsseite */
function markerFor(option) {
  if (option.id === event.value.decided_option_id) {
    return { label: t('manage.dates.confirmed'), dot: 'var(--od-apricot)', text: 'var(--od-ink)' }
  }
  // Markierung nur, wenn ein Termin eindeutig vorne liegt — bei Gleichstand
  // liefert der Server keinen best_match_id.
  if (!decided.value && option.id === event.value.best_match_id) {
    return fitsEveryone(option)
      ? { label: t('manage.dates.best'), dot: 'var(--od-mint)', text: 'var(--od-mint)' }
      : { label: t('manage.dates.best_partial'), dot: 'var(--od-violet)', text: 'var(--od-violet)' }
  }
  return null
}

function note(option) {
  return timezoneNote(option, event.value.timezone)
}
</script>

<template>
  <Head :title="event.title" />

  <div class="min-h-screen">
    <header class="mx-auto flex max-w-2xl items-center justify-between px-6 py-5">
      <a href="/" class="flex"><Logo /></a>
      <LanguageSwitcher />
    </header>

    <main class="mx-auto max-w-2xl space-y-4 px-6 pb-10">
      <div class="od-card p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
          <h1 class="od-h1 min-w-0 flex-1">{{ event.title }}</h1>

          <!-- Nur das Symbol, kein Knopf. Erscheint bei bestätigtem Termin. -->
          <a
            v-if="decided && !readOnly"
            :href="`${baseUrl}/event.ics`"
            class="shrink-0 p-1 transition"
            style="color: var(--od-slate)"
            :title="t('public.add_to_calendar')"
            :aria-label="t('public.add_to_calendar')"
            @mouseenter="$event.currentTarget.style.color = 'var(--od-violet)'"
            @mouseleave="$event.currentTarget.style.color = 'var(--od-slate)'"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <rect x="3" y="5" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.8" />
              <path d="M8 3v4M16 3v4M3 10h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
              <path d="M12 14v4M10 16h4" stroke="var(--od-violet)" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </a>
        </div>

        <p v-if="!showDates && decided" class="od-h3 mt-1">{{ formatFull(decided) }}</p>

        <p v-if="event.description" class="mt-1 text-sm text-[var(--od-slate)]">{{ event.description }}</p>
        <p v-if="event.location" class="mt-1 text-sm text-[var(--od-slate)]">
          📍
          <!-- Klickbar, sieht aber weiter aus wie Text: Unterstreichung erst
               beim Überfahren, Farbe bleibt Slate. -->
          <a
            :href="mapsLink(event.location)"
            target="_blank"
            rel="noopener noreferrer"
            class="text-inherit hover:underline"
          >{{ event.location }}</a>
        </p>

        <p
          v-if="event.status === 'cancelled'"
          class="od-small mt-3 px-3 py-2"
          style="background: var(--od-mist); color: var(--od-slate); border-radius: var(--od-radius-sm)"
        >
          {{ t('public.cancelled_banner') }}
        </p>
        <p v-else-if="event.status === 'closed'" class="mt-3 text-sm text-[var(--od-slate)]">
          {{ t('public.closed_banner') }}
        </p>

        <div
          v-else-if="decided && showDates"
          class="od-settle mt-4 p-4"
          style="background: var(--od-sand); border: 1px solid var(--od-apricot); border-radius: var(--od-radius-lg)"
        >
          <p class="od-small" style="color: var(--od-slate)">{{ t('public.decided_banner') }}</p>
          <p class="od-h2 mt-0.5">{{ formatFull(decided) }}</p>
          <p v-if="note(decided)" class="od-meta">
            {{ t('public.your_time', note(decided)) }}
          </p>
          <WhoList v-if="me" class="mt-3" :option="decided" :participants="event.participants" :show-declined="false" />
          <a :href="`${baseUrl}/event.ics`" class="od-btn od-btn-ghost od-small mt-3">{{ t('public.add_to_calendar') }}</a>
        </div>
      </div>

      <!-- Eingetragen: Name und Austragen -->
      <div v-if="me" class="flex items-center justify-between px-1 text-sm">
        <span>{{ t('public.hello', { name: me.display_name }) }}</span>
        <button type="button" class="text-xs text-[var(--od-slate)] hover:text-[var(--od-slate)]" @click="confirmLeave = true">
          {{ t('public.leave') }}
        </button>
      </div>

      <!-- Verfuegbarkeit -->
      <section v-if="showDates" class="od-card p-4 sm:p-5">
        <header class="flex items-baseline justify-between gap-3">
          <div class="min-w-0">
            <!-- Vor dem Eintragen die Aufforderung, danach die Beschreibung -->
            <h2 class="font-display font-semibold">
              {{ !me && !readOnly && !resolving ? t('public.who_ask') : t('public.who') }}
            </h2>
          </div>
          <!-- "Gespeichert" sitzt in der Kopfzeile: kein reservierter Platz, kein Sprung -->
          <span v-if="saved" class="od-meta od-settle shrink-0" style="color: var(--od-violet)">{{ t('public.saved') }}</span>
        </header>

        <button
          v-if="decided && otherCount"
          type="button"
          class="od-btn od-btn-quiet mt-2 px-0 text-[13px] hover:bg-transparent"
          :aria-expanded="showDateList"
          @click="showDateList = !showDateList"
        >
          {{ t('manage.dates.other_options', otherCount) }} ·
          {{ showDateList ? t('manage.dates.hide') : t('manage.dates.show') }}
        </button>

        <p v-if="!event.date_options.length" class="mt-3 text-sm text-[var(--od-slate)]">
          {{ t('public.no_dates') }}
        </p>

        <!-- Termine nur durch Linien getrennt; einen Rahmen bekommt nur der
             markierte Termin, damit er heraussticht. -->
        <ul v-else-if="dateListVisible" class="mt-2">
          <li
            v-for="(option, index) in ordered"
            :key="option.id"
            class="border border-transparent px-3.5 py-3.5"
            :class="markerFor(option) || markerFor(ordered[index + 1] ?? {}) ? '' : 'border-b-[var(--od-line)]! last:border-b-transparent!'"
            :style="
              markerFor(option)
                ? {
                    borderColor: option.id === event.decided_option_id ? 'var(--od-apricot)' : markerFor(option).dot,
                    borderRadius: 'var(--od-radius-md)',
                    background: option.id === event.decided_option_id ? 'var(--od-sand)' : 'var(--od-white)',
                  }
                : {}
            "
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="min-w-0">
                <!-- Eigene Zeile ueber dem Datum, damit die Daten buendig bleiben.
                     Der Platz bleibt immer frei, sonst springt die Liste, wenn die
                     Markierung zu einem anderen Termin wandert. -->
                <p
                  class="flex min-h-[1.25rem] items-center gap-1.5 text-[13px] font-medium"
                  :style="{ color: markerFor(option)?.text }"
                >
                  <template v-if="markerFor(option)">
                    <span class="inline-block h-2 w-2 shrink-0 rounded-full" :style="{ background: markerFor(option).dot }" />
                    {{ markerFor(option).label }}
                  </template>
                </p>
                <!-- Handy: kurzes Format, damit der Termin in eine Zeile passt -->
                <p class="od-h3" :class="{ 'opacity-60': option.blocked }">
                  <span class="sm:hidden">{{ formatCompact(option) }}</span>
                  <span class="hidden sm:inline">{{ formatFull(option) }}</span>
                </p>
                <p v-if="note(option)" class="od-meta">
                  {{ t('public.your_time', note(option)) }}
                </p>
              </div>

              <!-- Desktop: rechts neben dem Datum -->
              <div v-if="(me || !resolving) && !readOnly" class="ml-auto hidden sm:block">
                <AvailabilityButtons :value="currentValue(option.id)" @update:value="setValue(option.id, $event)" />
              </div>
            </div>

            <CountBar
              class="mt-2"
              :option="option"
              :highlighted="option.id === event.best_match_id && !decided && fitsEveryone(option)"
              :participants="me ? event.participants : null"
              :show-declined="false"
              :open="showNames"
              @toggle="showNames = !showNames"
            />

            <!-- Handy: immer ganz unten rechts, egal ob es schon einen Balken gibt -->
            <div v-if="(me || !resolving) && !readOnly" class="mt-3 flex justify-end sm:hidden">
              <AvailabilityButtons :value="currentValue(option.id)" @update:value="setValue(option.id, $event)" />
            </div>
          </li>
        </ul>

      </section>

      <!-- Planung: erscheint erst, wenn sie relevant ist -->
      <template v-if="showPlan">
        <p class="px-1 text-sm text-[var(--od-slate)]">{{ t('public.tasks_intro') }}</p>
        <PlanPanel
          :event="event"
          :base-url="baseUrl"
          :participant-token="token"
          :me="me"
          @need-name="requestName"
          @updated="event = $event"
          @focus-change="editing = $event"
          @error="flash(t('common.error'), 'error')"
        />
      </template>
    </main>

    <Footer powered-by />
    <Toast :message="toast" :tone="toastTone" />
    <NameModal :open="askName" :busy="busy" @confirm="joinFromModal" @cancel="cancelName" />
    <ConfirmModal
      :open="confirmLeave"
      :message="t('public.leave_confirm')"
      danger
      @confirm="leave"
      @cancel="confirmLeave = false"
    />
  </div>
</template>
