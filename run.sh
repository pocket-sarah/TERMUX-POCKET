#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail

# --- CONFIG ---
PORT=8080
BASE="$HOME/build/POCKET"
WWW="$BASE/.www"
LOGS="$BASE/.logs"
PHP_LOG="$LOGS/php_${PORT}.log"
CLOUDFLARED_LOG="$LOGS/cloudflared_${PORT}.log"

# ensure dirs
mkdir -p "$WWW" "$LOGS"

# create minimal index.php if missing
if [ ! -f "$WWW/index.php" ]; then
  printf '<?php echo "ok"; ?>\n' > "$WWW/index.php" || exit 1
fi

# create log files if missing
: > "$PHP_LOG"
: > "$CLOUDFLARED_LOG"

# set safe permissions where sensible
chmod 600 "$PHP_LOG" "$CLOUDFLARED_LOG" 2>/dev/null || true
chmod 644 "$WWW/index.php" 2>/dev/null || true

# quick verification output (optional)
printf 'BASE=%s\nWWW=%s\nPHP_LOG=%s\nCLOUDFLARED_LOG=%s\n' "$BASE" "$WWW" "$PHP_LOG" "$CLOUDFLARED_LOG"

# --- 1) Ensure Cloudflared installed ---
if ! command -v cloudflared >/dev/null 2>&1; then
  echo "[INFO] Installing cloudflared..."
  pkg update -y
  pkg install cloudflared -y
fi

echo "[INFO] Cloudflared version: $(cloudflared --version)"

# --- 2) Check index.php ---
IDX="$WWW/index.php"
if [ -f "$IDX" ]; then
  echo "[OK] index.php exists: $IDX"
  head -c 200 "$IDX" || true
  echo -e "\n---- end head ----"
else
  echo "[WARN] MISSING: $IDX"
fi

# --- 3) Kill old PHP server ---
pkill -f "php -S 127.0.0.1:${PORT}" 2>/dev/null || true
sleep 0.3

# --- 4) Start PHP server ---
echo "[INFO] Starting PHP server on 127.0.0.1:${PORT}..."
nohup php -S 127.0.0.1:${PORT} -t "$WWW" >"$PHP_LOG" 2>&1 &
PHP_PID=$!
sleep 0.8
echo "[INFO] PHP PID: $PHP_PID"

# --- 5) Confirm listener ---
echo "[INFO] Listener:"
ss -ltnp 2>/dev/null | grep ":${PORT}" || true

# --- 6) Test index.php ---
echo "[INFO] Testing HTTP /index.php..."
curl -v -L --max-time 5 "http://127.0.0.1:${PORT}/index.php" 2>&1 | sed -n '1,50p' || true

# --- 7) Tail PHP log ---
echo "[INFO] Last 60 lines of PHP log:"
tail -n 60 "$PHP_LOG" 2>/dev/null || echo "(no php log yet)"

# --- 8) Kill old Cloudflared ---
pkill -f "cloudflared tunnel --url" 2>/dev/null || true
sleep 0.3

# --- 9) Start Cloudflared tunnel ---
echo "[INFO] Launching Cloudflared tunnel to PHP server..."
nohup cloudflared tunnel --url "http://127.0.0.1:${PORT}" --loglevel info >"$CLOUDFLARED_LOG" 2>&1 &
CLOUD_PID=$!
sleep 2

# --- 10) Wait for URL and print ---
echo "[INFO] Waiting for trycloudflare URL..."
END=$((SECONDS+20))
PUBLIC_URL=""
while [ $SECONDS -le $END ]; do
  PUBLIC_URL=$(grep -Eo 'https?://[a-z0-9-]+\.trycloudflare\.com' "$CLOUDFLARED_LOG" 2>/dev/null | tail -n1 || true)
  if [ -n "$PUBLIC_URL" ]; then break; fi
  sleep 1
done

if [ -z "$PUBLIC_URL" ]; then
  echo "[WARN] No public URL yet. Check Cloudflared log: $CLOUDFLARED_LOG"
else
  echo "[OK] Public URL: $PUBLIC_URL"
fi

echo "[INFO] PHP and Cloudflared running."
echo "[INFO] Kill PHP: pkill -f 'php -S 127.0.0.1:${PORT}'"
echo "[INFO] Kill Cloudflared: pkill -f 'cloudflared tunnel --url'"
