#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import json
import math
import re
import shutil
import socket
import subprocess
import threading
import time
from collections import defaultdict
from pathlib import Path

import requests
from telebot import TeleBot, types


# ============================================================
# CONFIG LOADING
# ============================================================

BASE = Path(__file__).resolve().parent
CONFIG_FILE = BASE / "config.json"

if not CONFIG_FILE.exists():
    raise SystemExit("config.json not found. Create it with BOT_TOKEN and OWNER_CHAT_ID.")

try:
    cfg = json.loads(CONFIG_FILE.read_text(encoding="utf-8"))
except Exception as e:
    raise SystemExit("Failed to read config.json: %s" % e)

BOT_TOKEN = (cfg.get("BOT_TOKEN") or "").strip()
OWNER_CHAT_ID = cfg.get("OWNER_CHAT_ID")

if not BOT_TOKEN:
    raise SystemExit("BOT_TOKEN missing or empty in config.json")
if OWNER_CHAT_ID is None:
    raise SystemExit("OWNER_CHAT_ID missing in config.json")

BOT_NAME = "Sarah"
PROJECT_NAME = "PROJECT-SARAH"

WWW = BASE / "www"
DOT_WWW = BASE / ".www"
LOGS = BASE / ".logs"; LOGS.mkdir(exist_ok=True)
DATA = BASE / ".data"; DATA.mkdir(exist_ok=True)

WATCHERS_FILE = DATA / "watchers.json"
USAGE_FILE = DATA / "usage.json"
INTENT_DB_FILE = DATA / "intents.json"

PHP_BIN = shutil.which("php") or "php"
CF_BIN = shutil.which("cloudflared") or "cloudflared"
CF_RE = re.compile(r"https://[a-z0-9\-]+\.trycloudflare\.com")

bot = TeleBot(BOT_TOKEN, parse_mode="Markdown", threaded=True)


# ============================================================
# STATE
# ============================================================

class State(object):
    def __init__(self):
        self.url = ""
        self.url_short = ""
        self.port = 0
        self.started = 0
        self.watchers = set()
        self.last_online = None

S = State()

USAGE = {}         # {chat_id: {command: count}}
INTENT_DB = {}     # {intent_name: {"examples": [...], "responses": [...]} }
USER_MODE = {}     # {chat_id: "menu"|"chat"}
TEACH_PENDING = {} # {chat_id: "intent_name"}


# ============================================================
# PERSISTENCE
# ============================================================

def load_watchers():
    if WATCHERS_FILE.exists():
        try:
            S.watchers = set(json.loads(WATCHERS_FILE.read_text(encoding="utf-8")))
        except Exception:
            S.watchers = set()

def save_watchers():
    try:
        WATCHERS_FILE.write_text(json.dumps(list(S.watchers)), encoding="utf-8")
    except Exception:
        pass

def load_usage():
    global USAGE
    if USAGE_FILE.exists():
        try:
            USAGE = json.loads(USAGE_FILE.read_text(encoding="utf-8"))
        except Exception:
            USAGE = {}
    else:
        USAGE = {}

def save_usage():
    try:
        USAGE_FILE.write_text(json.dumps(USAGE), encoding="utf-8")
    except Exception:
        pass

def load_intents():
    global INTENT_DB
    if INTENT_DB_FILE.exists():
        try:
            INTENT_DB = json.loads(INTENT_DB_FILE.read_text(encoding="utf-8"))
        except Exception:
            INTENT_DB = {}
    else:
        INTENT_DB = {}

    if not isinstance(INTENT_DB, dict):
        INTENT_DB = {}
    for k, v in list(INTENT_DB.items()):
        if not isinstance(v, dict):
            INTENT_DB[k] = {"examples": [], "responses": []}
            continue
        if "examples" not in v or not isinstance(v["examples"], list):
            v["examples"] = []
        if "responses" not in v or not isinstance(v["responses"], list):
            v["responses"] = []


