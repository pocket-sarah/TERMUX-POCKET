#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail

BASE="$HOME/build/POCKET"
WWW="$BASE/.www"
LOGS="$BASE/.logs"
PHP_LOG="$LOGS/php.log"
CF_LOG="$LOGS/cloudflared.log"
WAIT=20

_err(){ printf 'ERROR: %s\n' "$1" >&2; exit 1; }

mkdir -p "$WWW" "$LOGS"

# install required packages if missing
need_pkg() {
  for p in "$@"; do
    if ! command -v "$p" >/dev/null 2>&1; then
      pkg update -y >/dev/null 2>&1 || true
      pkg install -y "$p" >/dev/null 2>&1 || _err "install $p"
    fi
  done
}
need_pkg php cloudflared curl python unzip lsof

# create start.php uploader (overwrites)
cat > "$WWW/start.php" <<'PHP'
<?php
$msg='';
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
            if(!move_uploaded_file($u['tmp_name'],$tmpzip)){ $msg='Failed to move upload.'; }
            else {
                $zip=new ZipArchive();
                if($zip->open($tmpzip)===true){
                    $extractDir=$tmpdir.'/extracted';
                    @mkdir($extractDir,0755,true);
                    $safe=true;
                    for($i=0;$i<$zip->numFiles;$i++){
                        $fn=$zip->getNameIndex($i);
                        if(strpos($fn,'..')!==false || strpos($fn,':')!==false || substr($fn,0,1)==='/'){ $safe=false; break; }
                    }
                    if(!$safe){ $msg='Zip contains unsafe paths.'; }
                    else {
                        if($zip->extractTo($extractDir)){
                            $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir,RecursiveDirectoryIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
                            foreach($it as $f){
                                $rel = $it->getSubPathName();
                                $dest = __DIR__.DIRECTORY_SEPARATOR.$rel;
                                if($f->isDir()){ @mkdir($dest,0755,true); }
                                else { @copy($f->getRealPath(), $dest); }
                            }
                            $msg='Unpacked successfully.';
                        } else { $msg='Failed to extract zip.'; }
                    }
                    $zip->close();
                } else { $msg='Invalid zip file.'; }
            }
            // cleanup
            $it2=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpdir,RecursiveDirectoryIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach($it2 as $f){ $f->isDir()?@rmdir($f->getRealPath()):@unlink($f->getRealPath()); }
            @rmdir($tmpdir);
        }
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Upload webroot zip</title>
<style>body{font-family:system-ui,Arial;margin:24px}.box{max-width:720px;margin:auto}.msg{margin:12px 0;color:#070}</style>
</head><body>
<div class="box">
<h1>Upload webroot zip folder</h1>
<p>Upload a .zip containing your web files. It will overwrite the webroot (including index.php & splash.php).</p>
<?php if($msg):?><div class="msg"><?=htmlspecialchars($msg,ENT_QUOTES)?></div><?php endif;?>
<form method="post" enctype="multipart/form-data">
<input type="file" name="zipfile" accept=".zip" required>
<button type="submit">Upload &amp; Unzip</button>
<button type="button" onclick="location.href='splash.php'">Go to splash.php</button>
</form>
</div>
</body></html>
PHP

# create minimal splash.php if missing
[ -f "$WWW/splash.php" ] || cat > "$WWW/splash.php" <<'PHP'
<?php
echo "<!doctype html><html><head><meta charset='utf-8'><title>Splash</title></head><body><h1>Splash page</h1><p>Site is live.</p></body></html>";
PHP

chmod 644 "$WWW/start.php" "$WWW/splash.php" 2>/dev/null || true

# pick random free port
pick_port(){
  for i in $(seq 1 60); do
    p=$((20000 + RANDOM % 30000))
    if ! ss -ltn 2>/dev/null | grep -q ":$p\b"; then
      printf '%s' "$p"; return 0
    fi
  done
  return 1
}
PORT=$(pick_port) || _err "no free port"

PHP_LOG="$LOGS/php_${PORT}.log"
CF_LOG="$LOGS/cloudflared_${PORT}.log"
: > "$PHP_LOG"
: > "$CF_LOG"

# kill anything on that port
for pid in $(lsof -ti :"$PORT" 2>/dev/null || true); do kill -9 "$pid" 2>/dev/null || true; done
pkill -f "php -S 127.0.0.1:${PORT}" 2>/dev/null || true
pkill -f "cloudflared tunnel --url" 2>/dev/null || true
sleep 0.2

# launch php bound to .www
nohup php -S 127.0.0.1:"${PORT}" -t "$WWW" >"$PHP_LOG" 2>&1 &
sleep 0.8

# confirm start.php reachable
_ok=0
for i in 1 2 3 4 5; do
  if curl -fs "http://127.0.0.1:${PORT}/start.php" 2>/dev/null | grep -qi 'Upload'; then _ok=1; break; fi
  sleep 0.6
done
[ "$_ok" -eq 1 ] || { tail -n 30 "$PHP_LOG" 2>/dev/null || true; _err "php not responding on ${PORT}"; }

# start cloudflared
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

# build link to start.php with url param (url-encode using python)
ENC=$(python -c "import sys,urllib.parse; print(urllib.parse.quote(sys.argv[1], safe=''))" "$PUBLIC")
LONG="${PUBLIC}/start.php?url=${ENC}"

# try clck.ru then is.gd
SHORT=""
CLCK_RESP=$(curl -fs -L -d "url=${LONG}" --compressed "https://clck.ru/" 2>/dev/null || true)
SHORT=$(printf '%s' "$CLCK_RESP" | grep -Eo 'https?://clck\.ru/[A-Za-z0-9]+' | head -n1 || true)
if [ -z "$SHORT" ]; then
  SHORT=$(curl -fsG --data-urlencode "url=${LONG}" "https://is.gd/create.php?format=simple" 2>/dev/null || true)
fi
[ -n "$SHORT" ] || _err "shortening failed"

printf '%s\n' "$SHORT"