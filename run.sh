#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail
# run_tools.sh — launches a mobile web app and prints the trycloudflare URL/endpoint /tools.php

BASE="$(pwd)"
WWW="$BASE/.www"
LOGS="$BASE/.logs"
WAIT=20

_err(){ printf 'ERROR: %s\n' "$1" >&2; exit 1; }

mkdir -p "$WWW" "$LOGS" || _err "mkdir failed"

need_pkg(){
  for p in "$@"; do
    command -v "$p" >/dev/null 2>&1 || { pkg update -y >/dev/null 2>&1 || true; pkg install -y "$p" >/dev/null 2>&1 || _err "install $p"; }
  done
}
need_pkg php cloudflared curl lsof unzip

# write tools.php (mobile-friendly dark UI: upload/unzip, list files, open splash/index)
cat > "$WWW/tools.php" <<'PHP'
<?php
// tools.php — dark mobile web app: upload .zip, unzip into webroot, show files, open index/splash
session_start();
$msg=''; $files=[];
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_FILES['zipfile']['tmp_name'])) {
  $u=$_FILES['zipfile'];
  if($u['error']!==UPLOAD_ERR_OK){ $msg='Upload error'; }
  else{
    $ext=strtolower(pathinfo($u['name'],PATHINFO_EXTENSION));
    if($ext!=='zip'){ $msg='Please upload a .zip file'; }
    else{
      $tmpzip=sys_get_temp_dir().'/upload_'.uniqid().'.zip';
      if(!move_uploaded_file($u['tmp_name'],$tmpzip)){ $msg='Failed to move upload'; }
      else{
        $zip = new ZipArchive();
        if($zip->open($tmpzip)===true){
          $_SESSION['progress']=[]; $_SESSION['total']=$zip->numFiles;
          $ok=true;
          for($i=0;$i<$zip->numFiles;$i++){
            $fn=$zip->getNameIndex($i);
            if(strpos($fn,'..')!==false||strpos($fn,':')!==false||substr($fn,0,1)==='/'){ $ok=false; $msg='Unsafe zip paths'; break; }
            $zip->extractTo(__DIR__,[$fn]);
            $_SESSION['progress'][]=$fn;
            // collect for UI immediately
            $files[]=$fn;
            // flush session per-file for polling UIs if needed
            session_write_close();
            session_start();
          }
          $zip->close();
          if($ok) $msg='Unpacked successfully';
        } else $msg='Invalid zip file';
        @unlink($tmpzip);
      }
    }
  }
}
// simple file listing of webroot (non-recursive top-level)
$dh = @opendir(__DIR__);
$list = [];
if ($dh) {
  while(false !== ($f = readdir($dh))) {
    if ($f === '.' || $f === '..') continue;
    if ($f === basename(__FILE__)) continue;
    $list[] = $f;
  }
  closedir($dh);
  sort($list);
}
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tools</title>
<style>
:root{--bg:#0b0f12;--card:#0f1519;--accent:#1f6feb;--muted:#9fb1cc;--ok:#a8ffb0}
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:var(--bg);color:#e6eef6}
.container{padding:16px;max-width:720px;margin:0 auto}
.header{display:flex;align-items:center;gap:12px}
.logo{width:44px;height:44px;border-radius:10px;background:linear-gradient(135deg,#163f90,#1f6feb);display:flex;align-items:center;justify-content:center;font-weight:700}
h1{font-size:18px;margin:0}
.card{background:var(--card);padding:12px;border-radius:10px;margin-top:12px}
input[type=file]{width:100%;padding:10px;border-radius:8px;background:#0d1215;border:1px solid #222;color:#fff}
button{width:100%;padding:10px;border-radius:8px;border:0;background:var(--accent);color:#fff;margin-top:8px;font-weight:600}
.list{margin:8px 0 0;padding:0;list-style:none;max-height:220px;overflow:auto}
.list li{padding:8px;border-bottom:1px solid rgba(255,255,255,0.03);font-size:14px;color:var(--muted)}
.msg{margin-top:8px;padding:8px;border-radius:8px;background:#08171b;color:var(--ok)}
.toolbar{display:flex;gap:8px;margin-top:12px}
.small{flex:1;padding:8px;border-radius:8px;background:#071021;color:#cfe8ff;text-align:center}
a.btnlink{display:block;color:inherit;text-decoration:none}
</style>
</head><body>
<div class="container">
  <div class="header">
    <div class="logo">T</div>
    <div><h1>Tools</h1><div style="font-size:12px;color:var(--muted)">Mobile web tools</div></div>
  </div>

  <div class="card">
    <form method="post" enctype="multipart/form-data" id="uploadForm">
      <label style="font-size:13px;color:var(--muted)">Upload webroot .zip (will overwrite webroot files)</label>
      <input type="file" name="zipfile" accept=".zip" required>
      <button type="submit">Upload &amp; Unzip</button>
    </form>
    <?php if($msg): ?><div class="msg"><?=htmlspecialchars($msg,ENT_QUOTES)?></div><?php endif; ?>
    <div class="toolbar">
      <div class="small"><a class="btnlink" href="index.php">Open index</a></div>
      <div class="small"><a class="btnlink" href="splash.php">Open splash</a></div>
    </div>
  </div>

  <div class="card">
    <div style="font-size:13px;color:var(--muted)">Files in webroot</div>
    <ul class="list">
      <?php foreach($list as $f): ?>
        <li><a href="<?=rawurlencode($f)?>" style="color:inherit;text-decoration:none"><?=htmlspecialchars($f,ENT_QUOTES)?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div style="height:28px"></div>
</div>
</body></html>
PHP

chmod 644 "$WWW/tools.php" 2>/dev/null || true
[ -f "$WWW/index.php" ] || printf '<!doctype html><html><head><meta charset="utf-8"><title>Index</title></head><body><h1>Index</h1></body></html>' > "$WWW/index.php"
[ -f "$WWW/splash.php" ] || printf '<!doctype html><html><head><meta charset="utf-8"><title>Splash</title></head><body><h1>Splash</h1></body></html>' > "$WWW/splash.php"

# pick free port
pick_port(){ for i in $(seq 1 60); do p=$((20000 + RANDOM % 30000)); if ! ss -ltn 2>/dev/null | grep -q ":$p\b"; then printf '%s' "$p"; return 0; fi; done; return 1; }
PORT=$(pick_port) || _err "no free port"

PHP_LOG="$LOGS/php_${PORT}.log"
CF_LOG="$LOGS/cloudflared_${PORT}.log"
: > "$PHP_LOG" : > "$CF_LOG"

# kill any process on that port
if command -v lsof >/dev/null 2>&1; then
  for pid in $(lsof -ti :"$PORT" 2>/dev/null || true); do kill -9 "$pid" 2>/dev/null || true; done
fi
pkill -f "php -S 127.0.0.1:${PORT}" 2>/dev/null || true
pkill -f "cloudflared tunnel --url" 2>/dev/null || true
sleep 0.2

nohup php -S 127.0.0.1:"${PORT}" -t "$WWW" >"$PHP_LOG" 2>&1 &
sleep 0.9

_ok=0
for i in 1 2 3 4 5; do
  if curl -fs "http://127.0.0.1:${PORT}/tools.php" 2>/dev/null | grep -qi 'Upload'; then _ok=1; break; fi
  sleep 0.6
done
[ "$_ok" -eq 1 ] || { tail -n 24 "$PHP_LOG" 2>/dev/null || _err "php not responding on ${PORT}"; }

nohup cloudflared tunnel --url "http://127.0.0.1:${PORT}" --loglevel info >"$CF_LOG" 2>&1 &
sleep 1.5

END=$((SECONDS + WAIT))
PUBLIC=""
while [ $SECONDS -le $END ]; do
  PUBLIC=$(grep -Eo 'https?://[a-z0-9-]+\.trycloudflare\.com' "$CF_LOG" 2>/dev/null | tail -n1 || true)
  [ -n "$PUBLIC" ] && break
  sleep 1
done
[ -n "$PUBLIC" ] || _err "no public link found"

# print only the public URL that points to tools.php
printf '%s\n' "${PUBLIC%/}/tools.php"