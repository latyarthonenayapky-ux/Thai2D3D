#!/usr/bin/env bash
#
# Expose the local Thai2D3D Laravel server on a public HTTPS URL for free.
#
# Why localhost.run and not Cloudflare: this network silently drops TLS to
# api.trycloudflare.com (SNI filtering), so `cloudflared tunnel --url` can never
# create a quick tunnel from here. localhost.run tunnels over outbound SSH 22,
# which is open.
#
# Caveat: free localhost.run URLs are ephemeral. The hostname changes on every
# reconnect, and the service drops idle connections. This script reconnects
# automatically and writes the current URL to storage/logs/public-url.txt.
# It is a demo/preview path, not production hosting.
#
# Usage: scripts/go-live.sh {start|stop|status|url}

set -uo pipefail

PROJECT_DIR="/home/waiyanaung/Projects/Thai2D3D"
SSH_KEY="$HOME/.ssh/id_ed25519"
LISTEN_HOST="127.0.0.1"
LISTEN_PORT="8000"
LOG_DIR="$PROJECT_DIR/storage/logs"
SERVER_LOG="$LOG_DIR/server.log"
TUNNEL_LOG="$LOG_DIR/tunnel.log"
URL_FILE="$LOG_DIR/public-url.txt"
SERVER_PIDFILE="$LOG_DIR/go-live-server.pid"
TUNNEL_PIDFILE="$LOG_DIR/go-live-tunnel.pid"

mkdir -p "$LOG_DIR"
cd "$PROJECT_DIR" || exit 1

# --- helpers ---------------------------------------------------------------

# The Laravel server must NOT inherit DB_* / APP_URL variables. A stale shell
# that exported DB_CONNECTION=sqlite / DB_DATABASE=...demo.sqlite silently
# overrides .env (real environment wins over .env in Laravel), which makes the
# web app talk to a different database than `php artisan` does — the login form
# then rejects credentials that are provably correct. Strip them explicitly.
SERVER_ENV=(env -u DB_CONNECTION -u DB_DATABASE -u DB_URL -u DB_USERNAME -u DB_PASSWORD -u APP_URL -u APP_DEBUG -u APP_ENV)

server_running() {
    ss -ltnH 2>/dev/null | grep -q ":$LISTEN_PORT"
}

tunnel_running() {
    [ -f "$TUNNEL_PIDFILE" ] && kill -0 "$(cat "$TUNNEL_PIDFILE")" 2>/dev/null
}

current_url() {
    # Anchor tightly on the lhr.life hostname. Loose patterns previously matched
    # localhost.run's own banner text and produced bogus URLs.
    grep -oE '\b[a-z0-9]{12,20}\.lhr\.life' "$TUNNEL_LOG" 2>/dev/null | tail -1
}

wait_for_url() {
    local i
    for i in $(seq 1 40); do
        if [ -n "$(current_url)" ]; then
            return 0
        fi
        sleep 1
    done
    return 1
}

# --- commands --------------------------------------------------------------

start_server() {
    if server_running; then
        echo "Server: already listening on $LISTEN_HOST:$LISTEN_PORT"
        return 0
    fi
    echo "Server: starting php artisan serve on $LISTEN_HOST:$LISTEN_PORT"
    # PHP_CLI_SERVER_WORKERS is deliberately not set: Laravel 12's serve runs in
    # reload mode and warns that the variable is ignored without --no-reload.
    # Reload mode is worth more here — code edits take effect without a restart.
    "${SERVER_ENV[@]}" setsid nohup php artisan serve \
        --host="$LISTEN_HOST" --port="$LISTEN_PORT" \
        >>"$SERVER_LOG" 2>&1 < /dev/null &

    local i
    for i in $(seq 1 20); do
        if server_running; then
            echo "Server: up on $LISTEN_HOST:$LISTEN_PORT"
            return 0
        fi
        sleep 1
    done
    echo "Server: FAILED to bind $LISTEN_PORT — see $SERVER_LOG" >&2
    return 1
}

