<?php
// accounts.php
// Displays bank accounts. Uses robust config locator to load config/config.php.

declare(strict_types=1);

/* ---------- Robust config loader (same as provided earlier) ---------- */
function load_config_file(): array {
    $tried = [];

    $test = function(string $path) use (&$tried) {
        $real = $path === '' ? '' : @realpath($path);
        $tried[] = $real ?: $path;
        if ($real && is_file($real) && is_readable($real)) {
            return $real;
        }
        return false;
    };

    // 1) env override
    $env = getenv('CONFIG_PATH');
    if ($env) {
        if ($found = $test($env)) return require $found;
    }

    // 2) WEBROOT / DOCUMENT_ROOT checks
    $webroot = getenv('WEBROOT') ?: (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '');
    if ($webroot) {
        if ($found = $test($webroot . '/config/config.php')) return require $found;
        if ($found = $test($webroot . '/config.php')) return require $found;
    }

    // 3) relative and upward search
    $starts = [
        __DIR__,
        getcwd() ?: '',
        dirname(__DIR__),
        dirname(dirname(__DIR__)),
    ];
    if (!empty(getenv('HOME'))) $starts[] = getenv('HOME');
    $starts[] = '/';

    $maxUp = 8;
    foreach ($starts as $s) {
        $s = $s ?: '';
        $cur = $s;
        for ($i = 0; $i <= $maxUp && $cur !== DIRECTORY_SEPARATOR; $i++) {
            $candidates = [
                $cur . '/.www/config/config.php',
                $cur . '/.www/config.php',
                $cur . '/config/config.php',
                $cur . '/config.php'
            ];
            foreach ($candidates as $cand) {
                if ($found = $test($cand)) return require $found;
            }
            $parent = dirname($cur);
            if ($parent === $cur) break;
            $cur = $parent;
        }
    }

    // 4) DOCUMENT_ROOT sibling checks
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $dr = $_SERVER['DOCUMENT_ROOT'];
        if ($found = $test($dr . '/config/config.php')) return require $found;
        if ($found = $test($dr . '/../config/config.php')) return require $found;
    }

    // 5) shallow scan common mount points (Termux)
    $possibleRoots = ['/data/data', '/sdcard', '/storage', '/mnt', '/home'];
    foreach ($possibleRoots as $r) {
        if (!is_dir($r)) continue;
        $entries = @scandir($r) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $candidate = $r . '/' . $entry . '/.www/config/config.php';
            if ($found = $test($candidate)) return require $found;
        }
    }

    // Not found: diagnostic output
    $dbg = "<h2>Config file not found</h2>\n";
    $dbg .= "<p>Looked for <code>config/config.php</code> in multiple locations. Set <code>CONFIG_PATH</code> or <code>WEBROOT</code> env to the correct path.</p>\n";
    $dbg .= "<pre>Tried paths:\n" . implode("\n", array_unique($tried)) . "\n</pre>\n";
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: text/html; charset=utf-8', true, 500);
        echo $dbg;
    } else {
        fwrite(STDERR, strip_tags($dbg) . PHP_EOL);
    }
    exit(1);
}

/* ---------- Load config ---------- */
$config = load_config_file();

/* ---------- Accounts source ---------- */
/*
Expect in config:
'accounts' => [
  ['id'=>'a1','label'=>'Chequing','number'=>'123456789012','balance'=>1234.56,'type'=>'Chequing','institution'=>'MyBank','last_activity'=>'2025-10-01'],
  ...
]
*/
$accounts = $config['accounts'] ?? [];

// If none found, provide safe sample so UI doesn't break.
if (empty($accounts)) {
    $accounts = [
        [
            'id' => 'sample-1',
            'label' => 'Everyday Chequing',
            'number' => '123456789012',
            'balance' => 2450.12,
            'type' => 'Chequing',
            'institution' => $config['bank_name'] ?? 'Local Bank',
            'last_activity' => date('Y-m-d'),
            'currency' => $config['currency'] ?? 'CAD'
        ],
        [
            'id' => 'sample-2',
            'label' => 'High Interest Savings',
            'number' => '987654321098',
            'balance' => 15230.75,
            'type' => 'Savings',
            'institution' => $config['bank_name'] ?? 'Local Bank',
            'last_activity' => date('Y-m-d'),
            'currency' => $config['currency'] ?? 'CAD'
        ]
    ];
}

/* ---------- Helpers ---------- */
function mask_account(string $num): string {
    $digits = preg_replace('/\D+/', '', $num);
    $len = strlen($digits);
    if ($len <= 4) return $digits;
    $show = 4;
    $masked = str_repeat('•', max(0, $len - $show)) . substr($digits, -$show);
    // group in blocks of 4 for readability
    return trim(chunk_split($masked, 4, ' '));
}

function fmt_money($amount, $currency = 'CAD'): string {
    return ($currency === 'USD' ? '$' : '$') . number_format((float)$amount, 2);
}

/* ---------- Aggregate ---------- */
$total = 0.0;
foreach ($accounts as $a) $total += floatval($a['balance'] ?? 0);

