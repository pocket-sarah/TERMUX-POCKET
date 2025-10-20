<?php
session_start();

// HARDSET ADMIN CREDENTIALS (first login)
$users = ['admin'=>password_hash('AdminPass123', PASSWORD_DEFAULT)];
$msg='';

// LOGIN HANDLING
if (!isset($_SESSION['username'])) {
    if ($_SERVER['REQUEST_METHOD']==='POST'){
        $u=$_POST['username']??'';
        $p=$_POST['password']??'';
        if(isset($users[$u]) && password_verify($p,$users[$u])){
            $_SESSION['username']=$u;
            header('Location:index.php'); exit;
        } else $msg='Invalid credentials';
    }
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

// Load app config
$configFile = __DIR__.'/config/config.php';
$config = file_exists($configFile) ? require $configFile : [];

$www = __DIR__;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard Editor</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/index.css">
<style>
:root{--bg:#f7f7f7;--card:#e0e0e0;--accent:#333;--muted:#555;--ok:#4caf50}
body{margin:0;font-family:system-ui;background:var(--bg);color:#111}
header{display:flex;justify-content:space-between;align-items:center;padding:10px 16px;background:#ccc;color:#111}
header .logo{font-weight:bold;font-size:20px}
header .header-icons button{background:none;border:none;color:#111;margin-left:10px;font-size:18px;cursor:pointer}
main{padding:16px;max-width:960px;margin:0 auto}
iframe{width:100%;height:calc(100vh - 80px);border:none;background:#fff;box-shadow:0 0 12px rgba(0,0,0,0.08)}
.card{background:var(--card);padding:12px;border-radius:10px;margin-top:10px}
.btn{display:block;width:100%;padding:12px;margin:8px 0;border-radius:8px;border:0;background:var(--accent);color:#fff;font-size:16px;cursor:pointer}
.btn:hover{opacity:0.85}
#spinnerOverlay{display:none;position:fixed;inset:0;z-index:99999;justify-content:center;align-items:center;background:rgba(0,0,0,0.1)}
#spinner{width:56px;height:56px;border-radius:50%;border:6px solid rgba(0,0,0,0.1);border-top-color:var(--accent);animation:spin 1s linear infinite}
@keyframes spin{100%{transform:rotate(360deg)}}
</style>
</head>
<body>
<header>
    <div class="logo">ADMIN EDITOR</div>
    <div class="header-icons">
        <button onclick="reloadIframe()">Refresh</button>
        <a href="?logout=1"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</header>

<main>
    <iframe id="app-frame" src="pages/home.php"></iframe>
</main>

<div id="spinnerOverlay"><div id="spinner"></div></div>

<script>
// iframe reload with spinner
function reloadIframe(){
    const iframe = document.getElementById('app-frame');
    const overlay = document.getElementById('spinnerOverlay');
    overlay.style.display='flex';
    iframe.onload = ()=>{ setTimeout(()=>overlay.style.display='none',200); };
    const src = iframe.getAttribute('src').split('?')[0];
    iframe.setAttribute('src', src+'?t='+Date.now());
}

// live reload every 5s optional
// setInterval(reloadIframe,5000);
</script>
</body>
</html>