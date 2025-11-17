<?php
declare(strict_types=1);
session_start();

/* --- Load data --- */
$root = rtrim($_SERVER['DOCUMENT_ROOT'],'/').'/data/';
$accounts = [];
if (is_file($root.'accounts.json')) {
  $j = json_decode(file_get_contents($root.'accounts.json'), true);
  if (is_array($j)) $accounts = $j;
}
$contacts = [];
if (is_file($root.'contacts.csv') && ($f=fopen($root.'contacts.csv','r'))) {
  while (($r=fgetcsv($f,0,',','"','\\'))!==false) {
    $n=trim($r[0]??''); $v=trim($r[1]??'');
    if ($n!=='' && $v!=='') $contacts[] = ['name'=>$n,'value'=>$v];
  }
  fclose($f);
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Transfer</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" rel="stylesheet">
<style>
/* === Root Variables === */
:root {
  --purple: #1d123c;
  --yellow: #c5e600;
  --yellow2: #b4d300;
  --green: #79d200;
  --modal-bg: #0f0a25;
}

/* === Layout === */
body {
  font-family: Poppins, sans-serif;
  background: var(--purple);
  color: #fff;
  margin: 0;
  padding: 20px;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  min-height: 100vh;
}

/* === Forms === */
form {
  width: 100%;
  max-width: 460px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

label {
  font-weight: 500;
  margin-top: 6px;
}

.form-control,
.form-select {
  background: var(--yellow);
  color: var(--purple);
  border: none;
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 0.95em;
}

.form-control:focus,
.form-select:focus {
  outline: 2px solid var(--yellow2);
}

/* === Buttons === */
.btn-custom {
  background: var(--yellow);
  color: var(--purple);
  border: none;
  padding: 10px;
  border-radius: 6px;
  font-weight: 700;
  transition: 0.25s;
}

.btn-custom:hover {
  background: var(--yellow2);
  color: #fff;
}

/* === Contact Selector === */
.select-with-btn {
  display: flex;
  gap: 8px;
  align-items: center;
}

.select-with-btn .form-select {
  flex: 1;
}

.btn-add {
  width: 42px;
  height: 42px;
  background: var(--yellow);
  color: var(--purple);
  border: none;
  border-radius: 6px;
  font-weight: 700;
  display: flex;
  justify-content: center;
  align-items: center;
  transition: 0.2s;
}

.btn-add:hover {
  background: var(--yellow2);
  color: #fff;
}

/* === One-Time Contact === */
#oneTimeField {
  display: none;
}

.warn {
  display: none;
  background: rgba(197, 230, 0, 0.25);
  color: #000;
  padding: 8px;
  border-radius: 6px;
  font-size: 0.9em;
  margin-top: 5px;
}

/* === Modals === */
.modal-content {
  background: var(--modal-bg);
  color: #fff;
  border: 2px solid var(--yellow);
  border-radius: 10px;
}

.modal-header,
.modal-footer {
  border: none;
  background: transparent;
}

.modal-header .btn-close {
  filter: invert(1);
}

/* === Confirmation Modal === */
#confirmModal .modal-content {
  background: var(--modal-bg);
  color: #fff;
  border: 2px solid var(--yellow2);
  border-radius: 10px;
}

#confirmModal h5 {
  color: #fff;
  text-align: center;
  font-weight: 700;
  margin-bottom: 12px;
}

#confirmModal table {
  width: 100%;
  border-collapse: collapse;
  background: transparent;
  color: #fff;
}

#confirmModal td {
  padding: 6px 4px;
}

#confirmModal .k {
  text-align: left;
  color: #fff;
  font-weight: 600;
  width: 45%;
}

#confirmModal .v {
  text-align: right;
  color: var(--yellow2);
  font-weight: 600;
  width: 55%;
}

#rowMemo {
  display: none;
}

/* === OTP Field === */
#otpInput {
  max-width: 240px;
  margin: 0 auto;
  text-align: center;
  font-size: 1.2em;
  background: #fff;
  color: #000;
  letter-spacing: 2px;
  border-radius: 6px;
}

/* === Spinner Overlay === */
#spinner {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.82);
  z-index: 999;
  align-items: center;
  justify-content: center;
  flex-direction: column;
}

#spinner .spinner-border {
  width: 3rem;
  height: 3rem;
  color: var(--green);
}

.moodle-message {
  color: #fff;
  font-size: 1.2em;
  margin-top: 15px;
}

