<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
require __DIR__ . '/Support/ElevenLabsFixture.php';
require __DIR__ . '/Support/WebhookClient.php';
use VoiceShop\Webhook\{ElevenLabsSignature,ElevenLabsExtraction};
use VoiceShop\Config\{ConfigValidator,FileShopConfigRepository,WebhookSettings};
use VoiceShop\Repository\{CallRepository,OrderRepository};
use VoiceShopTests\{ElevenLabsFixture,WebhookClient};
$root=dirname(__DIR__); $env=require $root.'/config/env.php';
$db=VoiceShop\Db\Database::connect($env); $base=$argv[1]??'http://127.0.0.1:8087';
$settings=new WebhookSettings($env); $secret=$settings->secret();
if ($secret==='') { throw new RuntimeException('Configure local webhook settings first.'); }
$client=new WebhookClient($base,$secret); $agent=ElevenLabsFixture::groceryAgent($env);
$repo=new FileShopConfigRepository($root.'/config/shops',new ConfigValidator()); $c=$repo->load('grocery_store_02');
$prefix='WEBTEST-'.bin2hex(random_bytes(8)); $created=[]; $pass=0; $fail=0;
function test(string $name, callable $fn): void { global $pass,$fail; try { if (!$fn()) throw new RuntimeException('Assertion failed'); ++$pass; echo "PASS $name\n"; } catch(Throwable $e) { ++$fail; echo "FAIL $name: {$e->getMessage()}\n"; } }
function counts(string $id): array { global $db; $s=$db->prepare('SELECT (SELECT COUNT(*) FROM calls WHERE call_id=?) AS calls_count,(SELECT COUNT(*) FROM orders WHERE call_id=?) AS orders_count');$s->execute([$id,$id]);return array_map('intval',$s->fetch()); }
function payload(string $suffix): array { global $prefix,$agent,$created; $id=$prefix.'-'.$suffix; $created[]=$id; return ElevenLabsFixture::make($agent,$id); }
function send(array $p): array { global $client; return $client->raw(json_encode($p,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)); }
function sign(string $raw,int $time,string $secret): string { return 't='.$time.',v0='.hash_hmac('sha256',$time.'.'.$raw,$secret); }
try {
 $v=new ElevenLabsSignature(); $now=time();$raw='{"message":"தமிழ்"}';$random=random_bytes(32);$header=sign($raw,$now,$random);
 test('HMAC verifies exact UTF-8 raw bytes',fn()=>$v->verify($raw,$header,$random,$now));
 test('Whitespace, component order and multiple v0 signatures accepted',fn()=>$v->verify($raw,'v0='.str_repeat('0',64).', '.substr($header,strpos($header,',')+1).', t='.$now,$random,$now));
 test('Empty secret and missing header rejected',fn()=>!$v->verify($raw,$header,'',$now)&&!$v->verify($raw,'',$random,$now));
 test('Tampered body and wrong secret rejected',fn()=>!$v->verify($raw.' ',$header,$random,$now)&&!$v->verify($raw,$header,random_bytes(32),$now));
 test('Stale and future timestamps rejected',fn()=>!$v->verify($raw,sign($raw,$now-1801,$random),$random,$now)&&!$v->verify($raw,sign($raw,$now+1801,$random),$random,$now));
 test('Unsupported version and repeated timestamp rejected',fn()=>!$v->verify($raw,str_replace('v0=','v1=',$header),$random,$now)&&!$v->verify($raw,$header.',t='.$now,$random,$now));
 test('Missing secret controller returns generic 401 without DB',fn()=>(new VoiceShop\Http\ElevenLabsWebhookController($root.'/storage/nonexistent-config-root'))->handle($raw,$header,'POST')===[401,['error'=>'unauthorized']]);
 test('Settings reject malformed map and unknown shop',fn()=>!(new ConfigValidator())->validateWebhookSettings(['SHOP_AGENT_MAP'=>['agent'=>'absent']],$repo->ids())->valid()&&!(new ConfigValidator())->validateWebhookSettings(['SHOP_AGENT_MAP'=>'bad'],$repo->ids())->valid());
 test('Placeholder agent mapping warns and cannot route',fn()=>(new WebhookSettings(['SHOP_AGENT_MAP'=>['REPLACE_AGENT'=>'grocery_store_02']]))->shopFor('REPLACE_AGENT')===null && count((new ConfigValidator())->validateWebhookSettings(['SHOP_AGENT_MAP'=>['REPLACE_AGENT'=>'grocery_store_02']],$repo->ids())->warnings)>=1);
 test('Malformed nested data produces generic rejection',function(){[$code,$body]=send(['type'=>'post_call_transcription','data'=>['agent_id'=>[]]]);return $code===422&&json_decode($body,true)===['error'=>'invalid_payload'];});
 test('Unknown agent acknowledged without database rows',function(){ $p=payload('unknown');$p['data']['agent_id']='unmapped_agent';[$code,$body]=send($p);return $code===200&&json_decode($body,true)===['ok'=>true]&&counts($p['data']['conversation_id'])===['calls_count'=>0,'orders_count'=>0]; });
 test('Audio event skipped without reserving call ID',function(){ $p=payload('audio');$p['type']='post_call_audio';[$code]=send($p);return $code===200&&counts($p['data']['conversation_id'])===['calls_count'=>0,'orders_count'=>0]; });
 test('Signed malformed JSON rejected generically',function()use($client){[$code,$body]=$client->raw('{broken');return $code===422&&json_decode($body,true)===['error'=>'invalid_payload'];});
 test('Signed invalid quantity rolls back audit and order',function(){ $p=payload('invalid-qty');$items=json_decode($p['data']['analysis']['data_collection_results']['items']['value'],true);$items[0]['quantity']=-1;$p['data']['analysis']['data_collection_results']['items']['value']=json_encode($items);[$code,$body]=send($p);return $code===422&&json_decode($body,true)===['error'=>'invalid_payload']&&counts($p['data']['conversation_id'])===['calls_count'=>0,'orders_count'=>0]; });
 test('Retry after corrected extraction can create the rolled-back call',function()use($prefix,$agent){$p=ElevenLabsFixture::make($agent,$prefix.'-invalid-qty');[$code]=send($p);return $code===200&&counts($p['data']['conversation_id'])===['calls_count'=>1,'orders_count'=>1];});
 // Native MySQL JSON canonicalizes object key order; compare JSON values semantically.
 test('Audit preserves full payload, extraction and Tamil transcript',function()use($db){$p=payload('audit');[$code]=send($p);$row=(new CallRepository($db))->byCall($p['data']['conversation_id']);return $code===200&&$row&&json_decode($row['payload'],true)==$p&&json_decode($row['extraction'],true)==$p['data']['analysis']['data_collection_results']&&str_contains($row['transcript'],'அரிசி');});
 test('Names map rice/oil; dal stays flagged free text; supplied money ignored',function()use($db,$repo,$c,$root){$p=payload('names');$items=json_decode($p['data']['analysis']['data_collection_results']['items']['value'],true);$items[0]['price']=0.01;$p['data']['analysis']['data_collection_results']['items']['value']=json_encode($items);[$code]=send($p);$o=(new OrderRepository($db))->byCall($p['data']['conversation_id']);$rows=(new VoiceShop\Repository\OrderItemRepository($db))->forOrder((int)$o['id']);$view=(new VoiceShop\Service\OrderPresenter(new VoiceShop\Language\LanguageResolver($root.'/storage/logs')))->detail($o,$rows,$c);return $code===200&&$rows[0]['sku']==='rice_ponni'&&$rows[2]['sku']==='oil_sunflower'&&(int)$rows[1]['is_unknown_item']===1&&$rows[1]['raw_text']==='dal'&&in_array('unknown_item',json_decode($o['flags_json'],true),true)&&$view['items'][0]['price']===62;});
 test('255-character conversation ID accepted without truncation',function()use($prefix,$agent,&$created){$id=$prefix.str_repeat('x',255-strlen($prefix));$created[]=$id;[$code]=send(ElevenLabsFixture::make($agent,$id));return $code===200&&counts($id)===['calls_count'=>1,'orders_count'=>1];});
 test('256-character conversation ID rejected',function()use($prefix,$agent){$id=$prefix.str_repeat('x',256-strlen($prefix));[$code,$body]=send(ElevenLabsFixture::make($agent,$id));return $code===422&&json_decode($body,true)===['error'=>'invalid_payload'];});
 test('Existing manual order gets one audit without another order',function()use($db,$repo,$c){$p=payload('manual');$x=new ElevenLabsExtraction();$order=$x->order($c,$p['data']['conversation_id'],$x->collection($p['data']));$initial=(new VoiceShop\Service\OrderIntakeService($db,$repo))->ingest($order);[$code]=send($p);$o=(new OrderRepository($db))->byCall($p['data']['conversation_id']);return $code===200&&$initial->order['id']===$o['id']&&counts($p['data']['conversation_id'])===['calls_count'=>1,'orders_count'=>1];});
 test('Concurrent replay creates exactly one call, order and history row',function()use($base,$secret,$db){
   $p=payload('parallel');$raw=json_encode($p,JSON_UNESCAPED_UNICODE);$header=sign($raw,time(),$secret);$multi=curl_multi_init();$handles=[];
   for($i=0;$i<6;$i++){ $ch=curl_init($base.'/webhook/elevenlabs.php');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$raw,CURLOPT_HTTPHEADER=>['Content-Type: application/json','ElevenLabs-Signature: '.$header],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);curl_multi_add_handle($multi,$ch);$handles[]=$ch; }
   do{curl_multi_exec($multi,$running);if($running)curl_multi_select($multi,1.0);}while($running);
   $ok=true;foreach($handles as $ch){$ok=$ok&&curl_getinfo($ch,CURLINFO_HTTP_CODE)===200&&json_decode(curl_multi_getcontent($ch),true)===['ok'=>true];curl_multi_remove_handle($multi,$ch);curl_close($ch);}curl_multi_close($multi);
   $o=(new OrderRepository($db))->byCall($p['data']['conversation_id']);return $ok&&counts($p['data']['conversation_id'])===['calls_count'=>1,'orders_count'=>1]&&count((new VoiceShop\Repository\StatusLogRepository($db))->forOrder((int)$o['id']))===1;
 });
} finally {
 foreach(array_unique($created) as $id){$db->prepare('DELETE FROM calls WHERE call_id=?')->execute([$id]);$db->prepare('DELETE FROM orders WHERE call_id=?')->execute([$id]);}
}
echo "\n$pass PASS, $fail FAIL\n";exit($fail?1:0);
