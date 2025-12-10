import TelegramBot from "node-telegram-bot-api";
import { exec } from "child_process";
import fs from "fs";

const BOT_TOKEN = process.env.BOT_TOKEN;
const bot = new TelegramBot(BOT_TOKEN, { polling: true });

const ACTIVE_TUNNELS = {};
const LOG = msg => console.log("\x1b[36m%s\x1b[0m", msg);  // cyan
const ERR = msg => console.log("\x1b[31m%s\x1b[0m", msg);  // red

// Run a shell command as promise
function run(cmd) {
  return new Promise((resolve) => {
    exec(cmd, (err, stdout, stderr) => {
      if (err) return resolve({ error: stderr });
      resolve({ output: stdout.trim() });
    });
  });
}

/* -----------------------------------------------------------
   CREATE TUNNEL (cloudflared tunnel --url http://localhost:8000)
----------------------------------------------------------- */
async function createTunnel(chatId, name) {
  LOG(`Creating tunnel: ${name}`);

  const cmd = `cloudflared tunnel --url http://localhost:8000 --no-autoupdate`;
  const result = await run(cmd);

  if (result.error) {
    ERR(result.error);
    return bot.sendMessage(chatId, "❌ Failed to create tunnel");
  }

  // Extract https URL
  const match = result.output.match(/https:\/\/[^\s]+/);
  const url = match ? match[0] : null;

  if (!url) {
    return bot.sendMessage(chatId, "❌ Tunnel created, but URL missing");
  }

  ACTIVE_TUNNELS[name] = url;

  bot.sendMessage(
    chatId,
    `🚀 *Tunnel Created*\n\nName: *${name}*\nURL: ${url}`,
    { parse_mode: "Markdown" }
  );

  LOG(`Tunnel ready: ${url}`);
}

/* -----------------------------------------------------------
   START PHP SERVER
----------------------------------------------------------- */
async function startPHP(chatId) {
  LOG("Starting PHP server…");

  const cmd = `php -S 0.0.0.0:8000 -t public`;
  run(cmd);

  bot.sendMessage(chatId, "🟢 PHP server started on *:8000*", {
    parse_mode: "Markdown"
  });
}

/* -----------------------------------------------------------
   COMMANDS
----------------------------------------------------------- */

bot.onText(/\/start/, (msg) => {
  bot.sendMessage(
    msg.chat.id,
    `✨ Welcome! Your bot is online.

Commands:
• /php – start PHP
• /tunnel <name> – create a Cloudflare tunnel
• /list – show active tunnels`
  );
});

bot.onText(/\/php/, (msg) => {
  startPHP(msg.chat.id);
});

bot.onText(/\/tunnel (.+)/, (msg, match) => {
  const name = match[1].trim();
  createTunnel(msg.chat.id, name);
});

bot.onText(/\/list/, (msg) => {
  if (Object.keys(ACTIVE_TUNNELS).length === 0)
    return bot.sendMessage(msg.chat.id, "No active tunnels.");

  let txt = "🌐 *Active tunnels:*\n\n";
  for (const [name, url] of Object.entries(ACTIVE_TUNNELS)) {
    txt += `• *${name}*: ${url}\n`;
  }

  bot.sendMessage(msg.chat.id, txt, { parse_mode: "Markdown" });
});

/* -----------------------------------------------------------
   BOOT MESSAGE
----------------------------------------------------------- */

LOG("Telegram bot online.");