/* === Animations === */
@keyframes rotate {
  100% {
    transform: rotate(360deg);
  }
}

@keyframes dash {
  0% {
    stroke-dasharray: 1, 150;
    stroke-dashoffset: 0;
  }
  50% {
    stroke-dasharray: 90, 150;
    stroke-dashoffset: -35;
  }
  100% {
    stroke-dasharray: 90, 150;
    stroke-dashoffset: -124;
  }
}

/* === Responsive === */
@media (max-width: 480px) {
  form {
    padding: 0 8px;
  }
  .btn-custom {
    font-size: 0.95em;
  }
  #confirmModal td {
    font-size: 0.9em;
  }
}
/* === Force Confirmation Modal Primary Theme === */
#confirmModal .modal-dialog,
#confirmModal .modal-content {
  background-color: var(--purple) !important;
  color: #fff !important;
  border: 2px solid var(--yellow2) !important;
  border-radius: 10px !important;
  box-shadow: 0 0 18px rgba(197, 230, 0, 0.3);
}

#confirmModal h5 {
  color: #fff !important;
  text-align: center;
  font-weight: 700;
  margin-bottom: 12px;
}

#confirmModal table {
  width: 100%;
  border-collapse: collapse;
  background: transparent;
  color: #fff;
}

#confirmModal td {
  padding: 6px 4px;
}

#confirmModal .k {
  color: #fff !important;
  text-align: left;
  font-weight: 600;
  width: 45%;
}

#confirmModal .v {
  color: var(--yellow2) !important;
  text-align: right;
  font-weight: 600;
  width: 55%;
}

#confirmModal .modal-footer,
#confirmModal .modal-header {
  border: none !important;
  background: transparent !important;
}
/* FORCE confirm modal to primary theme */
#confirmModal {
  /* override BS5 modal tokens at the root of this modal */
  --bs-modal-bg: #1d123c;
  --bs-modal-color: #fff;
  --bs-body-bg: #1d123c;
  --bs-body-color: #fff;
}

#confirmModal .modal-dialog,
#confirmModal .modal-content,
#confirmModal .modal-header,
#confirmModal .modal-body,
#confirmModal .modal-footer {
  background-color: #1d123c !important;
  color: #fff !important;
  border: none !important;
}

#confirmModal .modal-content {
  border: 2px solid #b4d300 !important;
  border-radius: 10px !important;
  box-shadow: 0 0 18px rgba(197,230,0,.3);
}

#confirmModal h5 { color: #fff !important; }

#confirmModal table { background: transparent !important; }

#confirmModal .k { color: #fff !important; text-align: left; }

#confirmModal .v { color: #b4d300 !important; text-align: right; }
</style>
</head>
<body>
<div id="spinner"><div class="spinner-border"></div><div class="mt-2">Processing…</div></div>

<form id="transferForm" autocomplete="off">
  <label>Select Account</label>
  <select id="selected_account" class="form-select" required>
    <option value="">-- Select Account --</option>
    <?php foreach($accounts as $a): $label=($a['name']??'Account'); if(!empty($a['type']))$label.=" ({$a['type']})"; if(isset($a['balance']))$label.=' - $'.number_format((float)$a['balance'],2); ?>
      <option value="<?=e($a['id']??'')?>"><?=e($label)?></option>
    <?php endforeach; ?>
  </select>

  <label>Recipient</label>
  <div class="select-with-btn">
    <select id="recipientSelect" class="form-select">
      <option value="">-- Select Contact --</option>
      <?php foreach($contacts as $c): ?>
        <option value="<?=e($c['value'])?>" data-name="<?=e($c['name'])?>"><?=e($c['name'])?> (<?=e($c['value'])?>)</option>
      <?php endforeach; ?>
      <option value="one_time">One-Time Contact</option>
    </select>
    <button type="button" id="addContactBtn" class="btn-add">+</button>
  </div>

  <div id="oneTimeField">
    <input id="recipient_value_one_time" class="form-control" placeholder="Email or phone" disabled>
    <div id="one_unreg_warn" class="warn mt-2">⚠ Not registered. Add a security question and answer.</div>
    <div id="one_sec" style="display:none">
      <input id="one_q" class="form-control mt-2" placeholder="Security question">
      <input id="one_a" class="form-control mt-2" placeholder="Security answer">
    </div>
  </div>

  <label>Amount</label>
  <input id="amount" class="form-control" placeholder="0.00" required>

  <label>Memo (optional)</label>
  <textarea id="memo" class="form-control" rows="1"></textarea>

  <button type="button" id="confirmButton" class="btn-custom mt-2">Send Money</button>