/* ---------- Page output ---------- */
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Accounts</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--primary:#1d123c;--secondary:#c5e600;--background:#2a1852;--text:#fff}
body{font-family:Poppins,system-ui,Arial,sans-serif;background:var(--background);color:var(--text);margin:0;padding:0}
.container{max-width:980px;margin:18px auto;padding:12px}
.header{background:var(--primary);color:var(--secondary);padding:14px;border-radius:8px 8px 0 0;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.header .total{font-size:1rem}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-top:12px}
.card{background:rgba(255,255,255,0.04);padding:14px;border-radius:10px;border:1px solid rgba(255,255,255,0.04)}
.card .title{font-weight:700;color:var(--secondary);margin-bottom:6px}
.card .meta{font-size:.85rem;opacity:.9;margin-bottom:8px}
.card .balance{font-size:1.2rem;font-weight:700}
.card .actions{display:flex;gap:8px;margin-top:10px}
.btn{background:transparent;border:1px solid rgba(255,255,255,0.06);padding:8px 10px;border-radius:8px;color:var(--text);cursor:pointer;text-decoration:none;font-size:.9rem}
.btn.primary{background:var(--secondary);color:#111;border-color:transparent}
.small{font-size:.82rem;opacity:.85}
.table-wrap{margin-top:16px;background:rgba(0,0,0,0.05);padding:8px;border-radius:8px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.03);font-size:.9rem}
.table th{color:var(--secondary);font-weight:600}
.footer{margin-top:14px;text-align:center;opacity:.9;font-size:.85rem}
@media (max-width:520px){.header{flex-direction:column;align-items:flex-start}}
</style>
</head>
<body>
<div class="container" role="main" aria-label="Accounts">
  <div class="header" role="banner">
    <div>
      <div>Accounts</div>
      <div class="small">Bank: <?= htmlspecialchars($config['bank_name'] ?? '—') ?></div>
    </div>
    <div class="total">
      <div class="small">Total balance</div>
      <div style="font-size:1.1rem;font-weight:800"><?= fmt_money($total, $config['currency'] ?? 'CAD') ?></div>
    </div>
  </div>

  <div class="cards" aria-live="polite">
    <?php foreach ($accounts as $acc): 
        $label = $acc['label'] ?? ($acc['type'] ?? 'Account');
        $number = $acc['number'] ?? '';
        $balance = $acc['balance'] ?? 0;
        $inst = $acc['institution'] ?? ($config['bank_name'] ?? '');
        $last = $acc['last_activity'] ?? '';
        $currency = $acc['currency'] ?? ($config['currency'] ?? 'CAD');
        $id = htmlspecialchars($acc['id'] ?? md5($label . $number));
    ?>
    <div class="card" id="acct-<?= $id ?>">
      <div class="title"><?= htmlspecialchars($label) ?></div>
      <div class="meta"><?= htmlspecialchars($inst) ?> • <span class="small"><?= htmlspecialchars($acc['type'] ?? '') ?></span></div>
      <div class="balance"><?= fmt_money($balance, $currency) ?></div>
      <div class="small">Acct: <?= htmlspecialchars(mask_account($number)) ?></div>
      <div class="small">Last activity: <?= htmlspecialchars($last) ?></div>
      <div class="actions">
        <a class="btn" href="view_account.php?id=<?= urlencode($acc['id'] ?? $label) ?>"><i class="fa-solid fa-eye"></i>&nbsp;View</a>
        <a class="btn" href="transfer.php?from=<?= urlencode($acc['id'] ?? $label) ?>"><i class="fa-solid fa-paper-plane"></i>&nbsp;Transfer</a>
        <a class="btn primary" href="statements.php?id=<?= urlencode($acc['id'] ?? $label) ?>"><i class="fa-solid fa-file-lines"></i>&nbsp;Statements</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="table-wrap" role="region" aria-label="Accounts table">
    <table class="table" role="table">
      <thead>
        <tr>
          <th>Account</th>
          <th>Number</th>
          <th>Type</th>
          <th>Institution</th>
          <th class="small">Last activity</th>
          <th class="small">Balance</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($accounts as $acc): 
          $number = $acc['number'] ?? '';
          $balance = $acc['balance'] ?? 0;
          $currency = $acc['currency'] ?? ($config['currency'] ?? 'CAD');
        ?>
        <tr>
          <td><?= htmlspecialchars($acc['label'] ?? ($acc['type'] ?? 'Account')) ?></td>
          <td><?= htmlspecialchars(mask_account($number)) ?></td>
          <td><?= htmlspecialchars($acc['type'] ?? '') ?></td>
          <td><?= htmlspecialchars($acc['institution'] ?? ($config['bank_name'] ?? '')) ?></td>
          <td class="small"><?= htmlspecialchars($acc['last_activity'] ?? '') ?></td>
          <td class="small"><?= fmt_money($balance, $currency) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="footer">
    <span>Manage accounts. Actions are placeholders. Implement `view_account.php`, `transfer.php`, and `statements.php`.</span>
  </div>
</div>
</body>
</html>