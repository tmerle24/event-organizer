# ORGDATE – CLAUDE.md

Architektur, Konventionen und Entscheidungen dieses Projekts für Claude Code.

**Marke:** ORGDATE · **Domain:** orgdate.com · **Kontakt:** hello@orgdate.com

Der verbindliche Brand Guide liegt als [BRANDING.md](BRANDING.md) im Repo. Alles
zu Logo, Farbe, Typografie und Tonalität kommt von dort — bei Widersprüchen
gewinnt der Guide, nicht dieser Text.

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
| Datenbank | **PostgreSQL 16** (lokal via `docker compose`, Container `orgdate-pgsql`, Port **5434**) |
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

**Port 5434 statt 5432:** Auf der Entwicklungsmaschine belegt `namibway-pgsql`
den Standard-Port. **5433 ist ebenfalls tabu** — darüber laufen SSH-Tunnel zu
Produktionsdatenbanken (`ssh -L 5433:127.0.0.1:5432 …`). Ein Container auf 5433
nimmt dem Tunnel den Port weg; SSH bricht deswegen nicht ab, sondern warnt nur
einmal und läuft weiter, sodass der Client unbemerkt auf der lokalen Datenbank
landet. Das sieht dann nach `password authentication failed` vom Server aus.

**Eine Schrift:** Outfit für alles (300/400/500/600). **Bold 700 wird nicht
benutzt** — zu laut für den Markencharakter.

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
    AvailabilityButtons.vue    # ✓ / ~ / ✕ + „offen" durch erneutes Klicken
    CountBar.vue               # Balken + Zahlen, nie eine Quote; Mint nur bei „passt allen"
    ConfirmModal.vue           # statt window.confirm()
    Toast.vue
  Pages/
    Landing.vue
    Event/Manage.vue           # Organisator
    Event/Public.vue           # Teilnehmer
    Legal/{Imprint,Privacy}.vue
```

---

## Design-Tokens (`resources/css/app.css`)

Aus dem Brand Guide, Abschnitt 4.1. Die Tokens stehen in `:root`, nicht in
`@theme` — im Code wird durchgängig `var(--od-*)` benutzt, nie ein Hex-Wert
(Ausnahme: der Print-Block, dort sind CSS-Variablen nicht zuverlässig da).

```css
--od-violet:      #5B4BE8   /* Primär: Buttons, Links, Aktivzustände, Ja-Stimmen */
--od-violet-dark: #3A2CB8   /* Hover, Pressed */
--od-violet-soft: #AFA9EC   /* Sekundäre Marker, "vielleicht" */
--od-mint:        #16D6A4   /* "Passt allen" — NUR der gefundene Termin */
--od-mint-tint:   #EAFBF3
--od-apricot:     #FFB25C   /* Bestätigung, Feiermoment */
--od-sand:        #FFF4E6
--od-ink:         #14122B
--od-slate:       #6E6B85   /* Metainfos, Hinweise, auch Fehlerzustände */
--od-mist:        #F2F1FA
--od-white:       #FFFFFF
--od-line:        #E4E2F2   /* abgeleitet: Borders */
--od-violet-tint: #EEECFC   /* abgeleitet: Badge-/Aktivflächen */
```

`--od-line` und `--od-violet-tint` nennt der Guide nicht; beide bleiben in der
Violett-Familie und sind als abgeleitet im CSS kommentiert.

**Drei Farbregeln, die nicht verhandelbar sind:**

1. **Kein Rot im System.** Fehler, offene Punkte, Absagen und Löschaktionen
   laufen in Slate. Es gibt in ORGDATE nichts, was man falsch machen kann.
2. **Mint ist reserviert.** Es markiert ausschließlich den Termin, der allen
   passt — im Logo wie im UI. Taucht Mint anderswo auf, verliert das Signal
   seine Bedeutung. Die Antwort-Buttons laufen deshalb komplett in Violett.
3. **Apricot nur nach einer Bestätigung**, und nur kurz. Danach kehrt das UI
   zur ruhigen Grundstimmung zurück.

**Typo-Klassen** statt Tailwind-Stufen: `.od-display` (40/1.15/600), `.od-h1`
(28/1.25/500), `.od-h2`, `.od-h3`, `.od-body`, `.od-small`, `.od-meta`.
`.od-measure` begrenzt Fließtext auf 68 Zeichen.

**Bausteine:** `.od-card`, `.od-panel`, `.od-input`, `.od-btn` mit
`.od-btn-primary` / `.od-btn-ghost` / `.od-btn-quiet`. Radien: `--od-radius-sm`
10px (Buttons/Inputs), `-md` 12px (Zeilen), `-lg` 16px (Karten), `-xl` 28px.

**Eine Primäraktion pro Screen.** Im Terminscreen bekommt nur die Zeile, die
allen passt, den gefüllten Button (`isPrimaryChoice()` in `DateOptionsPanel`);
alles andere ist Ghost oder Quiet.

---

## Logo

`Components/Logo.vue` zeichnet das Symbol „The Common Point" inline: ein
Reuleaux-Dreieck aus drei Kreisbögen mit gesetztem Mittelpunkt. Die Geometrie
ist exakt aus Brand Guide 2.2 übernommen (Bogenradius 60, Strichstärke 16,
Punkt bei cx 50 / cy 54 / r 9.5) und darf nicht gedreht oder verzerrt werden.

Props: `variant` (`horizontal` | `stacked` | `symbol`), `size` (`sm`/`md`/`lg`),
`inverse` (Negativ auf Violett), `open` (ein Bogen hell — Einladungszustand).

Die Wortmarke steht immer in Versalien, Outfit 600, Tracking +0.05em. Nie in
Gemischtsatz, nie kursiv, nie in einer anderen Schrift.

**Assets** unter `brand/` — alle vom Design geliefert, nicht hier abgeleitet.
Die drei `make_*.py` sind die Quellen; wer etwas ändert, ändert sie und
generiert neu, statt einzelne SVGs von Hand zu editieren.

```
brand/
  logo/     Symbol (normal, weiß, mono, offen, klein) + Lockups horizontal
            und gestapelt, je normal/invers/mono · make_logos.py
  icon/     Favicons, App-Icons 16…1024, maskable, light, transparent,
            favicon.ico · make_icons.py · README.md
  og/       Share-Bilder als SVG-Quelle · make_og.py · README.md
  tokens/   colors.css, tailwind.colors.js
