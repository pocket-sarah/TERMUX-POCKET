#!/usr/bin/env node
import fs from "fs";
import path from "path";
import express from "express";
import fetch from "node-fetch";
import TelegramBot from "node-telegram-bot-api";
import { fileURLToPath } from "url";

// ──────────────────────────────
// PATHS
// ──────────────────────────────
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const BASE = __dirname;
const DOT_WWW = path.join(BASE, ".www");
const WWW = path.join(BASE, "www");
const LOGS = path.join(BASE, ".logs");
const DATA = path.join(BASE, ".data");
const WATCHERS_FILE = path.join(DATA, "watchers.json");

// ──────────────────────────────
// CONFIG (hardcoded token for testing)
// ──────────────────────────────
const BOT_TOKEN = "8372102152:AAHb50tvjQnKQiQ_iAYkA4lFSoKwJO85NmQ";
const PUBLIC_URL = process.env.RENDER_EXTERNAL_URL || "http://localhost:3000";
const BOT_NAME = "Sarah";
const PROJECT_NAME = "PROJECT-SARAH";

// ──────────────────────────────
// STATE
// ──────────────────────────────
const S = {
  url: PUBLIC_URL,
  watchers: new Set(),
  lastOnline: null,
  started: Date.now(),
};

// ──────────────────────────────
// UTIL
// ──────────────────────────────
function ensureDir(dir) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

function writeFile(file, content) {
  fs.writeFileSync(file, content);
}

function log(msg) {
  const line = `[${new Date().toISOString()}] ${msg}`;
  console.log(line);
  fs.appendFileSync(path.join(LOGS, "debug.log"), line + "\n");
}

// ──────────────────────────────
// INITIALIZE FOLDERS & FILES
// ──────────────────────────────
[LOGS, DATA, WWW, DOT_WWW].forEach(ensureDir);
["dashboard","metrics","docs"].forEach(d => ensureDir(path.join(DOT_WWW,d)));

if (!fs.existsSync(WATCHERS_FILE)) writeFile(WATCHERS_FILE, JSON.stringify([]));

// .www files
writeFile(path.join(DOT_WWW,"splash.php"), `<?php echo "<h1>Koho PHP Web App Splash Page</h1>"; ?>`);
writeFile(path.join(DOT_WWW,"index.php"), `<?php echo "<h1>Main index.php</h1>"; ?>`);
writeFile(path.join(DOT_WWW,"admin.php"), `<?php
session_start();
$pass="admin123";
if(isset($_POST['password'])){
  if($_POST['password']==$pass){ $_SESSION['logged_in']=true; } 
  else{ echo "<p style='color:red;'>Wrong password</p>"; }
}
if(!isset($_SESSION['logged_in'])){
?>
<form method="post">
<label>Password:</label><input type="password" name="password"/><button type="submit">Login</button>
</form>
<?php } else { echo "<h1>Admin Panel</h1><p>Manage your site here.</p>"; } ?>`);

// placeholder dashboard pages
["dashboard","metrics","docs"].forEach(dir=>{
  writeFile(path.join(DOT_WWW,dir,"index.html"), `<h1>${dir.charAt(0).toUpperCase()+dir.slice(1)}</h1><p>Placeholder page</p>`);
});

// copy to www
fs.cpSync(DOT_WWW, WWW, { recursive:true });

// create empty log
writeFile(path.join(LOGS,"debug.log"), "");

// ──────────────────────────────
// LOAD WATCHERS
// ──────────────────────────────
function loadWatchers() {
  try {
    S.watchers = new Set(JSON.parse(fs.readFileSync(WATCHERS_FILE)));
  } catch {
    S.watchers = new Set();
  }
}

function saveWatchers() {
  fs.writeFileSync(WATCHERS_FILE, JSON.stringify([...S.watchers]));
}

// ──────────────────────────────
// EXPRESS SERVER
// ──────────────────────────────
const app = express();
app.use(express.static(WWW));

app.get("/splash.php", (req,res)=>res.send("<h1>Koho PHP Web App Splash Page</h1>"));

const PORT = process.env.PORT || 3000;
app.listen(PORT, ()=>log(`Web online @ ${PORT}`));

// ──────────────────────────────
// TELEGRAM BOT
// ──────────────────────────────
const bot = new TelegramBot(BOT_TOKEN, { polling: true });

function mainKB() {
  return {
    keyboard: [
      ["▶️ START","📎 LINKS MENU","⏹ STOP"],
      ["📊 STATUS","📝 LOGS","👁 WATCH"],
      ["❓ HELP","⚙️ SETTINGS"]
    ],
    resize_keyboard:true
  }
}

function linksPanel(base){
  const routes = [
    ["/splash.php","KOHO BUSINESS"],
    ["/admin.php","Admin Panel"]
  ];
  return {
    inline_keyboard: routes.map(([p,n])=>[{text:n,url:`${base}${p}`}])
  };
}

const INTRO = `Hi, I’m ${BOT_NAME} — welcome to ${PROJECT_NAME}. Hosted on Render.`;
const HELP = `▶️ START - prepare site\n📎 LINKS MENU - show links\n📊 STATUS - check health`;

bot.onText(/\/start/, m=>{
  S.watchers.add(m.chat.id);
  saveWatchers();
  bot.sendMessage(m.chat.id, INTRO, { reply_markup: mainKB() });
});

bot.on("message", async m=>{
  const text = (m.text||"").toUpperCase();
  const cid = m.chat.id;
  if(!S.watchers.has(cid)){ S.watchers.add(cid); saveWatchers(); }

  if(text.includes("START")){
    return bot.sendMessage(cid,"Links ready", { reply_markup: linksPanel(PUBLIC_URL) });
  }
  if(text.includes("LINK")) return bot.sendMessage(cid,"Quick links:", { reply_markup: linksPanel(PUBLIC_URL) });
  if(text.includes("STATUS")) return bot.sendMessage(cid, `Web online: ${PUBLIC_URL}`, { reply_markup: mainKB() });
  if(text.includes("LOG")){
    const data = fs.existsSync(path.join(LOGS,"debug.log")) ? fs.readFileSync(path.join(LOGS,"debug.log"),"utf8") : "No logs";
    return bot.sendMessage(cid,data);
  }
  if(text.includes("WATCH")){
    if(S.watchers.has(cid)){ S.watchers.delete(cid); bot.sendMessage(cid,"Watch disabled"); }
    else{ S.watchers.add(cid); bot.sendMessage(cid,"Watch enabled"); }
    saveWatchers();
    return;
  }
  if(text.includes("HELP")) return bot.sendMessage(cid,HELP,{ reply_markup: mainKB() });
  bot.sendMessage(cid,"Try START or STATUS",{ reply_markup: mainKB() });
});

// ──────────────────────────────
// MONITOR
// ──────────────────────────────
setInterval(async ()=>{
  if(!S.watchers.size) return;
  try{
    await fetch(PUBLIC_URL + "/splash.php");
  }catch{}
}, 10000);

loadWatchers();
log("Bot & panel online");