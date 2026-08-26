<script setup>
import { computed } from 'vue'

/**
 * ORGDATE-Logo, Brand Guide Abschnitt 2.
 *
 * Symbol "The Common Point": Reuleaux-Dreieck aus drei Kreisbögen (die
 * Beteiligten und ihre Verfügbarkeiten) mit gesetztem Mittelpunkt (der
 * gefundene Termin). Nicht drehen, spiegeln oder verzerren.
 *
 * Die Maße folgen exakt brand/logo/make_logos.py, damit das Web-Logo und die
 * ausgelieferten SVG-Lockups deckungsgleich sind:
 *
 *   - Konstruktionsraster 0..100, die sichtbare Form liegt darin zwischen
 *     12 und 88 — deshalb viewBox "12 12 76 76" statt "0 0 100 100".
 *   - horizontal: Schriftgrad = 52/76 der sichtbaren Symbolhöhe,
 *     Abstand = halbe Symbolbreite, Grundlinien über die Versalhöhe zentriert
 *   - gestapelt: Schriftgrad = 34/76, Abstand = ein Viertel der Symbolhöhe
 *
 * Die Wortmarke rendert hier live in Outfit 600 statt als Pfad — im Web ist
 * das rund 5 KB kleiner. Für externe Assets liegen die Pfad-Fassungen in
 * brand/logo/.
 */
const props = defineProps({
  // Lockups aus Abschnitt 2.5
  variant: { type: String, default: 'horizontal' },
  size: { type: String, default: 'md' },
  // Negativ auf Primärfarbe: Bögen und Wortmarke weiß, Punkt bleibt Mint.
  inverse: { type: Boolean, default: false },
  // "offen": ein Bogen hell — Einladung, noch nicht entschieden (Abschnitt 3)
  open: { type: Boolean, default: false },
})

/** Sichtbare Symbolhöhe in px. */
const VISIBLE = { sm: 20, md: 26, lg: 36 }

const INK_BOX = 76
const FONT_RATIO = { horizontal: 52 / INK_BOX, stacked: 34 / INK_BOX }

const symbolSize = computed(() => VISIBLE[props.size] ?? VISIBLE.md)

const wordSize = computed(
  () => symbolSize.value * (FONT_RATIO[props.variant] ?? FONT_RATIO.horizontal)
)

const gap = computed(() =>
  props.variant === 'stacked' ? symbolSize.value / 4 : symbolSize.value / 2
)

/**
 * Unter 24px läuft der Ring beim Rastern zu — dafür gibt es die kräftiger
 * gezeichnete Fassung (brand/logo/orgdate-symbol-16.svg).
 */
const stroke = computed(() => (symbolSize.value < 24 ? 17 : 16))
const dotRadius = computed(() => (symbolSize.value < 24 ? 10 : 9.5))

const ringColor = computed(() => (props.inverse ? '#FFFFFF' : 'var(--od-violet)'))
const wordColor = computed(() => (props.inverse ? '#FFFFFF' : 'var(--od-ink)'))

const MARK = 'M50 20A60 60 0 0 0 20 71.96A60 60 0 0 0 80 71.96A60 60 0 0 0 50 20Z'
const MARK_OPEN = 'M50 20A60 60 0 0 0 20 71.96A60 60 0 0 0 80 71.96'
const MARK_OPEN_TAIL = 'M78.69 59.49A60 60 0 0 0 60.15 27.37'
</script>

<template>
  <span
    class="inline-flex"
    :class="variant === 'stacked' ? 'flex-col items-center' : 'items-center'"
    :style="{ gap: `${gap}px` }"
  >
    <svg
      :width="symbolSize"
      :height="symbolSize"
      viewBox="12 12 76 76"
      xmlns="http://www.w3.org/2000/svg"
      role="img"
      aria-label="ORGDATE"
      class="shrink-0"
    >
      <template v-if="open">
        <path
          :d="MARK_OPEN"
          fill="none"
          :stroke="ringColor"
          :stroke-width="stroke"
          stroke-linejoin="round"
          stroke-linecap="round"
        />
        <path
          :d="MARK_OPEN_TAIL"
          fill="none"
          stroke="var(--od-violet-soft)"
          :stroke-width="stroke"
          stroke-linecap="round"
        />
      </template>
      <path
        v-else
        :d="MARK"
        fill="none"
        :stroke="ringColor"
        :stroke-width="stroke"
        stroke-linejoin="round"
      />
      <circle cx="50" cy="54" :r="dotRadius" fill="var(--od-mint)" />
    </svg>

    <span
      v-if="variant !== 'symbol'"
      class="font-display leading-none"
      :style="{
        fontSize: `${wordSize}px`,
        fontWeight: 600,
        letterSpacing: '0.05em',
        color: wordColor,
      }"
    >ORGDATE</span>
  </span>
</template>
