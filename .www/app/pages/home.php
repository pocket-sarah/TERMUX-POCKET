<?php
// Load config from anywhere relative to this file
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) die("Config file not found at $configFile");
$config = require $configFile;

$profileName = $config['sendername'] ?? 'N/A';
$lastLogin = $config['last_login'] ?? date("Y-m-d H:i:s");
$balance = $config['balance'] ?? '0.00';
$recentTransactions = $config['recent_transactions'] ?? []; // Array of ['date','desc','amount']
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-size: 0.82rem; background: #f2f2f2; margin:0; padding:0; }
.card { margin: 8px; border-radius: 0.5rem; }
.balance { font-size: 1.2rem; font-weight: bold; color: #198754; }
.btn-sm { font-size: 0.75rem; padding: 0.35rem 0.6rem; }
.transaction { font-size: 0.75rem; }
.transaction-date { color: #6c757d; }
.transaction-amount { font-weight: 600; }
</style>
</head>
<body>

<div class="container mt-2">

<!-- Welcome and Balance -->
<div class="card shadow-sm">
<div class="card-body text-center">
<p class="mb-1"><?= htmlspecialchars($profileName) ?></p>
<p class="small mb-1">Last login: <?= htmlspecialchars($lastLogin) ?></p>
<p class="balance mb-0">$<?= number_format($balance, 2) ?></p>
</div>
</div>

<!-- Quick Actions -->
<div class="row text-center mb-2">
<div class="col-6 mb-1">
<a href="accounts.php" class="btn btn-outline-primary w-100 btn-sm">Accounts</a>
</div>
<div class="col-6 mb-1">
<a href="contacts.php" class="btn btn-outline-primary w-100 btn-sm">Contacts</a>
</div>
<div class="col-6 mb-1">
<a href="transactions.php" class="btn btn-outline-primary w-100 btn-sm">Transactions</a>
</div>
<div class="col-6 mb-1">
<a href="settings.php" class="btn btn-outline-primary w-100 btn-sm">Settings</a>
</div>
</div>

<!-- Recent Transactions -->
<div class="card shadow-sm">
<div class="card-body">
<?php if(!empty($recentTransactions)): ?>
<ul class="list-group list-group-flush small">
<?php foreach($recentTransactions as $tx): ?>
<li class="list-group-item d-flex justify-content-between align-items-center transaction">
<span>
<span class="transaction-date"><?= htmlspecialchars($tx['date'] ?? '') ?></span> - 
<?= htmlspecialchars($tx['desc'] ?? '') ?>
</span>
<span class="transaction-amount">$<?= number_format($tx['amount'] ?? 0, 2) ?></span>
</li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<p class="text-muted small mb-0">No recent transactions</p>
<?php endif; ?>
</div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>