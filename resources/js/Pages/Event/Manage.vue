<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Logo from '@/Components/Logo.vue'
import Footer from '@/Components/Footer.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'
import ShareBox from '@/Components/ShareBox.vue'
import EventFieldRow from '@/Components/EventFieldRow.vue'
import DateOptionsPanel from '@/Components/DateOptionsPanel.vue'
import ParticipantsPanel from '@/Components/ParticipantsPanel.vue'
import PlanPanel from '@/Components/PlanPanel.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import Toast from '@/Components/Toast.vue'
import { rememberEvent, forgetEvent, updateEventTitle } from '@/composables/useMyEvents'

const props = defineProps({
  event: { type: Object, required: true },
})

const { t } = useI18n()

const event = ref(props.event)
const toast = ref('')
const toastTone = ref('ok')
const editing = ref(false)
const email = ref(props.event.organizer_email || '')
const busy = ref(false)
const confirm = ref({ open: false, message: '', danger: false, action: null })

const baseUrl = computed(() => `/e/${event.value.manage_token}`)

const showDates = computed(() => ['dates', 'both'].includes(event.value.mode))
/**
 * Der Planungsbereich erscheint erst, wenn er gebraucht wird: bei reinen
 * Listen sofort, sonst ab der Terminbestaetigung (Spec Abschnitt 3 + 10).
 */
const showPlan = computed(
  () =>
    event.value.mode === 'list' ||
    ['decided', 'planning', 'closed'].includes(event.value.status)
)

let poller = null

onMounted(() => {
  rememberEvent({
    manage_token: event.value.manage_token,
    public_token: event.value.public_token,
    title: event.value.title,
  })

  // Live-Aktualisierung. Pausiert, solange ein Feld fokussiert ist — sonst
  // ueberschreibt der Refresh ungespeicherte Eingaben.
  poller = setInterval(refresh, 6000)
})

onBeforeUnmount(() => clearInterval(poller))

async function refresh() {
  if (editing.value || busy.value || document.hidden) return

  try {
    const { data } = await window.axios.get(`${baseUrl.value}/data`)
    event.value = data.event
  } catch (e) {
    // Kein Toast: ein fehlgeschlagener Hintergrund-Refresh ist kein Fehler,
    // den der Nutzer sehen muss.
  }
}

function onUpdated(payload) {
  event.value = payload
  updateEventTitle(payload.manage_token, payload.title)
}

function flash(message, tone = 'ok') {
  toastTone.value = tone
  toast.value = message
  setTimeout(() => (toast.value = ''), 2600)
}

function ask(message, action, danger = false) {
  confirm.value = { open: true, message, danger, action }
}

async function runConfirmed() {
  const action = confirm.value.action
  confirm.value = { open: false, message: '', danger: false, action: null }
  if (action) await action()
}

async function sendManageLink() {
  if (!email.value) return
  busy.value = true

  try {
    await window.axios.post(`${baseUrl.value}/email`, { email: email.value })
    flash(t('manage.email_sent'))
  } catch (e) {
    flash(t('common.error'), 'error')
  } finally {
    busy.value = false
  }
}

async function cancelEvent() {
  busy.value = true
  try {
    const { data } = await window.axios.post(`${baseUrl.value}/cancel`)
    event.value = data.event
  } catch (e) {
    flash(t('common.error'), 'error')
  } finally {
    busy.value = false
  }
}

async function reopenEvent() {
  busy.value = true
  try {
    const { data } = await window.axios.post(`${baseUrl.value}/reopen`)
    event.value = data.event
  } catch (e) {
    flash(t('common.error'), 'error')
  } finally {
    busy.value = false
  }
}

async function deleteEvent() {
  busy.value = true
  try {
    await window.axios.delete(baseUrl.value)
    forgetEvent(event.value.manage_token)
    window.location.href = '/'
  } catch (e) {
    busy.value = false
    flash(t('common.error'), 'error')
  }
}
</script>

