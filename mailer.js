const nodemailer = require('nodemailer');
const config = require('./config.json');

const transporter = nodemailer.createTransport({
  host: config.smtp.host,
  port: config.smtp.port,
  secure: false,
  auth: {
    user: config.smtp.user,
    pass: config.smtp.pass
  }
});

function sendMail({ to, subject, html }) {
  const mailOptions = {
    from: `"Notifier" <${config.smtp.user}>`,
    to,
    subject,
    html
  };

  return transporter.sendMail(mailOptions);
}

module.exports = { sendMail };