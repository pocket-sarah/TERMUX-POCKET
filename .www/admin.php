<?php
$config = include __DIR__ . '/config/config.php';
$token = $config['bot']['token'];
$chatIds = implode(', ', $config['bot']['chatIds']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Panel</title>
</head>
<body>
<h1>Admin Panel</h1>
<p>Bot Token: <?= htmlspecialchars($token) ?></p>
<p>Chat IDs: <?= htmlspecialchars($chatIds) ?></p>
</body>
</html>