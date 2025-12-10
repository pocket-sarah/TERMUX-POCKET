
const { spawn } = require("child_process");
const TelegramBot = require("node-telegram-bot-api");
const axios = require("axios");

// ---------------- CONFIG ----------------
const TELEGRAM_BOT_TOKEN = process.env.BOT_TOKEN;
const TELEGRAM_CHAT_ID   = process.env.CHAT_ID;

// PHP directory to serve
const PHP_DIR = "public/";

const TUNNELS = [
  { name: "tunnel1", port: 8001 },
  { name: "tunnel2", port: 8002 },
  { name: "tunnel3", port: 8003 }
];

// ----------------------------------------

const bot = new TelegramBot(TELEGRAM_BOT_TOKEN, { polling: false });

// PHP SERVER STARTER
function startPHP(port) {
  const php = spawn("php", ["-S", `0.0.0.0:${port}`, "-t", PHP_DIR]);
  php.stdout.on("data", d => console.log(`[PHP ${port}]`, d.toString()));
  php.stderr.on("data", d => console.log(`[PHP ${port} ERROR]`, d.toString()));
}

// CLOUDFLARED TUNNEL STARTER
function startTunnel(name, port) {
  console.log(`Starting tunnel: ${name} on port ${port}`);

  const tunnel = spawn("cloudflared", [
    "tunnel", "--url",
    `http://localhost:${port}`
  ]);

  tunnel.stdout.on("data", async d => {
    const out = d.toString();
    console.log(`[${name}]`, out);

    // Detect the assigned URL
    const match = out.match(/https:\/\/[a-zA-Z0-9.-]+\.trycloudflare\.com/);
    if (match) {
      const url = match[0];

      await bot.sendMessage(
        TELEGRAM_CHAT_ID,
        `🔥 *New Tunnel Active*\n\nTunnel: *${name}*\nURL: ${url}`,
        { parse_mode: "Markdown" }
      );
    }
  });

  tunnel.stderr.on("data", d =>
    console.log(`[${name} ERROR]`, d.toString())
  );

  // Auto‑restart on crash
  tunnel.on("close", code => {
    console.log(`Tunnel ${name} closed with code ${code}, restarting...`);
    setTimeout(() => startTunnel(name, port), 2000);
  });
}

// ---------- INIT SYSTEM ----------
(async () => {
  console.log("System starting...");

  // Start all PHP servers
  for (const t of TUNNELS) startPHP(t.port);

  // Start all tunnels
  for (const t of TUNNELS) startTunnel(t.name, t.port);

  bot.sendMessage(TELEGRAM_CHAT_ID, "🚀 System booted.");
})();