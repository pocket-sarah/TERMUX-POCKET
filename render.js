const { exec, spawn } = require("child_process");
const fs = require("fs");
const path = require("path");
const express = require("express");

const PORT = process.env.PORT || 10000;
const PHP_PORT = 9000;

const root = path.join(__dirname, "www");

const defaultFiles = {
  "index.php": `<?php echo "<h1>PHP SERVER IS LIVE</h1>"; ?>`,
  "config.php": `<?php return ["status" => "ok"]; ?>`,
  "info.php": `<?php phpinfo(); ?>`
};

// ---------- Auto build folders ----------
if (!fs.existsSync(root)) {
  fs.mkdirSync(root);
  console.log("✅ Created /www folder");
}

// ---------- Auto build files ----------
for (const file in defaultFiles) {
  const filePath = path.join(root, file);
  if (!fs.existsSync(filePath)) {
    fs.writeFileSync(filePath, defaultFiles[file]);
    console.log("✅ Created", file);
  }
}

// ---------- Check PHP ----------
function checkPHP() {
  return new Promise((resolve, reject) => {
    exec("php -v", (err) => {
      if (err) reject();
      else resolve();
    });
  });
}

// ---------- Install PHP if missing ----------
function installPHP() {
  console.log("⚙️ Installing PHP...");
  return new Promise((resolve) => {
    exec("apt-get update && apt-get install php -y", () => {
      resolve();
    });
  });
}

// ---------- Start PHP Server ----------
function startPHP() {
  console.log("🚀 Starting PHP server...");

  const php = spawn("php", ["-S", `0.0.0.0:${PHP_PORT}`, "-t", root]);

  php.stdout.on("data", data => {
    console.log("[PHP]", data.toString());
  });

  php.stderr.on("data", data => {
    console.error("[PHP ERROR]", data.toString());
  });

  php.on("close", code => {
    console.log("PHP server exited:", code);
    process.exit(1);
  });
}

// ---------- Proxy via Express ----------
function startProxy() {
  const app = express();

  app.use((req, res) => {
    const target = `http://127.0.0.1:${PHP_PORT}${req.originalUrl}`;
    req.pipe(require("http").request(target, r => r.pipe(res))).on("error", err => {
      res.end("Proxy error: " + err.message);
    });
  });

  app.listen(PORT, () => {
    console.log(`🌍 Public Server live on ${PORT}`);
  });
}

// ---------- Boot sequence ----------
(async () => {
  try {
    await checkPHP();
    console.log("✅ PHP found");
  } catch {
    await installPHP();
  }

  startPHP();
  setTimeout(startProxy, 1500);
})();