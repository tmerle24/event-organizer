/**
 * Google-Maps-Suchlink für einen frei eingegebenen Ort.
 *
 * Bewusst die Suche und nicht /maps/place: die Eingabe ist Freitext
 * ("bei Anna im Garten"), keine Adresse. Die Suche findet auch Namen und
 * zeigt sonst wenigstens das Suchergebnis, statt auf einer leeren Karte zu
 * landen.
 */
export function mapsLink(location) {
  const query = (location || '').trim()
  if (!query) return null

  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`
}
