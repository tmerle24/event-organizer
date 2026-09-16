<script setup>
// Hinweis ueber Icon-Buttons. Nur mit Maus/Tastatur: am Handy loest der Tipp
// die Aktion aus, dort muss die Aktion selbst verstaendlich sein.
defineProps({
  text: { type: String, required: true },
  // start | center | end — end fuer Icons am rechten Rand
  align: { type: String, default: 'center' },
})

const ALIGN = {
  start: 'left-0',
  center: 'left-1/2 -translate-x-1/2',
  end: 'right-0',
}
</script>

<template>
  <span class="group/tip relative inline-flex">
    <slot />
    <span
      aria-hidden="true"
      class="pointer-events-none absolute bottom-full z-30 mb-1 hidden whitespace-nowrap rounded-lg bg-[var(--od-ink)] px-2 py-1 text-xs font-normal text-white pointer-fine:group-hover/tip:block group-has-focus-visible/tip:block"
      :class="ALIGN[align]"
    >
      {{ text }}
    </span>
  </span>
</template>
