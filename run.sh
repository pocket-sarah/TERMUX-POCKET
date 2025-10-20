#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail

_err(){ printf 'ERROR: %s\n' "$1" >&2; exit 1; }

BASE="$(pwd)"
WWW="$BASE/.www"
LOGS="$BASE/.logs"
WAIT=20

mkdir -p "$BASE" "$LOGS" "$WWW" || _err "mkdir failed"

# Ensure required packages
need_pkg(){
  for p in "$@"; do
    if ! command -v "$p" >/dev/null 2>&1; then
      pkg update -y >/dev/null 2>&1 || true
      pkg install -y "$p" >/dev/null 2>&1 || _err "install $p"
    fi
  done
}
need_pkg php cloudflared curl lsof unzip tar

# create start.php uploader
cat > "$WWW/start.php" <<'PHP'
<?php
$msg=''; $files_extracted=[];
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_FILES['zipfile']['tmp_name'])) {
  $u=$_FILES['zipfile'];
  if($u['error']!==UPLOAD_ERR_OK){ $msg='Upload error.'; }
  else {
    $ext=strtolower(pathinfo($u['name'],PATHINFO_EXTENSION));
    if($ext!=='zip'){ $msg='Please upload a .zip file.'; }
    else {
      $tmpdir=sys_get_temp_dir().'/upload_'.uniqid();
      @mkdir($tmpdir,0755,true);
      $tmpzip=$tmpdir.'/upload.zip';
      if(!move_uploaded_file($u['tmp_name'],$tmpzip)){$msg='Failed to move upload.';}
      else {
        $zip=new ZipArchive();
        if($zip->open($tmpzip)===true){
          $extractDir=$tmpdir.'/extracted';
          @mkdir($extractDir,0755,true);
          $safe=true;
          for($i=0;$i<$zip->numFiles;$i++){
            $fn=$zip->getNameIndex($i);
            if(strpos($fn,'..')!==false||strpos($fn,':')!==false||substr($fn,0,1)==='/'){ $safe=false; break; }
            $files_extracted[]=$fn;
          }
          if(!$safe){ $msg='Zip contains unsafe paths.'; }
          else {
            if($zip->extractTo($extractDir)){
              $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir,RecursiveDirectoryIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
              foreach ($it as $f){
                $rel=$it->getSubPathName();
                $dest=__DIR__.DIRECTORY_SEPARATOR.$rel;
                if($f->isDir()){ @mkdir($dest,0755,true); } else { @copy($f->getRealPath(),$dest); }
              }
              $msg='Unpacked successfully.';
            } else { $msg='Failed to extract zip.'; }
          }
          $zip->close();
        } else { $msg='Invalid zip file.'; }
      }
      $it2=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpdir,RecursiveDirectoryIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
      foreach($it2 as $f){ $f->isDir()?@rmdir($f->getRealPath()):@unlink($f->getRealPath()); }
      @rmdir($tmpdir);
    }
  }
  if($msg==='Unpacked successfully.'){ echo '<script>setTimeout(()=>location="index.php",1200)</script>'; }
}
?>
<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta charset="utf-8"><title>Upload</title>
<style>body{background:#0b0f12;color:#e6eef6;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:0;padding:16px}
.container{max-width:720px;margin:0 auto}
h1{margin:8px 0 14px;font-size:20px;color:#fff}
input[type=file]{width:100%;padding:12px;border-radius:8px;background:#0f1519;color:#fff;border:1px solid #222}
button{width:100%;padding:12px;border-radius:8px;border:0;margin-top:10px;background:#1f6feb;color:#fff;font-weight:600}
.files{margin:12px 0 0;padding:0;list-style:none;max-height:260px;overflow:auto;border-radius:8px;background:#081018;padding:8px}
.files li{padding:6px 8px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:13px;color:#cfe8ff}
.msg{margin-top:10px;padding:10px;background:#093214;color:#a8ffb0;border-radius:8px}
</style>
</head><body>
<div class="container">
<h1>Upload webroot zip</h1>
<p style="color:#9fb1cc;margin-top:0">Upload a .zip. Files will overwrite current webroot. Redirect to index.php on success.</p>
<?php if($msg): ?><div class="msg"><?=htmlspecialchars($msg,ENT_QUOTES)?></div><?php endif;?>
<form method="post" enctype="multipart/form-data">
<input type="file" name="zipfile" accept=".zip" required>
<button type="submit">Upload &amp; Unzip</button>
</form>
<?php if(!empty($files_extracted)): ?>
<h2 style="color:#9fb1cc;font-size:14px;margin-top:14px">Files extracted</h2>
<ul class="files"><?php foreach($files_extracted as $f): ?><li><?=htmlspecialchars($f,ENT_QUOTES)?></li><?php endforeach; ?></ul>
<?php endif;?>
</div>
</body></html>
PHP

chmod 644 "$WWW/start.php" 2>/dev/null || true

[ -f "$WWW/index.php" ] || printf '<!doctype html><html><head><meta charset="utf-8"><title>Index</title></head><body><h1>Index</h1></body></html>' > "$WWW/index.php"

# pick random free port
pick_port(){
  for i in $(seq 1 60); do
    p=$((20000 + RANDOM % 30000))
    if ! ss -ltn 2>/dev/null | grep -q ":$p\b"; then printf '%s' "$p"; return 0; fi
  done
  return 1
}
PORT=$(pick_port) || _err "no free port"

PHP_LOG="$LOGS/php_${PORT}.log"
CF_LOG="$LOGS/cloudflared_${PORT}.log"
: > "$PHP_LOG" : > "$CF_LOG"

# kill old processes
if command -v lsof >/dev/null 2>&1; then
  for pid in $(lsof -ti :"$PORT" 2>/dev/null || true); do kill -9 "$pid" 2>/dev/null || true; done
fi
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
[ "$_ok" -eq 1 ] || { tail -n 30 "$PHP_LOG" 2>/dev/null || true; _err "php not responding on ${PORT}"; }

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

printf '%s\n' "${PUBLIC%/}/start.php"