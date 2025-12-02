#!/usr/bin/env node
import fs from "fs";
import path from "path";
import { spawn } from "child_process";
import express from "express";
import TelegramBot from "node-telegram-bot-api";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// ─────────────────────────────────────
// CONFIG
// ─────────────────────────────────────
const BASE = __dirname;
const WWW = path.join(BASE, "www");
const DATA = path.join(BASE, ".data");
const LOGS = path.join(BASE, ".logs");
const USERS_FILE = path.join(DATA, "users.json");

const TOKEN = "8372102152:AAHb50tvjQnKQiQ_iAYkA4lFSoKwJO85NmQ"; // hardcoded bot token
const URL = process.env.RENDER_EXTERNAL_URL;

if (!fs.existsSync(DATA)) fs.mkdirSync(DATA, { recursive: true });
if (!fs.existsSync(LOGS)) fs.mkdirSync(LOGS, { recursive: true });
if (!fs.existsSync(WWW)) fs.mkdirSync(WWW, { recursive: true });

const bot = new TelegramBot(TOKEN, { polling: false }); // webhook preferred on Render
const app = express();

// ─────────────────────────────────────
// STATE
// ─────────────────────────────────────
const state = {
  users: {},        // chat_id -> {username, password, urlToken, port}
  phpServers: {},   // urlToken -> child process
};

// Load users
if (fs.existsSync(USERS_FILE)) {
  state.users = JSON.parse(fs.readFileSync(USERS_FILE, "utf8"));
}

// Save users
function saveUsers() {
  fs.writeFileSync(USERS_FILE, JSON.stringify(state.users, null, 2));
}

// Generate random password / token
function randomStr(len = 8) {
  return [...Array(len)].map(() => Math.random().toString(36)[2]).join("");
}

// Spawn PHP built-in server
function spawnPhpServer(port, folder) {
  const proc = spawn("php", ["-S", `0.0.0.0:${port}`, "-t", folder]);
  proc.stdout.on("data", d => console.log(`[PHP ${port}] ${d}`));
  proc.stderr.on("data", d => console.error(`[PHP ${port} ERROR] ${d}`));
  proc.on("exit", code => console.log(`[PHP ${port} EXIT] ${code}`));
  return proc;
}

// Create user panel
function createUser(chatId) {
  if (state.users[chatId]) return state.users[chatId];

  const username = `user${chatId}`;
  const password = randomStr(10);
  const port = 3000 + Object.keys(state.users).length + 1;
  const urlToken = randomStr(12);
  const userDir = path.join(WWW, urlToken);
  fs.mkdirSync(userDir, { recursive: true });

  // create a simple index.php
  fs.writeFileSync(path.join(userDir, "index.php"),
    `<?php echo "<h1>Welcome ${username}</h1>"; ?>`
  );

  const phpProc = spawnPhpServer(port, userDir);
  state.phpServers[urlToken] = phpProc;

  const user = { username, password, port, urlToken };
  state.users[chatId] = user;
  saveUsers();
  return user;
}

// ─────────────────────────────────────
// TELEGRAM BOT
// ─────────────────────────────────────
const mainKeyboard = {
  keyboard: [
    ["▶️ START", "📎 LINKS MENU", "⏹ STOP"],
    ["📊 STATUS", "📝 LOGS", "👁 WATCH"],
    ["❓ HELP", "⚠️ DISCLAIMER"]
  ],
  resize_keyboard: true
};

bot.onText(/\/start/, (msg) => {
  const chatId = msg.chat.id;
  const user = createUser(chatId);

  bot.sendMessage(chatId,
    `Welcome ${user.username}!\nYour panel is available at:\n${URL}/${user.urlToken}/index.php\nPassword: ${user.password}`,
    { reply_markup: mainKeyboard }
  );
});

// Links menu inline buttons
bot.onText(/LINKS MENU/, (msg) => {
  const chatId = msg.chat.id;
  const user = state.users[chatId];
  if (!user) return bot.sendMessage(chatId, "No panel yet. Tap START.");

  const inline = {
    inline_keyboard: [[
      { text: "Admin Panel", url: `${URL}/${user.urlToken}/index.php` }
    ]]
  };
  bot.sendMessage(chatId, "Your links:", { reply_markup: inline });
});

// Status
bot.onText(/STATUS/, (msg) => {
  const chatId = msg.chat.id;
  const user = state.users[chatId];
  if (!user) return bot.sendMessage(chatId, "No panel yet. Tap START.");

  bot.sendMessage(chatId,
    `Panel: ${user.username}\nPort: ${user.port}\nURL: ${URL}/${user.urlToken}/index.php`,
    { reply_markup: mainKeyboard }
  );
});

// ─────────────────────────────────────
// EXPRESS FALLBACK
// ─────────────────────────────────────
app.use(express.static(WWW));
app.get("/", (req, res) => res.send("<h1>Render PHP Panel System</h1>"));

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Express fallback running on ${PORT}`));