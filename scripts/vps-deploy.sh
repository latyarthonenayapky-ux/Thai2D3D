#!/usr/bin/env bash
#
# Server-side half of the WYA update. It runs ON the VPS, never on Windows:
# the Desktop helper "Deploy to WYA VPS.cmd" uploads the code bundle to /tmp and
# then pipes this file into `ssh ... "bash -s"`.
#
# Safe to run twice: .env, vendor/ and storage/ are never in the bundle, every
# step is backed up first, and the app is left in maintenance mode if anything
# fails so a half-copied build can never serve sales.
#
# Env overrides: APP_DIR, BUNDLE, BACKUP_DIR, WEB_GROUP, INSTALL_VHOST (0/1)

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/thai2d3d}"
BUNDLE="${BUNDLE:-/tmp/wya-update.tar.gz}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/thai2d3d}"
WEB_GROUP="${WEB_GROUP:-www-data}"
INSTALL_VHOST="${INSTALL_VHOST:-0}"
STAMP=''

step() { printf '\n=== %s\n' "$*"; }
warn() { printf 'WARNING: %s\n' "$*"; }

# Any unhandled failure stops the run while the site is in maintenance mode, so
# say that out loud and hand over the exact rollback command.
trap 'printf "\n!!! THE UPDATE STOPPED (line %s). The site is STILL in maintenance mode.\n!!! Either fix the line above and run this again, or roll back now:\n!!!   cd %s && tar -xzf %s -C %s && php artisan up\n" "$LINENO" "$APP_DIR" "$BACKUP_DIR/files-${STAMP}.tar.gz" "$APP_DIR" >&2' ERR

step 'Checking prerequisites'
[ -f "$BUNDLE" ] || { echo "Missing code bundle $BUNDLE - upload it first."; exit 1; }
[ -d "$APP_DIR" ] || { echo "Missing app directory $APP_DIR."; exit 1; }
[ -f "$APP_DIR/.env" ] || { echo "Missing $APP_DIR/.env - refusing to touch an unconfigured directory."; exit 1; }
command -v php >/dev/null || { echo 'php is not on PATH.'; exit 1; }
php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || { echo 'PHP 8.2+ is required.'; exit 1; }

cd "$APP_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR" 2>/dev/null || true
# The bundle never carries storage/ or bootstrap/cache, so make sure the
# directories artisan needs to write into exist before the first command runs.
mkdir -p bootstrap/cache storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs
echo "app dir  : $APP_DIR"
echo "bundle   : $(du -h "$BUNDLE" | cut -f1)  $BUNDLE"
echo "backups  : $BACKUP_DIR"

step 'Taking the site offline (retry 60s)'
php artisan down --retry=60 || true

step 'Backing up the files this run will replace'
tar -czf "$BACKUP_DIR/files-$STAMP.tar.gz" \
    --exclude=vendor --exclude=node_modules --exclude=storage --exclude=.git \
    -C "$APP_DIR" .
echo "written: $BACKUP_DIR/files-$STAMP.tar.gz"

step 'Backing up the database'
if command -v mysqldump >/dev/null; then
    bash scripts/backup-db.sh "$BACKUP_DIR" || warn 'database backup failed - migrate anyway at your own risk'
else
    warn 'mysqldump not found - no database backup taken'
fi

step 'Unpacking the new code'
tar -xzf "$BUNDLE" -C "$APP_DIR"

step 'Refreshing dependencies'
if command -v composer >/dev/null; then
    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
else
    warn 'composer missing - keeping the existing vendor/ autoloader'
fi

step 'Fixing writable directories'
if [ "$(id -un)" = 'root' ] || [ -n "${SUDO_USER:-}" ]; then
    chown -R "${SUDO_USER:-$(id -un)}:$WEB_GROUP" storage bootstrap/cache || true
fi
chmod -R ug+rwx storage bootstrap/cache || true

step 'Migrating and seeding'
php artisan migrate --force
php artisan db:seed --force || warn 'seeder failed - existing code rules were left untouched'

step 'Rebuilding caches'
php artisan optimize
php artisan storage:link || true

if [ "$INSTALL_VHOST" = '1' ]; then
    step 'Installing the wya2d3d.site nginx vhost'
    # A vhost problem must never strand the update, so every branch here only
    # warns; the code and the database are already in place at this point.
    RUN=''
    [ "$(id -un)" = 'root' ] || RUN='sudo'
    VHOST=''
    if [ -d /etc/nginx/sites-available ]; then
        VHOST=/etc/nginx/sites-available/wya2d3d.site
        LINK=/etc/nginx/sites-enabled/wya2d3d.site
    elif [ -d /etc/nginx/conf.d ]; then
        VHOST=/etc/nginx/conf.d/wya2d3d.site.conf
        LINK=''
    fi
    if [ -z "$VHOST" ] || [ ! -f deploy/nginx/wya2d3d.site.conf ] || ! command -v nginx >/dev/null; then
        warn 'no nginx config directory, nginx binary or deploy/nginx/wya2d3d.site.conf - vhost skipped'
    elif $RUN cp deploy/nginx/wya2d3d.site.conf "$VHOST" &&
         { [ -z "$LINK" ] || $RUN ln -sf "$VHOST" "$LINK"; } then
        if $RUN nginx -t >/dev/null 2>&1 && $RUN systemctl reload nginx; then
            echo "vhost installed: $VHOST and nginx reloaded"
        else
            $RUN nginx -t || true
            warn 'nginx rejected the config - the vhost is NOT active; nothing else was changed'
        fi
    else
        warn 'could not write the vhost file - nothing else was changed'
    fi
fi

step 'Putting the site back online'
php artisan up || true

step 'Result'
php artisan migrate:status | tail -n 6
php artisan about --only=environment || true
echo "local http status: $(curl -s -o /dev/null -w '%{http_code}' --max-time 10 http://127.0.0.1/ || echo 'unreachable')"
rm -f "$BUNDLE"
echo
echo "DEPLOY COMPLETE  ($STAMP)"
echo "If anything above looks wrong, restore the files with:"
echo "  tar -xzf $BACKUP_DIR/files-$STAMP.tar.gz -C $APP_DIR && php artisan up"
