<?php
// etransfer_contacts.php
declare(strict_types=1);

/* ----------------- Robust config loader (same pattern) ----------------- */
function load_config_file(): array {
    $tried = [];
    $test = function(string $path) use (&$tried) {
        $real = $path === '' ? '' : @realpath($path);
        $tried[] = $real ?: $path;
        if ($real && is_file($real) && is_readable($real)) return $real;
        return false;
    };

    $env = getenv('CONFIG_PATH');
    if ($env && ($found = $test($env))) return require $found;

    $webroot = getenv('WEBROOT') ?: (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '');
    if ($webroot) {
        if ($found = $test($webroot . '/config/config.php')) return require $found;
        if ($found = $test($webroot . '/config.php')) return require $found;
    }

    $starts = [__DIR__, getcwd() ?: '', dirname(__DIR__), dirname(dirname(__DIR__))];
    if (!empty(getenv('HOME'))) $starts[] = getenv('HOME');
    $starts[] = '/';

    $maxUp = 8;
    foreach ($starts as $s) {
        $s = $s ?: '';
        $cur = $s;
        for ($i = 0; $i <= $maxUp && $cur !== DIRECTORY_SEPARATOR; $i++) {
            $candidates = [
                $cur . '/.www/config/config.php',
                $cur . '/.www/config.php',
                $cur . '/config/config.php',
                $cur . '/config.php'
            ];
            foreach ($candidates as $cand) {
                if ($found = $test($cand)) return require $found;
            }
            $parent = dirname($cur);
            if ($parent === $cur) break;
            $cur = $parent;
        }
    }

    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $dr = $_SERVER['DOCUMENT_ROOT'];
        if ($found = $test($dr . '/config/config.php')) return require $found;
        if ($found = $test($dr . '/../config/config.php')) return require $found;
    }

    // final fallback shallow scan (Termux common roots)
    $possibleRoots = ['/data/data', '/sdcard', '/storage', '/mnt', '/home'];
    foreach ($possibleRoots as $r) {
        if (!is_dir($r)) continue;
        $entries = @scandir($r) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $candidate = $r . '/' . $entry . '/.www/config/config.php';
            if ($found = $test($candidate)) return require $found;
        }
    }

    // fail with diagnostics
    $dbg = "<h2>Config file not found</h2><p>Set CONFIG_PATH or WEBROOT env var.</p><pre>Tried:\n" . implode("\n", array_unique($tried)) . "</pre>";
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: text/html; charset=utf-8', true, 500);
        echo $dbg;
    } else {
        fwrite(STDERR, strip_tags($dbg) . PHP_EOL);
    }
    exit(1);
}
$config = load_config_file();

/* ----------------- Storage path (data/contacts.json under webroot or script dir) ----------------- */
$webroot = getenv('WEBROOT') ?: (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : null);
$baseDir = $webroot ? rtrim($webroot, '/\\') : realpath(__DIR__ . '/..');
if (!$baseDir) $baseDir = realpath(__DIR__);
$dataDir = $baseDir . '/data';
if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
$dataFile = $dataDir . '/contacts.json';

/* ----------------- Load or initialize contacts ----------------- */
if (file_exists($dataFile)) {
    $contacts = json_decode(file_get_contents($dataFile), true) ?: [];
} else {
    $contacts = []; // start empty
}

/* ----------------- Helpers ----------------- */
function save_contacts(array $contacts, string $path): void {
    file_put_contents($path, json_encode($contacts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function uid(): string { return bin2hex(random_bytes(6)); }

function find_contact_index(array $contacts, string $id): int {
    foreach ($contacts as $i => $c) if (isset($c['id']) && $c['id'] === $id) return $i;
    return -1;
}

/* ----------------- POST actions: add / edit / delete / add_txn / delete_txn ----------------- */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_contact') {
        $new = [
            'id' => uid(),
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'security_question' => trim($_POST['security_question'] ?? ''),
            'security_answer' => trim($_POST['security_answer'] ?? ''),
            'transactions' => []
        ];
        $contacts[] = $new;
        save_contacts($contacts, $dataFile);
        $flash = 'Contact added.';
    } elseif ($action === 'edit_contact') {
        $id = $_POST['id'] ?? '';
        $i = find_contact_index($contacts, $id);
        if ($i >= 0) {
            $contacts[$i]['name'] = trim($_POST['name'] ?? $contacts[$i]['name']);
            $contacts[$i]['email'] = trim($_POST['email'] ?? $contacts[$i]['email']);
            $contacts[$i]['security_question'] = trim($_POST['security_question'] ?? $contacts[$i]['security_question']);
            $contacts[$i]['security_answer'] = trim($_POST['security_answer'] ?? $contacts[$i]['security_answer']);
            save_contacts($contacts, $dataFile);
            $flash = 'Contact saved.';
        } else { $flash = 'Contact not found.'; }
    } elseif ($action === 'delete_contact') {
        $id = $_POST['id'] ?? '';
        $i = find_contact_index($contacts, $id);
        if ($i >= 0) {
            array_splice($contacts, $i, 1);
            save_contacts($contacts, $dataFile);
            $flash = 'Contact deleted.';
        } else $flash = 'Contact not found.';
    } elseif ($action === 'add_txn') {
        $id = $_POST['id'] ?? '';
        $i = find_contact_index($contacts, $id);
        if ($i >= 0) {
            $txn = [
                'id' => uid(),
                'amount' => floatval($_POST['amount'] ?? 0),
                'currency' => $_POST['currency'] ?? ($config['currency'] ?? 'CAD'),
                'note' => trim($_POST['note'] ?? ''),
                'date' => $_POST['date'] ?? date('Y-m-d H:i:s')
            ];
            $contacts[$i]['transactions'][] = $txn;
            save_contacts($contacts, $dataFile);
            $flash = 'Transaction recorded.';
        } else $flash = 'Contact not found.';
    } elseif ($action === 'delete_txn') {
        $id = $_POST['id'] ?? '';
        $txnId = $_POST['txn_id'] ?? '';
        $i = find_contact_index($contacts, $id);
        if ($i >= 0) {
            foreach ($contacts[$i]['transactions'] as $j => $t) {
                if (($t['id'] ?? '') === $txnId) {
                    array_splice($contacts[$i]['transactions'], $j, 1);
                    save_contacts($contacts, $dataFile);
                    $flash = 'Transaction removed.';
                    break;
                }
            }
        } else $flash = 'Contact not found.';
    }
    // after POST redirect to avoid resubmission
    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? basename(__FILE__)) . (strpos($_SERVER['REQUEST_URI'], '?') === false ? '' : ''));
    exit;
}

