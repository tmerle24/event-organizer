# ORGDATE — Server-Setup

## Erstinstallation

Frischer Ubuntu-Server (22.04 oder 24.04), ein Benutzer mit sudo-Rechten, **nicht root**:

```bash
git clone git@github.com:tmerle24/event-organizer.git /tmp/orgdate-setup
cd /tmp/orgdate-setup
bash install.sh
```

Der Wizard fragt Domain, Verzeichnis, Repo, Datenbank, TLS-Mail, SMTP und den
optionalen Anthropic-Key ab. Enter übernimmt jeweils den Vorschlag.

Danach steht: PHP-FPM, Node, Composer, PostgreSQL mit Datenbank und Benutzer,
nginx-vHost, Let's-Encrypt-Zertifikat, Queue-Worker unter Supervisor,
Scheduler-Cron und ufw.

**Vorher den DNS-A-Record auf den Server zeigen lassen** — sonst scheitert
certbot. Das Skript bricht deswegen nicht ab, die App läuft dann erst mal über
HTTP; nachholen mit `sudo certbot --nginx -d orgdate.com --redirect`.

Bei einem privaten Repo über SSH erzeugt das Skript einen Deploy-Key und zeigt
ihn an. Der gehört unter *Settings → Deploy keys* ins GitHub-Repo, danach Enter.

### Ohne Rückfragen

```bash
DOMAIN=orgdate.com TLS_EMAIL=hello@orgdate.com \
MAIL_HOST=smtp.mail.ovh.net MAIL_USERNAME=... MAIL_PASSWORD=... \
bash install.sh --non-interactive
```

Weitere Optionen: `--no-tls` (Staging, IP-Only oder Proxy davor), `--help`.

Ein zweiter Durchlauf ist unkritisch: jeder Schritt prüft erst, ob er schon
erledigt ist. Bestehende Passwörter, `.env` und vHost bleiben unangetastet.

## Updates

```bash
cd /var/www/orgdate && bash deploy.sh
```

`install.sh` legt `.deploy.conf` an; daraus liest `deploy.sh` Verzeichnis, Repo,
Branch und Worker-Namen. Optionen: `--no-npm`, `--no-migrate`.

Bei Push auf `main` löst `.github/workflows/deploy.yml` das per SSH aus
(Secrets: `SSH_HOST`, `SSH_USER`, `SSH_KEY`, `SSH_PORT`).

## Was wo liegt

| | |
|---|---|
| App | `/var/www/orgdate` |
| nginx-vHost | `/etc/nginx/sites-available/<domain>` |
| Queue-Worker | `/etc/supervisor/conf.d/orgdate-queue.conf` |
| Scheduler | `/etc/cron.d/orgdate-scheduler` |
| Logs | `storage/logs/laravel.log`, `/var/log/nginx/<domain>.error.log` |

Der Scheduler läuft über `/etc/cron.d`, nicht über ein Benutzer-Crontab —
er gehört zur Installation, nicht zu einer Person. `deploy.sh` erkennt das und
legt dann keinen zweiten Eintrag an.

## Backups

`backup.sh` sichert die Datenbank täglich nach Cloudflare R2 (30 Tage
Aufbewahrung). Es braucht noch `CLOUDFLARE_R2_ACCESS_KEY_ID`,
`CLOUDFLARE_R2_SECRET_ACCESS_KEY` und `CLOUDFLARE_R2_ENDPOINT` in der `.env`,
die AWS-CLI und einen eigenen Cron:

```bash
sudo apt install awscli
echo "0 5 * * * www-data /var/www/orgdate/backup.sh >> /var/log/orgdate-backup.log 2>&1" \
  | sudo tee /etc/cron.d/orgdate-backup
```

Wiederherstellen: `bash restore.sh --list`, dann `bash restore.sh 2026-08-26`.

## Häufige Fälle

**Seite zeigt 502** — PHP-FPM läuft nicht oder der Socket-Pfad im vHost passt
nicht zur installierten PHP-Version:

```bash
sudo systemctl status php8.4-fpm
grep fastcgi_pass /etc/nginx/sites-available/<domain>
```

**Keine Mails** — ohne `MAIL_HOST` läuft die App auf `MAIL_MAILER=log`, die
Mails stehen dann nur in `storage/logs/laravel.log`. Nach dem Nachtragen der
`MAIL_*`-Werte `php artisan config:cache` nicht vergessen.

**Änderungen wirken nicht** — in Produktion sind Config, Routen und Views
gecacht. `deploy.sh` baut die Caches neu; von Hand:

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Zertifikat erneuern** — macht certbot selbst über seinen systemd-Timer.
Prüfen mit `sudo certbot renew --dry-run`.

**Vorschaubild fehlt** — **zuerst einen fremden Link im selben Client testen**,
etwa `https://github.com`. Zeigt der auch kein Bild, liegt es am Client oder am
Netz und nicht an dieser Seite. Das kostet zehn Sekunden und erspart die ganze
serverseitige Sucherei. (Genau so ist es einmal ausgegangen: Microsoft Teams
zeigte in einem Firmennetz für keine Seite Vorschaubilder, auch nicht für
GitHub — der Server war die ganze Zeit korrekt.)

Zeigt der fremde Link ein Bild, unsere Seite aber nicht: holt der Crawler es
überhaupt ab?

```bash
sudo grep "/og/" /var/log/nginx/<domain>.access.log | tail -20
```

Ist die Ausgabe leer, prüfe zuerst, ob im vHost `access_log off;` im
`location ~* ^/(icon|og)/`-Block steht — dann wird schlicht nichts
protokolliert und die leere Ausgabe bedeutet nichts.

**Vorschaubild fehlt in Teams, funktioniert aber in WhatsApp** — dann kommt der
Abrufer nicht durch den TLS-Handshake. certbot stellt inzwischen ECDSA-Zertifikate
aus; wer nur RSA-Cipher-Suites beherrscht, bekommt gar keine Verbindung. Prüfen:

```bash
openssl s_client -connect <domain>:443 -servername <domain> -cipher aRSA -tls1_2 </dev/null
```

`handshake failure` heißt: es fehlt ein RSA-Zertifikat. Neue Installationen
legen beide an, ältere rüsten so nach:

```bash
sudo certbot certonly --nginx -d <domain> -d www.<domain> \
  --key-type rsa --cert-name <domain>-rsa --agree-tos -m hello@orgdate.com
```

Dann im vHost **zusätzlich** zu den vorhandenen Zeilen eintragen:

```nginx
ssl_certificate     /etc/letsencrypt/live/<domain>-rsa/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/<domain>-rsa/privkey.pem;
```

nginx wählt dann je nach Client aus. Danach `sudo nginx -t && sudo systemctl reload nginx`
und den Link mit frischer URL (`?v=2`) erneut teilen — Teams cacht Vorschauen hartnäckig.

## Lokale Entwicklung

Siehe [README.md](README.md) — dort läuft PostgreSQL über `docker compose` auf
Port 5433, `install.sh` ist ausschließlich für Server gedacht.
