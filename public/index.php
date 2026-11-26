<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
(new VoiceShop\Http\App(dirname(__DIR__)))->run(is_string($_GET['page'] ?? null) ? $_GET['page'] : 'orders');