/* ----------------- Small helpers for display ----------------- */
function fmt_money($amt, $cur = 'CAD') { return ($cur === 'USD' ? '$' : '$') . number_format((float)$amt, 2); }
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ----------------- Page output ----------------- */
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>e-Transfer Contacts</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--bg:#10092a;--card:#1c1142;--accent:#c5e600;--text:#fff}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Poppins,Segoe UI,Roboto,Arial}
.container{max-width:980px;margin:10px auto;padding:12px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.h-title{font-weight:700;color:var(--accent);font-size:1rem}
.add-btn{background:var(--accent);color:#111;padding:8px 10px;border-radius:8px;border:none;cursor:pointer;font-weight:600}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px}
.card{background:var(--card);padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,0.04)}
.fine{font-size:0.76rem;opacity:0.9}
.row{display:flex;justify-content:space-between;align-items:center;margin:6px 0}
.label{color:var(--accent);font-weight:600;font-size:0.82rem}
.value{font-size:0.82rem;text-align:right}
.small{font-size:0.72rem;opacity:0.85}
.txn{background:rgba(255,255,255,0.02);padding:6px;border-radius:6px;margin:6px 0;font-size:0.78rem}
.form-inline{display:flex;gap:6px;flex-wrap:wrap}
.input,textarea,select{background:transparent;border:1px solid rgba(255,255,255,0.06);padding:8px;border-radius:8px;color:var(--text);min-width:0}
textarea{min-height:64px}
.btn{padding:8px 10px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);background:transparent;color:var(--text);cursor:pointer}
.btn.danger{border-color:#7a1b1b}
.footer{margin-top:12px;text-align:center;font-size:0.75rem;opacity:0.8}

/* modal */
.modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);}
.modal.open{display:flex}
.modal .panel{width:96%;max-width:560px;background:var(--card);padding:12px;border-radius:12px}
@media(max-width:480px){.h-title{font-size:0.95rem}.add-btn{padding:7px 9px}.label{font-size:0.78rem}.value{font-size:0.78rem}}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="h-title"><i class="fa-solid fa-user-check"></i> e-Transfer Contacts</div>
    <button class="add-btn" id="openAdd">+ Add Contact</button>
  </div>

  <?php if ($flash): ?>
    <div class="card fine"><?= esc($flash) ?></div>
  <?php endif; ?>

  <?php if (empty($contacts)): ?>
    <div class="card fine">No contacts yet. Use "Add Contact" to create one.</div>
  <?php endif; ?>

  <div class="grid">
    <?php foreach ($contacts as $c): $cid = $c['id'] ?? ''; ?>
    <div class="card" id="card-<?= esc($cid) ?>">
      <div class="row">
        <div>
          <div class="label"><?= esc($c['name'] ?? '—') ?></div>
          <div class="small"><?= esc($c['email'] ?? '') ?></div>
        </div>
        <div class="value"><?= esc($c['security_question'] ?? '') ?></div>
      </div>

      <div class="row">
        <div class="small">Transactions</div>
        <div class="small"><?= count($c['transactions'] ?? []) ?> items</div>
      </div>

      <div>
        <?php if (!empty($c['transactions'])): foreach ($c['transactions'] as $t): ?>
          <div class="txn">
            <div style="display:flex;justify-content:space-between">
              <div><strong><?= esc($t['currency'] ?? ($config['currency'] ?? 'CAD')) ?> <?= number_format((float)$t['amount'],2) ?></strong></div>
              <div class="small"><?= esc($t['date'] ?? '') ?></div>
            </div>
            <div class="small"><?= esc($t['note'] ?? '') ?></div>
            <form method="post" style="margin-top:6px">
              <input type="hidden" name="action" value="delete_txn">
              <input type="hidden" name="id" value="<?= esc($cid) ?>">
              <input type="hidden" name="txn_id" value="<?= esc($t['id'] ?? '') ?>">
              <button class="btn small btn danger" type="submit">Delete txn</button>
            </form>
          </div>
        <?php endforeach; else: ?>
          <div class="small">No transactions</div>
        <?php endif; ?>
      </div>

      <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
        <button class="btn" onclick="openEdit('<?= esc($cid) ?>')"><i class="fa-solid fa-pen"></i> Edit</button>

        <form method="post" style="display:inline">
          <input type="hidden" name="action" value="delete_contact">
          <input type="hidden" name="id" value="<?= esc($cid) ?>">
          <button class="btn danger" type="submit"><i class="fa-solid fa-trash"></i> Delete</button>
        </form>

        <button class="btn" onclick="openTxn('<?= esc($cid) ?>')"><i class="fa-solid fa-paper-plane"></i> Add Txn</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="footer">Data stored at <code><?= esc($dataFile) ?></code></div>
