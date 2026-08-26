<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { SUPPORTED_LOCALES, setLocale } from '@/i18n'

const { locale } = useI18n()
const open = ref(false)
const root = ref(null)

const FLAGS = { de: '🇩🇪', en: '🇬🇧', fr: '🇫🇷', es: '🇪🇸', nl: '🇳🇱' }
const NAMES = { de: 'Deutsch', en: 'English', fr: 'Français', es: 'Español', nl: 'Nederlands' }

function pick(code) {
  setLocale(code)
  open.value = false
}

function onClickOutside(event) {
  if (root.value && !root.value.contains(event.target)) open.value = false
}

onMounted(() => document.addEventListener('click', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>

<template>
  <div ref="root" class="relative">
    <button
      type="button"
      class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm hover:bg-[var(--od-mist)]"
      :aria-expanded="open"
      aria-haspopup="listbox"
      @click="open = !open"
    >
      <span class="text-base leading-none">{{ FLAGS[locale] }}</span>
      <span class="uppercase text-xs tracking-wide text-[var(--od-slate)]">{{ locale }}</span>
    </button>

    <ul
      v-if="open"
      class="absolute right-0 z-30 mt-1 w-40 overflow-hidden rounded-xl border border-[var(--od-line)] bg-white shadow-lg"
      role="listbox"
    >
      <li v-for="code in SUPPORTED_LOCALES" :key="code">
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-[var(--od-mist)]"
          :class="{ 'font-semibold': code === locale }"
          @click="pick(code)"
        >
          <span class="text-base leading-none">{{ FLAGS[code] }}</span>
          <span>{{ NAMES[code] }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>
