# Plandu — Termin- & Eventplaner

**Find a time. Make a plan. Get it done.**

Ohne Anmeldung: einen Satz eingeben, Link teilen, alle tragen sich ein. Sobald
der Termin steht, geht es direkt in die Organisation — wer bringt was mit.

> Arbeitsname. Der endgültige Name wird ausschließlich über `APP_NAME` in der
> `.env` gesetzt; im Code steht kein hartcodierter Brand-Name.

## Stack

Laravel 13 · Inertia.js v2 · Vue 3 · Tailwind CSS v4 · PostgreSQL 16 · Vite

Architektur, Konventionen und alle Entscheidungen: **[CLAUDE.md](CLAUDE.md)**
Produkt-Spezifikation: **[event-planner-spec-v0.2.md](event-planner-spec-v0.2.md)**

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
docker exec plandu-pgsql psql -U plandu -d plandu -c "CREATE DATABASE plandu_test OWNER plandu;"
php artisan test
```

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
