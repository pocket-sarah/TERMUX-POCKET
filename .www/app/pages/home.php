<?php
session_start();

// --- Load accounts JSON ---
$accountsFile = __DIR__ . '/data/accounts.json';
$accounts = [];
if(file_exists($accountsFile)){
    $accounts = json_decode(file_get_contents($accountsFile), true) ?? [];
}

// --- Load transactions CSV ---
$transactionsFile = __DIR__ . '/data/transactions.csv';
$recentTransactions = [];
if(file_exists($transactionsFile)){
    if(($handle = fopen($transactionsFile, 'r')) !== FALSE){
        $header = fgetcsv($handle, 0, ',', '"', '\\'); // specify escape char
        while(($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE){
            if(count($row) >= count($header)){
                $recentTransactions[] = array_combine($header, array_slice($row,0,count($header)));
            }
        }
        fclose($handle);
    }
}
$recentTransactions = array_reverse($recentTransactions); // newest first

// --- Helpers ---
function maskAccount($acct){ return '**** **** **** ' . substr($acct,-4); }
function formatCurrency($amt){ return '$' . number_format((float)$amt,2); }

// --- Device detection ---
$userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$isApple = preg_match('/iphone|ipad|ipod/', $userAgent);
$isAndroid = preg_match('/android/', $userAgent);

// --- Insights ---
$insights = [
    "You spent $250.00 at groceries this week. Consider setting a budget.",
    "Your savings account earned $12.50 in interest last month.",
    "Upcoming bill: $120.00 due in 3 days."
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Home | KOHO</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family:'Poppins', sans-serif; background:#f4f5f7; color:#1d123c; margin:0; padding:20px; }
h2,h3 { font-weight:600; margin-bottom:15px; }
.account-cards-wrapper { overflow-x:auto; display:flex; gap:15px; padding-bottom:10px; scroll-behavior:smooth; }
.account-card { min-width:250px; background:#1d123c; color:#fff; border-radius:12px; padding:20px; flex-shrink:0; box-shadow:0 4px 12px rgba(0,0,0,.2); cursor:pointer; transition: transform .2s; }
.account-card:hover { transform: translateY(-3px); box-shadow:0 8px 18px rgba(0,0,0,.25); }
.account-info span { display:block; font-size:14px; opacity:0.85; }
.account-info .type { font-weight:600; font-size:16px; }
.account-balance { font-size:22px; font-weight:700; margin-top:10px; text-align:center; }

.wallet-btn { margin-top:15px; display:flex; justify-content:center; gap:15px; }
.wallet-btn img { height:48px; cursor:pointer; transition: transform .2s; }
.wallet-btn img:hover { transform: scale(1.05); }

.transactions { margin-top:25px; }
.transactions table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; font-size:0.65rem; } /* smaller font */
.transactions th, .transactions td { padding:6px 8px; text-align:left; }
.transactions th { background:#f0f0f0; font-weight:600; }
.transactions tr { border-bottom:1px solid #eee; }
.transactions tr:last-child { border-bottom:none; }
.transactions td.amount { text-align:right; }
.transactions td.amount.negative { color:#ff3b3b; }
.transactions td.amount.positive { color:#2ecc71; }

.insights { margin-top:25px; background:#fff; border-radius:12px; padding:15px; box-shadow:0 4px 12px rgba(0,0,0,.1); font-size:0.75rem; }
.insights ul { list-style:none; padding-left:0; }
.insights ul li { margin-bottom:8px; display:flex; align-items:center; gap:8px; }
.insights ul li i { color:#c5e600; }

@media(max-width:600px){ .wallet-btn img{height:40px;} }
</style>
</head>
<body>

    <!-- Account Cards -->
    <div class="account-cards-wrapper">
        <?php foreach($accounts as $acc): ?>
            <div class="account-card" onclick="showAccountTransactions('<?= htmlspecialchars($acc['number'] ?? '') ?>')">
                <div class="account-info">
                    <span class="type"><?= htmlspecialchars($acc['type'] ?? 'Checking') ?></span>
                    <span><?= isset($acc['number']) ? maskAccount($acc['number']) : '' ?></span>
                    <?php if(!empty($acc['available'])): ?><span class="extra">Available: <?= formatCurrency($acc['available']) ?></span><?php endif; ?>
                </div>
                <div class="account-balance"><?= formatCurrency($acc['balance'] ?? 0) ?></div>

                <!-- Wallet Buttons -->
                <div class="wallet-btn">
                    <?php if($isApple): ?>
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/3/30/Add_to_Apple_Wallet_badge.svg/1200px-Add_to_Apple_Wallet_badge.svg.png" alt="Add to Apple Wallet">
                    <?php elseif($isAndroid): ?>
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/bb/Add_to_Google_Wallet_badge.svg/1200px-Add_to_Google_Wallet_badge.svg.png" alt="Add to Google Wallet">
                    <?php else: ?>
                        <span class="text-light small">Wallet not supported</span>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

    <!-- Transactions Table -->
    <div class="transactions" id="transactions-container">
        <h3>Recent Transactions</h3>
        <p>Select an account to view its transactions.</p>
    </div>

    <!-- Insights -->
    <div class="insights">
        <h3>Insights & Notifications</h3>
        <ul>
            <?php foreach($insights as $insight): ?>
                <li><i class="fa-solid fa-lightbulb"></i><?= htmlspecialchars($insight) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<script>
const transactions = <?= json_encode($recentTransactions) ?>;

function showAccountTransactions(accountNumber){
    const container = document.getElementById('transactions-container');
    const filtered = transactions.filter(tx => tx.account_number === accountNumber || tx.account === accountNumber);
    if(filtered.length === 0){
        container.innerHTML = "<h3>Recent Transactions</h3><p>No transactions for this account.</p>";
        return;
    }
    let html = "<h3>Transactions for " + accountNumber.slice(-4) + "</h3><table><thead><tr><th>Date</th><th>Description</th><th>Amount</th></tr></thead><tbody>";
    filtered.forEach(tx => {
        const amount = parseFloat(tx.amount);
        html += "<tr><td>"+tx.date+"</td><td>"+tx.description+"</td><td class='amount "+(amount<0?"negative":"positive")+"'>"+(amount<0?"-":"+")+"$"+Math.abs(amount).toFixed(2)+"</td></tr>";
    });
    html += "</tbody></table>";
    container.innerHTML = html;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
