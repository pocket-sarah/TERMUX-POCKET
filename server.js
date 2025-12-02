import express from "express";
import path from "path";
import { fileURLToPath } from "url";

const app = express();
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Serve /public as the webroot
app.use(express.static(path.join(__dirname, "public")));

// Default route: load index.php
app.get("/", (req, res) => {
  res.sendFile(path.join(__dirname, "public", "index.php"));
});

// Health check for Render
app.get("/health", (req, res) => {
  res.json({ status: "OK", time: new Date() });
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Server running on ${PORT}`));