```

**Die Wortmarke liegt in allen SVGs als Pfad vor**, keine Schriftdatei nötig.
Im Web rendert `Logo.vue` sie dagegen live in Outfit — das spart rund 5 KB
gegenüber dem Pfad.

`Logo.vue` hält exakt dieselben Maße wie `make_logos.py`:

| | Wert |
|---|---|
| viewBox | `12 12 76 76` — die sichtbare Form, nicht das 100er-Konstruktionsraster |
| Schriftgrad horizontal | 52/76 der sichtbaren Symbolhöhe |
| Schriftgrad gestapelt | 34/76 |
| Abstand horizontal | halbe Symbolbreite |
| Abstand gestapelt | ein Viertel der Symbolhöhe |
| Strichstärke | 16, unter 24px 17 (sonst läuft der Ring beim Rastern zu) |
| Punktradius | 9.5, unter 24px 10 |

`orgdate-symbol-confirmed.svg` ist die einzige handgepflegte Datei: Abschnitt 3
des Guides nennt den Zustand „bestätigt", das gelieferte Set enthält ihn nicht.
Gehört bei nächster Gelegenheit in `make_logos.py`.

**Icons sind pro Größe gezeichnet, nicht herunterskaliert** — Strichstärke und
Punktradius wachsen zu kleinen Größen hin (Tabelle in `brand/icon/README.md`).
Neu skalieren aus einer großen Datei macht das kaputt.

`apple-touch-icon.png`, `icon-1024.png` und die Maskable-Icons sind bewusst
**unrund**: iOS, die Stores und Android legen ihre eigene Maske darüber, eine
mitgelieferte Rundung würde doppelt beschnitten.

Ausgeliefert wird aus `public/icon/`; `favicon.ico` liegt zusätzlich im
Wurzelverzeichnis, weil Browser den Pfad auch ohne `<link>` anfragen.

Zum Neuerzeugen brauchen die Skripte `cairosvg`, `pillow`, `fonttools` und
`fonts/Outfit.ttf` (Variable Font).

---

## Link-Vorschau

`public/og/og-image.png` (1200×630) hängt in `app.blade.php` an jeder Seite.
Bedingungen, unter denen die Vorschau still bricht — alle vier hängen an
Bedingungen, die man beim Umbauen leicht verletzt, deshalb stehen sie in
`tests/Feature/ShareImageTest.php`:

- **Absolute URL**, sonst ignoriert der WhatsApp-Crawler das Bild
- **Unter 600 KB** (aktuell 29 KB), sonst bleibt die Vorschau leer
- **PNG**, kein WebP oder SVG
- **Serverseitig gerendert** — der Crawler führt kein JavaScript aus

Der Vorschautext (`brand.share_text`) ist deckungsgleich mit der Zeile im Bild,
damit Bild und Text dasselbe sagen. Beim Testen einen Query-Parameter anhängen
(`?v=2`), WhatsApp cached die Vorschau pro URL sehr lange.

**Event-Seiten zeigen Titel und Termin** (`SharePreview`, per
`withViewData()` an das Blade-Root gereicht). Wer den Link in einen Gruppenchat
bekommt, weiß damit sofort Bescheid, ohne zu klicken:

| Zustand | Vorschautext |
|---|---|
| Termin steht | `Freitag, 4. September 2026, 18:00 · Im Garten` |
| noch offen | `3 Termine stehen zur Wahl. Trag ein, wann du kannst.` |
| abgesagt / vorbei | entsprechender Hinweis |

Der erste Stand war hier bewusst anders: generische Marken-Vorschau, damit
Vorschau-Bots nichts über das Event verraten. Der Nutzen für eine Einladung
wiegt schwerer — der Link ist ohnehin der Schlüssel zum Event. Texte in
`lang/{locale}/share.php`.

**Die Manage-Seite bleibt generisch.** Der Verwaltungslink gehört nicht in einen
Gruppenchat; eine Vorschau, die ihn attraktiv macht, hilft niemandem.

Eigene Bilder pro Event wären möglich (`brand/og/README.md` skizziert das
Layout), gehören aber hinter einen Cache und nicht in den Request.

---

## Tonalität

Locker, entlastend, nie belehrend. Duzen. Kurze Sätze. Brand Guide Abschnitt 6:

- **Positiv zählen.** „Eine Rückmeldung ist schon da" statt „5 fehlen noch".
  Der Presenter liefert dafür `answered_count` mit, nicht nur `answers_needed`.
- **Keine Countdowns, Fristen oder Mahnungen.** Unter dem Quorum steht
  „Noch offen – kein Stress.", nicht „zu wenige Rückmeldungen".
- **Ergebnis statt Prozess.** „Passt allen" statt „optimaler Termin",
  „Steht" statt „erfolgreich bestätigt".
- **Kein Fachvokabular** — keine Umfrage-, Polling- oder Scheduling-Begriffe.
- Keine Ausrufezeichen in Systemmeldungen, kein „bitte", kein „erfolgreich".
- Fehler nennen, was passiert ist und was jetzt hilft, in einem Satz:
  „Das hat nicht geklappt. Nochmal versuchen?"

Zahlen werden pluralisiert (vue-i18n `Singular | Plural`, Aufruf
`t(key, count)`), nicht mit „(en)" abgekürzt.

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
- **KI-Grenzen liegen im `AiExtractor`, nicht auf der Route.** Eine
  Route-Drosselung antwortet mit 429 — das Anlegen des Events würde scheitern
  und die Person bekäme einen Fehler zu sehen. Stattdessen fällt nur der
  KI-Aufruf weg und die Heuristik übernimmt:
  - je IP `AI_PER_IP_HOURLY` (5) und `AI_PER_IP_DAILY` (20)
  - global `AI_DAILY_BUDGET` (500) als harter Cutoff
  - `throttle:20,1` bleibt auf der Route, aber gegen das massenhafte Anlegen
    von Events, nicht gegen KI-Kosten
- **Jeder Ausfallpfad ist getestet** (`AiFallbackTest`): kein Key, 500, 429,
  Timeout, Antwort ohne `tool_use`. In allen Fällen entsteht das Event, die
  Heuristik greift, und nichts davon ist für die Person sichtbar.

### Retention
`events.delete_after` = `last_activity_at + 12 Monate`, jede schreibende Aktion
verlängert (`Event::touchActivity()`). Ein täglicher Scheduled Task in
`routes/console.php` löscht abgelaufene Events; Cascade-Delete räumt den Rest.

---

## Lokale Entwicklung

```bash
docker compose up -d          # PostgreSQL auf Port 5434
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate

