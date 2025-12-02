#!/usr/bin/env node
import fs from "fs";
import path from "path";
import { spawn } from "child_process";
import TelegramBot from "node-telegram-bot-api";
import fetch from "node-fetch";

const BASE = process.cwd();
const WWW = path.join(BASE, "www");
const LOGS = path.join(BASE, "logs");
const DATA = path.join(BASE, "data");
const WATCHERS_FILE = path.join(DATA, "watchers.json");

const BOT_TOKEN = "YOUR_BOT_TOKEN_HERE";
const PORT = process.env.PORT || 3000;
const BASE_URL = process.env.RENDER_EXTERNAL_URL || `http://localhost:${PORT}`;

[WWW, LOGS, DATA].forEach(d => { if (!fs.existsSync(d)) fs.mkdirSync(d); });

if (!fs.existsSync(path.join(WWW, "splash.php"))) {
  fs.writeFileSync(path.join(WWW, "splash.php"), "<?php echo '<h1>Welcome to your PHP App</h1>'; ?>");
}
if (!fs.existsSync(path.join(WWW, "admin.php"))) {
  fs.writeFileSync(path.join(WWW, "admin.php"), "<?php echo '<h1>Admin Panel</h1>'; ?>");
}

// --- Logger ---
function log(msg) {
  const line = `[${new Date().toISOString()}] ${msg}\n`;
  console.log(line.trim());
  fs.appendFileSync(path.join(LOGS, "debug.log"), line);
}

// --- Watchers ---
const S = { watchers: new Set(), lastOnline: null };
function loadWatchers() {
  if (fs.existsSync(WATCHERS_FILE)) {
    try { S.watchers = new Set(JSON.parse(fs.readFileSync(WATCHERS_FILE))); } 
    catch {}
  }
}
function saveWatchers() { fs.writeFileSync(WATCHERS_FILE, JSON.stringify([...S.watchers])); }

// --- PHP CLI Server ---
const php = spawn("php", ["-S", `0.0.0.0:${PORT}`, "-t", WWW]);
php.stdout.on("data", d => process.stdout.write(`[PHP] ${d}`));
php.stderr.on("data", d => process.stderr.write(`[PHP ERROR] ${d}`));
php.on("exit", code => log(`PHP exited with code ${code}`));

// --- Telegram Bot ---
const bot = new TelegramBot(BOT_TOKEN, { polling: true });
loadWatchers();

function linksPanel(base) {
  return { inline_keyboard: [
    [{ text: "Home", url: `${base}/splash.php` }],
    [{ text: "Admin Panel", url: `${base}/admin.php` }]
  ]};
}

function mainKB() {
  return { keyboard: [["▶️ START", "📎 LINKS MENU"]], resize_keyboard: true };
}

bot.onText(/\/start/, m => {
  S.watchers.add(m.chat.id); saveWatchers();
  bot.sendMessage(m.chat.id, `Welcome to your PHP app!`, { reply_markup: mainKB() });
});

bot.on("message", async m => {
  const text = (m.text || "").toUpperCase();
  const cid = m.chat.id;

  if (text.includes("START")) {
    bot.sendMessage(cid, "PHP server running!", { reply_markup: linksPanel(BASE_URL) });
  }

  if (text.includes("LINK")) {
    bot.sendMessage(cid, "Links:", { reply_markup: linksPanel(BASE_URL) });
  }
});

// --- Monitor PHP Server ---
setInterval(async () => {
  const ok = await fetch(`${BASE_URL}/splash.php`).then(r => r.ok).catch(() => false);
  if (S.lastOnline === null) S.lastOnline = ok;
  if (ok !== S.lastOnline) {
    S.lastOnline = ok;
    for (const id of S.watchers) bot.sendMessage(id, ok ? "✅ Back online" : "⚠️ Went offline");
  }
}, 10000);

log("PHP auto-runner + Telegram bot online");
