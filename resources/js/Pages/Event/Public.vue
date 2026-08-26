<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Logo from '@/Components/Logo.vue'
import Footer from '@/Components/Footer.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'
import AvailabilityButtons from '@/Components/AvailabilityButtons.vue'
import CountBar from '@/Components/CountBar.vue'
import PlanPanel from '@/Components/PlanPanel.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import Toast from '@/Components/Toast.vue'
import { useParticipantToken } from '@/composables/useDeviceToken'
import { formatFull, timezoneNote } from '@/composables/useDateFormat'
import { mapsLink } from '@/composables/useMapsLink'

const props = defineProps({
  event: { type: Object, required: true },
})

const { t } = useI18n()

const event = ref(props.event)
const token = useParticipantToken(props.event.public_token)
const me = ref(props.event.me)

const form = ref({ display_name: '', email: '', website: '' })
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

const ordered = computed(() => {
  const byId = new Map(event.value.date_options.map((option) => [option.id, option]))
  return event.value.ranking.map((id) => byId.get(id)).filter(Boolean)
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

onBeforeUnmount(() => clearInterval(poller))

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
  answers.value = { ...answers.value, [optionId]: value }
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

async function join() {
  if (!form.value.display_name.trim()) return
  busy.value = true

  try {
    const { data } = await window.axios.post(`${baseUrl.value}/join`, {
      display_name: form.value.display_name.trim(),
      email: form.value.email.trim() || null,
      token,
      website: form.value.website,
    })
    event.value = data.event
    me.value = data.event.me
  } catch (e) {
    flash(t('common.error'), 'error')
  } finally {
    busy.value = false
  }
}

async function saveAnswers() {
  if (!me.value || !dirty.value) return
  busy.value = true

  try {
    const { data } = await window.axios.post(`${baseUrl.value}/availability`, {
      token,
      answers: answers.value,
    })
    event.value = data.event
    me.value = data.event.me
    answers.value = {}
    flash(t('public.answers_saved'))
  } catch (e) {
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
    form.value = { display_name: '', email: '', website: '' }
  } catch (e) {
    flash(t('common.error'), 'error')
  } finally {
    busy.value = false
  }
}

function note(option) {
  return timezoneNote(option, event.value.timezone)
}
</script>

<template>
  <Head :title="event.title" />

  <div class="min-h-screen">
    <header class="mx-auto flex max-w-2xl items-center justify-between px-6 py-5">
      <a href="/"><Logo /></a>
      <LanguageSwitcher />
    </header>

    <main class="mx-auto max-w-2xl space-y-4 px-6 pb-10">
      <div class="od-card p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
          <h1 class="od-h1 min-w-0 flex-1">{{ event.title }}</h1>

          <!--
            Kalender-Symbol statt Textknopf: die Aktion gehört zum Termin oben,
            nicht ans Ende der Seite. Erscheint nur, wenn es einen bestätigten
            Termin gibt — ohne den lässt sich kein Kalendereintrag erzeugen.
          -->
          <a
            v-if="decided && !readOnly"
            :href="`${baseUrl}/event.ics`"
            class="od-btn od-btn-ghost shrink-0 px-2.5 py-2"
            :title="t('public.add_to_calendar')"
            :aria-label="t('public.add_to_calendar')"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <rect x="3" y="5" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.8" />
              <path d="M8 3v4M16 3v4M3 10h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
              <path d="M12 14v4M10 16h4" stroke="var(--od-violet)" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </a>
        </div>

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
          v-else-if="decided"
          class="od-settle mt-4 p-4"
          style="background: var(--od-sand); border: 1px solid var(--od-apricot); border-radius: var(--od-radius-lg)"
        >
          <p class="od-small" style="color: var(--od-slate)">{{ t('public.decided_banner') }}</p>
          <p class="od-h2 mt-0.5">{{ formatFull(decided) }}</p>
          <p v-if="note(decided)" class="od-meta">
            {{ t('public.your_time', note(decided)) }}
          </p>
        </div>
      </div>

      <!-- Eintragen: erste Antwort erzeugt den Teilnehmer -->
      <form v-if="!me && !readOnly && !resolving" class="od-card p-4 sm:p-5" @submit.prevent="join">
        <p class="text-sm">{{ showDates ? t('public.intro') : t('public.intro_list') }}</p>

        <label class="mt-3 block text-xs font-semibold text-[var(--od-slate)]" for="p-name">
          {{ t('public.name') }}
        </label>
        <input
          id="p-name"
          v-model="form.display_name"
          class="od-input mt-1"
          maxlength="80"
          required
          :placeholder="t('public.name_placeholder')"
        />

        <label class="mt-3 block text-xs font-semibold text-[var(--od-slate)]" for="p-email">
          {{ t('public.email') }} <span class="font-normal">({{ t('common.optional') }})</span>
        </label>
        <input id="p-email" v-model="form.email" type="email" class="od-input mt-1" maxlength="180" />
        <p class="mt-1 text-xs text-[var(--od-slate)]">{{ t('public.email_hint') }}</p>

        <input v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true" />

        <button type="submit" class="od-btn od-btn-primary mt-4 w-full py-2.5" :disabled="busy || !form.display_name.trim()">
          {{ t('public.join') }}
        </button>
      </form>

      <div v-else-if="me" class="flex items-center justify-between px-1 text-sm">
        <span>{{ t('public.hello', { name: me.display_name }) }}</span>
        <button type="button" class="text-xs text-[var(--od-slate)] hover:text-[var(--od-slate)]" @click="confirmLeave = true">
          {{ t('public.leave') }}
        </button>
      </div>

      <!-- Verfuegbarkeit -->
      <section v-if="showDates" class="od-card p-4 sm:p-5">
        <h2 class="font-display font-semibold">{{ t('public.who') }}</h2>

        <button
          v-if="decided && otherCount"
          type="button"
          class="od-btn od-btn-quiet mt-2 px-0 text-[13px]"
          :aria-expanded="showDateList"
          @click="showDateList = !showDateList"
        >
          {{ t('manage.dates.other_options', otherCount) }} ·
          {{ showDateList ? t('manage.dates.hide') : t('manage.dates.show') }}
        </button>

        <p v-if="!event.date_options.length" class="mt-3 text-sm text-[var(--od-slate)]">
          {{ t('public.no_dates') }}
        </p>

        <ul v-else-if="dateListVisible" class="mt-3 space-y-2">
          <li
            v-for="option in ordered"
            :key="option.id"
            class="border p-3.5"
            :style="{
              borderColor: option.id === event.decided_option_id ? 'var(--od-apricot)' : 'var(--od-line)',
              borderRadius: 'var(--od-radius-md)',
              background: option.id === event.decided_option_id ? 'var(--od-sand)' : 'var(--od-white)',
            }"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="min-w-0">
                <p class="od-h3 flex items-center gap-2" :class="{ 'opacity-60': option.blocked }">
                  <span
                    v-if="option.id === event.decided_option_id || option.id === event.best_match_id"
                    class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                    :style="{
                      background:
                        option.id === event.decided_option_id ? 'var(--od-apricot)' : 'var(--od-mint)',
                    }"
                  />
                  {{ formatFull(option) }}
                </p>
                <p v-if="note(option)" class="od-meta">
                  {{ t('public.your_time', note(option)) }}
                </p>
              </div>

              <AvailabilityButtons
                v-if="me && !readOnly"
                :value="currentValue(option.id)"
                @update:value="setValue(option.id, $event)"
              />
            </div>

            <CountBar class="mt-2" :option="option" :highlighted="option.id === event.best_match_id && !decided" />
          </li>
        </ul>

        <button
          v-if="me && !readOnly && event.date_options.length && dateListVisible"
          type="button"
          class="od-btn od-btn-primary mt-4 w-full py-2.5"
          :disabled="busy || !dirty"
          @click="saveAnswers"
        >
          {{ t('public.save_answers') }}
        </button>
      </section>

      <!-- Planung: erscheint erst, wenn sie relevant ist -->
      <template v-if="showPlan">
        <p class="px-1 text-sm text-[var(--od-slate)]">{{ t('public.tasks_intro') }}</p>
        <PlanPanel
          :event="event"
          :base-url="baseUrl"
          :participant-token="token"
          :me="me"
          @updated="event = $event"
          @focus-change="editing = $event"
          @error="flash(t('common.error'), 'error')"
        />
      </template>
    </main>

    <Footer powered-by />
    <Toast :message="toast" :tone="toastTone" />
    <ConfirmModal
      :open="confirmLeave"
      :message="t('public.leave_confirm')"
      danger
      @confirm="leave"
      @cancel="confirmLeave = false"
    />
  </div>
</template>
