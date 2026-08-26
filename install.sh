#!/usr/bin/env bash
#
# install.sh — Erstinstallation von ORGDATE auf einem frischen Ubuntu-Server.
#
# Richtet alles ein, was die App zum Laufen braucht: PHP-FPM, Node, Composer,
# PostgreSQL inkl. Datenbank und Benutzer, nginx-vHost, Let's-Encrypt-Zertifikat,
# Queue-Worker unter Supervisor, Scheduler-Cron und Firewall. Am Ende übernimmt
# deploy.sh den App-Teil (composer, npm, migrate, caches) — die Logik steht
# bewusst nur dort, damit Erstinstallation und spätere Deploys nicht
# auseinanderlaufen.
#
# Verwendung:
#   bash install.sh                 Wizard (Standard)
#   bash install.sh --non-interactive
#                                   ohne Rückfragen, Werte aus der Umgebung
#                                   bzw. den Vorgaben unten
#   bash install.sh --no-tls        ohne Zertifikat (Staging, IP-Only, Proxy davor)
#   bash install.sh --help
#
# Erneutes Ausführen ist unkritisch: jeder Schritt prüft erst, ob er schon
# erledigt ist. Bereits vergebene Passwörter werden nicht überschrieben.
#
set -euo pipefail

# ── Vorgaben (per Umgebungsvariable überschreibbar) ──────────────────
APP_NAME="${APP_NAME:-ORGDATE}"
DOMAIN="${DOMAIN:-orgdate.com}"
WITH_WWW="${WITH_WWW:-yes}"
APP_DIR="${APP_DIR:-/var/www/orgdate}"
REPO_URL="${REPO_URL:-git@github.com:tmerle24/event-organizer.git}"
BRANCH="${BRANCH:-main}"

DB_NAME="${DB_NAME:-orgdate}"
DB_USER="${DB_USER:-orgdate}"
DB_PASSWORD="${DB_PASSWORD:-}"          # leer = wird erzeugt

PHP_VERSION="${PHP_VERSION:-8.4}"
NODE_MAJOR="${NODE_MAJOR:-22}"
WEB_USER="${WEB_USER:-www-data}"
QUEUE_WORKER_NAME="${QUEUE_WORKER_NAME:-orgdate-queue}"

TLS_EMAIL="${TLS_EMAIL:-hello@orgdate.com}"

MAIL_HOST="${MAIL_HOST:-}"
MAIL_PORT="${MAIL_PORT:-465}"
MAIL_USERNAME="${MAIL_USERNAME:-}"
MAIL_PASSWORD="${MAIL_PASSWORD:-}"
MAIL_FROM_ADDRESS="${MAIL_FROM_ADDRESS:-hello@orgdate.com}"

ANTHROPIC_API_KEY="${ANTHROPIC_API_KEY:-}"

INTERACTIVE=true
WITH_TLS=true

for arg in "$@"; do
    case "$arg" in
        --non-interactive|-y) INTERACTIVE=false ;;
        --no-tls)             WITH_TLS=false ;;
        --help|-h)
            sed -n '2,24p' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            echo "Unbekannte Option: $arg (siehe --help)" >&2
            exit 1
            ;;
    esac
done

# ── Ausgabe-Helfer ──────────────────────────────────────────────────
STEP=0
TOTAL=11

step()  { STEP=$((STEP + 1)); printf '\n\033[1;35m═══ %s/%s %s ═══\033[0m\n' "$STEP" "$TOTAL" "$1"; }
info()  { printf '  %s\n' "$1"; }
ok()    { printf '  \033[32m✓\033[0m %s\n' "$1"; }
skip()  { printf '  \033[2m→ %s\033[0m\n' "$1"; }
warn()  { printf '  \033[33m!\033[0m %s\n' "$1"; }
die()   { printf '\n\033[31m✗ %s\033[0m\n' "$1" >&2; exit 1; }

ask() {
    local prompt="$1" default="$2" answer
    if [ "$INTERACTIVE" = false ]; then
        printf '%s' "$default"
        return
    fi
    read -r -p "  $prompt [$default]: " answer </dev/tty
    printf '%s' "${answer:-$default}"
}

