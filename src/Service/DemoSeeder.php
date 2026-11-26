<?php
namespace VoiceShop\Service;
use VoiceShop\Config\ShopConfigRepositoryInterface;
final class DemoSeeder
{
    public function __construct(private \PDO $db, private ShopConfigRepositoryInterface $configs) {}
    public static function sample(string $shop, bool $missing = false, string $call = 'SAMPLE-0001'): array
    {
        $grocery = $shop === 'grocery_store_02';
        return ['shop_id' => $shop, 'call_id' => $call, 'language' => $grocery ? 'en-IN' : 'ta-IN', 'customer' => ['name' => 'டெமோ குமார் (fictional)', 'phone' => $missing ? '' : '0000000000', 'address' => $missing ? [] : ['house_no' => 'DEMO 1', 'street' => 'மாதிரி தெரு', 'area' => 'Demo area', 'landmark' => 'Fictional location', 'pincode' => '000000']], 'items' => [['sku' => $grocery ? 'rice_ponni' : 'masala_puri', 'qty' => 2, 'unit' => $grocery ? 'kg' : 'plate', 'options' => (object)[], 'raw_text' => 'Demo order']], 'delivery_choice' => 'pickup', 'payment_choice' => 'upi', 'requested_time' => '', 'missing_fields' => [], 'flags' => []];
    }
    public function run(): array
    {
        $messages = [];
        foreach ([1,2,3,4] as $n) {
            $p = self::sample($n < 3 ? 'panipuri_stall_01' : 'grocery_store_02', $n === 2, sprintf('DEMO-%04d', $n));
            // PHP payload has JSON-object options converted to associative arrays by the HTTP decoder.
            $p = json_decode(json_encode($p, JSON_UNESCAPED_UNICODE), true);
            if ($n === 3) {
                $p['customer']['phone'] = ''; $p['customer']['address']['pincode'] = '';
                $p['items'][] = ['sku' => 'unknown_demo', 'qty' => 1, 'unit' => 'item', 'options' => [], 'raw_text' => 'Fictional special snack'];
            }
            if ($n === 4) { $p['language'] = 'ta-IN'; }
            $r = (new OrderIntakeService($this->db, $this->configs))->ingest($p, true);
            if ($r->code >= 400) { throw new \RuntimeException($r->message); }
            if ($n === 4 && $r->code === 201) { (new OrderStatusService($this->db, $this->configs))->transition((int)$r->order['id'], 'CONFIRMED', true); }
            $messages[] = $p['call_id'] . ': ' . $r->message;
        }
        return $messages;
    }
}
