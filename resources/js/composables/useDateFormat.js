import { currentLocale } from '@/i18n'

/**
 * Anzeige immer in der Zeitzone des Betrachters (Spec Abschnitt 6).
 * Ganztaegige Optionen werden nie umgerechnet — sie sind ein reines Datum.
 */
export function viewerTimezone() {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Berlin'
  } catch (e) {
    return 'Europe/Berlin'
  }
}

function locale() {
  return currentLocale()
}

export function formatDay(option) {
  const date = option.all_day && option.day ? new Date(`${option.day}T12:00:00`) : new Date(option.starts_at_utc)
  const opts = { weekday: 'short', day: 'numeric', month: 'short' }
  if (!option.all_day) opts.timeZone = viewerTimezone()
  return new Intl.DateTimeFormat(locale(), opts).format(date)
}

export function formatTime(option, timeZone = viewerTimezone()) {
  if (option.all_day) return null
  return new Intl.DateTimeFormat(locale(), {
    hour: '2-digit',
    minute: '2-digit',
    timeZone,
  }).format(new Date(option.starts_at_utc))
}

export function formatFull(option, timeZone = viewerTimezone()) {
  const date = option.all_day && option.day ? new Date(`${option.day}T12:00:00`) : new Date(option.starts_at_utc)
  const opts = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }
  if (!option.all_day) {
    opts.hour = '2-digit'
    opts.minute = '2-digit'
    opts.timeZone = timeZone
  }
  return new Intl.DateTimeFormat(locale(), opts).format(date)
}

/**
 * Nur einblenden, wenn Betrachter- und Event-Zeitzone wirklich zu einer
 * anderen Uhrzeit fuehren — sonst ist der Hinweis Rauschen.
 */
export function timezoneNote(option, eventTimezone) {
  if (option.all_day) return null
  const viewer = viewerTimezone()
  if (viewer === eventTimezone) return null

  const local = formatTime(option, viewer)
  const atEvent = formatTime(option, eventTimezone)
  if (local === atEvent) return null

  return { local, event: atEvent, zone: eventTimezone.split('/').pop().replace('_', ' ') }
}

export function isoDay(date) {
  const d = new Date(date)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
