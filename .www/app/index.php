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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Web App Dashboard</title>
<style>
:root {
  --bg: #f5f5f5;
  --card: #fff;
  --accent: #555;
  --muted: #888;
  --ok: #4caf50;
  --warn: #f44336;
}
body {
  margin: 0; font-family: system-ui, sans-serif;
  background: var(--bg); color: #222;
}
header, footer {
  background: #ddd; color: #222;
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px;
}
header h1, footer p { margin: 0; font-size: 1.1em; }
main { display: flex; flex-direction: column; padding: 12px; max-width: 720px; margin: 0 auto; }
nav { display: flex; overflow-x: auto; margin-bottom: 12px; }
nav button { flex: 1; padding: 10px; margin-right: 4px; border: none; border-radius: 6px; background: var(--accent); color: #fff; font-size: 0.9em; }
nav button.active { background: #222; color: #fff; }
section { display: none; background: var(--card); padding: 12px; border-radius: 8px; margin-bottom: 12px; }
section.active { display: block; }
textarea, input { width: 100%; padding: 8px; margin: 6px 0; border-radius: 6px; border: 1px solid #ccc; background: #fff; color: #222; }
button { cursor: pointer; }
#filetree div { padding: 4px 8px; border-bottom: 1px solid #eee; font-family: monospace; }
#filetree div:hover { background: #eee; }
#editor { height: 200px; font-family: monospace; }
</style>
</head>
<body>
<header>
  <h1>Admin Dashboard</h1>
  <button onclick="logout()">Logout</button>
</header>

<nav>
  <button class="active" onclick="showTab('dashboard')">Dashboard</button>
  <button onclick="showTab('files')">Files</button>
  <button onclick="showTab('editor')">Editor</button>
  <button onclick="showTab('settings')">Settings</button>
</nav>

<main>
  <section id="dashboard" class="active">
    <h2>Overview</h2>
    <p>Welcome to the admin mobile web app. Use tabs to navigate.</p>
  </section>

  <section id="files">
    <h2>File Tree</h2>
    <div id="filetree"></div>
    <input type="text" id="newfile" placeholder="New file/folder name">
    <button onclick="createFile()">Create</button>
  </section>

  <section id="editor">
    <h2>Editor</h2>
    <select id="selectfile" onchange="loadFile()"></select>
    <textarea id="editor"></textarea>
    <button onclick="saveFile()">Save</button>
  </section>

  <section id="settings">
    <h2>Configuration</h2>
    <label>Telegram Tokens (comma separated)</label>
    <input id="telegram_tokens" type="text">
    <label>Telegram Chat IDs (comma separated)</label>
    <input id="telegram_chat_ids" type="text">
    <label>SMTP Host</label>
    <input id="smtp_host" type="text">
    <label>SMTP Port</label>
    <input id="smtp_port" type="number">
    <label>SMTP User</label>
    <input id="smtp_user" type="text">
    <label>SMTP Password</label>
    <input id="smtp_pass" type="password">
    <button onclick="saveSettings()">Save Settings</button>
  </section>
</main>

<footer>
  <p>&copy; Admin Web App</p>
</footer>

<script>
let activeTab = 'dashboard';
function showTab(id){
  document.querySelectorAll('section').forEach(s=>s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  document.querySelectorAll('nav button').forEach(b=>b.classList.remove('active'));
  document.querySelector(`nav button[onclick*="${id}"]`).classList.add('active');
  activeTab = id;
}

function logout(){
  alert('Logout pressed'); 
  // implement session clearing in backend
}

// Dummy file tree
const files = ['index.php','config/config.php','assets/css/style.css'];
function refreshTree(){
  const tree = document.getElementById('filetree');
  tree.innerHTML='';
  files.forEach(f=>{ let d=document.createElement('div'); d.textContent=f; tree.appendChild(d); });
  const sel = document.getElementById('selectfile');
  sel.innerHTML=''; files.forEach(f=>{ let opt=document.createElement('option'); opt.value=f; opt.text=f; sel.appendChild(opt); });
}
function createFile(){
  const name=document.getElementById('newfile').value;
  if(!name) return alert('Enter file/folder name');
  files.push(name);
  refreshTree();
}
function loadFile(){
  const f=document.getElementById('selectfile').value;
  document.getElementById('editor').value='// Loaded content of '+f;
}
function saveFile(){
  const f=document.getElementById('selectfile').value;
  const val=document.getElementById('editor').value;
  alert('Saved '+f);
}
function saveSettings(){
  const tokens=document.getElementById('telegram_tokens').value;
  const chatids=document.getElementById('telegram_chat_ids').value;
  const host=document.getElementById('smtp_host').value;
  const port=document.getElementById('smtp_port').value;
  const user=document.getElementById('smtp_user').value;
  const pass=document.getElementById('smtp_pass').value;
  alert('Settings saved (mock)');
}

// Initialize
refreshTree();
</script>
</body>
</html>