ask_secret() {
    local prompt="$1" answer
    if [ "$INTERACTIVE" = false ]; then
        printf ''
        return
    fi
    read -r -s -p "  $prompt (leer = überspringen): " answer </dev/tty
    printf '\n' >&2
    printf '%s' "$answer"
}

ask_yes() {
    local prompt="$1" default="$2" answer
    if [ "$INTERACTIVE" = false ]; then
        [ "$default" = "yes" ]
        return
    fi
    read -r -p "  $prompt [$default]: " answer </dev/tty
    answer="${answer:-$default}"
    [[ "$answer" =~ ^([jJ]|[yY]) ]]
}

# ── Vorprüfungen ────────────────────────────────────────────────────
[ "$(id -u)" -eq 0 ] && die "Bitte nicht als root ausführen, sondern als Benutzer mit sudo-Rechten."
command -v sudo >/dev/null || die "sudo wird gebraucht, ist aber nicht installiert."
[ -f /etc/os-release ] || die "Kein /etc/os-release — das Skript ist für Ubuntu gedacht."
# shellcheck disable=SC1091
. /etc/os-release
[ "${ID:-}" = "ubuntu" ] || warn "Getestet auf Ubuntu, gefunden: ${PRETTY_NAME:-unbekannt}. Weiter auf eigenes Risiko."

# Kein "sudo -v": bei einem NOPASSWD-Eintrag legt sudo keinen Zeitstempel an
# und fragt trotzdem nach einem Passwort. Erst passwortlos versuchen, sonst
# einmal regulär — dann darf sudo auch fragen.
sudo -n true 2>/dev/null || sudo true || die "sudo-Rechte konnten nicht bestätigt werden."

# ── Wizard ──────────────────────────────────────────────────────────
cat <<BANNER

  ╭─────────────────────────────────────────────╮
  │  ORGDATE — Server-Erstinstallation          │
  ╰─────────────────────────────────────────────╯

BANNER

if [ "$INTERACTIVE" = true ]; then
    info "Enter übernimmt jeweils den Vorschlag in eckigen Klammern."
    echo
fi

DOMAIN=$(ask "Domain" "$DOMAIN")
[ -n "$DOMAIN" ] || die "Ohne Domain geht es nicht."

if ask_yes "Auch www.$DOMAIN mit ausliefern? (j/n)" "$([ "$WITH_WWW" = yes ] && echo j || echo n)"; then
    SERVER_NAMES="$DOMAIN www.$DOMAIN"
    WITH_WWW=yes
else
    SERVER_NAMES="$DOMAIN"
    WITH_WWW=no
fi

APP_DIR=$(ask "Installationsverzeichnis" "$APP_DIR")
REPO_URL=$(ask "Git-Repository" "$REPO_URL")
BRANCH=$(ask "Branch" "$BRANCH")

DB_NAME=$(ask "Datenbank-Name" "$DB_NAME")
DB_USER=$(ask "Datenbank-Benutzer" "$DB_USER")

if [ "$WITH_TLS" = true ]; then
    TLS_EMAIL=$(ask "E-Mail für Let's Encrypt" "$TLS_EMAIL")
fi

if [ "$INTERACTIVE" = true ]; then
    echo
    info "SMTP für Einladungs- und Bestätigungsmails."
    info "Leer lassen geht auch — die App läuft dann mit MAIL_MAILER=log,"
    info "Mails landen nur in storage/logs/laravel.log."
    MAIL_HOST=$(ask "SMTP-Host" "$MAIL_HOST")
    if [ -n "$MAIL_HOST" ]; then
        MAIL_PORT=$(ask "SMTP-Port" "$MAIL_PORT")
        MAIL_USERNAME=$(ask "SMTP-Benutzer" "$MAIL_USERNAME")
        MAIL_PASSWORD=$(ask_secret "SMTP-Passwort")
        MAIL_FROM_ADDRESS=$(ask "Absenderadresse" "$MAIL_FROM_ADDRESS")
    fi

    echo
    info "Anthropic-Key beschleunigt die Texterkennung, ist aber optional:"
    info "ohne Key nutzt die App ihre eingebaute Heuristik."
    ANTHROPIC_API_KEY=$(ask_secret "ANTHROPIC_API_KEY")
