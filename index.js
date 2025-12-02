import express from "express";
import TelegramBot from "node-telegram-bot-api";
import dotenv from "dotenv";
import path from "path";
import fs from "fs";

dotenv.config();

const PORT = process.env.PORT || 3000;
const BOT_TOKEN = process.env.BOT_TOKEN;
const WEBHOOK_URL = "https://termux-pocket.onrender.com/bot"; // your Render URL

if (!BOT_TOKEN) throw new Error("BOT_TOKEN not set");

const __dirname = path.resolve();
const WWW = path.join(__dirname, "www");
if (!fs.existsSync(WWW)) fs.mkdirSync(WWW);

// Minimal PHP pages
if (!fs.existsSync(path.join(WWW, "splash.php"))) {
  fs.writeFileSync(path.join(WWW, "splash.php"), "<?php echo '<h1>Koho Web App</h1>'; ?>");
}
if (!fs.existsSync(path.join(WWW, "admin.php"))) {
  fs.writeFileSync(path.join(WWW, "admin.php"), "<?php echo '<h1>Admin Panel</h1>'; ?>");
}

// Express server
const app = express();
app.use(express.json());
app.use(express.static(WWW));

// Telegram bot
const bot = new TelegramBot(BOT_TOKEN);
bot.setWebHook(WEBHOOK_URL);

// Handle incoming webhook updates
app.post("/bot", (req, res) => {
  bot.processUpdate(req.body);
  res.sendStatus(200);
});

// Bot commands
bot.onText(/\/start/, (msg) => {
  const chatId = msg.chat.id;
  bot.sendMessage(chatId, "Welcome to PROJECT-SARAH! Use /links to access the web panel.");
});

bot.onText(/\/links/, (msg) => {
  const chatId = msg.chat.id;
  bot.sendMessage(chatId, "Access your web panel:", {
    reply_markup: {
      inline_keyboard: [
        [{ text: "Splash Page", url: "https://termux-pocket.onrender.com/splash.php" }],
        [{ text: "Admin Panel", url: "https://termux-pocket.onrender.com/admin.php" }]
      ]
    }
  });
});

// Start Express
app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
});