<?php
// contacts_demo.php
// PHP backend version. Bootstrap mobile/formal theme. Reads contacts from data/contacts.json,
// excludes any contact with name exactly "One-time Contact", sorts alphabetically,
// and shows expandable cards. Transaction history read from data/transactions.log (JSONL).
// This is a demo only. Do not use real sensitive data.

declare(strict_types=1);

// --- Paths ---
$scriptDir = realpath(__DIR__) ?: __DIR__;
$dataDir = $scriptDir . '/data';
@mkdir($dataDir, 0755, true);
$contactsFile = $dataDir . '/contacts.json';
$txnLog = $dataDir . '/transactions.log';

// --- Load contacts (safe defaults if missing) ---
$contacts = [];
if (is_file($contactsFile)) {
    $raw = file_get_contents($contactsFile);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $contacts = $decoded;
}
if (empty($contacts)) {
    // sample fictional contacts (no real PII)
    $contacts = [
        ['id'=>'c1','name'=>'Alice Baker','email'=>'alice@example.local','note'=>'Preferred receiver'],
        ['id'=>'c2','name'=>'Carlos Diaz','email'=>'carlos@example.local','note'=>'VIP'],
        ['id'=>'c3','name'=>'One-time Contact','email'=>'temp@example.local','note'=>'Should be hidden'],
        ['id'=>'c4','name'=>'Beatrice Chan','email'=>'beatrice@example.local','note'=>'Frequent']
    ];
}

// --- Load transactions from JSON-lines log ---
// Each line expected: {"id":"txn-1","contact_id":"c1","amount":125.00,"currency":"CAD","date":"2025-10-01 12:34","note":"Test"}
$txns = [];
if (is_file($txnLog)) {
    $handle = fopen($txnLog, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') continue;
            $j = json_decode($line, true);
            if (is_array($j) && !empty($j['contact_id'])) {
                $txns[] = $j;
            }
        }
        fclose($handle);
    }
}
// If no txns found create mock entries (safe fictional demo)
if (empty($txns)) {
    $txns = [
        ['id'=>'t-1','contact_id'=>'c1','amount'=>125.00,'currency'=>'CAD','date'=>date('Y-m-d H:i', strtotime('-3 days')),'note'=>'Mock e-transfer'],
        ['id'=>'t-2','contact_id'=>'c4','amount'=>250.50,'currency'=>'CAD','date'=>date('Y-m-d H:i', strtotime('-2 days')),'note'=>'Mock payment'],
        ['id'=>'t-3','contact_id'=>'c2','amount'=>75.00,'currency'=>'CAD','date'=>date('Y-m-d H:i', strtotime('-1 days')),'note'=>'Mock refund'],
    ];
}

// --- Filter and sort contacts ---
// remove exact matches named "One-time Contact"
$contacts = array_values(array_filter($contacts, function($c){
    return !isset($c['name']) || trim($c['name']) !== 'One-time Contact';
}));

usort($contacts, function($a,$b){
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

// Helper: get txns for contact id, newest first
function txns_for(array $txns, string $contact_id): array {
    $out = [];
    foreach ($txns as $t) if (($t['contact_id'] ?? '') === $contact_id) $out[] = $t;
    usort($out, function($x,$y){ return strcmp($y['date'] ?? '', $x['date'] ?? ''); });
    return $out;
}

// Money format small
function fmt_money($amount, $cur='CAD'){ return ($cur==='USD' ? '$' : '$') . number_format((float)$amount,2); }

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Contacts • Demo</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{--bg:#f8fafc;--card:#ffffff;--accent:#0d6efd}
    body{background:var(--bg);-webkit-font-smoothing:antialiased;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial}
    .app-header{background:#fff;border-bottom:1px solid rgba(0,0,0,.05);padding:.75rem}
    .card-compact {border-radius:.7rem;box-shadow:0 1px 4px rgba(16,24,40,.04)}
    .muted-small{font-size:.82rem;color:rgba(0,0,0,.55)}
    .txn-row{font-size:.88rem;border-top:1px dashed rgba(0,0,0,.04);padding:.5rem 0}
    .contact-meta{font-size:.85rem;color:#495057}
    @media (max-width:420px){ .contact-name{font-size:1rem} .muted-small{font-size:.75rem} }
  </style>
</head>
<body>
  <header class="app-header">
    <div class="container d-flex align-items-center justify-content-between">
      <div>
        <h5 class="mb-0 fw-semibold">Contacts</h5>
        <div class="muted-small">Alphabetical. "One-time Contact" hidden.</div>
      </div>
      <div>
        <a href="#" class="btn btn-outline-secondary btn-sm" onclick="location.reload()">Refresh</a>
      </div>
    </div>
  </header>

  <main class="container py-3">
    <div class="row g-3">
      <?php if (empty($contacts)): ?>
        <div class="col-12">
          <div class="alert alert-secondary small mb-0">No contacts available.</div>
        </div>
      <?php endif; ?>

      <?php foreach ($contacts as $c): 
        $cid = htmlspecialchars($c['id'] ?? bin2hex(random_bytes(4)));
        $name = htmlspecialchars($c['name'] ?? '—');
        $email = htmlspecialchars($c['email'] ?? '—');
        $note = htmlspecialchars($c['note'] ?? '');
        $contactTxns = txns_for($txns, $c['id'] ?? '');
      ?>
      <div class="col-12">
        <div class="card card-compact">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="contact-name fw-semibold"><?= $name ?></div>
                <div class="contact-meta"><?= $email ?><?= $note ? " · " . $note : "" ?></div>
              </div>
              <div class="text-end">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#c-<?= $cid ?>" aria-expanded="false" aria-controls="c-<?= $cid ?>">
                  Details
                </button>
              </div>
            </div>

            <div class="collapse mt-3" id="c-<?= $cid ?>">
              <div class="small text-muted mb-2">Security Q/A (hidden by default)</div>
              <div class="row gx-2">
                <div class="col-12 col-md-6 mb-2">
                  <label class="form-label small mb-1">Question</label>
                  <div class="form-control form-control-sm"><?= htmlspecialchars($c['security_question'] ?? '—') ?></div>
                </div>
                <div class="col-12 col-md-6 mb-2">
                  <label class="form-label small mb-1">Answer</label>
                  <div class="form-control form-control-sm"><?= htmlspecialchars(str_repeat('*', min(12, strlen((string)($c['security_answer'] ?? ''))))) ?></div>
                </div>
              </div>

              <div class="mt-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <div class="fw-semibold small">Transaction history</div>
                  <div class="muted-small"><?= count($contactTxns) ?> entries</div>
                </div>

                <?php if (empty($contactTxns)): ?>
                  <div class="text-muted small">No transactions for this contact.</div>
                <?php else: ?>
                  <?php foreach ($contactTxns as $t): ?>
                    <div class="txn-row">
                      <div class="d-flex justify-content-between">
                        <div>
                          <div class="fw-medium"><?= htmlspecialchars($t['note'] ?? 'Transfer') ?></div>
                          <div class="muted-small"><?= htmlspecialchars($t['date'] ?? '') ?></div>
                        </div>
                        <div class="text-end">
                          <div class="fw-semibold"><?= htmlspecialchars($t['currency'] ?? 'CAD') ?> <?= number_format((float)($t['amount'] ?? 0),2) ?></div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>

              </div><!-- txn area -->

            </div><!-- collapse -->
          </div><!-- card-body -->
        </div><!-- card -->
      </div><!-- col -->
      <?php endforeach; ?>

    </div><!-- row -->
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>