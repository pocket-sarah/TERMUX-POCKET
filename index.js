#!/usr/bin/env node
import fs from "fs";
import path from "path";
import { spawn } from "child_process";
import express from "express";
import TelegramBot from "node-telegram-bot-api";

const BASE = process.cwd();
const WWW = path.join(BASE, "www");
const LOGS = path.join(BASE, ".logs");
const DATA = path.join(BASE, ".data");
const WATCHERS_FILE = path.join(DATA, "watchers.json");

const BOT_TOKEN = process.env.BOT_TOKEN || "YOUR_BOT_TOKEN_HERE";
const BOT_NAME = "Sarah";
const PROJECT_NAME = "PROJECT-SARAH";
const PORT = process.env.PORT || 3000;

if (!fs.existsSync(LOGS)) fs.mkdirSync(LOGS);
if (!fs.existsSync(DATA)) fs.mkdirSync(DATA);
if (!fs.existsSync(WWW)) fs.mkdirSync(WWW);

// Minimal PHP pages
if (!fs.existsSync(path.join(WWW, "splash.php"))) {
  fs.writeFileSync(path.join(WWW, "splash.php"), "<?php echo '<h1>Koho Web App</h1>'; ?>");
}
if (!fs.existsSync(path.join(WWW, "admin.php"))) {
  fs.writeFileSync(path.join(WWW, "admin.php"), "<?php echo '<h1>Admin Panel</h1>'; ?>");
}

// Start PHP built-in server
const php = spawn("php", ["-S", `0.0.0.0:${PORT}`, "-t", WWW]);
php.stdout.on("data", data => process.stdout.write(`[PHP] ${data}`));
php.stderr.on("data", data => process.stderr.write(`[PHP ERROR] ${data}`));
php.on("exit", code => console.log(`PHP server exited with code ${code}`));

// Express fallback
const app = express();
app.use(express.static(WWW));
app.listen(PORT + 1, () => console.log(`Express fallback running on port ${PORT + 1}`));

// Telegram bot
const bot = new TelegramBot(BOT_TOKEN, { polling: true });

// State
const S = { watchers: new Set(), lastOnline: null, started: Date.now() };

// Utils
function loadWatchers() {
  if (fs.existsSync(WATCHERS_FILE)) {
    try { S.watchers = new Set(JSON.parse(fs.readFileSync(WATCHERS_FILE))); }
    catch { S.watchers = new Set(); }
  }
}
function saveWatchers() {
  fs.writeFileSync(WATCHERS_FILE, JSON.stringify([...S.watchers]));
}

// Reply keyboards
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

function linksPanel() {
  return {
    inline_keyboard: [
      [{ text: "Splash Page", url: `http://localhost:${PORT}/splash.php` }],
      [{ text: "Admin Panel", url: `http://localhost:${PORT}/admin.php` }]
    ]
  };
}

// Bot handlers
loadWatchers();

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
    bot.sendMessage(cid, "Links ready!", { reply_markup: linksPanel() });
    return;
  }

  if (text.includes("STATUS")) {
    const uptime = Math.floor((Date.now() - S.started) / 1000);
    bot.sendMessage(cid, `🟢 Online\nUptime: ${uptime}s`, { reply_markup: mainKB() });
    return;
  }

  if (text.includes("LOGS")) {
    const data = fs.existsSync(path.join(LOGS, "debug.log"))
      ? fs.readFileSync(path.join(LOGS, "debug.log"), "utf8").slice(-1800)
      : "No logs yet";
    bot.sendMessage(cid, `\`\`\`\n${data}\n\`\`\``, { parse_mode: "Markdown" });
    return;
  }

  if (text.includes("WATCH")) {
    if (S.watchers.has(cid)) { S.watchers.delete(cid); bot.sendMessage(cid, "Watch disabled"); }
    else { S.watchers.add(cid); bot.sendMessage(cid, "Watch enabled"); }
    saveWatchers();
    return;
  }

  bot.sendMessage(cid, "Try START or STATUS", { reply_markup: mainKB() });
});

// Monitor PHP server
setInterval(() => {
  const ok = true; // basic, add HTTP check if desired
  if (S.lastOnline === null) S.lastOnline = ok;
  if (ok !== S.lastOnline) {
    S.lastOnline = ok;
    for (const id of S.watchers) {
      bot.sendMessage(id, ok ? "✅ PHP Server Back Online" : "⚠️ PHP Server Down");
    }
  }
}, 10000);

console.log("Master PHP + Telegram bot running");