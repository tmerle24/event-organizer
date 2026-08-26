<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
  open: { type: Boolean, default: false },
  message: { type: String, default: '' },
  danger: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" @click.self="emit('cancel')">
    <div class="od-card w-full max-w-sm p-5" role="dialog" aria-modal="true">
      <p class="text-sm">{{ message || t('common.confirm') }}</p>
      <div class="mt-5 flex justify-end gap-2">
        <button type="button" class="od-btn od-btn-ghost" @click="emit('cancel')">{{ t('common.cancel') }}</button>
        <button
          type="button"
          class="od-btn text-white"
          :style="{ background: danger ? 'var(--od-slate)' : 'var(--od-ink)' }"
          @click="emit('confirm')"
        >
          {{ t('common.done') }}
        </button>
      </div>
    </div>
  </div>
</template>
