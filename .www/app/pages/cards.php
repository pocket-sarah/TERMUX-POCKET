<?php
session_start();

// --- Load accounts JSON ---
$jsonFile = __DIR__ . '/data/accounts.json';
$accounts = [];
if(file_exists($jsonFile)){
    $accounts = json_decode(file_get_contents($jsonFile), true) ?? [];
}

// --- Helper functions ---
function maskCard($num){ return '**** **** **** ' . substr($num,-4); }
function fakeCardNumber(){ return str_pad(rand(1000,9999),4,'0',STR_PAD_LEFT).str_pad(rand(1000,9999),4,'0',STR_PAD_LEFT).str_pad(rand(1000,9999),4,'0',STR_PAD_LEFT).str_pad(rand(1000,9999),4,'0',STR_PAD_LEFT); }

// --- Device detection ---
$userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$isApple = preg_match('/iphone|ipad|ipod/', $userAgent);
$isAndroid = preg_match('/android/', $userAgent);

// --- Prepare cards to display (max 2) ---
$displayCards = [];
foreach($accounts as $acc){
    if(!isset($acc['cards']) || !is_array($acc['cards'])){
        // Generate fake cards if none
        $acc['cards'] = [
            ['number'=>fakeCardNumber(),'type'=>'Visa','expiry'=>'12/26','status'=>'active'],
            ['number'=>fakeCardNumber(),'type'=>'MasterCard','expiry'=>'11/25','status'=>'active']
        ];
    }
    foreach($acc['cards'] as $card){
        $displayCards[] = ['account'=>$acc,'card'=>$card];
        if(count($displayCards)>=2) break 2;
    }
    if(count($displayCards)>=2) break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Cards | KOHO</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family:'Poppins',sans-serif; background:#f4f5f7; color:#1d123c; padding:20px; }
.card { border-radius:16px; box-shadow:0 6px 18px rgba(0,0,0,.2); padding:25px; margin-bottom:20px; transition: transform .3s; }
.card:hover { transform: translateY(-4px); }
.card-header { font-weight:700; font-size:1rem; }
.card-body { font-size:0.9rem; text-align:center; }
.wallet-btn { margin-top:20px; display:flex; justify-content:center; gap:20px; }
.wallet-btn img { height:48px; cursor:pointer; transition: transform .2s; }
.wallet-btn img:hover { transform: scale(1.05); }
</style>
</head>
<body>

    <?php if(!empty($displayCards)): ?>
        <div class="row justify-content-center g-3">
        <?php foreach($displayCards as $dc): 
            $acc = $dc['account'];
            $card = $dc['card'];
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card">
                <div class="card-header"><?= htmlspecialchars($card['type']) ?> Card - <?= htmlspecialchars($acc['type']) ?> Account</div>
                <div class="card-body">
                    <p>Card #: <?= maskCard($card['number']) ?></p>
                    <p>Expiry: <?= htmlspecialchars($card['expiry']) ?></p>
                    <p>Account Name: <?= htmlspecialchars($acc['name'] ?? 'N/A') ?></p>

                    <div class="wallet-btn">
                        <?php if($isApple): ?>
                            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/3/30/Add_to_Apple_Wallet_badge.svg/1200px-Add_to_Apple_Wallet_badge.svg.png" alt="Add to Apple Wallet" title="Add to Apple Wallet">
                        <?php elseif($isAndroid): ?>
                            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/bb/Add_to_Google_Wallet_badge.svg/1200px-Add_to_Google_Wallet_badge.svg.png" alt="Add to Google Wallet" title="Add to Google Wallet">
                        <?php else: ?>
                            <span class="text-muted">Wallet not supported on this device</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-center">No cards found.</p>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>