def save_intents():
    try:
        INTENT_DB_FILE.write_text(json.dumps(INTENT_DB), encoding="utf-8")
    except Exception:
        pass


# ============================================================
# LOGGING + UTIL
# ============================================================

def log(msg):
    ts = time.strftime("%Y-%m-%d %H:%M:%S")
    line = "[%s] %s\n" % (ts, msg)
    print(line, end="")
    with open(LOGS / "debug.log", "a", encoding="utf-8") as f:
        f.write(line)

def free_port():
    s = socket.socket()
    s.bind(("127.0.0.1", 0))
    p = s.getsockname()[1]
    s.close()
    return p

def http_ok(url, timeout=6):
    try:
        r = requests.head(url, timeout=timeout, allow_redirects=True)
        if r.status_code in (405, 403):
            r = requests.get(url, timeout=timeout)
        return 200 <= r.status_code < 300
    except Exception:
        return False

def shorten(url):
    try:
        r = requests.get("https://clck.ru/--", params={"url": url}, timeout=6)
        t = r.text.strip()
        if r.ok and t.startswith("http"):
            return t
    except Exception:
        pass
    return url

def ensure_www():
    DOT_WWW.mkdir(exist_ok=True)
    splash = DOT_WWW / "splash.php"
    if not splash.exists():
        splash.write_text("<?php http_response_code(200); ?>OK", encoding="utf-8")
    admin = DOT_WWW / "blacklist.php"
    if not admin.exists():
        admin.write_text("<?php http_response_code(200); ?>ADMIN", encoding="utf-8")

def clone_www():
    ensure_www()
    if WWW.exists():
        shutil.rmtree(WWW)
    shutil.copytree(DOT_WWW, WWW)


# ============================================================
# STACK CONTROL
# ============================================================

def kill_stack():
    subprocess.run(["pkill", "-f", "php -S"], check=False)
    subprocess.run(["pkill", "-f", "cloudflared"], check=False)

def start_stack():
    kill_stack()
    clone_www()

    port = free_port()
    php_log = LOGS / ("php_%d.log" % port); php_log.touch()
    cf_log = LOGS / ("cf_%d.log" % port); cf_log.touch()

    subprocess.Popen(
        [PHP_BIN, "-S", "127.0.0.1:%d" % port, "-t", str(WWW)],
        stdout=open(php_log, "ab"),
        stderr=subprocess.STDOUT
    )

    subprocess.Popen(
        [CF_BIN, "tunnel", "--url", "http://127.0.0.1:%d" % port, "--no-autoupdate"],
        stdout=open(cf_log, "ab"),
        stderr=subprocess.STDOUT
    )

    found = ""
    t0 = time.time()

    while time.time() - t0 < 25:
        try:
            txt = cf_log.read_text(encoding="utf-8", errors="ignore")
            m = CF_RE.search(txt)
            if m:
                found = m.group(0)
                break
        except Exception:
            pass
        time.sleep(0.2)

    if not found:
        log("Tunnel not found")
        return False

    S.url = found
    S.url_short = shorten(found)
    S.port = port
    S.started = time.time()
    S.last_online = True
    log("Started on :%d -> %s" % (port, S.url))
    return True


# ============================================================
# ADAPTIVE COMMAND USAGE
# ============================================================

def record_usage(cid, cmd):
    cid_str = str(cid)
    if cid_str not in USAGE:
        USAGE[cid_str] = {}
    USAGE[cid_str][cmd] = USAGE[cid_str].get(cmd, 0) + 1
    save_usage()

def top_commands(cid, allowed, limit=3):
    cid_str = str(cid)
    stats = USAGE.get(cid_str, {})
    items = [(cmd, stats.get(cmd, 0)) for cmd in allowed]
    items.sort(key=lambda x: (-x[1], x[0]))
    hot = [cmd for cmd, count in items if count > 0]
    return hot[:limit]


# ============================================================
# MODE CONTROL
# ============================================================

