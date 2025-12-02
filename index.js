const fs = require('fs');
const express = require('express');
const { spawn } = require('child_process');
const TelegramBot = require('node-telegram-bot-api');
const config = require('./config.json');
const { sendMail } = require('./mailer');

const app = express();
const PORT = process.env.PORT || 3000;
const bot = new TelegramBot(config.botToken, { polling: true });

// ---------- START PHP SERVER ----------
spawn('php', ['-S', `0.0.0.0:${PORT}`, '-t', 'www'], { stdio: 'inherit' });

// ---------- TELEGRAM ACCESS CHECK ----------
function allowed(chatId) {
  if (config.autoDetectChats && !config.allowedChats.includes(chatId)) {
    config.allowedChats.push(chatId);
    fs.writeFileSync('./config.json', JSON.stringify(config, null, 2));
  }
  return config.allowedChats.includes(chatId);
}

// ---------- TELEGRAM COMMANDS ----------

bot.on('message', async (msg) => {
  const chatId = msg.chat.id;
  if (!allowed(chatId)) return;

  if (msg.text === '/start') {
    bot.sendMessage(chatId,
`Controller Online

/status
/redirect_on
/redirect_off
/setredirect URL
/testmail`
    );
  }

  if (msg.text === '/status') {
    bot.sendMessage(chatId, `
Status:
Redirect: ${config.masterRedirect}
Authorized chats: ${config.allowedChats.length}
Server: ACTIVE
    `);
  }

  if (msg.text.startsWith('/setredirect')) {
    const url = msg.text.split(' ')[1];
    if (!url) return bot.sendMessage(chatId, "Provide a URL");

    config.masterRedirect = url;
    fs.writeFileSync('./config.json', JSON.stringify(config, null, 2));

    bot.sendMessage(chatId, `Redirect set to: ${url}`);
  }

  if (msg.text === '/redirect_on') {
    fs.writeFileSync('./www/redirect.txt', config.masterRedirect);
    bot.sendMessage(chatId, "Master redirect ON");
  }

  if (msg.text === '/redirect_off') {
    if (fs.existsSync('./www/redirect.txt'))
      fs.unlinkSync('./www/redirect.txt');

    bot.sendMessage(chatId, "Master redirect OFF");
  }

  if (msg.text === '/testmail') {
    sendMail({
      to: msg.from.username + "@example.com",
      subject: "Test Notification",
      html: "<h2>System active</h2><p>Mailer online.</p>"
    });

    bot.sendMessage(chatId, "Test mail sent (if valid)");
  }
});

// ---------- WEB SIDE ----------
app.get('/', (req, res) => {
  res.send('Render Node Controller is running.');
});

app.listen(PORT, () => console.log("Node controller active"));