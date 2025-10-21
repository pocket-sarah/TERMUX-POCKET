<?php
session_start();

// ================== Paths ==================
$documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/';
$configFile = $documentRoot . 'config/config.php';
$templatesDir = $documentRoot . 'templates/';

// ================== Load Config ==================
if (!file_exists($configFile)) die("Config file not found.");
$config = require $configFile;

// ================== Get current sender name ==================
$currentSender = $config['sendername'] ?? $config['smtp']['sendername'] ?? '';

// ================== Scan Templates ==================
$templates = [];
if (is_dir($templatesDir)) {
    foreach (glob($templatesDir . '*.html') as $file) {
        $templates[] = basename($file);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>⚙️ Interac Settings</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<style>
body {font-family:Poppins,sans-serif;background:#1d123c;color:#fff;}
.main-container {max-width:700px;margin:20px auto;padding:20px;background:#2c2060;border-radius:10px;}
.btn-custom {background:#c5e600;color:#1d123c;border:none;padding:10px;font-weight:bold;width:100%;margin-top:10px;border-radius:8px;}
.btn-custom:hover {background:#b4d300;color:#fff;}
label {margin-top:10px;}
#spinnerOverlay {
    display:none;
    position:fixed;
    top:0; left:0; width:100%; height:100%;
    background:rgba(0,0,0,0.5);
    justify-content:center; align-items:center; z-index:9999;
}
</style>
</head>
<body class="p-3">

<div id="spinnerOverlay">
    <i class="fas fa-spinner fa-spin fa-3x text-white"></i>
</div>

<div class="main-container">
<h3>⚙️ Interac Settings</h3>

<div id="alertContainer"></div>

<div class="mb-3">
    <label for="sender_name" class="form-label">Sender Name (First and Last Name required)</label>
    <input type="text" class="form-control" id="sender_name" value="<?=htmlspecialchars($currentSender)?>" placeholder="Enter First and Last Name" required>
</div>

<div class="mb-3">
    <label for="email_template" class="form-label">Select Template</label>
    <select class="form-select" id="email_template">
        <option value="">-- Select Template --</option>
        <?php foreach($templates as $t): ?>
            <option value="<?=htmlspecialchars($t)?>" <?=($config['email_template'] ?? '') === ($templatesDir.$t) ? 'selected' : ''?>>
                <?=htmlspecialchars($t)?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="text-center mt-3">
    <button id="saveButton" class="btn btn-custom">💾 Save Sender & Template</button>
</div>
</div>

<!-- WARNING MODAL -->
<div class="modal fade" id="warningModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-dark">
      <div class="modal-header">
        <h5 class="modal-title">⚠️ Important Warning</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Never use your legal name with this project. Using your real name may put you at legal risk.</p>
        <p>The developer is <strong>not responsible</strong> for any illegal activity conducted using this app.</p>
        <p>By clicking "I Understand", you accept this responsibility and wish to continue.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmSaveBtn">I Understand</button>
      </div>
    </div>
  </div>
</div>

<script>
// Capitalize words
function capitalizeWords(str) {
    return str.replace(/\b\w/g, l => l.toUpperCase());
}

const senderInput = document.getElementById("sender_name");
senderInput.addEventListener("blur", () => {
    senderInput.value = capitalizeWords(senderInput.value.trim());
});

const saveButton = document.getElementById('saveButton');
const spinner = document.getElementById('spinnerOverlay');
const alertContainer = document.getElementById('alertContainer');
const confirmSaveBtn = document.getElementById('confirmSaveBtn');

saveButton.addEventListener("click", () => {
    // Validate first and last name
    const sender = senderInput.value.trim();
    if (sender.split(/\s+/).length < 2) {
        alertContainer.innerHTML = `<div class="alert alert-danger">Please enter both first and last name</div>`;
        return;
    }
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('warningModal'));
    modal.show();
});

// Actual save after user clicks "I Understand"
confirmSaveBtn.addEventListener("click", async () => {
    const modalEl = document.getElementById('warningModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    modal.hide();

    const sender = capitalizeWords(senderInput.value.trim());
    senderInput.value = sender;
    const template = document.getElementById("email_template").value;

    spinner.style.display = "flex";
    alertContainer.innerHTML = "";

    try {
        const formData = new FormData();
        formData.append("sender_name", sender);
        formData.append("email_template", template);

        const response = await fetch("save_config.php", {
            method: "POST",
            body: formData
        });

        const result = await response.json();
        spinner.style.display = "none";

        alertContainer.innerHTML = `<div class="alert ${result.success?'alert-success':'alert-danger'}">${result.message}</div>`;
    } catch (err) {
        spinner.style.display = "none";
        alertContainer.innerHTML = `<div class="alert alert-danger">Unexpected error occurred</div>`;
    }
});
</script>

</body>
</html>