composer dev                  # Laravel + Queue + Logs + Vite (Port 8080)
```

App: **http://localhost:8080**

```bash
php artisan test              # läuft gegen die DB orgdate_test (echtes Postgres)
npm run build
```

**Tests laufen bewusst gegen PostgreSQL, nicht gegen SQLite** — `timestampTz`,
JSON-Casts und Unique-Constraints verhalten sich sonst anders als in Produktion.
Die Testdatenbank (`orgdate_test`) einmalig anlegen:

```bash
docker exec orgdate-pgsql psql -U orgdate -d orgdate -c "CREATE DATABASE orgdate_test OWNER orgdate;"
```

---

## Deployment

**Erstinstallation** auf einem frischen Ubuntu-Server:

```bash
bash install.sh          # Wizard: Domain, DB, TLS, SMTP, Crons, Firewall
```

**Updates**:

```bash
bash deploy.sh           # --no-npm, --no-migrate
```

Die Aufteilung ist bewusst: `install.sh` macht alles Einmalige (Pakete,
PostgreSQL-Rolle, nginx-vHost, certbot, Supervisor, Cron, ufw) und ruft für den
App-Teil `deploy.sh` auf. Composer, npm, Migrationen und Caches stehen dadurch
**nur an einer Stelle** — sonst laufen Erstinstallation und spätere Deploys
auseinander.

`install.sh` schreibt `.deploy.conf` ins App-Verzeichnis; `deploy.sh` liest sie
und benutzt dieselben Werte. Ohne die Datei greifen die Vorgaben im Skriptkopf.

Details, Pfade und Troubleshooting: [DEPLOYMENT.md](DEPLOYMENT.md).

Ein paar Dinge, die beim Testen im Container aufgefallen sind und im Skript
festgehalten sind, damit sie nicht zurückkommen:

- **`sudo -v` taugt nicht als Vorprüfung.** Hat der Benutzer einen
  NOPASSWD-Eintrag, legt sudo keinen Zeitstempel an und fragt trotzdem nach
  einem Passwort. Stattdessen `sudo -n true || sudo true`.
- **`set -o pipefail` und Zuweisungen aus Pipelines vertragen sich nicht.**
  `X=$(foo | awk ...)` reißt das Skript mit, sobald `foo` fehlt — deshalb
  überall `|| true` dahinter.
- **ufw wird nie ohne SSH-Regel aktiviert.** Das sperrt einen sonst vom eigenen
  Server aus. Lässt die Regel sich nicht anlegen, bleibt die Firewall aus und
  es gibt eine Warnung.
- **APP_KEY entsteht mit `openssl`, nicht mit `artisan key:generate`** — an der
  Stelle gibt es noch kein `vendor/`.
- Firewall, Supervisor und certbot dürfen die Installation **nicht abbrechen**.
  Die App läuft auch ohne sie; ein Abbruch nach 90 % der Arbeit hilft niemandem.

Produktions-`.env`:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://orgdate.com
DB_CONNECTION=pgsql
```

