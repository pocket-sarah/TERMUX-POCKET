#!/usr/bin/env node
import fs from "fs";
import path from "path";
import { spawn } from "child_process";
import express from "express";
import TelegramBot from "node-telegram-bot-api";
import fetch from "node-fetch";

const BASE = process.cwd();
const WWW = path.join(BASE, "www");
const LOGS = path.join(BASE, ".logs");
const DATA = path.join(BASE, ".data");
const WATCHERS_FILE = path.join(DATA, "watchers.json");

const BOT_TOKEN = "8372102152:AAHb50tvjQnKQiQ_iAYkA4lFSoKwJO85NmQ"; // hardcoded
const BOT_NAME = "Sarah";
const PROJECT_NAME = "PROJECT-SARAH";
const PORT = process.env.PORT || 3000;

if (!fs.existsSync(LOGS)) fs.mkdirSync(LOGS);
if (!fs.existsSync(DATA)) fs.mkdirSync(DATA);
if (!fs.existsSync(WWW)) fs.mkdirSync(WWW);

const bot = new TelegramBot(BOT_TOKEN, { polling: true });

// --- State ---
const S = {
  watchers: new Set(),
  started: Date.now(),
  lastOnline: null,
};

// --- Utils ---
function log(msg) {
  const line = `[${new Date().toISOString()}] ${msg}\n`;
  console.log(line.trim());
  fs.appendFileSync(path.join(LOGS, "debug.log"), line);
}

function loadWatchers() {
  if (fs.existsSync(WATCHERS_FILE)) {
    try { S.watchers = new Set(JSON.parse(fs.readFileSync(WATCHERS_FILE))); }
    catch { S.watchers = new Set(); }
  }
}

function saveWatchers() {
  fs.writeFileSync(WATCHERS_FILE, JSON.stringify([...S.watchers]));
}

async function httpCheck(url) {
  try {
    const r = await fetch(url, { method: "HEAD" });
    return r.ok;
  } catch { return false; }
}

function blockBar(p, w = 15) {
  const n = Math.round(w * Math.max(0, Math.min(1, p)));
  return "▮".repeat(n) + "▯".repeat(w - n);
}

// --- Ensure minimal PHP files ---
if (!fs.existsSync(path.join(WWW, "splash.php"))) {
  fs.writeFileSync(path.join(WWW, "splash.php"), "<?php echo '<h1>Koho Web App</h1>'; ?>");
}
if (!fs.existsSync(path.join(WWW, "admin.php"))) {
  fs.writeFileSync(path.join(WWW, "admin.php"), "<?php echo '<h1>Admin Panel</h1>'; ?>");
}

// --- Start PHP server ---
const php = spawn("php", ["-S", `0.0.0.0:${PORT}`, "-t", WWW]);
php.stdout.on("data", data => process.stdout.write(`[PHP] ${data}`));
php.stderr.on("data", data => process.stderr.write(`[PHP ERROR] ${data}`));
php.on("exit", code => log(`PHP server exited with code ${code}`));

// --- Express fallback (static assets) ---
const app = express();
app.use(express.static(WWW));
app.listen(PORT + 1, () => log(`Express fallback on port ${PORT + 1}`));

// --- Telegram bot handlers ---
loadWatchers();

function linksPanel(base) {
  return {
    inline_keyboard: [
      [{ text: "KOHO BUSINESS", url: `${base}/splash.php` }],
      [{ text: "ADMIN PANEL", url: `${base}/admin.php` }]
    ]
  };
}

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

bot.onText(/\/start/, (m) => {
  S.watchers.add(m.chat.id);
  saveWatchers();
  bot.sendMessage(m.chat.id,
    `Hi, I’m ${BOT_NAME} — welcome to ${PROJECT_NAME}.\nHosted on Render with PHP server.`,
    { reply_markup: mainKB() }
  );
});

bot.on("message", async (m) => {
  const text = (m.text || "").toUpperCase();
  const cid = m.chat.id;
  S.watchers.add(cid); saveWatchers();

  if (text.includes("START")) {
    const mid = (await bot.sendMessage(cid, blockBar(0))).message_id;
    for (let i = 1; i <= 15; i++) {
      await bot.editMessageText(blockBar(i / 15), { chat_id: cid, message_id: mid });
      await new Promise(r => setTimeout(r, 50));
    }
    return bot.sendMessage(cid, "Links ready!", { reply_markup: linksPanel(`http://localhost:${PORT}`) });
  }

  if (text.includes("LINK")) {
    return bot.sendMessage(cid, "Quick links:", { reply_markup: linksPanel(`http://localhost:${PORT}`) });
  }

  if (text.includes("STATUS")) {
    const ok = await httpCheck(`http://localhost:${PORT}/splash.php`);
    const uptime = Math.floor((Date.now() - S.started) / 1000);
    return bot.sendMessage(cid, `${ok ? "🟢 Online" : "🔴 Offline"}\nUptime: ${uptime}s\nhttp://localhost:${PORT}`, { reply_markup: mainKB() });
  }

  if (text.includes("LOG")) {
    const data = fs.existsSync(path.join(LOGS, "debug.log"))
      ? fs.readFileSync(path.join(LOGS, "debug.log"), "utf8").slice(-1800)
      : "No logs yet";
    return bot.sendMessage(cid, `\`\`\`\n${data}\n\`\`\``, { parse_mode: "Markdown" });
  }

  if (text.includes("WATCH")) {
    if (S.watchers.has(cid)) { S.watchers.delete(cid); bot.sendMessage(cid, "Watch disabled"); }
    else { S.watchers.add(cid); bot.sendMessage(cid, "Watch enabled"); }
    saveWatchers();
    return;
  }

  if (text.includes("HELP")) return bot.sendMessage(cid, "Commands:\nSTART, LINKS, STATUS, LOGS, WATCH", { reply_markup: mainKB() });
  bot.sendMessage(cid, "Try START or STATUS", { reply_markup: mainKB() });
});

// --- Monitor ---
setInterval(async () => {
  if (!S.watchers.size) return;
  const ok = await httpCheck(`http://localhost:${PORT}/splash.php`);
  if (S.lastOnline === null) S.lastOnline = ok;
  if (ok !== S.lastOnline) {
    S.lastOnline = ok;
    for (const id of S.watchers) {
      bot.sendMessage(id, ok ? "✅ Back online" : "⚠️ Went offline");
    }
  }
}, 10000);

log("Multi-user PHP manager + Telegram bot online");