<?php
require dirname(__DIR__) . '/bootstrap.php';
session_start();
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="en"><meta charset="utf-8"><title>Prototype demo integration tests</title><h1>PROTOTYPE DEMO · Integration tests</h1><p>Create voiceshop_test in phpMyAdmin first. Tests use credentials from config/env.php, create tables in voiceshop_test and remove only their own generated orders.</p><form action="run_tests.php" method="post"><input type="hidden" name="csrf" value="<?= VoiceShop\Http\View::e(VoiceShop\Http\Csrf::token()) ?>"><button name="integration" value="1">Run isolated MySQL tests</button></form></html>
