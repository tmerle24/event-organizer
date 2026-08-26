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