<template>
  <Head :title="event.title" />

  <div class="min-h-screen">
    <header class="mx-auto flex max-w-3xl items-center justify-between px-6 py-5">
      <a href="/"><Logo /></a>
      <div class="flex items-center gap-2">
        <span
          class="rounded-lg px-2.5 py-1 text-xs font-semibold"
          :style="
            event.status === 'cancelled'
              ? { background: 'var(--od-mist)', color: 'var(--od-slate)' }
              : { background: 'var(--od-violet-tint)', color: 'var(--od-violet-dark)' }
          "
        >
          {{ t(`manage.status.${event.status}`) }}
        </span>
        <LanguageSwitcher />
      </div>
    </header>

    <main class="mx-auto max-w-3xl space-y-4 px-6 pb-10">
      <EventFieldRow
        :event="event"
        :base-url="baseUrl"
        @updated="onUpdated"
        @focus-change="editing = $event"
      />

      <ShareBox :url="event.public_url" :title="event.title" />

      <DateOptionsPanel
        v-if="showDates"
        :event="event"
        :base-url="baseUrl"
        @updated="onUpdated"
        @focus-change="editing = $event"
        @error="flash(t('common.error'), 'error')"
      />

      <PlanPanel
        v-if="showPlan"
        :event="event"
        :base-url="baseUrl"
        can-manage
        @updated="onUpdated"
        @focus-change="editing = $event"
        @error="flash(t('common.error'), 'error')"
      />

      <ParticipantsPanel
        :event="event"
        :base-url="baseUrl"
        @updated="onUpdated"
        @focus-change="editing = $event"
        @error="flash(t('common.error'), 'error')"
        @flash="flash"
      />

      <!-- Verwaltungslink sichern -->
      <section class="od-card p-4 sm:p-5">
        <h2 class="font-display font-semibold">{{ t('manage.save_link') }}</h2>
        <p class="mt-1 text-xs text-[var(--od-slate)]">{{ t('manage.save_link_hint') }}</p>
        <p class="mt-2 text-xs" style="color: var(--od-violet-soft)">⚠ {{ t('manage.manage_warning') }}</p>

        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
          <input
            v-model="email"
            type="email"
            class="od-input text-sm"
            :placeholder="t('manage.email_placeholder')"
            @focus="editing = true"
            @blur="editing = false"
          />
          <button type="button" class="od-btn od-btn-ghost whitespace-nowrap text-sm" :disabled="busy || !email" @click="sendManageLink">
            {{ t('common.save') }}
          </button>
        </div>
      </section>

      <section class="od-card p-4 sm:p-5">
        <h2 class="font-display font-semibold">{{ t('manage.danger.title') }}</h2>
        <div class="mt-3 flex flex-wrap gap-2">
          <button
            v-if="event.status !== 'cancelled'"
            type="button"
            class="od-btn od-btn-ghost text-sm"
            :disabled="busy"
            @click="ask(t('manage.danger.cancel_confirm'), cancelEvent, true)"
          >
            {{ t('manage.danger.cancel') }}
          </button>
          <button v-else type="button" class="od-btn od-btn-ghost text-sm" :disabled="busy" @click="reopenEvent">
            {{ t('manage.danger.reopen') }}
          </button>

          <button
            type="button"
            class="od-btn text-sm text-white"
            style="background: var(--od-slate)"
            :disabled="busy"
            @click="ask(t('manage.danger.delete_confirm'), deleteEvent, true)"
          >
            {{ t('manage.danger.delete') }}
          </button>
        </div>
      </section>
    </main>

    <Footer />
    <Toast :message="toast" :tone="toastTone" />
    <ConfirmModal
      :open="confirm.open"
      :message="confirm.message"
      :danger="confirm.danger"
      @confirm="runConfirmed"
      @cancel="confirm.open = false"
    />
  </div>
</template>
