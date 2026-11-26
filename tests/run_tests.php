<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
use VoiceShop\Config\{ConfigValidator,FileShopConfigRepository,ConfigException};
use VoiceShop\Language\LanguageResolver;
use VoiceShop\Voice\{VoiceResolver,VoiceProviderFactory};
use VoiceShop\Domain\StatusFlow;
use VoiceShop\Service\{MissingFieldsDetector,DemoSeeder,OrderIntakeService,OrderStatusService,OrderEditService,PayloadValidator};
use VoiceShop\Repository\{OrderRepository,OrderItemRepository,StatusLogRepository};
use VoiceShop\Db\{Database,Migrator};
if (PHP_SAPI !== 'cli') { header('Content-Type: text/plain; charset=utf-8'); }
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
$passed = 0; $failed = 0; $skipped = 0;
function check(string $name, callable $test): void {
    global $passed, $failed;
    try { if ($test() === false) { throw new RuntimeException('Assertion returned false'); } ++$passed; echo "PASS $name\n"; }
    catch (Throwable $e) { ++$failed; echo "FAIL $name: {$e->getMessage()}\n"; }
}
function rejected(callable $fn): bool { try { $fn(); return false; } catch (InvalidArgumentException $e) { return true; } }
$root = dirname(__DIR__); $repo = new FileShopConfigRepository($root . '/config/shops', new ConfigValidator());
$a = $repo->load('panipuri_stall_01'); $b = $repo->load('grocery_store_02'); $validator = new ConfigValidator();
$lang = new LanguageResolver($root . '/storage/logs');
check('Both exact seed configs validate', fn() => $validator->validate($a)->valid() && $validator->validate($b)->valid());
check('Recursive comment stripping', fn() => !str_contains(json_encode($a), '_comment'));
check('Broken default block rejected', function () use ($a,$validator) { unset($a['languages']['ta-IN']['closing']); return !$validator->validate($a)->valid(); });
check('Mismatched product key rejected', function () use ($a,$validator) { $a['languages']['ta-IN']['products']['wrong'] = ['display_name' => 'wrong']; return !$validator->validate($a)->valid(); });
check('Bad voice.selected rejected', function () use ($a,$validator) { $a['languages']['ta-IN']['voice']['selected'] = 'other'; return !$validator->validate($a)->valid(); });
check('Malformed voice type returns errors', function () use ($a,$validator) { $a['languages']['ta-IN']['voice'] = 'bad'; return !$validator->validate($a)->valid(); });
check('Invalid status / address subset / price visibility rejected', function () use ($a,$validator) { $a['statuses'][] = 'BAD'; $a['required_fields']['address']['must_have'][] = 'bad'; $a['products'][0]['price_visibility'] = 'bad'; return count($validator->validate($a)->errors) >= 3; });
check('Placeholder voices are warnings only', fn() => $validator->validate($a)->valid() && count($validator->validate($a)->warnings) === 2);
check('Partial English closing and delivery fall back to Tamil', fn() => $lang->resolve($b,'en-IN','closing') === $b['languages']['ta-IN']['closing'] && $lang->resolve($b,'en-IN','delivery_notice') === $b['languages']['ta-IN']['delivery_notice']);
check('Dotted product fallback and English override', fn() => $lang->resolve($b,'en-IN','products.rice_ponni.display_name') === 'Ponni Rice' && $lang->resolve($b,'en-IN','products.chilli_powder.display_name') === $b['languages']['ta-IN']['products']['chilli_powder']['display_name']);
check('Unknown language uses default', fn() => $lang->resolve($b,'zz-ZZ','closing') === $b['languages']['ta-IN']['closing']);
check('Double miss returns null and writes log', fn() => $lang->resolve($b,'en-IN','not.present') === null && is_file($root . '/storage/logs/language.log'));
check('Voice placeholder fallback gender and source', function () use ($b) { $v = (new VoiceResolver())->resolve($b,'en-IN'); return $v['fallback'] && $v['gender'] === 'female' && $v['language'] === 'ta-IN' && $v['placeholder']; });
check('Voice config-only gender switch', function () use ($b) { $b['languages']['en-IN']['voice']['selected'] = 'female'; $b['languages']['en-IN']['voice']['options']['female']['voice_id'] = 'test-real-id'; $v = (new VoiceResolver())->resolve($b,'en-IN'); return $v['voice_id'] === 'test-real-id' && !$v['fallback']; });
check('Provider stub makes no call', function () { try { VoiceProviderFactory::make('elevenlabs')->synthesize('Hi','x','en-IN'); return false; } catch (LogicException $e) { return true; } });
check('Unknown provider rejected', fn() => rejected(fn() => VoiceProviderFactory::make('unknown')));
check('Allowed and disallowed StatusFlow', fn() => (new StatusFlow())->allows($a,'AWAITING_MERCHANT_APPROVAL','CONFIRMED') && !(new StatusFlow())->allows($a,'NEEDS_DETAILS','COMPLETED'));
check('Puri required fields, phone optional, merge deduped', function () use ($a) { $m = (new MissingFieldsDetector())->detect($a,['customer'=>[], 'missing_fields'=>['customer_name']]); return $m === ['customer_name','address.street','address.area']; });
check('Grocery required phone and pincode', function () use ($b) { $p = DemoSeeder::sample('grocery_store_02'); $p['customer']['phone']=''; unset($p['customer']['address']['pincode']); return (new MissingFieldsDetector())->detect($b,$p) === ['phone','address.pincode']; });
check('Complete payload missing fields empty', fn() => (new MissingFieldsDetector())->detect($a,DemoSeeder::sample('panipuri_stall_01')) === []);
check('Invalid quantity, unit and max items rejected', function () use ($a) { $p=json_decode(json_encode(DemoSeeder::sample('panipuri_stall_01')),true); $v=new PayloadValidator(); $p['items'][0]['qty']=-1; if (!rejected(fn()=>$v->normalize($a,$p))) return false; $p['items'][0]['qty']=1; $p['items'][0]['unit']='kg'; if (!rejected(fn()=>$v->normalize($a,$p))) return false; $p['items']=array_fill(0,21,$p['items'][0]); return rejected(fn()=>$v->normalize($a,$p)); });
check('Third shop discovered with no engine edits', function () use ($a,$root) {
    $dir=$root.'/storage/test-config-'.bin2hex(random_bytes(4)); mkdir($dir,0775,true); $a['shop_id']='third_shop';
    try { file_put_contents($dir.'/third_shop.json',json_encode($a,JSON_UNESCAPED_UNICODE)); $r=new FileShopConfigRepository($dir,new ConfigValidator()); return $r->ids()===['third_shop'] && $r->load('third_shop')['shop_name']===$a['shop_name']; }
    finally { unlink($dir.'/third_shop.json'); rmdir($dir); }
});
// Integration is explicitly opt-in and ONLY uses a database whose name ends in _test.
// Browser: POST integration=1 + CSRF to this page. CLI: --integration.
$integration = PHP_SAPI === 'cli' ? in_array('--integration', $argv, true) : false;
if (PHP_SAPI !== 'cli') {
    session_start();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { \VoiceShop\Http\Csrf::verify($_POST['csrf'] ?? null); $integration = isset($_POST['integration']); }
    if (!$integration) { echo "\nFor browser DB tests, use tests/integration.php.\n"; }
}
if ($integration) {
    try {
        $env = require $root . '/config/env.php'; $env['db_name'] = 'voiceshop_test';
        $db = Database::connect($env); (new Migrator())->run($db,$root.'/database/schema.sql');
        $intake = new OrderIntakeService($db,$repo); $orders = new OrderRepository($db); $items = new OrderItemRepository($db); $history = new StatusLogRepository($db);
        $prefix='TEST-'.bin2hex(random_bytes(8)); $ids=[];
        try {
            $p=json_decode(json_encode(DemoSeeder::sample('panipuri_stall_01',false,$prefix.'-1')),true);
            $r=$intake->ingest($p); $id=(int)($r->order['id']??0); if ($id) $ids[]=$id;
            check('DB complete intake and initial history', fn()=> $r->code===201 && $r->order['status']==='AWAITING_MERCHANT_APPROVAL' && count($history->forOrder($id))===1);
            check('DB call_id dedupe through intake', function () use ($intake,$p,$id) { $r=$intake->ingest($p); return $r->code===200 && (int)$r->order['id']===$id && str_contains($r->message,'duplicate'); });
            check('DB UNIQUE constraint rejects duplicate directly', function () use ($db,$p) { try { $db->prepare('UPDATE orders SET call_id=call_id WHERE call_id=?')->execute([$p['call_id']]); $db->prepare('INSERT INTO orders (shop_id,call_id,language,status,customer_name,customer_phone,address_json,delivery_choice,payment_choice,requested_time,missing_fields_json,flags_json,order_json,is_demo,created_at,updated_at) SELECT shop_id,call_id,language,status,customer_name,customer_phone,address_json,delivery_choice,payment_choice,requested_time,missing_fields_json,flags_json,order_json,is_demo,created_at,updated_at FROM orders WHERE call_id=?')->execute([$p['call_id']]); return false; } catch (PDOException $e) { return ($e->errorInfo[1]??0)===1062; } });
            $status=new OrderStatusService($db,$repo);
            check('DB non-owner approval rejected', fn()=>rejected(fn()=>$status->transition($id,'CONFIRMED',false)));
            check('DB disallowed transition rejected', fn()=>rejected(fn()=>$status->transition($id,'COMPLETED',true)));
            check('DB owner approval and history', function () use ($status,$id,$orders,$history) { $status->transition($id,'CONFIRMED',true); return $orders->find($id)['status']==='CONFIRMED' && count($history->forOrder($id))===2; });
            $p=json_decode(json_encode(DemoSeeder::sample('grocery_store_02',true,$prefix.'-2')),true); $p['items'][]=['sku'=>'unknown','qty'=>1,'unit'=>'item','options'=>[],'raw_text'=>'Free text']; $r=$intake->ingest($p); $id2=(int)($r->order['id']??0); if ($id2) $ids[]=$id2;
            check('DB missing fields and unknown free text flag', fn()=> $r->code===201 && $r->order['status']==='NEEDS_DETAILS' && in_array('unknown_item',json_decode($r->order['flags_json'],true),true) && (int)$items->forOrder($id2)[1]['is_unknown_item']===1);
            check('DB incomplete approval queue rejected', fn()=>rejected(fn()=>$status->transition($id2,'AWAITING_MERCHANT_APPROVAL',true)));
            $edit=json_decode(json_encode(DemoSeeder::sample('grocery_store_02')),true); $edit['items']=[]; foreach ($items->forOrder($id2) as $i) { $edit['items'][$i['id']]=['qty'=>$i['qty'],'unit'=>$i['unit']]; }
            check('DB edit fills missing without forcing status', function () use ($db,$repo,$id2,$edit,$orders) { (new OrderEditService($db,$repo))->save($id2,$edit); $o=$orders->find($id2); return $o['status']==='NEEDS_DETAILS' && json_decode($o['missing_fields_json'],true)===[]; });
            check('DB explicit queue transition after edit', function () use ($status,$id2,$orders) { $status->transition($id2,'AWAITING_MERCHANT_APPROVAL',true); return $orders->find($id2)['status']==='AWAITING_MERCHANT_APPROVAL'; });
            check('DB invalid edit is atomic', function () use ($db,$repo,$id2,$edit,$orders) { $before=$orders->find($id2); $edit['customer']['name']='Changed'; $key=array_key_first($edit['items']); $edit['items'][$key]['unit']='BAD'; return rejected(fn()=>(new OrderEditService($db,$repo))->save($id2,$edit)) && $orders->find($id2)['customer_name']===$before['customer_name']; });
            check('DB raw payload prices ignored by owner display', function () use ($intake,$prefix,$items,$a,$lang,&$ids) { $p=json_decode(json_encode(DemoSeeder::sample('panipuri_stall_01',false,$prefix.'-money')),true); $p['items'][0]['price']=0.01; $r=$intake->ingest($p); $ids[]=(int)$r->order['id']; $view=(new \VoiceShop\Service\OrderPresenter($lang))->detail($r->order,$items->forOrder((int)$r->order['id']),$a); return $r->code===201 && $view['items'][0]['price']===40 && $view['total']===80.0; });
        } finally {
            foreach ($ids as $id) { $db->prepare('DELETE FROM orders WHERE id=? AND call_id LIKE ?')->execute([$id,$prefix.'%']); }
        }
    } catch (Throwable $e) { ++$failed; echo 'FAIL DB integration: '.$e->getMessage()."\n"; }
} else { ++$skipped; echo "NOT RUN DB integration (use --integration after creating voiceshop_test).\n"; }
echo "\n$passed PASS, $failed FAIL, $skipped NOT RUN groups\n";
if (PHP_SAPI==='cli') { exit($failed ? 1 : 0); }
