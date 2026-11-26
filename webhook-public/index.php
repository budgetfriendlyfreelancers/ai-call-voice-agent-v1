<?php
declare(strict_types=1);

// Use this file as the built-in server router; never fall through to static files.
$path = explode('?', (string)($_SERVER['REQUEST_URI'] ?? ''), 2)[0];
if ($path !== '/webhook/elevenlabs.php' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    $status = $path === '/webhook/elevenlabs.php' ? 405 : 404;
    if ($status === 405) { header('Allow: POST'); }
    http_response_code($status);
    echo json_encode(['error' => $status === 404 ? 'not_found' : 'method_not_allowed']);
    exit;
}

// The original entry owns raw-body reading, authentication and shared intake.
require dirname(__DIR__) . '/public/webhook/elevenlabs.php';
