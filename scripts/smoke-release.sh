#!/usr/bin/env bash
#
# Boots a packaged payload exactly like the desktop launcher does (bundled PHP,
# throwaway SQLite database, redirected storage) and checks the things that only
# break in the shipped build: the PHP built-in server router, static asset MIME
# types, the branding markup and a clean Laravel log.
#
#   scripts/smoke-release.sh                                  # Windows folder build
#   scripts/smoke-release.sh --root release/VPS --php php     # VPS payload
#
set -u

ROOT="release/Windows/Thai2D3D/resources/laravel"
PHP=""
# A fixed port can be squatted by a server left behind by an earlier run, which
# makes every later request hit the stale payload and fail as HTTP 500.
PORT="${SMOKE_PORT:-$((8100 + RANDOM % 600))}"

while [ $# -gt 0 ]; do
    case "$1" in
        --root) ROOT="$2"; shift 2 ;;
        --php) PHP="$2"; shift 2 ;;
        --port) PORT="$2"; shift 2 ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
done

case "$PHP" in
    *[\\/]*) ;;
    *) for candidate in \
        "release/Windows/Thai2D3D/resources/php/php.exe" \
        "release/Windows/Thai2D3D/resources/php/php" \
        "php"; do
        if [ -f "$candidate" ]; then
            PHP="$candidate"
            break
        fi
    done ;;
esac
command -v "$PHP" >/dev/null 2>&1 || [ -f "$PHP" ] || { echo "FAIL: PHP binary not found: $PHP" >&2; exit 1; }
# The checks cd into the payload, so a relative binary would stop resolving.
if [ -f "$PHP" ]; then
    PHP="$(cd "$(dirname "$PHP")" && pwd)/$(basename "$PHP")"
fi

[ -f "$ROOT/artisan" ] || { echo "FAIL: no Laravel payload at $ROOT" >&2; exit 1; }
[ -f "$ROOT/server-router.php" ] || { echo "FAIL: $ROOT is missing server-router.php" >&2; exit 1; }

WORK="$(mktemp -d "${TMPDIR:-/tmp}/wya-smoke.XXXXXX")"
# Bash works with /tmp-style paths, but native Windows PHP only opens C:/... ones,
# so scratch paths get a second, Windows-flavoured form for the environment.
winpath() {
    ( cd "$1" && pwd -W 2>/dev/null ) || ( cd "$1" && pwd )
}
WORK_WIN="$(winpath "$WORK")"
ROOT="$(winpath "$ROOT")"
STORAGE="$WORK/storage"
BOOTSTRAP="$WORK/bootstrap"
DATABASE="$WORK/wya.sqlite"
LOG_FILE="$STORAGE/logs/laravel.log"
SERVER_LOG="$WORK/server.log"
mkdir -p "$STORAGE/app/private" "$STORAGE/app/public" "$STORAGE/framework/cache/data" \
         "$STORAGE/framework/sessions" "$STORAGE/framework/views" "$STORAGE/logs" \
         "$BOOTSTRAP/cache"

# The bundled php.ini points extension_dir at the packaged extensions; Windows
# short path names contain "~", which the ini parser rejects, so keep slashes.
INI="$WORK/php.ini"
PHP_DIR="$(dirname "$PHP")"
PHP_DIR_WIN="$(winpath "$PHP_DIR")"
if [ -f "$PHP_DIR/php.ini" ]; then
    sed "s#^extension_dir=.*#extension_dir=\"${PHP_DIR_WIN//\\//}/ext\"#" "$PHP_DIR/php.ini" > "$INI"
else
    : > "$INI"
fi

cp "$ROOT/bootstrap/providers.php" "$BOOTSTRAP/providers.php"
: > "$DATABASE"

export APP_NAME="Winning Yields Always (WYA)"
export APP_ENV=local
export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
export APP_DEBUG=false
export APP_URL="http://127.0.0.1:$PORT"
export APP_LOCALE=en APP_FALLBACK_LOCALE=en APP_TIMEZONE=Asia/Yangon
export DB_CONNECTION=sqlite DB_DATABASE="${WORK_WIN}/wya.sqlite" DB_URL= DB_FOREIGN_KEYS=true
export SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync FILESYSTEM_DISK=local
export LOG_CHANNEL=single LOG_LEVEL=warning SESSION_SECURE_COOKIE=false
export THAI2D3D_DESKTOP=true
export LARAVEL_STORAGE_PATH="${WORK_WIN}/storage"
export LARAVEL_BOOTSTRAP_PATH="${WORK_WIN}/bootstrap"

php_run() {
    "$PHP" -c "${WORK_WIN}/php.ini" -d memory_limit=512M "$@"
}

# Every artisan call has to run from inside the payload, otherwise it would pick
# up the development tree's own .env and database.
php_artisan() {
    ( cd "$ROOT" && php_run artisan "$@" )
}

failures=0
check() {
    local label="$1" ok="$2" detail="${3:-}"
    if [ "$ok" = "0" ]; then
        echo "  PASS  $label"
    else
        echo "  FAIL  $label ${detail:+— $detail}"
        failures=$((failures + 1))
    fi
}

echo "Payload: $ROOT"
echo "PHP:     $PHP"
echo "Scratch: $WORK"

