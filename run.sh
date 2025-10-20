#!/data/data/com.termux/files/usr/bin/bash
# advanced-web-launcher.sh
# Usage: ./advanced-web-launcher.sh [--base /path/to/base] [--webroot /path/to/.www] [--config /full/path/to/config.php] [--port 0] [--no-cloudflared] [--wait 20] [--debug]
set -euo pipefail

# defaults
BASE="$(pwd)"
WEBROOT=""
CONFIG_PATH=""
PORT=0          # 0 means pick random free port
WAIT=20
NO_CLOUDFLARED=0
DEBUG=0

# helpers
_err(){ printf 'ERROR: %s\n' "$1" >&2; exit 1; }
_warn(){ printf 'WARN: %s\n' "$1" >&2; }
_info(){ printf '%s\n' "$1"; }

# parse args
while [ $# -gt 0 ]; do
  case "$1" in
    --base) BASE="$2"; shift 2;;
    --webroot) WEBROOT="$2"; shift 2;;
    --config) CONFIG_PATH="$2"; shift 2;;
    --port) PORT="$2"; shift 2;;
    --no-cloudflared) NO_CLOUDFLARED=1; shift;;
    --wait) WAIT="$2"; shift 2;;
    --debug) DEBUG=1; shift;;
    -h|--help) printf "Usage: %s [--base BASE] [--webroot WEBROOT] [--config CONFIG_PATH] [--port PORT] [--no-cloudflared] [--wait N] [--debug]\n" "$0"; exit 0;;
    *) _err "Unknown argument: $1";;
  esac
done

# resolve paths
BASE="$(realpath -m "$BASE")"
LOGS="$BASE/.logs"
mkdir -p "$LOGS"
PHP_LOG="$LOGS/php.log"
CF_LOG="$LOGS/cloudflared.log"
# rotated per-run logs
RUN_ID="$(date +%s)_$$"
PHP_LOG_RUN="$LOGS/php_${RUN_ID}.log"
CF_LOG_RUN="$LOGS/cloudflared_${RUN_ID}.log"
: > "$PHP_LOG_RUN" : > "$CF_LOG_RUN"

# minimal package check (attempt install if missing)
need_bin(){
  local miss=0
  for b in "$@"; do
    if ! command -v "$b" >/dev/null 2>&1; then
      miss=1
      _warn "Missing: $b"
    fi
  done
  if [ "$miss" -eq 1 ]; then
    _warn "Attempting to install missing packages via 'pkg' (termux). If install fails, install manually."
    for b in "$@"; do
      command -v "$b" >/dev/null 2>&1 || { pkg update -y >/dev/null 2>&1 || true; pkg install -y "$b" >/dev/null 2>&1 || _warn "Please install: $b"; }
    done
  fi
}

need_bin php curl

# optional helpers (not fatal)
command -v ss >/dev/null 2>&1 || true
command -v lsof >/dev/null 2>&1 || true

