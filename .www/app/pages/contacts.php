<?php
// contacts_manage.php
// Contacts management page: list, add, edit, with transactions. Uses Bootstrap mobile/formal theme.

declare(strict_types=1);

$scriptDir = realpath(__DIR__) ?: __DIR__;
$dataDir = $scriptDir . '/data';
@mkdir($dataDir, 0755, true);
$contactsFile = $dataDir . '/contacts.json';
$txnLog = $dataDir . '/transactions.log';

// --- Load contacts ---
$contacts = [];
if (is_file($contactsFile)) {
    $raw = file_get_contents($contactsFile);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $contacts = $decoded;
}

// --- Handle form submissions (add/edit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $question = trim($_POST['security_question'] ?? '');
    $answer = trim($_POST['security_answer'] ?? '');
    $note = trim($_POST['note'] ?? '');
    if ($name !== '' && $email !== '') {
        if ($id) {
            // edit existing
            foreach ($contacts as &$c) {
                if (($c['id'] ?? '') === $id) {
                    $c['name'] = $name;
                    $c['email'] = $email;
                    $c['security_question'] = $question;
                    $c['security_answer'] = $answer;
                    $c['note'] = $note;
                }
            }
            unset($c);
        } else {
            // add new
            $contacts[] = [
                'id' => bin2hex(random_bytes(4)),
                'name' => $name,
                'email' => $email,
                'security_question' => $question,
                'security_answer' => $answer,
                'note' => $note,
            ];
        }
        file_put_contents($contactsFile, json_encode($contacts, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// --- Load transactions ---
$txns = [];
if (is_file($txnLog)) {
    $handle = fopen($txnLog, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') continue;
            $j = json_decode($line, true);
            if (is_array($j) && !empty($j['contact_id'])) $txns[] = $j;
        }
        fclose($handle);
    }
}

// --- Filter & sort contacts ---
$contacts = array_values(array_filter($contacts, fn($c)=>trim($c['name']??'')!=='One-time Contact'));
usort($contacts, fn($a,$b)=>strcasecmp($a['name']??'',$b['name']??''));

// Helper: get txns for contact id
function txns_for(array $txns, string $contact_id): array {
    $out = [];
    foreach ($txns as $t) if (($t['contact_id']??'') === $contact_id) $out[] = $t;
    usort($out, fn($x,$y)=>strcmp($y['date']??'',$x['date']??''));
    return $out;
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contacts • Manage</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f8fafc;font-family:Inter,system-ui}
.card-compact{border-radius:.7rem;box-shadow:0 1px 4px rgba(16,24,40,.04)}
.muted-small{font-size:.82rem;color:rgba(0,0,0,.55)}
.txn-row{font-size:.88rem;border-top:1px dashed rgba(0,0,0,.04);padding:.5rem 0}
.contact-meta{font-size:.85rem;color:#495057}
</style>
</head>
<body>
<header class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
  <h5 class="mb-0">Contacts</h5>
  <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addContactModal">Add Contact</button>
</header>
<main class="container py-3">
<div class="row g-3">
<?php foreach($contacts as $c):
$cid=htmlspecialchars($c['id']);
$name=htmlspecialchars($c['name']??'—');
$email=htmlspecialchars($c['email']??'—');
$note=htmlspecialchars($c['note']??'');
$contactTxns = txns_for($txns,$c['id']??'');
?>
<div class="col-12">
<div class="card card-compact">
<div class="card-body">
<div class="d-flex justify-content-between align-items-start">
<div>
<div class="fw-semibold"><?= $name ?></div>
<div class="contact-meta"><?= $email ?><?= $note? " · $note":"" ?></div>
</div>
<div class="text-end">
<button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#c-<?= $cid ?>">Details</button>
<button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit-<?= $cid ?>">Edit</button>
</div>
</div>
<div class="collapse mt-3" id="c-<?= $cid ?>">
<div class="small text-muted mb-2">Security Q/A</div>
<div class="row gx-2">
<div class="col-12 col-md-6 mb-2">
<label class="form-label small mb-1">Question</label>
<div class="form-control form-control-sm"><?= htmlspecialchars($c['security_question']??'—') ?></div>
</div>
<div class="col-12 col-md-6 mb-2">
<label class="form-label small mb-1">Answer</label>
<div class="form-control form-control-sm"><?= htmlspecialchars(str_repeat('*',min(12,strlen((string)($c['security_answer']??'')))) ) ?></div>
</div>
</div>
<div class="mt-3">
<div class="d-flex justify-content-between mb-2">
<div class="fw-semibold small">Transaction history</div>
<div class="muted-small"><?= count($contactTxns) ?> entries</div>
</div>
<?php if(empty($contactTxns)): ?>
<div class="text-muted small">No transactions.</div>
<?php else: foreach($contactTxns as $t): ?>
<div class="txn-row d-flex justify-content-between">
<div>
<div class="fw-medium"><?= htmlspecialchars($t['note']??'Transfer') ?></div>
<div class="muted-small"><?= htmlspecialchars($t['date']??'') ?></div>
</div>
<div class="text-end fw-semibold"><?= htmlspecialchars($t['currency']??'CAD') ?> <?= number_format((float)($t['amount']??0),2) ?></div>
</div>
<?php endforeach; endif;?>
</div>
</div>
</div>
</div>

<!-- Edit modal -->
<div class="modal fade" id="edit-<?= $cid ?>" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<h6 class="modal-title">Edit <?= $name ?></h6>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<form method="post">
<div class="modal-body">
<input type="hidden" name="id" value="<?= $cid ?>">
<div class="mb-2">
<label class="form-label small">Name</label>
<input class="form-control form-control-sm" name="name" value="<?= $name ?>" required>
</div>
<div class="mb-2">
<label class="form-label small">Email</label>
<input class="form-control form-control-sm" name="email" value="<?= $email ?>" required>
</div>
<div class="mb-2">
<label class="form-label small">Security Question</label>
<input class="form-control form-control-sm" name="security_question" value="<?= htmlspecialchars($c['security_question']??'') ?>">
</div>
<div class="mb-2">
<label class="form-label small">Security Answer</label>
<input class="form-control form-control-sm" name="security_answer" value="<?= htmlspecialchars($c['security_answer']??'') ?>">
</div>
<div class="mb-2">
<label class="form-label small">Note</label>
<input class="form-control form-control-sm" name="note" value="<?= $note ?>">
</div>
</div>
<div class="modal-footer">
<button class="btn btn-secondary btn-sm" type="button" data-bs-dismiss="modal">Cancel</button>
<button class="btn btn-primary btn-sm" type="submit">Save</button>
</div>
</form>
</div>
</div>
</div>

<?php endforeach; ?>
</div>
</main>

<!-- Add Contact Modal -->
<div class="modal fade" id="addContactModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header"><h6 class="modal-title">Add Contact</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post">
<div class="modal-body">
<div class="mb-2"><label class="form-label small">Name</label><input class="form-control form-control-sm" name="name" required></div>
<div class="mb-2"><label class="form-label small">Email</label><input class="form-control form-control-sm" name="email" required></div>
<div class="mb-2"><label class="form-label small">Security Question</label><input class="form-control form-control-sm" name="security_question"></div>
<div class="mb-2"><label class="form-label small">Security Answer</label><input class="form-control form-control-sm" name="security_answer"></div>
<div class="mb-2"><label class="form-label small">Note</label><input class="form-control form-control-sm" name="note"></div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
<button type="submit" class="btn btn-primary btn-sm">Add</button>
</div>
</form>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>