<?php
session_start();
$www = __DIR__.'/.www';
$configDir = __DIR__.'/config';
$credFile = $configDir.'/admin_credentials.php';
$msg='';

// Load credentials
$creds = file_exists($credFile) ? require $credFile : null;

// LOGIN HANDLER
if (!isset($_SESSION['username'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $u = $_POST['username'] ?? '';
        $p = $_POST['password'] ?? '';
        if ($creds) {
            if ($u === $creds['username'] && password_verify($p, $creds['password'])) {
                $_SESSION['username'] = $u;
                header('Location:index.php'); exit;
            } else {
                $msg='Invalid credentials';
            }
        } else {
            // First login: save credentials
            if(!is_dir($configDir)) mkdir($configDir,0755,true);
            $hash = password_hash($p,PASSWORD_DEFAULT);
            file_put_contents($credFile,"<?php\nreturn ['username'=>'".addslashes($u)."','password'=>'$hash'];\n");
            $_SESSION['username']=$u;
            header('Location:index.php'); exit;
        }
    }
    // LOGIN FORM
    ?>
    <!doctype html>
    <html lang="en"><head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        body{margin:0;display:flex;justify-content:center;align-items:center;height:100vh;background:#f5f5f5;font-family:system-ui;color:#111}
        form{background:#e0e0e0;padding:24px;border-radius:12px;width:320px;display:flex;flex-direction:column;box-shadow:0 4px 12px rgba(0,0,0,0.12)}
        input{margin:8px 0;padding:10px;border-radius:8px;border:1px solid #ccc;background:#fff;color:#111}
        button{margin-top:12px;padding:12px;border:none;border-radius:8px;background:#333;color:#fff;cursor:pointer}
        button:hover{background:#555}
        .msg{color:#d32f2f;text-align:center;margin:4px 0}
        h2{text-align:center;margin:0 0 12px 0}
    </style>
    </head>
    <body>
        <form method="post">
            <h2>Admin Login</h2>
            <?php if($msg):?><div class="msg"><?=htmlspecialchars($msg)?></div><?php endif;?>
            <input name="username" placeholder="Username" required>
            <input name="password" type="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </body>
    </html>
    <?php exit;
}

// LOGOUT
if(isset($_GET['logout'])){ session_destroy(); header('Location:index.php'); exit; }

// FILE TREE AND EDIT HANDLER
$action = $_POST['action'] ?? null;
$path = $_POST['path'] ?? '';
$fullPath = realpath($www.DIRECTORY_SEPARATOR.$path) ?: '';

if ($action) {
    if (strpos($fullPath,$www)!==0){ http_response_code(403); echo "Forbidden"; exit; }
    switch($action){
        case 'save':
            file_put_contents($fullPath, $_POST['content'] ?? '');
            echo "ok"; exit;
        case 'mkdir':
            mkdir($fullPath,0755,true); echo "ok"; exit;
        case 'rm':
            if(is_dir($fullPath)){
                $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullPath,RecursiveDirectoryIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
                foreach($it as $f){ $f->isDir()?@rmdir($f->getRealPath()):@unlink($f->getRealPath()); }
                @rmdir($fullPath);
            } else { @unlink($fullPath); }
            echo "ok"; exit;
        case 'list':
            $list=[]; $dh=opendir($fullPath ?: $www);
            while(false!==($f=readdir($dh))){ if($f==='.'||$f==='..')continue;$list[]=$f; } closedir($dh);
            sort($list); echo json_encode($list); exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Web App Editor</title>
<style>
body{margin:0;font-family:system-ui;background:#f9f9f9;color:#111}
header{padding:12px;background:#ccc;color:#222;font-weight:bold;display:flex;justify-content:space-between;align-items:center}
main{display:flex;flex-wrap:wrap;height:calc(100vh - 48px)}
aside{width:240px;background:#eee;padding:8px;overflow-y:auto;height:100%}
section{flex:1;padding:8px;overflow:auto;height:100%}
ul{list-style:none;padding:0;margin:0}
li{padding:4px;cursor:pointer;border-radius:4px}
li:hover{background:#ddd}
input,textarea{width:100%;margin:4px 0;padding:6px;border-radius:6px;border:1px solid #bbb;background:#fff;color:#111;font-family:monospace;font-size:14px}
button{margin:4px 0;padding:6px;border-radius:6px;border:none;background:#333;color:#fff;cursor:pointer}
button:hover{background:#555}
#logout{color:#333;text-decoration:none}
</style>
</head>
<body>
<header>
    Web App Editor
    <a href="?logout=1" id="logout">Logout</a>
</header>
<main>
    <aside>
        <h3>Files</h3>
        <button onclick="refreshTree()">Refresh</button>
        <ul id="fileTree"></ul>
        <button onclick="createFile()">+ File</button>
        <button onclick="createFolder()">+ Folder</button>
    </aside>
    <section>
        <h3>Editor</h3>
        <div>Editing: <span id="currentFile">none</span></div>
        <textarea id="editor" rows="20"></textarea>
        <button onclick="saveFile()">Save</button>
    </section>
</main>
<script>
let currentPath = '';
function fetchJSON(path,action){
    return fetch('',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action='+action+'&path='+encodeURIComponent(path)
    }).then(r=>r.json());
}
function refreshTree(){
    fetchJSON('','list').then(list=>{
        const ul=document.getElementById('fileTree'); ul.innerHTML='';
        list.forEach(f=>{
            const li=document.createElement('li');
            li.textContent=f; li.onclick=()=>loadFile(f); ul.appendChild(li);
        });
    });
}
function loadFile(name){
    currentPath=name;
    document.getElementById('currentFile').textContent=name;
    fetch('',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=list&path='+encodeURIComponent(name)
    }).then(r=>r.text()).then(txt=>{
        if(txt.startsWith('[')){ document.getElementById('editor').value=''; } 
        else{ fetchFileContent(name); }
    });
}
function fetchFileContent(name){
    fetch('',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=save&path='+encodeURIComponent(name)
    }).then(r=>r.text()).then(txt=>{
        document.getElementById('editor').value=txt;
    });
}
function saveFile(){
    if(!currentPath) return alert('Select a file first');
    const content=document.getElementById('editor').value;
    fetch('',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=save&path='+encodeURIComponent(currentPath)+'&content='+encodeURIComponent(content)
    }).then(r=>r.text()).then(txt=>{ if(txt==='ok') alert('Saved'); else alert('Error'); });
}
function createFile(){
    const name=prompt('New file name'); if(!name) return;
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=save&path='+encodeURIComponent(name)+'&content='}).then(r=>r.text()).then(()=>refreshTree());
}
function createFolder(){
    const name=prompt('New folder name'); if(!name) return;
    fetch('',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=mkdir&path='+encodeURIComponent(name)
    }).then(r=>r.text()).then(()=>refreshTree());
}
refreshTree();
</script>
</body>
</html>