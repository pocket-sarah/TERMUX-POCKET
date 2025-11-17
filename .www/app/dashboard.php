<?php
$path = '/data/data/com.termux/files/home/persistent_sessions';
if (!is_dir($path)) mkdir($path, 0777, true);
ini_set('session.save_path', $path);
session_start();
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/';
$config = @include($root . 'config/config.php');
$sender = $config['sendername'] ?? 'User';

// Latest transfer
$latest = null;
$file = $root . 'data/lending_pending.json';
if (file_exists($file)) {
  $list = json_decode(file_get_contents($file), true);
  $today = date('Y-m-d');
  foreach (array_reverse($list) as $t) {
    if (strpos($t['date'], $today) === 0) { $latest = $t; break; }
  }
}
$rewards = [
  "5% cashback on groceries",
  "Double KOHO points online",
  "Refer a friend for $20 bonus"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,maximum-scale=1">
<title>KOHO Mobile</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" rel="stylesheet">
<style>
:root{--purple:#1d123c;--yellow:#c5e600;--yellow2:#b4d300;--green:#79d200}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent;margin:0;padding:0}
body{font-family:Poppins,sans-serif;background:var(--purple);color:#fff;height:100dvh;overflow:hidden;display:flex;flex-direction:column}
header{display:flex;justify-content:space-between;align-items:center;height:54px;padding:0 16px;background:rgba(15,10,37,.85);backdrop-filter:blur(10px);border-bottom:1px solid rgba(197,230,0,.15);z-index:10}
header svg{height:22px;color:#fff}
header .icons{display:flex;gap:10px}
header button{background:none;border:0;color:#fff;font-size:18px}
main{flex:1;position:relative;overflow:hidden}
iframe{position:absolute;inset:0;width:100%;height:100%;border:0;background:#0f0a25;opacity:0;transition:opacity .3s;pointer-events:auto;z-index:1}
iframe.active{opacity:1}
footer{position:fixed;bottom:0;left:0;right:0;height:56px;background:rgba(15,10,37,.9);backdrop-filter:blur(12px);border-top:1px solid rgba(197,230,0,.15);display:grid;grid-template-columns:repeat(5,1fr);align-items:center;justify-items:center;z-index:15}
footer button{background:none;border:0;color:#bbb;font-size:1.2em;display:flex;justify-content:center;align-items:center}
footer button.active{color:var(--yellow)}
footer .center .badge{background:rgba(197,230,0,.15);border:1px solid rgba(197,230,0,.25);border-radius:12px;padding:6px 10px}
.sheet{position:fixed;inset:0;z-index:100;pointer-events:none}
.sheet.open{pointer-events:auto}
.scrim{position:absolute;inset:0;background:rgba(15,10,37,.45);backdrop-filter:blur(10px);opacity:0}
.panel{position:absolute;bottom:0;left:0;right:0;background:rgba(15,10,37,.95);border-top-left-radius:18px;border-top-right-radius:18px;padding:20px;transform:translateY(100%)}
.grid6{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center}
.tile{background:rgba(255,255,255,.05);border:1px solid rgba(197,230,0,.16);border-radius:12px;padding:14px 6px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-decoration:none;color:#fff;transition:background .25s}
.tile i{color:var(--yellow);font-size:22px;margin-bottom:4px}
.tile span{font-size:.72rem;color:#ddd}
.tile:active{background:rgba(197,230,0,.12)}
#spinnerOverlay{display:none;position:fixed;inset:0;background:rgba(29,18,60,.9);z-index:200;align-items:center;justify-content:center;pointer-events:none}
#spinnerOverlay.is-open{pointer-events:auto}
#spinner{width:52px;height:52px;border-radius:50%;border:6px solid rgba(255,255,255,.1);border-top-color:var(--green);animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.modal{position:fixed;inset:0;z-index:300;background:linear-gradient(160deg,#0f0a25,#1a1145);display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;padding:20px;opacity:0;visibility:hidden;pointer-events:none}
.modal.active{opacity:1;visibility:visible;pointer-events:auto}
.modal h3{color:var(--yellow);margin-bottom:10px}
.modal button{background:var(--yellow);color:var(--purple);border:0;padding:10px 16px;border-radius:8px;margin-top:16px;font-weight:700}
/* Interaction rules */
.sheet:not(.open),.modal:not(.active){pointer-events:none}
</style>
</head>
<body>
<header>
  <svg width="128" height="32" viewBox="0 0 128 32"><path fill="currentColor" d="M25.8216 14.5243L33.7593 24.8135L33.7566 24.8216C35.1285 26.5807 35.5502 27.4281 35.5502 28.6375C35.5502 30.418 33.7166 31.901 31.6481 31.901C30.4444 31.901 28.7148 31.2869 27.7647 30.056L20.0085 19.9813L7.7817 31.901V11.183L5.67584 13.1513C4.94453 13.8351 4.11979 14.2749 2.83065 14.3044C1.45611 14.3366 0.0335272 13.0574 0.00149898 11.7461C-0.0278603 10.4697 0.36715 9.48019 1.79774 8.13403L9.13755 1.29866C9.86086 0.663126 10.8324 0.298419 11.9854 0.298419C14.4756 0.298419 16.1437 2.03877 16.1437 4.60505L16.1678 13.1513L28.6534 1.39788C29.5395 0.571951 30.5404 0.0383085 32.1018 0.000766265C33.77 -0.0394576 35.4941 1.5105 35.5315 3.10068C35.5689 4.64528 35.0911 5.84663 33.3563 7.47704L25.8216 14.5243Z"/></svg>
  <div class="icons">
    <button id="notifBtn"><i class="fa-solid fa-bell"></i></button>
    <button><i class="fa-solid fa-headset"></i></button>
  </div>
</header>

<main id="main"></main>
<footer id="footerNav"></footer>

<div id="sheet" class="sheet"><div class="scrim"></div><div class="panel"><div class="grid6" id="grid6"></div></div></div>
<div id="spinnerOverlay"><div id="spinner"></div></div>

<div id="welcomeModal" class="modal">
  <h3>Welcome, <?= htmlspecialchars($sender) ?></h3>
  <p><?= $latest ? 'Latest transfer to <b>'.htmlspecialchars($latest['recipient_name']).'</b> — $'.number_format($latest['amount_sent'],2) : 'No transfers today.' ?></p>
  <div style="margin-top:10px">
    <?php foreach($rewards as $r): ?><div>🎁 <?= htmlspecialchars($r) ?></div><?php endforeach; ?>
  </div>
  <button id="closeWelcome">Continue</button>
</div>

<div id="timeoutModal" class="modal">
  <h3>Session Timeout</h3>
  <p>Inactive 5 minutes.<br>Redirecting in <span id="countdown">5</span> s…</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
<script>
const pages=['home','accounts','payments','profile','etransfer','cards','rewards','contacts'];
const main=document.getElementById('main'),spinner=document.getElementById('spinnerOverlay');
const iframes={};
pages.forEach(p=>{
  const f=document.createElement('iframe');f.src=`pages/${p}.php`;
  main.appendChild(f);iframes[p]={f,loaded:false};
  f.onload=()=>iframes[p].loaded=true;
});
setTimeout(()=>show('home'),120);

function setFrameInteractivity(){
  Object.values(iframes).forEach(o=>{
    o.f.style.pointerEvents = o.f.classList.contains('active') ? 'auto' : 'none';
  });
}

function show(p){
  spinner.classList.add('is-open'); spinner.style.display='flex';
  Object.values(iframes).forEach(o=>o.f.classList.remove('active'));
  const o=iframes[p];
  const chk=setInterval(()=>{
    if(o.loaded){
      clearInterval(chk);
      o.f.classList.add('active');
      setFrameInteractivity();
      spinner.classList.remove('is-open'); spinner.style.display='none';
    }
  },40);
}

const footer=document.getElementById('footerNav');
footer.innerHTML=[
  {p:'home',i:'fa-house'},{p:'accounts',i:'fa-wallet'},
  {a:'sheet'},{p:'payments',i:'fa-money-bill-transfer'},{p:'profile',i:'fa-user'}
].map(n=>n.a?
  `<button class="center" id="openSheet"><div class="badge"><i class="fa-solid fa-plus"></i></div></button>`:
  `<button data-p="${n.p}"><i class="fa-solid ${n.i}"></i></button>`
).join('');
footer.addEventListener('click',e=>{
  const b=e.target.closest('button');if(!b)return;
  if(b.id==='openSheet'){toggleSheet();return;}
  footer.querySelectorAll('button').forEach(x=>x.classList.remove('active'));
  b.classList.add('active');show(b.dataset.p);
});

const sheet=document.getElementById('sheet'),scrim=sheet.querySelector('.scrim'),panel=sheet.querySelector('.panel');
scrim.onclick=()=>toggleSheet();
function toggleSheet(){
  if(sheet.classList.contains('open')){
    gsap.to(panel,{y:'100%',duration:.25});
    gsap.to(scrim,{opacity:0,duration:.25,onComplete:()=>{
      sheet.classList.remove('open');
      setFrameInteractivity();
    }});
  } else {
    sheet.classList.add('open');
    gsap.set(panel,{y:'100%'}); gsap.to(panel,{y:0,duration:.4,ease:'back.out(1.4)'});
    gsap.to(scrim,{opacity:1,duration:.3});
  }
}

document.getElementById('grid6').innerHTML=[
  ['accounts','fa-wallet','Accounts'],
  ['etransfer','fa-money-bill-transfer','e-Transfer'],
  ['payments','fa-file-invoice-dollar','Bills'],
  ['cards','fa-credit-card','Cards'],
  ['rewards','fa-gift','Rewards'],
  ['contacts','fa-address-book','Contacts']
].map(x=>`<a href="#" data-p="${x[0]}" class="tile"><i class="fa-solid ${x[1]}"></i><span>${x[2]}</span></a>`).join('');
document.getElementById('grid6').onclick=e=>{
  const a=e.target.closest('a');if(!a)return;e.preventDefault();
  toggleSheet();show(a.dataset.p);
};

window.addEventListener('load',()=>{
  const w=document.getElementById('welcomeModal');w.classList.add('active');
  gsap.fromTo(w,{opacity:0},{opacity:1,duration:.4});
  new Notyf().success('Welcome back <?= htmlspecialchars($sender) ?>');
});
document.getElementById('closeWelcome').onclick=()=>{
  gsap.to('#welcomeModal',{opacity:0,duration:.3,onComplete:()=>document.getElementById('welcomeModal').remove()});
};

let last=Date.now();
['touchstart','keydown','mousemove'].forEach(ev=>document.addEventListener(ev,()=>last=Date.now()));
setInterval(()=>{if(Date.now()-last>5*60*1000)triggerTimeout();},20000);
function triggerTimeout(){
  const t=document.getElementById('timeoutModal');t.classList.add('active');
  gsap.fromTo(t,{opacity:0},{opacity:1,duration:.3});
  let s=5;const cd=document.getElementById('countdown');
  const int=setInterval(()=>{s--;cd.textContent=s;if(s<=0){clearInterval(int);location.href='index.php';}},1000);
}
</script>
</body>
</html>