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
const DOT_WWW = path.join(BASE, ".www");
const WWW = path.join(BASE, "www");
const LOGS = path.join(BASE, ".logs");
const DATA = path.join(BASE, ".data");
const PANELS_FILE = path.join(DATA, "panels.json");

const BOT_NAME = "Sarah";
const PROJECT_NAME = "PROJECT-SARAH";

// Hardcoded token (replace with env variable for production)
const TOKEN = "8372102152:AAHb50tvjQnKQiQ_iAYkA4lFSoKwJO85NmQ";

if (!TOKEN) throw "❌ BOT_TOKEN missing";

// Create required folders
for (const d of [LOGS, DATA, WWW, DOT_WWW]) if (!fs.existsSync(d)) fs.mkdirSync(d, { recursive: true });

// ─────────────────────────────────────
// UTILS
// ─────────────────────────────────────
function log(msg) {
  const line = `[${new Date().toISOString()}] ${msg}`;
  console.log(line);
  fs.appendFileSync(path.join(LOGS, "debug.log"), line + "\n");
}

function randomToken(len = 6) {
  const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  return Array.from({ length: len }, () => chars[Math.floor(Math.random() * chars.length)]).join("");
}

function randomUserPass() {
  return {
    username: "user" + Math.floor(Math.random() * 9999),
    password: "pass" + Math.floor(Math.random() * 9999)
  };
}

let users = new Map();
let masterRedirect = false;

function savePanels() {
  const obj = {};
  for (const [chatId, panels] of users.entries()) obj[chatId] = panels;
  fs.writeFileSync(PANELS_FILE, JSON.stringify({ users: obj, masterRedirect }, null, 2));
}

function loadPanels() {
  if (fs.existsSync(PANELS_FILE)) {
    const raw = JSON.parse(fs.readFileSync(PANELS_FILE));
    if (raw.users) {
      for (const chatId in raw.users) users.set(chatId, raw.users[chatId]);
    }
    if (typeof raw.masterRedirect === "boolean") masterRedirect = raw.masterRedirect;
  }
}

// ─────────────────────────────────────
// PANEL CREATION
// ─────────────────────────────────────
function createUserPanel(chatId) {
  const token = randomToken(6);
  const creds = randomUserPass();
  const folder = path.join(WWW, token);

  fs.cpSync(DOT_WWW, folder, { recursive: true });

  // Save credentials in config.php
  fs.writeFileSync(path.join(folder, "config.php"),
`<?php
$username = "${creds.username}";
$password = "${creds.password}";
$token = "${token}";
?>`
  );

  // Auto-generate login page
  fs.writeFileSync(path.join(folder, "index.php"),
`<?php
include "config.php";
session_start();
if (isset($_POST['user'], $_POST['pass'])) {
  if ($_POST['user'] === $username && $_POST['pass'] === $password) {
    $_SESSION['logged'] = true;
    header("Location: dashboard.php");
    exit;
  } else {
    $error = "Invalid credentials!";
  }
}
?>
<!doctype html>
<html>
<head><title>Login</title></head>
<body>
<h1>Welcome to your panel</h1>
<?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
<form method="POST">
<input name="user" placeholder="Username" required><br>
<input name="pass" type="password" placeholder="Password" required><br>
<button type="submit">Login</button>
</form>
</body>
</html>`);

  fs.writeFileSync(path.join(folder, "dashboard.php"),
`<?php
include "config.php";
session_start();
if (!isset($_SESSION['logged']) || $_SESSION['logged']!==true) {
  header("Location: index.php"); exit;
}
?>
<!doctype html>
<html>
<body>
<h1>Dashboard</h1>
<p>Welcome, <?php echo $username; ?>!</p>
<p>Panel token: <?php echo $token; ?></p>
</body>
</html>`);

  const phpProc = spawn("php", ["-S", "0.0.0.0:0", "-t", folder], { stdio: "ignore" });

  const panel = { token, folder, ...creds, pid: phpProc.pid };
  if (!users.has(chatId)) users.set(chatId, []);
  users.get(chatId).push(panel);
  savePanels();
  return panel;
}

