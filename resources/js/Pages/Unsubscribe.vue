<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Logo from '@/Components/Logo.vue'
import Footer from '@/Components/Footer.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'

const props = defineProps({
  title: { type: String, required: true },
  publicUrl: { type: String, required: true },
  done: { type: Boolean, default: false },
})

const { t } = useI18n()

const finished = ref(props.done)
const busy = ref(false)
const failed = ref(false)

// POST an dieselbe signierte URL, GET allein aendert nichts
async function confirm() {
  busy.value = true
  failed.value = false
  try {
    await window.axios.post(window.location.pathname + window.location.search)
    finished.value = true
  } catch (e) {
    failed.value = true
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <Head :title="title" />

    <header class="mx-auto flex w-full max-w-2xl items-center justify-between px-6 py-5">
      <a href="/" class="flex"><Logo /></a>
      <LanguageSwitcher />
    </header>

    <main class="mx-auto w-full max-w-2xl flex-1 px-6 py-6">
      <section class="od-card p-5 sm:p-6">
        <p class="od-meta">{{ title }}</p>

        <template v-if="finished">
          <h1 class="od-h2 mt-1">{{ t('unsubscribe.done_title') }}</h1>
          <p class="od-small mt-2 text-[var(--od-slate)]">{{ t('unsubscribe.done_body') }}</p>
        </template>

        <template v-else>
          <h1 class="od-h2 mt-1">{{ t('unsubscribe.title') }}</h1>
          <p class="od-small mt-2 text-[var(--od-slate)]">{{ t('unsubscribe.body') }}</p>
          <button type="button" class="od-btn od-btn-primary mt-4" :disabled="busy" @click="confirm">
            {{ t('unsubscribe.confirm') }}
          </button>
          <p v-if="failed" class="od-small mt-2 text-[var(--od-slate)]">{{ t('common.error') }}</p>
        </template>

        <a :href="publicUrl" class="mt-5 block w-fit text-sm text-[var(--od-violet)] hover:underline">
          {{ t('unsubscribe.back') }}
        </a>
      </section>
    </main>

    <Footer />
  </div>
</template>
