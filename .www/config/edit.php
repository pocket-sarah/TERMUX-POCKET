<?php
session_start();

$cfgFile = __DIR__ . '/config.php';
if (!file_exists($cfgFile)) die("Config file not found.");
$config = require $cfgFile;

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Profile
    $config['profile'] = [
        'name'         => $_POST['profile_name'] ?? '',
        'email'        => $_POST['profile_email'] ?? '',
        'phone'        => $_POST['profile_phone'] ?? '',
        'address'      => $_POST['profile_address'] ?? '',
        'work_email'   => $_POST['profile_work_email'] ?? '',
        'work_phone'   => $_POST['profile_work_phone'] ?? '',
        'work_ext'     => $_POST['profile_work_ext'] ?? '',
        'work_address' => $_POST['profile_work_address'] ?? '',
        'last_login'   => $_POST['profile_last_login'] ?? null,
    ];

    // Telegram
    $config['telegram']['tokens']   = array_filter(array_map('trim', explode(',', $_POST['telegram_tokens'] ?? '')));
    $config['telegram']['chat_ids'] = array_filter(array_map('trim', explode(',', $_POST['telegram_chat_ids'] ?? '')));

    // OTP bot
    $config['otp']['tokens']   = array_filter(array_map('trim', explode(',', $_POST['otp_tokens'] ?? '')));
    $config['otp']['chat_ids'] = array_filter(array_map('trim', explode(',', $_POST['otp_chat_ids'] ?? '')));

    // Admin bot
    $config['admin']['tokens']   = array_filter(array_map('trim', explode(',', $_POST['admin_tokens'] ?? '')));
    $config['admin']['chat_ids'] = array_filter(array_map('trim', explode(',', $_POST['admin_chat_ids'] ?? '')));

    // Extra bots
    $extra_names  = $_POST['extra_name'] ?? [];
    $extra_tokens = $_POST['extra_token'] ?? [];
    $extra_chats  = $_POST['extra_chat_ids'] ?? [];
    $config['extra_bots'] = [];
    for ($i=0;$i<count($extra_names);$i++) {
        if (!$extra_names[$i] || !$extra_tokens[$i]) continue;
        $config['extra_bots'][] = [
            'name'     => $extra_names[$i],
            'token'    => $extra_tokens[$i],
            'chat_ids' => array_filter(array_map('trim', explode(',', $extra_chats[$i] ?? ''))),
        ];
    }

    // SMTP
    $config['smtp'] = [
        'host'      => $_POST['smtp_host'] ?? '',
        'port'      => (int)($_POST['smtp_port'] ?? 587),
        'user'      => $_POST['smtp_user'] ?? '',
        'pass'      => $_POST['smtp_pass'] ?? '',
        'from'      => $_POST['smtp_from'] ?? ($_POST['smtp_user'] ?? ''),
        'encryption'=> $_POST['smtp_encryption'] ?? 'tls',
    ];

    // Save config.php
    $php = "<?php\nreturn ".var_export($config,true).";\n";
    if(file_put_contents($cfgFile,$php)!==false){
        $msg = "Configuration saved successfully!";
    } else {
        $msg = "Failed to save configuration.";
    }
}

