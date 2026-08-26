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
      <a v-if="poweredBy" href="/" class="flex items-center gap-2 opacity-60 transition hover:opacity-100">
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
    </div>
  </footer>
</template>
