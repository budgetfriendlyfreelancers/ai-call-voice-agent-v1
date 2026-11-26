<?php
declare(strict_types=1);
// No session, form decoding, CSRF or output before signature verification of the raw bytes.
ini_set('display_errors', '0');
require dirname(__DIR__, 2) . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$raw = file_get_contents('php://input');
[$status, $body] = (new VoiceShop\Http\ElevenLabsWebhookController(dirname(__DIR__, 2)))->handle(
    $raw === false ? '' : $raw, (string)($_SERVER['HTTP_ELEVENLABS_SIGNATURE'] ?? ''), $_SERVER['REQUEST_METHOD'] ?? ''
);
http_response_code($status);
echo json_encode($body, JSON_UNESCAPED_UNICODE);
