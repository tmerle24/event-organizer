# ORGDATE – Brand Guide

Verbindliche Grundlage für Logo, Farbe, Typografie und Tonalität.
Stand: 2026-08 · Version 1.0

---

## 1. Marke

ORGDATE hilft Gruppen, gemeinsam einen Termin zu finden und daraus ein Event zu machen –
vom BBQ über den Geburtstag bis zu Team-Event, Reise und Wochenende.

**Markencharakter:** modern, freundlich, smart, unkompliziert, gemeinschaftlich, leicht,
vertrauenswürdig.

**Zielgefühl:** Spaß, Vorfreude und vor allem **Erleichterung**. Nie Stress, Druck oder
Verpflichtung. Wer ORGDATE benutzt, soll das Gefühl haben, dass ihm etwas abgenommen wurde.

**Bewusst nicht:** Business-/Enterprise-Optik, Produktivitäts-Ästhetik, klassische
Kalender- oder To-do-App-Sprache.

---

## 2. Logo

### 2.1 Konzept

Das Symbol heißt **„The Common Point"**: ein Dreieck aus drei Kreisbögen (Reuleaux-Form)
mit einem gesetzten Punkt in der Mitte.

- Die drei Bögen = die Beteiligten und ihre Verfügbarkeiten
- Die geschlossene Form = Organisation, Struktur, Verlässlichkeit
- Der Punkt = der gefundene gemeinsame Termin

Kein Kalender, kein Häkchen, kein Map-Pin. Die Reuleaux-Form hat konstante Breite und
wirkt dadurch in jeder Rotation und in jeder Icon-Maske (quadratisch, rund, Squircle)
stabil, ohne dass sie pro Plattform neu gezeichnet werden muss.

### 2.2 Geometrie

Referenz-Koordinatensystem: `viewBox="0 0 100 100"`.

| Element | Wert |
|---|---|
| Eckpunkte | A (50, 20) · B (20, 71.96) · C (80, 71.96) |
| Bogenradius | 60 (jeder Bogen ist um die gegenüberliegende Ecke geschlagen) |
| Strichstärke | 16 |
| `stroke-linejoin` | `round` |
| Mittelpunkt | cx 50 · cy 54 · r 9.5 |

Der Punkt sitzt minimal über dem geometrischen Schwerpunkt (54 statt 54.64) – optischer
Ausgleich für die breite Basis der Form.

### 2.3 Bildmarke (SVG)

```svg
<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="ORGDATE">
  <path d="M50 20A60 60 0 0 0 20 71.96A60 60 0 0 0 80 71.96A60 60 0 0 0 50 20Z"
        fill="none" stroke="#5B4BE8" stroke-width="16" stroke-linejoin="round"/>
  <circle cx="50" cy="54" r="9.5" fill="#16D6A4"/>
</svg>
```

### 2.4 Wortmarke

`ORGDATE` – Versalsatz, Outfit Semibold (600), Tracking ca. `+0.05em`.

Die kreisrunden O und D nehmen die Geometrie des Symbols auf. Versalsatz ohne Serifen
wirkt bestimmt, durch die runden Formen aber nicht steif.