# The desktop boot path: migrate, seed rules and detect the missing owner.
init_output="$(php_artisan thai2d3d:desktop-initialize --no-interaction 2>&1)"
init_code=$?
check "artisan thai2d3d:desktop-initialize" $init_code "$(echo "$init_output" | tail -3)"
echo "$init_output" | grep -q "DESKTOP_OWNER_SETUP_REQUIRED=1"
check "empty payload asks for owner setup" $?

php_artisan migrate:status >/dev/null 2>&1
check "migrations are recorded" $?

php_artisan route:list --json 2>/dev/null | grep -q "operator.sales.void"
check "sale void routes are registered" $?

# exec keeps $! pointing at php itself; without it the subshell dies and the
# native server is orphaned, still listening on the port for the next run.
( cd "$ROOT" && exec "$PHP" -c "${WORK_WIN}/php.ini" -d memory_limit=512M -S "127.0.0.1:$PORT" -t public server-router.php ) >"$SERVER_LOG" 2>&1 &
SERVER_PID=$!
trap 'kill "$SERVER_PID" >/dev/null 2>&1' EXIT

ready=1
for _ in $(seq 1 40); do
    code="$(curl -s -o /dev/null -w '%{http_code}' "$APP_URL/login" 2>/dev/null)"
    [ "$code" = "200" ] && { ready=0; break; }
    sleep 0.5
done
check "GET /login answers 200" $ready

fetch() {
    curl -s -H 'Accept: text/html' "$APP_URL$1"
}

for path in /login /desktop/owner-setup; do
    body="$(fetch "$path")"
    docs=0
    [ -n "$body" ] && docs="$(printf '%s' "$body" | grep -ci '<!DOCTYPE html')"
    check "$path renders exactly one HTML document" "$((docs == 1 ? 0 : 1))" "found $docs"
    printf '%s' "$body" | grep -q 'Winning Yields Always'
    check "$path carries the WYA branding" $?
    # An untranslated page leaks the dictionary key itself, e.g. app.clock.closed.
    if printf '%s' "$body" | grep -Eq 'app\.[a-z_]+\.[a-z_]+'; then
        check "$path has no untranslated dictionary keys" 1 "raw app.* keys leaked"
    else
        check "$path has no untranslated dictionary keys" 0
    fi
done

[ -f "$ROOT/lang/en/app.php" ] && [ -f "$ROOT/lang/my/app.php" ]
check "translation files ship inside the payload" $?

fetch /login | grep -q 'id="app-name"\|/images/wya-logo.png'
check "the header brand logo is referenced" $?

mime="$(curl -s -o /dev/null -w '%{content_type}' "$APP_URL/images/wya-logo.png")"
case "$mime" in
    image/png*) static_ok=0 ;;
    *) static_ok=1 ;;
esac
check "static PNG keeps its MIME type" $static_ok "$mime"

mime="$(curl -s -o /dev/null -w '%{content_type}' "$APP_URL/js/sale-entry-keyboard.js")"
case "$mime" in
    *javascript*|*ecmascript*) js_ok=0 ;;
    *) js_ok=1 ;;
esac
check "static JS keeps its MIME type" $js_ok "$mime"

code="$(curl -s -o /dev/null -w '%{http_code}' "$APP_URL/")"
[ "$code" = "302" ] || [ "$code" = "200" ]
check "the root URL serves the app" $? "HTTP $code"

php_artisan route:list --json 2>/dev/null | grep -q "api.v1.sales.upload"
check "the agent mobile API is routed" $?

code="$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' \
    -H 'Accept: application/json' -d '{"records":[]}' "$APP_URL/api/v1/sales/upload")"
[ "$code" = "401" ]
check "an unauthenticated upload is rejected" $? "HTTP $code"

code="$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{"login_code":"NOSUCH01","password":"wrong"}' "$APP_URL/api/v1/auth/login")"
[ "$code" = "401" ]
check "a bad mobile sign-in is rejected" $? "HTTP $code"

php_artisan route:list --json 2>/dev/null | grep -q "settings.mobile-app.download"
check "the admin-only APK routes are registered" $?

code="$(curl -s -o /dev/null -w '%{http_code}' "$APP_URL/admin/settings/mobile-app/download")"
[ "$code" = "302" ]
check "the APK is not downloadable without an admin session" $? "HTTP $code"

log_lines=0
[ -s "$LOG_FILE" ] && log_lines="$(wc -l < "$LOG_FILE" | tr -d ' ')"
check "the Laravel log stayed empty" "$((log_lines == 0 ? 0 : 1))" "$log_lines line(s)"

kill "$SERVER_PID" >/dev/null 2>&1
wait "$SERVER_PID" 2>/dev/null
server_errors=0
[ -s "$SERVER_LOG" ] && server_errors="$(grep -ciE 'error|exception|headers already sent' "$SERVER_LOG" || true)"
check "the built-in server logged no warnings" "$((server_errors == 0 ? 0 : 1))" "$server_errors line(s)"

if [ "$failures" -eq 0 ]; then
    rm -rf "$WORK"
    echo "SMOKE PASS — $ROOT"
    exit 0
fi

echo "SMOKE FAIL — $failures check(s) failed; scratch kept at $WORK"
exit 1
