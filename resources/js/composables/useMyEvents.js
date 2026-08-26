const KEY = 'plandu_my_events'

/**
 * Merkt sich erstellte Events auf dem Geraet. Ersetzt bewusst kein Konto —
 * der Verwaltungslink bleibt der einzige echte Schluessel; das hier ist nur
 * Bequemlichkeit auf der Startseite.
 */
export function readMyEvents() {
  try {
    const raw = localStorage.getItem(KEY)
    const list = raw ? JSON.parse(raw) : []
    return Array.isArray(list) ? list : []
  } catch (e) {
    return []
  }
}

export function rememberEvent({ manage_token, public_token, title }) {
  try {
    const list = readMyEvents().filter((e) => e.manage_token !== manage_token)
    list.unshift({ manage_token, public_token, title, created_at: new Date().toISOString() })
    localStorage.setItem(KEY, JSON.stringify(list.slice(0, 12)))
  } catch (e) {
    // ignorieren
  }
}

export function updateEventTitle(manage_token, title) {
  try {
    const list = readMyEvents().map((e) => (e.manage_token === manage_token ? { ...e, title } : e))
    localStorage.setItem(KEY, JSON.stringify(list))
  } catch (e) {
    // ignorieren
  }
}

export function forgetEvent(manage_token) {
  try {
    localStorage.setItem(KEY, JSON.stringify(readMyEvents().filter((e) => e.manage_token !== manage_token)))
  } catch (e) {
    // ignorieren
  }
}
