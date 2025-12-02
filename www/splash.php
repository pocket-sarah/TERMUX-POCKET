<?php
// splash.php - Dynamic landing page for Render PHP Panel

// Get user key if provided
$key = $_GET['key'] ?? '';

// Basic HTML template
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Koho Panel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f8;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0px 6px 18px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        a.button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            transition: 0.2s;
        }
        a.button:hover {
            background: #0056b3;
        }
        p {
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to Koho Panel</h1>
        <p>This is your Render-hosted PHP panel.</p>

        <?php if(!empty($key)): ?>
            <p>Your admin key: <strong><?= htmlspecialchars($key) ?></strong></p>
            <a class="button" href="admin.php?key=<?= urlencode($key) ?>">Open Admin Panel</a>
        <?php else: ?>
            <p>No admin key detected. Log in via Telegram to get access.</p>
        <?php endif; ?>
    </div>
</body>
</html>