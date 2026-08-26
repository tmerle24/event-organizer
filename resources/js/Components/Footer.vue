<script setup>
import { useI18n } from 'vue-i18n'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const { t } = useI18n()
const year = new Date().getFullYear()
const name = computed(() => usePage().props.appName || 'Plandu')

defineProps({
  poweredBy: { type: Boolean, default: false },
})
</script>

<template>
  <footer class="mt-16 border-t border-[var(--color-pl-line)]">
    <div class="mx-auto flex max-w-3xl flex-col items-center justify-between gap-3 px-6 pt-6 pb-10 text-sm text-[var(--color-pl-muted)] sm:flex-row">
      <span v-if="poweredBy" class="text-xs">
        <a href="/" class="hover:text-[var(--color-pl-accent)]">Powered by {{ name }}</a>
      </span>
      <span v-else>© {{ year }} <a href="/" class="hover:text-[var(--color-pl-accent)]">{{ name }}</a> — {{ t('footer.rights') }}</span>

      <nav class="flex items-center gap-4 text-xs">
        <a href="/" class="hover:text-[var(--color-pl-accent)]">{{ t('footer.home') }}</a>
        <a href="/datenschutz" class="hover:text-[var(--color-pl-accent)]">{{ t('footer.privacy') }}</a>
        <a href="/impressum" class="hover:text-[var(--color-pl-accent)]">{{ t('footer.imprint') }}</a>
      </nav>
    </div>
  </footer>
</template>
