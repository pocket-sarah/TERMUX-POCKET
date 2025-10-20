#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail

BASE="$(pwd)"
WWW="$BASE/.www"
LOGS="$BASE/.logs"
WAIT=20

mkdir -p "$WWW" "$LOGS"

_err(){ printf 'ERROR: %s\n' "$1" >&2; exit 1; }

# ensure packages
need_pkg(){ for p in "$@"; do command -v "$p" >/dev/null 2>&1 || { pkg update -y >/dev/null 2>&1 || true; pkg install -y "$p" >/dev/null 2>&1 || _err "install $p"; }; done; }
need_pkg php unzip curl cloudflared lsof

# create start.php for live upload & auto redirect
cat > "$WWW/start.php" <<'PHP'
<?php
session_start();
$msg=''; $files_extracted=[];

if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_FILES['zipfile']['tmp_name'])) {
    $u=$_FILES['zipfile'];
    if($u['error']!==UPLOAD_ERR_OK){ $msg='Upload error'; }
    else{
        $ext=strtolower(pathinfo($u['name'],PATHINFO_EXTENSION));
        if($ext!=='zip'){ $msg='Please upload a .zip file'; }
        else{
            $tmpzip=sys_get_temp_dir().'/upload_'.uniqid().'.zip';
            move_uploaded_file($u['tmp_name'],$tmpzip);
            $zip=new ZipArchive();
            if($zip->open($tmpzip)===true){
                $total=$zip->numFiles;
                $_SESSION['progress']=[];
                for($i=0;$i<$total;$i++){
                    $fn=$zip->getNameIndex($i);
                    $zip->extractTo(__DIR__,[$fn]);
                    $_SESSION['progress'][]=$fn;
                }
                $zip->close();
                $msg='Unpacked successfully';
            } else { $msg='Invalid zip'; }
            unlink($tmpzip);
        }
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Upload</title>
<style>
body{background:#0b0f12;color:#e6eef6;font-family:system-ui;padding:16px}
.container{max-width:720px;margin:auto}
h1{color:#fff}
input[type=file],button{width:100%;padding:12px;border-radius:8px;margin-top:8px}
input[type=file]{background:#0f1519;color:#fff;border:1px solid #222}
button{background:#1f6feb;color:#fff;font-weight:600;border:0}
.progress{background:#111;border-radius:8px;overflow:hidden;margin-top:12px;height:20px}
.bar{height:100%;width:0;background:#1f6feb;transition:width 0.2s}
.files{margin-top:12px;list-style:none;padding:0;color:#cfe8ff;font-size:13px}
.msg{margin-top:10px;color:#a8ffb0}
</style>
</head><body>
<div class="container">
<h1>Upload zip</h1>
<?php if($msg):?><div class="msg"><?=htmlspecialchars($msg,ENT_QUOTES)?></div><?php endif;?>
<form method="post" enctype="multipart/form-data">
<input type="file" name="zipfile" accept=".zip" required>
<button type="submit">Upload & Unzip</button>
</form>
<div class="progress"><div class="bar" id="bar"></div></div>
<ul class="files" id="filelist"></ul>
<script>
let bar=document.getElementById('bar'),list=document.getElementById('filelist'),msgEl=document.querySelector('.msg');
function fetchProgress(){
  fetch('progress.php').then(r=>r.json()).then(data=>{
    list.innerHTML=data.files.map(f=>'<li>'+f+'</li>').join('');
    bar.style.width=(data.files.length/data.total*100)+'%';
    if(data.complete){ msgEl.textContent='Done! Redirecting...'; setTimeout(()=>location='index.php',1200); }
    else setTimeout(fetchProgress,500);
  });
}
<?php if($msg==='Unpacked successfully'){ ?>setTimeout(fetchProgress,500);<?php } ?>
</script>
</div>
</body></html>
PHP

# create progress.php
cat > "$WWW/progress.php" <<'PHP'
<?php
session_start();
$total = isset($_SESSION['progress']) ? count($_SESSION['progress']) : 1;
$files = $_SESSION['progress'] ?? [];
$complete = count($files)>= $total;
header('Content-Type: application/json');
echo json_encode(['files'=>$files,'total'=>$total,'complete'=>$complete]);
PHP

chmod 644 "$WWW/start.php" "$WWW/progress.php"

[ -f "$WWW/index.php" ] || printf '<!doctype html><html><head><meta charset="utf-8"><title>Index</title></head><body><h1>Index</h1></body></html>' > "$WWW/index.php"

# pick random free port
pick_port(){ for i in $(seq 1 60); do p=$((20000 + RANDOM % 30000)); ! ss -ltn 2>/dev/null | grep -q ":$p\b" && { echo "$p"; return 0; }; done; return 1; }
PORT=$(pick_port) || { echo "No free port found"; exit 1; }

PHP_LOG="$LOGS/php_${PORT}.log"
CF_LOG="$LOGS/cloudflared_${PORT}.log"
: > "$PHP_LOG" : > "$CF_LOG"

pkill -f "php -S 127.0.0.1:${PORT}" 2>/dev/null || true
pkill -f "cloudflared tunnel --url" 2>/dev/null || true
sleep 0.2

nohup php -S 127.0.0.1:"$PORT" -t "$WWW" >"$PHP_LOG" 2>&1 &
sleep 1

# wait PHP
_ok=0
for i in {1..5}; do
  curl -fs "http://127.0.0.1:$PORT/start.php" 2>/dev/null | grep -qi 'Upload' && _ok=1 && break
  sleep 0.6
done
[ "$_ok" -eq 1 ] || { tail -n 30 "$PHP_LOG"; echo "PHP not responding"; exit 1; }

nohup cloudflared tunnel --url "http://127.0.0.1:$PORT" --loglevel info >"$CF_LOG" 2>&1 &
sleep 2

# fetch public URL
END=$((SECONDS+WAIT))
PUBLIC=""
while [ $SECONDS -le $END ]; do
  PUBLIC=$(grep -Eo 'https?://[a-z0-9-]+\.trycloudflare\.com' "$CF_LOG" 2>/dev/null | tail -n1 || true)
  [ -n "$PUBLIC" ] && break
  sleep 1
done
[ -n "$PUBLIC" ] || { echo "No public URL"; exit 1; }

printf 'Live upload URL: %s/start.php\n' "${PUBLIC%/}"