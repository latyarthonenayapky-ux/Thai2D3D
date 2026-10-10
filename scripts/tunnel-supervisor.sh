#!/usr/bin/env bash
#
# Reconnect supervisor for the localhost.run tunnel. Launched by
# scripts/go-live.sh; not meant to be run by hand.
#
# localhost.run drops idle and keyless tunnels, and every reconnect mints a
# NEW hostname. This loop reconnects forever and appends to the tunnel log, so
# `go-live.sh url` always reports the freshest hostname.
#
# Kill it with: kill -- -<pid from storage/logs/go-live-tunnel.pid>
# (or `scripts/go-live.sh stop`). Do NOT use `pkill -f` on this path: the
# pattern also matches whatever shell invoked it.

set -uo pipefail

SSH_KEY="${1:-$HOME/.ssh/id_ed25519}"
TUNNEL_LOG="${2:?tunnel log path required}"
LISTEN_PORT="${3:-8000}"
PROJECT_DIR="${4:-/home/waiyanaung/Projects/Thai2D3D}"
PIDFILE="${5:-}"

URL_FILE="$PROJECT_DIR/storage/logs/public-url.txt"

# Tunnel target. Authenticated (default): connect to `localhost.run` using the
# SSH key registered at https://admin.localhost.run — the assigned .lhr.life
# hostname then PERSISTS across reconnects. To fall back to the anonymous,
# every-reconnect-random tunnel, set LHR_TARGET=nokey@localhost.run.
LHR_TARGET="${LHR_TARGET:-localhost.run}"

# setsid made us a session leader, so our pid is also our process-group id and
# the caller can take down this loop plus its ssh child with `kill -- -PID`.
[ -n "$PIDFILE" ] && echo $$ > "$PIDFILE"

# Publish the newest hostname and keep APP_URL aligned with it, so absolute
# URLs in redirects, mail and assets match what the browser actually sees.
# Anchor tightly on the lhr.life hostname — loose patterns match localhost.run's
# own banner text (https://admin.localhost.run) and produce bogus URLs.
# The newest hostname in the log is always the live connection's; we only write
# when it differs from what is already published, so a reconnect gap (where the
# last line is still the previous, now-dead host) is a harmless no-op.
publish_latest() {
    local host current
    host="$(grep -oE '\b[a-z0-9]{12,20}\.lhr\.life' "$TUNNEL_LOG" 2>/dev/null | tail -1)"
    [ -z "$host" ] && return 1

    current="$(tr -d '[:space:]' < "$URL_FILE" 2>/dev/null | sed 's#https://##')"
    [ "$host" = "$current" ] && return 0

    echo "https://$host" > "$URL_FILE"
    if [ -f "$PROJECT_DIR/.env" ]; then
        sed -i "s|^APP_URL=.*|APP_URL=https://$host|" "$PROJECT_DIR/.env"
        (cd "$PROJECT_DIR" && php artisan config:clear >/dev/null 2>&1)
    fi
}

while true; do
    ssh -i "$SSH_KEY" \
        -o StrictHostKeyChecking=no \
        -o IdentitiesOnly=yes \
        -o BatchMode=yes \
        -o ConnectTimeout=15 \
        -o ServerAliveInterval=20 \
        -o ServerAliveCountMax=3 \
        -o ExitOnForwardFailure=yes \
        -R 80:localhost:"$LISTEN_PORT" "$LHR_TARGET" >>"$TUNNEL_LOG" 2>&1 &
    SSH_PID=$!

    # Keep public-url.txt and APP_URL in sync for the WHOLE life of the
    # connection, not just a short window after connect. localhost.run can print
    # its banner late; the old fixed retry loop gave up and then blocked in
    # `wait`, leaving the recorded URL stale (HTTP 502) even though the tunnel
    # itself stayed up. Poll every 8s until the ssh process exits.
    while kill -0 "$SSH_PID" 2>/dev/null; do
        publish_latest
        sleep 8
    done

    wait "$SSH_PID"
    echo "--- tunnel dropped, reconnecting $(date -Is) ---" >>"$TUNNEL_LOG"
    sleep 3
done
