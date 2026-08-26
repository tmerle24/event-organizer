<script setup>
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Logo from '@/Components/Logo.vue'
import Footer from '@/Components/Footer.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'

const { t } = useI18n()
</script>

<template>
  <Head :title="t('legal.privacy_title')" />

  <div class="min-h-screen">
    <header class="mx-auto flex max-w-2xl items-center justify-between px-6 py-5">
      <a href="/"><Logo /></a>
      <LanguageSwitcher />
    </header>

    <main class="mx-auto max-w-2xl px-6">
      <h1 class="font-display text-2xl font-bold">{{ t('legal.privacy_title') }}</h1>
      <p class="mt-4 text-sm text-[var(--color-pl-muted)]">{{ t('legal.placeholder') }}</p>
    </main>

    <Footer />
  </div>
</template>
