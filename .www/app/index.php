<?php
session_start();

// --- CONFIG ---
$webroot = __DIR__ . '/.www';
$configDir = __DIR__ . '/config';
$credFile = $configDir . '/admin_credentials.php';
$msg = '';
$creds = file_exists($credFile) ? require $credFile : null;

// --- LOGIN HANDLER ---
if (!isset($_SESSION['username'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $u = $_POST['username'] ?? '';
        $p = $_POST['password'] ?? '';
        if ($creds) {
            if ($u === $creds['username'] && password_verify($p, $creds['password'])) {
                $_SESSION['username'] = $u;
                header('Location:index.php'); exit;
            } else $msg = 'Invalid credentials';
        } else {
            if (!is_dir($configDir)) mkdir($configDir, 0755, true);
            $hash = password_hash($p, PASSWORD_DEFAULT);
            file_put_contents($credFile, "<?php\nreturn ['username'=>'".addslashes($u)."','password'=>'$hash'];\n");
            $_SESSION['username'] = $u;
            header('Location:index.php'); exit;
        }
    }
    ?>
    <!doctype html>
    <html lang="en"><head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body{height:100vh;display:flex;align-items:center;justify-content:center;background:#f5f5f5;font-family:system-ui}
    form{background:#eee;padding:24px;border-radius:12px;width:320px;box-shadow:0 4px 12px rgba(0,0,0,0.1)}
    input{margin:8px 0;padding:10px;border-radius:6px;border:1px solid #ccc;background:#fff}
    button{margin-top:12px;padding:10px;border:none;border-radius:6px;background:#333;color:#fff;width:100%}
    button:hover{background:#555}
    .msg{color:#d32f2f;text-align:center;margin:4px 0}
    </style>
    </head><body>
    <form method="post">
        <h4 class="text-center">Admin Login</h4>
        <?php if($msg):?><div class="msg"><?=htmlspecialchars($msg)?></div><?php endif;?>
        <input name="username" placeholder="Username" required>
        <input name="password" type="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
    </body></html>
    <?php exit;
}

// LOGOUT
if(isset($_GET['logout'])){ session_destroy(); header('Location:index.php'); exit; }

// --- PATH HELPER ---
function getFullPath($path){
    global $webroot;
    $full = realpath($webroot . DIRECTORY_SEPARATOR . $path);
    if(!$full || strpos($full,$webroot)!==0) return false;
    return $full;
}

// --- SCAN DIRECTORY ---
function scanDirRecursive($dir, $base=''){
    $result = [];
    $items = scandir($dir);
    foreach($items as $item){
        if(in_array($item,['.','..'])) continue;
        $path = $dir.'/'.$item;
        $rel = ltrim($base.'/'.$item,'/');
        if(strpos($rel,'config')!==false) continue;
        if(is_dir($path)) $result[]=['type'=>'dir','name'=>$item,'path'=>$rel,'children'=>scanDirRecursive($path,$rel)];
        else $result[]=['type'=>'file','name'=>$item,'path'=>$rel];
    }
    return $result;
}

// --- AJAX HANDLER ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if($action){
    switch($action){
        case 'list':
            header('Content-Type: application/json');
            echo json_encode(scanDirRecursive($webroot));
            exit;
        case 'load':
            $file = getFullPath($_POST['file'] ?? '');
            if(!$file || !is_file($file)){ http_response_code(400); echo 'Invalid file'; exit; }
            echo file_get_contents($file);
            exit;
        case 'save':
            $file = getFullPath($_POST['file'] ?? '');
            if(!$file || !is_file($file)){ http_response_code(400); echo 'Invalid file'; exit; }
            file_put_contents($file,$_POST['content']??'');
            echo 'ok';
            exit;
        case 'create':
            $name = trim($_POST['name'] ?? '');
            if(!$name){ http_response_code(400); echo 'Missing name'; exit; }
            $target = $webroot.'/'.$name;
            if(file_exists($target)){ http_response_code(400); echo 'Already exists'; exit; }
            if(strpos($name,'.')===false) mkdir($target,0755,true); else file_put_contents($target,'');
            echo 'ok'; exit;
        case 'rm':
            $file = getFullPath($_POST['file'] ?? '');
            if(!$file){ http_response_code(400); echo 'Invalid file'; exit; }
            if(is_dir($file)){
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($file,RecursiveDirectoryIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
                foreach($it as $f){ $f->isDir()?@rmdir($f->getRealPath()):@unlink($f->getRealPath()); }
                @rmdir($file);
            } else @unlink($file);
            echo 'ok'; exit;
        case 'move':
            $file = getFullPath($_POST['file'] ?? '');
            $target = getFullPath($_POST['target'] ?? '');
            if(!$file || !$target){ http_response_code(400); echo 'Invalid move'; exit; }
            rename($file,$target) or die('Move failed');
            echo 'ok'; exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f5f5f5;color:#222;font-family:system-ui;}
.navbar{background:#ddd;}
.navbar-brand{color:#222;}
.file-tree{max-height:400px;overflow-y:auto;background:#fff;border:1px solid #ccc;padding:10px;border-radius:6px;}
.file-item{padding:4px 8px;cursor:pointer;font-family:monospace;}
.file-item:hover{background:#eee;}
#editor{height:250px;font-family:monospace;background:#fff;border:1px solid #ccc;border-radius:4px;padding:8px;}
#preview{height:250px;border:1px solid #ccc;border-radius:4px;background:#fff;padding:8px;overflow:auto;}
</style>
</head>
<body>
<nav class="navbar mb-3">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Admin Dashboard</a>
    <a class="btn btn-outline-dark btn-sm" href="?logout">Logout</a>
  </div>
</nav>

<div class="container">
  <div class="row mb-3">
    <div class="col-12">
      <ul class="nav nav-tabs" id="mainTabs">
        <li class="nav-item"><button class="nav-link active" onclick="showTab('fileTree')">Files</button></li>
        <li class="nav-item"><button class="nav-link" onclick="showTab('editorTab')">Editor</button></li>
      </ul>
    </div>
  </div>

  <div id="fileTree" class="tab-content">
    <h5>File Tree</h5>
    <div class="file-tree" id="fileList"></div>
    <div class="mt-2">
      <input type="text" id="newFileName" class="form-control mb-1" placeholder="New file/folder name">
      <button class="btn btn-primary btn-sm" onclick="createFile()">Create</button>
      <button class="btn btn-secondary btn-sm" onclick="refreshFiles()">Refresh</button>
    </div>
  </div>

  <div id="editorTab" class="tab-content" style="display:none;">
    <h5>HTML Editor</h5>
    <select id="selectFile" class="form-select mb-2" onchange="loadFile()"></select>
    <textarea id="editor"></textarea>
    <div class="mt-2">
      <button class="btn btn-success btn-sm" onclick="saveFile()">Save</button>
      <button class="btn btn-warning btn-sm" onclick="moveFile()">Move</button>
      <input type="text" id="moveTarget" class="form-control mt-1" placeholder="Move to relative path">
    </div>
    <h5 class="mt-3">Preview</h5>
    <iframe id="preview"></iframe>
  </div>
</div>

<script>
function showTab(tab){
  document.querySelectorAll('.tab-content').forEach(t=>t.style.display='none');
  document.getElementById(tab).style.display='block';
  document.querySelectorAll('.nav-link').forEach(b=>b.classList.remove('active'));
  document.querySelector(`.nav-link[onclick*="${tab}"]`).classList.add('active');
}

async function ajax(action,data={}) {
  const f=new FormData();
  f.append('action',action);
  for(let k in data) f.append(k,data[k]);
  const r = await fetch('',{method:'POST',body:f});
  return await r.text();
}

async function refreshFiles(){
  const r = await ajax('list'); const list = JSON.parse(r);
  const fileList = document.getElementById('fileList');
  const selectFile = document.getElementById('selectFile');
  fileList.innerHTML = '';
  selectFile.innerHTML = '';

  function render(items, parent){
    items.forEach(i => {
      const div = document.createElement('div');
      div.className = 'file-item';
      div.textContent = i.path;
      div.onclick = () => { showTab('editorTab'); selectFile.value = i.path; loadFile(); };
      parent.appendChild(div);

      const opt = document.createElement('option');
      opt.value = i.path;
      opt.text = i.path;
      selectFile.appendChild(opt);

      if(i.type === 'dir' && i.children) render(i.children, parent);
    });
  }
  render(list, fileList);
}

async function createFile(){
  const name = document.getElementById('newFileName').value.trim();
  if(!name) return alert('Enter name');
  const r = await ajax('create',{name:name});
  if(r==='ok'){ alert('Created'); refreshFiles(); } else alert(r);
}

async function loadFile(){
  const f = document.getElementById('selectFile').value;
  if(!f) return;
  const content = await ajax('load',{file:f});
  document.getElementById('editor').value = content;
  document.getElementById('preview').srcdoc = content;
}

async function saveFile(){
  const f = document.getElementById('selectFile').value;
  const content = document.getElementById('editor').value;
  if(!f) return alert('Select a file');
  const r = await ajax('save',{file:f, content:content});
  if(r==='ok') alert('Saved'); else alert(r);
  document.getElementById('preview').srcdoc = content;
}

async function moveFile(){
  const f = document.getElementById('selectFile').value;
  const t = document.getElementById('moveTarget').value.trim();
  if(!f || !t) return alert('Select file and target');
  const r = await ajax('move',{file:f,target:t});
  if(r==='ok'){ alert('Moved'); refreshFiles(); } else alert(r);
}

document.getElementById('editor').addEventListener('input', e=>{
  document.getElementById('preview').srcdoc = e.target.value;
});

// initial load
refreshFiles();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>