# pick a free port
pick_port(){
  local start=20000
  local end=49999
  for i in $(seq 1 120); do
    p=$(( start + RANDOM % (end-start+1) ))
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

# search functions
find_webroot_upwards(){
  local start="$1"
  local maxdepth=30
  local depth=0
  local cwd
  cwd="$(realpath -m "$start")"
  while [ -n "$cwd" ] && [ "$depth" -lt "$maxdepth" ]; do
    if [ -d "$cwd/.www" ]; then
      printf '%s' "$cwd/.www"
      return 0
    fi
    cwd="$(dirname "$cwd")"
    [ "$cwd" = "/" ] && break
    depth=$((depth+1))
  done
  return 1
}

# find a config file anywhere under BASE limited depth
find_config_under_base(){
  local root="$1"
  local result
  result="$(find "$root" -maxdepth 6 -type f -path '*/.www/config/config.php' -print -quit 2>/dev/null || true)"
  if [ -n "$result" ]; then printf '%s' "$result"; return 0; fi
  # fallback: any config.php in .www/config
  result="$(find "$root" -maxdepth 6 -type f -name 'config.php' -path '*/.www/config/*' -print -quit 2>/dev/null || true)"
  if [ -n "$result" ]; then printf '%s' "$result"; return 0; fi
  return 1
}

# resolve WEBROOT
if [ -n "$WEBROOT" ]; then
  WEBROOT="$(realpath -m "$WEBROOT")"
elif [ -n "${WEBROOT:-}" ] && [ -d "${WEBROOT:-}" ]; then
  WEBROOT="$(realpath -m "$WEBROOT")"
else
  # prefer BASE/.www
  if [ -d "$BASE/.www" ]; then
    WEBROOT="$(realpath -m "$BASE/.www")"
  else
    # try upwards from current script dir
    WEBROOT="$(find_webroot_upwards "$BASE" || true)"
    if [ -z "$WEBROOT" ]; then
      WEBROOT="$(find_webroot_upwards "$(pwd)" || true)"
    fi
    if [ -z "$WEBROOT" ]; then
      _warn "No .www found in $BASE or parents. You may set --webroot or export WEBROOT env var."
    fi
  fi
fi

# resolve CONFIG_PATH
if [ -n "$CONFIG_PATH" ]; then
  CONFIG_PATH="$(realpath -m "$CONFIG_PATH")"
elif [ -n "${CONFIG_PATH:-}" ]; then
  CONFIG_PATH="$(realpath -m "${CONFIG_PATH:-}")"
fi

# Auto-find config if not provided
if [ -z "${CONFIG_PATH:-}" ]; then
  if [ -n "$WEBROOT" ] && [ -f "$WEBROOT/config/config.php" ]; then
    CONFIG_PATH="$WEBROOT/config/config.php"
  else
    CONFIG_PATH="$(find_config_under_base "$BASE" || true)"
  fi
fi

# final sanity checks
[ -n "$WEBROOT" ] || _warn "WEBROOT unresolved. Script will still attempt to run but apps may not find .www."
[ -n "$CONFIG_PATH" ] || _warn "CONFIG_PATH unresolved. Export CONFIG_PATH or place config at .www/config/config.php."

# ensure webroot has entrypoint
if [ -n "$WEBROOT" ]; then
  if [ ! -f "$WEBROOT/index.php" ] && [ ! -f "$WEBROOT/tools.php" ]; then
    _err "No index.php or tools.php in webroot: $WEBROOT"
  fi
fi

# pick port if necessary
if [ "$PORT" -eq 0 ]; then
  PORT="$(pick_port)" || _err "no free port available"
fi

# create router
ROUTER="$(mktemp "$LOGS/.php_router_${RUN_ID}.XXXX.php")"
cat > "$ROUTER" <<'PHPROUTER'
<?php
// generated router - sets DOCUMENT_ROOT and CONFIG_PATH envs for included apps
$webroot = getenv('WEBROOT') ?: (isset($argv[1]) ? $argv[1] : null);
if (!$webroot) {
    // attempt common relative path
    $cand = realpath(__DIR__ . '/../.www');
    $webroot = $cand ?: __DIR__ . '/../.www';
}
$docroot = realpath($webroot) ?: $webroot;
putenv('DOCUMENT_ROOT=' . $docroot);
$_SERVER['DOCUMENT_ROOT'] = $docroot;

// ensure CONFIG_PATH set if present under docroot
if (!getenv('CONFIG_PATH')) {
    $maybe = $docroot . '/config/config.php';
    if (file_exists($maybe) && is_readable($maybe)) {
        putenv('CONFIG_PATH=' . $maybe);
    }
}

// normalize
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$requested = $docroot . $uri;

// serve static files directly
if ($uri !== '/' && file_exists($requested) && is_file($requested)) {
    return false;
}

// choose entrypoint
$candidates = [
    $docroot . '/tools.php',
    $docroot . '/index.php'
];
foreach ($candidates as $c) {
    if (file_exists($c)) {
        chdir(dirname($c));
        require $c;
        exit;
    }
}
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "No entrypoint (tools.php or index.php) found in webroot.\n";
echo "DOCUMENT_ROOT={$docroot}\n";
PHPROUTER

# export envs for PHP processes
export WEBROOT="$WEBROOT"
[ -n "${CONFIG_PATH:-}" ] && export CONFIG_PATH="$CONFIG_PATH"
export DOCUMENT_ROOT="$WEBROOT"

# start php built-in server with router
_info "Starting PHP built-in server on 127.0.0.1:${PORT} (webroot: ${WEBROOT:-<unset>})"
nohup php -S 127.0.0.1:"${PORT}" "$ROUTER" -t "${WEBROOT:-.}" >"$PHP_LOG_RUN" 2>&1 &
PHP_PID=$!

# cleanup function
cleanup(){
  _info "Shutting down..."
  if kill -0 "$PHP_PID" >/dev/null 2>&1; then
    kill "$PHP_PID" 2>/dev/null || true
    sleep 0.2
    kill -9 "$PHP_PID" 2>/dev/null || true
  fi
  if [ -n "${CLOUDFLARED_PID:-}" ]; then
    kill "$CLOUDFLARED_PID" 2>/dev/null || true
  fi
  rm -f "$ROUTER"
  exit 0
}
trap cleanup INT TERM EXIT

# verify php server responded
END_CHECK=$((SECONDS + 8))
_started=0
while [ $SECONDS -le $END_CHECK ]; do
  if curl -fs "http://127.0.0.1:${PORT}/tools.php" >/dev/null 2>&1 || curl -fs "http://127.0.0.1:${PORT}/index.php" >/dev/null 2>&1; then
    _started=1; break
  fi
  sleep 0.3
done
[ "$_started" -eq 1 ] || { tail -n 200 "$PHP_LOG_RUN" 2>/dev/null || true; _err "PHP not responding on ${PORT}. See $PHP_LOG_RUN"; }

# optionally start cloudflared
PUBLIC_URL=""
if [ "$NO_CLOUDFLARED" -eq 0 ]; then
  if ! command -v cloudflared >/dev/null 2>&1; then
    _warn "cloudflared not found, skipping tunnel. Set --no-cloudflared to suppress this."
  else
    _info "Starting cloudflared tunnel..."
    nohup cloudflared tunnel --url "http://127.0.0.1:${PORT}" --loglevel info >"$CF_LOG_RUN" 2>&1 &
    CLOUDFLARED_PID=$!
    # wait for trycloudflare url
    END=$((SECONDS + WAIT))
    while [ $SECONDS -le $END ]; do
      PUBLIC_URL="$(grep -Eo 'https?://[a-z0-9-]+\.trycloudflare\.com' "$CF_LOG_RUN" 2>/dev/null | tail -n1 || true)"
      if [ -n "$PUBLIC_URL" ]; then break; fi
      sleep 1
    done
    if [ -z "$PUBLIC_URL" ]; then
      _warn "No public trycloudflare URL found in $CF_LOG_RUN"
    fi
  fi
fi

# print chosen public URL or local URL
if [ -n "$PUBLIC_URL" ]; then
  if [ -f "$WEBROOT/tools.php" ]; then
    printf '%s\n' "${PUBLIC_URL%/}/tools.php"
  else
    printf '%s\n' "${PUBLIC_URL%/}/index.php"
  fi
else
  if [ -f "$WEBROOT/tools.php" ]; then
    printf 'http://127.0.0.1:%s/tools.php\n' "$PORT"
  else
    printf 'http://127.0.0.1:%s/index.php\n' "$PORT"
  fi
fi

# show debug info if requested
if [ "$DEBUG" -eq 1 ]; then
  _info "DEBUG INFO:"
  _info "BASE=$BASE"
  _info "WEBROOT=$WEBROOT"
  _info "CONFIG_PATH=${CONFIG_PATH:-<unset>}"
  _info "ROUTER=$ROUTER"
  _info "PHP_PID=$PHP_PID"
  _info "PHP_LOG_RUN=$PHP_LOG_RUN"
  _info "CF_LOG_RUN=$CF_LOG_RUN"
fi

# keep script running to maintain trap and let user Ctrl-C to cleanup
wait "$PHP_PID" 2>/dev/null || true