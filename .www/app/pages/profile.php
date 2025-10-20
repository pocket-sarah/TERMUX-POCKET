<?php
// Robust config locator. Returns loaded $config array or dies with helpful diagnostics.
// Usage: include this at top of any script that needs config.

function load_config_file(): array {
    $tried = [];

    // helper to test a candidate path
    $test = function(string $path) use (&$tried) {
        $real = $path === '' ? '' : @realpath($path);
        $tried[] = $real ?: $path;
        if ($real && is_file($real) && is_readable($real)) {
            return $real;
        }
        return false;
    };

    // 1) explicit env override
    $env = getenv('CONFIG_PATH');
    if ($env) {
        if ($found = $test($env)) {
            return require $found;
        }
    }

    // 2) if WEBROOT env set, check WEBROOT/config/config.php and WEBROOT/config.php
    $webroot = getenv('WEBROOT') ?: (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '');
    if ($webroot) {
        if ($found = $test($webroot . '/config/config.php')) return require $found;
        if ($found = $test($webroot . '/config.php')) return require $found;
    }

    // 3) check common locations relative to script file (__DIR__) and cwd
    $starts = [
        __DIR__,                    // current file dir
        getcwd() ?: '',             // current working dir
        dirname(__DIR__),           // parent
        dirname(dirname(__DIR__)),  // parent parent
    ];

    // also include home and root as fallback
    if (!empty(getenv('HOME'))) $starts[] = getenv('HOME');
    $starts[] = '/';

    // for each start, walk up N levels and test patterns
    $maxUp = 8;
    foreach ($starts as $s) {
        $s = $s ?: '';
        $cur = $s;
        for ($i = 0; $i <= $maxUp && $cur !== DIRECTORY_SEPARATOR; $i++) {
            // Patterns to test at this level
            $candidates = [
                $cur . '/.www/config/config.php',
                $cur . '/.www/config.php',
                $cur . '/config/config.php',
                $cur . '/config.php'
            ];
            foreach ($candidates as $cand) {
                if ($found = $test($cand)) return require $found;
            }
            // move up
            $parent = dirname($cur);
            if ($parent === $cur) break;
            $cur = $parent;
        }
    }

    // 4) try DOCUMENT_ROOT relative checks if available
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $dr = $_SERVER['DOCUMENT_ROOT'];
        if ($found = $test($dr . '/config/config.php')) return require $found;
        if ($found = $test($dr . '/../config/config.php')) return require $found; // sibling
    }

    // 5) last-ditch: attempt to find under /data (Termux) quick search shallow (non-recursive)
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

    // Not found. Prepare diagnostic and abort.
    $dbg = "<h2>Config file not found</h2>\n";
    $dbg .= "<p>Looked for <code>config/config.php</code> in multiple locations. Set <code>CONFIG_PATH</code> or <code>WEBROOT</code> env to the correct path.</p>\n";
    $dbg .= "<pre>Tried paths:\n" . implode("\n", array_unique($tried)) . "\n</pre>\n";
    // If request from browser, show HTML. Otherwise plain text.
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: text/html; charset=utf-8', true, 500);
        echo $dbg;
    } else {
        fwrite(STDERR, strip_tags($dbg) . PHP_EOL);
    }
    exit(1);
}

// load config
$config = load_config_file();

