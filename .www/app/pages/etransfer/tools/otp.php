<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

// ---------- Load configuration ----------
$config_path = $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
if (!is_file($config_path)) {
    echo json_encode(['success' => false, 'message' => 'Config not found']);
    exit;
}

$config = require $config_path;
$otp_cfg = $config['otp'] ?? [];

// ---------- Validate Telegram settings ----------
$bot_token = trim((string)($otp_cfg['bot_token'] ?? ''));
$chat_id   = trim((string)($otp_cfg['chat_id']   ?? ''));

if ($bot_token === '' || $chat_id === '') {
    echo json_encode(['success' => false, 'message' => 'Telegram credentials missing']);
    exit;
}

// ---------- Generate and store OTP ----------
try {
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'OTP generation failed']);
    exit;
}

$_SESSION['otp']         = $otp;
$_SESSION['otp_expires'] = time() + 300; // 5 min expiry

// ---------- Prepare message ----------
$message = "<code>{$otp}</code> is your verification code from KOHO Financial.";

// ---------- Send via Telegram (JSON payload) ----------
$api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
$payload = [
    'chat_id'    => $chat_id,
    'text'       => $message,
    'parse_mode' => 'HTML',
];

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

// ---------- Handle Telegram response ----------
if ($response === false) {
    echo json_encode([
        'success' => false,
        'message' => "cURL error: {$error}"
    ]);
    exit;
}

$decoded = json_decode($response, true);
if (!is_array($decoded)) {
    echo json_encode([
        'success' => false,
        'message' => "Invalid JSON from Telegram: {$response}"
    ]);
    exit;
}

if (($decoded['ok'] ?? false) !== true) {
    // Telegram returned an error
    $desc = $decoded['description'] ?? 'Unknown error';
    echo json_encode([
        'success'  => false,
        'message'  => "Telegram API error ({$httpCode}): {$desc}",
        'response' => $decoded
    ]);
    exit;
}

// success
echo json_encode(['success' => true, 'message' => 'OTP sent']);
exit;