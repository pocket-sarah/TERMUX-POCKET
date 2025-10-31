#!/usr/bin/env node
import express from "express";
import { spawn, execSync } from "child_process";
import fs from "fs-extra";
import os from "os";
import path from "path";
import http from "http";
import { Server as SocketIO } from "socket.io";

// === Core Paths ===
const ROOT = path.join(os.homedir(), "render-tunnel");
const WWW = path.join(ROOT, "public");
const LOGS = path.join(ROOT, "logs");
const PHP_PID = path.join(ROOT, ".php.pid");
const CF_PID = path.join(ROOT, ".cf.pid");
fs.ensureDirSync(WWW);
fs.ensureDirSync(LOGS);

// === Config ===
const PHP_PORT = 8080;
const PANEL_PORT = process.env.PORT || 10000;
const HOST = "0.0.0.0";
const PHP_LOG = path.join(LOGS, "php.log");
const CF_LOG = path.join(LOGS, "cloudflared.log");

// === Create default index.php ===
if (!fs.existsSync(path.join(WWW, "index.php"))) {
  fs.writeFileSync(
    path.join(WWW, "index.php"),
    `<?php echo "<h3>Render PHP Active</h3><p>".date('Y-m-d H:i:s'); ?>`
  );
}

// === Helper functions ===
function tail(file, n = 50) {
  if (!fs.existsSync(file)) return "";
  const lines = fs.readFileSync(file, "utf8").split("\n");
  return lines.slice(-n).join("\n");
}
function readPid(file) {
  try {
    return parseInt(fs.readFileSync(file));
  } catch {
    return null;
  }
}
function killPid(file) {
  const pid = readPid(file);
  if (pid) {
    try {
      process.kill(pid, "SIGKILL");
    } catch {}
    fs.removeSync(file);
  }
}
function getCfUrl() {
  if (!fs.existsSync(CF_LOG)) return null;
  const txt = fs.readFileSync(CF_LOG, "utf8");
  const m = txt.match(/https:\/\/[-a-z0-9]+\.trycloudflare\.com/);
  return m ? m[0] : null;
}

// === Start/Stop ===
function startPHP() {
  if (fs.existsSync(PHP_PID)) return;
  const out = fs.openSync(PHP_LOG, "a");
  const err = fs.openSync(PHP_LOG, "a");
  const proc = spawn("php", ["-S", `0.0.0.0:${PHP_PORT}`, "-t", WWW], {
    stdio: ["ignore", out, err],
    detached: true,
  });
  fs.writeFileSync(PHP_PID, String(proc.pid));
  proc.unref();
  io.emit("log", `PHP started → http://127.0.0.1:${PHP_PORT}`);
}
function startCF() {
  if (fs.existsSync(CF_PID)) return;
  const out = fs.openSync(CF_LOG, "a");
  const err = fs.openSync(CF_LOG, "a");
  const proc = spawn(
    "cloudflared",
    ["tunnel", "--url", `http://127.0.0.1:${PHP_PORT}`],
    { stdio: ["ignore", out, err], detached: true }
  );
  fs.writeFileSync(CF_PID, String(proc.pid));
  proc.unref();
  io.emit("log", "Cloudflare tunnel started");
}

// === Express Panel ===
const app = express();
const server = http.createServer(app);
const io = new SocketIO(server);

app.use(express.static(ROOT));
app.use(express.json());

// === Panel UI ===
app.get("/", (_, res) => {
  res.send(`<!doctype html>
<html><head><meta name=viewport content="width=device-width,initial-scale=1">
<title>Render Cloudflare PHP Panel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel=stylesheet>
</head><body class="p-4">
<h2>Cloudflare Tunnel Manager</h2>
<p>
<a href="/api/start" class="btn btn-success">Start</a>
<a href="/api/stop" class="btn btn-danger">Stop</a>
<a href="/api/status" class="btn btn-info">Status</a>
</p>
<pre id="log" style="background:#000;color:#0f0;padding:10px;height:350px;overflow:auto"></pre>
<script src="/socket.io/socket.io.js"></script>
<script>
const s=io(); s.on('log',m=>{let e=document.getElementById('log');e.textContent+=m+'\\n';e.scrollTop=e.scrollHeight})
</script></body></html>`);
});

// === API ===
app.get("/api/start", (_, res) => {
  startPHP();
  setTimeout(() => startCF(), 2000);
  res.redirect("/");
});
app.get("/api/stop", (_, res) => {
  killPid(PHP_PID);
  killPid(CF_PID);
  io.emit("log", "Stopped all processes.");
  res.redirect("/");
});
app.get("/api/status", (_, res) => {
  const cfUrl = getCfUrl();
  const phpRunning = fs.existsSync(PHP_PID);
  const cfRunning = fs.existsSync(CF_PID);
  res.json({
    phpRunning,
    cfRunning,
    tunnelUrl: cfUrl,
    phpLog: tail(PHP_LOG),
    cfLog: tail(CF_LOG),
  });
});

// === Live log emitter ===
setInterval(() => {
  const lines = tail(CF_LOG, 5);
  if (lines.trim()) io.emit("log", lines);
}, 3000);

// === Start server ===
server.listen(PANEL_PORT, HOST, () => {
  console.log(`Panel online → http://0.0.0.0:${PANEL_PORT}`);
  startPHP();
  setTimeout(() => startCF(), 2500);
});