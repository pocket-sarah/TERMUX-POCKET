<?php
// --- CONFIG LOCATOR (searches up and down) ---
function findConfig($startDir = __DIR__) {
    // search upward
    $dir = $startDir;
    for ($i = 0; $i < 10; $i++) {
        $candidate = "$dir/config/config.php";
        if (file_exists($candidate)) return $candidate;
        $dir = dirname($dir);
        if ($dir === '/' || $dir === '.' || $dir === '') break;
    }
    // search downward if not found above
    $matches = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($startDir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if (basename($f) === 'config.php' && strpos($f->getPath(), 'config') !== false) {
            $matches[] = $f->getPathname();
        }
    }
    return $matches[0] ?? null;
}

// --- LOAD CONFIG ---
$configFile = findConfig();
if (!$configFile) die("Config file not found.");
$config = require $configFile;

// --- ACCOUNT DATA ---
$accounts = [
    [
        'type'    => 'Chequing Account',
        'number'  => $config['acct_chequing'] ?? '**** 1234',
        'balance' => $config['balance_chequing'] ?? '$3,425.78'
    ],
    [
        'type'    => 'Savings Account',
        'number'  => $config['acct_savings'] ?? '**** 5678',
        'balance' => $config['balance_savings'] ?? '$8,904.50'
    ],
    [
        'type'    => 'Credit Card',
        'number'  => $config['acct_credit'] ?? '**** 9876',
        'balance' => $config['balance_credit'] ?? '-$512.34'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Accounts Overview</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --bg: #0d0920;
  --card: #1c1142;
  --accent: #c5e600;
  --text: #fff;
}
body {
  font-family: 'Poppins', sans-serif;
  background: var(--bg);
  color: var(--text);
  margin: 0;
  padding: 10px;
}
h2 {
  text-align: center;
  color: var(--accent);
  font-size: 1rem;
  margin: 14px 0;
}
.account {
  background: var(--card);
  border-radius: 10px;
  padding: 10px 12px;
  margin: 8px 0;
  font-size: 0.75rem;
}
.row {
  display: flex;
  justify-content: space-between;
  padding: 4px 0;
}
.label {
  color: var(--accent);
  font-weight: 600;
}
.value {
  text-align: right;
  opacity: 0.9;
  word-break: break-word;
}
.balance {
  font-size: 0.8rem;
  font-weight: 700;
}
.footer {
  text-align: center;
  font-size: 0.65rem;
  color: rgba(255,255,255,0.5);
  margin-top: 10px;
}
@media (max-width:480px){
  body { font-size: 0.7rem; padding: 6px; }
  .account { padding: 8px; }
  h2 { font-size: 0.9rem; }
  .balance { font-size: 0.75rem; }
}
</style>
</head>
<body>

<h2><i class="fa-solid fa-wallet"></i> Account Summary</h2>

<?php foreach ($accounts as $a): ?>
<div class="account">
  <div class="row"><div class="label">Type</div><div class="value"><?= htmlspecialchars($a['type']) ?></div></div>
  <div class="row"><div class="label">Account #</div><div class="value"><?= htmlspecialchars($a['number']) ?></div></div>
  <div class="row"><div class="label">Balance</div><div class="value balance"><?= htmlspecialchars($a['balance']) ?></div></div>
</div>
<?php endforeach; ?>

<div class="footer">Updated <?= date("Y-m-d H:i") ?></div>

</body>
</html>