#!/usr/bin/env bash
#
# Capture the local X11 desktop to a JPEG that the dashboard serves.
# Runs as the interactive desktop user (needs access to the X display),
# writes to /run/systeminfo/screen.jpg which www-data can read.
#
set -uo pipefail

DISPLAY_NAME="${SYSTEMINFO_DISPLAY:-:0.0}"
INTERVAL="${SYSTEMINFO_SCREEN_INTERVAL:-4}"
WIDTH="${SYSTEMINFO_SCREEN_WIDTH:-1280}"
QUALITY="${SYSTEMINFO_SCREEN_QUALITY:-5}"
OUT_DIR="${SYSTEMINFO_SCREEN_DIR:-/run/systeminfo}"
OUT="${OUT_DIR}/screen.jpg"
TMP="${OUT_DIR}/.screen.$$.jpg"

mkdir -p "$OUT_DIR"

log() {
    printf '[%s] %s\n' "$(date '+%F %T')" "$*"
}

log "capture service starting (display=${DISPLAY_NAME}, interval=${INTERVAL}s, width=${WIDTH}px)"

while true; do
    started=$(date +%s)

    if ffmpeg -hide_banner -loglevel error \
        -f x11grab -video_size "${SYSTEMINFO_SCREEN_SIZE:-1920x1080}" -i "${DISPLAY_NAME}" \
        -frames:v 1 -vf "scale=${WIDTH}:-2" -q:v "${QUALITY}" -y "$TMP" 2>/dev/null; then
        mv -f "$TMP" "$OUT"
    else
        log "capture failed (is the X session running?)"
        rm -f "$TMP"
    fi

    elapsed=$(( $(date +%s) - started ))
    sleep_for=$(( INTERVAL - elapsed ))
    [ "$sleep_for" -gt 0 ] && sleep "$sleep_for"
done
