<?php

$request = trim($_SERVER['REQUEST_URI'], '/');

if ($request === '' || $request === 'index.php') {
    require __DIR__ . '/../splash.php';
    exit;
}

if (str_starts_with($request, 'admin')) {
    parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
    $_GET = array_merge($_GET, $query);
    require __DIR__ . '/../admin.php';
    exit;
}

// 404 fallback
http_response_code(404);
echo "<h1>404</h1><p>Route not found.</p>";