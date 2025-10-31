#!/usr/bin/env node
import express from "express"
import { spawn, execSync } from "child_process"
import fs from "fs"
import path from "path"
import os from "os"
import http from "http"
import { Server as SocketIO } from "socket.io"

const ROOT = path.join(os.homedir(), "render-tunnel")
const WWW = path.join(ROOT, "public")
const LOGS = path.join(ROOT, "logs")
const PHP_PID = path.join(ROOT, ".php.pid")
const CF_PID = path.join(ROOT, ".cf.pid")

fs.mkdirSync(WWW, { recursive: true })
fs.mkdirSync(LOGS, { recursive: true })

const PHP_PORT = 8080
const PANEL_PORT = process.env.PORT || 10000
const HOST = "0.0.0.0"
const PHP_LOG = path.join(LOGS, "php.log")
const CF_LOG = path.join(LOGS, "cf.log")

if (!fs.existsSync(path.join(WWW, "index.php"))) {
  fs.writeFileSync(path.join(WWW, "index.php"), "<?php echo '<h3>PHP OK</h3><p>'.date('Y-m-d H:i:s'); ?>")
}

// === Helper functions ===
const tail = (file, n = 40) => {
  if (!fs.existsSync(file)) return ""
  const lines = fs.readFileSync(file, "utf8").split("\n")
  return lines.slice(-n).join("\n")
}
const readPid = file => {
  try { return parseInt(fs.readFileSync(file)) } catch { return null }
}
const killPid = file => {
  const pid = readPid(file)
  if (pid) {
    try { process.kill(pid, "SIGKILL") } catch {}
    fs.rmSync(file, { force: true })
  }
}
const getCfUrl = () => {
  if (!fs.existsSync(CF_LOG)) return null
  const text = fs.readFileSync(CF_LOG, "utf8")
  const match = text.match(/https:\/\/[-a-z0-9]+\.trycloudflare\.com/)
  return match ? match[0] : null
}
const binaryExists = cmd => {
  try { execSync(`${cmd} --version`, { stdio: "ignore" }); return true }
  catch { return false }
}

// === Start/Stop ===
function startPHP(io) {
  if (fs.existsSync(PHP_PID)) return
  if (!binaryExists("php")) {
    io.emit("log", "PHP missing — skipping launch")
    return
  }
  const out = fs.openSync(PHP_LOG, "a")
  const err = fs.openSync(PHP_LOG, "a")
  const proc = spawn("php", ["-S", `0.0.0.0:${PHP_PORT}`, "-t", WWW], { stdio: ["ignore", out, err], detached: true })
  fs.writeFileSync(PHP_PID, String(proc.pid))
  proc.unref()
  io.emit("log", `PHP started on http://127.0.0.1:${PHP_PORT}`)
}

function startCF(io) {
  if (fs.existsSync(CF_PID)) return
  if (!binaryExists("cloudflared")) {
    io.emit("log", "Cloudflared missing — skipping tunnel")
    return
  }
  const out = fs.openSync(CF_LOG, "a")
  const err = fs.openSync(CF_LOG, "a")
  const proc = spawn("cloudflared", ["tunnel", "--url", `http://127.0.0.1:${PHP_PORT}`], { stdio: ["ignore", out, err], detached: true })
  fs.writeFileSync(CF_PID, String(proc.pid))
  proc.unref()
  io.emit("log", "Cloudflare tunnel starting…")
}

// === Express panel ===
const app = express()
const server = http.createServer(app)
const io = new SocketIO(server)

app.use(express.static(WWW))
app.use(express.json())

app.get("/", (_, res) => {
  res.send(`<!doctype html>
  <html><head><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1">
  <title>Render Cloudflare PHP Manager</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel=stylesheet>
  </head><body class="bg-dark text-light p-4">
  <h3>Cloudflare PHP Panel</h3>
  <div class="mb-3">
    <a href="/api/start" class="btn btn-success btn-sm">Start</a>
    <a href="/api/stop" class="btn btn-danger btn-sm">Stop</a>
    <a href="/api/status" class="btn btn-info btn-sm">Status</a>
  </div>
  <pre id="log" style="background:#000;color:#0f0;padding:10px;height:400px;overflow:auto"></pre>
  <script src="/socket.io/socket.io.js"></script>
  <script>
    const s=io()
    s.on('log',m=>{let e=document.getElementById('log');e.textContent+=m+'\\n';e.scrollTop=e.scrollHeight})
  </script>
  </body></html>`)
})

// === API ===
app.get("/api/start", (_, res) => {
  startPHP(io)
  setTimeout(() => startCF(io), 2000)
  res.redirect("/")
})
app.get("/api/stop", (_, res) => {
  killPid(PHP_PID)
  killPid(CF_PID)
  io.emit("log", "Stopped all processes.")
  res.redirect("/")
})
app.get("/api/status", (_, res) => {
  const phpRunning = fs.existsSync(PHP_PID)
  const cfRunning = fs.existsSync(CF_PID)
  res.json({
    phpRunning,
    cfRunning,
    tunnelUrl: getCfUrl(),
    phpLog: tail(PHP_LOG),
    cfLog: tail(CF_LOG)
  })
})

// === Periodic log push ===
setInterval(() => {
  const out = tail(CF_LOG, 5)
  if (out.trim()) io.emit("log", out)
}, 3000)

// === Launch ===
server.listen(PANEL_PORT, HOST, () => {
  console.log(`Panel → http://0.0.0.0:${PANEL_PORT}`)
  startPHP(io)
  setTimeout(() => startCF(io), 2500)
})