def set_mode(cid, mode):
    USER_MODE[str(cid)] = mode

def get_mode(cid):
    return USER_MODE.get(str(cid), "menu")


# ============================================================
# LOCAL INTENT ENGINE
# ============================================================

def vectorize(text):
    words = re.findall(r"[a-zA-Z0-9]+", text.lower())
    vec = defaultdict(float)
    for i, w in enumerate(words):
        vec[w] += 1.0 + (i / 100.0)
    return dict(vec)

def similarity(v1, v2):
    dot = 0.0
    for k, v in v1.items():
        if k in v2:
            dot += v * v2[k]
    mag1 = math.sqrt(sum(x * x for x in v1.values()))
    mag2 = math.sqrt(sum(x * x for x in v2.values()))
    if mag1 == 0 or mag2 == 0:
        return 0.0
    return dot / (mag1 * mag2)

def ensure_intent(intent_name):
    if intent_name not in INTENT_DB:
        INTENT_DB[intent_name] = {"examples": [], "responses": []}

def learn_example(intent_name, example):
    ensure_intent(intent_name)
    INTENT_DB[intent_name]["examples"].append(example)
    save_intents()

def learn_response(intent_name, response):
    ensure_intent(intent_name)
    INTENT_DB[intent_name]["responses"].append(response)
    save_intents()

def respond_to_intent(intent_name):
    data = INTENT_DB.get(intent_name, {})
    responses = data.get("responses") or []
    if not responses:
        return "I recognize that pattern, but there is no stored answer yet."
    return responses[-1]

def find_intent_for_text(text):
    if not INTENT_DB:
        return None
    vec = vectorize(text)
    best_intent = None
    best_score = 0.0
    for intent_name, data in INTENT_DB.items():
        for ex in data.get("examples", []):
            ex_vec = vectorize(ex)
            s = similarity(vec, ex_vec)
            if s > best_score:
                best_score = s
                best_intent = intent_name
    if best_score < 0.2:
        return None
    return best_intent


# ============================================================
# TOOL INTENT DETECTION
# ============================================================

TOOL_TEMPLATES = {
    "start_stack": [
        "start server",
        "start stack",
        "bring system up",
        "launch everything",
        "boot stack",
    ],
    "stop_stack": [
        "stop server",
        "stop stack",
        "shut everything down",
        "kill stack",
    ],
    "status": [
        "check status",
        "how is the server",
        "system status",
        "is it online",
    ],
    "show_links": [
        "show links",
        "tunnel links",
        "give me urls",
        "show endpoints",
    ],
    "view_logs": [
        "show logs",
        "view logs",
        "debug output",
        "what happened",
    ],
}

def detect_tool_intent(text):
    vec = vectorize(text)
    best_tool = None
    best_score = 0.0
    for tool, phrases in TOOL_TEMPLATES.items():
        for p in phrases:
            s = similarity(vec, vectorize(p))
            if s > best_score:
                best_score = s
                best_tool = tool
    if best_score < 0.25:
        return None
    return best_tool


def handle_tool_from_chat(cid, tool_name):
    if tool_name == "start_stack":
        ok = start_stack()
        if ok:
            return "Stack started. URL: %s" % (S.url_short or S.url)
        return "Failed to start stack."

    if tool_name == "stop_stack":
        kill_stack()
        S.url = ""
        S.url_short = ""
        S.port = 0
        S.started = 0
        S.last_online = None
        return "Stack stopped."

    if tool_name == "status":
        if not S.url:
            return "Stack is offline."
        online = http_ok("%s/splash.php" % S.url.rstrip("/"))
        uptime = int(time.time() - S.started) if S.started else 0
        return "Online: %s\nUptime: %ds\nURL: %s" % (online, uptime, S.url_short or S.url)

    if tool_name == "show_links":
        if not S.url:
            return "Stack is offline."
        splash = shorten("%s/splash.php" % S.url.rstrip("/"))
        admin = shorten("%s/blacklist.php" % S.url.rstrip("/"))
        return "Business: %s\nAdmin: %s" % (splash, admin)

    if tool_name == "view_logs":
        p = LOGS / "debug.log"
        if p.exists():
            data = p.read_text(encoding="utf-8", errors="ignore")
            data = data[-2000:]
        else:
            data = "No logs yet."
        return "Recent logs:\n\n%s" % data

    return "Tool intent detected but not implemented."


