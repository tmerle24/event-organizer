<script setup>
import { useI18n } from 'vue-i18n'
import Logo from '@/Components/Logo.vue'

const { t } = useI18n()
const year = new Date().getFullYear()

defineProps({
  // Auf der Teilnehmer-Seite steht die Marke dezent unter dem Inhalt.
  poweredBy: { type: Boolean, default: false },
})
</script>

<template>
  <footer class="mt-16" style="border-top: 1px solid var(--od-line)">
    <div
      class="mx-auto flex max-w-3xl flex-col items-center justify-between gap-3 px-6 pt-6 pb-10 sm:flex-row"
      style="color: var(--od-slate)"
    >
      <!-- gap-3 statt gap-2: die viewBox des Logos ist auf die sichtbare Form
           beschnitten, es bringt also keine eigene Luft mehr mit. -->
      <a v-if="poweredBy" href="/" class="flex items-center gap-3 opacity-60 transition hover:opacity-100">
        <span class="od-meta">{{ t('footer.made_with') }}</span>
        <Logo size="sm" />
      </a>
      <span v-else class="od-small">
        © {{ year }} <a href="/" class="hover:underline">ORGDATE</a> — {{ t('footer.rights') }}
      </span>

      <nav class="od-meta flex items-center gap-4">
        <a href="/" class="hover:text-[var(--od-violet)]">{{ t('footer.home') }}</a>
        <a href="/datenschutz" class="hover:text-[var(--od-violet)]">{{ t('footer.privacy') }}</a>
        <a href="/impressum" class="hover:text-[var(--od-violet)]">{{ t('footer.imprint') }}</a>
      </nav>
      <a
        href="https://www.solvedapps.com"
        target="_blank"
        rel="noopener"
        aria-label="solvedapps"
        class="opacity-50 transition-opacity hover:opacity-90"
      ><img src="/images/solvedapps-wordmark.svg" alt="solvedapps" width="130" height="32" class="relative top-[-2px]" /></a>
    </div>
  </footer>
</template>
