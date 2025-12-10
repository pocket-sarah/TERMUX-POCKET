import { spawn } from "child_process";
import fs from "fs";
import path from "path";
import express from "express";

const app = express();
app.use(express.json());

const BASE = "/app/sites";       // folder that holds all php sites
const TUNNELS = {};              // active tunnels map
const PHPS = {};                 // active php servers
const LOGS = {};

// Ensure dirs exist
if (!fs.existsSync(BASE)) fs.mkdirSync(BASE, { recursive: true });

/*──────────────────────────────────────────────
  CREATE NEW PHP SITE
──────────────────────────────────────────────*/
app.post("/create-site", (req, res) => {
    const name = req.body.name;
    if (!name) return res.status(400).json({ error: "Site name required" });

    const sitePath = path.join(BASE, name);
    if (fs.existsSync(sitePath))
        return res.json({ created: false, message: "Site already exists" });

    fs.mkdirSync(sitePath);
    fs.writeFileSync(path.join(sitePath, "index.php"), "<?php echo 'Hello from " + name + "'; ?>");

    res.json({ created: true, site: sitePath });
});

/*──────────────────────────────────────────────
  START PHP SERVER
──────────────────────────────────────────────*/
function startPHP(name, port) {
    const sitePath = path.join(BASE, name);

    const php = spawn("php", ["-S", `127.0.0.1:${port}`, "-t", sitePath]);

    PHPS[name] = php;
    LOGS[name] = [];

    php.stdout.on("data", d => LOGS[name].push(d.toString()));
    php.stderr.on("data", d => LOGS[name].push("ERR: " + d.toString()));

    php.on("close", () => {
        LOGS[name].push("PHP server stopped.");
    });

    return true;
}

/*──────────────────────────────────────────────
  START CLOUDFLARED TUNNEL
──────────────────────────────────────────────*/
function startTunnel(name, port) {
    const tunnel = spawn("cloudflared", ["tunnel", "--url", `http://127.0.0.1:${port}`]);

    TUNNELS[name] = tunnel;

    tunnel.stdout.on("data", d => {
        const line = d.toString();
        LOGS[name].push("[CLOUD] " + line);
    });

    tunnel.stderr.on("data", d => {
        LOGS[name].push("[CLOUD-ERR] " + d.toString());
    });

    tunnel.on("close", () => {
        LOGS[name].push("Tunnel closed.");
    });

    return true;
}

/*──────────────────────────────────────────────
  CREATE FULL STACK INSTANCE (PHP + TUNNEL)
──────────────────────────────────────────────*/
app.post("/deploy", (req, res) => {
    const { name, port } = req.body;
    if (!name || !port) return res.status(400).json({ error: "name & port required" });

    startPHP(name, port);
    startTunnel(name, port);

    res.json({
        deployed: true,
        name,
        php: `php://localhost:${port}`,
        cloudflared: "live"
    });
});

/*──────────────────────────────────────────────
  LIST RUNNING INSTANCES
──────────────────────────────────────────────*/
app.get("/status", (req, res) => {
    const sites = Object.keys(PHPS).map(name => ({
        name,
        php_running: !!PHPS[name],
        tunnel_running: !!TUNNELS[name],
        log_size: LOGS[name]?.length || 0
    }));
    res.json(sites);
});

/*──────────────────────────────────────────────
  GET LOGS
──────────────────────────────────────────────*/
app.get("/logs/:name", (req, res) => {
    const name = req.params.name;
    res.json(LOGS[name] || []);
});

/*──────────────────────────────────────────────
  STOP INSTANCE
──────────────────────────────────────────────*/
app.post("/stop", (req, res) => {
    const { name } = req.body;

    if (PHPS[name]) PHPS[name].kill("SIGTERM");
    if (TUNNELS[name]) TUNNELS[name].kill("SIGTERM");

    res.json({ stopped: name });
});

/*──────────────────────────────────────────────*/

app.listen(8080, () => console.log("Manager running on :8080"));