def chat_intent_reply(cid, text):
    if cid in TEACH_PENDING:
        intent_name = TEACH_PENDING.pop(cid)
        learn_response(intent_name, text)
        return "Stored that as the answer for this type of message."

    tool = detect_tool_intent(text)
    if tool:
        return handle_tool_from_chat(cid, tool)

    intent = find_intent_for_text(text)
    if intent:
        return respond_to_intent(intent)

    intent_name = "intent_%d" % (len(INTENT_DB) + 1)
    learn_example(intent_name, text)
    TEACH_PENDING[cid] = intent_name
    return "I do not recognize this yet. Tell me how I should respond to messages like that."


# ============================================================
# UNIQUE ROW HELPER FOR REPLY KEYBOARD
# ============================================================

def unique_rows(kb, rows):
    seen = set()
    for row in rows:
        filtered = [b for b in row if b not in seen]
        if filtered:
            kb.row(*filtered)
            for b in filtered:
                seen.add(b)
    return kb


# ============================================================
# REPLY KEYBOARD MENUS
# ============================================================

def main_menu(cid):
    kb = types.ReplyKeyboardMarkup(resize_keyboard=True)
    base_rows = [
        ["System", "Links"],
        ["Logs", "Tools"],
        ["Chat", "Help"],
    ]
    quick = top_commands(cid, ["Start", "Stop", "Status", "Show Links", "View Logs"], 3)
    if quick:
        base_rows.append(quick)
    return unique_rows(kb, base_rows)

def system_menu(cid):
    kb = types.ReplyKeyboardMarkup(resize_keyboard=True)
    base = []
    if not S.url:
        base.append(["Start"])
    else:
        base.append(["Stop", "Status"])
    base.append(["Quick Ops"])
    base.append(["Back"])
    quick = top_commands(cid, ["Start", "Stop", "Status"], 2)
    if quick:
        base.append(quick)
    return unique_rows(kb, base)

def logs_menu(cid):
    kb = types.ReplyKeyboardMarkup(resize_keyboard=True)
    base = [
        ["View Logs"],
        ["Back"],
    ]
    return unique_rows(kb, base)

def tools_menu(cid):
    kb = types.ReplyKeyboardMarkup(resize_keyboard=True)
    base = [
        ["Reset State", "Reset Watchers"],
        ["Back"],
    ]
    return unique_rows(kb, base)

def chat_menu(cid):
    kb = types.ReplyKeyboardMarkup(resize_keyboard=True)
    base = [["Exit Chat"]]
    return unique_rows(kb, base)


# ============================================================
# INLINE KEYBOARDS (LINKS, LOGS)
# ============================================================

def inline_links_keyboard():
    if not S.url:
        return None
    base = S.url.rstrip("/")
    splash = shorten("%s/splash.php" % base)
    admin = shorten("%s/blacklist.php" % base)
    kb = types.InlineKeyboardMarkup(row_width=1)
    kb.add(
        types.InlineKeyboardButton("Business page", url=splash),
        types.InlineKeyboardButton("Admin page", url=admin),
        types.InlineKeyboardButton("Base URL", url=S.url_short or S.url),
    )
    return kb

def inline_logs_keyboard():
    kb = types.InlineKeyboardMarkup(row_width=2)
    kb.add(
        types.InlineKeyboardButton("Tail 200", callback_data="logs_200"),
        types.InlineKeyboardButton("Tail 1000", callback_data="logs_1000"),
    )
    return kb


# ============================================================
# TELEGRAM MESSAGE HANDLERS
# ============================================================

