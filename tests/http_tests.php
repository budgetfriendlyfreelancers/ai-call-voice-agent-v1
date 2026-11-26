<?php
// Optional CLI smoke checks against the local demo server, no packages needed.
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
use VoiceShop\Db\Database;
use VoiceShop\Repository\OrderRepository;
use VoiceShop\Service\DemoSeeder;
require __DIR__ . '/Support/ElevenLabsFixture.php';
require __DIR__ . '/Support/WebhookClient.php';
use VoiceShopTests\{ElevenLabsFixture,WebhookClient};
$base = $argv[1] ?? 'http://127.0.0.1:8086';
if (!preg_match('~^http://(127\.0\.0\.1|localhost)(:\d+)?(/[a-zA-Z0-9_/-]*)?$~D',$base)) { throw new RuntimeException('Localhost URL only.'); }
$root=dirname(__DIR__); $env=require $root.'/config/env.php'; $db=Database::connect($env);
$cookie=tempnam(sys_get_temp_dir(),'voiceshop-cookie-'); $prefix='HTTP-'.bin2hex(random_bytes(8)); $ids=[]; $pass=0; $fail=0;
function request(string $path, ?array $post=null): array {
    global $base,$cookie;
    $ch=curl_init($base.'/'.$path); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15]);
    if ($post!==null) { curl_setopt($ch,CURLOPT_POST,true); curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($post)); }
    $body=curl_exec($ch); if ($body===false) { throw new RuntimeException(curl_error($ch)); }
    $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); return [$code,$body];
}
function token(string $body): string { preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m); return $m[1]??throw new RuntimeException('Missing CSRF token'); }
function verify(string $label,bool $ok): void { global $pass,$fail; if ($ok) { ++$pass; echo "PASS $label\n"; } else { ++$fail; echo "FAIL $label\n"; } }
try {
    [$code,$body]=request('setup.php'); $csrf=token($body);
    [$code,$body]=request('setup.php',['csrf'=>$csrf]); verify('Setup creates tables and four seeds',$code===200 && str_contains($body,'DEMO-0004'));
    [$code,$body]=request('setup.php',['csrf'=>$csrf]); verify('Repeated setup dedupes seed orders',$code===200 && str_contains($body,'duplicate - already imported'));
    [$code,$body]=request('index.php'); verify('Dashboard sections, demo badge and Tamil UTF-8',$code===200 && str_contains($body,'PROTOTYPE DEMO') && str_contains(html_entity_decode($body,ENT_QUOTES,'UTF-8'),"Today's Orders") && str_contains($body,'Needs Details') && str_contains($body,'Awaiting Approval') && str_contains($body,'டெமோ'));
    [$code,$body]=request('import.php'); verify('Import page banner and two JSON samples',$code===200 && str_contains($body,'FUTURE WEBHOOK ENTRY POINT') && str_contains($body,'data-sample="1"'));
    $p=DemoSeeder::sample('panipuri_stall_01',false,$prefix.'-1');
    [$code,$body]=request('import.php',['csrf'=>$csrf,'payload'=>json_encode($p,JSON_UNESCAPED_UNICODE)]); $o=(new OrderRepository($db))->byCall($p['call_id']); if ($o) $ids[]=(int)$o['id']; verify('Complete HTTP import awaits approval',$code===201 && $o['status']==='AWAITING_MERCHANT_APPROVAL'); $id=(int)$o['id'];
    [$code,$body]=request('import.php',['csrf'=>$csrf,'payload'=>json_encode($p)]); verify('HTTP duplicate message returns existing order',$code===200 && str_contains($body,'duplicate - already imported'));
    [$code,$body]=request('index.php?page=detail&id='.$id); verify('Approve disabled without owner mode',$code===200 && str_contains($body,'disabled>Approve') && str_contains($body,'Enable Owner mode'));
    [$code,$body]=request('index.php?page=detail&id='.$id,['csrf'=>$csrf,'action'=>'status','status'=>'CONFIRMED']); verify('Forged approval blocked server-side',$code===422 && str_contains($body,'Owner mode is required'));
    [$code,$body]=request('index.php?page=owner',['csrf'=>$csrf,'pin'=>$env['owner_pin']]); verify('Owner PIN enables session',$code===302);
    [$code,$body]=request('index.php?page=detail&id='.$id,['csrf'=>$csrf,'action'=>'status','status'=>'CONFIRMED']); verify('Owner HTTP approval succeeds',$code===302 && (new OrderRepository($db))->find($id)['status']==='CONFIRMED');
    [$code,$body]=request('index.php?page=detail&id='.$id,['csrf'=>$csrf,'action'=>'status','status'=>'COMPLETED']); verify('Disallowed HTTP transition rejected',$code===422 && str_contains($body,'Disallowed'));
    $p=DemoSeeder::sample('grocery_store_02',true,$prefix.'-2');
    [$code,$body]=request('import.php',['csrf'=>$csrf,'payload'=>json_encode($p)]); $o=(new OrderRepository($db))->byCall($p['call_id']); if ($o) $ids[]=(int)$o['id']; $id2=(int)$o['id']; verify('Incomplete HTTP import needs details',$code===201 && $o['status']==='NEEDS_DETAILS');
    [$code,$body]=request('index.php?page=detail&id='.$id2); verify('Missing inputs highlighted and preview fallback shown',$code===200 && str_contains($body,'class="missing"') && str_contains($body,'<strong>Missing</strong>') && str_contains($body,'fallback · ta-IN'));
    $edit=json_decode(json_encode(DemoSeeder::sample('grocery_store_02')),true); $edit['csrf']=$csrf; $edit['action']='save'; $edit['items']=[]; foreach ((new \VoiceShop\Repository\OrderItemRepository($db))->forOrder($id2) as $i) { $edit['items'][$i['id']]=['qty'=>$i['qty'],'unit'=>$i['unit']]; }
    [$code,$body]=request('index.php?page=detail&id='.$id2,$edit); [$code,$body]=request('index.php?page=detail&id='.$id2); verify('Save offers explicit approval queue transition',$code===200 && str_contains($body,'Details are complete.') && (new OrderRepository($db))->find($id2)['status']==='NEEDS_DETAILS');
    [$code,$body]=request('import.php',['csrf'=>'invalid','payload'=>json_encode($p)]); verify('CSRF mismatch rejected',$code===422 && str_contains($body,'token expired or invalid'));
    [$code,$body]=request('import.php',['csrf'=>$csrf,'payload'=>'{broken']); verify('Malformed JSON produces clear error',$code===422 && str_contains($body,'Syntax error'));
    [$code,$body]=request('index.php?page=health'); verify('Health has English fallback and unfilled voice warnings',$code===200 && str_contains($body,'en-IN closing:') && str_contains($body,'fallback · ta-IN') && str_contains($body,'unfilled voice_id'));
    $bad=$root.'/config/shops/http_broken_test.json';
    if (file_exists($bad)) { throw new RuntimeException('Temporary config already exists; refusing to overwrite.'); }
    try {
        $c=json_decode(file_get_contents($root.'/config/shops/panipuri_stall_01.json'),true); $c['shop_id']='http_broken_test'; $c['languages']['ta-IN']['products']['bad_key']=['display_name'=>'bad']; file_put_contents($bad,json_encode($c));
        [$code,$body]=request('index.php?page=health'); verify('Broken config shows error list without crash',$code===200 && str_contains($body,'unknown product key bad_key'));
    } finally { if (is_file($bad)) unlink($bad); }
    [$code,$body]=request('index.php?shop=grocery_store_02'); verify('Shop switcher filters orders',$code===200 && !str_contains($body,'<p>Anna Masala Puri Stall</p>'));
    $client = new WebhookClient($base, (new \VoiceShop\Config\WebhookSettings($env))->secret());
    $agent = ElevenLabsFixture::groceryAgent($env);
    $raw = json_encode(ElevenLabsFixture::make($agent, $prefix.'-webhook'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    [$missingCode,$missingBody] = $client->raw($raw, '');
    [$badCode,$badBody] = $client->raw($raw, 't='.time().',v0='.str_repeat('0',64));
    verify('Webhook wrong and missing signatures return generic 401', $missingCode===401 && $badCode===401 && $missingBody===$badBody && json_decode($badBody,true)===['error'=>'unauthorized']);
    [$code,$body] = $client->raw($raw);
    $o=(new OrderRepository($db))->byCall($prefix.'-webhook'); if ($o) $ids[]=(int)$o['id'];
    $call=(new \VoiceShop\Repository\CallRepository($db))->byCall($prefix.'-webhook');
    verify('Webhook correct signature creates audit and draft order', $code===200 && json_decode($body,true)===['ok'=>true] && $call && $o && $o['status']==='AWAITING_MERCHANT_APPROVAL');
    [$code,$body] = $client->raw($raw);
    $count=$db->prepare('SELECT (SELECT COUNT(*) FROM calls WHERE call_id=?) AS calls_count,(SELECT COUNT(*) FROM orders WHERE call_id=?) AS orders_count'); $count->execute([$prefix.'-webhook',$prefix.'-webhook']); $counts=$count->fetch();
    verify('Webhook replay returns 200 without duplicate call or order', $code===200 && (int)$counts['calls_count']===1 && (int)$counts['orders_count']===1);
    $rawMissing=json_encode(ElevenLabsFixture::make($agent,$prefix.'-webhook-missing',true),JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    [$code,$body]=$client->raw($rawMissing); $o=(new OrderRepository($db))->byCall($prefix.'-webhook-missing'); if ($o) $ids[]=(int)$o['id'];
    verify('Webhook missing address creates NEEDS_DETAILS', $code===200 && $o && $o['status']==='NEEDS_DETAILS' && in_array('address.street',json_decode($o['missing_fields_json'],true),true));
    $isolatedBase = $argv[2] ?? 'http://127.0.0.1:8088';
    $isolatedClient = new WebhookClient($isolatedBase, (new \VoiceShop\Config\WebhookSettings($env))->secret());
    $blocked = ['/', '/index.php', '/index.php?page=health', '/index.php?page=owner',
        '/index.php?page=detail&id=1', '/index.php?shop=grocery_store_02', '/import.php', '/setup.php',
        '/assets/css/app.css', '/assets/js/import.js', '/public/index.php', '/config/env.php',
        '/../public/index.php', '/%2e%2e/public/index.php', '/webhook/elevenlabs.php/extra',
        '/webhook/%65levenlabs.php', '//webhook/elevenlabs.php', '/unknown'];
    foreach ($blocked as $path) {
        foreach (['GET', 'POST'] as $method) {
            $ch=curl_init($isolatedBase.$path);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_TIMEOUT=>15,CURLOPT_PATH_AS_IS=>true,CURLOPT_CUSTOMREQUEST=>$method]);
            $response=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
            verify("Isolated $method $path returns generic 404", $status===404 && json_decode((string)$response,true)===['error'=>'not_found']);
        }
    }
    $ch=curl_init($isolatedBase.'/webhook/elevenlabs.php');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
    $response=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    verify('Isolated webhook GET returns 405', $status===405 && json_decode((string)$response,true)===['error'=>'method_not_allowed']);
    $isolatedRaw=json_encode(ElevenLabsFixture::make($agent,$prefix.'-isolated'),JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    [$code,$body]=$isolatedClient->raw($isolatedRaw,'');
    verify('Isolated missing signature returns 401', $code===401 && json_decode($body,true)===['error'=>'unauthorized']);
    [$code,$body]=$isolatedClient->raw($isolatedRaw,'t='.time().',v0='.str_repeat('0',64));
    verify('Isolated bad signature returns 401', $code===401 && json_decode($body,true)===['error'=>'unauthorized']);
    [$code,$body]=$isolatedClient->raw($isolatedRaw);
    $o=(new OrderRepository($db))->byCall($prefix.'-isolated');
    $call=(new \VoiceShop\Repository\CallRepository($db))->byCall($prefix.'-isolated');
    verify('Isolated signed webhook returns 200 with audit and draft order', $code===200 && json_decode($body,true)===['ok'=>true] && $call && $o && $o['status']==='AWAITING_MERCHANT_APPROVAL');
} finally {
    $db->prepare('DELETE FROM calls WHERE call_id=?')->execute([$prefix.'-isolated']);
    $db->prepare('DELETE FROM orders WHERE call_id=?')->execute([$prefix.'-isolated']);
    $db->prepare('DELETE FROM calls WHERE call_id IN (?,?)')->execute([$prefix.'-webhook',$prefix.'-webhook-missing']);
    foreach ($ids as $id) { $db->prepare('DELETE FROM orders WHERE id=? AND call_id LIKE ?')->execute([$id,$prefix.'%']); }
    unlink($cookie);
}
echo "\n$pass PASS, $fail FAIL\n"; exit($fail?1:0);
