<?php
header("Content-Type: application/json");

$logDir = __DIR__ . '/../.logs';
$dataDir = __DIR__ . '/../.data';

if(!is_dir($logDir)) mkdir($logDir, 0777, true);
if(!is_dir($dataDir)) mkdir($dataDir, 0777, true);

$response = [
    "status" => "active",
    "system" => "Render PHP Panel",
    "timestamp" => time(),
    "memory" => round(memory_get_usage()/1024/1024, 2) . "MB",
    "php_version" => phpversion(),
    "allowed" => true
];

file_put_contents("$logDir/hackers.log", json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);

echo json_encode($response, JSON_PRETTY_PRINT);