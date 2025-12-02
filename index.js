#!/usr/bin/env node
import fs from "fs";
import path from "path";
import express from "express";
import fetch from "node-fetch";
import TelegramBot from "node-telegram-bot-api";
import { fileURLToPath } from "url";
import { spawn } from "child_process";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const BASE = __dirname;
const WWW = path.join(BASE, "www");
const LOGS = path.join(BASE, ".logs");
const DATA = path.join(BASE, ".data");
const WATCHERS_FILE = path.join(DATA, "watchers.json");

// ────────────────── CONFIG ──────────────────
const BOT_TOKEN = "8372102152:AAHb50tvjQnKQiQ_iAYkA4lFSoKwJO85NmQ"; // hardcoded
const PUBLIC_URL = process.env.RENDER_EXTERNAL_URL || "http://localhost:3000";

if (!fs.existsSync(LOGS)) fs.mkdirSync(LOGS);
if (!fs.existsSync(DATA)) fs.mkdirSync(DATA);
if (!fs.existsSync(WWW)) fs.mkdirSync(WWW);

// ────────────────── STATE ──────────────────
const S = {
  watchers: new Set(),
  lastOnline: null,
};

// ────────────────── UTILS ──────────────────
function log(msg) {
  const line = `[${new Date().toISOString()}] ${msg}`;
  console.log(line);
  fs.appendFileSync(path.join(LOGS, "debug.log"), line + "\n");
}

function loadWatchers() {
  if (fs.existsSync(WATCHERS_FILE)) {
    try {
      S.watchers = new Set(JSON.parse(fs.readFileSync(WATCHERS_FILE)));
    } catch {
      S.watchers = new Set();
    }
  }
}

function saveWatchers() {
  fs.writeFileSync(WATCHERS_FILE, JSON.stringify([...S.watchers]));
}

function ensureWWW() {
  const indexFile = path.join(WWW, "index.php");
  if (!fs.existsSync(indexFile)) {
    fs.writeFileSync(indexFile, `<?php echo "<h1>Koho PHP Web App</h1>"; ?>`);
  }
}

// ────────────────── PHP SERVER ──────────────────
function startPHPServer() {
  const php = spawn("php", ["-S", "127.0.0.1:8080", "-t", WWW]);
  php.stdout.on("data", (d) => console.log(`PHP: ${d}`));
  php.stderr.on("data", (d) => console.error(`PHP ERR: ${d}`));
  return php;
}

// ────────────────── EXPRESS SERVER ──────────────────
ensureWWW();
const app = express();

// proxy all .php requests to internal PHP server
app.use(async (req, res, next) => {
  if (req.path.endsWith(".php") || req.path === "/") {
    try {
      const url = `http://127.0.0.1:8080${req.url}`;
      const r = await fetch(url);
      const text = await r.text();
      return res.send(text);
    } catch (e) {
      return res.status(500).send("PHP server error");
    }
  }
  next();
});

app.use(express.static(WWW));

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => log(`Express fallback running on ${PORT}`));

// ────────────────── TELEGRAM BOT ──────────────────
const bot = new TelegramBot(BOT_TOKEN, { polling: false });
bot.setWebHook(`${PUBLIC_URL}/bot${BOT_TOKEN}`);

app.post(`/bot${BOT_TOKEN}`, express.json(), (req, res) => {
  bot.processUpdate(req.body);
  res.sendStatus(200);
});

function mainKB() {
  return {
    keyboard: [
      ["▶️ START", "📎 LINKS MENU", "⏹ STOP"],
      ["📊 STATUS", "📝 LOGS", "👁 WATCH"],
      ["❓ HELP", "⚠️ DISCLAIMER", "⚙️ SETTINGS"],
    ],
    resize_keyboard: true,
  };
}

function linksPanel(base) {
  const routes = [
    ["/index.php", "Home"],
    ["/admin.php", "Admin Panel"],
  ];
  return {
    inline_keyboard: routes.map(([path, name]) => [
      { text: name, url: `${base}${path}` },
    ]),
  };
}

const INTRO = `Hi, welcome to Koho Render PHP Web App.`;
const HELP = `▶️ START - prepares site
📎 LINKS MENU - show links
📊 STATUS - check health`;

// ────────────────── BOT HANDLERS ──────────────────
bot.onText(/\/start/, (m) => {
  S.watchers.add(m.chat.id);
  saveWatchers();
  bot.sendMessage(m.chat.id, INTRO, { reply_markup: mainKB() });
});

bot.on("message", async (m) => {
  const text = (m.text || "").toUpperCase();
  const cid = m.chat.id;
  S.watchers.add(cid);
  saveWatchers();

  if (text.includes("START")) {
    bot.sendMessage(cid, "Site ready", { reply_markup: linksPanel(PUBLIC_URL) });
  }

  if (text.includes("LINK")) {
    bot.sendMessage(cid, "Quick links:", { reply_markup: linksPanel(PUBLIC_URL) });
  }

  if (text.includes("STATUS")) {
    bot.sendMessage(cid, `Web URL: ${PUBLIC_URL}`, { reply_markup: mainKB() });
  }

  if (text.includes("LOG")) {
    const data = fs.existsSync(path.join(LOGS, "debug.log"))
      ? fs.readFileSync(path.join(LOGS, "debug.log"), "utf8").slice(-1800)
      : "No logs";
    bot.sendMessage(cid, data);
  }

  if (text.includes("WATCH")) {
    if (S.watchers.has(cid)) {
      S.watchers.delete(cid);
      bot.sendMessage(cid, "Watch disabled");
    } else {
      S.watchers.add(cid);
      bot.sendMessage(cid, "Watch enabled");
    }
    saveWatchers();
  }

  if (text.includes("HELP")) {
    bot.sendMessage(cid, HELP, { reply_markup: mainKB() });
  }
});

// ────────────────── START PHP SERVER ──────────────────
startPHPServer();
loadWatchers();
log("Massive multi-user PHP manager + Telegram bot online.");