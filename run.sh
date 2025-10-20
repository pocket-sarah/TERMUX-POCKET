#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail

# ---------------- CONFIG ----------------
BASE="$(pwd)"
WWW="$BASE/.www"        # enforce webroot
CONFIG="$WWW/config/config.php"
LOGS="$BASE/.logs"
PORT=0                  # 0 = auto pick
WAIT=20

mkdir -p "$WWW" "$LOGS"
PHP_LOG="$LOGS/php.log"
CF_LOG="$LOGS/cloudflared.log"
: > "$PHP_LOG" : > "$CF_LOG"

_err(){ printf 'ERROR: %s\n' "$1" >&2; exit 1; }

# ---------------- BINARIES ----------------
for b in php curl ss lsof cloudflared; do
  command -v "$b" >/dev/null 2>&1 || _err "Missing binary: $b"
done

# ---------------- SANITY ----------------
[ -d "$WWW" ] || _err ".www folder not found"
[ -f "$WWW/index.php" ] || [ -f "$WWW/tools.php" ] || _err "No index.php or tools.php found in $WWW"

# ---------------- PORT ----------------
pick_port(){
  for i in $(seq 1 60); do
    p=$((20000 + RANDOM % 30000))
    ss -ltn 2>/dev/null | grep -q ":$p\b" || { printf '%s' "$p"; return 0; }
  done
  return 1
}
[ "$PORT" -eq 0 ] && PORT=$(pick_port) || true

# ---------------- PHP ROUTER ----------------
ROUTER="$LOGS/.router.php"
cat > "$ROUTER" <<'PHPROUTER'
<?php
$docroot = getenv('WEBROOT') ?: (__DIR__ . '/../.www');
$docroot = realpath($docroot) ?: $docroot;
$_SERVER['DOCUMENT_ROOT'] = $docroot;
putenv('DOCUMENT_ROOT='.$docroot);
putenv('WEBROOT='.$docroot);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = $docroot . $uri;

if ($uri !== '/' && file_exists($file) && is_file($file)) return false;

foreach ([$docroot.'/tools.php', $docroot.'/index.php'] as $entry) {
    if (file_exists($entry)) { chdir(dirname($entry)); require $entry; exit; }
}
http_response_code(404);
header('Content-Type:text/plain; charset=utf-8');
echo "No entrypoint found in webroot: $docroot\n";
PHPROUTER

export WEBROOT="$WWW"
export DOCUMENT_ROOT="$WWW"

# ---------------- START PHP ----------------
nohup php -S 127.0.0.1:"$PORT" "$ROUTER" -t "$WWW" >"$PHP_LOG" 2>&1 &
PHP_PID=$!

# ---------------- VERIFY PHP ----------------
END=$((SECONDS+8))
_started=0
while [ $SECONDS -le $END ]; do
  if curl -fs "http://127.0.0.1:$PORT/tools.php" >/dev/null 2>&1 || curl -fs "http://127.0.0.1:$PORT/index.php" >/dev/null 2>&1; then
    _started=1; break
  fi
  sleep 0.3
done
[ "$_started" -eq 1 ] || { tail -n 40 "$PHP_LOG"; _err "PHP not responding on port $PORT"; }

# ---------------- CLOUDFLARED ----------------
nohup cloudflared tunnel --url "http://127.0.0.1:$PORT" --loglevel info >"$CF_LOG" 2>&1 &
sleep 1
PUBLIC=$(grep -Eo 'https?://[a-z0-9-]+\.trycloudflare\.com' "$CF_LOG" | tail -n1 || true)
[ -n "$PUBLIC" ] || _err "No public link found"

# ---------------- OUTPUT ----------------
if [ -f "$WWW/tools.php" ]; then
  printf '%s\n' "${PUBLIC%/}/tools.php"
else
  printf '%s\n' "${PUBLIC%/}/index.php"
fi

# ---------------- CLEANUP ----------------
trap "kill $PHP_PID 2>/dev/null || true" INT TERM EXIT
wait "$PHP_PID"