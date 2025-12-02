#!/usr/bin/env node
import fs from "fs";
import path from "path";
import { spawn } from "child_process";
import express from "express";
import TelegramBot from "node-telegram-bot-api";
import crypto from "crypto";
import fetch from "node-fetch";

const BASE = path.resolve(".");
const WWW = path.join(BASE, "www");
const LOGS = path.join(BASE, ".logs");
const DATA = path.join(BASE, ".data");
const USERS_FILE = path.join(DATA, "users.json");

const BOT_NAME = "Sarah";
const PROJECT_NAME = "PROJECT-SARAH";
const TOKEN = "8372102152:AAHb50tvjQnKQiQ_iAYkA4lFSoKwJO85NmQ";
const PUBLIC_URL = process.env.RENDER_EXTERNAL_URL;

if (!fs.existsSync(LOGS)) fs.mkdirSync(LOGS);
if (!fs.existsSync(DATA)) fs.mkdirSync(DATA);
if (!fs.existsSync(WWW)) fs.mkdirSync(WWW);

// State
const S = {
  users: {},
  servers: {}, // chatId -> {port, process}
};

// Utils
function log(msg) {
  const line = `[${new Date().toISOString()}] ${msg}`;
  console.log(line);
  fs.appendFileSync(path.join(LOGS, "debug.log"), line + "\n");
}

function loadUsers() {
  if (fs.existsSync(USERS_FILE)) {
    try { S.users = JSON.parse(fs.readFileSync(USERS_FILE)); }
    catch { S.users = {}; }
  }
}

function saveUsers() {
  fs.writeFileSync(USERS_FILE, JSON.stringify(S.users, null, 2));
}

function generateCredentials(chatId) {
  const username = "user_" + chatId;
  const password = crypto.randomBytes(4).toString("hex");
  const accessKey = crypto.randomBytes(6).toString("hex");
  S.users[chatId] = { username, password, accessKey };
  saveUsers();
  return S.users[chatId];
}

function freePort() {
  return 8000 + Math.floor(Math.random() * 1000); // simple random free-ish port
}

function startPHPServer(folder, port) {
  if (!fs.existsSync(folder)) fs.mkdirSync(folder, { recursive: true });
  const proc = spawn("php", ["-S", `0.0.0.0:${port}`, "-t", folder], {
    stdio: ["ignore", "pipe", "pipe"]
  });
  proc.stdout.on("data", d => log(`[PHP:${port}] ${d.toString().trim()}`));
  proc.stderr.on("data", d => log(`[PHP:${port} ERR] ${d.toString().trim()}`));
  return proc;
}

// Express for root
const app = express();
app.use(express.static(WWW));
app.get("/", (_, res) => res.send(`<h1>${PROJECT_NAME}</h1>`));
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => log(`Node web root online @ ${PORT}`));

// Telegram bot
const bot = new TelegramBot(TOKEN, { polling: true });

function mainKB() {
  return {
    keyboard: [
      ["▶️ START", "📎 LINKS MENU", "🆕 NEW SERVER"],
      ["📊 STATUS", "📝 LOGS", "👁 WATCH"],
      ["⚙️ SETTINGS", "❓ HELP", "⚠️ DISCLAIMER"]
    ],
    resize_keyboard: true
  };
}

function userPanelURL(user, port) {
  return `http://${PUBLIC_URL}:${port}/panel.php?user=${user.username}&key=${user.accessKey}`;
}

// START command
bot.onText(/\/start|START/, (msg) => {
  const cid = msg.chat.id;
  if (!S.users[cid]) {
    const creds = generateCredentials(cid);
    const port = freePort();
    const folder = path.join(WWW, creds.username);
    const proc = startPHPServer(folder, port);
    S.servers[cid] = { port, process: proc };

    bot.sendMessage(cid,
      `Welcome to ${PROJECT_NAME}!\n\n` +
      `• Username: ${creds.username}\n` +
      `• Password: ${creds.password}\n` +
      `• Panel: ${userPanelURL(creds, port)}`,
      { reply_markup: mainKB() }
    );
  } else {
    const user = S.users[cid];
    const port = S.servers[cid]?.port || freePort();
    if (!S.servers[cid]) {
      const folder = path.join(WWW, user.username);
      const proc = startPHPServer(folder, port);
      S.servers[cid] = { port, process: proc };
    }
    bot.sendMessage(cid,
      `Welcome back!\nPanel: ${userPanelURL(user, port)}`,
      { reply_markup: mainKB() }
    );
  }
});

// NEW SERVER command
bot.onText(/NEW SERVER|🆕/, (msg) => {
  const cid = msg.chat.id;
  const user = S.users[cid];
  if (!user) return bot.sendMessage(cid, "Tap START first.");

  const port = freePort();
  const folder = path.join(WWW, `${user.username}_server_${port}`);
  const proc = startPHPServer(folder, port);
  S.servers[cid] = { port, process: proc };

  bot.sendMessage(cid, `New server ready!\nPanel: ${userPanelURL(user, port)}`);
});

// LOGS command
bot.onText(/LOGS|📝/, (msg) => {
  const cid = msg.chat.id;
  const pathLog = path.join(LOGS, "debug.log");
  const tail = fs.existsSync(pathLog) ? fs.readFileSync(pathLog, "utf8").slice(-1800) : "No logs";
  bot.sendMessage(cid, "```\n" + tail + "\n```", { parse_mode: "Markdown" });
});

// STATUS command
bot.onText(/STATUS|📊/, async (msg) => {
  const cid = msg.chat.id;
  const user = S.users[cid];
  if (!user) return bot.sendMessage(cid, "Tap START first.");
  const port = S.servers[cid]?.port || "N/A";
  bot.sendMessage(cid, `User: ${user.username}\nServer port: ${port}`);
});

// WATCH loop
setInterval(async () => {
  for (const [cid, server] of Object.entries(S.servers)) {
    try {
      const res = await fetch(`http://127.0.0.1:${server.port}`);
      const ok = res.ok;
      bot.sendMessage(cid, ok ? "✅ Server online" : "⚠️ Server offline");
    } catch {
      bot.sendMessage(cid, "⚠️ Server offline");
    }
  }
}, 15000);

loadUsers();
log("Master Node-PHP Telegram controller online.");