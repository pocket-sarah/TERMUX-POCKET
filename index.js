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

// Hardcoded bot token
const BOT_TOKEN = "8372102152:AAHb50tvjQnKQiQ_iAYkA4lFSoKwJO85NmQ";
const bot = new TelegramBot(BOT_TOKEN, { polling: true });

// State: each user gets a server
const users = new Map(); // chat_id -> { token, username, password, folder }

// Utility
function log(msg) {
  console.log(`[${new Date().toISOString()}] ${msg}`);
  if (!fs.existsSync(LOGS)) fs.mkdirSync(LOGS, { recursive: true });
  fs.appendFileSync(path.join(LOGS, "debug.log"), `[${new Date().toISOString()}] ${msg}\n`);
}

function randomToken(len=12) { return crypto.randomBytes(len).toString("hex"); }
function randomUserPass() {
  return {
    username: "u_" + crypto.randomBytes(3).toString("hex"),
    password: crypto.randomBytes(4).toString("hex")
  };
}

// Setup www template if missing
if (!fs.existsSync(DOT_WWW)) fs.mkdirSync(DOT_WWW, { recursive: true });
if (!fs.existsSync(WWW)) fs.mkdirSync(WWW, { recursive: true });
fs.cpSync(DOT_WWW, WWW, { recursive: true });

// Express server
const app = express();
app.use(express.json());
app.use(express.static(WWW));

function createUserServer(chatId) {
  if (users.has(chatId)) return users.get(chatId);

  const token = randomToken(6);
  const creds = randomUserPass();
  const folder = path.join(WWW, token);
  fs.cpSync(DOT_WWW, folder, { recursive: true });

  // Create config file with credentials
  fs.writeFileSync(path.join(folder, "config.php"),
    `<?php $username="${creds.username}"; $password="${creds.password}"; $token="${token}"; ?>`
  );

  users.set(chatId, { token, ...creds, folder });
  return users.get(chatId);
}

// Route to proxy user server
app.use("/s/:token", (req,res)=>{
  const user = [...users.values()].find(u => u.token === req.params.token);
  if (!user) return res.status(404).send("Not found");
  // Serve the index.php or static files (simple for demo)
  const indexPath = path.join(user.folder, "index.php");
  if (fs.existsSync(indexPath)) return res.sendFile(indexPath);
  res.sendFile(path.join(user.folder, "splash.php"));
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => log(`Controller running on port ${PORT}`));

// Telegram handlers
bot.onText(/\/start/, async (msg)=>{
  const chatId = msg.chat.id;
  const user = createUserServer(chatId);
  bot.sendMessage(chatId,
    `Your personal admin panel is ready.\nUsername: ${user.username}\nPassword: ${user.password}\nToken: ${user.token}`,
    {
      reply_markup: {
        inline_keyboard: [[
          { text: "Open Panel", url: `${process.env.RENDER_EXTERNAL_URL}/s/${user.token}` }
        ]]
      }
    }
  );
});

bot.onText(/\/panel/, (msg)=>{
  const chatId = msg.chat.id;
  if (!users.has(chatId)) return bot.sendMessage(chatId, "No panel yet. Use /start");
  const user = users.get(chatId);
  bot.sendMessage(chatId,
    `Open your panel:`,
    {
      reply_markup: { inline_keyboard: [[{ text: "Open Panel", url: `${process.env.RENDER_EXTERNAL_URL}/s/${user.token}` }]] }
    }
  );
});

bot.onText(/\/list/, (msg)=>{
  const list = [...users.values()].map(u=>`${u.username} - ${process.env.RENDER_EXTERNAL_URL}/s/${u.token}`).join("\n");
  bot.sendMessage(msg.chat.id, list || "No users yet");
});

log("Multi-user PHP manager bot online");