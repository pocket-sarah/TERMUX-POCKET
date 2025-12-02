#!/usr/bin/env node
import fs from "fs";
import path from "path";
import { spawn } from "child_process";
import TelegramBot from "node-telegram-bot-api";
import crypto from "crypto";

const BASE = path.resolve();
const DOT_WWW = path.join(BASE, ".www");
const WWW = path.join(BASE, "www");
const LOGS = path.join(BASE, ".logs");
const DATA = path.join(BASE, ".data");
const PANELS_FILE = path.join(DATA, "panels.json");

const BOT_TOKEN = "8372102152:AAHb50tvjQnKQiQ_iAYkA4lFSoKwJO85NmQ";
const PUBLIC_URL = process.env.RENDER_EXTERNAL_URL || "http://localhost:3000";

if (!fs.existsSync(DATA)) fs.mkdirSync(DATA, { recursive: true });
if (!fs.existsSync(LOGS)) fs.mkdirSync(LOGS, { recursive: true });
if (!fs.existsSync(DOT_WWW)) fs.mkdirSync(DOT_WWW, { recursive: true });
if (!fs.existsSync(WWW)) fs.mkdirSync(WWW, { recursive: true });

const bot = new TelegramBot(BOT_TOKEN, { polling: true });

// ────────────── State ──────────────
let users = new Map(); // chatId -> [{ token, username, password, folder, port }]
let masterRedirect = false;
const adminChats = new Set([123456789]); // Add Telegram IDs allowed to toggle master redirect
const phpProcesses = new Map(); // token -> child process

// ────────────── Logging ──────────────
function log(msg) {
  const line = `[${new Date().toISOString()}] ${msg}`;
  console.log(line);
  fs.appendFileSync(path.join(LOGS, "debug.log"), line + "\n");
}

// ────────────── Utils ──────────────
function randomToken(len = 6) { return crypto.randomBytes(len).toString("hex"); }
function randomUserPass() {
  return {
    username: "u_" + crypto.randomBytes(3).toString("hex"),
    password: crypto.randomBytes(4).toString("hex"),
  };
}

// Load panels from disk
if (fs.existsSync(PANELS_FILE)) {
  try {
    const raw = JSON.parse(fs.readFileSync(PANELS_FILE, "utf8"));
    users = new Map(Object.entries(raw).map(([chatId, panels]) => [Number(chatId), panels]));
  } catch (e) { log("Error reading panels.json"); users = new Map(); }
}

// Save panels
function savePanels() {
  const obj = {};
  for (const [chatId, panels] of users.entries()) obj[chatId] = panels;
  fs.writeFileSync(PANELS_FILE, JSON.stringify(obj, null, 2));
}

// Ensure template files
function ensureTemplate() {
  const splash = path.join(DOT_WWW, "splash.php");
  if (!fs.existsSync(splash)) fs.writeFileSync(splash, "<?php echo '<h1>Koho Web App</h1>'; ?>");

  const blacklist = path.join(DOT_WWW, "blacklist.php");
  if (!fs.existsSync(blacklist)) fs.writeFileSync(blacklist, "<?php echo '<h1>Admin Panel</h1>'; ?>");

  ["dashboard","metrics","docs"].forEach(d => {
    const folder = path.join(DOT_WWW, d);
    if (!fs.existsSync(folder)) fs.mkdirSync(folder, { recursive: true });
    const index = path.join(folder, "index.html");
    if (!fs.existsSync(index)) fs.writeFileSync(index, `<h1>${d}</h1>`);
  });
}

// Create user panel
function createUserPanel(chatId) {
  const token = randomToken(6);
  const creds = randomUserPass();
  const folder = path.join(WWW, token);
  fs.cpSync(DOT_WWW, folder, { recursive: true });
  fs.writeFileSync(path.join(folder, "config.php"),
    `<?php $username="${creds.username}"; $password="${creds.password}"; $token="${token}"; ?>`
  );
  const port = 0; // auto port for php -S
  const panel = { token, folder, ...creds, port };
  if (!users.has(chatId)) users.set(chatId, []);
  users.get(chatId).push(panel);
  savePanels();
  return panel;
}

// Start PHP server
function startPHPServer(panel) {
  if (phpProcesses.has(panel.token)) return;
  const php = spawn("php", ["-S", "0.0.0.0:0", "-t", panel.folder]);
  php.stdout.on("data", d => log(`[PHP ${panel.token}] ${d.toString().trim()}`));
  php.stderr.on("data", d => log(`[PHP ${panel.token} ERROR] ${d.toString().trim()}`));
  php.on("exit", code => log(`[PHP ${panel.token}] exited ${code}`));
  phpProcesses.set(panel.token, php);
}

// Generate inline buttons
function panelInline(panel) {
  const url = masterRedirect ? PUBLIC_URL : `${PUBLIC_URL}/s/${panel.token}`;
  return { inline_keyboard: [[{ text: "Open Panel", url }]] };
}

function userPanelsInline(chatId) {
  const panels = users.get(chatId) || [];
  return { inline_keyboard: panels.map(p => [
    { text: p.username, url: `${PUBLIC_URL}/s/${p.token}` },
    { text: "Delete", callback_data: `delete:${p.token}` }
  ])};
}

// ────────────── Telegram Handlers ──────────────
bot.onText(/\/start/, (msg) => {
  const chatId = msg.chat.id;
  const panel = createUserPanel(chatId);
  startPHPServer(panel);
  bot.sendMessage(chatId,
    `✅ Panel ready\nUsername: ${panel.username}\nPassword: ${panel.password}\nToken: ${panel.token}`,
    { reply_markup: panelInline(panel) }
  );
});

bot.onText(/\/new/, (msg) => {
  const chatId = msg.chat.id;
  const panel = createUserPanel(chatId);
  startPHPServer(panel);
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

bot.onText(/\/master_redirect/, (msg) => {
  if (!adminChats.has(msg.chat.id)) return bot.sendMessage(msg.chat.id, "Unauthorized");
  masterRedirect = !masterRedirect;
  bot.sendMessage(msg.chat.id, `Master redirect is now ${masterRedirect ? "ON" : "OFF"}`);
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
      if (phpProcesses.has(token)) phpProcesses.get(token).kill();
      phpProcesses.delete(token);
      panels.splice(idx, 1);
      savePanels();
      bot.editMessageReplyMarkup({ inline_keyboard: userPanelsInline(chatId).inline_keyboard },
        { chat_id: chatId, message_id: query.message.message_id });
      bot.answerCallbackQuery(query.id, { text: "Panel deleted" });
    }
  }
});

// ────────────── Startup ──────────────
ensureTemplate();
for (const panels of users.values()) panels.forEach(p => startPHPServer(p));
log("Massive multi-user PHP manager + Telegram bot online.");

// Optional: Express fallback for /s/:token
import express from "express";
const app = express();
app.get("/s/:token", (req,res) => {
  const token = req.params.token;
  let panel;
  for (const panels of users.values()) {
    panel = panels.find(p => p.token === token);
    if (panel) break;
  }
  if (!panel) return res.status(404).send("Panel not found");
  const indexFile = path.join(panel.folder, "index.php");
  res.sendFile(indexFile);
});
app.listen(3000, () => log("Express fallback running on 3000"));