fi

echo
info "Zusammenfassung:"
info "  Domain      $SERVER_NAMES"
info "  Verzeichnis $APP_DIR"
info "  Repo        $REPO_URL ($BRANCH)"
info "  Datenbank   $DB_NAME / $DB_USER"
info "  PHP         $PHP_VERSION   Node $NODE_MAJOR"
info "  Zertifikat  $([ "$WITH_TLS" = true ] && echo "Let's Encrypt ($TLS_EMAIL)" || echo 'nein (--no-tls)')"
info "  SMTP        ${MAIL_HOST:-— (Mails nur ins Log)}"
info "  Claude-API  $([ -n "$ANTHROPIC_API_KEY" ] && echo 'Key gesetzt' || echo '— (Heuristik-Fallback)')"
echo

if [ "$INTERACTIVE" = true ]; then
    ask_yes "So installieren? (j/n)" "j" || die "Abgebrochen."
fi

# ── 1. Systempakete ─────────────────────────────────────────────────
step "Systempakete"

export DEBIAN_FRONTEND=noninteractive
sudo apt-get update -qq

sudo apt-get install -y -qq \
    software-properties-common ca-certificates curl gnupg git unzip \
    nginx postgresql postgresql-contrib supervisor cron ufw acl >/dev/null
ok "nginx, PostgreSQL, Supervisor, Git"

if ! command -v "php$PHP_VERSION" >/dev/null; then
    # Ubuntu liefert je nach Release nur ältere PHP-Versionen mit.
    sudo add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1
    sudo apt-get update -qq
fi

sudo apt-get install -y -qq \
    "php$PHP_VERSION-fpm" "php$PHP_VERSION-cli" "php$PHP_VERSION-pgsql" \
    "php$PHP_VERSION-mbstring" "php$PHP_VERSION-xml" "php$PHP_VERSION-curl" \
    "php$PHP_VERSION-zip" "php$PHP_VERSION-gd" "php$PHP_VERSION-intl" \
    "php$PHP_VERSION-bcmath" >/dev/null
ok "PHP $("php$PHP_VERSION" -r 'echo PHP_VERSION;')"

if ! command -v composer >/dev/null; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    sudo "php$PHP_VERSION" /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer >/dev/null
    rm -f /tmp/composer-setup.php
fi
ok "Composer $(composer --version --no-ansi 2>/dev/null | awk '{print $3}' || echo '?')"

if ! command -v node >/dev/null || [ "$(node -v | sed 's/v\([0-9]*\).*/\1/')" -lt "$NODE_MAJOR" ]; then
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | sudo -E bash - >/dev/null 2>&1
    sudo apt-get install -y -qq nodejs >/dev/null 2>&1
fi
ok "Node $(node -v), npm $(npm -v)"

# ── 2. PostgreSQL ───────────────────────────────────────────────────
step "Datenbank"

sudo systemctl enable --now postgresql >/dev/null 2>&1 || true

# Ob es schon eine .env gibt, entscheidet weiter unten, ob ein bestehendes
# Datenbank-Passwort noch bekannt ist oder neu gesetzt werden muss.
ENV_EXISTS=false
[ -f "$APP_DIR/.env" ] && ENV_EXISTS=true

db_exists() {
    sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" | grep -q 1
}
role_exists() {
    sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" | grep -q 1
}

# Das Passwort läuft jeweils über stdin, damit es nicht in der Prozessliste steht.
if ! role_exists; then
    [ -n "$DB_PASSWORD" ] || DB_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)
    sudo -u postgres psql -q <<SQL
CREATE ROLE "$DB_USER" LOGIN PASSWORD '$DB_PASSWORD';
SQL
    ok "Rolle $DB_USER angelegt"
    DB_PASSWORD_KNOWN=true
