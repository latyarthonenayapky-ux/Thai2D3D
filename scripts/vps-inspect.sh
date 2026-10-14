#!/usr/bin/env bash
#
# Read-only inspection of the production VPS. Run through SSH from
# "VPS check Thai2D3D.cmd"; nothing here writes, installs or restarts anything.
# Secrets are never printed — .env is checked for key names only.
#
set -u

say() { printf '\n=== %s\n' "$*"; }

say "identity"
id
uname -a
lsb_release -ds 2>/dev/null || cat /etc/os-release 2>/dev/null | head -2

say "runtimes"
for c in php composer nginx mysql certbot; do
    printf '%-9s ' "$c"
    if command -v "$c" >/dev/null 2>&1; then "$c" --version 2>&1 | head -1; else echo "MISSING"; fi
done

say "web services"
for s in nginx php8.3-fpm mysql mariadb cron; do
    printf '%-13s ' "$s"
    systemctl is-active "$s" 2>/dev/null || echo "unknown"
done

say "web roots"
ls -la /var/www 2>/dev/null || echo "/var/www not readable"

APP=""
for cand in /var/www/thai2d3d /var/www/*/; do
    if [ -f "${cand}artisan" ]; then APP="${cand%/}"; break; fi
done
say "application directory"
if [ -n "$APP" ]; then
    echo "found: $APP"
    ls -la "$APP" | head -25
    printf 'vendor/        : '; [ -d "$APP/vendor" ] && echo present || echo MISSING
    printf '.env           : '; [ -f "$APP/.env" ] && echo present || echo MISSING
    printf 'git checkout   : '; [ -d "$APP/.git" ] && (cd "$APP" && git log -1 --format='%h %s' 2>/dev/null) || echo "no .git"
    printf 'storage writable: '; [ -w "$APP/storage" ] && echo yes || echo NO
    printf 'cache writable : '; [ -w "$APP/bootstrap/cache" ] && echo yes || echo NO
else
    echo "no artisan found under /var/www — app may live elsewhere"
fi

if [ -n "$APP" ] && [ -d "$APP/vendor" ]; then
    say "routes that matter"
    cd "$APP"
    for r in "api/v1/auth/login" "api/v1/sales/upload" "settings/mobile-app" "operator.offline"; do
        printf '%-26s ' "$r"
        php artisan route:list --json 2>/dev/null | grep -c -- "$r"
    done
    printf 'app version    : '; php artisan --version 2>/dev/null
    say "env keys only (values never shown)"
    grep -oE '^[A-Z_]+' .env 2>/dev/null | tr '\n' ' '
    printf '\nDB driver      : '; grep -E '^DB_CONNECTION=' .env 2>/dev/null || echo "unset"
    printf 'APP_URL set    : '; grep -qE '^APP_URL=https' .env 2>/dev/null && echo https || echo "http-or-unset"
fi

say "nginx sites enabled"
ls -1 /etc/nginx/sites-enabled 2>/dev/null
grep -hE '^\s*(server_name|root|listen|client_max_body_size)' /etc/nginx/sites-enabled/* 2>/dev/null | head -30

say "php upload limits (apk publish needs 130M)"
php -r 'echo "upload_max_filesize=", ini_get("upload_max_filesize"), " post_max_size=", ini_get("post_max_size"), PHP_EOL;' 2>/dev/null
ls -1 /etc/php/8.3/fpm/conf.d/ 2>/dev/null

say "tls"
ls -1 /etc/letsencrypt/live 2>/dev/null || echo "no certificates issued"

say "local health"
for u in "http://127.0.0.1/up" "http://127.0.0.1/api/v1/auth/login"; do
    printf '%-38s ' "$u"
    curl -s -o /dev/null -m 10 -w '%{http_code}\n' "$u" 2>/dev/null || echo "curl failed"
done

say "scheduler + backups"
crontab -u www-data -l 2>/dev/null | grep -v '^#' | head -5 || echo "no www-data crontab"
ls -1t "$APP/storage/backups" 2>/dev/null | head -3 || echo "no backups directory"

say "done"
echo "inspection finished - read-only, nothing changed"
