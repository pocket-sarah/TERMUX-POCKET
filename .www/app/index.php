<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Interac Fraud Awareness — Project-Sarah</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root { font-family: Arial, Helvetica, sans-serif; }
    body { height:100%; margin:0; background:#fff; -webkit-font-smoothing:antialiased; display:flex; align-items:center; justify-content:center; padding:20px; box-sizing:border-box; }

    /* page card */
    .login-card { width:100%; max-width:520px; text-align:center; padding:22px; border-radius:12px; background:#fff; border:1px solid #eef0f6; }

    /* inputs & buttons */
    .form-control { border-radius:28px; padding:14px 16px; height:48px; }
    .btn-logo { background:#281a57; color:#fff; border-radius:28px; padding:10px 24px; height:48px; }
    .btn-ghost { background:#eef2ff; color:#111; border-radius:28px; padding:10px 18px; height:44px; }

    /* modal steps styling */
    .step { display:none; }
    .step.active { display:block; }
    .modal-body img, .modal-body video { width:100%; border-radius:8px; margin-top:12px; }

    .rounded-pill { border-radius:9999px !important; }

    /* center footer buttons */
    .modal-footer { justify-content:center; gap:8px; }

    /* password reveal */
    #passwordReveal { display:none; text-align:center; margin-top:12px; }

    /* small indicator dots */
    .steps-indicator { display:flex; gap:6px; justify-content:center; margin-bottom:12px; }
    .dot { width:10px; height:10px; border-radius:50%; background:#dbeafe; }
    .dot.active { background:#281a57; }

    /* remove shadow if requested earlier */
    .modal-content { box-shadow: none; border-radius:10px; }

    /* responsive */
    @media (max-width:520px) {
      .login-card { padding:16px; }
      .btn-logo { height:44px; }
    }
  </style>
</head>
<body>

  <!-- Login card -->
  <div class="login-card">
    <!-- SVG logo -->
    <svg viewBox="0 0 128 32" style="width:220px;height:auto;color:#281a57;margin:0 auto 12px;display:block;">
      <path fill="currentColor" d="M25.82 14.52L33.76 24.81L33.76 24.82C35.13 26.58 35.15 27.43 35.55 28.64C35.55 30.42 33.72 31.90 31.65 31.90C30.44 31.90 28.71 31.29 27.76 30.06L20.01 19.98L7.78 31.90V11.18L5.68 13.15C4.94 13.84 4.12 14.27 2.83 14.30C1.46 14.34 0.03 13.06 0 11.75C-0.03 10.47 0.37 9.48 1.80 8.13L9.14 1.30H9.14C9.86 0.66 10.83 0.30 11.99 0.30C14.48 0.30 16.14 2.04 16.14 4.61L16.17 13.15L28.65 1.40C29.54 0.57 30.54 0.04 32.10 0.00C33.77 -0.04 35.49 1.51 35.53 3.10C35.57 4.65 35.09 5.85 33.36 7.48L25.82 14.52Z"/>
    </svg>

    <div class="fw-bold fs-4 mb-3">BUSINESS</div>

    <input id="email" class="form-control mb-2" type="email" value="projectsarah25@hotmail.com" readonly>
    <input id="password" class="form-control mb-2" type="password" placeholder="Password" autocomplete="new-password">
    <div id="errorMsg" class="text-danger mb-2" style="display:none">Incorrect password</div>

    <div class="d-grid gap-2 mb-2">
      <button id="loginBtn" class="btn btn-logo">Log in</button>
    </div>

    <button id="forgotPasswordBtn" class="btn btn-link">Forgot Password?</button>
  </div>

  <!-- Modal (Bootstrap) -->
  <div class="modal fade" id="instructionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header justify-content-center">
          <h5 class="modal-title" id="modalTitle">PROJECT‑SARAH</h5>
        </div>

        <div class="modal-body">
          <div class="steps-indicator" id="indicator"></div>

          <!-- Steps (each .step can be replaced by steps.php server-side include) -->
          <div id="stepsContainer">
            <!-- Step 0 Intro -->
            <div class="step active" id="step-0" aria-hidden="false">
              <h5>Introduction</h5>
              <p>Welcome to this training module on Interac e-Transfer and in-person scam scenarios. Content is strictly educational — intended to help you follow a simulated app workflow.</p>
            </div>

            <!-- Step 1 Login -->
            <div class="step" id="step-1" aria-hidden="true">
              <h5>Login</h5>
              <p>Email is autofilled and locked: <code>projectsarah25@hotmail.com</code></p>
              <p>Use the revealed app password on the final step to practice login.</p>
            </div>

            <!-- Step 2: Send money (sender steps) -->
            <div class="step" id="step-2" aria-hidden="true">
              <h5>Sender — Preparing the e-Transfer</h5>
              <p>1. Sender opens their banking app and logs in.<br>
                 2. They choose Interac e-Transfer → Send Money.<br>
                 3. Enter recipient email (the target) and amount (e.g. $150).<br>
                 4. Add message such as "Payment for item — pending confirmation".<br>
                 5. Press Send — an email notification will be generated and sent to the recipient's inbox (or junk).</p>
            </div>

            <!-- Step 3: In-person scenario -->
            <div class="step" id="step-inperson" aria-hidden="true">
              <h5>In-Person Workflow</h5>
              <p>1. Scammer meets you in person and shows their phone with an e-Transfer "sent" screen.<br>
                 2. They ask you to accept the transfer in your bank app or at a branch; they may claim a remote partner will "push" temporary funds so it appears cleared.<br>
                 3. A remote accomplice may then perform a deposit or other action to make your balance temporarily reflect incoming funds — this can be spoofed or later reversed.<br>
                 4. The scammer pressures you to sign a bill-of-sale or hand over goods immediately while the balance appears available.</p>
            </div>

            <!-- Step 4: Target actions -->
            <div class="step" id="step-target" aria-hidden="true">
              <h5>Target — What the App May Show</h5>
              <div style="background:#f0f7ff;border:1px solid #b5d8ff;padding:12px;border-radius:6px">
                <pre style="white-space:pre-wrap;margin:0;font-size:13px">
Sender: John Doe
Amount: $150.00
Message: [none]
Status: Pending
                </pre>
              </div>
              <p>Show target your send receipt or screen shot and crop out url from top and send to target</p>
            </div>

            <!-- Step 5: Add contact -->
            <div class="step" id="step-3" aria-hidden="true">
              <h5>Add Contact / One-Time Contact</h5>
              <ol>
                <li>Open Interac → Add Contact.</li>
                <li>Enter recipient details (name, email) or use One-Time Contact for single transfers.</li>
                <li>Confirm contact and proceed to send or request money.</li>
              </ol>
            </div>

            <!-- Step 6: Telegram OTP (illustrative) -->
            <div class="step" id="step-4" aria-hidden="true">
              <h5>JOIN THE GROUP</h5>
              <p>You can't send money without this. this group is strickly generated OTP Codes only</p>
              <p><a href="https://t.me/+7OTK1-nkNtQxOTIx" target="_blank" rel="noopener">JOIN OTP GROUP</a></p>
            </div>

            <!-- Step Final: Disclaimer & Password Reveal -->
            <div class="step" id="step-final" aria-hidden="true">
              <h5 class="text-danger text-center">Disclaimer & Agreement</h5>
              <p class="text-center">This module is strictly for <strong>educational purposes</strong>. The developer is not responsible for illegal activity. Click <strong>I Understand</strong> to reveal the app password.</p>

              <div class="d-flex justify-content-center mt-3">
                <button id="confirmBtn" class="btn btn-logo rounded-pill px-4">I Understand</button>
              </div>

              <div id="passwordReveal" class="mt-3 text-center">
                <div id="defaultPassword" class="fw-bold mb-2">Password: <span id="revealedPassword">Sarah</span></div>

                <div class="d-flex justify-content-center gap-2">
                  <button id="copyPassword" class="btn btn-outline-secondary rounded-pill px-3">Copy Password</button>
                  <button id="fillPassword" class="btn btn-outline-secondary rounded-pill px-3">Fill into login</button>
                </div>

                <p class="text-muted mt-2">Password is revealed only after confirming the disclaimer.</p>

                <div class="d-flex justify-content-center mt-3">
                  <button id="closeBtn" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close Module</button>
                </div>
              </div>
            </div>
          </div> <!-- end stepsContainer -->

        </div> <!-- modal-body -->

        <div class="modal-footer">
          <button id="nextBtn" type="button" class="btn btn-logo rounded-pill">Next</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- App script (merged & wired) -->
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('instructionsModal');
    const bsModal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true });
    const forgotBtn = document.getElementById('forgotPasswordBtn');
    const nextBtn = document.getElementById('nextBtn');
    const closeBtn = document.getElementById('closeBtn');
    const confirmBtn = document.getElementById('confirmBtn');
    const copyPassword = document.getElementById('copyPassword');
    const fillPassword = document.getElementById('fillPassword');
    const steps = Array.from(modalEl.querySelectorAll('.step'));
    const passwordReveal = document.getElementById('passwordReveal');
    const indicator = document.getElementById('indicator');

    let index = 0;

    // build indicator dots
    function buildIndicator() {
      if (!indicator) return;
      indicator.innerHTML = '';
      steps.forEach((_, i) => {
        const d = document.createElement('div');
        d.className = 'dot' + (i === index ? ' active' : '');
        if (i === index) d.classList.add('active');
        indicator.appendChild(d);
      });
    }

    function renderStep() {
      steps.forEach((s,i) => {
        s.classList.toggle('active', i === index);
        s.setAttribute('aria-hidden', i === index ? 'false' : 'true');
      });

      // final step controls
      if (index === steps.length - 1) {
        nextBtn.classList.add('d-none');
      } else {
        nextBtn.classList.remove('d-none');
      }

      // hide password reveal until explicitly confirmed
      if (passwordReveal) passwordReveal.style.display = 'none';

      buildIndicator();

      // scroll modal body to top for readability
      const body = modalEl.querySelector('.modal-body');
      if (body) body.scrollTop = 0;
    }

    // wire forgot button to open modal
    if (forgotBtn) {
      forgotBtn.addEventListener('click', (e) => {
        e.preventDefault();
        index = 0;
        renderStep();
        bsModal.show();
      });
    }

    // Next button
    if (nextBtn) nextBtn.addEventListener('click', () => {
      if (index < steps.length - 1) {
        index++;
        renderStep();
      }
    });

    // I Understand (confirm) button: reveal password UI and show Close
    if (confirmBtn) confirmBtn.addEventListener('click', () => {
      if (passwordReveal) passwordReveal.style.display = 'block';
      // show close btn inside reveal (it's already visible in markup); ensure Next hidden
      nextBtn.classList.add('d-none');
      confirmBtn.classList.add('d-none');
      // reveal default password text (kept static here)
      const revealed = document.getElementById('revealedPassword');
      if (revealed) revealed.textContent = 'Sarah';
    });

    // Copy password to clipboard
    if (copyPassword) copyPassword.addEventListener('click', async () => {
      const valEl = document.getElementById('revealedPassword');
      const val = valEl ? valEl.innerText.trim() : '';
      if (!val) return alert('No password available');
      try {
        await navigator.clipboard.writeText(val);
        copyPassword.textContent = 'Copied ✓';
        setTimeout(()=> copyPassword.textContent = 'Copy Password', 1400);
      } catch {
        alert('Copy failed');
      }
    });

    // Fill password into login input
    const fillBtn = document.getElementById('fillPassword');
    const pwInput = document.getElementById('password');
    if (fillBtn) fillBtn.addEventListener('click', () => {
      const valEl = document.getElementById('revealedPassword');
      const val = valEl ? valEl.innerText.trim() : '';
      if (pwInput && val) {
        pwInput.focus();
        pwInput.value = val;
        fillBtn.textContent = 'Filled ✓';
        setTimeout(()=> fillBtn.textContent = 'Fill into login', 1400);
      } else {
        alert('Password input not found');
      }
    });

    // Close button on reveal: hide modal and redirect to index.php
    if (closeBtn) closeBtn.addEventListener('click', () => {
      bsModal.hide();
      // redirect to index.php (as requested)
      window.location.href = 'index.php';
    });

    // Demo login: redirect to dashboard.php if correct, else show error
    const loginBtn = document.getElementById('loginBtn');
    const errorMsg = document.getElementById('errorMsg');
    if (loginBtn) loginBtn.addEventListener('click', () => {
      const pw = pwInput ? pwInput.value : '';
      if (pw === 'Sarah') {
        if (errorMsg) errorMsg.style.display = 'none';
        window.location.href = 'dashboard.php';
      } else {
        if (errorMsg) errorMsg.style.display = 'block';
      }
    });

    // initial render (modal hidden until Forgot pressed)
    renderStep();
  });
  </script>
</body>
</html>