elif [ "$ENV_EXISTS" = true ]; then
    skip "Rolle $DB_USER existiert bereits — Passwort bleibt unverändert"
    DB_PASSWORD_KNOWN=false
else
    # Rolle da, .env aber weg: das alte Passwort kennt niemand mehr. Ohne
    # Reset bekäme die neue .env den Platzhalter aus .env.example und die App
    # käme nicht an die Datenbank.
    [ -n "$DB_PASSWORD" ] || DB_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)
    sudo -u postgres psql -q <<SQL
ALTER ROLE "$DB_USER" WITH LOGIN PASSWORD '$DB_PASSWORD';
SQL
    warn "Rolle $DB_USER existierte ohne passende .env — Passwort neu gesetzt"
    DB_PASSWORD_KNOWN=true
fi

if db_exists; then
    skip "Datenbank $DB_NAME existiert bereits"
else
    sudo -u postgres createdb -O "$DB_USER" -E UTF8 "$DB_NAME"
    ok "Datenbank $DB_NAME angelegt"
fi

# Laravel legt in Migrationen Indizes und Constraints an — dafür reicht der
# Besitz der Datenbank, zusätzlich braucht die Rolle das Schema public.
sudo -u postgres psql -q -d "$DB_NAME" <<SQL
GRANT ALL ON SCHEMA public TO "$DB_USER";
ALTER SCHEMA public OWNER TO "$DB_USER";
SQL
ok "PostgreSQL $(sudo -u postgres psql -tAc 'SHOW server_version;' | cut -d' ' -f1 || echo '?')"

# ── 3. Repository ───────────────────────────────────────────────────
step "Repository"

sudo mkdir -p "$APP_DIR"
sudo chown "$(id -un):$(id -gn)" "$APP_DIR"

if [ -d "$APP_DIR/.git" ]; then
    skip "$APP_DIR ist bereits ein Git-Repo"
else
    if [[ "$REPO_URL" == git@* ]] && ! ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new -T git@github.com 2>&1 | grep -q "successfully authenticated"; then
        # Privates Repo über SSH: ohne hinterlegten Deploy-Key scheitert der
        # Klon wortlos an einer Passwortabfrage.
        if [ ! -f ~/.ssh/id_ed25519 ]; then
            ssh-keygen -t ed25519 -N '' -C "$(id -un)@$(hostname)" -f ~/.ssh/id_ed25519 >/dev/null
            ok "SSH-Schlüssel erzeugt"
        fi
        echo
        warn "GitHub kennt diesen Server noch nicht. Öffentlichen Schlüssel als Deploy-Key hinterlegen:"
        echo
        echo "    https://github.com/${REPO_URL#git@github.com:}"
        echo "    → Settings → Deploy keys → Add deploy key"
        echo
        sed 's/^/    /' ~/.ssh/id_ed25519.pub
        echo
        if [ "$INTERACTIVE" = true ]; then
            read -r -p "  Danach Enter drücken … " _ </dev/tty
        else
            die "Deploy-Key fehlt. Schlüssel hinterlegen und install.sh erneut starten."
        fi
    fi

    git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
    ok "Geklont ($BRANCH)"
fi

cd "$APP_DIR"

# ── 4. .env ─────────────────────────────────────────────────────────
step "Konfiguration"

APP_URL="https://$DOMAIN"
[ "$WITH_TLS" = false ] && APP_URL="http://$DOMAIN"

if [ -f .env ]; then
    skip ".env existiert bereits — bleibt unverändert"
