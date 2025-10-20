#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail

BASE="$(pwd)"
WWW="$BASE/.www"
CFG="$BASE/config"
LOGS="$BASE/.logs"
PHP_LOG="$LOGS/php.log"
CF_LOG="$LOGS/cloudflared.log"
WAIT=20

_err(){ printf 'ERROR: %s\n' "$1" >&2; exit 1; }

mkdir -p "$WWW" "$CFG" "$LOGS"
: > "$PHP_LOG" : > "$CF_LOG"

need_pkg(){
  for p in "$@"; do
    command -v "$p" >/dev/null 2>&1 || { pkg update -y >/dev/null 2>&1 || true; pkg install -y "$p" >/dev/null 2>&1 || _err "install $p"; }
  done
}
need_pkg php cloudflared curl ss lsof

# sanity: do not create any PHP files. require an existing web UI entrypoint (index.php or tools.php).
if [ ! -d "$WWW" ]; then _err ".www not found"; fi
if [ ! -f "$WWW/index.php" ] && [ ! -f "$WWW/tools.php" ]; then
  printf 'No index.php or tools.php found in %s\n' "$WWW" >&2
  _err "Place your web app files in $WWW before running this script"
fi

# pick random free port
pick_port(){
  for i in $(seq 1 60); do
    p=$((20000 + RANDOM % 30000))
    if command -v ss >/dev/null 2>&1; then
      ss -ltn 2>/dev/null | grep -q ":$p\b" || { printf '%s' "$p"; return 0; }
    elif command -v lsof >/dev/null 2>&1; then
      lsof -ti :"$p" >/dev/null 2>&1 || { printf '%s' "$p"; return 0; }
    else
      printf '%s' "$p"; return 0
    fi
  done
  return 1
}
PORT=$(pick_port) || _err "no free port found"

PHP_LOG="$LOGS/php_${PORT}.log"
CF_LOG="$LOGS/cloudflared_${PORT}.log"
: > "$PHP_LOG" : > "$CF_LOG"

# kill old listeners on that port and old cloudflared
if command -v lsof >/dev/null 2>&1; then
  for pid in $(lsof -ti :"$PORT" 2>/dev/null || true); do kill -9 "$pid" 2>/dev/null || true; done
fi
pkill -f "php -S 127.0.0.1:${PORT}" 2>/dev/null || true
pkill -f "cloudflared tunnel --url" 2>/dev/null || true
sleep 0.2

# start PHP built-in server serving $WWW
nohup php -S 127.0.0.1:"${PORT}" -t "$WWW" >"$PHP_LOG" 2>&1 &
sleep 0.8

# verify server started by requesting either tools.php or index.php
END_CHECK=$((SECONDS+8))
_started=0
while [ $SECONDS -le $END_CHECK ]; do
  if curl -fs "http://127.0.0.1:${PORT}/tools.php" >/dev/null 2>&1 || curl -fs "http://127.0.0.1:${PORT}/index.php" >/dev/null 2>&1; then
    _started=1; break
  fi
  sleep 0.4
done
[ "$_started" -eq 1 ] || { tail -n 40 "$PHP_LOG" 2>/dev/null || true; _err "php not responding on ${PORT}"; }

# start cloudflared tunnel
nohup cloudflared tunnel --url "http://127.0.0.1:${PORT}" --loglevel info >"$CF_LOG" 2>&1 &
sleep 1.5

# wait for trycloudflare URL
END=$((SECONDS + WAIT))
PUBLIC=""
while [ $SECONDS -le $END ]; do
  PUBLIC=$(grep -Eo 'https?://[a-z0-9-]+\.trycloudflare\.com' "$CF_LOG" 2>/dev/null | tail -n1 || true)
  [ -n "$PUBLIC" ] && break
  sleep 1
done
[ -n "$PUBLIC" ] || _err "no public link found"

# print only the tools URL (prefer tools.php if present)
if [ -f "$WWW/tools.php" ]; then
  printf '%s\n' "${PUBLIC%/}/tools.php"
else
  printf '%s\n' "${PUBLIC%/}/index.php"
fi