Backups: `backup.sh` (täglich per Cron nach Cloudflare R2, 30 Tage
Aufbewahrung), Wiederherstellung mit `restore.sh`.

---

## Suchmaschinen

Indexierbar sind genau drei Seiten: Start, Impressum, Datenschutz.
**Event-Seiten tragen `noindex`** und stehen zusätzlich in der `robots.txt`
unter `Disallow` — sie sind privat, der Link ist der Schlüssel.

- `/sitemap.xml` ist eine Route, keine Datei: so kommen die Adressen aus
  `APP_URL` statt fest verdrahtet. Sie enthält **nie** Event-URLs — eine
  Sitemap ist öffentlich lesbar, ein Token darin wäre für jeden einsehbar.
  Ein Test hält das fest.
- `public/robots.txt` ist dagegen statisch, weil nginx die Datei direkt
  ausliefert und eine Laravel-Route dort nie ankäme. Die `Sitemap:`-Zeile
  muss eine absolute URL sein und nennt deshalb die Domain im Klartext.
- **Ohne JavaScript ist der `<body>` leer** — Inertia baut die Seite erst im
  Browser auf. Die Startseite hat deshalb einen `<noscript>`-Block mit
  Überschrift und Beschreibung. Beide Texte kommen aus `config/brand.php`,
  denselben Strings, die Titel und Meta-Description füllen — so kann der Block
  nicht von dem abweichen, was Nutzer sehen. Inline-Stile, weil auch das CSS im
  JS-Bundle steckt.
