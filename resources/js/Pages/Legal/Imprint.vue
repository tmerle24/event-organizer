<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Logo from '@/Components/Logo.vue'
import Footer from '@/Components/Footer.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'

const { t } = useI18n()

/* Kontaktadresse kommt aus config/brand.php, damit sie an genau einer Stelle
   steht — im Template nie als i18n-String, sonst greift der vue-i18n-Parser
   auf das @ zu. */
const email = computed(() => usePage().props.brand?.email ?? '')
</script>

<template>
  <Head :title="t('legal.imprint_title')" />

  <div class="min-h-screen">
    <header class="mx-auto flex max-w-2xl items-center justify-between px-6 py-5">
      <a href="/"><Logo /></a>
      <LanguageSwitcher />
    </header>

    <main class="mx-auto max-w-2xl px-6">
      <h1 class="od-h1">{{ t('legal.imprint_title') }}</h1>
      <p class="od-body od-measure mt-4" style="color: var(--od-slate)">{{ t('legal.placeholder') }}</p>
      <p class="od-body mt-4">
        <a :href="`mailto:${email}`" class="underline" style="color: var(--od-violet)">{{ email }}</a>
      </p>
    </main>

    <Footer />
  </div>
</template>