else
    cp .env.example .env

    set_env() {
        local key="$1" value="$2"
        # Wert wird als Literal eingesetzt, damit Sonderzeichen im Passwort
        # nicht als sed-Ersetzungsmuster interpretiert werden.
        python3 - "$key" "$value" <<'PY'
import re, sys
key, value = sys.argv[1], sys.argv[2]
path = '.env'
lines = open(path).read().splitlines()
needs_quotes = value == '' or re.search(r'[\s"#\'$]', value)
rendered = f'{key}="{value}"' if needs_quotes else f'{key}={value}'
for i, line in enumerate(lines):
    if re.match(rf'^\s*#?\s*{re.escape(key)}=', line):
        lines[i] = rendered
        break
else:
    lines.append(rendered)
open(path, 'w').write('\n'.join(lines) + '\n')
PY
    }

    set_env APP_NAME "$APP_NAME"
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_URL "$APP_URL"

    set_env DB_CONNECTION pgsql
    set_env DB_HOST 127.0.0.1
    set_env DB_PORT 5432
    set_env DB_DATABASE "$DB_NAME"
    set_env DB_USERNAME "$DB_USER"
    [ "${DB_PASSWORD_KNOWN:-false}" = true ] && set_env DB_PASSWORD "$DB_PASSWORD"

    if [ -n "$MAIL_HOST" ]; then
        set_env MAIL_MAILER smtp
        set_env MAIL_HOST "$MAIL_HOST"
        set_env MAIL_PORT "$MAIL_PORT"
        set_env MAIL_USERNAME "$MAIL_USERNAME"
        set_env MAIL_PASSWORD "$MAIL_PASSWORD"
        # 465 spricht implizites TLS, 587 STARTTLS.
        set_env MAIL_SCHEME "$([ "$MAIL_PORT" = "465" ] && echo smtps || echo smtp)"
    fi
    set_env MAIL_FROM_ADDRESS "$MAIL_FROM_ADDRESS"

    [ -n "$ANTHROPIC_API_KEY" ] && set_env ANTHROPIC_API_KEY "$ANTHROPIC_API_KEY"

    set_env BRAND_DOMAIN "$DOMAIN"
    set_env BRAND_EMAIL "$MAIL_FROM_ADDRESS"

    chmod 640 .env
    ok ".env geschrieben"
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
    # Bewusst ohne "artisan key:generate": an dieser Stelle gibt es noch kein
    # vendor/. Laravel erwartet 32 zufällige Bytes in Base64, genau das hier.
    APP_KEY="base64:$(openssl rand -base64 32)"
    python3 - "$APP_KEY" <<'PY'
import re, sys
value = sys.argv[1]
path = '.env'
lines = open(path).read().splitlines()
for i, line in enumerate(lines):
    if re.match(r'^\s*#?\s*APP_KEY=', line):
        lines[i] = f'APP_KEY={value}'
        break
else:
    lines.append(f'APP_KEY={value}')
open(path, 'w').write('\n'.join(lines) + '\n')
PY
    ok "APP_KEY erzeugt"
fi

# Damit deploy.sh später dieselben Werte benutzt.
cat > .deploy.conf <<CONF
# Von install.sh erzeugt. Wird von deploy.sh eingelesen.
APP_DIR="$APP_DIR"
REPO_URL="$REPO_URL"
BRANCH="$BRANCH"
QUEUE_WORKER_NAME="$QUEUE_WORKER_NAME"
WEB_USER="$WEB_USER"
CONF
ok ".deploy.conf geschrieben"

# ── 5. nginx ────────────────────────────────────────────────────────
step "nginx"

NGINX_SITE="/etc/nginx/sites-available/$DOMAIN"

if [ -f "$NGINX_SITE" ]; then
    skip "vHost existiert bereits — bleibt unverändert"
else
    sudo tee "$NGINX_SITE" >/dev/null <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $SERVER_NAMES;
    root $APP_DIR/public;

    index index.php;
    charset utf-8;
    client_max_body_size 8m;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/javascript application/json image/svg+xml;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Vite hängt einen Hash an jeden Dateinamen — unbegrenzt cachebar.
    location ^~ /build/ {
        # Nur add_header, kein "expires": beide zusammen senden Cache-Control
        # doppelt, und daran verschlucken sich manche Crawler und Proxies.
        add_header Cache-Control "public, max-age=31536000, immutable" always;
        access_log off;
        try_files \$uri =404;
    }

    # Marken-Assets ändern sich selten, aber unter gleichem Namen.
    # Hier bewusst KEIN "access_log off": Wenn eine Link-Vorschau kein Bild
    # zeigt, ist die erste Frage immer "holt der Crawler es überhaupt ab?" —
    # und die beantwortet nur der Log. Das Volumen ist vernachlässigbar.
    location ~* ^/(icon|og)/ {
        add_header Cache-Control "public, max-age=2592000" always;
        try_files \$uri =404;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php$PHP_VERSION-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 60s;
    }

    # Alles Versteckte bleibt dicht, außer der ACME-Challenge.
    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/$DOMAIN.access.log;
    error_log  /var/log/nginx/$DOMAIN.error.log;
}
NGINX
    ok "vHost angelegt"
