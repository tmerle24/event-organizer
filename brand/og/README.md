# ORGDATE – Share Images

Vorschaubilder für geteilte Links (WhatsApp, iMessage, Slack, Signal, LinkedIn, X, Facebook).

## Dateien

| Datei | Format | Einsatz |
|---|---|---|
| `og-image.png` | 1200 × 630 | Standard, `og:image` |
| `og-image-light.png` | 1200 × 630 | Alternative auf hellen Seiten, z. B. Blog |
| `og-image-dark.png` | 1200 × 630 | Alternative für Dark-Mode-Kontexte |
| `og-image-square.png` | 1200 × 1200 | Messenger mit quadratischem Thumbnail, WhatsApp-Kontaktkarte |

Die `.svg`-Dateien sind die Quellen. Die Wortmarke liegt darin als Pfad vor, es wird
keine Schriftdatei benötigt.

## Einbindung

```html
<meta property="og:type"        content="website">
<meta property="og:site_name"   content="ORGDATE">
<meta property="og:title"       content="ORGDATE">
<meta property="og:description" content="Der Termin, der allen passt. Gemeinsam planen, ohne Hin und Her.">
<meta property="og:url"         content="https://orgdate.example/">
<meta property="og:image"       content="https://orgdate.example/og/og-image.png">
<meta property="og:image:type"  content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt"   content="ORGDATE – Der Termin, der allen passt.">
<meta property="og:locale"      content="de_DE">

<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="ORGDATE">
<meta name="twitter:description" content="Der Termin, der allen passt.">
<meta name="twitter:image"       content="https://orgdate.example/og/og-image.png">
```

## Was WhatsApp braucht

- **Absolute URL, https.** Relative Pfade werden ignoriert.
- **Unter 600 KB.** Größere Bilder werden nicht geladen, die Vorschau bleibt leer.
  Alle Dateien hier liegen unter 40 KB.
- **PNG oder JPG.** WebP und SVG werden in der Vorschau nicht zuverlässig gerendert.
- **Meta-Tags im ausgelieferten HTML.** Der WhatsApp-Crawler führt kein JavaScript aus –
  bei einer SPA müssen die Tags serverseitig gerendert oder pre-rendered sein.
- **Cache.** WhatsApp speichert die Vorschau pro URL sehr lange. Beim Testen einen
  Query-Parameter anhängen (`?v=2`), sonst siehst du das alte Bild.

## Sicherer Bereich

WhatsApp zeigt je nach Client entweder das volle 1200 × 630 oder einen zentrierten,
annähernd quadratischen Ausschnitt. Alle wichtigen Inhalte liegen deshalb innerhalb
eines zentrierten Bereichs von 620 × 540 px. Die Bogenformen in den Ecken laufen bewusst
aus dem Format und dürfen beschnitten werden.

## Event-spezifische Bilder

Wenn geteilte Einladungslinks später ein eigenes Bild bekommen sollen (Eventname,
Datum, Teilnehmerzahl), lässt sich `make_og.py` als Vorlage für eine
Runtime-Generierung nutzen: gleiches Layout, Wortmarke kleiner nach oben, Eventname
als Headline, Mint-Punkt als Statusanzeige. Empfehlung: als Edge-Function mit
Cache-Header ausliefern, nicht pro Request neu rendern.

## Neu erzeugen

```bash
pip install cairosvg fonttools
python make_og.py
```

Benötigt `fonts/Outfit.ttf` (Variable Font) im Projektverzeichnis.
