#!/usr/bin/env bash
#
# Packs the deployable code bundle for "Deploy to WYA VPS.cmd".
#
# The Windows helper has no rsync, no sshpass and no SSH key, so the bundle is
# built here and shipped to the server as one small tar.gz. Two shipping modes:
# a base64 stream that rides inside a single SSH session, or a plain tarball the
# helper uploads with scp.
#
# Usage:
#   scripts/vps-bundle.sh <output-stream-file> [remote-bundle-path]
#   scripts/vps-bundle.sh --tarball <output.tar.gz>
#
# The --tarball form is for the scp route: Git's ssh.exe reads its password from
# stdin when stdin is redirected, so an upload that pipes a file never shows the
# prompt. The helper uploads with scp instead, which prompts normally.

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${1:?usage: scripts/vps-bundle.sh <output-stream-file|--tarball DEST> [remote-bundle-path]}"
MODE=stream
if [ "$OUT" = '--tarball' ]; then
    MODE=tarball
    OUT="${2:?usage: scripts/vps-bundle.sh --tarball <output.tar.gz>}"
    REMOTE_BUNDLE=''
else
    REMOTE_BUNDLE="${2:-/tmp/wya-update.tar.gz}"
fi
MAX_BYTES="${MAX_BYTES:-5000000}"

command -v tar >/dev/null || { echo 'tar is missing - install Git for Windows fully.'; exit 1; }
command -v base64 >/dev/null || { echo 'base64 is missing - install Git for Windows fully.'; exit 1; }
[ -f "$REPO_DIR/scripts/vps-deploy.sh" ] || { echo 'scripts/vps-deploy.sh is missing.'; exit 1; }

# Only the application files travel. .env, vendor, storage and every local-only
# folder stay on this machine, so the live server's secrets can never be
# overwritten or shipped.
TAR_EXCLUDES=(
    --exclude=.git --exclude=.github --exclude=vendor --exclude=node_modules
    --exclude=storage --exclude=bootstrap/cache --exclude=.env --exclude=.env.*
    --exclude=desktop --exclude=release --exclude=mobile --exclude=tests
    --exclude='*.sqlite' --exclude='*.log' --exclude=phpunit.xml
    --exclude='*.zip' --exclude=.history --exclude=.vscode --exclude=.qoder
    --exclude=wya.png
)

umask 077
PACK_DIR="$(mktemp -d)"
trap 'rm -rf "$PACK_DIR"' EXIT
TARBALL="$PACK_DIR/wya-update.tar.gz"

tar -czf "$TARBALL" "${TAR_EXCLUDES[@]}" -C "$REPO_DIR" .
FILES=$(tar -tf "$TARBALL" | wc -l)
BYTES=$(wc -c < "$TARBALL")
if [ "$BYTES" -gt "$MAX_BYTES" ]; then
    echo "The code bundle is $BYTES bytes, over the ${MAX_BYTES} limit, so the"
    echo 'packing grabbed vendor/desktop/release. Nothing was written for upload.'
    exit 1
fi

if [ "$MODE" = tarball ]; then
    cp -f "$TARBALL" "$OUT"
    echo "TARBALL READY  tarball=$OUT  bytes=$BYTES  files=$FILES"
    exit 0
fi

: > "$OUT"
{
    printf '%s\n' 'set -e'
    printf "base64 -d > '%s' <<'WYABUNDLE_EOF'\n" "$REMOTE_BUNDLE"
    base64 -w 76 "$TARBALL"
    printf '%s\n' 'WYABUNDLE_EOF'
    printf "echo 'code bundle received at %s (%s bytes, %s files)'\n" "$REMOTE_BUNDLE" "$BYTES" "$FILES"
    cat "$REPO_DIR/scripts/vps-deploy.sh"
} > "$OUT"

SIZE=$(wc -c < "$OUT")
echo "STREAM READY  stream=$OUT  bytes=$SIZE  bundle=$BYTES  files=$FILES"