<br><br><br><br></form>

<!-- Add Contact Modal -->
<div class="modal fade" id="addContactModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3">
      <div class="modal-header">
        <h5 class="modal-title">Add Contact</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input id="mc_name" class="form-control mb-2" placeholder="Contact name" disabled>
        <select id="mc_method" class="form-select mb-2" disabled>
          <option value="">Method</option>
          <option value="email">Email</option>
          <option value="phone">Phone</option>
        </select>
        <input id="mc_value" class="form-control mb-2" placeholder="Email or phone" disabled>
        <div id="mc_warn" class="warn">⚠ Not registered. Add security question and answer.</div>
        <div id="mc_sec" style="display:none">
          <input id="mc_q" class="form-control mt-2" placeholder="Security question">
          <input id="mc_a" class="form-control mt-2" placeholder="Security answer">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="mc_add" class="btn-custom" disabled>Add Contact</button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3">
      <h5 class="text-center mb-2">Confirm Transfer Details</h5>
      <table class="table table-borderless text-white mb-0">
        <tr><td class="k">Account</td><td class="v" id="cAccount"></td></tr>
        <tr><td class="k">Recipient Name</td><td class="v" id="cName"></td></tr>
        <tr><td class="k">Email</td><td class="v" id="cVal"></td></tr>
        <tr><td class="k">Method</td><td class="v" id="cMethod"></td></tr>
        <tr><td class="k">Amount</td><td class="v" id="cAmount"></td></tr>
        <tr id="rowMemo"><td class="k">Memo</td><td class="v" id="cMemo"></td></tr>
        <tr id="cSecRow" style="display:none"><td class="k">Question</td><td class="v" id="cSecQ"></td></tr>
      <tr id="cSecRow" style="display:none"><td class="k">Answer</td><td class="v" id="">•••••••••</td></tr>
      </table>
      <div class="text-center mt-3 d-grid gap-2">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel Transfer</button>
        <button id="confirmSend" class="btn-custom">Confirm details</button>
      </div>
    </div>
  </div>
</div>

