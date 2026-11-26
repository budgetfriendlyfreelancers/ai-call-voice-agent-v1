<?php
header('Content-Type: application/json; charset=utf-8');
$env = require dirname(__DIR__) . '/config/env.php';
echo json_encode([
 'php_version'=>PHP_VERSION,'sapi'=>PHP_SAPI,'server'=>$_SERVER['SERVER_SOFTWARE']??'',
 'pdo_mysql'=>extension_loaded('pdo_mysql'),'mbstring'=>extension_loaded('mbstring'),
 'rewrite_module'=>function_exists('apache_get_modules') ? in_array('mod_rewrite',apache_get_modules(),true) : null,
 'session_cookie_path'=>ini_get('session.cookie_path'),'session_save_path'=>ini_get('session.save_path'),
 'php_error_log'=>ini_get('error_log'),'db_host'=>$env['db_host'],'db_port'=>$env['db_port'],
 'db_name'=>$env['db_name'],'owner_pin_matches_demo'=>hash_equals((string)$env['owner_pin'],'2468')
], JSON_PRETTY_PRINT);
