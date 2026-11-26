<?php
require dirname(__DIR__) . '/bootstrap.php';
use VoiceShop\Config\{ConfigValidator,FileShopConfigRepository};
use VoiceShop\Domain\StatusFlow;
use VoiceShop\Voice\VoiceResolver;
use VoiceShop\Db\Database;
$root=dirname(__DIR__); $repo=new FileShopConfigRepository($root.'/config/shops',new ConfigValidator());
$db=Database::connect(require $root.'/config/env.php'); $result=[];
foreach($repo->ids() as $id){
 $c=$repo->load($id); $v=(new ConfigValidator())->validate($c); $default=$c['languages'][$c['default_language']]; $skus=array_column($c['products'],'sku');
 $checks=[];
 $required=['shop_id','shop_name','shop_type','inbound_number','default_language','enabled_languages','voice_provider','languages','products','required_fields','delivery','payment','whatsapp_enquiry_number','statuses','status_flow','rules'];
 $checks['Required top-level keys']=!array_diff($required,array_keys($c));
 $checks['Enabled languages have blocks']=!array_diff($c['enabled_languages'],array_keys($c['languages']));
 $checks['Default language is enabled']=in_array($c['default_language'],$c['enabled_languages'],true);
 $checks['Default language complete']=!array_diff(['voice','persona','greeting','closing','delivery_notice','agent_may_say_after_order','products','dashboard_customer_text'],array_keys($default)) && !array_diff(['order_received','order_confirmed','out_for_delivery'],array_keys($default['dashboard_customer_text']));
 $checks['Language product keys match SKUs']=true; $checks['Selected gender exists']=true; $voices=[];
 foreach($c['languages'] as $code=>$block){
   if(array_diff(array_keys($block['products']??[]),$skus)) $checks['Language product keys match SKUs']=false;
   if(isset($block['voice'])){ $voice=$block['voice']; if(!in_array($voice['selected'],['male','female'],true)||!isset($voice['options'][$voice['selected']])) $checks['Selected gender exists']=false;
    foreach($voice['options'] as $gender=>$option) $voices[]=['language'=>$code,'gender'=>$gender,'voice_id'=>$option['voice_id'],'status'=>str_starts_with($option['voice_id'],'REPLACE_')?'WARN / unusable placeholder':'configured'];
   }
 }
 $checks['Default product display names']=!array_filter($skus,fn($sku)=>empty($default['products'][$sku]['display_name']));
 $checks['Price visibility valid']=!array_diff(array_column($c['products'],'price_visibility'),['owner_only','customer_ok','never_show']);
 $statuses=$c['statuses'];$expected=StatusFlow::CODES;sort($statuses);sort($expected);$checks['Exactly six agreed statuses']=$statuses===$expected;
 $checks['Status flow uses agreed codes']=!array_diff(array_merge(array_keys($c['status_flow']),...array_values($c['status_flow'])),StatusFlow::CODES);
 $checks['Address must_have subset']=!array_diff($c['required_fields']['address']['must_have'],$c['required_fields']['address']['parts']);
 $s=$db->prepare('SELECT config_json FROM shops WHERE shop_id=?');$s->execute([$id]); $registry=json_decode($s->fetchColumn(),true);$checks['DB registry matches stripped config']=$registry===$c;
 $resolved=[];foreach($c['enabled_languages'] as $code)$resolved[$code]=(new VoiceResolver())->resolve($c,$code);
 $result[$id]=['shop_name'=>$c['shop_name'],'checks'=>array_map(fn($ok)=>$ok?'PASS':'FAIL',$checks),'validator_errors'=>$v->errors,'validator_warnings'=>$v->warnings,'voices'=>$voices,'resolved'=>$resolved];
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