- Der Bestätigungscode der Search Console kommt aus
  `GOOGLE_SITE_VERIFICATION` in der `.env`. Ohne Wert steht kein Meta-Tag im
  HTML, es braucht also keine Datei auf dem Server.

---

## Rechtsseiten

`Pages/Legal/Imprint.vue` und `Privacy.vue` sind bewusst deutschsprachig
(Anbieter sitzt in Deutschland), nur die Überschriften laufen über `t()` — so
wie bei SimpleVoter. Anbieterdaten: Till Merlé, Birkenstr. 19,
61440 Oberursel.

**E-Mail und Telefon werden erst im `onMounted` zusammengesetzt** und stehen
nirgends als fertiger String:

- Nicht im ausgelieferten HTML — deshalb liegt die Adresse **nicht** in den
  geteilten Inertia-Props. Eine geteilte Prop landet im `data-page`-Attribut
  **jeder** Seite und hebelt den Schutz komplett aus.
- Nicht im JS-Bundle — `'hello' + '@' + 'orgdate.com'` würde esbuild beim Build
  wieder zu einem Literal zusammenfalten. Deshalb
  `['hello', 'orgdate.com'].join(String.fromCharCode(64))`.

Nach Änderungen an diesen Seiten gegenprüfen:

```bash
curl -s http://localhost:8080/impressum | grep -c "hello@orgdate"   # muss 0 sein
grep -rl "hello@orgdate\.com" public/build/assets/*.js             # darf nichts finden
```

Der Datenschutztext beschreibt das echte Verhalten der App: Tokens im
LocalStorage statt Login, `creator_ip` zur Missbrauchsprävention, die optionale
Übermittlung des Freitexts an die Claude-API, 12 Monate Retention. Ändert sich
eines dieser Verhalten, muss der Text mitgeändert werden.

---

## Datei-Konventionen

- **Controller:** immer JSON bei axios-Calls, nie `redirect()->back()`.
- **Vue:** ausschließlich `<script setup>`, kein Options API.
- **Imports:** `ref`, `computed`, `onMounted` etc. immer explizit importieren.
- **Farben:** `var(--od-*)` statt Hex-Werte. Mint nie außerhalb des gefundenen
  Termins, nie Rot.
- **Texte:** alle sichtbaren Strings über `t()` — keine hartcodierten deutschen
  Strings in Templates.
- **Bestätigungen:** `ConfirmModal` statt `window.confirm()`.
- **Gewichte:** 300/400/500/600. Kein `font-bold`.
- **Kontakt/Domain:** aus `config/brand.php`, nie hartcodiert im Template — ein
  `@` in einem i18n-String bringt sonst den vue-i18n-Parser zu Fall.

---

## Offene Punkte

- [ ] Juristische Prüfung der Rechtstexte, insbesondere Double-Opt-in für die
      transaktionalen Mails, das Verzeichnis von Verarbeitungstätigkeiten
      (Spec Abschnitt 8) und Abschnitt 5 der Datenschutzerklärung
      (Drittlandübermittlung an Anthropic)
- [ ] Erinnerungs-Mail 24h vorher (Spec: bewusst hinter der Cut-Line)
- [ ] Retention-Warnmail 14 Tage vor Löschung (`retention_warned_at` ist im
      Schema, der Versand fehlt)
- [ ] `orgdate-symbol-confirmed.svg` in `brand/logo/make_logos.py` aufnehmen
      (Guide Abschnitt 3 nennt den Zustand, das gelieferte Set enthält ihn nicht)
- [ ] `safari-pinned-tab.svg` fehlt — verlangt eine einfarbige, strichlose
      Vektorform, der Ring müsste dafür in eine gefüllte Kontur umgewandelt
      werden. Aktuelle Safari-Versionen nutzen stattdessen Manifest und
      Apple-Touch-Icon, deshalb niedrige Priorität.
- [ ] Der Guide nennt in Abschnitt 8 für das Favicon Strichstärke 17 / Punkt 10.
      Bei 16 px reicht das nicht; die geprüfte Tabelle steht in
      `brand/icon/README.md` und sollte in den Guide zurückfließen.
- [ ] DNS/TLS für orgdate.com
