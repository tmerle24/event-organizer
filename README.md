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
docker compose up -d                     # PostgreSQL auf Port 5433
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

Marken-Assets neu erzeugen:

```bash
bash brand/generate-icons.sh        # App-Icons + Favicons aus der Bildmarke
python3 brand/generate-lockups.py   # Logo-Lockups aus dem Wortmarken-Pfad
```

Die Share-Bilder liegen fertig in `public/og/`, ihre Quellen in `brand/og/`.

## KI ist optional

Ohne `ANTHROPIC_API_KEY` läuft die App vollständig — die Freitext-Extraktion
nutzt dann die eingebaute Heuristik statt der Claude API. Ein Ausfall des
Providers darf die Event-Erstellung nie blockieren.

## Deployment

```bash
bash deploy.sh
```

Erkennt Erstinstallation und Update automatisch. Bei Push auf `main` löst
`.github/workflows/deploy.yml` das per SSH aus.
