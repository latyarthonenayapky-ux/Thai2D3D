#!/usr/bin/env bash
#
# Raises the PHP upload limits on the WYA server so the Admin can publish the
# Agent APK from Settings.
#
# Why this exists: Laravel's ValidatePostSize middleware runs before any
# controller and compares CONTENT_LENGTH against PHP's own post_max_size, which
# is 8M on a stock Ubuntu PHP-FPM. The Admin's upload therefore stops with
# "413 Content Too Large" and Symfony's generic error page, even though nginx
# already allows 130M and the app allows 120M.
#
# Runs ON the server. From Windows use the Desktop helper
# "Fix WYA Upload Limit.cmd", which uploads this file and runs it over ssh.
#
#   bash scripts/vps-fix-uploads.sh           # install and reload
#   bash scripts/vps-fix-uploads.sh --check   # only report what is in place

set -euo pipefail

CHECK_ONLY=0
[ "${1:-}" = '--check' ] && CHECK_ONLY=1

TARGET_KB=133120   # 130 MB, matching client_max_body_size in the nginx vhost
POST_KB=138240     # 135 MB, so the file plus the form fields still fit

RUN=''
[ "$(id -un)" = 'root' ] || RUN='sudo'

VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo '')"
if [ -z "$VERSION" ]; then
    echo 'STOP: the php command is not on PATH, so the version cannot be detected.'
    exit 1
fi

echo "php version  : $VERSION"
echo "php-fpm unit : php$VERSION-fpm"

if [ "$CHECK_ONLY" = '1' ]; then
    echo
    echo "cli limits   : $(php -r 'echo ini_get("upload_max_filesize"), " / post ", ini_get("post_max_size");' 2>/dev/null || echo unknown)"
    for DIR in "/etc/php/$VERSION/fpm/conf.d" "/etc/php/$VERSION/cli/conf.d"; do
        if [ -d "$DIR" ]; then
            echo "conf.d       : $DIR"
            [ -f "$DIR/99-wya-uploads.ini" ] \
                && sed 's/^/               /' "$DIR/99-wya-uploads.ini" \
                || echo '               99-wya-uploads.ini is NOT installed'
        fi
    done
    echo
    echo 'CHECK DONE'
    exit 0
fi

WRITTEN=0
for DIR in "/etc/php/$VERSION/fpm/conf.d" "/etc/php/$VERSION/cli/conf.d"; do
    [ -d "$DIR" ] || continue
    if printf 'upload_max_filesize = %sM\npost_max_size = %sM\nmax_execution_time = 300\n' \
        "$((TARGET_KB / 1024))" "$((POST_KB / 1024))" | $RUN tee "$DIR/99-wya-uploads.ini" > /dev/null; then
        echo "written      : $DIR/99-wya-uploads.ini"
        WRITTEN=1
    else
        echo "FAILED TO WRITE: $DIR/99-wya-uploads.ini"
    fi
done

if [ "$WRITTEN" = '0' ]; then
    echo 'STOP: no PHP conf.d directory was found, so nothing was changed.'
    exit 1
fi

if command -v systemctl > /dev/null; then
    $RUN systemctl reload "php$VERSION-fpm" 2> /dev/null \
        && echo 'reloaded     : php'"$VERSION"'-fpm' \
        || echo 'WARNING: PHP-FPM could not be reloaded. Run it yourself: systemctl restart php'"$VERSION"'-fpm'
else
    echo 'WARNING: systemctl is unavailable. Restart PHP-FPM yourself or the new limits stay unread.'
fi

echo
echo "cli limits now: $(php -r 'echo ini_get("upload_max_filesize"), " / post ", ini_get("post_max_size");' 2>/dev/null || echo unknown)"
echo
echo 'UPLOAD LIMITS FIXED'
echo 'Retry the publish in Admin -> Settings -> Mobile app. The app still refuses a'
echo 'file over 120 MB, so use app-release.apk from the Actions artifact, not the'
echo 'debug build - a debug APK is usually over 150 MB.'