Die Wortmarke wird **nie** in Gemischtsatz („Orgdate"), nie kursiv und nie in einer
anderen Schrift gesetzt.

### 2.5 Lockups

| Variante | Einsatz |
|---|---|
| Horizontal (Symbol links, Wortmarke rechts) | Standard: Header, Landingpage, Signaturen |
| Gestapelt (Symbol über Wortmarke) | Enge/quadratische Formate, Social-Profile, Print |
| Bildmarke solo | App-Icon, Favicon, App-Header, Avatar |
| Negativ auf Primärfarbe | Farbflächen, Werbemittel |
| Einfarbig weiß/schwarz | Gravur, Stempel, einfarbiger Druck |

**Horizontales Lockup:** Symbolmitte und optische Mitte der Versalhöhe liegen auf einer
Linie. Abstand Symbol → Wortmarke = halbe Symbolbreite.

**Gestapeltes Lockup:** Abstand Symbol → Wortmarke = ein Drittel der Symbolhöhe,
Wortmarke zentriert.

### 2.6 Schutzraum und Mindestgrößen

- **Schutzraum:** rundum mindestens die Höhe des Mittelpunkts (≈ ein Viertel der
  Symbolhöhe). Innerhalb dieser Zone stehen keine anderen Elemente, kein Text, keine
  Bildkanten.
- **Bildmarke:** ab 16 px Höhe.
- **Horizontales Lockup:** ab 96 px Breite. Darunter auf gestapelt oder Solo-Symbol wechseln.
- **Print:** Bildmarke ab 6 mm, horizontales Lockup ab 28 mm Breite.

### 2.7 Don'ts

- Symbol drehen, spiegeln oder verzerren
- Wortmarke ohne Versalien setzen oder das Tracking verändern
- Verläufe, Schatten, Outlines oder Glow auf Logo oder Symbol
- Punkt entfernen, verschieben oder in einer anderen Farbe als Mint setzen
  (Ausnahme: einfarbige Anwendungen)
- Logo auf unruhigem Foto ohne abdunkelnde Fläche platzieren
- Symbol und Wortmarke frei neu anordnen; nur die definierten Lockups verwenden

---

## 3. Zustandsvarianten des Symbols

Die Bildmarke ist nicht nur Logo, sondern Bedeutungsträger im Produkt.

| Variante | Bedeutung | Umsetzung |
|---|---|---|
| Geschlossen, Mint-Punkt | Standard, Markenauftritt | wie oben |
| Offen (ein Bogen unterbrochen, hell) | Einladung, Onboarding, noch nicht entschieden | oberer rechter Bogen in `#AFA9EC`, `stroke-linecap="round"` |
| Bestätigt | Der Termin steht – Moment der Erleichterung | Punkt wächst auf r 16, Farbe `#FFB25C`, Fläche `#FFF4E6` |

Der Mint-Punkt markiert im gesamten Produkt den Termin, der für alle passt. Diese
Kopplung zwischen Logo und UI-Signal wird nicht aufgeweicht.

---

## 4. Farbwelt

| Rolle | Name | Hex | Einsatz |
|---|---|---|---|
| Primär | Signal Violet | `#5B4BE8` | Symbol, Buttons, Links, Aktivzustände |
| Primär dunkel | Deep Violet | `#3A2CB8` | Hover, Pressed, Text auf hellen Violettflächen |
| Primär hell | Soft Violet | `#AFA9EC` | Inaktive Bögen, Avatare, sekundäre Marker |
| Akzent | Fresh Mint | `#16D6A4` | „Passt allen" – ausschließlich für den gefundenen Termin |
| Akzent hell | Mint Tint | `#EAFBF3` | Fläche des bestätigten Termins |
| Freude | Sunny Apricot | `#FFB25C` | Bestätigung, Erinnerung, Feiermoment |
| Freude hell | Warm Sand | `#FFF4E6` | Fläche im Bestätigungszustand |
| Text | Midnight Ink | `#14122B` | Überschriften, Fließtext |
| Text sekundär | Slate | `#6E6B85` | Metainformationen, Hinweise, auch Fehlerzustände |
| Fläche | Mist | `#F2F1FA` | Sektionshintergründe, Karten |
| Fläche | White | `#FFFFFF` | Basis |

**Regeln:**

- **Kein Rot im System.** Fehler, offene Punkte und Warnungen laufen in Slate, nicht in
  Alarmfarbe. Es gibt in ORGDATE nichts, was man falsch machen kann.
- Mint ist reserviert. Wenn Mint überall auftaucht, verliert der gefundene Termin sein Signal.
- Apricot erscheint nur nach einer Bestätigung und nur kurz. Danach kehrt das UI zur
  ruhigen Grundstimmung zurück.
- Violett bleibt Primärfarbe. Mint wird nie zur Primärfarbe.

### 4.1 CSS Custom Properties

```css
:root {
  --od-violet: #5B4BE8;
  --od-violet-dark: #3A2CB8;
  --od-violet-soft: #AFA9EC;
  --od-mint: #16D6A4;
  --od-mint-tint: #EAFBF3;
  --od-apricot: #FFB25C;
  --od-sand: #FFF4E6;
  --od-ink: #14122B;
  --od-slate: #6E6B85;
  --od-mist: #F2F1FA;
  --od-white: #FFFFFF;

  --od-radius-sm: 10px;
  --od-radius-md: 12px;
  --od-radius-lg: 16px;
  --od-radius-xl: 28px;
}
```

### 4.2 Tailwind

```js
// tailwind.config.js
theme: {
  extend: {
    colors: {
      violet: { DEFAULT: '#5B4BE8', dark: '#3A2CB8', soft: '#AFA9EC' },
      mint:   { DEFAULT: '#16D6A4', tint: '#EAFBF3' },
      apricot:{ DEFAULT: '#FFB25C', sand: '#FFF4E6' },
      ink:    '#14122B',
      slate:  '#6E6B85',
      mist:   '#F2F1FA',
    },
    fontFamily: { sans: ['Outfit', 'system-ui', 'sans-serif'] },
    borderRadius: { card: '12px', panel: '16px', sheet: '28px' },
  },
}
```

---

## 5. Typografie

**Outfit** – geometrische Grotesk, Google Fonts.

```html
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap">
```

| Schnitt | Einsatz |
|---|---|
| Light 300 | Große Zitate, Hero-Subline |
| Regular 400 | Fließtext |
| Medium 500 | Überschriften, Buttons, Labels |
| Semibold 600 | Wortmarke, Hero-Headline |

Bold 700 wird nicht verwendet – zu laut für den Markencharakter.

### Typo-Skala

| Rolle | Größe / Zeilenhöhe | Schnitt |
|---|---|---|
| Display | 40 / 1.15 | 600 |
| H1 | 28 / 1.25 | 500 |
| H2 | 22 / 1.3 | 500 |
| H3 | 18 / 1.4 | 500 |
| Body | 16 / 1.6 | 400 |
| Small | 14 / 1.5 | 400 |
| Meta | 13 / 1.4 | 400, Slate |

Fließtext maximal 68 Zeichen pro Zeile.

---

## 6. Tonalität

Locker, entlastend, nie belehrend. Duzen. Kurze Sätze.

- **Positiv zählen.** „4 haben schon geantwortet" statt „2 fehlen noch".
- **Keine Countdowns, keine Fristen, keine Mahnungen.** Erinnerungen sind Angebote,
  keine Aufforderungen.
- **Ergebnis statt Prozess.** „Passt allen" statt „Optimaler Termin ermittelt".
  „Steht. Mehr musst du nicht tun." statt „Termin erfolgreich bestätigt".
- **Kein Fachvokabular.** Keine Umfrage-, Polling- oder Scheduling-Begriffe.
- Keine Ausrufezeichen in Systemmeldungen, kein „bitte", kein „erfolgreich".
- Fehler: sagen was passiert ist und was jetzt hilft, in einem Satz, ohne Schuldzuweisung.

**Beispiele**

| Statt | Besser |
|---|---|
| Termin erfolgreich bestätigt! | Steht. Ich sag den anderen Bescheid. |
| 2 von 6 Teilnehmern haben noch nicht abgestimmt | 4 haben schon geantwortet |
| Abstimmung endet in 2 Tagen | Noch offen – kein Stress |
| Fehler: Einladung konnte nicht gesendet werden | Die Einladung ist nicht rausgegangen. Nochmal versuchen? |

---

## 7. UI-Prinzipien

- **Runde Formen, viel Luft.** Radien ab 10 px, Karten 12 px, Sheets 28 px. Keine scharfen Kanten.
- **Eine Primäraktion pro Screen.** Alles andere ist sekundär oder Ghost.
- **Der Mint-Punkt ist das einzige starke Signal.** Nicht mehrere konkurrierende Highlights.
- **Keine dichten Listen.** Zeilenabstand und Padding großzügig, lieber weniger auf einmal zeigen.
- **Der Bestätigungsmoment darf kurz feiern:** Punkt wächst, Fläche wird warm, ein Satz
  Entlastung. Danach wieder ruhig.
- Avatare als einfache Kreise in Violett-Abstufungen, überlappend, maximal drei plus Zähler.

**Bildsprache:** echte Menschen in echten Situationen, viel Luft, warmes Tageslicht.
Keine Stockfoto-Meetings, keine Screenshots von Kalendern, keine Business-Settings.

---

## 8. App-Icon

- Grundform: Squircle, Hintergrund Signal Violet `#5B4BE8`
- Bildmarke weiß, Punkt in Fresh Mint
- Symbolbreite = 66 % der Icon-Breite
- Helle Alternative: weißer Grund, Symbol in Violett, Punkt in Mint

**Benötigte Größen:** 1024, 512, 192, 180, 167, 152, 120, 76, 64, 48, 32, 16.

**Maskable Icon (PWA):** Symbol auf 52 % der Canvas-Breite verkleinern, damit es
innerhalb der Safe Zone von 80 % liegt.

```json
{
  "name": "ORGDATE",
  "short_name": "ORGDATE",
  "theme_color": "#5B4BE8",
  "background_color": "#FFFFFF",
  "icons": [
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" },
    { "src": "/icons/icon-maskable-512.png", "sizes": "512x512",
      "type": "image/png", "purpose": "maskable" }
  ]
}
```

---

## 9. Assets

```
brand/
├── logo/
│   ├── orgdate-logo-horizontal.svg
│   ├── orgdate-logo-stacked.svg
│   ├── orgdate-symbol.svg
│   ├── orgdate-symbol-open.svg
│   ├── orgdate-logo-inverse.svg
│   └── orgdate-logo-mono.svg
├── icon/
│   ├── icon-{16,32,48,64,76,120,152,167,180,192,512,1024}.png
│   ├── icon-maskable-512.png
│   └── favicon.ico
└── tokens/
    ├── colors.css
    └── tailwind.colors.js
```

Alle Logo-Dateien als SVG mit `currentColor`-freien, festen Hexwerten, ohne
eingebettete Schrift – die Wortmarke liegt als Pfad vor, damit sie ohne geladene
Schriftdatei korrekt rendert.

---

## 10. Kurzcheck vor dem Release

- [ ] Logo hat Schutzraum, ist nicht verzerrt, Punkt ist Mint
- [ ] Nur eine Primäraktion pro Screen
- [ ] Kein Rot, keine Countdowns, keine Mahnungen im Text
- [ ] Mint nur beim gefundenen Termin
- [ ] Positiv formuliert und geduzt
- [ ] Kontrast: Text auf Violett und auf Mint-Tint geprüft (WCAG AA)
- [ ] Symbol bei 16 px noch lesbar
