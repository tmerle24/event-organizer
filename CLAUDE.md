# Plandu (Event-Organizer) – CLAUDE.md

Architektur, Konventionen und Entscheidungen dieses Projekts für Claude Code.

> **Arbeitsname:** „Plandu". Der endgültige Name steht noch nicht fest. Er wird
> **ausschließlich** über `APP_NAME` in der `.env` gesetzt — im Code steht kein
> hartcodierter Brand-Name (Logo, Footer und `<title>` lesen `appName` aus den
> geteilten Inertia-Props). Umbenennen = eine Zeile in der `.env`.

---

## Produkt-Übersicht

**Find a time. Make a plan. Get it done.**

Ohne Anmeldung: Freitext auf der Startseite eingeben → die App extrahiert Titel,
Zeitraum, Teilnehmerzahl und Terminvorschläge → Link teilen → alle tragen ihre
Verfügbarkeit ein → Organisator bestätigt einen Termin → daraus entsteht die
Planungsliste (wer bringt was mit).

Die Produkthypothese ist der **Übergang von Terminfindung zu Planung**, nicht die
Terminfindung selbst. North Star Metric: Anteil bestätigter Events, in denen
danach mindestens eine Aufgabe erstellt wurde.

Vollständige Spezifikation: `event-planner-spec-v0.2.md` im Repo-Root.

---

## Tech-Stack

| Bereich | Technologie |
|---|---|
| Backend | Laravel 13, PHP 8.4 |
| Frontend-Bridge | Inertia.js v2 (`inertiajs/inertia-laravel` ^2.0 + `@inertiajs/vue3` ^2.0) |
| Datenbank | **PostgreSQL 16** (lokal via `docker compose`, Port **5433**) |
| Frontend | Vue 3, ausschließlich `<script setup>` |
| Styling | Tailwind CSS v4 + `@tailwindcss/vite` (CSS-first, kein `tailwind.config.js`) |
| i18n | vue-i18n v9 (DE/EN/FR/ES/NL) + Laravel `__()` für Mails/Templates |
| Build | Vite 8 |
| KI | Anthropic Claude API (Tool-Use als Structured Output), **optional** |
| Mail | Laravel Mailable (Markdown) |
| Dev-Server | `composer dev` (Port 8080) |

**Tailwind v4:** `resources/css/app.css` wird **nicht** separat in `@vite()`
übergeben — Tailwind v4 bündelt das CSS ins JS. In `app.blade.php` steht nur
`resources/js/app.js`.

**Port 5433 statt 5432:** Auf der Entwicklungsmaschine belegt bereits ein anderes
Projekt (`namibway-pgsql`) den Standard-Port.

---

## Architektur-Prinzip: „Auth ohne Auth"

