<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Logo from '@/Components/Logo.vue'
import Footer from '@/Components/Footer.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'
import Toast from '@/Components/Toast.vue'
import { viewerTimezone } from '@/composables/useDateFormat'
import { readMyEvents, rememberEvent } from '@/composables/useMyEvents'

const { t } = useI18n()
const appName = computed(() => usePage().props.appName || 'ORGDATE')

const input = ref('')
const mode = ref('dates')
const website = ref('') // Honeypot
const busy = ref(false)
const toast = ref('')
const toastTone = ref('ok')
const myEvents = ref([])

const MODES = ['dates', 'list', 'both']

const canSubmit = computed(() => input.value.trim().length > 1 && !busy.value)

onMounted(() => {
  myEvents.value = readMyEvents()
})

function flash(message, tone = 'ok') {
  toastTone.value = tone
  toast.value = message
  setTimeout(() => (toast.value = ''), 2600)
}

async function submit() {
  if (!canSubmit.value) {
    if (!input.value.trim()) flash(t('landing.empty'), 'error')
    return
  }

  busy.value = true

  try {
    const { data } = await window.axios.post('/events', {
      input: input.value.trim(),
      mode: mode.value,
      timezone: viewerTimezone(),
      website: website.value,
    })

    rememberEvent({
      manage_token: data.manage_token,
      public_token: data.public_token,
      title: input.value.trim().slice(0, 60),
    })

    // Harte Navigation statt Inertia-Visit: der Manage-Screen laedt seine
    // Daten frisch und der Verlaufseintrag zeigt direkt auf das Event.
    window.location.href = data.manage_url
  } catch (e) {
    busy.value = false
    flash(t('common.error'), 'error')
  }
}

function onKeydown(event) {
  if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') submit()
}
</script>

<template>
  <Head :title="`${appName} – ${t('landing.tagline')}`" />

  <div class="min-h-screen">
    <header class="mx-auto flex max-w-3xl items-center justify-between px-6 py-5">
      <Logo />
      <LanguageSwitcher />
    </header>

    <main class="mx-auto max-w-3xl px-6">
      <section class="pt-8 pb-2 text-center sm:pt-16">
        <h1 class="od-display od-measure mx-auto">{{ t('landing.tagline') }}</h1>
        <p class="od-measure mx-auto mt-4 text-lg" style="font-weight: 300; color: var(--od-slate)">
          {{ t('landing.sub') }}
        </p>
      </section>

      <form class="od-card mt-8 p-4 sm:p-6" @submit.prevent="submit">
        <label for="event-input" class="sr-only">{{ t('landing.placeholder') }}</label>
        <textarea
          id="event-input"
          v-model="input"
          rows="3"
          maxlength="500"
          class="od-input text-lg leading-snug sm:text-xl"
          :placeholder="t('landing.placeholder')"
          autofocus
          @keydown="onKeydown"
        />
        <p class="od-meta mt-2">{{ t('landing.example') }}</p>

        <!-- Honeypot: unsichtbar, nur Bots fuellen das aus -->
        <input v-model="website" type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true" />

        <fieldset class="mt-5">
          <legend class="od-meta">{{ t('landing.mode.label') }}</legend>
          <div class="mt-2 grid gap-2 sm:grid-cols-3">
            <label
              v-for="option in MODES"
              :key="option"
              class="cursor-pointer border p-3.5 transition"
              :style="{
                borderRadius: 'var(--od-radius-md)',
                borderColor: mode === option ? 'var(--od-violet)' : 'var(--od-line)',
                background: mode === option ? 'var(--od-violet-tint)' : 'var(--od-white)',
              }"
            >
              <input v-model="mode" type="radio" :value="option" class="sr-only" />
              <span class="block text-[15px] font-medium">{{ t(`landing.mode.${option}`) }}</span>
              <span class="od-meta mt-0.5 block">{{ t(`landing.mode.${option}_hint`) }}</span>
            </label>
          </div>
        </fieldset>

        <button type="submit" class="od-btn od-btn-primary mt-5 w-full py-3.5 text-base" :disabled="!canSubmit">
          {{ busy ? t('landing.creating') : t('landing.submit') }}
        </button>
      </form>

      <section v-if="myEvents.length" class="mt-10">
        <h2 class="od-h3">{{ t('landing.recent') }}</h2>
        <p class="od-meta">{{ t('landing.recent_hint') }}</p>
        <ul class="mt-3 space-y-1.5">
          <li v-for="event in myEvents" :key="event.manage_token">
            <a
              :href="`/e/${event.manage_token}`"
              class="od-card flex items-center justify-between px-4 py-3 text-sm transition hover:border-[var(--od-violet-soft)]"
            >
              <span class="truncate">{{ event.title }}</span>
              <span aria-hidden="true" class="text-[var(--od-slate)]">→</span>
            </a>
          </li>
        </ul>
      </section>

      <section class="mt-14">
        <h2 class="od-h3 text-center" style="color: var(--od-slate)">{{ t('landing.how.title') }}</h2>
        <ol class="mt-5 grid gap-4 sm:grid-cols-3">
          <li v-for="step in [1, 2, 3]" :key="step" class="od-panel p-5">
            <span
              class="font-mono-num flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium"
              style="background: var(--od-violet-tint); color: var(--od-violet-dark)"
            >
              {{ step }}
            </span>
            <h3 class="od-h3 mt-3">{{ t(`landing.how.step${step}`) }}</h3>
            <p class="od-small mt-1" style="color: var(--od-slate)">{{ t(`landing.how.step${step}_text`) }}</p>
          </li>
        </ol>
      </section>
    </main>

    <Footer />
    <Toast :message="toast" :tone="toastTone" />
  </div>
</template>