<!-- Shared OTP Modal -->
<div class="modal fade" id="otpModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 id="otpTitle" class="mb-2">Verificaton</h5>
      <p id="otpSub" class="text-light mb-3">Enter the 6-digit code.</p>
      <input id="otpInput" class="form-control mb-3" maxlength="6" inputmode="numeric" placeholder="• • • • • •">
      <div class="d-grid gap-2">
        <button id="otpVerifyBtn" class="btn-custom">Verify</button>
        <button id="otpCancelBtn" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const $=id=>document.getElementById(id);
  const notyf=new Notyf({duration:2200,position:{x:'center',y:'top'}});
  const show=()=>{ $('spinner').style.display='flex' };
  const hide=()=>{ $('spinner').style.display='none' };
  const isEmail=v=>/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  const isPhone=v=>/^\+?\d{7,15}$/.test(v.replace(/[^\d+]/g,''));
  const api=async(u,d)=>{try{const r=await fetch(u,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(d)});const t=await r.text();try{return JSON.parse(t)}catch{return {success:false,message:'Invalid JSON',raw:t}}}catch{return {success:false,message:'Network error'}}};

  const addModal=new bootstrap.Modal($('addContactModal'));
  const confirmModal=new bootstrap.Modal($('confirmModal'));
  const otpModal=new bootstrap.Modal($('otpModal'));

  let otpContext=null; // 'unlock_onetime' | 'add_contact' | 'final_send'
  let pendingRecipient='';

  async function openOtp(context,title,sub,recipient=''){
    otpContext=context; pendingRecipient=recipient||'SELF';
    $('otpTitle').textContent=title; $('otpSub').textContent=sub; $('otpInput').value='';
    // hide any open modal then show spinner
    document.querySelectorAll('.modal.show').forEach(m=>bootstrap.Modal.getInstance(m).hide());
    show();
    const sent=await api('tools/otp.php',{recipient: pendingRecipient});
    hide();
    if(!sent||sent.success!==true){ notyf.error(sent?.message||'OTP send failed'); return; }
    otpModal.show();
  }

  // --- One-Time Contact gate: OTP before the field becomes editable ---
  $('recipientSelect').onchange=()=>{
    const isOne=$('recipientSelect').value==='one_time';
    $('oneTimeField').style.display=isOne?'block':'none';
    if(isOne){
      $('recipient_value_one_time').value='';
      $('recipient_value_one_time').disabled=true;
      $('one_unreg_warn').style.display='none';
      $('one_sec').style.display='none';
      openOtp('unlock_onetime','Verify to use One-Time Contact','We sent a code. Enter it to unlock.');
    }
  };

  $('recipient_value_one_time').oninput=()=>{
    const v=$('recipient_value_one_time').value.trim();
    const ok=isEmail(v)||isPhone(v);
    $('one_unreg_warn').style.display=ok?'block':'none';
    $('one_sec').style.display=ok?'block':'none';
  };

  // --- Add Contact: OTP first, then show modal enabled ---
  $('addContactBtn').onclick=()=>{
    // lock fields until verified
    $('mc_name').disabled=true; $('mc_method').disabled=true; $('mc_value').disabled=true; $('mc_add').disabled=true;
    $('mc_name').value=''; $('mc_method').value=''; $('mc_value').value='';
    openOtp('add_contact','Verify to add contact','We sent a code. Enter it to continue.');
  };

  // enable validation UI inside Add Contact after OTP verified
  function enableAddContactForm(){
    $('mc_name').disabled=false; $('mc_method').disabled=false; $('mc_value').disabled=false; $('mc_add').disabled=false;
    addModal.show();
  }
  $('mc_method').onchange=()=>$('mc_value').dispatchEvent(new Event('input'));
  $('mc_value').oninput=()=>{
    const m=$('mc_method').value,v=$('mc_value').value.trim();
    const ok=(m==='email'&&isEmail(v))||(m==='phone'&&isPhone(v));
    $('mc_warn').style.display=ok?'block':'none';
    $('mc_sec').style.display=ok?'block':'none';
  };
  $('mc_add').onclick=e=>{
    e.preventDefault();
    const n=$('mc_name').value.trim(), m=$('mc_method').value, v=$('mc_value').value.trim();
    if(!n||!m||!v){notyf.error('Complete all fields');return}
    if((m==='email'&&!isEmail(v))||(m==='phone'&&!isPhone(v))){notyf.error('Invalid contact');return}
    const opt=document.createElement('option'); opt.value=v; opt.dataset.name=n; opt.textContent=`${n} (${v})`;
    const s=$('recipientSelect'); const idx=[...s.options].findIndex(o=>o.value==='one_time');
    if(idx>=0) s.insertBefore(opt,s.options[idx]); else s.appendChild(opt);
    s.value=v; addModal.hide(); notyf.success('Contact added');
  };

  // --- Confirm flow ---
  $('confirmButton').onclick=()=>{
    const acc=$('selected_account'), recSel=$('recipientSelect'), amt=$('amount').value.trim(), memo=$('memo').value.trim();
    if(!acc.value){notyf.error('Select account');return}
    if(!recSel.value){notyf.error('Select recipient');return}
    if(!amt||isNaN(+amt)||+amt<=0){notyf.error('Enter valid amount');return}

    let name='', val='', method='Email';
    if(recSel.value==='one_time'){
      if($('recipient_value_one_time').disabled){notyf.error('Verify OTP to unlock one-time contact');return}
      val=$('recipient_value_one_time').value.trim();
      if(!(isEmail(val)||isPhone(val))){notyf.error('Enter valid contact');return}
      name='One-Time Contact'; method=isPhone(val)?'Phone':'Email';
      const q=$('one_q').value.trim(), a=$('one_a').value.trim();
      if(!q||!a){notyf.error('Enter security Q & A');return}
      $('cSecQ').textContent=q; $('cSecRow').style.display='table-row';
    }else{
      name=recSel.selectedOptions[0].dataset.name||''; val=recSel.value; method=isPhone(val)?'Phone':'Email'; $('cSecRow').style.display='none';
    }
    $('cAccount').textContent=acc.selectedOptions[0].text;
    $('cName').textContent=name;
    $('cVal').textContent=val;
    $('cMethod').textContent=method;
    $('cAmount').textContent='$'+(+amt).toFixed(2);
    if(memo){$('cMemo').textContent=memo; $('rowMemo').style.display='table-row'} else {$('rowMemo').style.display='none'}
    confirmModal.show();
  };

  // --- Final send requires OTP first ---
  $('confirmSend').onclick=()=>{
    const rec=$('cVal').textContent.trim();
    confirmModal.hide();
    openOtp('final_send','Verify to send transfer','Enter the code to proceed.', rec);
  };

  // --- Shared OTP verify handler ---
  $('otpVerifyBtn').onclick=async()=>{
    const code=$('otpInput').value.trim();
    if(!/^\d{6}$/.test(code)){notyf.error('Enter 6-digit OTP');return}
    otpModal.hide();
    show();
    const v=await api('tools/verify.php',{otp:code});
    hide();
    if(!v||v.success!==true){notyf.error(v?.message||'OTP invalid');return}

    if(otpContext==='unlock_onetime'){
      $('recipient_value_one_time').disabled=false;
      notyf.success('Verified. Enter contact.');
    } else if(otpContext==='add_contact'){
      enableAddContactForm();
      notyf.success('Verified. Add contact.');
    } else if(otpContext==='final_send'){
      await finalizeSend();
    }
  };

  // --- One-time contact warning/Q&A visibility after unlock ---
  $('recipient_value_one_time').addEventListener('blur',()=>{ // re-check on blur too
    const v=$('recipient_value_one_time').value.trim();
    const ok=isEmail(v)||isPhone(v);
    $('one_unreg_warn').style.display=ok?'block':'none';
    $('one_sec').style.display=ok?'block':'none';
  });

  // --- Finalize send to mailer.php (after OTP verified) ---
  async function finalizeSend(){
    show();
    const isOne=$('recipientSelect').value==='one_time';
    const payload={
      selected_account:$('selected_account').value||'',
      amount:$('amount').value.trim()||'',
      memo:$('memo').value.trim()||'',
      recipient_name: isOne ? '' : ($('recipientSelect').selectedOptions[0].dataset.name||''),
      recipient_email: isOne ? '' : ($('recipientSelect').value||''),
      recipient_name_one_time: isOne ? 'One-Time Contact' : '',
      recipient_email_one_time: isOne ? ($('recipient_value_one_time').value.trim()||'') : '',
      security_question: isOne ? ($('one_q').value.trim()||'') : '',
      security_answer: isOne ? ($('one_a').value.trim()||'') : ''
    };
    const m=await api('tools/mailer.php',payload);
    hide();
    if(!m||m.success!==true){notyf.error(m?.message||'Send failed');return}
    notyf.success('Transfer complete');
    setTimeout(()=>location.href='rec.php',900);
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const $=s=>document.querySelector(s);
  const $$=s=>document.querySelectorAll(s);

  function keepInView(el){
    const vv=window.visualViewport;
    const rect=el.getBoundingClientRect();
    const top=rect.top+(window.pageYOffset||document.documentElement.scrollTop);
    const center=top-(vv? (vv.height*0.5) : (window.innerHeight*0.5))+rect.height*0.5;
    window.scrollTo({top:Math.max(0,center),behavior:'smooth'});
  }

  function focusOnTap(e){
    const t=e.target.closest('input,textarea,select,[contenteditable="true"]');
    if(t){ t.focus(); setTimeout(()=>keepInView(t),50); }
  }

  function bindField(f){
    f.addEventListener('focus',()=>keepInView(f));
    f.addEventListener('click',()=>keepInView(f));
    f.addEventListener('input',()=>keepInView(f));
  }

  $$('input,textarea,select,[contenteditable="true"]').forEach(bindField);
  document.body.addEventListener('click',focusOnTap,true);

  if(window.visualViewport){
    visualViewport.addEventListener('resize',()=>{
      const a=document.activeElement;
      if(a && (a.tagName==='INPUT'||a.tagName==='TEXTAREA'||a.isContentEditable)) keepInView(a);
    });
    visualViewport.addEventListener('scroll',()=>{
      const a=document.activeElement;
      if(a && (a.tagName==='INPUT'||a.tagName==='TEXTAREA'||a.isContentEditable)) keepInView(a);
    });
  }

  $$('label[for]').forEach(l=>{
    l.addEventListener('click',e=>{
      const i=document.getElementById(l.getAttribute('for'));
      if(i){ i.focus(); setTimeout(()=>keepInView(i),50); }
    });
  });
});
</script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('button').forEach(btn=>{
    btn.style.position='fixed';
    btn.style.bottom='60px';
  });
});

</body>
</html>