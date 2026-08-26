<script setup>
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'

const props = defineProps({
  url: { type: String, required: true },
  title: { type: String, default: '' },
})

const { t } = useI18n()
const copied = ref(false)
const qr = ref('')
const canShare = ref(false)

async function render() {
  try {
    qr.value = await QRCode.toDataURL(props.url, {
      margin: 1,
      width: 320,
      color: { dark: '#1f2430', light: '#ffffff' },
    })
  } catch (e) {
    qr.value = ''
  }
}

onMounted(() => {
  canShare.value = typeof navigator !== 'undefined' && !!navigator.share
  render()
})
watch(() => props.url, render)

async function copy() {
  try {
    await navigator.clipboard.writeText(props.url)
  } catch (e) {
    // Fallback fuer Browser ohne Clipboard-API (oder ohne HTTPS)
    const input = document.createElement('input')
    input.value = props.url
    document.body.appendChild(input)
    input.select()
    document.execCommand('copy')
    document.body.removeChild(input)
  }

  copied.value = true
  setTimeout(() => (copied.value = false), 1800)
}

async function share() {
  try {
    await navigator.share({ title: props.title, url: props.url })
  } catch (e) {
    // Abbruch durch den Nutzer ist kein Fehler
  }
}
</script>

<template>
  <div class="od-card p-4 sm:p-5">
    <p class="font-display text-sm font-semibold">{{ t('manage.your_link') }}</p>
    <p class="mt-1 text-xs text-[var(--od-slate)]">{{ t('manage.link_hint') }}</p>

    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
      <input
        :value="url"
        readonly
        class="od-input font-mono-num text-sm"
        aria-label="Link"
        @focus="$event.target.select()"
      />
      <div class="flex gap-2">
        <button type="button" class="od-btn od-btn-primary whitespace-nowrap" @click="copy">
          {{ copied ? t('common.copied') : t('common.copy') }}
        </button>
        <button v-if="canShare" type="button" class="od-btn od-btn-ghost whitespace-nowrap" @click="share">
          {{ t('common.share') }}
        </button>
      </div>
    </div>

    <details class="mt-3">
      <summary class="cursor-pointer text-xs text-[var(--od-slate)] hover:text-[var(--od-ink)]">QR-Code</summary>
      <img v-if="qr" :src="qr" alt="QR" class="mt-3 h-40 w-40 rounded-xl border border-[var(--od-line)]" />
    </details>
  </div>
</template>