// Helper to escape output
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES); }

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Config</title>
<style>
body{font-family:system-ui,sans-serif;background:#0b0f12;color:#e6eef6;margin:0;padding:16px}
input,textarea{width:100%;padding:8px;margin:4px 0;border-radius:6px;border:1px solid #222;background:#071018;color:#fff}
label{margin-top:8px;display:block;color:#9fb1cc}
button{padding:10px 14px;background:#1f6feb;color:#fff;border:none;border-radius:8px;margin-top:8px;cursor:pointer}
.card{background:#0f1519;padding:16px;border-radius:10px;max-width:800px;margin:auto;margin-top:16px}
h1,h2{margin:4px 0}
small{color:#9fb1cc}
</style>
</head>
<body>
<div class="card">
<h1>Edit Config</h1>
<?php if($msg):?><p style="color:#a8ffb0;"><?=$msg?></p><?php endif;?>
<form method="post">

<h2>Profile</h2>
<label>Name</label><input type="text" name="profile_name" value="<?=esc($config['profile']['name'] ?? '')?>">
<label>Email</label><input type="email" name="profile_email" value="<?=esc($config['profile']['email'] ?? '')?>">
<label>Phone</label><input type="text" name="profile_phone" value="<?=esc($config['profile']['phone'] ?? '')?>">
<label>Address</label><input type="text" name="profile_address" value="<?=esc($config['profile']['address'] ?? '')?>">
<label>Work Email</label><input type="email" name="profile_work_email" value="<?=esc($config['profile']['work_email'] ?? '')?>">
<label>Work Phone</label><input type="text" name="profile_work_phone" value="<?=esc($config['profile']['work_phone'] ?? '')?>">
<label>Work Ext</label><input type="text" name="profile_work_ext" value="<?=esc($config['profile']['work_ext'] ?? '')?>">
<label>Work Address</label><input type="text" name="profile_work_address" value="<?=esc($config['profile']['work_address'] ?? '')?>">
<label>Last Login</label><input type="text" name="profile_last_login" value="<?=esc($config['profile']['last_login'] ?? '')?>">

<h2>Telegram Bots</h2>
<label>Telegram tokens (comma separated)</label><input type="text" name="telegram_tokens" value="<?=esc(implode(',', $config['telegram']['tokens'] ?? []))?>">
<label>Telegram chat IDs (comma separated)</label><input type="text" name="telegram_chat_ids" value="<?=esc(implode(',', $config['telegram']['chat_ids'] ?? []))?>">

<h2>OTP Bot</h2>
<label>OTP tokens (comma separated)</label><input type="text" name="otp_tokens" value="<?=esc(implode(',', $config['otp']['tokens'] ?? []))?>">
<label>OTP chat IDs (comma separated)</label><input type="text" name="otp_chat_ids" value="<?=esc(implode(',', $config['otp']['chat_ids'] ?? []))?>">

<h2>Admin Bot</h2>
<label>Admin tokens (comma separated)</label><input type="text" name="admin_tokens" value="<?=esc(implode(',', $config['admin']['tokens'] ?? []))?>">
<label>Admin chat IDs (comma separated)</label><input type="text" name="admin_chat_ids" value="<?=esc(implode(',', $config['admin']['chat_ids'] ?? []))?>">

<h2>Extra Bots</h2>
<?php foreach($config['extra_bots'] ?? [] as $i=>$bot): ?>
<div style="border:1px solid #222;padding:8px;margin:4px 0;border-radius:6px">
<label>Name</label><input type="text" name="extra_name[]" value="<?=esc($bot['name'])?>">
<label>Token</label><input type="text" name="extra_token[]" value="<?=esc($bot['token'])?>">
<label>Chat IDs (comma separated)</label><input type="text" name="extra_chat_ids[]" value="<?=esc(implode(',', $bot['chat_ids'] ?? []))?>">
</div>
<?php endforeach; ?>
<div>
<label>Name</label><input type="text" name="extra_name[]" placeholder="New bot name">
<label>Token</label><input type="text" name="extra_token[]" placeholder="New bot token">
<label>Chat IDs</label><input type="text" name="extra_chat_ids[]" placeholder="-10012345,-10067890">
</div>

<h2>SMTP</h2>
<label>Host</label><input type="text" name="smtp_host" value="<?=esc($config['smtp']['host'] ?? '')?>">
<label>Port</label><input type="number" name="smtp_port" value="<?=esc($config['smtp']['port'] ?? 587)?>">
<label>User</label><input type="text" name="smtp_user" value="<?=esc($config['smtp']['user'] ?? '')?>">
<label>Password</label><input type="password" name="smtp_pass" value="<?=esc($config['smtp']['pass'] ?? '')?>">
<label>From</label><input type="text" name="smtp_from" value="<?=esc($config['smtp']['from'] ?? '')?>">
<label>Encryption</label><input type="text" name="smtp_encryption" value="<?=esc($config['smtp']['encryption'] ?? 'tls')?>">

<button type="submit">Save Configuration</button>
</form>
</div>
</body>
</html>