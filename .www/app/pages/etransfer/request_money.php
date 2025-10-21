<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Send e-Transfer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/etransfere.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<style>
#confirmModal table td:first-child { font-weight:600; font-size:0.9em; text-align:left; padding-right:10px; }
#confirmModal table td:last-child { text-align:right; font-size:0.9em; }
iframe { width:100%; height:70vh; border:none; }
#spinnerOverlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:9999; }
</style>
</head>
<body class="p-3">

<div id="spinnerOverlay"><i class="fas fa-spinner fa-spin fa-3x text-white"></i></div>

<form id="transferForm" method="POST">
  <label>Select Account</label>
  <select id="selected_account" name="selected_account" class="form-select" required>
    <option value="">-- Select Account --</option>
    <?php foreach($accounts as $a): ?>
      <option value="<?= htmlspecialchars($a['id']) ?>"><?= htmlspecialchars($a['nickname'].' ('.$a['type'].') - $'.number_format($a['balance'],2)) ?></option>
    <?php endforeach; ?>
  </select>

  <label>Recipient</label>
  <div class="select-with-btn d-flex gap-2">
    <select id="recipientSelect" name="recipient_email" class="form-select flex-grow-1" required>
      <option value="">-- Select Contact --</option>
      <?php foreach($contacts as $c): ?>
        <option value="<?= htmlspecialchars($c['email']) ?>" data-name="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name'].' ('.$c['email'].')') ?></option>
      <?php endforeach; ?>
      <option value="one_time" data-name="">One-Time Contact</option>
    </select>
    <button type="button" id="addContactBtn" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">+</button>
  </div>

  <div id="oneTimeField" style="display:none; margin-top:10px;">
    <input type="hidden" id="recipient_name_one_time" name="recipient_name_one_time" value="One-Time Contact">
    <input type="email" id="recipient_email_one_time" name="recipient_email_one_time" class="form-control" placeholder="Recipient Email">
  </div>

  <input type="hidden" id="recipient_name" name="recipient_name" value="">
  <label>Amount</label>
  <input type="text" id="amount" name="amount" class="form-control" placeholder="0.00" required>
  <label>Memo (optional)</label>
  <textarea id="memo" name="memo" class="form-control" rows="1"></textarea>

  <div class="spacer-60"></div>
  <div class="form-buttons text-center">
    <button type="submit" id="confirmButton" class="btn btn-primary">Send Money</button>
  </div>
</form>
<!-- Modals -->
<div class="modal fade" id="addContactModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add Contact</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0"><iframe src="add_contact.php"></iframe></div></div></div></div>

<div class="modal fade" id="confirmModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirm Transfer</h5></div><div class="modal-body"><table style="width:100%;"><tr><td>Account:</td><td id="cAccount"></td></tr><tr><td>Recipient:</td><td id="cRecipient"></td></tr><tr><td>Amount:</td><td id="cAmount"></td></tr><tr><td>Memo:</td><td id="cMemo"></td></tr></table></div><div class="modal-footer flex-column gap-2"><button id="agreeConfirm" class="btn btn-primary w-100">Confirm</button><button class="btn btn-secondary w-100" data-bs-dismiss="modal">Cancel</button></div></div></div></div>

<div class="modal fade" id="otpModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content text-center"><div class="modal-header"><h5 class="modal-title">Verify OTP</h5></div><div class="modal-body"><p>We sent you a code to +1(780)574-4754</p><input type="text" id="otpInput" maxlength="6" class="form-control text-center fs-4"></div><div class="modal-footer flex-column gap-2"><button id="verifyOtpBtn" class="btn btn-primary w-100">Verify</button><button class="btn btn-secondary w-100" data-bs-dismiss="modal">Cancel</button></div></div></div></div>

<div class="modal fade" id="unregisteredModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:8px;"><div class="modal-header" style="background-color:#dc3545; color:#fff;"><h5 class="modal-title"><i class="fas fa-exclamation-circle"></i> Warning</h5><button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button></div><div class="modal-body text-center" style="background:#1d123c; color:#fff;"><p id="modalMessage" class="mb-0"></p></div><div class="modal-footer" style="background:#1d123c; border-top:none;"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background:#6c757d; border:none;">Cancel</button><button type="button" class="btn btn-primary" id="continueButton" style="border:none;">Continue</button></div></div></div></div>
<script>
const transferForm = document.getElementById('transferForm');
const recipientSelect = document.getElementById('recipientSelect');
const unregisteredModalEl = document.getElementById('unregisteredModal');
const unregisteredModal = new bootstrap.Modal(unregisteredModalEl);
const modalMessage = document.getElementById('unregisteredModalMessage');
const modalOkBtn = document.getElementById('modalOkBtn');

transferForm.addEventListener('submit', function(e) {
  e.preventDefault(); // stop immediate submission

  let recipientName, recipientEmail;

  if (recipientSelect.value === 'one_time') {
    recipientName = document.getElementById('recipient_name_one_time').value || 'One-Time Contact';
    recipientEmail = document.getElementById('recipient_email_one_time').value.trim() || 'unspecified';
  } else {
    const selectedOption = recipientSelect.options[recipientSelect.selectedIndex];
    recipientName = selectedOption.dataset.name || 'Registered Contact';
    recipientEmail = selectedOption.value || 'unspecified';
  }

  // Set the modal message
  modalMessage.textContent = `${recipientName} <${recipientEmail}> is not registered for Koho2Koho. Interac auto-deposit will be disabled for this transaction.`;

  // Show the modal
  unregisteredModal.show();

  // When user clicks OK, submit the form
  modalOkBtn.onclick = () => {
    unregisteredModal.hide();
    transferForm.submit();
  };
});
</script>
<script src="assets/js/etransfere.js"></script>
</body>
</html>