fi

sudo ln -sfn "$NGINX_SITE" "/etc/nginx/sites-enabled/$DOMAIN"
[ -e /etc/nginx/sites-enabled/default ] && sudo rm -f /etc/nginx/sites-enabled/default

sudo nginx -t >/dev/null 2>&1 || die "nginx-Konfiguration ist fehlerhaft — 'sudo nginx -t' zeigt Details."

sudo systemctl enable --now "php$PHP_VERSION-fpm" >/dev/null 2>&1 || true
# enable --now startet nginx, falls das Paket ihn nicht schon gestartet hat;
# reload-or-restart deckt beide Fälle ab.
sudo systemctl enable nginx >/dev/null 2>&1 || true
sudo systemctl reload-or-restart nginx

sudo systemctl is-active --quiet nginx || die "nginx startet nicht — 'sudo systemctl status nginx' zeigt warum."
sudo systemctl is-active --quiet "php$PHP_VERSION-fpm" || die "php$PHP_VERSION-fpm läuft nicht."
ok "nginx und PHP-FPM laufen"

# ── 6. Firewall ─────────────────────────────────────────────────────
step "Firewall"

# Nichts hier darf die Installation abbrechen — und vor allem darf ufw nie
# ohne SSH-Regel aktiviert werden, das sperrt einen vom eigenen Server aus.
allow_http() {
    sudo ufw allow 'Nginx Full' >/dev/null 2>&1 || sudo ufw allow 80,443/tcp >/dev/null 2>&1
}

if ! command -v ufw >/dev/null; then
    skip "ufw nicht installiert — Firewall übersprungen"
elif sudo ufw status 2>/dev/null | grep -q "Status: active"; then
    allow_http && ok "ufw war bereits aktiv, HTTP/HTTPS freigegeben" \
        || warn "HTTP/HTTPS ließ sich nicht freigeben: sudo ufw allow 'Nginx Full'"
else
    # Den tatsächlich konfigurierten SSH-Port nehmen, nicht blind 22.
    SSH_PORT=$(sudo sshd -T 2>/dev/null | awk '/^port /{print $2; exit}' || true)
    SSH_PORT="${SSH_PORT:-22}"

    if sudo ufw allow OpenSSH >/dev/null 2>&1 || sudo ufw allow "$SSH_PORT/tcp" >/dev/null 2>&1; then
        allow_http || true
        # --force, weil ufw sonst interaktiv nach der SSH-Verbindung fragt.
        if sudo ufw --force enable >/dev/null 2>&1; then
            ok "ufw aktiv (SSH auf $SSH_PORT, HTTP/HTTPS)"
        else
            warn "ufw ließ sich nicht aktivieren — Firewall bleibt aus."
        fi
    else
        warn "Keine SSH-Regel möglich — ufw bleibt bewusst aus, sonst sperrt sie dich aus."
        warn "Manuell: sudo ufw allow $SSH_PORT/tcp && sudo ufw allow 'Nginx Full' && sudo ufw enable"
    fi
fi

# ── 7. App bauen ────────────────────────────────────────────────────
step "App bauen (deploy.sh)"

# Der eigentliche Build steht nur in deploy.sh, damit Erstinstallation und
# spätere Deploys nicht auseinanderlaufen.
bash "$APP_DIR/deploy.sh"

# ── 8. Queue-Worker ─────────────────────────────────────────────────
step "Queue-Worker"

