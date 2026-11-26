<?php
namespace VoiceShop\Service;
use VoiceShop\Config\ShopConfigRepositoryInterface;
use VoiceShop\Repository\{OrderRepository,OrderItemRepository};
final class OrderEditService
{
    public function __construct(private \PDO $db, private ShopConfigRepositoryInterface $configs) {}
    public function save(int $id, array $input): void
    {
        $this->db->beginTransaction();
        try {
            $orders = new OrderRepository($this->db); $items = new OrderItemRepository($this->db);
            $o = $orders->find($id, true) ?? throw new \InvalidArgumentException('Order not found.');
            $c = $this->configs->load($o['shop_id']); $stored = $items->forOrder($id);
            $p = ['shop_id' => $o['shop_id'], 'call_id' => $o['call_id'], 'language' => $o['language'], 'customer' => $input['customer'] ?? [], 'delivery_choice' => $input['delivery_choice'] ?? '', 'payment_choice' => $input['payment_choice'] ?? '', 'requested_time' => $input['requested_time'] ?? '', 'items' => []];
            foreach ($stored as $i) {
                $edit = $input['items'][$i['id']] ?? [];
                if (!is_array($edit)) { throw new \InvalidArgumentException('Invalid item edit.'); }
                if ($i['is_unknown_item'] && ($edit['unit'] ?? '') !== $i['unit']) { throw new \InvalidArgumentException('Unknown item unit must retain its imported value.'); }
                $p['items'][] = ['sku' => $i['sku'], 'qty' => $edit['qty'] ?? null, 'unit' => $edit['unit'] ?? '', 'raw_text' => $i['raw_text'], 'options' => json_decode($i['options_json'], true)];
            }
            $p = (new PayloadValidator())->normalize($c, $p);
            // Known fields are recomputed; extraction-only notes need explicit acknowledgement.
            $known = array_merge(['customer_name','phone'], array_map(fn($part) => 'address.' . $part, $c['required_fields']['address']['parts']));
            $ack = is_array($input['resolve_missing'] ?? null) ? $input['resolve_missing'] : [];
            $p['missing_fields'] = array_values(array_diff(json_decode($o['missing_fields_json'], true), $known, $ack));
            $missing = (new MissingFieldsDetector())->detect($c, $p);
            $orders->edit($id, $p, $missing);
            foreach ($stored as $index => $i) { $items->edit((int)$i['id'], $id, $p['items'][$index]['qty'], $p['items'][$index]['unit']); }
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }
}