</div>

<!-- Add/Edit Modal -->
<div id="modal" class="modal" aria-hidden="true">
  <div class="panel">
    <h3 id="modalTitle">Add Contact</h3>
    <form id="contactForm" method="post">
      <input type="hidden" name="action" id="formAction" value="add_contact">
      <input type="hidden" name="id" id="formId" value="">
      <div style="margin:8px 0"><label class="small">Receiver name</label><input class="input" name="name" id="name" required></div>
      <div style="margin:8px 0"><label class="small">Email</label><input class="input" type="email" name="email" id="email"></div>
      <div style="margin:8px 0"><label class="small">Security question</label><input class="input" name="security_question" id="security_question"></div>
      <div style="margin:8px 0"><label class="small">Security answer</label><input class="input" name="security_answer" id="security_answer"></div>
      <div style="display:flex;gap:8px;margin-top:8px">
        <button class="add-btn" type="submit">Save</button>
        <button class="btn" type="button" onclick="closeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Txn Modal -->
<div id="txnModal" class="modal" aria-hidden="true">
  <div class="panel">
    <h3 id="txnTitle">Add Transaction</h3>
    <form id="txnForm" method="post">
      <input type="hidden" name="action" value="add_txn">
      <input type="hidden" name="id" id="txnId" value="">
      <div style="margin:8px 0"><label class="small">Amount</label><input class="input" name="amount" id="txnAmount" required></div>
      <div style="margin:8px 0"><label class="small">Currency</label><input class="input" name="currency" id="txnCurrency" value="<?= esc($config['currency'] ?? 'CAD') ?>"></div>
      <div style="margin:8px 0"><label class="small">Date</label><input class="input" name="date" id="txnDate" value="<?= date('Y-m-d H:i:s') ?>"></div>
      <div style="margin:8px 0"><label class="small">Note</label><textarea name="note" id="txnNote"></textarea></div>
      <div style="display:flex;gap:8px;margin-top:8px">
        <button class="add-btn" type="submit">Add</button>
        <button class="btn" type="button" onclick="closeTxn()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
const contacts = <?= json_encode($contacts, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;

function openAdd(){
  document.getElementById('modalTitle').textContent = 'Add Contact';
  document.getElementById('formAction').value = 'add_contact';
  document.getElementById('formId').value = '';
  ['name','email','security_question','security_answer'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('modal').classList.add('open');
}
function openEdit(id){
  const c = contacts.find(x=>x.id===id);
  if(!c) return alert('Contact not found');
  document.getElementById('modalTitle').textContent = 'Edit Contact';
  document.getElementById('formAction').value = 'edit_contact';
  document.getElementById('formId').value = id;
  document.getElementById('name').value = c.name||'';
  document.getElementById('email').value = c.email||'';
  document.getElementById('security_question').value = c.security_question||'';
  document.getElementById('security_answer').value = c.security_answer||'';
  document.getElementById('modal').classList.add('open');
}
function closeModal(){ document.getElementById('modal').classList.remove('open'); }

function openTxn(id){
  document.getElementById('txnId').value = id;
  document.getElementById('txnAmount').value = '';
  document.getElementById('txnNote').value = '';
  document.getElementById('txnCurrency').value = '<?= esc($config['currency'] ?? 'CAD') ?>';
  document.getElementById('txnDate').value = new Date().toISOString().slice(0,19).replace('T',' ');
  document.getElementById('txnModal').classList.add('open');
}
function closeTxn(){ document.getElementById('txnModal').classList.remove('open'); }

document.getElementById('openAdd').addEventListener('click', openAdd);
</script>
</body>
</html>