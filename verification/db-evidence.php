<?php
require dirname(__DIR__) . '/bootstrap.php';
$db=VoiceShop\Db\Database::connect(require dirname(__DIR__).'/config/env.php');
$result=['queries'=>[], 'orders'=>[], 'counts'=>[]];
foreach(['apache','builtin'] as $server){
 $log=json_decode(file_get_contents(__DIR__.'/logs/'.$server.'-mobile.json'),true);
 foreach($log['orders'] as $type=>$o){
  $sql='SELECT id,call_id,status,customer_name,customer_phone,missing_fields_json FROM orders WHERE id=? AND call_id=?';
  $s=$db->prepare($sql);$s->execute([$o['id'],$o['call_id']]);$row=$s->fetch();
  $historySql='SELECT from_status,to_status,changed_by,changed_at FROM order_status_log WHERE order_id=? ORDER BY id';
  $h=$db->prepare($historySql);$h->execute([$o['id']]);
  $result['queries'][]=['sql'=>$sql,'parameters'=>[$o['id'],$o['call_id']]];
  $result['queries'][]=['sql'=>$historySql,'parameters'=>[$o['id']]];
  $result['orders'][$server][$type]=['row'=>$row,'history'=>$h->fetchAll()];
 }
}
foreach(['shops','orders','order_items','order_status_log'] as $table){$s=$db->prepare('SELECT COUNT(*) FROM '.$table);$s->execute();$result['counts'][$table]=(int)$s->fetchColumn();}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
