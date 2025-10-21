<?php
session_start();
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/';
$lending_file = $docRoot . 'data/lending_pending.json';
$lending_transfers = file_exists($lending_file) ? json_decode(file_get_contents($lending_file), true) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lending Transfers</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary:#1d123c;
    --secondary:#c5e600;
    --background:#2a1852;
    --text:#fff;
    --cancel:#e74c3c;
    --resend:#3498db;
    --success:#27ae60;
}
body{margin:0;font-family:'Poppins',sans-serif;background:var(--background);color:var(--text);}
.main-container{max-width:900px;margin:0 auto;padding:15px;}
.tabs{display:flex;margin-bottom:15px;}
.tab{flex:1;text-align:center;padding:10px;border-radius:8px 8px 0 0;cursor:pointer;background:var(--primary);margin-right:2px;transition:.3s;font-weight:bold;}
.tab.active{background:var(--secondary);color:#000;}
.transfer-card{background:var(--primary);padding:12px;margin-bottom:12px;border-radius:10px;position:relative;transition:transform 0.2s;cursor:pointer;}
.transfer-card:hover{transform:scale(1.01);}
.transfer-header{display:flex;justify-content:space-between;align-items:center;font-size:0.95em;}
.transfer-details{display:none;margin-top:8px;font-size:0.85em;flex-direction:column;position:relative;}
.transfer-row{display:flex;justify-content:space-between;margin:4px 0;}
.transfer-row .title{text-align:left;font-weight:bold;}
.transfer-row .value{text-align:right;}
.invisible-btn{position:absolute;bottom:5px;right:5px;width:30px;height:30px;cursor:pointer;}
.btn{padding:6px 14px;border:none;border-radius:5px;cursor:pointer;font-weight:bold;transition:.3s;font-size:0.85em;}
.btn-cancel{background:var(--cancel);color:#fff;margin-right:5px;}
.btn-cancel:hover{background:#c0392b;}
.btn-resend{background:var(--resend);color:#fff;}
.btn-resend:hover{background:#2980b9;}
.spinner-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);justify-content:center;align-items:center;z-index:999;}
.spinner-overlay svg{width:80px;height:80px;animation:rotate 2s linear infinite;}
.spinner-overlay .path{stroke:#fff;stroke-linecap:round;animation:dash 1.5s ease-in-out infinite;}
@keyframes rotate{100%{transform:rotate(360deg);}}
@keyframes dash{0%{stroke-dasharray:1,150;stroke-dashoffset:0;}50%{stroke-dasharray:90,150;stroke-dashoffset:-35;}100%{stroke-dasharray:90,150;stroke-dashoffset:-124;}}
.notification{position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:6px;color:#fff;font-weight:bold;display:none;z-index:1000;}
.notification.success{background:var(--success);}
.notification.error{background:var(--cancel);}
</style>
</head>
<body>
<div class="main-container">

    <!-- Tabs -->
    <div class="tabs">
        <div class="tab active" data-status="Pending" onclick="switchTab(this)">Pending</div>
        <div class="tab" data-status="Completed" onclick="switchTab(this)">Completed</div>
        <div class="tab" data-status="Canceled" onclick="switchTab(this)">Canceled</div>
    </div>

    <!-- Cards -->
    <div id="cards-container">
        <?php foreach($lending_transfers as $t): ?>
            <div class="transfer-card" id="transfer-<?=htmlspecialchars($t['transaction_id'])?>" data-status="<?=htmlspecialchars($t['status'])?>" onclick="toggleDetails(this)">
                <div class="transfer-header">
                    <strong><?=htmlspecialchars($t['recipient_name'])?></strong>
                    <span class="status-text"><?=htmlspecialchars($t['status'])?></span>
                </div>
                <div class="transfer-details">
                    <div class="transfer-row"><div class="title">Email:</div><div class="value"><?=htmlspecialchars($t['recipient_email'])?></div></div>
                    <div class="transfer-row"><div class="title">Amount:</div><div class="value">$<?=number_format($t['amount_sent'],2)?></div></div>
                    <?php if($t['status']==='Pending'): ?>
                        <div style="margin-top:6px;">
                            <button class="btn btn-cancel" onclick="event.stopPropagation();updateTransfer('<?=htmlspecialchars($t['transaction_id'])?>','cancel')">Cancel</button>
                            <button class="btn btn-resend" onclick="event.stopPropagation();updateTransfer('<?=htmlspecialchars($t['transaction_id'])?>','resend')">Resend</button>
                        </div>
                    <?php endif; ?>
                    <?php if($t['status']==='Pending' || $t['status']==='Completed'): ?>
                        <div class="invisible-btn" style="display:none" onclick="toggleStatus('<?=htmlspecialchars($t['transaction_id'])?>')" title="Toggle status"></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<div class="spinner-overlay" id="spinnerOverlay">
    <svg viewBox="0 0 50 50">
        <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
    </svg>
</div>
<div class="notification" id="notification"></div>

<script>
const spinner = document.getElementById('spinnerOverlay');
const notification = document.getElementById('notification');

// Expand/collapse card details
function toggleDetails(card){
    const details = card.querySelector('.transfer-details');
    const btn = card.querySelector('.invisible-btn');
    if(details.style.display==='flex'){
        details.style.display='none';
        if(btn) btn.style.display='none';
    } else {
        details.style.display='flex';
        if(btn) btn.style.display='block';
    }
}

// Tab switching
function switchTab(tab){
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    tab.classList.add('active');
    const status = tab.dataset.status;
    document.querySelectorAll('.transfer-card').forEach(card=>{
        card.style.display = card.dataset.status===status ? 'block':'none';
        const details = card.querySelector('.transfer-details');
        const btn = card.querySelector('.invisible-btn');
        if(details) details.style.display='none';
        if(btn) btn.style.display='none';
    });
}

// Spinner & notification helpers
function showSpinner(show=true){ spinner.style.display = show?'flex':'none'; }
function showNotification(msg,type='success'){
    notification.textContent = msg;
    notification.className = 'notification '+type;
    notification.style.display='block';
    setTimeout(()=>notification.style.display='none',3000);
}

// Cancel / Resend actions
async function updateTransfer(txId,action){
    showSpinner(true);
    try{
        const res = await fetch('pending_transfers_ajax.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({transaction_id:txId,action:action})
        });
        const data = await res.json();
        showSpinner(false);
        if(data.success){
            const card = document.getElementById('transfer-'+txId);
            if(action==='cancel'){
                card.querySelector('.status-text').textContent='Canceled';
                card.dataset.status='Canceled';
                card.querySelectorAll('button').forEach(b=>b.remove());
                const btn = card.querySelector('.invisible-btn');
                if(btn) btn.remove();
                showNotification('Transfer canceled','success');
            } else if(action==='resend'){
                showNotification('Resend notice sent','success');
            }
        } else showNotification('Action failed','error');
    }catch(e){
        showSpinner(false);
        showNotification('Network error','error');
        console.error(e);
    }
}

// Toggle Pending <-> Completed
async function toggleStatus(txId){
    const card = document.getElementById('transfer-'+txId);
    const current = card.dataset.status;
    let newStatus = '';
    if(current==='Pending') newStatus='Completed';
    else if(current==='Completed') newStatus='Pending';
    else return;

    showSpinner(true);
    try{
        const res = await fetch('pending_transfers_ajax.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({transaction_id:txId,action:newStatus.toLowerCase()})
        });
        const data = await res.json();
        showSpinner(false);
        if(data.success){
            card.dataset.status=newStatus;
            card.querySelector('.status-text').textContent=newStatus;
            showNotification('Status updated to '+newStatus,'success');
            const activeTab = document.querySelector('.tab.active').dataset.status;
            card.style.display = card.dataset.status===activeTab ? 'block':'none';
        } else showNotification('Action failed','error');
    }catch(e){
        showSpinner(false);
        showNotification('Network error','error');
        console.error(e);
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', ()=>switchTab(document.querySelector('.tab.active')));
</script>
</body></html>