// ─────────────────────────────────────
// TELEGRAM BOT
// ─────────────────────────────────────
const bot = new TelegramBot(TOKEN, { polling: true });

function mainKB() {
  return {
    keyboard: [
      ["➕ NEW PANEL", "📎 MY PANELS", "⏹ STOP ALL"],         // row 1
      ["📊 STATUS", "📝 LOGS", "👁 WATCH"],                 // row 2
      ["⚙️ SETTINGS", "🛠 TOOLS", "🗂 PANEL LIST"],        // row 3
      ["🔑 CREDENTIALS", "🖥 DASHBOARDS", "📁 FILE MANAGER"],// row 4
      ["🌐 MASTER REDIRECT", "📝 NOTES", "📌 PIN PANEL"],   // row 5
      ["💬 FEEDBACK", "❓ HELP", "⚠️ DISCLAIMER"],        // row 6
      ["🔄 RESTART BOT", "🔒 LOCK PANEL", "♻️ CLEAN LOGS"] // row 7
    ],
    resize_keyboard: true
  };
}

function inlinePanelButtons(panels) {
  return {
    inline_keyboard: panels.map(p => [{ text: p.username, url: `https://${p.token}.render.com` }])
  };
}

bot.onText(/\/start/, (msg) => {
  bot.sendMessage(msg.chat.id, `Hi, I’m ${BOT_NAME} — Render PHP Multi-Panel Bot`, { reply_markup: mainKB() });
});

bot.on("message", async (msg) => {
  const text = (msg.text || "").toUpperCase();
  const cid = msg.chat.id;

  if (text.includes("NEW PANEL") || text.includes("➕")) {
    const panel = createUserPanel(cid);
    bot.sendMessage(cid, `Panel created!\nUser: ${panel.username}\nPass: ${panel.password}\nAccess URL: https://${panel.token}.render.com`, {
      reply_markup: inlinePanelButtons(users.get(cid))
    });
  }

  if (text.includes("MY PANELS") || text.includes("📎")) {
    const panels = users.get(cid) || [];
    if (!panels.length) return bot.sendMessage(cid, "No panels yet.");
    bot.sendMessage(cid, "Your panels:", { reply_markup: inlinePanelButtons(panels) });
  }

  if (text.includes("MASTER REDIRECT") || text.includes("🌐")) {
    masterRedirect = !masterRedirect;
    savePanels();
    bot.sendMessage(cid, `Master redirect is now ${masterRedirect ? "ENABLED" : "DISABLED"}`);
  }

  if (text.includes("STATUS") || text.includes("📊")) {
    bot.sendMessage(cid, `Bot online.\nMaster redirect: ${masterRedirect}\nPanels created: ${users.get(cid)?.length || 0}`);
  }

  if (text.includes("LOG") || text.includes("📝")) {
    const data = fs.existsSync(path.join(LOGS, "debug.log"))
      ? fs.readFileSync(path.join(LOGS, "debug.log"), "utf8").slice(-2000)
      : "No logs";
    bot.sendMessage(cid, data);
  }

  if (text.includes("HELP") || text.includes("❓")) {
    bot.sendMessage(cid, "Commands:\n➕ NEW PANEL\n📎 MY PANELS\n🌐 MASTER REDIRECT\n📊 STATUS\n📝 LOGS", { reply_markup: mainKB() });
  }
});

// ─────────────────────────────────────
// EXPRESS BASE SERVER
// ─────────────────────────────────────
const app = express();
app.get("/", (req,res) => {
  res.send(`<h1>${PROJECT_NAME}</h1><p>Use Telegram bot to create and manage your panels.</p>`);
});
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => log(`Express base server running on port ${PORT}`));

loadPanels();
log("Bot online & ready for multi-panel PHP hosting on Render");