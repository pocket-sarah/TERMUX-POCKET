<?php
// admin.php - User-specific admin panel

session_start();

// Define some dummy user data storage
// In production, replace with database
$usersFile = __DIR__ . '/.data/users.json';
if(!file_exists($usersFile)) file_put_contents($usersFile, json_encode([]));

$users = json_decode(file_get_contents($usersFile), true);

// Get key from GET or session
$key = $_GET['key'] ?? $_SESSION['user_key'] ?? '';
if(!$key) {
    header("Location: splash.php");
    exit;
}

// Save key in session
$_SESSION['user_key'] = $key;

// Register user if not exists
if(!isset($users[$key])) {
    $users[$key] = [
        'created_at' => time(),
        'actions' => []
    ];
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
}

// Handle actions
$action = $_GET['action'] ?? '';
if($action) {
    $users[$key]['actions'][] = [
        'action' => $action,
        'time' => time()
    ];
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
}

// Fetch user info
$user = $users[$key];
$lastAction = end($user['actions']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef2f7;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .panel {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        h1 { color: #333; margin-bottom: 20px; }
        a.button {
            display: inline-block;
            margin: 10px 5px;
            padding: 12px 20px;
            background: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            transition: 0.2s;
        }
        a.button:hover { background: #0056b3; }
        p { color: #555; }
        .actions { margin-top: 20px; }
        .actions ul { list-style: none; padding: 0; }
        .actions li { margin-bottom: 5px; font-size: 14px; color: #444; }
    </style>
</head>
<body>
<div class="panel">
    <h1>Your Admin Panel</h1>
    <p>Welcome, <strong><?= htmlspecialchars($key) ?></strong></p>

    <div class="actions">
        <h3>Actions</h3>
        <ul>
            <?php foreach($user['actions'] as $a): ?>
                <li><?= date('Y-m-d H:i:s', $a['time']) ?> - <?= htmlspecialchars($a['action']) ?></li>
            <?php endforeach; ?>
            <?php if(empty($user['actions'])): ?>
                <li>No actions yet.</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="buttons">
        <a class="button" href="?action=create_server">Create New Webserver</a>
        <a class="button" href="?action=view_logs">View Logs</a>
        <a class="button" href="?action=logout">Logout</a>
    </div>

    <?php
    if($action === 'logout') {
        session_destroy();
        header("Location: splash.php");
        exit;
    }
    ?>
</div>
</body>
</html>