# Termin- & Eventplaner – MVP-Spezifikation v0.2

> Status: Entscheidungsreif. Alle mit **[E]** markierten Punkte sind Vorschläge mit Begründung – sie können überschrieben werden, aber sie müssen entschieden sein, bevor implementiert wird. Die drei Punkte in Abschnitt 13 brauchen zwingend eine Entscheidung des PO.

## 1. Ziel & Erfolgsmetrik

**Find a time. Make a plan. Get it done.**

Die App findet nicht nur einen Termin, sondern führt anschließend in die Organisation des Events über. Der Übergang von Terminfindung zu Planung ist die Produkthypothese – nicht die Terminfindung selbst.

**North Star Metric:**
`Planning Conversion` = Anteil der bestätigten Events, in denen nach der Terminentscheidung mindestens eine Aufgabe erstellt wurde.

**Sekundäre Metriken:**

* Response Rate: Anteil eingeladener Teilnehmer, die abstimmen
* Time to Decision: Zeit zwischen Event-Erstellung und Terminbestätigung
* Anteil Events, die ohne KI-Korrektur durch Schritt 1 kommen (Qualität der Extraktion)

**Bewusst keine Metrik:** Anzahl erstellter Events. Ein Terminfinder ohne Planungsübergang validiert die These nicht.

## 2. Rollen & Identität

| Rolle | Account | Identifikation | Rechte |
| --- | --- | --- | --- |
| Organisator | ja (leichtgewichtig) | Magic Link per E-Mail, Session-Cookie | alles im eigenen Event |
| Teilnehmer | nein | `participant_token` (Cookie, HttpOnly, SameSite=Lax, 180 Tage, event-gebunden) | eigene Verfügbarkeit setzen/ändern, eigene Aufgaben abhaken |
| Besucher | nein | – | Event-Seite lesen, Verfügbarkeit erfassen (wird dabei zum Teilnehmer) |

**[E] Kein Passwort im MVP.** Der Organisator legt beim ersten Event eine E-Mail an und erhält einen Magic Link. Begründung: Passwort-Reset, Hashing-Policy und Account-Verwaltung sind vier Wochen Aufwand ohne Beitrag zur Hypothese.

**[E] Teilnehmer-Wiedererkennung:**

1. Erste Antwort → `Participant` wird erstellt (Name Pflicht, E-Mail optional), Server setzt `participant_token`.
2. Zweiter Besuch mit Cookie → Antworten sind editierbar, vorbelegt.
3. Zweiter Besuch ohne Cookie (anderes Gerät) → Nutzer wählt aus der Teilnehmerliste „Das bin ich" und erhält bei hinterlegter E-Mail einen Bestätigungslink; ohne E-Mail entsteht ein neuer Teilnehmer, den der Organisator mergen kann.

**[E] Fremde Antworten sind sichtbar, aber nicht editierbar.** Der Organisator kann Teilnehmer umbenennen, mergen und entfernen. Begründung: Doodle erlaubt das Editieren fremder Antworten und es ist dort die häufigste Beschwerde.

**Pflichtteilnehmer:** Der Organisator kann einzelne Teilnehmer als `required` markieren (z. B. den, der den Grill hat).

## 3. Event-Lifecycle

```text
draft ──► collecting ──► decided ──► planning ──► closed
              │              │           │
              └──────────────┴───────────┴──► cancelled
```

| Status | Bedeutung | Sichtbar |
| --- | --- | --- |
| `draft` | KI-Extraktion erfolgt, Organisator prüft noch | nur Organisator |
| `collecting` | Link ist geteilt, Verfügbarkeiten laufen ein | öffentlich |
| `decided` | Termin bestätigt, Teilnehmer benachrichtigt | öffentlich |
| `planning` | Planungsbereich aktiv, Aufgaben existieren | öffentlich |
| `closed` | Event liegt in der Vergangenheit, read-only | öffentlich |
| `cancelled` | abgesagt | öffentlich, mit Hinweis |

