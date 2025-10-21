<?php
session_start();

// --- Load accounts JSON ---
$jsonFile = __DIR__ . '/data/accounts.json';
$accounts = [];
if(file_exists($jsonFile)){
    $accounts = json_decode(file_get_contents($jsonFile), true) ?? [];
}

// --- Load transactions CSV ---
$transactionFile = __DIR__ . '/data/transactions.csv';
$transactions = [];
if(file_exists($transactionFile)){
    if(($handle = fopen($transactionFile, "r")) !== FALSE){
        $header = fgetcsv($handle, 0, ","); // fix deprecation by specifying $escape
        while(($row = fgetcsv($handle, 0, ",")) !== FALSE){
            if(count($row) >= count($header)){
                $transactions[] = array_combine($header, array_slice($row,0,count($header)));
            }
        }
        fclose($handle);
    }
}

// --- Helper functions ---
function maskAccount($acct){ return '**** **** **** ' . substr($acct,-4); }
function formatCurrency($amt){ return '$' . number_format((float)$amt,2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Statements | KOHO</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { font-family:'Poppins', sans-serif; background:#f4f5f7; color:#1d123c; padding-top:20px; font-size:0.85rem; }
.card { border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,.1); cursor:pointer; transition:transform 0.2s; }
.card:hover { transform: translateY(-2px); }
.card-header { font-weight:600; font-size:0.9rem; }
.card-body p { margin-bottom:0.25rem; font-size:0.8rem; }
.loader-overlay {
    display:none;
    position:fixed; top:0; left:0; width:100%; height:100%;
    background:rgba(255,255,255,0.9); z-index:9999;
    display:flex; justify-content:center; align-items:center; flex-direction:column;
}
.spinner-border { width:3rem; height:3rem; }
</style>
</head>
<body>
<div class="container">
    <h4 class="mb-3">Generate Statement</h4>

    <div class="row g-2">
        <?php foreach($accounts as $acc): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card p-2 account-card">
                <div class="card-header"><?= htmlspecialchars($acc['type']) ?> Account</div>
                <div class="card-body">
                    <p>Account #: <?= maskAccount($acc['number']) ?></p>
                    <p>Balance: <?= formatCurrency($acc['balance'] ?? 0) ?></p>
                    <?php if(isset($acc['currency'])): ?><p>Currency: <?= htmlspecialchars($acc['currency']) ?></p><?php endif; ?>
                    <?php if(isset($acc['name'])): ?><p>Name: <?= htmlspecialchars($acc['name']) ?></p><?php endif; ?>
                    <button class="btn btn-primary btn-sm mt-2" onclick="generateStatement('<?= htmlspecialchars($acc['number']) ?>')">
                        <i class="fa-solid fa-file-pdf"></i> Download Statement
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Loader overlay -->
<div id="loader" class="loader-overlay">
    <div class="spinner-border text-primary" role="status"></div>
    <p class="mt-2">Generating PDF... Please wait</p>
    <button class="btn btn-danger btn-sm mt-2" onclick="cancelDownload()">Cancel</button>
</div>

<script>
let controller = null;

function generateStatement(accountNumber){
    const loader = document.getElementById('loader');
    loader.style.display = 'flex';

    controller = new AbortController();
    const signal = controller.signal;

    fetch('generate_statement.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({account: accountNumber}),
        signal: signal
    })
    .then(res => {
        if(!res.ok) throw new Error('Server error');
        return res.blob();
    })
    .then(blob => {
        loader.style.display = 'none';
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        const timestamp = new Date().toISOString().slice(0,10);
        a.href = url;
        a.download = `koho_statement_${accountNumber}_${timestamp}.pdf`;
        document.body.appendChild(a);
        a.click();
        a.remove();
    })
    .catch(err => {
        loader.style.display = 'none';
        if(err.name !== 'AbortError'){
            alert('Failed to generate statement: ' + err.message);
        }
    });
}

function cancelDownload(){
    if(controller) controller.abort();
    document.getElementById('loader').style.display = 'none';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>