INTRO = (
    "%s - %s\n\n"
    "Hybrid control panel with self-learning chat, smart menus, and inline panels."
) % (BOT_NAME, PROJECT_NAME)

@bot.message_handler(commands=["start", "menu", "home"])
def cmd_start(m):
    cid = m.chat.id
    S.watchers.add(cid); save_watchers()
    set_mode(cid, "menu")
    bot.send_message(cid, INTRO, reply_markup=main_menu(cid))


@bot.message_handler(func=lambda m: True)
def main_handler(m):
    cid = m.chat.id
    text = (m.text or "").strip()
    mode = get_mode(cid)

    # CHAT MODE
    if mode == "chat":
        if text == "Exit Chat":
            set_mode(cid, "menu")
            bot.send_message(cid, "Exited chat mode.", reply_markup=main_menu(cid))
            return
        reply = chat_intent_reply(cid, text)
        bot.send_message(cid, reply, reply_markup=chat_menu(cid))
        return

    # MENU MODE

    # MAIN NAVIGATION
    if text == "System":
        record_usage(cid, "System")
        bot.send_message(cid, "System control:", reply_markup=system_menu(cid))
        return

    if text == "Links":
        record_usage(cid, "Links")
        if not S.url:
            bot.send_message(cid, "Stack is offline, no links available.")
            return
        kb_inline = inline_links_keyboard()
        bot.send_message(cid, "Link panel:", reply_markup=kb_inline)
        return

    if text == "Logs":
        record_usage(cid, "Logs")
        kb_inline = inline_logs_keyboard()
        bot.send_message(cid, "Log console. Choose tail size:", reply_markup=kb_inline)
        return

    if text == "Tools":
        record_usage(cid, "Tools")
        bot.send_message(cid, "Tools:", reply_markup=tools_menu(cid))
        return

    if text == "Chat":
        record_usage(cid, "Chat")
        set_mode(cid, "chat")
        bot.send_message(
            cid,
            "Chat mode enabled.\n"
            "- Speak naturally.\n"
            "- I will try to recognize intent and call internal tools.\n"
            "- If I cannot answer, I will ask you to teach me.\n"
            "Send 'Exit Chat' to go back.",
            reply_markup=chat_menu(cid)
        )
        return

    if text == "Help":
        record_usage(cid, "Help")
        bot.send_message(
            cid,
            "System: start, stop, status, quick ops\n"
            "Links: inline menu with URLs\n"
            "Logs: inline log tail selector\n"
            "Tools: reset state or watchers\n"
            "Chat: self-learning mode that can call tools.\n\n"
            "Menus adapt based on what you use most.",
            reply_markup=main_menu(cid)
        )
        return

    # SYSTEM LAYER
    if text == "Start":
        record_usage(cid, "Start")
        bot.send_message(cid, "Starting stack...", reply_markup=system_menu(cid))
        ok = start_stack()
        if ok:
            bot.send_message(cid, "Started at %s" % (S.url_short or S.url), reply_markup=system_menu(cid))
        else:
            bot.send_message(cid, "Start failed.", reply_markup=system_menu(cid))
        return

    if text == "Stop":
        record_usage(cid, "Stop")
        kill_stack()
        S.url = ""
        S.url_short = ""
        S.port = 0
        S.started = 0
        S.last_online = None
        bot.send_message(cid, "Stopped.", reply_markup=system_menu(cid))
        return

    if text == "Status":
        record_usage(cid, "Status")
        if not S.url:
            bot.send_message(cid, "Stack is offline.", reply_markup=system_menu(cid))
            return
        online = http_ok("%s/splash.php" % S.url.rstrip("/"))
        uptime = int(time.time() - S.started) if S.started else 0
        bot.send_message(
            cid,
            "Online: %s\nUptime: %ds\nURL: %s" % (online, uptime, S.url_short or S.url),
            reply_markup=system_menu(cid)
        )
        return

    if text == "Quick Ops":
        record_usage(cid, "Quick Ops")
        if not S.url:
            bot.send_message(cid, "Stack is offline.", reply_markup=system_menu(cid))
            return
        online = http_ok("%s/splash.php" % S.url.rstrip("/"))
        uptime = int(time.time() - S.started) if S.started else 0
        p = LOGS / "debug.log"
        if p.exists():
            data = p.read_text(encoding="utf-8", errors="ignore")
            tail = data[-800:]
        else:
            tail = "No logs."
        summary = (
            "Status snapshot:\n\n"
            "Online: %s\n"
            "Uptime: %ds\n"
            "URL: %s\n\n"
            "Recent log tail:\n\n%s"
        ) % (online, uptime, S.url_short or S.url, tail)
        bot.send_message(cid, summary, reply_markup=system_menu(cid))
        return

    # LOGS (reply keyboard button -> inline picker already handled above)
    if text == "View Logs":
        # Old direct behavior kept for compatibility
        record_usage(cid, "View Logs")
        p = LOGS / "debug.log"
        if p.exists():
            data = p.read_text(encoding="utf-8", errors="ignore")
            data = data[-2500:]
        else:
            data = "No logs."
        bot.send_message(
            cid,
            "```\n%s\n```" % data,
            parse_mode="Markdown",
            reply_markup=logs_menu(cid)
        )
        return

    # TOOLS LAYER
    if text == "Reset State":
        record_usage(cid, "Reset State")
        kill_stack()
        S.url = ""
        S.url_short = ""
        S.port = 0
        S.started = 0
        S.last_online = None
        bot.send_message(cid, "State reset.", reply_markup=tools_menu(cid))
        return

    if text == "Reset Watchers":
        record_usage(cid, "Reset Watchers")
        S.watchers = set()
        save_watchers()
        bot.send_message(cid, "Watchers cleared.", reply_markup=tools_menu(cid))
        return

    # UNIVERSAL BACK
    if text == "Back":
        bot.send_message(cid, "Main menu:", reply_markup=main_menu(cid))
        return

    # FALLBACK
    bot.send_message(cid, "Command not recognized.", reply_markup=main_menu(cid))