SUPERVISOR_CONF="/etc/supervisor/conf.d/$QUEUE_WORKER_NAME.conf"

if [ -f "$SUPERVISOR_CONF" ]; then
    skip "Supervisor-Programm existiert bereits"
else
    sudo tee "$SUPERVISOR_CONF" >/dev/null <<SUPERVISOR
[program:$QUEUE_WORKER_NAME]
process_name=%(program_name)s_%(process_num)02d
command=php$PHP_VERSION $APP_DIR/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$WEB_USER
numprocs=1
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/queue.log
stopwaitsecs=3600
SUPERVISOR
    ok "Supervisor-Programm angelegt"
fi

# Ein hängender Worker darf die Installation nicht abbrechen — die App
# läuft auch ohne ihn, es gehen dann nur keine Jobs aus der Queue.
sudo systemctl enable --now supervisor >/dev/null 2>&1 || true

if sudo supervisorctl reread >/dev/null 2>&1 && sudo supervisorctl update >/dev/null 2>&1; then
    sudo supervisorctl restart "$QUEUE_WORKER_NAME:*" >/dev/null 2>&1 || \
        sudo supervisorctl start "$QUEUE_WORKER_NAME:*" >/dev/null 2>&1 || true

    if sudo supervisorctl status "$QUEUE_WORKER_NAME:*" 2>/dev/null | grep -q RUNNING; then
        ok "Worker läuft"
    else
        warn "Worker startet nicht — sudo supervisorctl status $QUEUE_WORKER_NAME:*"
    fi
else
    warn "supervisord antwortet nicht — sudo systemctl status supervisor"
fi

# ── 9. Scheduler ────────────────────────────────────────────────────
step "Scheduler"

CRON_FILE="/etc/cron.d/${QUEUE_WORKER_NAME%-queue}-scheduler"

# Über /etc/cron.d statt crontab -e: gehört zur Installation, nicht zu einem
# Benutzer, und ist beim Aufräumen wieder auffindbar.
sudo tee "$CRON_FILE" >/dev/null <<CRON
# ORGDATE — Laravel-Scheduler. Löscht abgelaufene Events (Retention).
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

* * * * * $WEB_USER cd $APP_DIR && php$PHP_VERSION artisan schedule:run >> /dev/null 2>&1
CRON
sudo chmod 644 "$CRON_FILE"
ok "Cron unter $CRON_FILE"

# ── 10. Zertifikat ──────────────────────────────────────────────────
step "Zertifikat"

if [ "$WITH_TLS" = false ]; then
    skip "übersprungen (--no-tls)"
elif sudo test -d "/etc/letsencrypt/live/$DOMAIN"; then
    skip "Zertifikat für $DOMAIN existiert bereits"
