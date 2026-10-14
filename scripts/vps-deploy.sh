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

step 'Restoring file permissions'
# tar on Git for Windows records the NTFS ACLs, which land on Linux as 0600/0700
# modes owned by root. nginx then answers 403 for the new static files and
# PHP-FPM answers "Primary script unknown" for index.php - the site 404s even
# though the code unpacked correctly (seen on 2026-10-14 after the first run of
# this script). Put the standard web modes back before anything else reads them.
if [ "$(id -un)" = 'root' ] || [ -n "${SUDO_USER:-}" ]; then
    chown -R "root:$WEB_GROUP" "$APP_DIR" || true
fi
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR" -type f -exec chmod 644 {} +
chmod 640 "$APP_DIR/.env"
echo "index.php now: $(ls -l public/index.php 2>/dev/null || echo MISSING)"

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
    elif [ -f "$VHOST" ] && grep -q 'listen 443\|listen \[::\]:443' "$VHOST"; then
        # certbot writes its TLS block into this same file, so overwriting it
        # silently deletes HTTPS and the 80->443 redirect (seen 2026-10-14: the
        # site answered 443 "connection refused" right after a vhost reinstall).
        warn "$VHOST already serves 443 - leaving it untouched. Re-run this step only after 'certbot delete' or a new certificate."
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

step 'Raising the PHP upload limits'
# Laravel's ValidatePostSize middleware compares CONTENT_LENGTH against PHP's
# post_max_size - 8M on a stock Ubuntu PHP-FPM - and answers 413 before nginx or
# the app's own 120 MB APK rule is ever reached. Publishing the Agent app from
# Settings needs the drop-in that deploy/nginx/wya2d3d.site.conf already
# documents, so install it here instead of trusting a hand-run step.
RUN=''
[ "$(id -un)" = 'root' ] || RUN='sudo'
PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo '')"
UPLOAD_INI=''
for DIR in "/etc/php/$PHP_VER/fpm/conf.d" "/etc/php/$PHP_VER/cli/conf.d"; do
    [ -d "$DIR" ] || continue
    UPLOAD_INI="$DIR/99-wya-uploads.ini"
    printf 'upload_max_filesize = 130M\npost_max_size = 135M\nmax_execution_time = 300\n' \
        | $RUN tee "$UPLOAD_INI" > /dev/null \
        && echo "written: $UPLOAD_INI" \
        || warn "could not write $UPLOAD_INI - upload it as root"
done
[ -n "$UPLOAD_INI" ] || warn 'no PHP conf.d directory found - upload limits left untouched'
if [ -n "$UPLOAD_INI" ] && command -v systemctl > /dev/null; then
    $RUN systemctl reload "php$PHP_VER-fpm" 2> /dev/null \
        || warn "PHP-FPM was not reloaded - run: systemctl restart php$PHP_VER-fpm"
fi
echo "limits seen by the php command: $(php -r 'echo ini_get("upload_max_filesize"), " / ", ini_get("post_max_size");' 2>/dev/null || echo unknown)"

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
