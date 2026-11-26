<?php
require dirname(__DIR__, 2) . '/bootstrap.php';
$root = dirname(__DIR__, 2);
$db = VoiceShop\Db\Database::connect(require $root . '/config/env.php');
$schema = $db->prepare('SHOW CREATE TABLE calls'); $schema->execute();
$column = $db->prepare("SELECT COLUMN_TYPE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='call_id'"); $column->execute();
$rows = $db->prepare("SELECT c.call_id,c.shop_id,JSON_VALID(c.payload) AS valid_payload,JSON_VALID(c.extraction) AS valid_extraction,o.id AS order_id,o.status,COUNT(l.id) AS history_rows FROM calls c JOIN orders o ON o.call_id=c.call_id LEFT JOIN order_status_log l ON l.order_id=o.id WHERE c.call_id LIKE ? GROUP BY c.call_id,c.shop_id,c.payload,c.extraction,o.id,o.status ORDER BY c.call_id"); $rows->execute(['SIM-%']);
echo json_encode(['calls_schema' => $schema->fetch(), 'orders_call_id' => $column->fetch(), 'simulation_rows' => $rows->fetchAll()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
