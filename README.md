# ORGDATE

**Termin finden. Plan machen. Fertig.**

Ohne Anmeldung: einen Satz eingeben, Link teilen, alle tragen sich ein. Sobald
der Termin steht, geht es direkt in die Organisation — wer bringt was mit.

Domain: [orgdate.com](https://orgdate.com) · Kontakt: hello@orgdate.com

## Stack

Laravel 13 · Inertia.js v2 · Vue 3 · Tailwind CSS v4 · PostgreSQL 16 · Vite

- Architektur und Konventionen: **[CLAUDE.md](CLAUDE.md)**
- Marke, Logo, Farbe, Tonalität: **[BRANDING.md](BRANDING.md)**
- Produkt-Spezifikation: **[event-planner-spec-v0.2.md](event-planner-spec-v0.2.md)**

## Schnellstart

```bash
docker compose up -d                     # PostgreSQL auf Port 5434
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
composer dev                             # http://localhost:8080
```

Für die Tests einmalig die Testdatenbank anlegen:

```bash
docker exec orgdate-pgsql psql -U orgdate -d orgdate -c "CREATE DATABASE orgdate_test OWNER orgdate;"
php artisan test
```

## Marken-Assets

Logos, Icons und Share-Bilder liegen fertig unter `brand/` bzw. ausgeliefert in
`public/icon/` und `public/og/`. Die Quellen sind die drei Generatoren
`brand/logo/make_logos.py`, `brand/icon/make_icons.py` und
`brand/og/make_og.py` — sie brauchen `cairosvg`, `pillow`, `fonttools` und
`fonts/Outfit.ttf`. Einzelne SVGs bitte nicht von Hand nachziehen.

## KI ist optional

Ohne `ANTHROPIC_API_KEY` läuft die App vollständig — die Freitext-Extraktion
nutzt dann die eingebaute Heuristik statt der Claude API. Ein Ausfall des
Providers darf die Event-Erstellung nie blockieren.

## Deployment

Server aufsetzen (Ubuntu, fragt alles Nötige ab):

```bash
bash install.sh
```

Später aktualisieren:

```bash
bash deploy.sh
```

Bei Push auf `main` löst `.github/workflows/deploy.yml` das per SSH aus.
Details: [DEPLOYMENT.md](DEPLOYMENT.md).