# ============================================================
# CALLBACK HANDLERS (INLINE BUTTONS)
# ============================================================

@bot.callback_query_handler(func=lambda call: True)
def callback_handler(call):
    cid = call.message.chat.id
    data = call.data or ""
    if data.startswith("logs_"):
        try:
            n = int(data.split("_", 1)[1])
        except Exception:
            n = 200
        p = LOGS / "debug.log"
        if p.exists():
            t = p.read_text(encoding="utf-8", errors="ignore")
            t = t[-n*10:]  # crude heuristic: ~10 chars per log char cluster
        else:
            t = "No logs."
        bot.answer_callback_query(call.id, "Showing last logs.")
        bot.send_message(
            cid,
            "Last logs slice (%d units):\n\n```\n%s\n```" % (n, t),
            parse_mode="Markdown"
        )
        return

    # default
    bot.answer_callback_query(call.id, "Nothing to do.")


# ============================================================
# WATCHER MONITOR
# ============================================================

def monitor_loop():
    while True:
        time.sleep(10)
        if not S.url or not S.watchers:
            continue
        online = http_ok("%s/splash.php" % S.url.rstrip("/"))
        if S.last_online is None:
            S.last_online = online
            continue
        if online != S.last_online:
            S.last_online = online
            msg = "Tunnel restored." if online else "Tunnel lost."
            for wcid in list(S.watchers):
                try:
                    bot.send_message(wcid, msg)
                except Exception:
                    pass


# ============================================================
# MAIN
# ============================================================

if __name__ == "__main__":
    load_watchers()
    load_usage()
    load_intents()
    threading.Thread(target=monitor_loop, daemon=True).start()
    log("Bot running.")
    bot.infinity_polling(timeout=30, long_polling_timeout=25)