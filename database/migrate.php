<?php
// Optional CLI equivalent of the CSRF-protected Setup action.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__);
$db = VoiceShop\Db\Database::connect(require $root . '/config/env.php');
(new VoiceShop\Db\Migrator())->runAll($db, $root);
echo "Schema and migrations applied.\n";
