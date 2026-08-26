import { createI18n } from 'vue-i18n'
import de from './locales/de.json'
import en from './locales/en.json'
import fr from './locales/fr.json'
import es from './locales/es.json'
import nl from './locales/nl.json'

const SUPPORTED_LOCALES = ['de', 'en', 'fr', 'es', 'nl']
const STORAGE_KEY = 'plandu_locale'

/**
 * 1. Gespeicherte Auswahl aus LocalStorage
 * 2. navigator.language auf unterstuetzte Codes mappen
 * 3. Fallback 'en'
 */
function detectLocale() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored && SUPPORTED_LOCALES.includes(stored)) return stored
  } catch (e) {
    // Private-Mode o.ae. — dann entscheidet der Browser.
  }

  const browserLang = (navigator.language || 'en').slice(0, 2).toLowerCase()
  return SUPPORTED_LOCALES.includes(browserLang) ? browserLang : 'en'
}

export const i18n = createI18n({
  legacy: false,
  locale: detectLocale(),
  fallbackLocale: 'en',
  messages: { de, en, fr, es, nl },
})

export function currentLocale() {
  return i18n.global.locale.value
}

export function setLocale(locale) {
  if (!SUPPORTED_LOCALES.includes(locale)) return
  i18n.global.locale.value = locale
  try {
    localStorage.setItem(STORAGE_KEY, locale)
  } catch (e) {
    // ignorieren — die Auswahl gilt dann nur fuer diese Sitzung
  }
  document.documentElement.setAttribute('lang', locale)
  window.axios.defaults.headers.common['X-App-Locale'] = locale
}

export { SUPPORTED_LOCALES }
