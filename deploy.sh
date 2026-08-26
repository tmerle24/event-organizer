#!/bin/bash
# deploy.sh — Deploy-Skript für ORGDATE
# Angelehnt an das bewährte Skript aus NamibWay/Wishlist.
#
# Erkennt automatisch, ob APP_DIR bereits ein Git-Repo ist:
#   NEIN -> klont main frisch (Erstinstallation)
#   JA   -> normales Update (git pull, composer, npm, migrate, caches)
#
# Verwendung:
#   bash deploy.sh              — volles Update
#   bash deploy.sh --no-npm     — ohne npm-Build
#   bash deploy.sh --no-migrate — ohne Migrationen
#
set -e

# ── Projekt-spezifische Werte — vor dem ersten Deploy anpassen ──────
APP_DIR="/var/www/orgdate"
REPO_URL="git@github.com:tmerle24/orgdate.git"
BRANCH="main"
QUEUE_WORKER_NAME="orgdate-queue"        # Supervisor-Programm (php artisan queue:work)

SKIP_NPM=false
SKIP_MIGRATE=false
for arg in "$@"; do
    case $arg in
        --no-npm)     SKIP_NPM=true ;;
        --no-migrate) SKIP_MIGRATE=true ;;
    esac
done

if [ ! -d "$APP_DIR/.git" ]; then
    echo "═══ Kein Git-Repo gefunden — klone $BRANCH nach $APP_DIR ═══"
    sudo mkdir -p "$APP_DIR"
    sudo chown "$(whoami):$(whoami)" "$APP_DIR"
    git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"

    cd "$APP_DIR"

    if [ ! -f .env ]; then
        echo "═══ .env aus Vorlage erstellen — MUSS danach befüllt werden ═══"
        cp .env.example .env
        php artisan key:generate
        echo ""
        echo "⚠  STOPP: .env jetzt mit echten Werten befüllen (DB, MAIL, ggf. ANTHROPIC_API_KEY),"
        echo "   Postgres-Datenbank + User anlegen, dann dieses Skript erneut ausführen."
        exit 0
    fi

    FIRST_INSTALL=true
else
    cd "$APP_DIR"
    FIRST_INSTALL=false
fi

echo "═══ 1/9 Maintenance-Mode AN ═══"
php artisan down --retry=15 || true

if [ "$FIRST_INSTALL" = false ]; then
    echo "═══ 2/9 Git Pull ($BRANCH) ═══"
    git fetch origin "$BRANCH"
    git reset --hard "origin/$BRANCH"
else
    echo "═══ 2/9 Erstinstallation — kein Pull nötig ═══"
fi

echo "═══ 3/9 Composer ═══"
composer install --no-dev --optimize-autoloader

if [ "$SKIP_NPM" = false ]; then
    echo "═══ 4/9 Caches leeren vor dem Build ═══"
    php artisan config:clear
    php artisan route:clear

    echo "═══ 5/9 npm ci + build ═══"
    npm ci
    export NODE_OPTIONS="--max-old-space-size=3072"
    npm run build
else
    echo "═══ 4-5/9 npm-Build übersprungen (--no-npm) ═══"
fi

if [ "$SKIP_MIGRATE" = false ]; then
    echo "═══ 6/9 Migrationen ═══"
    php artisan migrate --force
else
    echo "═══ 6/9 Migrationen übersprungen (--no-migrate) ═══"
fi

echo "═══ 7/9 Caches neu aufbauen ═══"
php artisan config:cache
php artisan route:cache
php artisan view:clear
php artisan view:cache
php artisan event:cache

echo "═══ 8/9 Scheduler-Cron sicherstellen (Retention-Löschung läuft darüber) ═══"
CRON_LINE="* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1"
(crontab -l 2>/dev/null | grep -qF "$APP_DIR" && echo "  → Cron-Eintrag bereits vorhanden") || \
    (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -

echo "═══ 9/9 Rechte, Queue-Worker, Maintenance-Mode AUS ═══"
sudo chown -R "$(whoami):www-data" "$APP_DIR"
sudo find "$APP_DIR/storage" -type d -exec chmod 775 {} \;
sudo find "$APP_DIR/storage" -type f -exec chmod 664 {} \;
sudo find "$APP_DIR/bootstrap/cache" -type d -exec chmod 775 {} \;
sudo find "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} \;

sudo supervisorctl restart "${QUEUE_WORKER_NAME}:*" 2>/dev/null || \
    echo "  → Supervisor-Worker '$QUEUE_WORKER_NAME' noch nicht eingerichtet, übersprungen"
php artisan up

echo ""
if [ "$FIRST_INSTALL" = true ]; then
    echo "✅ Erstinstallation abgeschlossen."
    echo "   Nächste Schritte: nginx-vHost + TLS, Supervisor für die Queue,"
    echo "   MAIL_*-Werte prüfen (Einladungs- und Bestätigungsmails)."
else
    echo "✅ Deploy fertig."
fi