// now safe to use $config
$profile = [
    'name'         => $config['sendername'] ?? 'N/A',
    'email'        => $config['support_email'] ?? 'support@example.com',
    'phone'        => $config['phone'] ?? '+1 (780) 473-4567',
    'address'      => $config['address'] ?? '11346 110a Ave NW, Edmonton, AB',
    'work_email'   => $config['work_email'] ?? 'support@atco.ca',
    'work_phone'   => $config['work_phone'] ?? '+1 (780) 987-6543',
    'work_ext'     => $config['work_ext'] ?? '245',
    'work_address' => $config['work_address'] ?? '456 Corporate Ave, Edmonton, AB',
    'lastLogin'    => $config['last_login'] ?? date("Y-m-d H:i:s"),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Profile</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --primary: #1d123c;
    --secondary: #c5e600;
    --background: #2a1852;
    --text: #ffffff;
}
body { font-family: 'Poppins', sans-serif; background: var(--background); color: var(--text); margin: 0; padding: 0; }
.profile-info { background: var(--primary); padding: 16px; margin: 12px; border-radius: 10px; font-size: 0.85rem; }
.profile-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.08); }
.profile-row:last-child { border-bottom: none; }
.profile-row .label { font-weight: 600; color: var(--secondary); flex: 1; font-size: 0.85rem; }
.profile-row .value { flex: 1; text-align: right; color: var(--text); font-size: 0.75rem; opacity: 0.9; word-break: break-word; }
.profile-row .value.email { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.section-divider { margin: 10px 0; border-top: 1px solid rgba(255,255,255,0.2); }
.menu-section { margin-top: 16px; }
.section-title { font-size: 0.8rem; font-weight: 600; color: var(--secondary); padding: 8px 15px; text-transform: uppercase; letter-spacing: .5px; }
.menu-item { background: var(--primary); border-bottom: 1px solid rgba(255,255,255,0.1); padding: 14px 15px; display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem; color: var(--text); cursor: pointer; transition: background 0.2s; }
.menu-item i { margin-right: 8px; color: var(--secondary); font-size: 0.9rem; }
.menu-item:hover { background: #36246a; }
.menu-item span { display: flex; align-items: center; }
</style>
</head>
<body>

<div class="profile-info">
    <div class="profile-row"><div class="label">Full Name</div><div class="value"><?= htmlspecialchars($profile['name']) ?></div></div>
    <div class="profile-row"><div class="label">Email</div><div class="value email"><?= htmlspecialchars($profile['email']) ?></div></div>
    <div class="profile-row"><div class="label">Phone</div><div class="value"><?= htmlspecialchars($profile['phone']) ?></div></div>
    <div class="profile-row"><div class="label">Address</div><div class="value"><?= htmlspecialchars($profile['address']) ?></div></div>
    <div class="section-divider"></div>
    <div class="profile-row"><div class="label">Work Email</div><div class="value email"><?= htmlspecialchars($profile['work_email']) ?></div></div>
    <div class="profile-row"><div class="label">Work Phone</div><div class="value"><?= htmlspecialchars($profile['work_phone']) ?> Ext <?= htmlspecialchars($profile['work_ext']) ?></div></div>
    <div class="profile-row"><div class="label">Work Address</div><div class="value"><?= htmlspecialchars($profile['work_address']) ?></div></div>
    <div class="section-divider"></div>
    <div class="profile-row"><div class="label">Last Login</div><div class="value"><?= htmlspecialchars($profile['lastLogin']) ?></div></div>
</div>

<div class="menu-section">
    <div class="section-title">Settings & Alerts</div>
    <div class="menu-item"><span><i class="fa-solid fa-user-shield"></i> Profile & Security</span><span>&#8250;</span></div>
    <div class="menu-item"><span><i class="fa-solid fa-bell"></i> Manage Alerts</span><span>&#8250;</span></div>
    <div class="section-title">Additional Links</div>
    <div class="menu-item"><span><i class="fa-solid fa-desktop"></i> Desktop Version</span><span>&#8250;</span></div>
    <div class="menu-item"><span><i class="fa-solid fa-file-contract"></i> Mobile Terms & Conditions</span><span>&#8250;</span></div>
    <div class="menu-item" onclick="window.location.href='dev.php'"><span><i class="fa-solid fa-code"></i> Developer Mode</span><span>&#8250;</span></div>
    <div class="section-title">Mobile Features</div>
    <div class="menu-item"><span><i class="fa-solid fa-link"></i> Outside Accounts</span><span>&#8250;</span></div>
    <div class="section-title">Help & Support</div>
    <div class="menu-item"><span><i class="fa-solid fa-circle-info"></i> About</span><span>&#8250;</span></div>
    <div class="menu-item"><span><i class="fa-solid fa-envelope-open-text"></i> Contact Us / FAQs</span><span>&#8250;</span></div>
</div>

</body>
</html>