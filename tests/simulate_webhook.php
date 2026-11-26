<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
require __DIR__ . '/Support/ElevenLabsFixture.php';
require __DIR__ . '/Support/WebhookClient.php';
use VoiceShopTests\{ElevenLabsFixture,WebhookClient};
use VoiceShop\Db\Database;
use VoiceShop\Config\WebhookSettings;
$base = $argv[1] ?? 'http://127.0.0.1:8087';
$env = require dirname(__DIR__) . '/config/env.php';
$secret = (new WebhookSettings($env))->secret();
if ($secret === '') { fwrite(STDERR, "FAIL: configure ELEVENLABS_WEBHOOK_SECRET first (tests/configure_webhook_demo.php for local use).\n"); exit(1); }
$client = new WebhookClient($base, $secret); $db = Database::connect($env);
$agent = ElevenLabsFixture::groceryAgent($env); $prefix = 'SIM-' . bin2hex(random_bytes(8));
$complete = json_encode(ElevenLabsFixture::make($agent, $prefix . '-complete'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$incomplete = json_encode(ElevenLabsFixture::make($agent, $prefix . '-missing', true), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$failures = 0;
foreach ([['Complete order', $complete, $prefix . '-complete', 'AWAITING_MERCHANT_APPROVAL'], ['Missing address', $incomplete, $prefix . '-missing', 'NEEDS_DETAILS'], ['Replay complete unchanged', $complete, $prefix . '-complete', 'AWAITING_MERCHANT_APPROVAL']] as [$name,$raw,$callId,$expected]) {
    [$status,$body] = $client->raw($raw);
    $s = $db->prepare('SELECT (SELECT COUNT(*) FROM calls WHERE call_id=?) AS calls_count, (SELECT COUNT(*) FROM orders WHERE call_id=?) AS orders_count, (SELECT status FROM orders WHERE call_id=?) AS status');
    $s->execute([$callId,$callId,$callId]); $row = $s->fetch();
    $pass = $status === 200 && json_decode($body, true) === ['ok' => true] && (int)$row['calls_count'] === 1 && (int)$row['orders_count'] === 1 && $row['status'] === $expected;
    if (!$pass) { ++$failures; }
    echo "$name: HTTP $status $body\n" . ($pass ? 'PASS' : 'FAIL') . " call_id=$callId calls={$row['calls_count']} orders={$row['orders_count']} status={$row['status']}\n";
}
echo "\n" . (3 - $failures) . " PASS, $failures FAIL. Simulation orders retained for dashboard inspection.\n";
exit($failures ? 1 : 0);
