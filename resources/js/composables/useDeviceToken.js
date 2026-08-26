/**
 * Geraete-Token aus LocalStorage (Muster aus SimpleVoter/Wisherful).
 * Identifiziert einen Teilnehmer ohne Login und ohne Cookie-Banner —
 * der Token ist technisch erforderlich, nicht analytisch.
 */
export function useDeviceToken(storageKey) {
  let token = null

  try {
    token = localStorage.getItem(storageKey)
  } catch (e) {
    // Private Mode: dann gilt der Token nur fuer diese Seitensitzung.
  }

  if (!token) {
    token = crypto.randomUUID().replace(/-/g, '')
    try {
      localStorage.setItem(storageKey, token)
    } catch (e) {
      // ignorieren
    }
  }

  return token
}

export function useParticipantToken(publicToken) {
  return useDeviceToken(`od_participant_${publicToken}`)
}
