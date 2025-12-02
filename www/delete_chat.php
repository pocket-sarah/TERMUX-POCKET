<?php

$configPath = __DIR__ . "/config/config.php";
$config = include $configPath;

if (empty($config['token'])) {
    die("Bot token is missing.");
}

$token = $config['token'];

$response = file_get_contents("https://api.telegram.org/bot{$token}/getUpdates");
$data = json_decode($response, true);

if (!isset($data['result'][0]['message']['chat']['id'])) {
    die("No chats found. Send your bot a message first.");
}

$chatId = $data['result'][0]['message']['chat']['id'];

$config['chat_id'] = $chatId;

// Rewrite config file with detected chat id
$export = "<?php\n\nreturn " . var_export($config, true) . ";";
file_put_contents($configPath, $export);

echo "Chat ID detected and stored: " . $chatId;