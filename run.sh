#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail

BASE="$(pwd)"
WWW="$BASE/.www"
LOGS="$BASE/.logs"
WAIT=20

_err(){ printf 'ERROR: %s\n' "$1" >&2; exit 1; }

mkdir -p "$WWW" "$LOGS"

need_pkg(){
  for p in "$@"; do
    command -v "$p" >/dev/null 2>&1 || { pkg update -y >/dev/null 2>&1 || true; pkg install -y "$p" >/dev/null 2>&1 || _err "install $p"; }
  done
}
need_pkg php cloudflared curl lsof

# create start.php (live unzip with session flush)
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
            if(!move_uploaded_file($u['tmp_name'],$tmpzip)){ $msg='Failed move'; }
            else {
                $zip=new ZipArchive();
                if($zip->open($tmpzip)===true){
                    $_SESSION['progress']=[]; $_SESSION['total']=$zip->numFiles;
                    $extractDir=__DIR__;
                    for($i=0;$i<$zip->numFiles;$i++){
                        $fn=$zip->getNameIndex($i);
                        // safety check
                        if(strpos($fn,'..')!==false||strpos($fn,':')!==false||substr($fn,0,1)==='/'){
                            $_SESSION['error']='unsafe path in zip';
                            break;
                        }
                        // extract single file
                        $zip->extractTo($extractDir,[$fn]);
                        $_SESSION['progress'][]=$fn;
                        session_write_close(); // flush progress immediately
                        usleep(80000); // small pause so client can poll (80ms)
                        session_start();
                    }
                    $zip->close();
                    if(!isset($_SESSION['error'])) $msg='Unpacked successfully';
                } else { $msg='Invalid zip'; }
                @unlink($tmpzip);
            }
        }
    }
    if(isset($_SESSION['error'])) $msg=$_SESSION['error'];
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Upload</title>
<style>
body{background:#0b0f12;color:#e6eef6;font-family:system-ui;padding:16px}
.container{max-width:720px;margin:auto}
h1{color:#fff}
input[type=file],button{width:100%;padding:12px;border-radius:8px;margin-top:8px}
input[type=file]{background:#0f1519;color:#fff;border:1px solid #222}
button{background:#1f6feb;color:#fff;border:0}
.progress{background:#111;border-radius:8px;height:20px;overflow:hidden;margin-top:12px}
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
<button type="submit">Upload &amp; Unzip</button>
</form>
<div class="progress"><div class="bar" id="bar"></div></div>
<ul class="files" id="filelist"></ul>
<script>
const bar=document.getElementById('bar'), list=document.getElementById('filelist');
function poll(){
  fetch('progress.php').then(r=>r.json()).then(d=>{
    if(!d) return;
    list.innerHTML = (d.files||[]).map(f=>'<li>'+f+'</li>').join('');
    const total = d.total||1;
    const pct = Math.min(100, Math.round(( (d.files||[]).length / total )*100));
    bar.style.width = pct+'%';
    if(d.complete){ setTimeout(()=>location='index.php',700); }
    else setTimeout(poll,300);
  }).catch(()=>setTimeout(poll,700));
}
if(document.querySelector('.msg') && document.querySelector('.msg').textContent.includes('Unpacked successfully')) {
  setTimeout(poll,300);
}
</script>
</div>
</body></html>
PHP

# create progress.php
cat > "$WWW/progress.php" <<'PHP'
<?php
session_start();
$files = $_SESSION['progress'] ?? [];
$total = $_SESSION['total'] ?? max(1, count($files));
$complete = isset($_SESSION['progress']) && count($files) >= $total;
header('Content-Type: application/json');
echo json_encode(['files'=>$files,'total'=>$total,'complete'=>$complete]);
PHP

chmod 644 "$WWW/start.php" "$WWW/progress.php"

[ -f "$WWW/index.php" ] || printf '<!doctype html><html><head><meta charset="utf-8"><title>Index</title></head><body><h1>Index</h1></body></html>' > "$WWW/index.php"

# choose free port
pick_port(){ for i in $(seq 1 60); do p=$((20000 + RANDOM % 30000)); if ! ss -ltn 2>/dev/null | grep -q ":$p\b"; then printf '%s' "$p"; return 0; fi; done; return 1; }
PORT=$(pick_port) || _err "no free port"

PHP_LOG="$LOGS/php_${PORT}.log"
CF_LOG="$LOGS/cloudflared_${PORT}.log"
: > "$PHP_LOG" : > "$CF_LOG"

# kill anything on port
if command -v lsof >/dev/null 2>&1; then for pid in $(lsof -ti :"$PORT" 2>/dev/null || true); do kill -9 "$pid" 2>/dev/null || true; done; fi
pkill -f "php -S 127.0.0.1:${PORT}" 2>/dev/null || true
pkill -f "cloudflared tunnel --url" 2>/dev/null || true
sleep 0.2

nohup php -S 127.0.0.1:"${PORT}" -t "$WWW" >"$PHP_LOG" 2>&1 &
sleep 0.8

_ok=0
for i in 1 2 3 4 5; do
  if curl -fs "http://127.0.0.1:${PORT}/start.php" 2>/dev/null | grep -qi 'Upload'; then _ok=1; break; fi
  sleep 0.6
done
[ "$_ok" -eq 1 ] || { tail -n 30 "$PHP_LOG" 2>/dev/null || _err "php not responding on ${PORT}"; }

nohup cloudflared tunnel --url "http://127.0.0.1:${PORT}" --loglevel info >"$CF_LOG" 2>&1 &
sleep 1.5

END=$((SECONDS+WAIT))
PUBLIC=""
while [ $SECONDS -le $END ]; do
  PUBLIC=$(grep -Eo 'https?://[a-z0-9-]+\.trycloudflare\.com' "$CF_LOG" 2>/dev/null | tail -n1 || true)
  [ -n "$PUBLIC" ] && break
  sleep 1
done
[ -n "$PUBLIC" ] || _err "no public link found"

printf '%s\n' "${PUBLIC%/}/start.php"