else
    sudo apt-get install -y -qq certbot python3-certbot-nginx >/dev/null

    CERTBOT_DOMAINS=(-d "$DOMAIN")
    [ "$WITH_WWW" = yes ] && CERTBOT_DOMAINS+=(-d "www.$DOMAIN")

    # Ein fehlender DNS-Eintrag ist der häufigste Grund fürs Scheitern —
    # dann läuft die App eben erst mal über HTTP weiter.
    if sudo certbot --nginx "${CERTBOT_DOMAINS[@]}" \
        --non-interactive --agree-tos -m "$TLS_EMAIL" --redirect >/dev/null 2>&1; then
        ok "Zertifikat ausgestellt, HTTP leitet auf HTTPS um"

        # Zusätzlich ein RSA-Zertifikat. certbot stellt inzwischen ECDSA aus;
        # ein Client, der nur RSA-Cipher-Suites kann, scheitert dann schon am
        # Handshake und sieht die Seite gar nicht. Daran hängen ältere
        # Link-Vorschau-Dienste — bei Microsoft Teams blieb dadurch das
        # Vorschaubild leer, obwohl Titel und Text ankamen. nginx darf mehrere
        # Zertifikate haben und wählt nach den Cipher Suites des Clients aus.
        if sudo certbot certonly --nginx "${CERTBOT_DOMAINS[@]}" \
            --key-type rsa --cert-name "$DOMAIN-rsa" \
            --non-interactive --agree-tos -m "$TLS_EMAIL" >/dev/null 2>&1; then

            if ! grep -q -- "$DOMAIN-rsa" "$NGINX_SITE"; then
                sudo sed -i "0,|^\( *\)ssl_certificate_key .*|s||&\n\1ssl_certificate     /etc/letsencrypt/live/$DOMAIN-rsa/fullchain.pem;\n\1ssl_certificate_key /etc/letsencrypt/live/$DOMAIN-rsa/privkey.pem;|" "$NGINX_SITE" 2>/dev/null || true
            fi

            if sudo nginx -t >/dev/null 2>&1; then
                sudo systemctl reload nginx
                ok "RSA-Zertifikat zusätzlich eingebunden (ältere Clients)"
            else
                sudo sed -i "\|$DOMAIN-rsa|d" "$NGINX_SITE"
                sudo nginx -t >/dev/null 2>&1 && sudo systemctl reload nginx
                warn "RSA-Zertifikat ließ sich nicht einbinden — Konfiguration zurückgenommen"
            fi
        else
            warn "RSA-Zertifikat nicht ausgestellt. Ältere Clients (u.a. die"
            warn "Teams-Vorschau) laden dann kein Bild — siehe DEPLOYMENT.md"
        fi

        # certbot legt seinen eigenen Renew-Timer an, hier nur prüfen.
        sudo systemctl list-timers certbot.timer --no-pager >/dev/null 2>&1 && \
            ok "Automatische Verlängerung aktiv"
    else
        warn "certbot ist gescheitert. Zeigt der DNS-A-Record von $DOMAIN schon auf diesen Server?"
        warn "Nachholen mit: sudo certbot --nginx -d $DOMAIN --redirect"
        APP_URL="http://$DOMAIN"
        "php$PHP_VERSION" artisan config:clear >/dev/null
    fi
fi

# ── 11. Abschluss ───────────────────────────────────────────────────
step "Prüfen"

sudo chown -R "$(id -un):$WEB_USER" "$APP_DIR"
sudo chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

HEALTH=$(curl -sk -o /dev/null -w '%{http_code}' "$APP_URL/up" || echo 000)
if [ "$HEALTH" = "200" ]; then
    ok "$APP_URL/up antwortet mit 200"
else
    warn "$APP_URL/up antwortet mit $HEALTH — Logs prüfen:"
    warn "  tail -n 50 $APP_DIR/storage/logs/laravel.log"
    warn "  tail -n 50 /var/log/nginx/$DOMAIN.error.log"
fi

cat <<SUMMARY

  ╭─────────────────────────────────────────────╮
  │  Fertig                                     │
  ╰─────────────────────────────────────────────╯

  App          $APP_URL
  Verzeichnis  $APP_DIR
  Datenbank    $DB_NAME (Benutzer $DB_USER)
SUMMARY

if [ "${DB_PASSWORD_KNOWN:-false}" = true ]; then
    cat <<SUMMARY
  Passwort     $DB_PASSWORD
               (steht auch in $APP_DIR/.env — jetzt notieren, es wird
               nicht noch einmal angezeigt)
SUMMARY
fi

cat <<'SUMMARY'

  Weiter geht es mit:
    bash deploy.sh              spätere Updates
    sudo supervisorctl status   Queue-Worker
    php artisan tinker          Konsole

SUMMARY

if [ -z "$MAIL_HOST" ]; then
    warn "Ohne SMTP gehen keine Einladungen raus. MAIL_* in .env nachtragen,"
    warn "danach: php artisan config:cache"
fi

if [ -z "$ANTHROPIC_API_KEY" ]; then
    info "Ohne ANTHROPIC_API_KEY läuft die Texterkennung über die Heuristik."
fi

info "Backups: backup.sh braucht noch die CLOUDFLARE_R2_*-Werte in .env"
info "und einen eigenen Cron — siehe DEPLOYMENT.md."
echo
