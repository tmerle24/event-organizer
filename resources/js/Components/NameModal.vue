<script setup>
import { nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { rememberedName } from '@/composables/useRememberedName'

/**
 * Nach dem ersten Klick auf eine Antwort oder Aufgabe: Name (Pflicht) und
 * E-Mail (freiwillig). Ohne Formular auf der Seite gibt es kein Scrollen —
 * die Seite bleibt eine Liste.
 */
const props = defineProps({
  open: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])
const { t } = useI18n()

const name = ref(rememberedName())
const email = ref('')
const website = ref('')
const input = ref(null)

watch(
  () => props.open,
  async (open) => {
    if (!open) return
    name.value = rememberedName()
    await nextTick()
    input.value?.focus()
  }
)

function confirm() {
  if (!name.value.trim()) return
  emit('confirm', { display_name: name.value.trim(), email: email.value.trim() || null, website: website.value })
}
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4 py-4" @click.self="emit('cancel')">
    <form class="od-card w-full max-w-sm p-5" role="dialog" aria-modal="true" @submit.prevent="confirm">
      <label class="block font-display font-semibold" for="m-name">{{ t('public.modal_title') }}</label>

      <input
        id="m-name"
        ref="input"
        v-model="name"
        class="od-input mt-3"
        maxlength="80"
        required
        :placeholder="t('public.name_placeholder')"
      />

      <details class="mt-2">
        <summary class="cursor-pointer text-xs text-[var(--od-slate)] hover:text-[var(--od-ink)]">
          {{ t('public.email_toggle') }}
        </summary>
        <input
          id="m-email"
          v-model="email"
          type="email"
          class="od-input mt-2"
          maxlength="180"
          :aria-label="t('public.email')"
          :placeholder="t('manage.email_placeholder')"
        />
        <p class="mt-1 text-xs text-[var(--od-slate)]">{{ t('public.email_hint') }}</p>
      </details>

      <input v-model="website" type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true" />

      <div class="mt-5 flex justify-end gap-2">
        <button type="button" class="od-btn od-btn-ghost" @click="emit('cancel')">{{ t('common.cancel') }}</button>
        <button type="submit" class="od-btn od-btn-primary" :disabled="busy || !name.trim()">{{ t('public.join') }}</button>
      </div>
    </form>
  </div>
</template>