Es gibt **keine `users`-Tabelle** und kein Login — auch nicht für den
Organisator. Damit ist die offene Entscheidung aus Spec-Abschnitt 13.1 zugunsten
der Alternative („nur geheimer Admin-Link") beantwortet: die Hürde bleibt null,
passend zum SimpleVoter-Muster.

| Token | Länge | Wer bekommt ihn | Zweck |
|---|---|---|---|
| `manage_token` | 64 | Nur der Ersteller (URL + LocalStorage) | Voller Zugriff auf das Event |
| `public_token` | 12 | Alle via Teilen-Link/QR | Lesen, Verfügbarkeit eintragen, Aufgaben übernehmen |
| Teilnehmer-Token | 32 | Client-seitig erzeugt (LocalStorage) | Wiedererkennung ohne Konto |

Tokens entstehen im `booted()`-Hook (`static::creating`) des `Event`-Models.

**Preis dieser Entscheidung:** „Meine Events" existiert nur im LocalStorage, und
Retention-Warnungen erreichen nur Organisatoren, die freiwillig eine E-Mail
hinterlegt haben (Manage-Screen, „Verwaltungslink sichern").

**Der `manage_token` darf niemals über eine Public-Route ausgeliefert werden.**
`EventPresenter::forPublic()` baut die JSON-Shape deshalb explizit auf, statt das
Model zu serialisieren. Ein Test wacht darüber
(`ParticipationTest::test_the_public_response_never_contains_the_manage_token`).

---

## Datenmodell

```
events            id, public_token(12), manage_token(64), title, description,
                  location, event_type, planning_template, status, mode,
                  timezone, decided_option_id, participant_count_hint, ai_meta,
                  organizer_email, organizer_name, creator_ip,
                  last_activity_at, delete_after, retention_warned_at

participants      id, event_id, display_name, email(nullable), token(32),
                  is_required, is_organizer
                  UNIQUE(event_id, token)

date_options      id, event_id, starts_at_utc, ends_at_utc(nullable), day,
                  all_day, sort

availabilities    id, date_option_id, participant_id, value(yes|no|maybe)
                  UNIQUE(date_option_id, participant_id)

plan_sections     id, event_id, key, title, sort
tasks             id, event_id, plan_section_id(nullable), title,
                  assignee_participant_id(nullable), status(open|done), sort
mail_notifications id, event_id, recipient_email, type, dedupe_key(UNIQUE),
                  sent_at, error
```

`cascade_on_delete` auf allen Foreign Keys → ein gelöschtes Event nimmt alles mit.

**`mode`** (`dates` | `list` | `both`) entscheidet, welche Bereiche es überhaupt
gibt. Ein reines `list`-Event startet direkt in `planning` — es gibt keinen
Termin, auf den gewartet werden müsste.

---

## Statusmaschine

```
draft ──► collecting ──► decided ──► planning ──► closed
              │              │           │
              └──────────────┴───────────┴──► cancelled
```

Der Status steuert, welche UI-Bereiche gerendert werden. Damit ist das
UX-Prinzip „jeder Schritt wird erst sichtbar, wenn er benötigt wird" technisch
verankert und nicht nur eine Absichtserklärung.

- `decided → planning` passiert **automatisch** bei der ersten Aktion im
  Planungsbereich (`Event::enterPlanningIfNeeded()`), nie über einen Button.
- `closed` wird beim Aufruf der Manage-Seite ausgewertet
  (`EventManageController::closeIfPast()`), nicht per Cronjob.

---

## Ranking (Spec Abschnitt 5)

`RankingService` bildet die Regeln eins zu eins ab:

```
score = 2 * yes_count + 1 * maybe_count
blocked = mindestens ein required-Teilnehmer hat "no"
```

Sortierung: `blocked` nach unten → `no_count` aufsteigend → `score` absteigend →
`open_count` aufsteigend → Datum aufsteigend.

**Zwei Regeln, die nicht verhandelbar sind:**

1. **Nie eine Quote anzeigen, solange Antworten fehlen.** Nicht „6/8 verfügbar",
   sondern `6 kann · 2 offen`. `CountBar.vue` setzt das um; ein falsches
   Vollständigkeitsgefühl ist der teuerste Fehler in diesem Screen.
2. **Kein Best Match unter dem Quorum.** Erst wenn mindestens die Hälfte der
   Teilnehmer geantwortet hat (`RankingService::BEST_MATCH_QUORUM`), gibt es eine
   Empfehlung — sonst „Noch zu wenige Rückmeldungen."

Blockierte Optionen werden **nach unten sortiert, nicht ausgeblendet** — der
Organisator muss sehen, warum.

Die Reihenfolge kommt immer vom Server (`event.ranking` = Liste von IDs). Das
Frontend sortiert nie selbst nach.

---

## KI-Extraktion

`AiExtractor` → Claude API mit erzwungenem Schema (Tool-Use).
`HeuristicExtractor` → Fallback ohne KI (Monatsnamen, Wochentage, Tageszeit,
Teilnehmerzahl, Event-Typ per Wortliste, DE + EN).

**Die KI ist Beschleuniger, nie Voraussetzung.** Jeder Fehlerpfad — kein Key,
Timeout, Rate Limit, überschrittenes Tagesbudget, ungültiges Schema — endet in
der Heuristik. Nie in einer Fehlerseite. Beide liefern dasselbe Array-Schema,
der Aufrufer merkt keinen Unterschied.

Weitere Regeln:
- Relative Zeitangaben lösen immer auf das **nächste zukünftige** Vorkommen auf
  („im September" im Oktober 2026 heißt September 2027).
- Modell-Output wird nie blind übernommen: `AiExtractor::normalize()` prüft gegen
  die Heuristik, begrenzt den Zeitraum auf 8 Wochen und verwirft Vergangenes.
- Felder mit `confidence: low` werden im UI als „geraten" markiert.
- Im MVP arbeitet die KI **ausschließlich vorschlagend** — kein Schreibzugriff
  ohne Bestätigung, keine Selbst-Zuweisung von Aufgaben.

Ohne `ANTHROPIC_API_KEY` in der `.env` läuft die App vollständig — nur mit
etwas gröberer Extraktion.

---

## Routen

```
GET  /                         → Landing.vue
POST /events                   → EventController@store (throttle 20/min)

# Organisator — {event} über manage_token (Binding in AppServiceProvider)
GET    /e/{event}              → Manage.vue
GET    /e/{event}/data         → JSON (Live-Polling)
PATCH  /e/{event}              → Felder ändern
DELETE /e/{event}              → Event löschen
POST   /e/{event}/options            PATCH/DELETE /e/{event}/options/{option}
POST   /e/{event}/options/suggest    → Vorschläge aus Zeitraum erzeugen
POST   /e/{event}/decide | /undecide | /cancel | /reopen
PATCH/DELETE /e/{event}/participants/{participant}
POST   /e/{event}/participants/{participant}/merge
POST   /e/{event}/sections           PATCH/DELETE /e/{event}/sections/{section}
POST   /e/{event}/tasks | /tasks/adopt   PATCH/DELETE /e/{event}/tasks/{task}
POST   /e/{event}/invite (10/min) | /email (5/min)
GET    /e/{event}/event.ics

# Teilnehmer — {event} über public_token
GET  /t/{event}                → Public.vue
GET  /t/{event}/state          → JSON (Live-Polling)
POST /t/{event}/join (20/min) | /availability (60/min) | /leave (10/min)
POST/PATCH/DELETE /t/{event}/tasks[/{task}]
GET  /t/{event}/event.ics
```

**Ein `{event}`-Parameter, zwei Schlüssel:** Das Route-Binding in
`AppServiceProvider` löst in `public.*`-Routen über `public_token` auf, sonst
über `manage_token`. Eine Public-URL kann so niemals in den Organisator-Bereich
führen.

**Alle axios-Endpunkte geben JSON zurück, nie `redirect()->back()`** — das
verursacht sonst Redirect-Loops mit Inertia.

---

## Frontend-Struktur

```
resources/js/
  app.js                       # Inertia-Setup, i18n, X-App-Locale-Header
  bootstrap.js                 # axios + CSRF
  i18n/index.js                # Spracherkennung, setLocale
  i18n/locales/{de,en,fr,es,nl}.json
  composables/
    useDeviceToken.js          # Teilnehmer-Token (LocalStorage)
    useMyEvents.js             # „Deine Events" auf der Landing
    useDateFormat.js           # Anzeige in Betrachter-Zeitzone, Zeitzonen-Hinweis
  Components/
    Logo.vue                   # Inline-SVG, Name aus APP_NAME
    Footer.vue                 # powered-by auf der Public-Seite
    LanguageSwitcher.vue       # Flaggen-Dropdown, kein natives <select>
    ShareBox.vue               # Link + Copy + Web-Share + QR
    EventFieldRow.vue          # Editierbare Feldzeile aus der Extraktion
    DateOptionsPanel.vue       # Terminvorschläge, Ranking, Bestätigung
    ParticipantsPanel.vue      # Umbenennen, Pflicht, Merge, Einladen
    PlanPanel.vue              # Sektionen, Aufgaben, Vorschläge (Manage + Public)
    AvailabilityButtons.vue    # ✓ / ? / ✕ + „offen" durch erneutes Klicken
    CountBar.vue               # Balken + Zahlen, nie eine Quote
    ConfirmModal.vue           # statt window.confirm()
    Toast.vue
  Pages/
    Landing.vue
    Event/Manage.vue           # Organisator
    Event/Public.vue           # Teilnehmer
    Legal/{Imprint,Privacy}.vue
```

---

## Design-Tokens (`resources/css/app.css`, Tailwind v4 `@theme`)

```css
--color-pl-ink:         #1f2430  /* Primärfarbe, Text + Buttons */
--color-pl-muted:       #77808f
--color-pl-line:        #e5e7eb
--color-pl-bg:          #faf9f7
--color-pl-surface:     #ffffff
--color-pl-accent:      #0f9d76  /* Akzent = „kann" */
--color-pl-accent-dark: #0b7d5d
--color-pl-accent-soft: #e7f4ef
--color-pl-maybe:       #c2810c
--color-pl-no:          #b8394a
--color-pl-open:        #9aa3b0  /* „offen" — bewusst neutral, nie rot */

--font-display: 'Outfit'   /* Headlines */
--font-body:    'Inter'
--font-mono:    'IBM Plex Mono'  /* Zahlen */
```

Farben immer als `var(--color-pl-*)`, nie hartcodiert (Ausnahme: Print-Block,
dort sind CSS-Variablen nicht zuverlässig verfügbar).

---

## Zeit & Zeitzonen

- **Alles in UTC speichern.** `Event.timezone` als IANA-Kennung, Default aus der
  Browser-Zeitzone des Organisators (serverseitig gegen
  `timezone_identifiers_list()` geprüft — die Browser-Angabe ist ein Vorschlag,
  kein Vertrauensanker).
- Anzeige immer in der Zeitzone des Betrachters.
- Weicht sie ab, blendet `timezoneNote()` einen Hinweis ein — aber nur, wenn
  wirklich eine andere Uhrzeit herauskommt, sonst ist der Hinweis Rauschen.
- **Ganztägige Optionen (`all_day`) werden nie umgerechnet** — sie sind ein
  reines Datum.

---

## i18n-Konventionen

- 5 Sprachen: `de`, `en`, `fr`, `es`, `nl`. Frontend: `resources/js/i18n/locales/*.json`.
  Backend (Mails, Planungs-Templates): `lang/{de,en,fr,es,nl}/{mail,planning}.php`.
- Der Client schickt seine Sprache als `X-App-Locale`-Header mit; `SetLocale`
  koppelt daran die Backend-Locale, damit Mails in der richtigen Sprache
  herausgehen.
- Keine URL-Präfixe (`/fr/...`) — Links bleiben sprachunabhängig teilbar.
- **`@` in Übersetzungsstrings löst den vue-i18n-„Linked Message"-Parser aus**
  und wirft `SyntaxError: Invalid linked format` — die Seite bleibt dann weiß.
  E-Mail-Platzhalter sind deshalb als `deine{'@'}mail.de` escaped.
- Nach jeder Änderung an Locale-Dateien alle 5 auf Key-Konsistenz prüfen:

```bash
python3 -c "
import json
def flatten(d,p=''):
    s=set()
    for k,v in d.items():
        f=f'{p}.{k}' if p else k
        s |= flatten(v,f) if isinstance(v,dict) else {f}
    return s
base=flatten(json.load(open('resources/js/i18n/locales/de.json')))
for f in ['en','fr','es','nl']:
    diff=base ^ flatten(json.load(open(f'resources/js/i18n/locales/{f}.json')))
    print(f, 'ABWEICHUNG:'+str(diff) if diff else 'OK')
"
```

---

## Wichtige Implementierungs-Details

### Live-Polling
Manage pollt `/e/{token}/data`, Public pollt `/t/{token}/state` — beide alle 6s.
Das Polling pausiert, solange ein Feld fokussiert ist (`editing`), sonst
überschreibt der Refresh ungespeicherte Eingaben. Auf der Public-Seite pausiert
es zusätzlich bei ungespeicherten Antworten (`dirty`).

**Ausnahme:** Der erste `/state`-Aufruf beim Mount läuft mit `force = true` und
ignoriert `document.hidden`. Sonst sieht ein wiederkehrender Teilnehmer im
Hintergrund-Tab das Eintragen-Formular und legt sich womöglich doppelt an.

### Teilnehmer-Wiedererkennung
Der Geräte-Token liegt im LocalStorage und kann beim Server-Rendering nicht
mitgeschickt werden. `Public.vue` hält deshalb ein `resolving`-Flag: das
Eintragen-Formular erscheint erst, wenn der erste `/state`-Aufruf (mit
`X-Participant-Token`-Header) geantwortet hat.

Zweitgerät ohne Token → neuer Teilnehmer. Der Organisator kann zusammenführen
(`ParticipantController::merge`): Antworten und Aufgaben wandern zum Ziel, bei
Konflikten gewinnt das Ziel.

### Verfügbarkeit
- **Default für unbeantwortete Optionen ist „offen", nie „kann nicht".** Offene
  Antworten werden getrennt gezählt.
- Erneutes Klicken auf denselben Button setzt zurück auf „offen".
- Nur geänderte Optionen werden gesendet; `null` löscht die Antwort.

### Aufgaben-Autorisierung
`TaskController` unterscheidet Manage- und Public-Kontext am Routen-Namen
(`manage.*`). Im Public-Kontext gilt: nur die eigene Zuweisung, nur freie
Aufgaben übernehmen, nur selbst übernommene löschen. Zuweisung immer nur an
Teilnehmer **desselben** Events.

### Planungs-Vorschläge
`config/planning.php` mappt Template → Sektionen → Task-Keys, die Titel kommen
aus `lang/{locale}/planning.php`. `PlanBuilder::suggestionsFor()` filtert
Vorschläge heraus, die bereits als Aufgabe existieren — „Übernehmen" legt nie
doppelt an. Das Löschen einer Sektion **behält ihre Aufgaben** (sie rutschen in
den sektionslosen Bereich); eine Überschrift zu entfernen darf keine Arbeit
vernichten.

### Mails
`EventNotifier` mit Dedupe-Key pro `(Typ, Event, Empfänger)` in
`mail_notifications` — Retries erzeugen keine Doppelmails. Ausnahme: der
Verwaltungslink bekommt einen zeitbasierten Key, weil er bei Gerätewechsel
mehrfach anforderbar sein muss.

### Anti-Spam & Kosten
- Honeypot-Feld `website` auf Landing und Public-Join.
- Rate-Limits: Event erstellen 20/min, Join 20/min, Verfügbarkeit 60/min,
  Einladungen 10/min, Verwaltungslink 5/min.
- Eingabelänge hart auf 500 Zeichen begrenzt.
- Globales KI-Tagesbudget (`AI_DAILY_BUDGET`) als harter Cutoff → bei
  Überschreitung greift der Fallback, kein Fehler.

### Retention
`events.delete_after` = `last_activity_at + 12 Monate`, jede schreibende Aktion
verlängert (`Event::touchActivity()`). Ein täglicher Scheduled Task in
`routes/console.php` löscht abgelaufene Events; Cascade-Delete räumt den Rest.

---

## Lokale Entwicklung

```bash
docker compose up -d          # PostgreSQL auf Port 5433
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate

composer dev                  # Laravel + Queue + Logs + Vite (Port 8080)
```

App: **http://localhost:8080**

```bash
php artisan test              # läuft gegen die DB plandu_test (echtes Postgres)
npm run build
```

**Tests laufen bewusst gegen PostgreSQL, nicht gegen SQLite** — `timestampTz`,
JSON-Casts und Unique-Constraints verhalten sich sonst anders als in Produktion.
Die Testdatenbank einmalig anlegen:

```bash
docker exec plandu-pgsql psql -U plandu -d plandu -c "CREATE DATABASE plandu_test OWNER plandu;"
```

---

## Deployment

```bash
bash deploy.sh                # erkennt Erstinstallation vs. Update
```

`.github/workflows/deploy.yml` löst das bei jedem Push auf `main` per SSH aus
(Secrets: `SSH_HOST`, `SSH_USER`, `SSH_KEY`, `SSH_PORT`).
Vor dem ersten Deploy in `deploy.sh` anpassen: `APP_DIR`, `REPO_URL`,
`QUEUE_WORKER_NAME`.

Produktions-`.env`:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<domain>
DB_CONNECTION=pgsql
```

Backups: `backup.sh` (täglich per Cron nach Cloudflare R2, 30 Tage
Aufbewahrung), Wiederherstellung mit `restore.sh`.

---

## Datei-Konventionen

- **Controller:** immer JSON bei axios-Calls, nie `redirect()->back()`.
- **Vue:** ausschließlich `<script setup>`, kein Options API.
- **Imports:** `ref`, `computed`, `onMounted` etc. immer explizit importieren.
- **Farben:** CSS-Variablen statt Hex-Werte.
- **Texte:** alle sichtbaren Strings über `t()` — keine hartcodierten deutschen
  Strings in Templates.
- **Bestätigungen:** `ConfirmModal` statt `window.confirm()`.
- **Brand-Name:** nur über `APP_NAME`/`appName`, nie im Code.

---

## Offene Punkte

- [ ] Endgültigen Namen + Domain festlegen → `APP_NAME`, `APP_URL`, `deploy.sh`
- [ ] Impressum und Datenschutzerklärung befüllen
      (`resources/js/Pages/Legal/*.vue` sind derzeit Platzhalter)
- [ ] Juristische Prüfung: Double-Opt-in für die transaktionalen Mails,
      Verzeichnis von Verarbeitungstätigkeiten (Spec Abschnitt 8)
- [ ] Erinnerungs-Mail 24h vorher (Spec: bewusst hinter der Cut-Line)
- [ ] Retention-Warnmail 14 Tage vor Löschung (`retention_warned_at` ist bereits
      im Schema, der Versand fehlt noch)
- [ ] og-image + Favicon-Set (aktuell nur `favicon.svg`)
