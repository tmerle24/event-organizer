/**
 * "Passt allen" nur, wenn wirklich alle zugesagt haben — kein Vielleicht, kein
 * Nein, nichts offen. Nur dann gibt es Mint (Brand Guide: Mint ist reserviert).
 */
export function fitsEveryone(option) {
  return option.yes_count > 0 && option.maybe_count === 0 && option.no_count === 0 && option.open_count === 0
}

/** Symbol und Farbe je Antwort — gleich in Namensliste und Teilnehmerliste */
export const ANSWERS = {
  yes: { icon: '✓', color: 'var(--od-violet)' },
  maybe: { icon: '~', color: 'var(--od-violet-soft)' },
  no: { icon: '✕', color: 'var(--od-slate)' },
  open: { icon: '○', color: 'var(--od-slate)' },
}