start_tunnel() {
    if tunnel_running; then
        echo "Tunnel: supervisor already running (pid $(cat "$TUNNEL_PIDFILE"))"
        return 0
    fi
    if [ ! -f "$SSH_KEY" ]; then
        echo "Tunnel: missing SSH key $SSH_KEY" >&2
        echo "        run: ssh-keygen -t ed25519 -f $SSH_KEY -N \"\" -C thai2d3d-tunnel" >&2
        return 1
    fi

    echo "Tunnel: starting reconnect supervisor"
    : > "$TUNNEL_LOG"
    rm -f "$TUNNEL_PIDFILE"

    # The supervisor records its OWN pid (authoritative: `setsid` may fork, so
    # $! here is not reliably the loop's pid). It becomes a session leader, so
    # `kill -- -PID` later takes down the loop and its ssh child together.
    setsid nohup bash "$PROJECT_DIR/scripts/tunnel-supervisor.sh" \
        "$SSH_KEY" "$TUNNEL_LOG" "$LISTEN_PORT" "$PROJECT_DIR" "$TUNNEL_PIDFILE" \
        >>"$LOG_DIR/tunnel-supervisor.log" 2>&1 < /dev/null &

    if wait_for_url; then
        echo "Tunnel: https://$(current_url)"
    else
        echo "Tunnel: no URL yet — check $TUNNEL_LOG" >&2
        return 1
    fi
}

# APP_URL is kept aligned with the live hostname by the supervisor itself, since
# every reconnect mints a new one.

stop_all() {
    # Kill the supervisor by recorded pid, never by `pkill -f`: a -f pattern
    # containing this script's path also matches the shell that invoked us, and
    # terminates the script mid-run.
    if [ -f "$TUNNEL_PIDFILE" ]; then
        local sup; sup="$(cat "$TUNNEL_PIDFILE" 2>/dev/null)"
        if [ -n "$sup" ]; then
            kill -- -"$sup" 2>/dev/null || kill "$sup" 2>/dev/null
            echo "Tunnel: supervisor stopped (pid $sup)"
        fi
        rm -f "$TUNNEL_PIDFILE"
    else
        echo "Tunnel: no supervisor pidfile"
    fi
    pkill -x ssh 2>/dev/null && echo "Tunnel: ssh stopped"

    local pids
    pids="$(pgrep -f 'artisan serve' 2>/dev/null | tr '\n' ' ')"
    if [ -n "${pids// /}" ]; then
        # shellcheck disable=SC2086
        kill $pids 2>/dev/null
        echo "Server: stopped"
    else
        echo "Server: not running"
    fi
    rm -f "$SERVER_PIDFILE" "$URL_FILE"
}

status() {
    echo "Server:  $(server_running && echo "UP on $LISTEN_HOST:$LISTEN_PORT" || echo DOWN)"
    echo "Tunnel:  $(tunnel_running && echo "supervisor UP (pid $(cat "$TUNNEL_PIDFILE"))" || echo "supervisor DOWN")"
    local url; url="$(current_url)"
    if [ -n "$url" ]; then
        local code
        code="$(curl -s -o /dev/null -m 20 -w '%{http_code}' "https://$url/login" 2>/dev/null)"
        echo "URL:     https://$url  (GET /login -> HTTP ${code:-timeout})"
        echo "APP_URL: $(grep -E '^APP_URL=' .env)"
    else
        echo "URL:     (none — tunnel not connected)"
    fi
    echo "Cron:    $(crontab -l 2>/dev/null | grep -c 'schedule:run') schedule:run entr(ies)"
    echo "DB:      $(db_summary)"
}

# Confirm the CLI and the web server agree on which database is in use. A
# mismatch here is exactly what made login look broken earlier.
db_summary() {
    php artisan tinker --execute='echo config("database.default")." / ".config("database.connections.".config("database.default").".database");' 2>/dev/null | tail -1
}

case "${1:-}" in
    start)
        start_server && start_tunnel
        ;;
    stop)
        stop_all
        ;;
    status)
        status
        ;;
    url)
        current_url | sed 's|^|https://|'
        ;;
    restart)
        stop_all
        sleep 2
        start_server && start_tunnel
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|url}" >&2
        exit 2
        ;;
esac
