<script setup>
import { computed } from 'vue'

/**
 * ORGDATE-Logo, Brand Guide Abschnitt 2.
 *
 * Symbol "The Common Point": Reuleaux-Dreieck aus drei Kreisbögen (die
 * Beteiligten und ihre Verfügbarkeiten) mit gesetztem Mittelpunkt (der
 * gefundene Termin). Die Geometrie ist exakt aus Abschnitt 2.2 übernommen und
 * darf nicht gedreht, gespiegelt oder verzerrt werden.
 *
 * Die Wortmarke steht immer in Versalien, Outfit 600, Tracking +0.05em.
 */
const props = defineProps({
  // horizontal | stacked | symbol (Lockups aus Abschnitt 2.5)
  variant: { type: String, default: 'horizontal' },
  size: { type: String, default: 'md' },
  // Negativ auf Primärfarbe: Bögen und Wortmarke weiß, Punkt bleibt Mint.
  inverse: { type: Boolean, default: false },
  // "offen": ein Bogen hell — Einladung, noch nicht entschieden (Abschnitt 3)
  open: { type: Boolean, default: false },
})

const SYMBOL_PX = { sm: 20, md: 26, lg: 36 }
const WORD_PX = { sm: 15, md: 19, lg: 27 }

const symbolSize = computed(() => SYMBOL_PX[props.size] ?? SYMBOL_PX.md)
const wordSize = computed(() => WORD_PX[props.size] ?? WORD_PX.md)
const strokeColor = computed(() => (props.inverse ? '#FFFFFF' : 'var(--od-violet)'))
const wordColor = computed(() => (props.inverse ? '#FFFFFF' : 'var(--od-ink)'))

/* Abstand Symbol → Wortmarke = halbe Symbolbreite (horizontal) bzw. ein
   Drittel der Symbolhöhe (gestapelt). */
const gap = computed(() =>
  props.variant === 'stacked' ? `${symbolSize.value / 3}px` : `${symbolSize.value / 2}px`
)
</script>

<template>
  <span
    class="inline-flex"
    :class="variant === 'stacked' ? 'flex-col items-center' : 'items-center'"
    :style="{ gap }"
  >
    <svg
      :width="symbolSize"
      :height="symbolSize"
      viewBox="0 0 100 100"
      xmlns="http://www.w3.org/2000/svg"
      role="img"
      aria-label="ORGDATE"
      class="shrink-0"
    >
      <template v-if="open">
        <!-- Offene Variante: der obere rechte Bogen bleibt hell. -->
        <path
          d="M80 71.96A60 60 0 0 0 50 20"
          fill="none"
          stroke="var(--od-violet-soft)"
          stroke-width="16"
          stroke-linecap="round"
        />
        <path
          d="M50 20A60 60 0 0 0 20 71.96A60 60 0 0 0 80 71.96"
          fill="none"
          :stroke="strokeColor"
          stroke-width="16"
          stroke-linejoin="round"
          stroke-linecap="round"
        />
      </template>
      <path
        v-else
        d="M50 20A60 60 0 0 0 20 71.96A60 60 0 0 0 80 71.96A60 60 0 0 0 50 20Z"
        fill="none"
        :stroke="strokeColor"
        stroke-width="16"
        stroke-linejoin="round"
      />
      <circle cx="50" cy="54" r="9.5" fill="var(--od-mint)" />
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
