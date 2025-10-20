#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail

# --- BASE PATH DETECTION ---
SCRIPT_PATH="$(realpath "$0")"
BASE_DIR="$(dirname "$SCRIPT_PATH")"
WWW_DIR="$BASE_DIR/.www"
CFG_DIR="$BASE_DIR/config"
LOGS_DIR="$BASE_DIR/.logs"

PHP_LOG="$LOGS_DIR/php.log"
CF_LOG="$LOGS_DIR/cloudflared.log"
WAIT_TIME=20

err() { echo "ERROR: $1" >&2; exit 1; }

mkdir -p "$WWW_DIR" "$CFG_DIR" "$LOGS_DIR"
: > "$PHP_LOG"
: > "$CF_LOG"

# --- Dependency Check ---
need_pkg() {
  for p in "$@"; do
    command -v "$p" >/dev/null 2>&1 || {
      pkg update -y >/dev/null 2>&1 || true
      pkg install -y "$p" >/dev/null 2>&1 || err "Failed to install $p"
    }
  done
}
need_pkg php cloudflared curl lsof ss

# --- Validate Webroot ---
[ -d "$WWW_DIR" ] || err ".www directory not found beside script"
if [ ! -f "$WWW_DIR/index.php" ] && [ ! -f "$WWW_DIR/tools.php" ]; then
  err "Missing index.php or tools.php in $WWW_DIR"
fi

# --- Random Free Port ---
pick_port() {
  for _ in $(seq 1 60); do
    p=$((20000 + RANDOM % 30000))
    if ss -ltn 2>/dev/null | grep -q ":$p\b"; then continue; fi
    echo "$p"; return 0
  done
  return 1
}
PORT="$(pick_port)" || err "No free port found"

PHP_LOG="$LOGS_DIR/php_${PORT}.log"
CF_LOG="$LOGS_DIR/cloudflared_${PORT}.log"
: > "$PHP_LOG"
: > "$CF_LOG"

# --- Cleanup Old Listeners ---
pkill -f "php -S 127.0.0.1:${PORT}" 2>/dev/null || true
pkill -f "cloudflared tunnel --url" 2>/dev/null || true
sleep 0.2

# --- Launch PHP Server with Correct Webroot ---
(
  cd "$WWW_DIR"
  nohup php -S 127.0.0.1:"$PORT" -t . >"$PHP_LOG" 2>&1 &
)
sleep 1

# --- Verify Server ---
END_CHECK=$((SECONDS + 10))
while [ $SECONDS -le $END_CHECK ]; do
  if curl -fs "http://127.0.0.1:${PORT}/tools.php" >/dev/null 2>&1 || \
     curl -fs "http://127.0.0.1:${PORT}/index.php" >/dev/null 2>&1; then
    break
  fi
  sleep 0.5
done

# --- Launch Cloudflared Tunnel ---
nohup cloudflared tunnel --url "http://127.0.0.1:${PORT}" --loglevel info >"$CF_LOG" 2>&1 &
sleep 2

# --- Extract Public URL ---
END=$((SECONDS + WAIT_TIME))
PUBLIC=""
while [ $SECONDS -le $END ]; do
  PUBLIC=$(grep -Eo 'https://[a-z0-9-]+\.trycloudflare\.com' "$CF_LOG" | tail -n1 || true)
  [ -n "$PUBLIC" ] && break
  sleep 1
done
[ -n "$PUBLIC" ] || err "No Cloudflare public link found"

# --- Output Final URL ---
if [ -f "$WWW_DIR/tools.php" ]; then
  echo "${PUBLIC%/}/tools.php"
else
  echo "${PUBLIC%/}/index.php"
fi