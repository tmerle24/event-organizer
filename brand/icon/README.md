# ORGDATE – Icons

Favicon- und App-Icon-Set. Alle Dateien sind einzeln gezeichnet, nicht herunterskaliert.

## Dateien

| Datei | Einsatz |
|---|---|
| `favicon.ico` | Klassisches Favicon, enthält 16 · 32 · 48 als eigene Ebenen |
| `favicon.svg` | Vektor-Favicon für moderne Browser |
| `favicon-16.png` `-32` `-48` | Einzelne PNG-Fallbacks |
| `apple-touch-icon.png` | 180 × 180, iOS Homescreen, ohne Rundung |
| `icon-{16,32,48,64,76,120,152,167,192,512}.png` | Allgemeines Set, abgerundete Kachel |
| `icon-1024.png` | App Store und Play Store, quadratisch, ohne Transparenz |
| `icon-maskable-{192,512}.png` | Android adaptive icons, `purpose: maskable` |
| `icon-light-{192,512}.png` | Helle Alternative, weißer Grund |
| `icon-transparent-512.png` | Bildmarke ohne Kachel, für Overlays und Watermarks |

## Einbindung

```html
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/icon/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/icon/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="theme-color" content="#5B4BE8">
```

`favicon.ico` gehört ins Wurzelverzeichnis. Browser fragen den Pfad auch dann an,
wenn kein `<link>` gesetzt ist, und ein 404 pro Seitenaufruf ist unnötiges Rauschen
im Log.

## site.webmanifest

```json
{
  "name": "ORGDATE",
  "short_name": "ORGDATE",
  "description": "Der Termin, der allen passt.",
  "lang": "de",
  "start_url": "/",
  "display": "standalone",
  "theme_color": "#5B4BE8",
  "background_color": "#FFFFFF",
  "icons": [
    { "src": "/icon/icon-192.png", "sizes": "192x192", "type": "image/png", "purpose": "any" },
    { "src": "/icon/icon-512.png", "sizes": "512x512", "type": "image/png", "purpose": "any" },
    { "src": "/icon/icon-maskable-192.png", "sizes": "192x192", "type": "image/png", "purpose": "maskable" },
    { "src": "/icon/icon-maskable-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
  ]
}
```

## Zeichenregeln

Die Bildmarke wird für kleine Größen nicht einfach skaliert, sondern kräftiger
gezeichnet, sonst laufen Ring und Punkt beim Rastern zu:

| Größe | Strichstärke | Punktradius | Flächenanteil |
|---|---|---|---|
| ≤ 16 | 22 | 12 | 74 % |
| ≤ 32 | 19 | 11 | 70 % |
| ≤ 48 | 17 | 10 | 68 % |
| ≥ 64 | 16 | 9.5 | 66 % |

Werte im Konstruktionsraster `viewBox="0 0 100 100"`. Der Flächenanteil bezieht sich
auf die sichtbare Symbolbreite im Verhältnis zur Kantenlänge des Icons.

**Rundung:** Die allgemeinen Icons haben eine Kachel mit `rx = 22.37 %` der Kantenlänge,
angelehnt an die iOS-Form. `apple-touch-icon.png`, `icon-1024.png` und die Maskable-Icons
sind bewusst unrund – iOS, die Stores und Android legen ihre eigene Maske darüber, eine
mitgelieferte Rundung würde doppelt beschnitten.

**Maskable:** Symbol auf 52 % der Kantenlänge, damit es innerhalb der Safe Zone von 80 %
liegt, egal welche Maske Android anlegt.

## Neu erzeugen

```bash
pip install cairosvg pillow
python make_icons.py
```

## Offene Punkte

- Abschnitt 8 im Brand Guide nennt für das Favicon Strichstärke 17 und Punkt r 10.
  Bei 16 px reicht das nicht, die Tabelle oben ist die geprüfte Fassung und sollte
  in den Guide übernommen werden.
- Kein `safari-pinned-tab.svg` enthalten. Das Format verlangt eine einfarbige,
  strichlose Vektorform; der Ring müsste dafür in eine gefüllte Kontur umgewandelt
  werden. Aktuelle Safari-Versionen nutzen stattdessen das Manifest und das
  Apple-Touch-Icon.
