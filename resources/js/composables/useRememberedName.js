/**
 * Zuletzt benutzter Name, nur im Browser — damit man ihn beim naechsten Event
 * nicht neu tippt. Geht nie an den Server, solange niemand "Passt so" drueckt.
 */
const KEY = 'od_name'

export function rememberedName() {
  try {
    return localStorage.getItem(KEY) || ''
  } catch (e) {
    return ''
  }
}

export function rememberName(name) {
  try {
    if (name) localStorage.setItem(KEY, name)
  } catch (e) {
    // Private Mode: dann eben nicht
  }
}
