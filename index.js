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

const BOT_TOKEN = "8372102152:AAHb50tvjQnKQiQ_iAYkA4lFSoKwJO85NmQ";
const BOT_NAME = "Sarah";
const PROJECT_NAME = "PROJECT-SARAH";
const PORT = process.env.PORT || 3000;

if (!fs.existsSync(LOGS)) fs.mkdirSync(LOGS);
if (!fs.existsSync(DATA)) fs.mkdirSync(DATA);
if (!fs.existsSync(WWW)) fs.mkdirSync(WWW);

// Minimal PHP files
if (!fs.existsSync(path.join(WWW, "splash.php"))) {
  fs.writeFileSync(path.join(WWW, "splash.php"), "<?php echo '<h1>Koho Web App</h1>'; ?>");
}
if (!fs.existsSync(path.join(WWW, "admin.php"))) {
  fs.writeFileSync(path.join(WWW, "admin.php"), "<?php echo '<h1>Admin Panel</h1>'; ?>");
}

// Detect PHP binary
let phpBin = path.join(BASE, "php", "php"); // bundled binary
import { execSync } from "child_process";
try {
  execSync(`${phpBin} -v`, { stdio: "ignore" });
} catch {
  console.error("❌ PHP CLI not found. Make sure php binary is in ./php/php");
  process.exit(1);
}

// Spawn PHP server
const php = spawn(phpBin, ["-S", `0.0.0.0:${PORT}`, "-t", WWW]);
php.stdout.on("data", d => process.stdout.write(`[PHP] ${d}`));
php.stderr.on("data", d => process.stderr.write(`[PHP ERROR] ${d}`));
php.on("exit", code => console.log(`PHP server exited with code ${code}`));

// Express fallback for assets
const app = express();
app.use(express.static(WWW));
app.listen(PORT + 1, () => console.log(`Express fallback on port ${PORT + 1}`));

// Telegram bot
const bot = new TelegramBot(BOT_TOKEN, { polling: true });
const S = { watchers: new Set(), started: Date.now(), lastOnline: null };

function saveWatchers() {
  fs.writeFileSync(WATCHERS_FILE, JSON.stringify([...S.watchers]));
}

function loadWatchers() {
  if (fs.existsSync(WATCHERS_FILE)) {
    try { S.watchers = new Set(JSON.parse(fs.readFileSync(WATCHERS_FILE))); }
    catch { S.watchers = new Set(); }
  }
}
loadWatchers();

// Bot handlers
bot.onText(/\/start/, m => {
  S.watchers.add(m.chat.id);
  saveWatchers();
  bot.sendMessage(m.chat.id, `Hi, I’m ${BOT_NAME} — welcome to ${PROJECT_NAME}.`, {
    reply_markup: { keyboard: [["▶️ START"]], resize_keyboard: true }
  });
});