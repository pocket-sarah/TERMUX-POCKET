<?php
session_start();

// --- Load payees ---
$payeesFile = __DIR__ . '/data/payees.json';
$payees = file_exists($payeesFile) ? json_decode(file_get_contents($payeesFile), true) : [];

// --- Recent payments ---
$paymentsFile = __DIR__ . '/data/payments.json';
$recentPayments = file_exists($paymentsFile) ? json_decode(file_get_contents($paymentsFile), true) : [];

// --- Transactions CSV ---
$transactionFile = __DIR__ . '/data/transactions.csv';
if(!file_exists($transactionFile)){
    $headers = ['date','description','type','amount','account_number','payee'];
    $f = fopen($transactionFile,'w');
    fputcsv($f,$headers);
    fclose($f);
}

// --- Log transaction ---
function logTransaction($payee, $amount, $account='0000-0000'){
    global $transactionFile;
    $row = [
        'date'=>date('Y-m-d H:i:s'),
        'description'=>"Bill payment to $payee",
        'type'=>'debit',
        'amount'=>$amount,
        'account_number'=>$account,
        'payee'=>$payee
    ];
    $f=fopen($transactionFile,'a');
    fputcsv($f,$row);
    fclose($f);
}

// --- AJAX handler ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])){
    header('Content-Type: application/json');
    $action=$_POST['action'];

    if($action==='add_payee'){
        $name=trim($_POST['payee'] ?? '');
        if($name && !in_array($name,$payees)){
            $payees[]=$name;
            file_put_contents($payeesFile,json_encode($payees));
        }
        echo json_encode(['ok'=>true,'payees'=>$payees]);
        exit;
    }

    if($action==='make_payment'){
        $payee=trim($_POST['payee'] ?? '');
        $amount=floatval($_POST['amount'] ?? 0);
        if($payee && $amount>0){
            $payment=['payee'=>$payee,'amount'=>$amount,'date'=>date('Y-m-d H:i:s')];
            $recentPayments[]=$payment;
            file_put_contents($paymentsFile,json_encode($recentPayments));
            logTransaction($payee,$amount);
            echo json_encode(['ok'=>true,'payment'=>$payment]);
        } else {
            echo json_encode(['ok'=>false,'error'=>'Invalid input']);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pay Bills | KOHO</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
  --primary:#1d123c;
  --secondary:#c5e600;
  --background:linear-gradient(135deg,#1d123c,#2a1852);
  --icon:#fff;
  --footer-bg:#2a1852;
  --transition:0.3s;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Poppins',sans-serif;background:var(--background);color:var(--icon);min-height:100vh;display:flex;flex-direction:column;}
.container{flex:1;padding:20px;}
h2,h3{margin-bottom:15px;}
.card{background:#2a1852;border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 4px 12px rgba(0,0,0,.2);transition:transform .2s;}
.card:hover{transform:translateY(-3px);}
input, select{border-radius:8px;border:1px solid #ccc;padding:8px;width:100%;margin-bottom:10px;}
button{border-radius:8px;transition:all .2s;}
button:hover{opacity:.85;}
.payment-list{margin-top:20px;}
.payment-item{padding:10px;background:#1d123c;color:#fff;border-radius:8px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 6px rgba(0,0,0,.3);}
.payment-item .amount{font-weight:600;}
</style>
</head>
<body>
<div class="container">
    <h2>Pay a Bill</h2>

    <div class="card">
        <label for="payeeSelect">Select Payee</label>
        <div class="input-group mb-2">
            <select id="payeeSelect" class="form-select">
                <?php foreach($payees as $p): ?>
                    <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-light" type="button" onclick="addPayee()"><i class="fa-solid fa-plus"></i></button>
        </div>

        <label for="amountInput">Amount</label>
        <input type="number" id="amountInput" placeholder="0.00" min="0.01" step="0.01">

        <button class="btn btn-secondary w-100" onclick="makePayment()">Submit Payment</button>
    </div>

    <div class="payment-list" id="paymentList">
        <h3>Recent Payments</h3>
        <?php foreach(array_reverse($recentPayments) as $p): ?>
            <div class="payment-item">
                <span><?= htmlspecialchars($p['payee']) ?></span>
                <span class="amount">$<?= number_format($p['amount'],2) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function addPayee(){
    let name = prompt("Enter new payee name:");
    if(!name) return;
    fetch('',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=add_payee&payee='+encodeURIComponent(name)
    }).then(r=>r.json()).then(data=>{
        if(data.ok){
            const select=document.getElementById('payeeSelect');
            select.innerHTML='';
            data.payees.forEach(p=>{
                let opt=document.createElement('option');
                opt.value=opt.text=p;
                select.add(opt);
            });
            select.value=name;
        }
    });
}

function makePayment(){
    const payee=document.getElementById('payeeSelect').value;
    const amount=parseFloat(document.getElementById('amountInput').value);
    if(!payee || isNaN(amount) || amount<=0){alert('Please enter valid payment details.');return;}
    fetch('',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=make_payment&payee=${encodeURIComponent(payee)}&amount=${encodeURIComponent(amount)}`
    }).then(r=>r.json()).then(data=>{
        if(data.ok){
            const list=document.getElementById('paymentList');
            const div=document.createElement('div');
            div.className='payment-item';
            div.innerHTML=`<span>${data.payment.payee}</span><span class="amount">$${data.payment.amount.toFixed(2)}</span>`;
            list.prepend(div);
            document.getElementById('amountInput').value='';
        } else alert(data.error);
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>