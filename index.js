#!/usr/bin/env node
import fs from "fs";
import path from "path";
import express from "express";
import { exec } from "child_process";
import TelegramBot from "node-telegram-bot-api";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const WWW = path.join(__dirname, "www");
const LOGS = path.join(__dirname, "logs");
const DATA = path.join(__dirname, "data");
const WATCHERS_FILE = path.join(DATA, "users.json");

const BOT_TOKEN = "8372102152:AAHb50tvjQnKQiQ_iAYkA4lFSoKwJO85NmQ";
const PUBLIC_URL = process.env.RENDER_EXTERNAL_URL;

if(!BOT_TOKEN) throw "Missing BOT_TOKEN";
if(!PUBLIC_URL) throw "Missing PUBLIC_URL";

[LOGS, DATA, WWW].forEach(d => { if(!fs.existsSync(d)) fs.mkdirSync(d, {recursive:true}) });

const bot = new TelegramBot(BOT_TOKEN, {polling:true});
let USERS = {};

if(fs.existsSync(WATCHERS_FILE)) USERS = JSON.parse(fs.readFileSync(WATCHERS_FILE));

// Express to serve PHP via php-cli
const app = express();
app.use(express.static(WWW));

app.get("/splash.php", (req,res)=>{
    exec(`php ${path.join(WWW,"index.php")}`, (err, stdout)=>{
        res.send(stdout);
    });
});

app.get("/admin.php", (req,res)=>{
    exec(`php ${path.join(WWW,"admin.php")} key=${req.query.key || ""}`, (err, stdout)=>{
        res.send(stdout);
    });
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, ()=>console.log(`PHP Panel accessible at port ${PORT}`));

// Telegram Handlers
bot.onText(/\/start/, (msg)=>{
    const cid = msg.chat.id;
    if(!USERS[cid]) USERS[cid] = { key: Math.random().toString(36).slice(2,10) };
    fs.writeFileSync(WATCHERS_FILE, JSON.stringify(USERS,null,2));
    bot.sendMessage(cid, `Welcome! Your admin key: ${USERS[cid].key}`, {
        reply_markup: {
            keyboard:[
                ["▶️ Open Panel","📊 Status","❓ Help"]
            ],
            resize_keyboard:true
        }
    });
});

bot.on("message", msg=>{
    const cid = msg.chat.id;
    const txt = (msg.text||"").toLowerCase();
    if(txt.includes("panel") || txt.includes("open")){
        const u = USERS[cid];
        bot.sendMessage(cid, `Open your panel: ${PUBLIC_URL}/admin.php?key=${u.key}`);
    }
});