Der Status steuert, welche UI-Bereiche gerendert werden. Damit ist das UX-Prinzip aus Abschnitt 10 („jeder Schritt wird erst sichtbar, wenn er benötigt wird") technisch verankert und nicht nur eine Absichtserklärung.

**[E] `decided` → `planning` erfolgt automatisch** bei der ersten Aktion im Planungsbereich, nicht durch einen expliziten Button. Der Nutzer soll nicht bestätigen, dass er planen will – er soll einfach anfangen.

## 4. User Flow

### Schritt 1 – Event erstellen

Startseite, ein Eingabefeld: **What do you want to organize?**

Die KI extrahiert (siehe Abschnitt 7) und rendert das Ergebnis als **editierbare Feldzeile** direkt über den vorgeschlagenen Terminen – keine Chat-Rückfrage, kein Formularprozess.

```text
🍖 Team BBQ   ·   8 Personen   ·   September 2026   ·   Fr/Sa   ·   [bearbeiten]
```

**[E] Nur ein einziges Feld ist Blocker:** ein Zeitraum. Fehlt er, fragt die App genau einmal nach („When roughly?"). Alles andere wird mit Defaults gefüllt und ist nachträglich änderbar.

### Schritt 2 – Termin finden

Event-Seite mit Terminoptionen. Der Organisator kann Optionen ergänzen, löschen, Uhrzeiten setzen oder auf ganztägig stellen. Teilen über öffentlichen Link (`/e/{slug}`), zusätzlich optional Einladung per E-Mail.

Antwortmöglichkeiten pro Option: ✅ Kann · ❌ Kann nicht · 🤷 Vielleicht

**[E] Default für unbeantwortete Optionen ist „offen", nicht „kann nicht".** Offene Antworten werden getrennt gezählt und dargestellt.

### Schritt 3 – Termin bestimmen

Ranking nach den Regeln aus Abschnitt 5. Der Organisator bestätigt manuell – die App entscheidet nie selbst.

```text
Best match
Saturday, September 12 · 18:00
6 kann · 1 vielleicht · 0 kann nicht · 1 offen
```

Bei Bestätigung: Status → `decided`, Benachrichtigung an alle Teilnehmer mit E-Mail.

### Schritt 4 – Planung starten

> **Your date is set 🎉** Want to plan the event?

Die KI erzeugt Planungssektionen anhand des `planning_template` (barbecue, dinner, trip, meeting, party, generic). Die Sektionen sind Vorschläge und einzeln entfernbar.

### Schritt 5 – Aufgaben verteilen

Aufgaben: erstellen, zuweisen, abhaken. Zuweisung nur an existierende Teilnehmer.

**[E] Die KI schlägt Aufgaben vor, weist aber nie selbst zu.** Vorschläge erscheinen als inaktive Liste mit „Übernehmen"-Aktion pro Zeile.

**[E] Kommentare sind nicht im MVP.** Sie stehen im ursprünglichen Objektmodell, aber nicht in der Muss-Liste – der Widerspruch wird zugunsten des kleineren Scopes aufgelöst. Statusänderung + Zuweisung reichen für die Hypothese.

## 5. Terminfindung – Ranking-Regeln

Pro `DateOption`:

```text
yes_count      = Anzahl ✅
maybe_count    = Anzahl 🤷
no_count       = Anzahl ❌
open_count     = Teilnehmer ohne Antwort
blocked        = mindestens ein required-Teilnehmer hat ❌
score          = 2 * yes_count + 1 * maybe_count
```

**Sortierung:**

1. `blocked` nach unten (nicht ausblenden – der Organisator muss sehen, warum)
2. `no_count` aufsteigend
3. `score` absteigend
4. `open_count` aufsteigend
5. Datum aufsteigend

**Darstellung:** Nie „8/8 verfügbar", solange Antworten fehlen. Stattdessen `6 kann · 2 offen`. Ein falsches Vollständigkeitsgefühl ist der teuerste Fehler in diesem Screen.

**Kein Best Match**, solange weniger als die Hälfte der eingeladenen Teilnehmer geantwortet hat. Stattdessen: „Noch zu wenige Rückmeldungen."

## 6. Zeit & Zeitzonen

**[E] Alles in UTC speichern.** `Event.timezone` als IANA-Kennung (z. B. `Europe/Berlin`), Default aus der Browser-Zeitzone des Organisators.

* Anzeige immer in der Zeitzone des Betrachters
* Weicht sie von der Event-Zeitzone ab, wird ein Hinweis eingeblendet: `18:00 your time · 19:00 in Berlin`
* Ganztägige Optionen (`all_day = true`) werden als reines Datum gespeichert und **nicht** umgerechnet

Begründung: Der Aufwand ist bei Neuentwicklung minimal, die Nachrüstung nach dem ersten Remote-Nutzer nicht. Kalendersync bleibt out of scope, aber ein ICS-Download bei `decided` ist billig und stark – siehe Abschnitt 12.

## 7. KI-Funktion

### Extraktion

Output-Schema (strikt, per Structured Output erzwungen):

```json
{
  "event_name": "Team BBQ",
  "event_type": "barbecue",
  "participant_count": 8,
  "date_range": { "from": "2026-09-01", "to": "2026-09-30" },
  "preferred_days": ["friday", "saturday"],
  "time_of_day": "evening",
  "planning_template": "barbecue",
  "confidence": { "participant_count": "high", "date_range": "medium" }
}
```

**[E] Relative Zeitangaben lösen immer auf das nächste zukünftige Vorkommen auf.** „im September" im Oktober 2026 bedeutet September 2027.

**[E] Felder mit `confidence: low` werden in der Feldzeile visuell markiert.** Der Nutzer sieht sofort, was die KI geraten hat.

### Korrektur

Jedes extrahierte Feld ist per Klick editierbar. Kein Chat-Turn zur Korrektur. Begründung: Eine Rückfrage-Schleife ist genau der Formularprozess, den Abschnitt 10 verbietet – nur langsamer.

### Fallback

**[E] Die KI ist Beschleuniger, nie Voraussetzung.** Bei Timeout, Rate Limit oder Schema-Fehler fällt die App auf ein leeres, manuell befüllbares Event zurück – mit Hinweis, ohne Fehlerseite. Ein Ausfall des LLM-Providers darf die Produkterstellung nicht blockieren.

### Aktionen

Im MVP arbeitet die KI ausschließlich **vorschlagend**. Alle Tools/Functions erzeugen Vorschläge, die der Nutzer übernimmt. Kein Schreibzugriff ohne Bestätigung.

## 8. Benachrichtigungen

**[E] Kanal: nur E-Mail, nur transaktional.**

| Trigger | Empfänger | Pflicht |
| --- | --- | --- |
| Einladung | eingeladene Teilnehmer | ja |
| Termin bestätigt | alle Teilnehmer mit E-Mail | ja |
| Event abgesagt | alle Teilnehmer mit E-Mail | ja |
| Erinnerung 24h vorher | alle Teilnehmer mit E-Mail | nein (Post-MVP) |
| Aufgabe zugewiesen | betroffener Teilnehmer | nein (Post-MVP) |

Jede Mail enthält einen Link zum Entfernen der eigenen Teilnahme inkl. Daten. Dedupe-Key pro (Empfänger, Typ, Event), damit Retries keine Doppelmails erzeugen.

Kein Marketing, kein Newsletter, keine Reaktivierungs-Mails im MVP.

> Hinweis: Ich bin kein Anwalt. Ob für die transaktionalen Mails ein Double-Opt-in nötig ist und wie das Verzeichnis von Verarbeitungstätigkeiten aussehen muss, sollte vor Launch juristisch geprüft werden.

## 9. Datenmodell

```text
User            id, email, created_at, last_login_at
Event           id, owner_user_id, public_slug, title, event_type,
                status, timezone, planning_template, decided_option_id,
                created_at, last_activity_at, delete_after
Participant     id, event_id, display_name, email(nullable),
                token_hash, is_required, user_id(nullable), created_at
DateOption      id, event_id, starts_at_utc, ends_at_utc(nullable),
                all_day, sort
Availability    id, date_option_id, participant_id, value(yes|no|maybe),
                updated_at   ─ UNIQUE(date_option_id, participant_id)
PlanSection     id, event_id, key, title, sort
Task            id, event_id, plan_section_id(nullable), title,
                assignee_participant_id(nullable), status(open|done),
                created_at
Notification    id, event_id, recipient_email, type, dedupe_key,
                sent_at, error  ─ UNIQUE(dedupe_key)
```

**Änderungen gegenüber v0.1:**

* `Participant` sauber von `User` getrennt – ein Teilnehmer *kann* einen Account haben, muss aber nicht
* `Plan` ist keine eigene Entität mehr, sondern die Menge der `PlanSection` eines Events
* `Comment` entfällt im MVP
* `Event.status` als explizite Statusmaschine ergänzt
* `Event.delete_after` für automatische Retention

`Event` bleibt der zentrale Container. Sämtliche Zugriffe laufen über den Event-Kontext – das begrenzt auch die Autorisierungslogik auf einen Punkt.

## 10. UX-Prinzip

Die Anwendung darf sich **nicht wie ein Projektmanagement-Tool** anfühlen.

**Natural language → Terminfindung → Entscheidung → Planung**

Jeder Schritt wird erst sichtbar, wenn er benötigt wird – technisch durchgesetzt über `Event.status`.

**Ablehnungskriterien für neue Features im MVP:** kein Gantt, keine Unteraufgaben, keine Deadlines auf Aufgabenebene, keine Rollen/Rechte-Matrix, keine Ansichtswechsel (Liste/Board/Kalender). Wenn ein Feature nur mit einer Einstellungsseite funktioniert, gehört es nicht ins MVP.

## 11. Missbrauch, Kosten & Datenschutz

**Rate Limiting** (KI-Endpoint, unauthentifiziert):

* max. 500 Zeichen Eingabelänge
* **[E]** 5 Anfragen pro IP und Stunde, 20 pro Tag
* globales Tages-Budget als harter Cutoff; bei Überschreitung → Fallback aus Abschnitt 7 statt Fehler
* Captcha erst ab Überschreitung, nicht by default

**Retention [E]:**

* Event wird 12 Monate nach `last_activity_at` gelöscht, Warn-Mail an den Organisator 14 Tage vorher
* Organisator kann sein Event jederzeit vollständig löschen (kaskadiert auf alle Teilnehmerdaten)
* Teilnehmer können ihre eigene Teilnahme per Link aus jeder Mail entfernen
* keine Analytics-Cookies im MVP; Reichweitenmessung serverseitig und ohne Personenbezug

## 12. MVP-Scope

### Muss (Cut-Line – darunter validiert das Produkt nichts)

* Event per Freitext erstellen, KI-Extraktion mit editierbarer Feldzeile
* Terminoptionen anlegen/ändern
* öffentlicher Teilnehmer-Link, Teilnahme ohne Account
* Verfügbarkeit erfassen und später ändern
* Ranking nach Abschnitt 5, Bestätigung durch Organisator
* E-Mail-Benachrichtigung bei Bestätigung und Absage
* Planungssektionen aus Template
* Aufgaben erstellen, zuweisen, abhaken
* responsive Web-App

### Bewusst hinter der Cut-Line, aber billig

* ICS-Download nach Terminbestätigung (kein Sync, nur eine Datei)
* Erinnerungs-Mail 24h vorher

### Nicht im MVP

Kalendersync · native Apps · Zahlungen · externe Buchungen · Chat · Kommentare · Projektmanagement-Features · Social Features · Benutzerprofile · Wiederholende Events · Mehrsprachigkeit

## 13. Offene Entscheidungen (PO)

1. **Account-Pflicht für den Organisator?** Vorschlag ist Magic Link ohne Passwort. Alternative: auch der Organisator ohne Account, nur mit geheimem Admin-Link. Das senkt die Hürde weiter, macht aber „meine Events" und Retention-Warnungen unmöglich.
2. **E-Mail beim Teilnehmer: Pflicht oder optional?** Optional senkt die Hürde und erhöht die Response Rate; Pflicht ist die Voraussetzung dafür, dass Schritt 3 und 4 überhaupt jemanden erreichen. Ohne Erreichbarkeit der Teilnehmer bricht die Planungsphase – und damit die North Star Metric.
3. **Zielmarkt DE/EU oder international?** Beeinflusst Aufwand bei Zeitzonen, Sprache der UI (die Spec ist durchgängig englisch getextet) und die rechtliche Prüfung.

## 14. Gästeliste & persönliche Einladungen (Konzept, zurückgestellt)

> **Zurückgestellt (15.09.2026).** Bei privaten Feiern ist das Risiko fremder Gäste klein, die Verwaltungsarbeit (Liste pflegen, Plätze freigeben) widerspricht Abschnitt 10, und weitergeleitete Links machen die Beschränkung ohnehin nicht dicht. Das eigentliche Problem – Doppelanmeldungen und Tippfehler-Namen – wird zuerst gemessen (Anzahl Merges, ähnliche Namen pro Event). Unabhängig davon umsetzen: Stufe 1 aus 14.7 (Einladungsadressen nicht im Klartext speichern).

> Status: Konzept, noch nicht implementiert. Baut auf dem **tatsächlichen** Stand der App auf (kein Account, `manage_token`/`public_token`, Geräte-Token pro Event im LocalStorage) – nicht auf den älteren Annahmen aus Abschnitt 2 und 9.

### 14.1 Problem

* Einladungen per E-Mail landen heute als Klartext-Adresse in `mail_notifications` (`recipient_email`, zusätzlich im `dedupe_key`). Das widerspricht dem Ziel „so einfach und sicher wie möglich".
* Wer über den allgemeinen Link kommt, tippt einen Namen. Folge: Tippfehler-Namen, Doppelanmeldungen vom Zweitgerät, manuelles Zusammenführen.
* Partys mit 30–40 Gästen laufen über WhatsApp-Gruppen. Ein persönlicher Link pro Person skaliert dort nicht.

### 14.2 Grundidee

**Ein Platz ist ein Teilnehmer mit Namen, der noch keinem Gerät gehört.**

Der Organisator legt eine Gästeliste an – nur Namen, keine Kontaktdaten. Jeder Platz kann auf zwei Wegen übernommen werden:

| Weg | Wie | Geeignet für |
| --- | --- | --- |
| **Gruppenlink** | Allgemeiner Link → „Wer bist du?" → Namen antippen | WhatsApp-Gruppe, viele Gäste |
| **Persönlicher Link** | Link mit `invite_token` → Platz direkt übernommen, keine Auswahl | E-Mail, einzelne WhatsApp-Nachricht, kleine Runden |

Beides ist optional. Ein Event ohne Gästeliste funktioniert exakt wie heute.

### 14.3 Entscheidungen

**[E] Keine E-Mail-Adressen aus Einladungen speichern.** Die Adresse existiert nur für die Dauer des Versands. Gespeichert wird höchstens ein HMAC-Hash für den Dedupe (siehe 14.7). Die freiwillig *selbst* eingetragene E-Mail beim Join (für „Termin steht"/„Abgesagt") ist davon nicht betroffen – das ist eine eigene Entscheidung (14.10).

**[E] Zuordnung über den Namen, nicht über die Adresse.** Wer per Mail einlädt, gibt Name + E-Mail an. Daraus entsteht ein Platz mit Namen und persönlichem Link; die Adresse wird verworfen.

**[E] Nichts wird beim bloßen Aufruf eingelöst.** Mail-Scanner (Outlook Safe Links, Gmail) und Vorschau-Crawler (WhatsApp) rufen Links vorab ab. Übernahme nur per `POST` aus dem Browser, nie per `GET`.

**[E] Der persönliche Link ist der Schlüssel – auch auf dem Zweitgerät.** Öffnet die Person ihren Link auf einem weiteren Gerät, landet sie beim selben Platz. Weiterleiten lässt sich ohne Login ohnehin nicht verhindern; das wird ehrlich so kommuniziert, statt Scheinsicherheit zu bauen.

**[E] Per Namensauswahl übernommene Plätze sind gerätegebunden.** Ein vergebener Name verschwindet aus der Auswahl. Verklickt oder Gerät gewechselt → der Organisator gibt den Platz frei.

**[E] „Nur Gäste von der Liste" ist ein Schalter pro Event, Default aus.** Aus: unter der Auswahl steht „Ich stehe nicht drauf" mit freier Namenseingabe wie heute. An: freie Eingabe entfällt.

**[E] Kein automatischer WhatsApp-Versand.** Die WhatsApp Business API kostet pro Nachricht, braucht Meta-Verifizierung, freigegebene Templates und Telefonnummern. Stattdessen `wa.me`-Deeplink bzw. Web Share API – der Organisator sendet selbst.

**[E] Keine Telefonnummern speichern.** Die Contact Picker API (nur Chrome/Android) darf Namen vorbefüllen; übernommen wird nur der Name.

### 14.4 Datenmodell

```text
participants    + token            nullable   (NULL = Platz noch frei)
                + invite_token(32) nullable, UNIQUE
                + claimed_at       nullable
                  UNIQUE(event_id, token) bleibt – PostgreSQL erlaubt mehrere NULL

events          + guest_list_only  boolean, default false

mail_notifications
                - recipient_email
                + recipient_hash   HMAC-SHA256(lower(email), APP_KEY)
                  dedupe_key enthält nur noch den Hash
```

* `invite_token` entsteht beim Anlegen eines Platzes (wie die Event-Tokens im `creating`-Hook).
* Ein Platz ohne `token` zählt **nicht** in `open_count` und nicht ins Quorum, solange er nicht übernommen ist. Sonst drückt eine lange Gästeliste die Anzeige in „20 offen" – genau das falsche Vollständigkeitsgefühl in die andere Richtung.
* Migration: vorhandene `recipient_email` in Hashes umrechnen, Spalte löschen.

### 14.5 Routen

```text
# Organisator
POST   /e/{event}/guests                          {names: [...]}         (max 100)
POST   /e/{event}/guests/mail                     {guests: [{name, email}]}  (10/min, max 50)
POST   /e/{event}/participants/{participant}/release   → token = NULL, neuer invite_token
PATCH  /e/{event}                                 + guest_list_only

# Teilnehmer
GET    /t/{event}?i={invite_token}                → Public.vue, löst NICHTS ein
POST   /t/{event}/claim                           {participant_id | invite_token, token}  (20/min)
```

`/e/{event}/data` liefert pro Platz zusätzlich `claimed` und die persönliche `invite_url`. `/t/{event}/state` liefert die **freien Namen** (nur `id` + `display_name`) für die Auswahl – nie `invite_token`.

### 14.6 Abläufe

**Gästeliste anlegen (Organisator)**

1. Teilnehmer-Bereich → „Gästeliste": Textfeld, ein Name pro Zeile (Einfügen aus einer Notiz geht).
2. Doppelte Namen im selben Event werden zusammengefasst (Groß-/Kleinschreibung egal).
3. Liste zeigt pro Platz: Name · „noch frei" / „dabei" · Aktionen *Link kopieren*, *per WhatsApp*, *freigeben*, *entfernen*.

**Gruppenlink (30–40 Gäste)**

1. Ein Link in die Gruppe.
2. Public-Seite, noch kein Platz auf diesem Gerät → „Wer bist du?" mit freien Namen als Buttons, alphabetisch; ab ~15 Namen mit Suchfeld.
3. Tipp auf den Namen → kurze Bestätigung „Du bist Anna?" (gegen Verklicken) → `POST /claim` mit `participant_id` + Geräte-Token.
4. Server prüft `token IS NULL` in derselben Transaktion (`UPDATE … WHERE token IS NULL`), sonst „Den Namen hat gerade jemand genommen." – zwei Gäste gleichzeitig dürfen nicht denselben Platz bekommen.

**Persönlicher Link per E-Mail**

1. Organisator gibt Zeilen `Name, E-Mail` ein.
2. Server legt pro Zeile einen Platz an (oder nutzt einen freien Platz mit gleichem Namen), verschickt die Mail mit `…/t/{public_token}?i={invite_token}`, verwirft die Adresse.
3. Empfänger öffnet → Seite zeigt „Hallo Anna" → erste Aktion (oder ein Button „Das bin ich") löst `POST /claim` mit `invite_token` aus.

**Persönlicher Link per WhatsApp (kleine Runden)**

1. Pro Platz „per WhatsApp" → `https://wa.me/?text=…` mit vorbereitetem Text und persönlichem Link; auf Mobilgeräten alternativ `navigator.share`.
2. Für mehrere Gäste ein Durchklick-Modus: „Nächste: Ben →" öffnet direkt die nächste Nachricht.

**Claim-Regeln**

| Situation | Ergebnis |
| --- | --- |
| Platz frei | Geräte-Token wird gesetzt, `claimed_at = now()` |
| Per `invite_token`, Platz schon übernommen | Server gibt den Token des Platzes zurück, Client schreibt ihn in `od_participant_{public_token}` → Zweitgerät landet beim selben Platz |
| Per `participant_id`, Platz schon übernommen | abgelehnt, Name war nicht mehr in der Auswahl |
| Gerät hat in diesem Event schon einen eigenen Teilnehmer | wird in den Platz zusammengeführt (bestehende Merge-Logik, Platz gewinnt bei Konflikten) |
| `guest_list_only` an, kein Platz | nur Lesen, kein Join |

### 14.7 E-Mail ohne Speicherung

* `EventNotifier::send()` bekommt die Adresse nur noch als Parameter; persistiert werden `recipient_hash`, `type`, `dedupe_key`, `sent_at`.
* `error` wird vor dem Speichern bereinigt – SMTP-Fehlermeldungen enthalten oft die Adresse. Im Zweifel nur die Exception-Klasse speichern.
* Log-Einträge (`Log::warning('Mail failed', …)`) ohne Adresse.
* Versand bleibt synchron. Ein Queue-Job würde die Adresse in `jobs`/`failed_jobs` im Klartext ablegen.
* Datenschutzerklärung anpassen: Einladungsadressen werden nicht gespeichert.

### 14.8 Texte (Tonalität)

* „Wer bist du?" · „Du bist Anna?" · „Ich stehe nicht drauf"
* „Hallo Anna – schön, dass du dabei bist."
* Organisator: „7 sind schon dabei" – **nicht** „7 von 32" (keine Quote) und kein „25 fehlen noch".
* Vergeben: „Den Namen hat gerade jemand genommen. Frag kurz in der Gruppe nach."
* WhatsApp-Vorlage: „Hey Anna, ich plane {Titel}. Trag hier ein, wann du kannst: {Link}"

### 14.9 Grenzen (bewusst akzeptiert)

* Jemand kann in der Auswahl einen fremden Namen antippen. In einer Gruppe, die sich kennt, fällt das auf; der Organisator gibt frei.
* Ein weitergeleiteter persönlicher Link gibt vollen Teilnehmerzugriff auf diesen Platz. Freigeben erzeugt einen neuen `invite_token`, der alte Link ist danach wirkungslos.
* Die Namen auf der Gästeliste sind für alle mit dem Gruppenlink sichtbar. Hinweis im Organisator-UI: „Die Namen sehen alle, die den Link haben."
* Die Link-Vorschau eines persönlichen Links darf den Vornamen zeigen – der Crawler sieht ihn dann auch.

### 14.10 Umsetzung in Stufen

1. **E-Mail ohne Speicherung** (14.7) – unabhängig vom Rest, reine Verbesserung.
2. **Plätze + persönliche Links** (14.4, 14.5, Claim per `invite_token`, Mail mit Name).
3. **Gästeliste + „Wer bist du?"** (Gruppenlink, WhatsApp-Deeplink, Durchklick-Modus).
4. **Schalter „Nur Gäste von der Liste".**

**Tests (Pflicht):**

* `GET /t/{event}?i=…` verändert nichts (kein `token`, kein `claimed_at`).
* `/state` enthält nie `invite_token`.
* Zwei gleichzeitige Claims auf denselben Platz → genau einer gewinnt.
* Nach dem Versand einer Einladung steht die Adresse in keiner Tabelle (`mail_notifications`, `participants`, `jobs`, `failed_jobs`).
* Freie Plätze zählen nicht in `open_count` und nicht ins Quorum.
* `guest_list_only`: Join ohne Platz wird abgelehnt.

**Offene Fragen (PO):**

1. Bleibt die freiwillig beim Join eingetragene E-Mail (für „Termin steht"/„Abgesagt")? Sie ist eine Einwilligung der Person selbst, aber eben doch eine gespeicherte Adresse.
2. Soll ein freier Platz nach dem Termin-Entscheid noch übernehmbar sein, oder friert die Liste dann ein?
3. Obergrenze für die Gästeliste – 100 Plätze pro Event?
