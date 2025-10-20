<?php
// ---------- Find Config Dynamically ----------
function find_config($startDir = __DIR__, $maxDepth = 6) {
    $dir = realpath($startDir);
    $depth = 0;
    while ($dir && $depth < $maxDepth) {
        $cfg = $dir . '/.www/config/config.php';
        if (file_exists($cfg)) return $cfg;
        $dir = dirname($dir);
        $depth++;
    }
    return false;
}

$configFile = find_config();
if (!$configFile) die("Config file not found (.www/config/config.php)");
$config = require $configFile;

// ---------- Load Profile & Accounts ----------
$profileName = $config['sendername'] ?? 'N/A';
$lastLogin = $config['last_login'] ?? date("Y-m-d H:i:s");
$accounts = $config['accounts'] ?? [];

// Remove placeholder accounts and sort alphabetically
$accounts = array_filter($accounts, fn($a)=>($a['name'] ?? '') !== 'One-time Contact');
usort($accounts, fn($a,$b)=>strcasecmp($a['name'],$b['name']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bank Home</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#f5f5f5; font-family:'Segoe UI',sans-serif; font-size:0.8rem; }
.header { background:#004b87; color:#fff; text-align:center; font-weight:600; padding:12px 0; font-size:1rem; }
.card { border-radius:1rem; margin-bottom:10px; cursor:pointer; }
.card-header { padding:.75rem 1rem; }
.account-balance { font-weight:700; font-size:0.95rem; color:#0b6623; }
.transaction-list .list-group-item { font-size:0.75rem; padding:.4rem .75rem; }
.transaction-date { color:#6c757d; font-size:0.7rem; }
.transaction-amount { font-weight:600; }
.quick-actions .btn { font-size:0.75rem; padding:0.35rem; margin-bottom:5px; }
</style>
</head>
<body>

<div class="header">Welcome, <?= htmlspecialchars($profileName) ?></div>
<div class="container mt-2">

<!-- Accounts Overview -->
<div class="mb-2"><strong>Accounts Overview</strong></div>

<?php if(!empty($accounts)): ?>
<?php foreach($accounts as $idx => $acc): ?>
<div class="card shadow-sm" data-bs-toggle="collapse" data-bs-target="#acc-<?= $idx ?>" aria-expanded="false" aria-controls="acc-<?= $idx ?>">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <strong><?= htmlspecialchars($acc['name'] ?? 'Account') ?></strong><br>
      <small><?= htmlspecialchars($acc['type'] ?? '') ?></small>
    </div>
    <div class="account-balance">
      <?= htmlspecialchars($acc['currency'] ?? '$') ?><?= number_format($acc['balance'] ?? 0,2) ?>
    </div>
  </div>
  <?php if(!empty($acc['transactions'])): ?>
  <div class="collapse" id="acc-<?= $idx ?>">
    <ul class="list-group list-group-flush transaction-list">
      <?php foreach($acc['transactions'] as $tx): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center p-1">
        <div>
          <span class="transaction-date"><?= htmlspecialchars($tx['date'] ?? '') ?></span><br>
          <?= htmlspecialchars($tx['desc'] ?? '') ?>
        </div>
        <div class="transaction-amount"><?= htmlspecialchars($tx['currency'] ?? '$') ?><?= number_format($tx['amount'] ?? 0,2) ?></div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php else: ?>
<p class="text-muted mb-0">No accounts available.</p>
<?php endif; ?>

<!-- Quick Actions -->
<div class="row quick-actions mt-3 mb-2 g-1">
<div class="col-6"><a href="transfer.php" class="btn btn-outline-primary w-100">Transfer</a></div>
<div class="col-6"><a href="contacts.php" class="btn btn-outline-primary w-100">Contacts</a></div>
<div class="col-6"><a href="statements.php" class="btn btn-outline-primary w-100">Statements</a></div>
<div class="col-6"><a href="settings.php" class="btn btn-outline-primary w-100">Settings</a></div>
</div>

<div class="text-center small text-muted mt-2 mb-2">&copy; <?= date("Y") ?> Your Bank</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>