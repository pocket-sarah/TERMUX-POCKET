
<?php
// steps.php
// Project-Sarah: Interac e-Transfer educational scenario
// Focused on login and step-by-step in-person e-Transfer with accomplice temporary deposit
?>
<div id="steps" class="px-0">

  <!-- Step 0: Introduction -->
  <div class="step" id="step-0" aria-hidden="false">
    <h3 class="text-center">Introduction</h3>
    <p>This provides a <strong>step-by-step </strong> of an in-person Interac e-Transfer where an accomplice temporarily deposits funds to make it appear cleared. The purpose is purely educational to understand the workflow.</p>
    <img src="assets/images/intro.png" alt="Overview" class="w-100 rounded" onerror="this.style.display='none'">
  </div>

  <!-- Step 1: Login -->
  <div class="step" id="step-1" aria-hidden="true">
    <h3 class="text-center">Login</h3>
    <p>Email is <strong>autofilled and locked</strong>: <code>projectsarah25@hotmail.com</code></p>
    <p>Enter the password only after reviewing the disclaimer in the final step to proceed with the scenario.</p>
  </div>

  <!-- Step 2: Open App and Add Contact -->
  <div class="step" id="step-add-contact" aria-hidden="true">
    <h3 class="text-center">Add Contact</h3>
    <p>To prepare for the in-person transaction:</p>
    <ol>
      <li>Launch the KOHO BUISNESS app.</li>
      <li>Select <strong>Add Contact</strong> to register a recipient or <strong>One-Time Contact</strong> for a single transfer.</li>
      <li>Input recipient name, email, and Security question + answer if prompted.</li>
      <li>Confirm and save the contact.</li>
    </ol>
    <img src="assets/images/add_contact.png" alt="Add Contact" class="w-100 rounded" onerror="this.style.display='none'">
  </div>

  <!-- Step 3: Initiate In-Person e-Transfer -->
  <div class="step" id="step-inperson-transfer" aria-hidden="true">
    <h3 class="text-center">In-Person e-Transfer</h3>
    <p>This step shows the sender initiating a transfer in person:</p>
    <ol>
      <li>You open the  app and clicks add contact</li>
      <li>Hand your device to target let them fill there contact info </li>
      <li>Once added go to send money option to initiate the e-Transfer to the target account.</li>
      <li>You will the receive an otp code from the telegram OTP group.<b>need to send</b></li>
      <li>An accomplice temporarily deposits funds into the target's account to make it appear available.</li>
      <li>Target opens their app and sees the transfer as "verified" temporarily.</li>
      <li>Scenario proceeds with the target confirming the transfer within the app.</li>
      <li>Note: The temporary deposit is not real funds — it for educational purposes.</li>
    </ol>
  </div>

  <!-- Step 4: Using the App to Confirm & Send -->
  <div class="step" id="step-send-confirm" aria-hidden="true">
    <h3 class="text-center">Step 4 — Using the App to Confirm & Send</h3>
    <p>Follow the app workflow after adding a contact:</p>
    <ol>
      <li>Open the e-Transfer app and select the recipient added earlier.</li>
      <li>Enter the transfer amount and optional message.</li>
      <li>Check confirmation notices — it will show "verified" due to the accomplice deposit.</li>
      <li>Send or accept the transfer within the app interface.</li>
      <li>Observe how the temporary deposit makes funds appear available in the app.</li>
    </ol>
    <img src="assets/images/demo_app.png" alt="App interface" class="w-100 rounded" onerror="this.style.display='none'">
  </div>

  <!-- Step Final: Disclaimer & Password Reveal -->
<!-- Step Final: Disclaimer & Password Reveal -->
<div class="step" id="step-final" aria-hidden="true">
  <h3 class="text-center text-danger">Disclaimer & Agreement</h3>
  <p class="text-center">
    This module is strictly for <strong>educational and awareness</strong> purposes only.
    The developer and publisher of this application are <strong>not responsible</strong> for any illegal
    activity or misuse of the information demonstrated here. Click <strong>I Understand</strong> to reveal
    the password and continue with the app scenario.
  </p>

  <div class="d-flex justify-content-center mt-3">
    <button id="confirmBtn" class="btn btn-primary px-4 py-2 rounded-pill">I Understand</button>
  </div>

  <div id="passwordReveal" class="mt-3 text-center" style="display:none">
    <div id="defaultPassword" class="fw-bold mb-2">App Password: <span id="revealedPassword">Sarah</span></div>

    <div class="d-flex justify-content-center gap-2">
      <button id="copyPassword" class="btn btn-outline-secondary px-3 py-2 rounded-pill">Copy Password</button>
      <button id="fillPassword" class="btn btn-outline-secondary px-3 py-2 rounded-pill">Fill into login</button>
    </div>

    <p class="note text-muted mt-2">Password is revealed only after confirming the disclaimer.</p>

    <div class="d-flex justify-content-center mt-3">
      <button id="closeBtn" class="btn btn-secondary px-4 py-2 rounded-pill">Close Module</button>
    </div>
  </div>
</div>
