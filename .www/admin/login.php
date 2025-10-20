<?php
session_start();
$msg = '';

// Hardcoded admin credentials (first-time setup)
$users = [
    'admin' => password_hash('AdminPass123', PASSWORD_DEFAULT),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (isset($users[$username]) && password_verify($password, $users[$username])) {
        $_SESSION['username'] = $username;
        $_SESSION['first_login'] = true; // mark first login
        header('Location: dashboard.php');
        exit;
    }
    $msg = 'Invalid username or password';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login</title>
<style>
body{font-family:system-ui;background:#f5f5f5;color:#111;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}
form{background:#e0e0e0;padding:20px;border-radius:12px;display:flex;flex-direction:column;width:320px;box-shadow:0 4px 8px rgba(0,0,0,0.1)}
input{margin:8px 0;padding:10px;border-radius:8px;border:1px solid #ccc;background:#fff;color:#111}
button{padding:10px;border:none;border-radius:8px;background:#333;color:#fff;cursor:pointer;margin-top:10px}
button:hover{background:#555}
.msg{color:#d32f2f;margin:4px 0;}
h2{color:#333;margin-bottom:10px;text-align:center}
</style>
</head>
<body>
<form method="post">
<h2>Login</h2>
<?php if($msg):?><div class="msg"><?=htmlspecialchars($msg)?></div><?php endif;?>
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit">Login</button>
</form>
</body>
</html>