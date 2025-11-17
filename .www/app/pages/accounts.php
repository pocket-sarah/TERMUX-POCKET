<?php
session_start();

$base = realpath(__DIR__ . '//data');
$dataDir = ($base ? $base : (__DIR__ . '//data')) . '/';

function jsonLoad($f){ return is_file($f) ? (json_decode(file_get_contents($f), true) ?: []) : []; }
function money($v){ return '$'.number_format((float)$v,2); }
function mask($n){ return '•••• '.substr(preg_replace('/\D+/','',(string)$n),-4); }

$accounts = jsonLoad($dataDir.'accounts.json');
$lending  = jsonLoad($dataDir.'lending_pending.json');
$canceled = jsonLoad($dataDir.'canceled.json');

function sampleTx($n=10){
  $shops=['Walmart','Sobeys','Tim Hortons','Canadian Tire','Metro','Shell','PetSmart'];
  $out=[]; for($i=0;$i<$n;$i++){
    $out[]=['date'=>date('Y-m-d',strtotime("-$i days")),'description'=>'Purchase - '.$shops[array_rand($shops)],'amount'=>-(rand(5,180)),'kind'=>'debit'];
  }
  return $out;
}

$final=[];
foreach($accounts as $a){
  $id=$a['id']??uniqid('acct_');
  $a['tx']=sampleTx();
  foreach($lending as $l){
    if(($l['account_type']??'')==($a['type']??'')){
      $a['tx'][]=['date'=>$l['date']??date('Y-m-d'),'description'=>'e-Transfer sent to '.$l['recipient_name'],'amount'=>-abs($l['amount_sent']??0),'kind'=>'etr_out'];
    }
  }
  foreach($canceled as $c){
    if(($c['account_type']??'')==($a['type']??'')){
      $a['tx'][]=['date'=>$c['canceled_at']??$c['date']??date('Y-m-d'),'description'=>'Canceled e-Transfer refund from '.$c['recipient_name'],'amount'=>abs($c['amount_sent']??0),'kind'=>'refund_in'];
    }
  }
  usort($a['tx'],fn($x,$y)=>strcmp($y['date'],$x['date']));
  $a['id']=$id;
  $final[]=$a;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>KOHO Accounts</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body{margin:0;font-family:Poppins,system-ui;background:#1d123c;color:#fff;padding:16px}
.cards{display:flex;flex-direction:column;gap:12px}
.card{background:#0f0a25;border:1px solid rgba(255,255,255,.15);border-radius:10px;padding:14px}
.row{display:flex;justify-content:space-between;align-items:center}
.name{font-weight:700}
.sub{opacity:.8;font-size:.85rem}
.bal{font-weight:800;font-size:1.1rem}
.btn{background:#c5e600;color:#1d123c;border:0;border-radius:8px;padding:8px 12px;font-weight:800;margin-right:6px;cursor:pointer}
.alt{background:transparent;color:#c5e600;border:1px solid #c5e600}
.peek{display:none;margin-top:8px;font-size:.9rem}
.peek.active{display:block}
.out{color:#ff5656}.in{color:#79d200}
#history{display:none}
.table{width:100%;border-collapse:collapse;margin-top:10px}
.table th,.table td{padding:6px;text-align:left;border-bottom:1px solid rgba(255,255,255,.15)}
.table th{color:#c5e600}
#back{background:transparent;color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:8px;padding:6px 10px;cursor:pointer;margin-bottom:8px}
</style>
</head>
<body>
<h2>Accounts</h2>
<div id="cards" class="cards">
<?php foreach($final as $a): ?>
  <div class="card" data-id="<?=htmlspecialchars($a['id'])?>">
    <div class="row">
      <div>
        <div class="name"><?=htmlspecialchars($a['nickname']??$a['type'])?></div>
        <div class="sub"><?=htmlspecialchars($a['type'])?> • <?=mask($a['number'])?></div>
      </div>
      <div class="bal"><?=money($a['balance']??0)?></div>
    </div>
    <div class="row" style="margin-top:8px">
      <button class="btn view">View History</button>
      <button class="btn alt peekBtn">Peek</button>
    </div>
    <div class="peek">
      <?php foreach(array_slice($a['tx'],0,3) as $t):
        $cls=$t['amount']<0?'out':'in';
        $sign=$t['amount']<0?'-':'+';
        ?>
        <div class="row" style="font-size:.85rem">
          <div><?=date('M d',strtotime($t['date']))?></div>
          <div><?=htmlspecialchars($t['description'])?></div>
          <div class="<?=$cls?>"><?=$sign.money(abs($t['amount']))?></div>
        </div>
      <?php endforeach;?>
    </div>
  </div>
<?php endforeach;?>
</div>

<div id="history">
  <button id="back"><i class="fa-solid fa-chevron-left"></i> Back</button>
  <h3 id="acctTitle"></h3>
  <div id="txWrap"></div>
</div>

<script>
const data = <?=json_encode($final,JSON_UNESCAPED_SLASHES)?>;
const cards=document.getElementById('cards');
const hist=document.getElementById('history');
const wrap=document.getElementById('txWrap');
const title=document.getElementById('acctTitle');
document.querySelectorAll('.peekBtn').forEach(b=>{
  b.onclick=()=>{const pk=b.closest('.card').querySelector('.peek');pk.classList.toggle('active');};
});
document.querySelectorAll('.view').forEach(b=>{
  b.onclick=()=>{showHistory(b.closest('.card').dataset.id);}
});
document.getElementById('back').onclick=()=>{hist.style.display='none';cards.style.display='flex';};
function showHistory(id){
  const a=data.find(x=>x.id===id);
  if(!a)return;
  cards.style.display='none';hist.style.display='block';
  title.textContent=`${a.nickname||a.type} • ${a.type}`;
  const rows=a.tx.map(t=>{
    const cls=t.amount<0?'out':'in';const s=t.amount<0?'-':'+';
    const d=new Date(t.date+'T00:00:00');const wk=d.toLocaleString('en',{weekday:'short'});
    return `<tr><td>${wk} ${String(d.getDate()).padStart(2,'0')}</td><td>${esc(t.description)}</td><td class="${cls}" style="text-align:right">${s}${fmt(Math.abs(t.amount))}</td></tr>`;
  }).join('');
  wrap.innerHTML=`<table class="table"><thead><tr><th>Date</th><th>Description</th><th>Amount</th></tr></thead><tbody>${rows}</tbody></table>`;
}
function esc(s){return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");}
function fmt(n){return '$'+Number(n).toFixed(2);}
function mask(n){n=String(n);return '•••• '+n.slice(-4);}
</script>
</body>
</html>