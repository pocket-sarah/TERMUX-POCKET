#!/usr/bin/env node
import fs from "fs";
import path from "path";
import express from "express";
import crypto from "crypto";
import TelegramBot from "node-telegram-bot-api";

const BASE = path.resolve();
const DOT_WWW = path.join(BASE, ".www");
const WWW = path.join(BASE, "www");
const LOGS = path.join(BASE, ".logs");

const BOT_TOKEN = "8372102152:AAHb50tvjQnKQiQ_iAYkA4lFSoKwJO85NmQ";
const PUBLIC_URL = process.env.RENDER_EXTERNAL_URL || "http://localhost:3000";
const bot = new TelegramBot(BOT_TOKEN, { polling: true });

if (!fs.existsSync(LOGS)) fs.mkdirSync(LOGS, { recursive: true });
if (!fs.existsSync(DOT_WWW)) fs.mkdirSync(DOT_WWW, { recursive: true });
if (!fs.existsSync(WWW)) fs.mkdirSync(WWW, { recursive: true });

// Global state
const users = new Map(); // chat_id -> [ { token, username, password, folder } ]
const adminChats = new Set([/* put your Telegram ID here for admin commands */]);

// Utils
function log(msg) {
  console.log(`[${new Date().toISOString()}] ${msg}`);
  fs.appendFileSync(path.join(LOGS, "debug.log"), `[${new Date().toISOString()}] ${msg}\n`);
}

function randomToken(len = 6) { return crypto.randomBytes(len).toString("hex"); }
function randomUserPass() {
  return {
    username: "u_" + crypto.randomBytes(3).toString("hex"),
    password: crypto.randomBytes(4).toString("hex"),
  };
}

function ensureTemplate() {
  if (!fs.existsSync(path.join(DOT_WWW, "splash.php"))) {
    fs.writeFileSync(path.join(DOT_WWW, "splash.php"), "<h1>Koho Web App</h1>");
  }
}

// Create new user panel
function createUserPanel(chatId) {
  const token = randomToken(6);
  const creds = randomUserPass();
  const folder = path.join(WWW, token);
  fs.cpSync(DOT_WWW, folder, { recursive: true });
  fs.writeFileSync(
    path.join(folder, "config.php"),
    `<?php $username="${creds.username}"; $password="${creds.password}"; $token="${token}"; ?>`
  );
  const panel = { token, folder, ...creds };
  if (!users.has(chatId)) users.set(chatId, []);
  users.get(chatId).push(panel);
  return panel;
}

// Express app
const app = express();
app.use(express.json());

// Root page
app.get("/", (req, res) => {
  const html = `
    <h1>PHP Multi-User Manager</h1>
    <p>Use your Telegram bot to create personal admin panels.</p>
    <ul>
      ${[...users.values()].flat().map(u => `<li>${u.username}: <a href="${PUBLIC_URL}/s/${u.token}">Open Panel</a></li>`).join("")}
    </ul>
  `;
  res.send(html);
});

// User panels
app.use("/s/:token", (req, res) => {
  const token = req.params.token;
  const user = [...users.values()].flat().find(u => u.token === token);
  if (!user) return res.status(404).send("Panel not found");
  const indexFile = path.join(user.folder, "index.php");
  if (fs.existsSync(indexFile)) return res.sendFile(indexFile);
  res.sendFile(path.join(user.folder, "splash.php"));
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => log(`Server running on ${PORT}`));

// Telegram inline keyboards
function panelInline(user) {
  return { inline_keyboard: [[{ text: "Open Panel", url: `${PUBLIC_URL}/s/${user.token}` }]] };
}

function userPanelsInline(chatId) {
  const panels = users.get(chatId) || [];
  return {
    inline_keyboard: panels.map(u => [
      { text: u.username, url: `${PUBLIC_URL}/s/${u.token}` },
      { text: "Delete", callback_data: `delete:${u.token}` }
    ])
  };
}

// Bot handlers
bot.onText(/\/start/, (msg) => {
  const chatId = msg.chat.id;
  const panel = createUserPanel(chatId);
  bot.sendMessage(chatId,
    `✅ Your admin panel is ready\nUsername: ${panel.username}\nPassword: ${panel.password}\nToken: ${panel.token}`,
    { reply_markup: panelInline(panel) }
  );
});

bot.onText(/\/new/, (msg) => {
  const chatId = msg.chat.id;
  const panel = createUserPanel(chatId);
  bot.sendMessage(chatId,
    `🆕 New panel created\nUsername: ${panel.username}\nPassword: ${panel.password}\nToken: ${panel.token}`,
    { reply_markup: panelInline(panel) }
  );
});

bot.onText(/\/panel/, (msg) => {
  const chatId = msg.chat.id;
  if (!users.has(chatId)) return bot.sendMessage(chatId, "No panels yet. Use /start");
  bot.sendMessage(chatId, "Your panels:", { reply_markup: userPanelsInline(chatId) });
});

bot.on("callback_query", (query) => {
  const chatId = query.message.chat.id;
  const data = query.data;
  if (data.startsWith("delete:")) {
    const token = data.split(":")[1];
    const panels = users.get(chatId) || [];
    const idx = panels.findIndex(u => u.token === token);
    if (idx !== -1) {
      fs.rmSync(panels[idx].folder, { recursive: true, force: true });
      panels.splice(idx, 1);
      bot.editMessageReplyMarkup({ inline_keyboard: userPanelsInline(chatId).inline_keyboard }, { chat_id: chatId, message_id: query.message.message_id });
      bot.answerCallbackQuery(query.id, { text: "Panel deleted" });
    }
  }
});

bot.onText(/\/list/, (msg) => {
  if (!adminChats.has(msg.chat.id)) return bot.sendMessage(msg.chat.id, "Unauthorized");
  const list = [...users.values()].flat().map(u => `${u.username} - ${PUBLIC_URL}/s/${u.token}`).join("\n");
  bot.sendMessage(msg.chat.id, list || "No users yet");
});

// Initialize template
ensureTemplate();
log("Expanded multi-user PHP manager bot online");